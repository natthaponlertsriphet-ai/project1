"use client";

import { useState, useEffect } from "react";
import { Check, X, ShieldAlert, MessageSquare } from "lucide-react";

export default function BookingsManager() {
  const [bookings, setBookings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Line OA simulation log panel state
  const [lineLogs, setLineLogs] = useState<any[]>([]);

  useEffect(() => {
    fetchBookings();
  }, []);

  async function fetchBookings() {
    try {
      const res = await fetch("/api/bookings");
      if (res.ok) {
        const data = await res.json();
        setBookings(data);
      } else {
        throw new Error("Failed to fetch bookings list");
      }
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  }

  const handleUpdateStatus = async (bookingId: string, newStatus: string) => {
    setError(null);
    try {
      const res = await fetch("/api/bookings", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bookingId, status: newStatus }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Failed to update booking status");

      // Success - update list
      setBookings((prev) =>
        prev.map((b) => (b.id === bookingId ? { ...b, status: newStatus } : b))
      );

      // Log LINE OA Notification Simulation
      if (data.lineNotificationSent) {
        const newLog = {
          id: Date.now(),
          phone: data.booking.customerPhone,
          name: data.booking.customerName,
          message: data.lineMessage,
          timestamp: new Date().toLocaleTimeString(),
        };
        setLineLogs((prev) => [newLog, ...prev]);
      }
    } catch (err: any) {
      setError(err.message);
    }
  };

  return (
    <div className="flex flex-col lg:flex-row gap-8 w-full max-w-[1200px]">
      
      {/* Left/Main Section: Bookings List */}
      <div className="flex-grow bg-[#1b1c1c] border border-[#4d4732] p-6 relative">
        <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
        <h2 className="font-['Anton'] text-[20px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
          Customer Reservation Lists
        </h2>

        {error && (
          <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm mb-6">
            {error}
          </div>
        )}

        {loading ? (
          <div className="text-center font-mono py-12 text-[#d0c6ab]">Loading bookings...</div>
        ) : bookings.length === 0 ? (
          <div className="text-center font-mono py-12 text-[#605f5e]">No bookings registered in the system.</div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left font-mono text-xs">
              <thead>
                <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                  <th className="p-3">Customer</th>
                  <th className="p-3">Details</th>
                  <th className="p-3">Table assigned</th>
                  <th className="p-3 text-center">Status</th>
                  <th className="p-3 text-center">Action Console</th>
                </tr>
              </thead>
              <tbody>
                {bookings.map((b) => (
                  <tr key={b.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                    <td className="p-3">
                      <div className="font-sans text-sm font-bold text-[#e3e2e2]">{b.customerName}</div>
                      <div className="text-[#605f5e] mt-0.5">{b.customerPhone}</div>
                    </td>
                    <td className="p-3">
                      <div>Date: <strong className="text-[#e3e2e2]">{b.date}</strong></div>
                      <div>Time: <strong className="text-[#e3e2e2]">{b.timeSlot} น.</strong></div>
                      <div>Guests: <strong className="text-[#ffd700]">{b.pax} Pax</strong></div>
                    </td>
                    <td className="p-3">
                      {b.table ? (
                        <div>
                          <span className="font-bold text-[#ffd700] font-sans text-sm">
                            {b.table.number}
                          </span>
                          <span className="text-[10px] text-[#605f5e] uppercase ml-2 bg-[#343535] px-1 py-0.5">
                            {b.table.zone}
                          </span>
                        </div>
                      ) : (
                        <span className="text-[#605f5e] italic">Unassigned</span>
                      )}
                    </td>
                    <td className="p-3 text-center">
                      <span
                        className={`px-2 py-0.5 text-[10px] font-bold uppercase ${
                          b.status === "CONFIRMED"
                            ? "bg-green-950/60 border border-green-500 text-green-300"
                            : b.status === "CANCELLED"
                            ? "bg-red-950/60 border border-red-500 text-red-300"
                            : "bg-yellow-950/60 border border-[#ffd700] text-yellow-300"
                        }`}
                      >
                        {b.status}
                      </span>
                    </td>
                    <td className="p-3 text-center">
                      {b.status === "PENDING" ? (
                        <div className="flex justify-center gap-2">
                          <button
                            onClick={() => handleUpdateStatus(b.id, "CONFIRMED")}
                            className="bg-green-600 hover:bg-green-500 text-[#121414] font-bold px-2 py-1 flex items-center gap-1 transition-colors uppercase text-[10px]"
                          >
                            <Check className="w-3.5 h-3.5" /> Approve
                          </button>
                          <button
                            onClick={() => handleUpdateStatus(b.id, "CANCELLED")}
                            className="bg-red-600 hover:bg-red-500 text-[#121414] font-bold px-2 py-1 flex items-center gap-1 transition-colors uppercase text-[10px]"
                          >
                            <X className="w-3.5 h-3.5" /> Reject
                          </button>
                        </div>
                      ) : b.status === "CONFIRMED" ? (
                        <button
                          onClick={() => handleUpdateStatus(b.id, "CANCELLED")}
                          className="bg-transparent border border-red-500/50 hover:bg-red-500 hover:text-[#121414] text-red-400 font-bold px-2 py-1 transition-all uppercase text-[10px]"
                        >
                          Cancel Booking
                        </button>
                      ) : (
                        <span className="text-[#605f5e]">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Right Section: LINE OA Simulator log panel */}
      <div className="w-full lg:w-80 shrink-0 bg-[#1b1c1c] border border-[#4d4732] p-6 h-fit relative">
        <div className="absolute top-0 left-0 right-0 h-1 bg-[#ffd700]"></div>
        <h2 className="font-['Anton'] text-[18px] uppercase text-[#ffd700] m-0 mb-6 tracking-wider flex items-center gap-2">
          <MessageSquare className="w-5 h-5" />
          LINE OA Simulator Logs
        </h2>

        <div className="flex flex-col gap-4 max-h-[500px] overflow-y-auto pr-1">
          {lineLogs.length === 0 ? (
            <div className="text-center font-mono text-[11px] text-[#605f5e] py-12 border border-[#4d4732]/30 bg-[#121414] p-4">
              Waiting for booking status updates to trigger notifications...
            </div>
          ) : (
            lineLogs.map((log) => (
              <div
                key={log.id}
                className="bg-[#121414] border border-green-500/50 p-4 flex flex-col gap-2 relative overflow-hidden"
              >
                {/* Glowing neon top-bar */}
                <div className="absolute top-0 left-0 right-0 h-0.5 bg-green-500"></div>

                <div className="flex justify-between items-center text-[10px] font-mono text-green-400">
                  <span>To: {log.name} ({log.phone})</span>
                  <span>{log.timestamp}</span>
                </div>
                <p className="text-[11px] text-[#d0c6ab] m-0 leading-relaxed font-sans mt-1">
                  {log.message}
                </p>
                <div className="text-[9px] font-mono text-right text-[#605f5e]">
                  ✓ Delivered via LineAPI
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
}
