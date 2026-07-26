"use client";

import { useState, useEffect } from "react";

export default function ReservationPage() {
  // Input Form State
  const [customerName, setCustomerName] = useState("");
  const [customerPhone, setCustomerPhone] = useState("");
  const [pax, setPax] = useState("2");
  const [date, setDate] = useState("");
  const [timeSlot, setTimeSlot] = useState("18:00");
  const [selectedTable, setSelectedTable] = useState<any>(null);

  // Database lists
  const [tables, setTables] = useState<any[]>([]);
  const [bookings, setBookings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Feedback State
  const [bookingSuccess, setBookingSuccess] = useState<any>(null);
  const [bookingError, setBookingError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Search State
  const [searchPhone, setSearchPhone] = useState("");
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [searchDone, setSearchDone] = useState(false);

  // Set default date to today (YYYY-MM-DD)
  useEffect(() => {
    const today = new Date().toISOString().split("T")[0];
    setDate(today);
  }, []);

  // Fetch tables and bookings on mount
  useEffect(() => {
    async function fetchData() {
      try {
        const [tablesRes, bookingsRes] = await Promise.all([
          fetch("/api/tables"),
          fetch("/api/bookings"),
        ]);
        if (tablesRes.ok && bookingsRes.ok) {
          const tablesData = await tablesRes.json();
          const bookingsData = await bookingsRes.json();
          setTables(tablesData);
          setBookings(bookingsData);
        }
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    }
    fetchData();
  }, []);

  // Recalculate occupied tables for the selected Date and Time Slot
  const getReservedTableIdsForSelectedSlot = () => {
    return bookings
      .filter(
        (b) =>
          b.date === date &&
          b.timeSlot === timeSlot &&
          b.status !== "CANCELLED"
      )
      .map((b) => b.tableId);
  };

  const reservedTableIds = getReservedTableIdsForSelectedSlot();

  const handleBookingSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBookingError(null);
    setBookingSuccess(null);

    if (!selectedTable) {
      setBookingError("Please select a table from the floor plan layout first.");
      return;
    }

    setSubmitting(true);

    try {
      const res = await fetch("/api/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          customerName,
          customerPhone,
          date,
          timeSlot,
          pax: parseInt(pax),
          tableId: selectedTable.id,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.error || "Failed to create booking.");
      }

      // Success
      setBookingSuccess(data.booking);
      setCustomerName("");
      setCustomerPhone("");
      setSelectedTable(null);

      // Refresh bookings
      const newBookingsRes = await fetch("/api/bookings");
      if (newBookingsRes.ok) {
        const newBookingsData = await newBookingsRes.json();
        setBookings(newBookingsData);
      }
    } catch (err: any) {
      setBookingError(err.message);
    } finally {
      setSubmitting(false);
    }
  };

  const handleSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!searchPhone) return;

    setSearchLoading(true);
    setSearchDone(true);
    setSearchResults([]);

    try {
      const res = await fetch(`/api/bookings?phone=${encodeURIComponent(searchPhone)}`);
      if (res.ok) {
        const data = await res.json();
        setSearchResults(data);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setSearchLoading(false);
    }
  };

  const handleCancelBooking = async (bookingId: string) => {
    if (!confirm("Are you sure you want to cancel this booking?")) return;

    try {
      const res = await fetch("/api/bookings", {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bookingId, status: "CANCELLED" }),
      });

      if (res.ok) {
        alert("Booking cancelled successfully.");
        // Refresh
        const newBookingsRes = await fetch("/api/bookings");
        if (newBookingsRes.ok) {
          const newBookingsData = await newBookingsRes.json();
          setBookings(newBookingsData);
        }
        // Refresh search results
        setSearchResults((prev) =>
          prev.map((b) => (b.id === bookingId ? { ...b, status: "CANCELLED" } : b))
        );
      } else {
        const data = await res.json();
        alert(data.error || "Failed to cancel booking.");
      }
    } catch (err) {
      console.error(err);
      alert("Error occurred during cancellation.");
    }
  };

  return (
    <div className="flex flex-col w-full min-h-screen bg-[#131313] text-[#e5e2e1] font-['Work_Sans']">
      
      {/* HERO SECTION */}
      <section className="relative w-full min-h-[60vh] flex flex-col justify-end px-6 lg:px-16 pb-32 pt-24">
        {/* Background Gradients & Images */}
        <div className="absolute inset-0 z-0 bg-[#131313] overflow-hidden">
          <div className="absolute top-0 right-0 w-[80vw] h-[80vw] md:w-[40vw] md:h-[40vw] bg-[#ffd782]/5 rounded-full blur-[120px] mix-blend-screen translate-x-1/4 -translate-y-1/4"></div>
          <div className="absolute bottom-0 left-0 w-[60vw] h-[60vw] md:w-[30vw] md:h-[30vw] bg-[#a32400]/10 rounded-full blur-[100px] mix-blend-screen -translate-x-1/4 translate-y-1/4"></div>
          <div
            className="absolute inset-0 z-0 opacity-20 bg-cover bg-center mix-blend-luminosity"
            style={{
              backgroundImage:
                "url('https://lh3.googleusercontent.com/aida-public/AB6AXuDoC265t8UiQDEhT2ukPLunIihcawl6XSFiIjHO_AZUtksZ9h6445gZKZu8emq82Vf3hIv9JaunyKIBFf-3hnJL2f5PcyVYHueB2IFJXiNzXqckJtxqYsMYOIi9KSXwlNXdgIy-Jj8ilLtUgRRJz0Qk_Pshcx52dhBi-PoZz8TZ7vSIZzQpoEFWrKP0cBQCklEuue6lczE6J1A3XwuZ_9JJR0ysrJkMIPwe_XE6gFZJl1lVFOQo3nvYmp4ptZ7jzG2tZveU0EpJDHri')",
            }}
          ></div>
          <div className="absolute inset-0 bg-gradient-to-t from-[#131313] via-[#131313]/80 to-transparent"></div>
        </div>

        {/* Hero Content */}
        <div className="relative z-10 w-full max-w-[1200px] mx-auto grid grid-cols-1 md:grid-cols-12 gap-6 mt-32">
          <div className="col-span-1 md:col-span-8 flex flex-col gap-6">
            <div className="flex items-center gap-4">
              <div className="h-[2px] w-12 bg-[#ffd782]"></div>
              <span className="font-mono text-[12px] text-[#ffd782] uppercase tracking-widest">
                Chiang Mai Brewing Co.
              </span>
            </div>
            <h1 className="font-['Anton'] text-[48px] md:text-[72px] text-[#e5e2e1] uppercase leading-[0.85] tracking-tight m-0">
              The Freshest <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#ffd782] to-[#f2b705]">
                Pours In Town.
              </span>
            </h1>
            <p className="text-[18px] text-[#d3c5ac] max-w-2xl mt-4 leading-relaxed m-0">
              Authentic Thai craft beer culture. High-energy atmosphere, workshop vibes, and a
              commitment to liquid perfection. Reserve your spot at the source.
            </p>
          </div>

          {/* Tonight's status badge */}
          <div className="col-span-1 md:col-span-4 flex flex-col justify-end items-start md:items-end gap-2 pt-8 md:pt-0">
            <span className="font-mono text-[12px] text-[#d3c5ac] uppercase">Tonight's Taproom Status</span>
            <div className="flex items-center gap-3 bg-[#201f1f]/50 backdrop-blur-md px-4 py-2 rounded-full border border-white/5 shadow-lg">
              <div className="w-2 h-2 rounded-full bg-[#ffd782] shadow-[0_0_8px_rgba(255,215,130,0.8)] animate-pulse"></div>
              <span className="font-mono text-[14px] text-[#e5e2e1]">Brewing Live / Open until 00:00</span>
            </div>
          </div>
        </div>
      </section>

      {/* BOOKING & FLOOR PLAN SPLIT */}
      <section className="relative z-20 w-full px-6 lg:px-16 py-24 bg-[#131313]">
        <div className="max-w-[1200px] mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16">
          
          {/* LEFT: INTERACTIVE BLUEPRINT FLOOR PLAN */}
          <div className="col-span-1 lg:col-span-7 flex flex-col gap-8">
            <div className="flex flex-col gap-2">
              <h2 className="font-['Anton'] text-[32px] text-[#e5e2e1] uppercase flex items-center gap-4 m-0">
                <span className="material-symbols-outlined text-[#ffd782] text-[32px]">map</span>
                Select Your Table
              </h2>
              <p className="text-sm text-[#d3c5ac] m-0">
                Click an available table on the blueprint layout below to instantly bind it to your reservation form.
              </p>
            </div>

            {/* Layout Container */}
            <div className="relative w-full aspect-square md:aspect-[4/3] bg-[#201f1f] rounded-xl shadow-2xl overflow-hidden border border-white/5">
              {/* Grid overlay for industrial look */}
              <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff05_1px,transparent_1px),linear-gradient(to_bottom,#ffffff05_1px,transparent_1px)] bg-[size:40px_40px] pointer-events-none"></div>

              {loading ? (
                <div className="absolute inset-0 flex items-center justify-center font-mono text-xs text-[#d3c5ac]">
                  LOADING FLOOR BLUEPRINT...
                </div>
              ) : (
                <div className="absolute inset-6 flex flex-col justify-between">
                  {/* Zone Map Grid */}
                  <div className="flex flex-col gap-6 h-full justify-around">
                    {/* Stage View Zone */}
                    <div className="border border-red-500/10 bg-red-950/5 p-4 rounded">
                      <div className="text-[10px] font-mono uppercase text-red-400 mb-2 tracking-wider">Stage Spotlights Zone</div>
                      <div className="grid grid-cols-4 gap-3">
                        {tables
                          .filter((t) => t.zone === "STAGE")
                          .map((table) => {
                            const isReserved = reservedTableIds.includes(table.id) || table.status === "OCCUPIED";
                            const isSelected = selectedTable?.id === table.id;

                            return (
                              <button
                                key={table.id}
                                disabled={isReserved}
                                type="button"
                                onClick={() => setSelectedTable(table)}
                                className={`p-3 font-mono text-center transition-all border rounded-none ${
                                  isReserved
                                    ? "bg-[#1c1b1b] border-white/5 text-[#605f5e] cursor-not-allowed opacity-40"
                                    : isSelected
                                    ? "bg-[#ffd782] border-[#ffd782] text-[#3f2e00] font-bold shadow-[0_0_15px_rgba(255,215,130,0.5)]"
                                    : "bg-[#ffd782]/5 border-[#ffd782]/30 text-[#ffd782] hover:bg-[#ffd782]/10"
                                }`}
                              >
                                <div className="text-xs">{table.number}</div>
                                <div className="text-[9px] opacity-70">{table.capacity}P</div>
                              </button>
                            );
                          })}
                      </div>
                    </div>

                    {/* Indoor Zone */}
                    <div className="border border-[#ffd782]/10 bg-black/25 p-4 rounded">
                      <div className="text-[10px] font-mono uppercase text-[#ffd782] mb-2 tracking-wider">Air-Con Indoor Zone</div>
                      <div className="grid grid-cols-4 gap-3">
                        {tables
                          .filter((t) => t.zone === "INDOOR")
                          .map((table) => {
                            const isReserved = reservedTableIds.includes(table.id) || table.status === "OCCUPIED";
                            const isSelected = selectedTable?.id === table.id;

                            return (
                              <button
                                key={table.id}
                                disabled={isReserved}
                                type="button"
                                onClick={() => setSelectedTable(table)}
                                className={`p-3 font-mono text-center transition-all border rounded-none ${
                                  isReserved
                                    ? "bg-[#1c1b1b] border-white/5 text-[#605f5e] cursor-not-allowed opacity-40"
                                    : isSelected
                                    ? "bg-[#ffd782] border-[#ffd782] text-[#3f2e00] font-bold shadow-[0_0_15px_rgba(255,215,130,0.5)]"
                                    : "bg-[#ffd782]/5 border-[#ffd782]/30 text-[#ffd782] hover:bg-[#ffd782]/10"
                                }`}
                              >
                                <div className="text-xs">{table.number}</div>
                                <div className="text-[9px] opacity-70">{table.capacity}P</div>
                              </button>
                            );
                          })}
                      </div>
                    </div>

                    {/* Outdoor Zone */}
                    <div className="border border-green-500/10 bg-green-950/5 p-4 rounded">
                      <div className="text-[10px] font-mono uppercase text-green-400 mb-2 tracking-wider">Outdoor Beer Garden Zone</div>
                      <div className="grid grid-cols-4 gap-3">
                        {tables
                          .filter((t) => t.zone === "OUTDOOR")
                          .map((table) => {
                            const isReserved = reservedTableIds.includes(table.id) || table.status === "OCCUPIED";
                            const isSelected = selectedTable?.id === table.id;

                            return (
                              <button
                                key={table.id}
                                disabled={isReserved}
                                type="button"
                                onClick={() => setSelectedTable(table)}
                                className={`p-3 font-mono text-center transition-all border rounded-none ${
                                  isReserved
                                    ? "bg-[#1c1b1b] border-white/5 text-[#605f5e] cursor-not-allowed opacity-40"
                                    : isSelected
                                    ? "bg-[#ffd782] border-[#ffd782] text-[#3f2e00] font-bold shadow-[0_0_15px_rgba(255,215,130,0.5)]"
                                    : "bg-[#ffd782]/5 border-[#ffd782]/30 text-[#ffd782] hover:bg-[#ffd782]/10"
                                }`}
                              >
                                <div className="text-xs">{table.number}</div>
                                <div className="text-[9px] opacity-70">{table.capacity}P</div>
                              </button>
                            );
                          })}
                      </div>
                    </div>
                  </div>

                  {/* Legend */}
                  <div className="flex gap-6 mt-4 justify-end border-t border-white/5 pt-3">
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 bg-[#ffd782]/10 border border-[#ffd782]/40"></div>
                      <span className="font-mono text-[11px] text-[#d3c5ac] uppercase">Available</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="w-3 h-3 bg-[#1c1b1b] border border-white/5 opacity-40"></div>
                      <span className="font-mono text-[11px] text-[#d3c5ac] uppercase opacity-50">Reserved</span>
                    </div>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* RIGHT: RESERVATION BOOKING FORM */}
          <div className="col-span-1 lg:col-span-5 flex flex-col">
            <div className="w-full bg-[#201f1f] shadow-2xl rounded-xl p-8 md:p-10 relative overflow-hidden border border-white/5">
              <div className="absolute top-0 right-0 w-64 h-64 bg-[#ffd782]/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
              
              <div className="relative z-10 flex flex-col gap-8">
                <div className="flex flex-col gap-1">
                  <span className="font-mono text-[#ffd782] uppercase text-[11px] tracking-widest">Secure Your Spot</span>
                  <h3 className="font-['Anton'] text-[28px] uppercase text-[#e5e2e1] leading-none m-0">Reservation</h3>
                </div>

                {bookingSuccess && (
                  <div className="p-4 bg-green-950/20 border border-green-500/50 text-green-200 text-sm">
                    ✓ Booking Confirmed! We simulated sending a LINE OA message to {bookingSuccess.customerPhone}.
                  </div>
                )}

                {bookingError && (
                  <div className="p-4 bg-red-950/20 border border-red-500/50 text-red-200 text-sm">
                    ⚠️ {bookingError}
                  </div>
                )}

                <form onSubmit={handleBookingSubmit} className="flex flex-col gap-6">
                  {/* Select Table Input display */}
                  <div className="flex flex-col gap-2">
                    <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Selected Table</label>
                    <div className="w-full bg-[#131313] p-4 border border-white/10 flex items-center justify-between text-sm">
                      <span className="font-mono text-[#ffd782] font-bold">
                        {selectedTable
                          ? `${selectedTable.number} (${selectedTable.zone} - Max ${selectedTable.capacity} guests)`
                          : "None Selected (Click a table on map)"}
                      </span>
                      <span className="material-symbols-outlined text-[#d3c5ac] opacity-50">chair</span>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col gap-2">
                      <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Booking Date</label>
                      <input
                        type="date"
                        required
                        className="bg-[#131313] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                        value={date}
                        onChange={(e) => {
                          setDate(e.target.value);
                          setSelectedTable(null); // Reset table selection when date changes
                        }}
                      />
                    </div>
                    <div className="flex flex-col gap-2">
                      <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Time Slot</label>
                      <select
                        className="bg-[#131313] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                        value={timeSlot}
                        onChange={(e) => {
                          setTimeSlot(e.target.value);
                          setSelectedTable(null); // Reset table selection when slot changes
                        }}
                      >
                        <option value="17:00">17:00 น.</option>
                        <option value="18:00">18:00 น.</option>
                        <option value="19:00">19:00 น.</option>
                        <option value="20:00">20:00 น.</option>
                        <option value="21:00">21:00 น.</option>
                        <option value="22:00">22:00 น.</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col gap-2">
                      <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Guests (Pax)</label>
                      <input
                        type="number"
                        min="1"
                        max="30"
                        required
                        className="bg-[#131313] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                        value={pax}
                        onChange={(e) => setPax(e.target.value)}
                      />
                    </div>
                    <div className="flex flex-col gap-2">
                      <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Phone Number</label>
                      <input
                        type="tel"
                        placeholder="081-XXXXXXX"
                        required
                        className="bg-[#131313] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                        value={customerPhone}
                        onChange={(e) => setCustomerPhone(e.target.value)}
                      />
                    </div>
                  </div>

                  <div className="flex flex-col gap-2">
                    <label className="font-mono text-[12px] text-[#d3c5ac] uppercase">Customer Name</label>
                    <input
                      type="text"
                      placeholder="Enter your full name"
                      required
                      className="bg-[#131313] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                      value={customerName}
                      onChange={(e) => setCustomerName(e.target.value)}
                    />
                  </div>

                  <button
                    type="submit"
                    disabled={submitting}
                    className="w-full bg-[#ffd782] hover:bg-[#fff6df] text-[#3f2e00] font-['Anton'] text-[18px] uppercase py-4 transition-colors tracking-wide disabled:opacity-50"
                  >
                    {submitting ? "Booking..." : "Book Table Now"}
                  </button>
                </form>

                <p className="text-[12px] text-[#d3c5ac]/70 leading-relaxed font-sans border-t border-white/5 pt-4 m-0">
                  * Booking terms: Tables are held for 15 minutes past reservation time. After 15 minutes, your slot is released.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* LOOKUP & CANCELLATION SECTION */}
      <section className="w-full px-6 lg:px-16 py-16 bg-[#0e0e0e] border-t border-white/10 relative z-10">
        <div className="max-w-[1200px] mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          <div className="col-span-1 lg:col-span-4 flex flex-col gap-4">
            <h3 className="font-['Anton'] text-[24px] uppercase text-[#ffd782] m-0 tracking-wider">
              Manage Booking
            </h3>
            <p className="text-sm text-[#d3c5ac] leading-relaxed m-0">
              Did you already reserve a slot? Enter your phone number here to review your reservation status or cancel it.
            </p>
          </div>

          <div className="col-span-1 lg:col-span-8 bg-[#131313] border border-white/10 p-6 flex flex-col gap-6">
            <form onSubmit={handleSearch} className="flex gap-4">
              <input
                type="tel"
                placeholder="Enter phone number to search..."
                required
                className="flex-grow bg-[#201f1f] text-[#e5e2e1] p-3 border border-white/10 focus:border-[#ffd782] focus:outline-none text-sm"
                value={searchPhone}
                onChange={(e) => setSearchPhone(e.target.value)}
              />
              <button
                type="submit"
                disabled={searchLoading}
                className="bg-[#ffd782] text-[#3f2e00] font-['Anton'] uppercase px-6 text-sm tracking-wider hover:bg-[#fff6df] transition-colors"
              >
                {searchLoading ? "Searching..." : "Search"}
              </button>
            </form>

            {searchDone && searchResults.length === 0 && !searchLoading && (
              <div className="text-sm font-mono text-center py-6 text-[#605f5e]">
                No active bookings found for this phone number.
              </div>
            )}

            {searchResults.length > 0 && (
              <div className="flex flex-col gap-4">
                {searchResults.map((booking) => (
                  <div
                    key={booking.id}
                    className="bg-[#201f1f] p-4 border border-white/5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
                  >
                    <div className="font-mono text-xs flex flex-col gap-1">
                      <div>
                        Customer: <strong className="text-[#e5e2e1]">{booking.customerName}</strong>
                      </div>
                      <div>
                        Date/Time: <strong className="text-[#ffd782]">{booking.date} @ {booking.timeSlot} น.</strong>
                      </div>
                      <div>
                        Table Assigned:{" "}
                        <strong className="text-[#ffd782]">
                          {booking.table ? booking.table.number : "Pending auto-allocation"}
                        </strong>
                      </div>
                      <div>
                        Status:{" "}
                        <span
                          className={`font-bold ${
                            booking.status === "CONFIRMED"
                              ? "text-green-400"
                              : booking.status === "CANCELLED"
                              ? "text-red-400"
                              : "text-yellow-400"
                          }`}
                        >
                          {booking.status}
                        </span>
                      </div>
                    </div>

                    {booking.status !== "CANCELLED" && (
                      <button
                        onClick={() => handleCancelBooking(booking.id)}
                        className="bg-transparent border border-red-500 hover:bg-red-500 hover:text-black text-red-400 font-['Anton'] text-[12px] uppercase px-4 py-2 transition-all"
                      >
                        Cancel Booking
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </section>
    </div>
  );
}
