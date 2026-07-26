"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { LogOut } from "lucide-react";

export default function Header() {
  const pathname = usePathname();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [user, setUser] = useState<{ name: string; role: string } | null>(null);

  useEffect(() => {
    async function checkSession() {
      try {
        const res = await fetch("/api/auth/session");
        if (res.ok) {
          const data = await res.json();
          if (data.authenticated) {
            setUser(data.user);
          }
        }
      } catch (e) {
        console.error(e);
      }
    }
    checkSession();
  }, [pathname]);

  const handleLogout = async () => {
    await fetch("/api/auth/logout", { method: "POST" });
    window.location.href = "/";
  };

  const navLinks = [
    { label: "Home & Booking", path: "/reservation" },
    { label: "Beer Menu", path: "/tap-list" },
    { label: "Promotions", path: "/promotions" },
    { label: "Gallery", path: "/live-music" },
  ];

  const isActive = (path: string) => {
    return pathname === path;
  };

  return (
    <header className="fixed top-0 w-full z-50 bg-[#131313]/90 backdrop-blur-xl border-b border-white/10">
      <div className="h-20 w-full px-6 lg:px-16 flex items-center justify-between">
        {/* Brand Logo */}
        <Link href="/" className="flex items-center gap-4 hover:opacity-90">
          <div className="w-12 h-12 bg-[#ffd782] flex items-center justify-center rounded-lg shadow-[0_0_15px_rgba(255,215,130,0.3)]">
            <span className="material-symbols-outlined text-[#3f2e00] text-[28px] font-bold">sports_bar</span>
          </div>
          <div className="flex flex-col">
            <span className="font-['Anton'] text-[24px] leading-none text-[#ffd782] uppercase tracking-wider">
              CHIT HOLE
            </span>
            <span className="font-mono text-[11px] tracking-wider text-[#d3c5ac] uppercase">
              Chiang Mai Brewing
            </span>
          </div>
        </Link>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center gap-10 h-full">
          {navLinks.map((link) => (
            <Link
              key={link.path}
              href={link.path}
              className={`h-full flex items-center font-mono text-[13px] font-bold uppercase transition-all tracking-wider ${
                isActive(link.path)
                  ? "text-[#ffd782] border-b-2 border-[#ffd782]"
                  : "text-[#d3c5ac] hover:text-[#ffd782]"
              }`}
            >
              {link.label}
            </Link>
          ))}
        </nav>

        {/* User Session Actions */}
        <div className="flex items-center gap-6">
          <div className="hidden lg:flex flex-col items-end">
            <span className="font-mono text-[11px] text-[#d3c5ac] uppercase">Reservations</span>
            <span className="font-sans text-[15px] font-bold text-[#ffd782]">064 9546 616</span>
          </div>

          {user ? (
            <div className="flex items-center gap-3">
              <Link
                href={user.role === "ADMIN" ? "/admin" : "/staff"}
                className="hidden md:flex items-center gap-2 px-3 py-1.5 border border-[#ffd782]/40 bg-[#ffd782]/10 hover:bg-[#ffd782]/20 text-[#ffd782] font-mono text-[11px] font-bold uppercase tracking-wider transition-colors"
              >
                {user.role} Dashboard
              </Link>
              <div className="w-8 h-8 rounded-full bg-[#ffd782] flex items-center justify-center text-[#3f2e00] font-bold text-sm" title={user.name}>
                {user.name.charAt(0).toUpperCase()}
              </div>
              <button
                onClick={handleLogout}
                className="text-[#d3c5ac] hover:text-red-400 transition-colors p-1"
                title="Log Out"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          ) : (
            <Link
              href="/login"
              className="w-8 h-8 rounded-full bg-[#ffd782] flex items-center justify-center text-[#3f2e00] hover:bg-[#fff6df] transition-colors"
              title="Staff / Admin Sign In"
            >
              <span className="material-symbols-outlined text-[18px]">person</span>
            </Link>
          )}

          {/* Mobile Menu Button */}
          <button
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            className="md:hidden text-[#ffd782] flex items-center justify-center"
          >
            <span className="material-symbols-outlined text-[28px]">
              {mobileMenuOpen ? "close" : "menu"}
            </span>
          </button>
        </div>
      </div>

      {/* Mobile Navigation Dropdown */}
      {mobileMenuOpen && (
        <div className="md:hidden w-full bg-[#131313] border-b border-white/10 py-4 flex flex-col gap-3 items-center">
          {navLinks.map((link) => (
            <Link
              key={link.path}
              href={link.path}
              onClick={() => setMobileMenuOpen(false)}
              className={`font-mono text-[14px] font-bold uppercase py-2 w-full text-center tracking-wider ${
                isActive(link.path)
                  ? "text-[#ffd782] bg-[#201f1f]"
                  : "text-[#d3c5ac] hover:text-[#ffd782]"
              }`}
            >
              {link.label}
            </Link>
          ))}
          {user && (
            <Link
              href={user.role === "ADMIN" ? "/admin" : "/staff"}
              onClick={() => setMobileMenuOpen(false)}
              className="mt-2 px-6 py-2 bg-[#ffd782] text-[#3f2e00] font-mono font-bold uppercase tracking-wider text-xs"
            >
              {user.role} Console
            </Link>
          )}
        </div>
      )}
    </header>
  );
}
