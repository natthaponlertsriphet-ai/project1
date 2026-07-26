"use client";

import { useState, useEffect } from "react";

export default function AdminDashboard() {
  const [bookings, setBookings] = useState<any[]>([]);
  const [tables, setTables] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchData() {
      try {
        const [bookingsRes, tablesRes] = await Promise.all([
          fetch("/api/bookings"),
          fetch("/api/tables"),
        ]);
        if (bookingsRes.ok && tablesRes.ok) {
          const bookingsData = await bookingsRes.json();
          const tablesData = await tablesRes.json();
          setBookings(bookingsData);
          setTables(tablesData);
        }
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    }
    fetchData();
  }, []);

  // Aggregated Stats
  const totalBookings = bookings.length;
  const pendingBookings = bookings.filter((b) => b.status === "PENDING").length;
  const confirmedBookings = bookings.filter((b) => b.status === "CONFIRMED").length;
  const cancelledBookings = bookings.filter((b) => b.status === "CANCELLED").length;
  const totalGuests = bookings
    .filter((b) => b.status === "CONFIRMED")
    .reduce((sum, b) => sum + b.pax, 0);

  // Group by Day
  const dailyStats = bookings.reduce((acc: any, b) => {
    acc[b.date] = (acc[b.date] || 0) + 1;
    return acc;
  }, {});

  // Group by Month (YYYY-MM)
  const monthlyStats = bookings.reduce((acc: any, b) => {
    const month = b.date.substring(0, 7); // "YYYY-MM"
    acc[month] = (acc[month] || 0) + 1;
    return acc;
  }, {});

  // Group by Year (YYYY)
  const yearlyStats = bookings.reduce((acc: any, b) => {
    const year = b.date.substring(0, 4); // "YYYY"
    acc[year] = (acc[year] || 0) + 1;
    return acc;
  }, {});

  if (loading) {
    return (
      <div className="flex justify-center items-center py-12 font-mono text-[#d0c6ab]">
        CALCULATING STATISTICS REPORT...
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-8 w-full max-w-[1200px]">
      
      {/* 4 columns KPI grid */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        {/* KPI Card */}
        <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative overflow-hidden">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-[#ffd700]"></div>
          <div className="text-[12px] font-mono text-[#605f5e] uppercase tracking-wider">
            Total Bookings
          </div>
          <div className="font-['Anton'] text-[36px] text-[#ffd700] mt-2">
            {totalBookings}
          </div>
        </div>

        {/* KPI Card */}
        <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative overflow-hidden">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-yellow-500"></div>
          <div className="text-[12px] font-mono text-[#605f5e] uppercase tracking-wider">
            Pending Approval
          </div>
          <div className="font-['Anton'] text-[36px] text-yellow-500 mt-2">
            {pendingBookings}
          </div>
        </div>

        {/* KPI Card */}
        <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative overflow-hidden">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-green-500"></div>
          <div className="text-[12px] font-mono text-[#605f5e] uppercase tracking-wider">
            Confirmed Slots
          </div>
          <div className="font-['Anton'] text-[36px] text-green-500 mt-2">
            {confirmedBookings}
          </div>
        </div>

        {/* KPI Card */}
        <div className="bg-[#1b1c1c] border border-[#4d4732] p-6 relative overflow-hidden">
          <div className="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
          <div className="text-[12px] font-mono text-[#605f5e] uppercase tracking-wider">
            Total Confirmed Guests
          </div>
          <div className="font-['Anton'] text-[36px] text-blue-500 mt-2">
            {totalGuests} Pax
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left/Main Column: Booking Statistics Reports */}
        <div className="lg:col-span-7 bg-[#1b1c1c] border border-[#4d4732] p-6 flex flex-col gap-6">
          <h2 className="font-['Anton'] text-[24px] uppercase text-[#ffd700] m-0 border-b border-[#4d4732]/30 pb-3 flex items-center gap-2">
            <span className="material-symbols-outlined">bar_chart</span>
            Booking Statistics Report
          </h2>

          <div className="flex flex-col gap-6">
            {/* Daily Report */}
            <div>
              <h3 className="text-sm font-bold uppercase tracking-wider text-[#d0c6ab] mb-3">
                Daily Booking Counts
              </h3>
              <div className="border border-[#4d4732]/50 max-h-48 overflow-y-auto">
                <table className="w-full text-left font-mono text-xs">
                  <thead>
                    <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                      <th className="p-3">Date</th>
                      <th className="p-3 text-right">Bookings</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.keys(dailyStats).length === 0 ? (
                      <tr>
                        <td colSpan={2} className="p-3 text-center text-[#605f5e]">No data available</td>
                      </tr>
                    ) : (
                      Object.entries(dailyStats)
                        .sort((a, b) => b[0].localeCompare(a[0]))
                        .map(([date, count]: any) => (
                          <tr key={date} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                            <td className="p-3 text-[#e3e2e2]">{date}</td>
                            <td className="p-3 text-right text-[#ffd700] font-bold">{count}</td>
                          </tr>
                        ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Monthly Report */}
            <div>
              <h3 className="text-sm font-bold uppercase tracking-wider text-[#d0c6ab] mb-3">
                Monthly Booking Counts
              </h3>
              <div className="border border-[#4d4732]/50 max-h-32 overflow-y-auto">
                <table className="w-full text-left font-mono text-xs">
                  <thead>
                    <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                      <th className="p-3">Month</th>
                      <th className="p-3 text-right">Bookings</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.keys(monthlyStats).length === 0 ? (
                      <tr>
                        <td colSpan={2} className="p-3 text-center text-[#605f5e]">No data available</td>
                      </tr>
                    ) : (
                      Object.entries(monthlyStats)
                        .sort((a, b) => b[0].localeCompare(a[0]))
                        .map(([month, count]: any) => (
                          <tr key={month} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                            <td className="p-3 text-[#e3e2e2]">{month}</td>
                            <td className="p-3 text-right text-[#ffd700] font-bold">{count}</td>
                          </tr>
                        ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Yearly Report */}
            <div>
              <h3 className="text-sm font-bold uppercase tracking-wider text-[#d0c6ab] mb-3">
                Yearly Booking Counts
              </h3>
              <div className="border border-[#4d4732]/50">
                <table className="w-full text-left font-mono text-xs">
                  <thead>
                    <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                      <th className="p-3">Year</th>
                      <th className="p-3 text-right">Bookings</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.keys(yearlyStats).length === 0 ? (
                      <tr>
                        <td colSpan={2} className="p-3 text-center text-[#605f5e]">No data available</td>
                      </tr>
                    ) : (
                      Object.entries(yearlyStats)
                        .sort((a, b) => b[0].localeCompare(a[0]))
                        .map(([year, count]: any) => (
                          <tr key={year} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                            <td className="p-3 text-[#e3e2e2]">{year}</td>
                            <td className="p-3 text-right text-[#ffd700] font-bold">{count}</td>
                          </tr>
                        ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        {/* Right/Secondary Column: Seating Status Summary */}
        <div className="lg:col-span-5 bg-[#1b1c1c] border border-[#4d4732] p-6 flex flex-col gap-6">
          <h2 className="font-['Anton'] text-[24px] uppercase text-[#ffd700] m-0 border-b border-[#4d4732]/30 pb-3 flex items-center gap-2">
            <span className="material-symbols-outlined">table_chart</span>
            Tables Overview
          </h2>

          <div className="flex flex-col gap-4">
            <div className="grid grid-cols-3 gap-4 text-center">
              <div className="bg-[#121414] border border-[#4d4732] p-3">
                <div className="text-[10px] font-mono text-[#605f5e] uppercase">Total Tables</div>
                <div className="text-xl font-bold font-mono text-[#ffd700]">{tables.length}</div>
              </div>
              <div className="bg-[#121414] border border-[#4d4732] p-3">
                <div className="text-[10px] font-mono text-[#605f5e] uppercase">Indoor Zone</div>
                <div className="text-xl font-bold font-mono text-[#e3e2e2]">
                  {tables.filter((t) => t.zone === "INDOOR").length}
                </div>
              </div>
              <div className="bg-[#121414] border border-[#4d4732] p-3">
                <div className="text-[10px] font-mono text-[#605f5e] uppercase">Outdoor / Stage</div>
                <div className="text-xl font-bold font-mono text-[#e3e2e2]">
                  {tables.filter((t) => t.zone !== "INDOOR").length}
                </div>
              </div>
            </div>

            <h3 className="text-xs font-bold uppercase tracking-wider text-[#d0c6ab] mt-2 mb-1">
              Table Configuration List
            </h3>
            
            <div className="border border-[#4d4732]/50 max-h-80 overflow-y-auto">
              <table className="w-full text-left font-mono text-[11px]">
                <thead>
                  <tr className="bg-[#0d0e0f] text-[#605f5e] border-b border-[#4d4732]/50">
                    <th className="p-2">Table #</th>
                    <th className="p-2">Zone</th>
                    <th className="p-2 text-right">Capacity</th>
                  </tr>
                </thead>
                <tbody>
                  {tables.map((t) => (
                    <tr key={t.id} className="border-b border-[#4d4732]/20 hover:bg-[#121414]">
                      <td className="p-2 text-[#ffd700] font-bold">{t.number}</td>
                      <td className="p-2 text-[#e3e2e2]">{t.zone}</td>
                      <td className="p-2 text-right text-[#d0c6ab]">{t.capacity} Pax</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
