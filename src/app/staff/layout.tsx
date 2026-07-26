"use client";

import { useState, useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import Link from "next/link";
import { LogOut, ClipboardList, Map, HelpCircle } from "lucide-react";

export default function StaffLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const [loading, setLoading] = useState(true);
  const [user, setUser] = useState<any>(null);

  useEffect(() => {
    async function checkAuth() {
      try {
        const res = await fetch("/api/auth/session");
        const data = await res.json();

        // Staff layout allows both STAFF and ADMIN roles
        if (!res.ok || !data.authenticated || (data.user.role !== "STAFF" && data.user.role !== "ADMIN")) {
          router.push("/login");
        } else {
          setUser(data.user);
          setLoading(false);
        }
      } catch (e) {
        router.push("/login");
      }
    }
    checkAuth();
  }, [router, pathname]);

  const handleLogout = async () => {
    await fetch("/api/auth/logout", { method: "POST" });
    router.push("/login");
  };

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-[#121414] text-[#ffd700] font-mono">
        LOADING STAFF CONSOLE...
      </div>
    );
  }

  return (
    <div className="flex flex-col h-screen bg-[#121414] text-[#e3e2e2] font-['Hanken_Grotesk'] overflow-hidden">
      {/* Top Navbar */}
      <header className="h-20 bg-[#0d0e0f] border-b-2 border-[#4d4732] flex items-center justify-between px-6 shrink-0 z-10">
        <Link href="/staff" className="flex items-center gap-3">
          <div className="w-2 h-6 bg-[#ffd700]"></div>
          <span className="font-['Anton'] text-[22px] uppercase tracking-wider text-[#ffd700]">
            Staff Console
          </span>
          <span className="text-[10px] border border-[#605f5e] px-1.5 py-0.5 rounded font-mono text-[#d0c6ab]">
            V1.0
          </span>
        </Link>

        {/* Navigation Tabs */}
        <nav className="hidden md:flex items-center gap-6 h-full font-bold uppercase text-[13px] tracking-wider">
          <Link
            href="/staff"
            className={`flex items-center gap-2 px-3 py-1.5 border-b-2 transition-all ${
              pathname === "/staff"
                ? "border-[#ffd700] text-[#ffd700]"
                : "border-transparent text-[#d0c6ab] hover:text-[#ffd700]"
            }`}
          >
            <ClipboardList className="w-4 h-4" /> Bookings List
          </Link>
          <Link
            href="/staff/layout-map"
            className={`flex items-center gap-2 px-3 py-1.5 border-b-2 transition-all ${
              pathname === "/staff/layout-map"
                ? "border-[#ffd700] text-[#ffd700]"
                : "border-transparent text-[#d0c6ab] hover:text-[#ffd700]"
            }`}
          >
            <Map className="w-4 h-4" /> Seating Layout Map
          </Link>
        </nav>

        {/* User profile & signout */}
        <div className="flex items-center gap-4">
          <div className="text-right hidden sm:block">
            <div className="text-[10px] font-mono text-[#605f5e] uppercase">Active Staff</div>
            <div className="text-sm font-bold uppercase text-[#ffd700]">{user?.name}</div>
          </div>
          <button
            onClick={handleLogout}
            className="bg-transparent border border-red-500/50 hover:bg-red-500 hover:text-[#121414] text-red-400 p-2 font-bold uppercase text-xs transition-colors flex items-center gap-2"
            title="Log Out"
          >
            <LogOut className="w-4 h-4" /> <span className="hidden md:inline">Sign Out</span>
          </button>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 overflow-y-auto p-6 md:p-8 bg-[#121414]">
        {children}
      </main>
    </div>
  );
}
