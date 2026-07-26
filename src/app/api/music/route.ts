import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { decryptSession } from "@/lib/auth";

function getAdminSession(req: NextRequest) {
  const sessionCookie = req.cookies.get("session");
  if (!sessionCookie) return null;
  const payload = decryptSession(sessionCookie.value);
  if (!payload || payload.role !== "ADMIN") return null;
  return payload;
}

// GET: List all live music events
export async function GET(req: NextRequest) {
  try {
    const musicList = await prisma.liveMusic.findMany();
    // Sort custom order of days Mon-Sun for proper calendar view
    const dayOrder = { "Mon": 1, "Tue": 2, "Wed": 3, "Thu": 4, "Fri": 5, "Sat": 6, "Sun": 7 };
    musicList.sort((a, b) => {
      const orderA = dayOrder[a.day as keyof typeof dayOrder] || 99;
      const orderB = dayOrder[b.day as keyof typeof dayOrder] || 99;
      return orderA - orderB;
    });
    return NextResponse.json(musicList);
  } catch (error) {
    return NextResponse.json({ error: "Failed to fetch live music schedule" }, { status: 500 });
  }
}

// POST: Create live music schedule (Admin Only)
export async function POST(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { day, time, artist, description, status } = body;

    if (!day || !time || !artist || !description) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const liveMusic = await prisma.liveMusic.create({
      data: {
        day,
        time,
        artist,
        description,
        status: status || "REGULAR",
      },
    });

    return NextResponse.json(liveMusic);
  } catch (error) {
    console.error("LiveMusic Create Error:", error);
    return NextResponse.json({ error: "Failed to create live music event" }, { status: 500 });
  }
}

// PUT: Update live music schedule (Admin Only)
export async function PUT(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing live music id" }, { status: 400 });
    }

    const body = await req.json();
    const { day, time, artist, description, status } = body;

    const liveMusic = await prisma.liveMusic.update({
      where: { id },
      data: {
        day,
        time,
        artist,
        description,
        status,
      },
    });

    return NextResponse.json(liveMusic);
  } catch (error) {
    console.error("LiveMusic Update Error:", error);
    return NextResponse.json({ error: "Failed to update live music event" }, { status: 500 });
  }
}

// DELETE: Delete live music schedule (Admin Only)
export async function DELETE(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing live music id" }, { status: 400 });
    }

    await prisma.liveMusic.delete({
      where: { id },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("LiveMusic Delete Error:", error);
    return NextResponse.json({ error: "Failed to delete live music event" }, { status: 500 });
  }
}
