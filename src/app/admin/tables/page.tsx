"use client";

import { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, Grid, Layers } from "lucide-react";

export default function TablesManagement() {
  const [tables, setTables] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [number, setNumber] = useState("");
  const [zone, setZone] = useState("INDOOR");
  const [capacity, setCapacity] = useState("4");
  const [saving, setSaving] = useState(false);

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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setError(null);

    const url = isEditing ? `/api/tables?id=${editId}` : "/api/tables";
    const method = isEditing ? "PUT" : "POST";
    const payload = { number, zone, capacity: parseInt(capacity) };

    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to save table");

      // Success
      setNumber("");
      setCapacity("4");
      setZone("INDOOR");
      setIsEditing(false);
      setEditId(null);
      fetchTables();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEdit = (table: any) => {
    setNumber(table.number);
    setZone(table.zone);
    setCapacity(table.capacity.toString());
    setEditId(table.id);
    setIsEditing(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this table? Bookings attached to this table will lose table assignments.")) return;
    setError(null);

    try {
      const res = await fetch(`/api/tables?id=${id}`, { method: "DELETE" });
      const data = await res.json();

      if (!res.ok) throw new Error(data.error || "Failed to delete table");

      fetchTables();
    } catch (err: any) {
      setError(err.message);
    }
  };

  // Group tables by zone
  const indoorTables = tables.filter((t) => t.zone === "INDOOR");
  const outdoorTables = tables.filter((t) => t.zone === "OUTDOOR");
  const stageTables = tables.filter((t) => t.zone === "STAGE");

  return (
    <div className="flex flex-col gap-8 w-full max-w-[1200px]">
      
      {error && (
        <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm">
          {error}
        </div>
      )}

      {/* Seating Layout Quick Visualizer Map */}
      <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
        <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
        <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
          <Layers className="w-5 h-5" />
          Chiang Mai Taproom Floor Seating Map
        </h2>

        {loading ? (
          <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading layout...</div>
        ) : (
          <div className="flex flex-col gap-8">
            {/* Zones Map Layout Grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {/* Indoor Zone */}
              <div className="bg-[#121414] border border-[#4d4732] p-4 flex flex-col gap-4">
                <div className="text-xs font-bold uppercase tracking-widest text-[#ffd700] border-b border-[#4d4732]/30 pb-2">
                  Indoor Zone (Air-Con Room)
                </div>
                <div className="grid grid-cols-4 gap-3">
                  {indoorTables.length === 0 ? (
                    <div className="col-span-4 text-center text-xs font-mono text-[#605f5e] py-6">No tables</div>
                  ) : (
                    indoorTables.map((t) => (
                      <div
                        key={t.id}
                        onClick={() => handleEdit(t)}
                        className="bg-[#1f2020] border-2 border-[#343535] hover:border-[#ffd700] hover:bg-[#292a2a] transition-all p-3 text-center cursor-pointer select-none"
                      >
                        <div className="font-['Anton'] text-sm text-[#ffd700]">{t.number}</div>
                        <div className="text-[10px] font-mono text-[#605f5e]">{t.capacity}P</div>
                      </div>
                    ))
                  )}
                </div>
              </div>

              {/* Outdoor Zone */}
              <div className="bg-[#121414] border border-[#4d4732] p-4 flex flex-col gap-4">
                <div className="text-xs font-bold uppercase tracking-widest text-[#ffd700] border-b border-[#4d4732]/30 pb-2">
                  Outdoor Zone (Open-Air Garden)
                </div>
                <div className="grid grid-cols-4 gap-3">
                  {outdoorTables.length === 0 ? (
                    <div className="col-span-4 text-center text-xs font-mono text-[#605f5e] py-6">No tables</div>
                  ) : (
                    outdoorTables.map((t) => (
                      <div
                        key={t.id}
                        onClick={() => handleEdit(t)}
                        className="bg-[#1f2020] border-2 border-[#343535] hover:border-[#ffd700] hover:bg-[#292a2a] transition-all p-3 text-center cursor-pointer select-none"
                      >
                        <div className="font-['Anton'] text-sm text-[#ffd700]">{t.number}</div>
                        <div className="text-[10px] font-mono text-[#605f5e]">{t.capacity}P</div>
                      </div>
                    ))
                  )}
                </div>
              </div>

              {/* Stage View Zone */}
              <div className="bg-[#121414] border border-[#4d4732] p-4 flex flex-col gap-4">
                <div className="text-xs font-bold uppercase tracking-widest text-[#ffd700] border-b border-[#4d4732]/30 pb-2">
                  Stage Zone (Band Spotlights)
                </div>
                <div className="grid grid-cols-4 gap-3">
                  {stageTables.length === 0 ? (
                    <div className="col-span-4 text-center text-xs font-mono text-[#605f5e] py-6">No tables</div>
                  ) : (
                    stageTables.map((t) => (
                      <div
                        key={t.id}
                        onClick={() => handleEdit(t)}
                        className="bg-[#1f2020] border-2 border-red-950 hover:border-[#ffd700] hover:bg-[#292a2a] transition-all p-3 text-center cursor-pointer select-none"
                      >
                        <div className="font-['Anton'] text-sm text-[#ffd700]">{t.number}</div>
                        <div className="text-[10px] font-mono text-red-400">{t.capacity}P</div>
                      </div>
                    ))
                  )}
                </div>
              </div>
            </div>
            
            <div className="text-[11px] font-mono text-[#605f5e] uppercase">
              * Click any table in the seating map above to quickly load its properties for editing below.
            </div>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left Column: Form to Add/Edit Table */}
        <div className="lg:col-span-4 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider">
            {isEditing ? "Edit Table Properties" : "Create Table Node"}
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Table Number / Label</label>
              <input
                type="text"
                placeholder="e.g., T9 or S5"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors uppercase"
                value={number}
                onChange={(e) => setNumber(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Layout Zone</label>
              <select
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={zone}
                onChange={(e) => setZone(e.target.value)}
              >
                <option value="INDOOR">INDOOR (โซนในห้องแอร์)</option>
                <option value="OUTDOOR">OUTDOOR (โซนรับลมสวนหย่อม)</option>
                <option value="STAGE">STAGE (โซนหน้าเวทีดนตรีสด)</option>
              </select>
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Max Capacity (Guests)</label>
              <input
                type="number"
                min="1"
                max="30"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={capacity}
                onChange={(e) => setCapacity(e.target.value)}
              />
            </div>

            <div className="flex gap-3 mt-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-grow bg-[#ffd700] text-[#121414] hover:bg-[#fff6df] font-bold uppercase py-3 text-xs tracking-wider transition-colors disabled:opacity-50"
              >
                {saving ? "Saving..." : isEditing ? "Update Table" : "Add Table"}
              </button>
              {isEditing && (
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setNumber("");
                    setCapacity("4");
                    setZone("INDOOR");
                    setEditId(null);
                  }}
                  className="px-4 bg-[#343535] text-[#e3e2e2] font-bold uppercase py-3 text-xs tracking-wider hover:bg-[#292a2a] transition-colors"
                >
                  Cancel
                </button>
              )}
            </div>
          </form>
        </div>

        {/* Right Column: Tables Inventory List */}
        <div className="lg:col-span-8 bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
            <Grid className="w-5 h-5" />
            Registered Tables Seating Inventory ({tables.length})
          </h2>

          {loading ? (
            <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading inventory...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left font-mono text-xs">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-3">Table #</th>
                    <th className="p-3">Layout Zone</th>
                    <th className="p-3">Capacity</th>
                    <th className="p-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {tables.map((t) => (
                    <tr key={t.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-3 font-sans text-sm font-bold text-[#ffd700]">{t.number}</td>
                      <td className="p-3 text-[#d0c6ab]">{t.zone}</td>
                      <td className="p-3 text-[#e3e2e2]">{t.capacity} Guests</td>
                      <td className="p-3 text-center">
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleEdit(t)}
                            className="p-1 text-[#d0c6ab] hover:text-[#ffd700] transition-colors"
                            title="Edit Table"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(t.id)}
                            className="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Delete Table"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
