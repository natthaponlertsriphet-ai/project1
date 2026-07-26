"use client";

import { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, Tag } from "lucide-react";

export default function PromotionsManagement() {
  const [promos, setPromos] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [offer, setOffer] = useState("");
  const [period, setPeriod] = useState("");
  const [image, setImage] = useState("");
  const [active, setActive] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchPromos();
  }, []);

  async function fetchPromos() {
    try {
      const res = await fetch("/api/promotions?all=true");
      if (res.ok) {
        const data = await res.json();
        setPromos(data);
      } else {
        throw new Error("Failed to fetch promotions list");
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

    const url = isEditing ? `/api/promotions?id=${editId}` : "/api/promotions";
    const method = isEditing ? "PUT" : "POST";
    const payload = { title, description, offer, period, image, active };

    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to save promotion");

      // Reset
      setTitle("");
      setDescription("");
      setOffer("");
      setPeriod("");
      setImage("");
      setActive(true);
      setIsEditing(false);
      setEditId(null);
      fetchPromos();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEdit = (promo: any) => {
    setTitle(promo.title);
    setDescription(promo.description);
    setOffer(promo.offer);
    setPeriod(promo.period);
    setImage(promo.image);
    setActive(promo.active);
    setEditId(promo.id);
    setIsEditing(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this promotion?")) return;
    setError(null);

    try {
      const res = await fetch(`/api/promotions?id=${id}`, { method: "DELETE" });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to delete promotion");
      fetchPromos();
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <div className="flex flex-col gap-8 w-full max-w-[1200px]">
      
      {error && (
        <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left Column: Form to Add/Edit Promotion */}
        <div className="lg:col-span-4 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider">
            {isEditing ? "Edit Promo Properties" : "Create Promotion Offer"}
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Promotion Title</label>
              <input
                type="text"
                placeholder="e.g., Happy Hour: Buy 1 Get 1"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Offer Badge</label>
                <input
                  type="text"
                  placeholder="e.g., BUY 1 GET 1"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={offer}
                  onChange={(e) => setOffer(e.target.value)}
                />
              </div>
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Period / Schedule</label>
                <input
                  type="text"
                  placeholder="e.g., Daily • 5PM - 7PM"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                />
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Image URL</label>
              <input
                type="text"
                placeholder="e.g., https://lh3... or empty"
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={image}
                onChange={(e) => setImage(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Description</label>
              <textarea
                placeholder="Double the impact..."
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors h-20 resize-none"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>

            <div className="flex items-center gap-2">
              <input
                id="active"
                type="checkbox"
                className="w-4 h-4 accent-[#ffd700] bg-[#121414]"
                checked={active}
                onChange={(e) => setActive(e.target.checked)}
              />
              <label htmlFor="active" className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider cursor-pointer">
                Active Offer
              </label>
            </div>

            <div className="flex gap-3 mt-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-grow bg-[#ffd700] text-[#121414] hover:bg-[#fff6df] font-bold uppercase py-3 text-xs tracking-wider transition-colors disabled:opacity-50"
              >
                {saving ? "Saving..." : isEditing ? "Update Promo" : "Create Promo"}
              </button>
              {isEditing && (
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setTitle("");
                    setDescription("");
                    setOffer("");
                    setPeriod("");
                    setImage("");
                    setActive(true);
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

        {/* Right Column: Promotions Directory List */}
        <div className="lg:col-span-8 bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
            <Tag className="w-5 h-5" />
            Promotions Inventory ({promos.length})
          </h2>

          {loading ? (
            <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading promotions...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left font-mono text-xs">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-3">Title</th>
                    <th className="p-3">Offer</th>
                    <th className="p-3">Period</th>
                    <th className="p-3 text-center">Status</th>
                    <th className="p-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {promos.map((promo) => (
                    <tr key={promo.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-3 font-sans text-sm font-bold text-[#e3e2e2]">{promo.title}</td>
                      <td className="p-3 text-[#ffd700] font-bold">{promo.offer}</td>
                      <td className="p-3 text-[#d0c6ab]">{promo.period}</td>
                      <td className="p-3 text-center">
                        <span
                          className={`px-2 py-0.5 text-[9px] font-bold uppercase ${
                            promo.active
                              ? "bg-green-950/60 border border-green-500 text-green-300"
                              : "bg-[#343535] border border-[#4d4732] text-[#605f5e]"
                          }`}
                        >
                          {promo.active ? "Active" : "Expired"}
                        </span>
                      </td>
                      <td className="p-3 text-center">
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleEdit(promo)}
                            className="p-1 text-[#d0c6ab] hover:text-[#ffd700] transition-colors"
                            title="Edit Promo"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(promo.id)}
                            className="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Delete Promo"
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
