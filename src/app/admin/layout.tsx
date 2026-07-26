"use client";

import { useState, useEffect } from "react";
import { useRouter, usePathname } from "next/navigation";
import Link from "next/link";
import {
  LayoutDashboard,
  Users,
  Grid,
  Tag,
  Music,
  Beer,
  LogOut,
  ChevronRight,
} from "lucide-react";

export default function AdminLayout({
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

        if (!res.ok || !data.authenticated || data.user.role !== "ADMIN") {
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

  const menuItems = [
    { label: "Dashboard", path: "/admin", icon: LayoutDashboard },
    { label: "Staff management", path: "/admin/staff", icon: Users },
    { label: "Tables / Seating", path: "/admin/tables", icon: Grid },
    { label: "Promotions", path: "/admin/promotions", icon: Tag },
    { label: "Live Music", path: "/admin/music", icon: Music },
    { label: "Beer Tap List", path: "/admin/beers", icon: Beer },
  ];

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-[#121414] text-[#ffd700] font-mono">
        LOADING ADMIN CONSOLE...
      </div>
    );
  }

  return (
    <div className="flex h-screen bg-[#121414] text-[#e3e2e2] font-['Hanken_Grotesk'] overflow-hidden">
      {/* Sidebar */}
      <aside className="w-64 border-r-2 border-[#4d4732] bg-[#0d0e0f] flex flex-col shrink-0">
        {/* Brand */}
        <div className="h-20 border-b border-[#4d4732]/30 flex items-center px-6 gap-3">
          <div className="w-2 h-6 bg-[#ffd700]"></div>
          <span className="font-['Anton'] text-[20px] uppercase tracking-wider text-[#ffd700]">
            Admin Control
          </span>
        </div>

        {/* Menu Links */}
        <nav className="flex-1 py-6 flex flex-col gap-1 px-4 overflow-y-auto">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const active = pathname === item.path;
            return (
              <Link
                key={item.path}
                href={item.path}
                className={`flex items-center gap-3 px-4 py-3 font-bold uppercase text-[13px] tracking-wider transition-colors border ${
                  active
                    ? "bg-[#ffd700]/10 border-[#ffd700] text-[#ffd700]"
                    : "border-transparent text-[#d0c6ab] hover:text-[#ffd700] hover:bg-[#1f2020]"
                }`}
              >
                <Icon className="w-4 h-4 shrink-0" />
                <span className="flex-grow">{item.label}</span>
                {active && <ChevronRight className="w-4 h-4 text-[#ffd700]" />}
              </Link>
            );
          })}
        </nav>

        {/* Footer info */}
        <div className="p-4 border-t border-[#4d4732]/30 flex flex-col gap-2 bg-[#121414]">
          <div className="text-[11px] font-mono text-[#605f5e] uppercase">Logged in as:</div>
          <div className="text-sm font-bold uppercase truncate text-[#ffd700]">{user?.name}</div>
          <button
            onClick={handleLogout}
            className="mt-2 w-full bg-transparent border border-red-500 hover:bg-red-500 hover:text-[#121414] text-red-400 py-2 font-bold uppercase text-xs transition-colors flex items-center justify-center gap-2"
          >
            <LogOut className="w-3.5 h-3.5" /> Sign Out
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Header */}
        <header className="h-20 border-b border-[#4d4732]/30 flex items-center justify-between px-8 bg-[#0d0e0f] shrink-0">
          <h1 className="font-['Anton'] text-[24px] uppercase text-[#ffd700] m-0 tracking-wider">
            {menuItems.find((m) => m.path === pathname)?.label || "Admin Console"}
          </h1>
          <div className="flex items-center gap-4">
            <Link
              href="/"
              className="text-[#d0c6ab] hover:text-[#ffd700] text-xs font-bold uppercase tracking-wider border border-[#4d4732] px-3 py-1.5 hover:bg-[#1f2020] transition-all"
            >
              Customer View
            </Link>
            <div className="text-right">
              <div className="text-xs text-[#605f5e] font-mono uppercase">Zone</div>
              <div className="text-xs font-bold uppercase text-[#e3e2e2]">Chiang Mai Taproom</div>
            </div>
          </div>
        </header>

        {/* Dynamic page content */}
        <main className="flex-1 overflow-y-auto p-8 bg-[#121414] relative">
          {children}
        </main>
      </div>
    </div>
  );
}
