import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { decryptSession } from "@/lib/auth";

function getSessionUser(req: NextRequest) {
  const sessionCookie = req.cookies.get("session");
  if (!sessionCookie) return null;
  return decryptSession(sessionCookie.value);
}

// GET: List all tables. Optionally check availability for a specific date/timeSlot
export async function GET(req: NextRequest) {
  try {
    const date = req.nextUrl.searchParams.get("date");
    const timeSlot = req.nextUrl.searchParams.get("timeSlot");

    const tables = await prisma.table.findMany({
      orderBy: { number: "asc" },
    });

    if (date) {
      // Find occupied / reserved tables for this date and time
      const activeBookings = await prisma.booking.findMany({
        where: {
          date,
          timeSlot: timeSlot || undefined,
          status: { in: ["CONFIRMED", "PENDING"] },
        },
        select: { tableId: true },
      });

      const reservedTableIds = new Set(
        activeBookings.map((b) => b.tableId).filter(Boolean)
      );

      const tablesWithAvailability = tables.map((t) => ({
        ...t,
        isReserved: reservedTableIds.has(t.id),
      }));

      return NextResponse.json(tablesWithAvailability);
    }

    return NextResponse.json(tables);
  } catch (error) {
    console.error("Tables Fetch Error:", error);
    return NextResponse.json({ error: "Failed to fetch tables" }, { status: 500 });
  }
}

// POST: Create a table (Admin Only)
export async function POST(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { number, zone, capacity } = body;

    if (!number || !zone || !capacity) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const existing = await prisma.table.findUnique({ where: { number } });
    if (existing) {
      return NextResponse.json({ error: "Table number already exists" }, { status: 400 });
    }

    const table = await prisma.table.create({
      data: {
        number,
        zone,
        capacity: parseInt(capacity),
      },
    });

    return NextResponse.json(table);
  } catch (error) {
    console.error("Table Create Error:", error);
    return NextResponse.json({ error: "Failed to create table" }, { status: 500 });
  }
}

// PUT: Update a table (Admin Only)
export async function PUT(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing table id" }, { status: 400 });
    }

    const body = await req.json();
    const { number, zone, capacity } = body;

    const table = await prisma.table.update({
      where: { id },
      data: {
        number,
        zone,
        capacity: capacity ? parseInt(capacity) : undefined,
      },
    });

    return NextResponse.json(table);
  } catch (error) {
    console.error("Table Update Error:", error);
    return NextResponse.json({ error: "Failed to update table" }, { status: 500 });
  }
}

// DELETE: Delete a table (Admin Only)
export async function DELETE(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    if (!user || user.role !== "ADMIN") {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing table id" }, { status: 400 });
    }

    // Unlink any bookings before deleting
    await prisma.booking.updateMany({
      where: { tableId: id },
      data: { tableId: null },
    });

    await prisma.table.delete({
      where: { id },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Table Delete Error:", error);
    return NextResponse.json({ error: "Failed to delete table" }, { status: 500 });
  }
}

// PATCH: Update table status (Staff or Admin)
export async function PATCH(req: NextRequest) {
  try {
    const user = getSessionUser(req);
    if (!user) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { id, status } = body;

    if (!id || !status) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const table = await prisma.table.update({
      where: { id },
      data: { status },
    });

    return NextResponse.json(table);
  } catch (error) {
    console.error("Table Patch Error:", error);
    return NextResponse.json({ error: "Failed to update table status" }, { status: 500 });
  }
}
