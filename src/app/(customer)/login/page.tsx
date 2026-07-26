"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Check if already logged in, redirect accordingly
    async function checkSession() {
      const res = await fetch("/api/auth/session");
      if (res.ok) {
        const data = await res.json();
        if (data.authenticated) {
          if (data.user.role === "ADMIN") {
            router.push("/admin");
          } else {
            router.push("/staff");
          }
        }
      }
    }
    checkSession();
  }, [router]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const res = await fetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || "Login failed");
      }

      if (data.user.role === "ADMIN") {
        router.push("/admin");
      } else {
        router.push("/staff");
      }
    } catch (err: any) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex items-center justify-center min-h-[calc(100vh-200px)] px-5 py-12 bg-[#121414]">
      <div className="w-full max-w-md bg-[#1b1c1c] border-2 border-[#4d4732] p-8 relative flex flex-col gap-6">
        
        {/* Top Accent bar */}
        <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>

        <div className="flex flex-col gap-2 text-center">
          <h1 className="font-['Anton'] text-[36px] uppercase tracking-wider text-[#ffd700] m-0">
            Console Sign In
          </h1>
          <p className="font-['Hanken_Grotesk'] text-[#d0c6ab] text-sm m-0">
            Access credentials restricted to Admin and Staff members.
          </p>
        </div>

        {error && (
          <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm">
            {error}
          </div>
        )}

        <form onSubmit={handleLogin} className="flex flex-col gap-6">
          <div className="flex flex-col gap-2">
            <label className="text-[12px] font-bold uppercase text-[#d0c6ab] tracking-wider" htmlFor="email">
              Email Address
            </label>
            <input
              id="email"
              type="email"
              placeholder="e.g., admin@chithole.com"
              required
              className="w-full bg-[#121414] text-[#e3e2e2] p-4 border-2 border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none transition-colors"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div className="flex flex-col gap-2">
            <label className="text-[12px] font-bold uppercase text-[#d0c6ab] tracking-wider" htmlFor="password">
              Password
            </label>
            <input
              id="password"
              type="password"
              placeholder="••••••••"
              required
              className="w-full bg-[#121414] text-[#e3e2e2] p-4 border-2 border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none transition-colors"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-[#ffd700] text-[#121414] font-['Anton'] text-[20px] uppercase py-4 border-2 border-[#ffd700] hover:bg-transparent hover:text-[#ffd700] transition-colors disabled:opacity-50"
          >
            {loading ? "Verifying..." : "Sign In"}
          </button>
        </form>

        <div className="border-t border-[#4d4732]/30 pt-4 text-center">
          <p className="text-[11px] font-mono text-[#605f5e] uppercase">
            Default Admin: admin@chithole.com / admin123
            <br />
            Default Staff: staff@chithole.com / staff123
          </p>
        </div>
      </div>
    </div>
  );
}
