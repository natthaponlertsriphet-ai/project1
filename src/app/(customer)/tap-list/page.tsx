"use client";

import { useState, useEffect } from "react";

export default function BeerTapList() {
  const [beers, setBeers] = useState<any[]>([]);
  const [selectedFilter, setSelectedFilter] = useState("All");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchBeers() {
      try {
        const res = await fetch("/api/beers");
        if (res.ok) {
          const data = await res.json();
          setBeers(data);
        }
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    }
    fetchBeers();
  }, []);

  // Map database styles to general categories
  const getStyleCategory = (beerType: string) => {
    const type = beerType.toLowerCase();
    if (type.includes("ipa") || type.includes("pale") || type.includes("hoppy")) {
      return "Hoppy";
    }
    if (type.includes("lager") || type.includes("pilsner") || type.includes("crisp")) {
      return "Crisp";
    }
    if (type.includes("stout") || type.includes("porter") || type.includes("dark") || type.includes("belgian")) {
      return "Dark";
    }
    return "Fruity"; // Wheats, Sours, Ciders, etc.
  };

  const filteredBeers = beers.filter((beer) => {
    if (selectedFilter === "All") return beer.active;
    return beer.active && getStyleCategory(beer.type) === selectedFilter;
  });

  const categories = ["All", "Hoppy", "Crisp", "Dark", "Fruity"];

  return (
    <div className="flex flex-col w-full min-h-screen bg-[#131313] text-[#e5e2e1] font-['Work_Sans'] pt-20">
      
      {/* Decorative Top Block */}
      <div className="h-16 w-full"></div>

      {/* Page Header */}
      <div className="px-6 lg:px-16 w-full relative z-10 flex flex-col items-start mb-16 max-w-[1200px] mx-auto">
        <div className="flex items-center gap-4 mb-4">
          <span className="w-3 h-3 rounded-full bg-[#ffd782] animate-pulse shadow-[0_0_10px_rgba(255,215,130,0.8)]"></span>
          <span className="font-mono text-[#ffd782] tracking-[0.2em] uppercase text-xs">Live Draft Menu</span>
        </div>
        <h1 className="font-['Anton'] text-[48px] md:text-[72px] text-[#e5e2e1] uppercase leading-none m-0">
          Chiang Mai <br /> <span className="text-[#ffd782]">Branch Pours</span>
        </h1>
        <p className="text-[16px] md:text-[18px] text-[#d3c5ac] max-w-2xl mt-6 leading-relaxed m-0">
          16 taps of the freshest, most uncompromised craft brews. From crisp lagers to hop-heavy IPAs
          and deep stouts. The board updates as kegs blow.
        </p>
      </div>

      {/* Main Layout Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 px-6 lg:px-16 w-full pb-32 max-w-[1200px] mx-auto">
        
        {/* Left Column: Sidebar Filters & Info */}
        <div className="lg:col-span-4 flex flex-col gap-8 relative z-10">
          
          {/* Interactive Style Filter */}
          <div className="bg-[#201f1f] rounded-xl p-8 flex flex-col gap-6 shadow-xl border border-white/5">
            <h3 className="font-mono text-[#d3c5ac] uppercase tracking-widest text-xs m-0">
              Sort By Style
            </h3>
            <div className="flex flex-wrap gap-3">
              {categories.map((cat) => (
                <button
                  key={cat}
                  onClick={() => setSelectedFilter(cat)}
                  className={`px-4 py-2 font-['Anton'] text-sm uppercase rounded transition-all ${
                    selectedFilter === cat
                      ? "bg-[#ffd782] text-[#3f2e00] shadow-[0_0_15px_rgba(255,215,130,0.2)]"
                      : "bg-[#2a2a2a] text-[#e5e2e1] hover:bg-[#353534] hover:text-[#ffd782]"
                  }`}
                >
                  {cat === "All" ? "All Pours" : cat}
                </button>
              ))}
            </div>
          </div>

          {/* Atmospheric Image & Live Stats */}
          <div className="relative w-full h-[320px] rounded-xl overflow-hidden shadow-2xl border border-white/5">
            <div className="absolute inset-0 bg-gradient-to-t from-[#131313] via-transparent to-transparent z-10"></div>
            <div
              className="w-full h-full bg-cover bg-center mix-blend-luminosity opacity-80"
              style={{
                backgroundImage:
                  "url('https://lh3.googleusercontent.com/aida-public/AB6AXuDehm4gryD5GteeEhi8xKsolE3CKcoz9ca5A72-VK9zsdHvhbnZNEv7CInpHSoVY0SAaihS0Lc9AHhHyG4WVY7_20e87k3Xbc8_Ta9RqvwBQhj3tt6EEptMsFkINzvMpchQTPSSQ1TZdK2RbDWB1gmXegYApBUuqC6-qlwfskZDhfVnfVJgUp063Jy4dHsQ9bMAjqcyg-xeyhgXwptu98_6qiEUWNk0BUemZI52rP-MBTiDU00Tv_qxlGp-2fLQoiSS0AjQT8CSTwFH')",
              }}
            ></div>
            
            {/* Overlay Stats */}
            <div className="absolute bottom-6 left-6 z-20 flex gap-8">
              <div className="flex flex-col">
                <span className="font-['Anton'] text-[40px] text-[#ffd782] leading-none">
                  {beers.filter((b) => b.active).length}
                </span>
                <span className="font-mono text-[#d3c5ac] uppercase text-[10px]">Active Taps</span>
              </div>
              <div className="flex flex-col">
                <span className="font-['Anton'] text-[40px] text-[#e5e2e1] leading-none">04</span>
                <span className="font-mono text-[#d3c5ac] uppercase text-[10px]">Guest Brews</span>
              </div>
            </div>
          </div>

          {/* Activity Waves */}
          <div className="bg-[#0e0e0e] p-6 rounded-xl flex flex-col gap-4 border border-white/5">
            <span className="font-mono text-[#d3c5ac] uppercase text-[10px] tracking-wider">
              Taproom Energy Level
            </span>
            <svg className="w-full h-12 text-[#ffd782]" fill="none" stroke="currentColor" viewBox="0 0 200 40">
              <path
                d="M0 20 L20 20 L25 10 L35 30 L45 5 L55 25 L60 20 L100 20 L105 12 L115 28 L125 8 L135 24 L140 20 L200 20"
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth="2"
              ></path>
            </svg>
          </div>
        </div>

        {/* Right Column: Dynamic Beer List */}
        <div className="lg:col-span-8 flex flex-col gap-4">
          {loading ? (
            <div className="text-center font-mono py-12 text-[#d3c5ac]">Loading draft list...</div>
          ) : filteredBeers.length === 0 ? (
            <div className="text-center font-mono py-12 text-[#605f5e] border border-dashed border-white/10 rounded-lg">
              No active beers found for this style.
            </div>
          ) : (
            <div className="flex flex-col gap-4">
              {filteredBeers.map((beer) => (
                <div
                  key={beer.id}
                  className="group relative flex items-center justify-between p-6 bg-[#201f1f] hover:bg-[#2a2a2a] transition-all rounded-lg overflow-hidden cursor-pointer shadow-md hover:shadow-[0_0_20px_rgba(255,215,130,0.15)] border border-white/5"
                >
                  {/* Amber indicator on hover */}
                  <div className="absolute left-0 top-0 bottom-0 w-1 bg-[#ffd782] scale-y-0 group-hover:scale-y-100 transition-transform origin-bottom"></div>
                  
                  <div className="flex flex-col gap-1 min-w-0 pr-4">
                    <span className="font-mono text-[#ffd782] text-xs uppercase tracking-wider">
                      Tap {beer.tapNumber} • {beer.type}
                    </span>
                    <h4 className="font-['Anton'] text-[24px] uppercase text-[#e5e2e1] m-0 group-hover:text-[#ffd782] transition-colors">
                      {beer.name}
                    </h4>
                    <p className="font-sans text-[#d3c5ac] text-sm m-0 mt-1 line-clamp-1">
                      {beer.description}
                    </p>
                  </div>

                  <div className="flex items-center gap-4 shrink-0 font-mono">
                    <div className="flex flex-col items-end">
                      <div className="px-2.5 py-1 bg-[#ffd782]/10 rounded text-[#ffd782] text-xs font-bold">
                        ABV {beer.abv}
                      </div>
                      <div className="text-[10px] text-[#605f5e] mt-1">IBU {beer.ibu}</div>
                    </div>
                    <div className="text-[#ffd782] font-['Anton'] text-[20px] ml-2">
                      {beer.price}฿
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
