import { NextRequest, NextResponse } from "next/server";
import { prisma } from "@/lib/prisma";
import { decryptSession, hashPassword } from "@/lib/auth";

function getAdminSession(req: NextRequest) {
  const sessionCookie = req.cookies.get("session");
  if (!sessionCookie) return null;
  const payload = decryptSession(sessionCookie.value);
  if (!payload || payload.role !== "ADMIN") return null;
  return payload;
}

// GET: List all users/staff (Admin Only)
export async function GET(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const staffList = await prisma.user.findMany({
      select: {
        id: true,
        email: true,
        name: true,
        role: true,
        createdAt: true,
      },
      orderBy: { role: "asc" }
    });

    return NextResponse.json(staffList);
  } catch (error) {
    return NextResponse.json({ error: "Failed to fetch staff" }, { status: 500 });
  }
}

// POST: Create a staff member (Admin Only)
export async function POST(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const body = await req.json();
    const { email, password, name, role } = body;

    if (!email || !password || !name || !role) {
      return NextResponse.json({ error: "Missing required fields" }, { status: 400 });
    }

    const existing = await prisma.user.findUnique({ where: { email } });
    if (existing) {
      return NextResponse.json({ error: "Email already registered" }, { status: 400 });
    }

    const passwordHash = hashPassword(password);

    const user = await prisma.user.create({
      data: {
        email,
        passwordHash,
        name,
        role
      },
      select: {
        id: true,
        email: true,
        name: true,
        role: true,
      }
    });

    return NextResponse.json(user);
  } catch (error) {
    console.error("Staff Create Error:", error);
    return NextResponse.json({ error: "Failed to create staff" }, { status: 500 });
  }
}

// PUT: Edit a staff member (Admin Only)
export async function PUT(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing staff id" }, { status: 400 });
    }

    const body = await req.json();
    const { email, name, role, password } = body;

    const data: any = {
      email,
      name,
      role
    };

    if (password && password.trim() !== "") {
      data.passwordHash = hashPassword(password);
    }

    const user = await prisma.user.update({
      where: { id },
      data,
      select: {
        id: true,
        email: true,
        name: true,
        role: true,
      }
    });

    return NextResponse.json(user);
  } catch (error) {
    console.error("Staff Update Error:", error);
    return NextResponse.json({ error: "Failed to update staff" }, { status: 500 });
  }
}

// DELETE: Delete a staff member (Admin Only)
export async function DELETE(req: NextRequest) {
  try {
    const admin = getAdminSession(req);
    if (!admin) {
      return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
    }

    const id = req.nextUrl.searchParams.get("id");
    if (!id) {
      return NextResponse.json({ error: "Missing staff id" }, { status: 400 });
    }

    // Prevent deleting self
    if (admin.userId === id) {
      return NextResponse.json({ error: "Cannot delete your own account" }, { status: 400 });
    }

    await prisma.user.delete({
      where: { id }
    });

    return NextResponse.json({ success: true });
  } catch (error) {
    console.error("Staff Delete Error:", error);
    return NextResponse.json({ error: "Failed to delete staff" }, { status: 500 });
  }
}
