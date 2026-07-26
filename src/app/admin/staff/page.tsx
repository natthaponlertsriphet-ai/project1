"use client";

import { useState, useEffect } from "react";
import { Plus, Trash2, Edit2, ShieldAlert, Users } from "lucide-react";

export default function StaffManagement() {
  const [staff, setStaff] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  
  // Form State
  const [isEditing, setIsEditing] = useState(false);
  const [editId, setEditId] = useState<string | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [role, setRole] = useState("STAFF");
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    fetchStaff();
  }, []);

  async function fetchStaff() {
    try {
      const res = await fetch("/api/staff");
      if (res.ok) {
        const data = await res.json();
        setStaff(data);
      } else {
        throw new Error("Failed to fetch staff list");
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

    const url = isEditing ? `/api/staff?id=${editId}` : "/api/staff";
    const method = isEditing ? "PUT" : "POST";
    const payload = isEditing 
      ? { name, email, role, ...(password ? { password } : {}) } 
      : { name, email, role, password };

    try {
      const res = await fetch(url, {
        method,
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to save user");

      // Success
      setName("");
      setEmail("");
      setPassword("");
      setRole("STAFF");
      setIsEditing(false);
      setEditId(null);
      fetchStaff();
    } catch (err: any) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const handleEdit = (user: any) => {
    setName(user.name);
    setEmail(user.email);
    setPassword(""); // Clear password field
    setRole(user.role);
    setEditId(user.id);
    setIsEditing(true);
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this staff member?")) return;
    setError(null);

    try {
      const res = await fetch(`/api/staff?id=${id}`, { method: "DELETE" });
      const data = await res.json();

      if (!res.ok) throw new Error(data.error || "Failed to delete user");

      fetchStaff();
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <div className="flex flex-col gap-8 w-full max-w-[1200px]">
      
      {error && (
        <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm flex items-center gap-2">
          <ShieldAlert className="w-5 h-5 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left Column: Form to Add/Edit Staff */}
        <div className="lg:col-span-4 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider">
            {isEditing ? "Edit Account" : "Add Staff Account"}
          </h2>

          <form onSubmit={handleSubmit} className="flex flex-col gap-5">
            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Full Name</label>
              <input
                type="text"
                placeholder="e.g., John Doe"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Email Address</label>
              <input
                type="email"
                placeholder="e.g., john@chithole.com"
                required
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">
                Password {isEditing && <span className="text-[10px] text-[#605f5e] font-mono lowercase">(leave blank to keep current)</span>}
              </label>
              <input
                type="password"
                placeholder={isEditing ? "••••••••" : "Enter password"}
                required={!isEditing}
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>

            <div className="flex flex-col gap-2">
              <label className="text-xs font-bold uppercase text-[#d0c6ab] tracking-wider">Access Role</label>
              <select
                className="bg-[#121414] text-[#e3e2e2] p-3 border border-[#4d4732] rounded-none focus:border-[#ffd700] focus:outline-none text-sm transition-colors"
                value={role}
                onChange={(e) => setRole(e.target.value)}
              >
                <option value="STAFF">STAFF (พนักงานทั่วไป)</option>
                <option value="ADMIN">ADMIN (ผู้ดูแลระบบ)</option>
              </select>
            </div>

            <div className="flex gap-3 mt-2">
              <button
                type="submit"
                disabled={saving}
                className="flex-grow bg-[#ffd700] text-[#121414] hover:bg-[#fff6df] font-bold uppercase py-3 text-xs tracking-wider transition-colors disabled:opacity-50"
              >
                {saving ? "Saving..." : isEditing ? "Update Account" : "Create Account"}
              </button>
              {isEditing && (
                <button
                  type="button"
                  onClick={() => {
                    setIsEditing(false);
                    setName("");
                    setEmail("");
                    setPassword("");
                    setRole("STAFF");
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

        {/* Right Column: Staff Directory List */}
        <div className="lg:col-span-8 bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
          <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
          <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
            <Users className="w-5 h-5" />
            Staff Directory ({staff.length})
          </h2>

          {loading ? (
            <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading directory...</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left font-mono text-xs">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-3">Full Name</th>
                    <th className="p-3">Email</th>
                    <th className="p-3">Role</th>
                    <th className="p-3 text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {staff.map((userItem) => (
                    <tr key={userItem.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-3 font-sans text-sm font-bold text-[#e3e2e2]">{userItem.name}</td>
                      <td className="p-3 text-[#d0c6ab]">{userItem.email}</td>
                      <td className="p-3">
                        <span
                          className={`px-2 py-0.5 text-[10px] font-bold uppercase ${
                            userItem.role === "ADMIN"
                              ? "bg-red-950/60 border border-red-500 text-red-300"
                              : "bg-[#343535] border border-[#4d4732] text-[#d0c6ab]"
                          }`}
                        >
                          {userItem.role}
                        </span>
                      </td>
                      <td className="p-3 text-center">
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleEdit(userItem)}
                            className="p-1 text-[#d0c6ab] hover:text-[#ffd700] transition-colors"
                            title="Edit User"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(userItem.id)}
                            className="p-1 text-red-400 hover:text-red-600 transition-colors"
                            title="Delete User"
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
