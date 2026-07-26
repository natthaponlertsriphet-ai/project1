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

// GET: List all beers
export async function GET(req: NextRequest) {
  try {
    const beers = await prisma.beer.findMany({
      orderBy: { tapNumber: "asc" },
    });
    return NextResponse.json(beers);
  } catch (error) {
    return NextResponse.json({ error: "Failed to fetch beers" }, { status: 500 });
  }
}

// POST: Create a beer (Admin Only)
export async function POST(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { tapNumber, name, type, abv, ibu, description, price, active } = body;

    if (!tapNumber || !name || !type || !abv || !price) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const existing = await prisma.beer.findUnique({ where: { tapNumber } });
    if (existing) {
      return NextResponse.json({ error: "Tap number already exists" }, { status: 400 });
    }

    const beer = await prisma.beer.create({
      data: {
        tapNumber,
        name,
        type,
        abv,
        ibu: ibu || "N/A",
        description: description || "",
        price: parseFloat(price),
        active: active !== undefined ? active : true,
      },
    });

    return NextResponse.json(beer);
  } catch (error: any) {
    console.error("Beer Create Error:", error);
    return NextResponse.json({ error: "Failed to create beer" }, { status: 500 });
  }
}

// PUT: Update a beer (Admin Only)
export async function PUT(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing beer id" }, { status: 400 });
    }

    const body = await req.json();
    const { tapNumber, name, type, abv, ibu, description, price, active } = body;

    const beer = await prisma.beer.update({
      where: { id },
      data: {
        tapNumber,
        name,
        type,
        abv,
        ibu,
        description,
        price: price ? parseFloat(price) : undefined,
        active,
      },
    });

    return NextResponse.json(beer);
  } catch (error) {
    console.error("Beer Update Error:", error);
    return NextResponse.json({ error: "Failed to update beer" }, { status: 500 });
  }
}

// DELETE: Delete a beer (Admin Only)
export async function DELETE(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing beer id" }, { status: 400 });
    }

    await prisma.beer.delete({
      where: { id },
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Beer Delete Error:", error);
    return NextResponse.json({ error: "Failed to delete beer" }, { status: 500 });
  }
}
