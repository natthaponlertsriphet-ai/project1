import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { decryptSession } from "@/lib/auth";

function getSessionUser(req: NextRequest) {
  const sessionCookie = req.cookies.get("session");
  if (!sessionCookie) return null;
  return decryptSession(sessionCookie.value);
}

// GET: Fetch bookings
// - Staff/Admin can fetch all bookings
// - Customers can fetch their own bookings by passing ?phone=xxx
export async function GET(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    const phone = req.nextUrl.searchParams.get("phone");

    // If customer querying by phone
    if (phone) {
      const bookings = await prisma.booking.findMany({
        where: { customerPhone: phone },
        include: { table: true },
        orderBy: { date: "desc" },
      });
      return NextResponse.json(bookings);
    }

    // Otherwise, require authentication (Staff or Admin)
    if (!user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const bookings = await prisma.booking.findMany({
      include: { table: true },
      orderBy: [
        { date: "desc" },
        { timeSlot: "asc" }
      ],
    });

    return NextResponse.json(bookings);
  } catch (error) {
    console.error("Booking Fetch Error:", error);
    return NextResponse.json({ error: "Failed to fetch bookings" }, { status: 500 });
  }
}

// POST: Create a new booking (Public or Staff)
export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const { customerName, customerPhone, date, timeSlot, pax, zonePreference } = body;

    if (!customerName || !customerPhone || !date || !timeSlot || !pax) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const guests = parseInt(pax);

    // 1. Get all tables that can accommodate the pax
    const candidateTables = await prisma.table.findMany({
      where: {
        capacity: { gte: guests },
        zone: zonePreference ? zonePreference : undefined,
      },
      orderBy: { capacity: "asc" }, // Prefer smallest table first
    });

    if (candidateTables.length === 0) {
      return NextResponse.json(
        { error: "No tables match your party size or zone preference" },
        { status: 400 }
      );
    }

    // 2. Find which candidate tables are already booked on this date/timeslot
    const activeBookings = await prisma.booking.findMany({
      where: {
        date,
        timeSlot,
        status: { in: ["PENDING", "CONFIRMED"] },
        tableId: { in: candidateTables.map((t) => t.id) },
      },
      select: { tableId: true },
    });

    const bookedTableIds = new Set(activeBookings.map((b) => b.tableId));

    // 3. Find first free table
    const freeTable = candidateTables.find((t) => !bookedTableIds.has(t.id));

    if (!freeTable) {
      return NextResponse.json(
        { error: "All tables matching your request are fully booked for this date and time" },
        { status: 400 }
      );
    }

    // 4. Create booking
    const booking = await prisma.booking.create({
      data: {
        customerName,
        customerPhone,
        date,
        timeSlot,
        pax: guests,
        tableId: freeTable.id,
        status: "PENDING", // Customer bookings start as pending approval
      },
      include: { table: true },
    });

    return NextResponse.json({
      success: true,
      booking,
      message: "Booking submitted successfully! Waiting for staff confirmation."
    });
  } catch (error) {
    console.error("Booking Create Error:", error);
    return NextResponse.json({ error: "Failed to create booking" }, { status: 500 });
  }
}

// PATCH: Update booking status (Staff/Admin) or cancel booking (Customer/Staff)
export async function PATCH(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    const body = await req.json();
    const { bookingId, status, tableId } = body;

    if (!bookingId || !status) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    // Find the booking first
    const booking = await prisma.booking.findUnique({
      where: { id: bookingId },
      include: { table: true }
    });

    if (!booking) {
      return NextResponse.json({ error: "Booking not found" }, { status: 404 });
    }

    // If customer attempts to cancel, they don't need auth, but status can ONLY be "CANCELLED"
    if (!user) {
      if (status !== "CANCELLED") {
        return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
      }
    }

    // Update the booking status
    const updatedBooking = await prisma.booking.update({
      where: { id: bookingId },
      data: {
        status,
        tableId: tableId || undefined
      },
      include: { table: true }
    });

    // Simulate LINE OA Notification
    let lineMessage = "";
    if (status === "CONFIRMED") {
      lineMessage = `สวัสดีคุณ ${updatedBooking.customerName}, การจองโต๊ะหมายเลข ${updatedBooking.table?.number || "N/A"} (โซน ${updatedBooking.table?.zone || "N/A"}) วันที่ ${updatedBooking.date} เวลา ${updatedBooking.timeSlot} น. สำหรับ ${updatedBooking.pax} ท่าน ได้รับการยืนยันเรียบร้อยแล้วค่ะ! ขอบคุณที่เลือก CHIT HOLE Brewing Chiang Mai`;
    } else if (status === "CANCELLED") {
      lineMessage = `สวัสดีคุณ ${updatedBooking.customerName}, การจองของท่านในวันที่ ${updatedBooking.date} เวลา ${updatedBooking.timeSlot} น. ได้รับการยกเลิกเรียบร้อยแล้วค่ะ หวังว่าจะได้ให้บริการท่านในโอกาสหน้าค่ะ`;
    }

    return NextResponse.json({
      success: true,
      booking: updatedBooking,
      lineNotificationSent: lineMessage !== "",
      lineMessage
    });
  } catch (error) {
    console.error("Booking Update Error:", error);
    return NextResponse.json({ error: "Failed to update booking" }, { status: 500 });
  }
}
