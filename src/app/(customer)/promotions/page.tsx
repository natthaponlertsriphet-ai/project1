"use client";

import { useState, useEffect } from "react";
import Link from "next/link";

export default function PromotionsPage() {
  const [music, setMusic] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function fetchMusic() {
      try {
        const res = await fetch("/api/music");
        if (res.ok) {
          const data = await res.json();
          setMusic(data);
        }
      } catch (e) {
        console.error(e);
      } finally {
        setLoading(false);
      }
    }
    fetchMusic();
  }, []);

  return (
    <div className="flex flex-col w-full min-h-screen bg-[#131313] text-[#e5e2e1] font-['Work_Sans'] pt-20">
      {/* Hero / Header Section */}
      <div className="w-full px-6 lg:px-16 py-16 md:py-24 relative overflow-hidden flex items-end max-w-[1200px] mx-auto">
        <div className="absolute -top-40 -right-40 w-[600px] h-[600px] bg-[#ffd782]/5 rounded-full blur-[100px] pointer-events-none"></div>
        <div className="absolute top-10 left-10 w-2 h-16 bg-[#ffd782] shadow-[0_0_20px_rgba(255,215,130,0.5)]"></div>
        
        <div className="relative z-10 max-w-3xl">
          <span className="font-mono text-[#ffd782] mb-4 block tracking-[0.2em] uppercase text-xs">
            Chit Hole Experiences
          </span>
          <h1 className="font-['Anton'] text-[48px] md:text-[72px] text-[#e5e2e1] uppercase leading-[0.9] m-0">
            Promotions <br />
            <span className="text-[#353534]">&amp; Events</span>
          </h1>
          <p className="mt-8 text-[16px] md:text-[18px] text-[#d3c5ac] leading-relaxed m-0 max-w-xl">
            Stay hydrated, stay energized. From our signature Lady Night to fitness rewards, discover what's
            pouring this week at Chiang Mai's premier craft taproom.
          </p>
        </div>
      </div>

      {/* Featured Promotions Bento Grid */}
      <div className="w-full px-6 lg:px-16 pb-24 relative z-20 max-w-[1200px] mx-auto">
        <div className="grid grid-cols-1 xl:grid-cols-12 gap-8">
          
          {/* Lady Night Promo (Spans 7 columns) */}
          <div className="xl:col-span-7 bg-[#201f1f] rounded-sm shadow-2xl relative overflow-hidden group flex flex-col md:flex-row border border-white/5">
            {/* Left Edge highlight */}
            <div className="absolute top-0 left-0 w-1 h-full bg-[#ffd782] z-30 shadow-[0_0_15px_rgba(255,215,130,0.6)]"></div>
            
            <div className="w-full md:w-5/12 h-64 md:h-auto relative overflow-hidden">
              <div
                className="absolute inset-0 bg-cover bg-center group-hover:scale-105 transition-transform duration-1000 ease-out grayscale-[0.2]"
                style={{
                  backgroundImage:
                    "url('https://lh3.googleusercontent.com/aida-public/AB6AXuDnc0uDipAtglr4cQD3Q8EVMhMa_w1ZJ4v1CYzZ4AIS5jnQbKc6-bBufL8rpPWLIAlHA3ZrsamkxvYniRG_Nv_qw5BLfefsM3kEzmxHhSoHP_mqSphozYfd4xqgqJt4BwovdonLv9aS2mmVyVxBcukWcgSg52wkCw-ogIUsm1RtFzqKnRV7BHFWxn5o9KsZiutMtGH6XDLLLVxt1EH3tdYk57jh7n0isq1dbsS7qzpOh2TT5q15bRoS4IRHHveG9Li1ZImtbP2qd1bq')",
                }}
              ></div>
              <div className="absolute inset-0 bg-gradient-to-t from-[#201f1f] via-transparent to-transparent md:bg-gradient-to-r md:from-transparent md:to-[#201f1f] opacity-100"></div>
            </div>

            <div className="w-full md:w-7/12 p-8 md:p-12 flex flex-col justify-center bg-[#201f1f] relative z-10">
              <div className="inline-block bg-[#ffd782]/10 text-[#ffd782] font-mono text-[11px] uppercase px-3 py-1 mb-6 self-start backdrop-blur-md rounded-sm border border-[#ffd782]/20">
                Every Thursday
              </div>
              <h2 className="font-['Anton'] text-[32px] md:text-[40px] text-[#e5e2e1] uppercase leading-none mb-4 tracking-wide m-0">
                Lady Night
              </h2>
              <p className="text-sm text-[#d3c5ac] leading-relaxed mb-8 m-0">
                Assemble the crew. Groups of 3 or more women arriving before 20:00 receive a complimentary
                pitcher of our featured craft pour. High energy, heavy pours.
              </p>
              <Link
                href="/reservation"
                className="w-fit bg-[#ffd782] text-[#3f2e00] font-['Anton'] text-[18px] uppercase py-3 px-8 shadow-[0_0_15px_rgba(255,215,130,0.2)] hover:shadow-[0_0_25px_rgba(255,215,130,0.5)] transition-all duration-300 flex items-center justify-center gap-2"
              >
                <span className="material-symbols-outlined text-[20px]">local_bar</span>
                Book a Table
              </Link>
            </div>
          </div>

          {/* Run & Walk Rewards (Spans 5 columns) */}
          <div className="xl:col-span-5 bg-[#201f1f] rounded-sm shadow-2xl relative overflow-hidden group flex flex-col border border-white/5">
            <div className="absolute top-0 left-0 w-1 h-full bg-[#ffd782]/60 z-30"></div>
            <div className="w-full h-48 relative overflow-hidden">
              <div
                className="absolute inset-0 bg-cover bg-center group-hover:scale-105 transition-transform duration-1000 ease-out opacity-80 mix-blend-luminosity"
                style={{
                  backgroundImage:
                    "url('https://lh3.googleusercontent.com/aida-public/AB6AXuDjk0bRGEkD_qfKgYLWjUfCUjpslYFTXL1SA9pyHK2MxszeEI43APZ0kYm5oXvwIeYDe8C0SWCLAqr280NyRoi9Cv0sul7uBauiqgsBQ2UECpjU3DAKXT_KFZ58I4ZvofqMQnnNN0JB3_L4M2oWtm48soR-gkkcL9-8WEmgEadrI6TWuby3MaJ7PhxDDV2zwSBRPkJ9fG66dauRgFXOVLPA3n2pXtLYKgy8kw3q-1t2rr9f6-qeWHL0pJ2ADbGMzQ-d3CxZ6qdFT38f')",
                }}
              ></div>
              <div className="absolute inset-0 bg-gradient-to-b from-transparent to-[#201f1f]"></div>
            </div>

            <div className="w-full p-8 flex-1 flex flex-col bg-[#201f1f] relative z-10 -mt-10">
              <span className="font-mono text-[11px] text-[#ffd782] mb-1 block uppercase">Daily Challenge</span>
              <h2 className="font-['Anton'] text-[28px] text-[#e5e2e1] uppercase mb-4 m-0">
                Run &amp; Walk Rewards
              </h2>
              <p className="text-sm text-[#d3c5ac] mb-6 m-0 leading-relaxed">
                Trade your daily steps for taproom fuel. Flash your step counter to the bartender and
                claim your reward.
              </p>
              
              <ul className="flex flex-col gap-3 font-mono text-[12px] mt-auto p-0 m-0 list-none">
                <li className="flex items-center justify-between p-3 bg-[#1c1b1b] border border-white/5 hover:bg-[#2a2a2a] group transition-all">
                  <span className="text-[#e5e2e1] group-hover:text-[#ffd782]">6,500 STEPS</span>
                  <span className="text-[#d3c5ac] uppercase text-[10px]">Free Snack</span>
                </li>
                <li className="flex items-center justify-between p-3 bg-[#1c1b1b] border border-white/5 hover:bg-[#2a2a2a] group transition-all">
                  <span className="text-[#e5e2e1] group-hover:text-[#ffd782]">7,500 STEPS</span>
                  <span className="text-[#d3c5ac] uppercase text-[10px]">Free Fries</span>
                </li>
                <li className="flex items-center justify-between p-3 bg-[#2a2a2a] border border-[#ffd782]/20 relative overflow-hidden">
                  <div className="absolute left-0 top-0 h-full w-1 bg-[#ffd782]"></div>
                  <span className="text-[#ffd782] font-bold ml-2">12,500 STEPS</span>
                  <span className="text-[#e5e2e1] uppercase font-bold text-[10px]">Free Burger Set</span>
                </li>
              </ul>
            </div>
          </div>

        </div>
      </div>

      {/* Live Music Schedule Section */}
      <div className="w-full px-6 lg:px-16 py-24 bg-[#0e0e0e] border-t border-white/10 relative">
        <div className="max-w-[1200px] mx-auto relative z-10 flex flex-col lg:flex-row gap-12">
          
          <div className="w-full lg:w-1/3 flex flex-col">
            <div className="w-16 h-16 bg-[#201f1f] flex items-center justify-center rounded-sm mb-6 shadow-lg border border-white/5">
              <span className="material-symbols-outlined text-[#ffd782] text-[32px]">graphic_eq</span>
            </div>
            <h2 className="font-['Anton'] text-[48px] leading-tight text-[#e5e2e1] uppercase mb-6 m-0">
              Live<br />Sessions
            </h2>
            <p className="text-sm text-[#d3c5ac] leading-relaxed mb-8 m-0 max-w-sm">
              Raw acoustic sets and high-energy local bands. Arrive early to secure your spot near the stage.
            </p>
            <div className="hidden lg:block w-full h-px bg-white/10 mt-auto relative">
              <div className="absolute -top-[3px] left-0 w-2 h-2 bg-[#ffd782] rounded-full"></div>
            </div>
          </div>

          <div className="w-full lg:w-2/3 flex flex-col gap-6 font-mono text-sm">
            {loading ? (
              <div className="text-[#d3c5ac] py-6">Loading schedule lineup...</div>
            ) : music.length === 0 ? (
              <div className="text-[#605f5e] py-6">No scheduled sessions.</div>
            ) : (
              <div className="flex flex-col gap-4">
                {music.map((item) => (
                  <div
                    key={item.id}
                    className="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-[#131313] border border-white/5 hover:border-[#ffd782]/40 transition-colors"
                  >
                    <div className="flex items-center gap-6">
                      <span className="text-[#ffd782] font-bold uppercase min-w-[100px]">
                        {item.day}
                      </span>
                      <div className="flex flex-col">
                        <span className="text-[#e5e2e1] font-sans font-bold text-base uppercase">
                          {item.artist}
                        </span>
                        <span className="text-[#605f5e] text-xs uppercase">{item.time}</span>
                      </div>
                    </div>
                    <span className="text-[#d3c5ac] text-xs uppercase border border-white/10 px-2 py-0.5 rounded mt-2 sm:mt-0 w-fit">
                      {item.status}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>

        </div>
      </div>
    </div>
  );
}
