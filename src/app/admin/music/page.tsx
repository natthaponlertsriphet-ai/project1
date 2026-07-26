"use client";

import { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, Music } from "lucide-react";

export default function MusicManagement() {
  const [music, setMusic] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form State
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [day, setDay] = useState("Mon");
  const [time, setTime] = useState("8:00 PM");
  const [artist, setArtist] = useState("");
  const [description, setDescription] = useState("");
  const [status, setStatus] = useState("REGULAR");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchMusic();
  }, []);

  async function fetchMusic() {
    try {
      const res = await fetch("/api/music");
      if (res.ok) {
        const data = await res.json();
        setMusic(data);
      } else {
        throw new Error("Failed to fetch music schedule");
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

    const url = isEditing ? `/api/music?id=${editId}` : "/api/music";
    const method = isEditing ? "PUT" : "POST";
    const payload = { day, time, artist, description, status };

    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to save schedule");

      // Reset
      setDay("Mon");
      setTime("8:00 PM");
      setArtist("");
      setDescription("");
      setStatus("REGULAR");
      setIsEditing(false);
      setEditId(null);
      fetchMusic();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEdit = (event: any) => {
    setDay(event.day);
    setTime(event.time);
    setArtist(event.artist);
    setDescription(event.description);
    setStatus(event.status);
    setEditId(event.id);
    setIsEditing(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this event from the rotation?")) return;
    setError(null);

    try {
      const res = await fetch(`/api/music?id=${id}`, { method: "DELETE" });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to delete event");
      fetchMusic();
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
        {/* Left Column: Form to Add/Edit Music */}
        <div className="lg:col-span-4 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider">
            {isEditing ? "Edit Stage Slot" : "Schedule Live Performance"}
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Weekday</label>
                <select
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm"
                  value={day}
                  onChange={(e) => setDay(e.target.value)}
                >
                  <option value="Mon">Monday</option>
                  <option value="Tue">Tuesday</option>
                  <option value="Wed">Wednesday</option>
                  <option value="Thu">Thursday</option>
                  <option value="Fri">Friday</option>
                  <option value="Sat">Saturday</option>
                  <option value="Sun">Sunday</option>
                </select>
              </div>
              <div className="flex flex-col gap-2">
                <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Time Slot</label>
                <input
                  type="text"
                  placeholder="e.g., 8:00 PM"
                  required
                  className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                  value={time}
                  onChange={(e) => setTime(e.target.value)}
                />
              </div>
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Artist / Band Name</label>
              <input
                type="text"
                placeholder="e.g., The Rusty Valves"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={artist}
                onChange={(e) => setArtist(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Genre & Intro</label>
              <textarea
                placeholder="Stripped down R&B and soul covers..."
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors h-20 resize-none"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Performance Status</label>
              <select
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={status}
                onChange={(e) => setStatus(e.target.value)}
              >
                <option value="REGULAR">REGULAR (แสดงทั่วไป)</option>
                <option value="HOT">HOT (วงไฮไลต์พิเศษ)</option>
                <option value="DAYSET">DAYSET (รอบกลางวัน/บ่าย)</option>
              </select>
            </div>

            <div className="flex gap-3 mt-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-grow bg-[#ffd700] text-[#121414] hover:bg-[#fff6df] font-bold uppercase py-3 text-xs tracking-wider transition-colors disabled:opacity-50"
              >
                {saving ? "Saving..." : isEditing ? "Update Schedule" : "Add Schedule"}
              </button>
              {isEditing && (
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setDay("Mon");
                    setTime("8:00 PM");
                    setArtist("");
                    setDescription("");
                    setStatus("REGULAR");
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

        {/* Right Column: Weekly Schedule List */}
        <div className="lg:col-span-8 bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
            <Music className="w-5 h-5" />
            Weekly Rotation Stage Grid ({music.length})
          </h2>

          {loading ? (
            <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading schedule...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left font-mono text-xs">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-3">Day</th>
                    <th className="p-3">Time</th>
                    <th className="p-3">Artist / Band</th>
                    <th className="p-3">Highlight Status</th>
                    <th className="p-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {music.map((event) => (
                    <tr key={event.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-3 text-[#ffd700] font-bold">{event.day}</td>
                      <td className="p-3 text-[#e3e2e2]">{event.time}</td>
                      <td className="p-3 font-sans text-sm font-bold text-[#e3e2e2]">{event.artist}</td>
                      <td className="p-3">
                        <span
                          className={`px-2 py-0.5 text-[9px] font-bold uppercase ${
                            event.status === "HOT"
                              ? "bg-red-950/60 border border-red-500 text-red-300"
                              : event.status === "DAYSET"
                              ? "bg-blue-950/60 border border-blue-500 text-blue-300"
                              : "bg-[#343535] border border-[#4d4732] text-[#d0c6ab]"
                          }`}
                        >
                          {event.status}
                        </span>
                      </td>
                      <td className="p-3 text-center">
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleEdit(event)}
                            className="p-1 text-[#d0c6ab] hover:text-[#ffd700] transition-colors"
                            title="Edit Event"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(event.id)}
                            className="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Delete Event"
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
