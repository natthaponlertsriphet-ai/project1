import { NextRequest, NextResponse } from "next/server";

export async function GET(req: NextRequest) {
  const response = NextResponse.json({ success: true });
  response.cookies.delete("session");
  return response;
}

export async function POST(req: NextRequest) {
  const response = NextResponse.json({ success: true });
  response.cookies.delete("session");
  return response;
}
