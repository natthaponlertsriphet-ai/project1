"use client";

import { useState, useEffect } from "react";
import { Grid, Layers, Armchair } from "lucide-react";

export default function SeatingLayoutMap() {
  const [tables, setTables] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [updatingId, setUpdatingId] = useState<string | null>(null);

  useEffect(() => {
    fetchTables();
  }, []);

  async function fetchTables() {
    try {
      const res = await fetch("/api/tables");
      if (res.ok) {
        const data = await res.json();
        setTables(data);
      } else {
        throw new Error("Failed to fetch tables list");
      }
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  const handleToggleStatus = async (tableId: string, currentStatus: string) => {
    setUpdatingId(tableId);
    setError(null);
    const nextStatus = currentStatus === "AVAILABLE" ? "OCCUPIED" : "AVAILABLE";

    try {
      const res = await fetch("/api/tables", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id: tableId, status: nextStatus }),
      });

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.error || "Failed to update table status");
      }

      // Success - update local state
      setTables((prev) =>
        prev.map((t) => (t.id === tableId ? { ...t, status: nextStatus } : t))
      );
    } catch (err: any) {
      setError(err.message);
    } finally {
      setUpdatingId(null);
    }
  };

  const zones = [
    { key: "INDOOR", label: "Indoor Zone (ห้องแอร์)" },
    { key: "OUTDOOR", label: "Outdoor Zone (สวนหย่อม)" },
    { key: "STAGE", label: "Stage Zone (หน้าเวทีดนตรีสด)" }
  ];

  return (
    <div className="flex flex-col gap-8 w-full max-w-[1200px]">
      
      <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
        <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
        <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-3 tracking-wider flex items-center gap-2">
          <Grid className="w-5 h-5" />
          Real-time Seating & Table Availability Manager
        </h2>
        <p className="text-xs text-[#d0c6ab] mt-0.5 mb-6">
          Toggle table status between <strong className="text-[#ffd700]">AVAILABLE (โต๊ะว่าง)</strong> and <strong className="text-red-400">OCCUPIED (โต๊ะไม่ว่าง/ลูกค้าเข้าใช้แล้ว)</strong> by clicking on any table.
        </p>

        {error && (
          <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm mb-6">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading table mapping layout...</div>
        ) : (
          <div className="flex flex-col gap-8">
            {zones.map((zone) => {
              const zoneTables = tables.filter((t) => t.zone === zone.key);
              return (
                <div key={zone.key} className="bg-[#121414] border border-[#4d4732]/50 p-6 flex flex-col gap-4">
                  <div className="flex items-center gap-2 border-b border-[#4d4732]/30 pb-3">
                    <Layers className="w-4 h-4 text-[#ffd700]" />
                    <h3 className="text-sm font-bold uppercase tracking-wider text-[#ffd700] m-0">
                      {zone.label}
                    </h3>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4">
                    {zoneTables.length === 0 ? (
                      <div className="col-span-full text-center text-xs font-mono text-[#605f5e] py-6">
                        No tables in this zone.
                      </div>
                    ) : (
                      zoneTables.map((t) => {
                        const isOccupied = t.status === "OCCUPIED";
                        const isUpdating = updatingId === t.id;

                        return (
                          <div
                            key={t.id}
                            onClick={() => !isUpdating && handleToggleStatus(t.id, t.status)}
                            className={`border-2 p-4 text-center cursor-pointer select-none transition-all relative overflow-hidden flex flex-col items-center gap-2 ${
                              isOccupied
                                ? "bg-red-950/25 border-red-500/70 hover:border-red-400"
                                : "bg-green-950/15 border-green-700/60 hover:border-[#ffd700]"
                            } ${isUpdating ? "opacity-50" : ""}`}
                          >
                            <Armchair className={`w-6 h-6 ${isOccupied ? "text-red-500" : "text-green-500"}`} />
                            <div className="font-['Anton'] text-[18px] text-[#e3e2e2] leading-tight">
                              {t.number}
                            </div>
                            <div className="text-[10px] font-mono text-[#605f5e]">
                              {t.capacity} Pax
                            </div>
                            <div
                              className={`text-[9px] font-bold uppercase px-1.5 py-0.5 rounded font-mono ${
                                isOccupied
                                  ? "bg-red-900/30 text-red-400"
                                  : "bg-green-900/30 text-green-400"
                              }`}
                            >
                              {isOccupied ? "Occupied" : "Vacant"}
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
