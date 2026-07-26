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

// GET: List all active/all promotions
export async function GET(req: NextRequest) {
  try {
    const showAll = req.nextUrl.searchParams.get("all") === "true";
    const promotions = await prisma.promotion.findMany({
      where: showAll ? {} : { active: true }
    });
    return NextResponse.json(promotions);
  } catch (error) {
    return NextResponse.json({ error: "Failed to fetch promotions" }, { status: 500 });
  }
}

// POST: Create a promotion (Admin Only)
export async function POST(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { title, description, offer, period, image, active } = body;

    if (!title || !description || !offer || !period) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const promotion = await prisma.promotion.create({
      data: {
        title,
        description,
        offer,
        period,
        image: image || "",
        active: active !== undefined ? active : true
      }
    });

    return NextResponse.json(promotion);
  } catch (error) {
    console.error("Promo Create Error:", error);
    return NextResponse.json({ error: "Failed to create promotion" }, { status: 500 });
  }
}

// PUT: Update a promotion (Admin Only)
export async function PUT(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing promotion id" }, { status: 400 });
    }

    const body = await req.json();
    const { title, description, offer, period, image, active } = body;

    const promotion = await prisma.promotion.update({
      where: { id },
      data: {
        title,
        description,
        offer,
        period,
        image,
        active
      }
    });

    return NextResponse.json(promotion);
  } catch (error) {
    console.error("Promo Update Error:", error);
    return NextResponse.json({ error: "Failed to update promotion" }, { status: 500 });
  }
}

// DELETE: Delete a promotion (Admin Only)
export async function DELETE(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing promotion id" }, { status: 400 });
    }

    await prisma.promotion.delete({
      where: { id }
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Promo Delete Error:", error);
    return NextResponse.json({ error: "Failed to delete promotion" }, { status: 500 });
  }
}
