import { NextRequest, NextResponse } from "next/server";
import { decryptSession } from "@/lib/auth";

export async function GET(req: NextRequest) {
  try {
    const sessionCookie = req.cookies.get("session");
    if (!sessionCookie) {
      return NextResponse.json({ authenticated: false }, { status: 401 });
    }

    const payload = decryptSession(sessionCookie.value);
    if (!payload) {
      return NextResponse.json({ authenticated: false }, { status: 401 });
    }

    return NextResponse.json({
      authenticated: true,
      user: {
        userId: payload.userId,
        email: payload.email,
        name: payload.name,
        role: payload.role,
      },
    });
  } catch (error) {
    console.error("Session API Error:", error);
    return NextResponse.json(
      { error: "Internal Server Error" },
      { status: 500 }
    );
  }
}
