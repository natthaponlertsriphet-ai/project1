"use client";

import { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, Beer } from "lucide-react";

export default function BeersManagement() {
  const [beers, setBeers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [tapNumber, setTapNumber] = useState("");
  const [name, setName] = useState("");
  const [type, setType] = useState("");
  const [abv, setAbv] = useState("");
  const [ibu, setIbu] = useState("");
  const [description, setDescription] = useState("");
  const [price, setPrice] = useState("");
  const [active, setActive] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchBeers();
  }, []);

  async function fetchBeers() {
    try {
      const res = await fetch("/api/beers");
      if (res.ok) {
        const data = await res.json();
        setBeers(data);
      } else {
        throw new Error("Failed to fetch beers list");
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

    const url = isEditing ? `/api/beers?id=${editId}` : "/api/beers";
    const method = isEditing ? "PUT" : "POST";
    const payload = {
      tapNumber,
      name,
      type,
      abv,
      ibu: ibu || "N/A",
      description,
      price: parseFloat(price),
      active,
    };

    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to save beer");

      // Reset Form
      setTapNumber("");
      setName("");
      setType("");
      setAbv("");
      setIbu("");
      setDescription("");
      setPrice("");
      setActive(true);
      setIsEditing(false);
      setEditId(null);
      fetchBeers();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEdit = (beer: any) => {
    setTapNumber(beer.tapNumber);
    setName(beer.name);
    setType(beer.type);
    setAbv(beer.abv);
    setIbu(beer.ibu);
    setDescription(beer.description);
    setPrice(beer.price.toString());
    setActive(beer.active);
    setEditId(beer.id);
    setIsEditing(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this beer tap?")) return;
    setError(null);

    try {
      const res = await fetch(`/api/beers?id=${id}`, { method: "DELETE" });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to delete beer");
      fetchBeers();
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
        {/* Left Column: Form to Add/Edit Beer */}
        <div className="lg:col-span-4 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider">
            {isEditing ? "Edit Draft Properties" : "Register Draft Tap"}
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Tap #</label>
                <input
                  type="text"
                  placeholder="e.g., 01"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={tapNumber}
                  onChange={(e) => setTapNumber(e.target.value)}
                />
              </div>
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Price (THB)</label>
                <input
                  type="number"
                  placeholder="e.g., 220"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={price}
                  onChange={(e) => setPrice(e.target.value)}
                />
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Beer Name</label>
              <input
                type="text"
                placeholder="e.g., Lanna IPA"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Type / Style</label>
              <input
                type="text"
                placeholder="e.g., West Coast IPA"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={type}
                onChange={(e) => setType(e.target.value)}
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">ABV (%)</label>
                <input
                  type="text"
                  placeholder="e.g., 6.5%"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={abv}
                  onChange={(e) => setAbv(e.target.value)}
                />
              </div>
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">IBU</label>
                <input
                  type="text"
                  placeholder="e.g., 60"
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={ibu}
                  onChange={(e) => setIbu(e.target.value)}
                />
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Short Description</label>
              <textarea
                placeholder="Piney, citrus, aggressive bite..."
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
                Active on Tap List
              </label>
            </div>

            <div className="flex gap-3 mt-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-grow bg-[#ffd700] text-[#121414] hover:bg-[#fff6df] font-bold uppercase py-3 text-xs tracking-wider transition-colors disabled:opacity-50"
              >
                {saving ? "Saving..." : isEditing ? "Update Tap" : "Create Tap"}
              </button>
              {isEditing && (
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setTapNumber("");
                    setName("");
                    setType("");
                    setAbv("");
                    setIbu("");
                    setDescription("");
                    setPrice("");
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

        {/* Right Column: Beer Inventory List */}
        <div className="lg:col-span-8 bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
            <Beer className="w-5 h-5" />
            Current Draft Inventory ({beers.length})
          </h2>

          {loading ? (
            <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading draft lines...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left font-mono text-xs">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-3">Tap</th>
                    <th className="p-3">Draft Name</th>
                    <th className="p-3">Type</th>
                    <th className="p-3">ABV / IBU</th>
                    <th className="p-3 text-right">Price</th>
                    <th className="p-3 text-center">Status</th>
                    <th className="p-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {beers.map((beer) => (
                    <tr key={beer.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-3 text-[#ffd700] font-bold">{beer.tapNumber}</td>
                      <td className="p-3 font-sans text-sm font-bold text-[#e3e2e2]">{beer.name}</td>
                      <td className="p-3 text-[#d0c6ab]">{beer.type}</td>
                      <td className="p-3 text-[#d0c6ab]">
                        {beer.abv} / {beer.ibu}
                      </td>
                      <td className="p-3 text-right text-[#ffd700] font-bold">{beer.price} THB</td>
                      <td className="p-3 text-center">
                        <span
                          className={`px-2 py-0.5 text-[9px] font-bold uppercase ${
                            beer.active
                              ? "bg-green-950/60 border border-green-500 text-green-300"
                              : "bg-[#343535] border border-[#4d4732] text-[#605f5e]"
                          }`}
                        >
                          {beer.active ? "On Tap" : "Empty"}
                        </span>
                      </td>
                      <td className="p-3 text-center">
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleEdit(beer)}
                            className="p-1 text-[#d0c6ab] hover:text-[#ffd700] transition-colors"
                            title="Edit Beer"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(beer.id)}
                            className="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Delete Beer"
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
