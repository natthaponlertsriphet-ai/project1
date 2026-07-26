"use client";

import { useState } from "react";

export default function GalleryPage() {
  const [lightboxImage, setLightboxImage] = useState<string | null>(null);

  const galleryItems = [
    {
      id: 1,
      title: "The Workshop",
      tag: "Featured",
      src: "https://lh3.googleusercontent.com/aida-public/AB6AXuCCQlC3mQSx3eZAxpKjbhjrMsvPTfW7qCrK65ULfJ3B1MvIfcyLzbUaIPF2yEOv6EsY82JSi7BofpS1ue0cC90JN2HS08CJrr-hCfNE8FsOhzaBSrI46SATq90VsfPfVV_odioyFzX4FjgatUe7ClaXzp227ZGrEfgE0MiTS-m7fZkRrAN9cWuVTtFR1eoo_q--GHChIcJoEzsdqUVdUck7K_WnaTgcBCU_a06ascoRAc0cg2eiIJqANj9USezE3Scl14CeGSm0-gaJ",
      gridClass: "col-span-2 row-span-2 rounded-2xl overflow-hidden bg-[#201f1f] shadow-xl relative group cursor-pointer border border-white/5",
    },
    {
      id: 2,
      title: "Fresh Pour",
      tag: "Craft",
      src: "https://lh3.googleusercontent.com/aida-public/AB6AXuACB_WAcFNnKdnjJO_qijsZ9xrRGoqqeBJP0a1GN7WdhOQTviHmMrXHnL6I_iHFrU3-5Var-IDJgOVNF2SWf6dO09_RL27SWj5xNDETBqZ7WrdxyM6mZXhz7b1HPzOKc71Vy9H03CdtbjjqUMBxu0WLsvJnDlWKPvlYR229SpKEl7yFz6B6jCkYLU5J7T_wludxmUmLn1UrYxT6FvmMCiALqFRDmmtM-_ZKGsLcOy5elYyh0k8kkOnP4RdU8W-Q5IyNhtjlfs4ZlaeK",
      gridClass: "col-span-2 md:col-span-1 row-span-1 rounded-2xl overflow-hidden bg-[#201f1f] shadow-md relative group cursor-pointer border border-white/5",
    },
    {
      id: 3,
      title: "Social Scene",
      tag: "Vibe",
      src: "https://lh3.googleusercontent.com/aida-public/AB6AXuB5MgQCPq5r2jjoTl3v-4Y-k8WCtzutmhCDJAukg8iNV337gaNdbGbWtE_wLfGfzkZ96LhIapcBDjyWdvuOBTl80nT6uISsEYAhHQH-sBVafbcLGBPs7dzIrKcM4SgLztC3s7Xh8A8IdISoqvNgyA4jModx92ee-sLdg6hY8PiEdsve9vU62WwsQRgmtznzKPMNErbMuPIEIP39Hy_VI1LkN370Q3gGyYOKb-r0PpAS8WcaR_sLY_4auYeGNLNf4eE3I37wawoVu-DM",
      gridClass: "col-span-2 md:col-span-1 row-span-1 rounded-2xl overflow-hidden bg-[#201f1f] shadow-md relative group cursor-pointer border border-white/5",
    },
    {
      id: 4,
      title: "Neon Details",
      tag: "Nocturnal Vibe",
      src: "https://lh3.googleusercontent.com/aida-public/AB6AXuB6UxSJzIiYZhnrzRSo61ikFlXpgoq9CsamSrdYX5AruVl-zMbtSBmsL-L62gw2DRpR_IQ02qlhhnSjTgkvBE00-Uq-ceBlQtxooiwXqajcbQE25St9K3zAoo8n6jaGTR3Vn3PDx8TQMPd_cBkqhhvfdav-fqFXzvKzfDV-JpoETfPoP6C1T7-mY871biqBKDmE0pLsBeka8IFSr0iPm0j13GQYDPYLNXfJwNpAhfN_paGNZrskiwrRJFFHE2ZgmGaxa4lngfNIZMFa",
      gridClass: "col-span-2 row-span-1 rounded-2xl overflow-hidden bg-[#201f1f] shadow-md relative group cursor-pointer border border-white/5",
    },
    {
      id: 5,
      title: "Brewing Process",
      tag: "Workshop",
      src: "https://lh3.googleusercontent.com/aida-public/AB6AXuB3izFKtAmTdw4LiQzKbQj7opE7sqDLF1hvPU7Qut77Ll7BoGob_AGgkaM--xxW6PXpaXJeQoAG9ISk6ay4r5qFBaqTKCmkXQfBm48tYethK19uMLHl2eUQQ2WNO5vukL2MqVyO8LNg2-EAvmUeT1Q3qd9qh9hLL2wj14GgQ4U949jNUPimgjCZsOWNqq7oCFXirOnf1ksOzpgqjie4SEa04B_EIikRFO5NKme3BtN6Fn6uNpZNz5Unf2zkDWmDgQIZvF7K3kbSDiS-",
      gridClass: "col-span-2 md:col-span-1 row-span-1 rounded-2xl overflow-hidden bg-[#201f1f] shadow-md relative group cursor-pointer border border-white/5",
    },
  ];

  return (
    <div className="flex flex-col w-full min-h-screen bg-[#131313] text-[#e5e2e1] font-['Work_Sans'] pt-20">
      
      {/* Atmosphere Intro */}
      <section className="w-full px-6 lg:px-16 py-20 lg:py-32 flex flex-col md:flex-row items-end justify-between gap-8 relative z-10 max-w-[1200px] mx-auto">
        <div className="max-w-2xl flex flex-col gap-6 relative">
          <div className="absolute -top-20 -left-20 w-64 h-64 bg-[#ffd782]/10 rounded-full blur-[80px] pointer-events-none"></div>
          <div className="flex items-center gap-4">
            <div className="w-2 h-2 bg-[#ffd782] rounded-full shadow-[0_0_10px_rgba(255,215,130,0.8)] animate-pulse"></div>
            <span className="font-mono text-xs text-[#ffd782] uppercase tracking-widest">San Sai Branch</span>
          </div>
          <h2 className="font-['Anton'] text-[48px] md:text-[64px] text-[#e5e2e1] uppercase leading-tight m-0">
            Raw Grit. <br />
            <span className="text-[#353534] mix-blend-screen">Liquid Gold.</span>
          </h2>
          <p className="text-[16px] md:text-[18px] text-[#d3c5ac] leading-relaxed m-0">
            The San Sai taproom captures the unfiltered essence of Thai craft beer culture. Stepping inside
            feels like entering an industrial workshop, where heavy machinery meets the warm, nocturnal glow
            of neon amber. It's high-energy, unapologetic, and built for the authentic social experience.
          </p>
        </div>

        <div className="hidden lg:flex flex-col items-end gap-2 text-right">
          <span className="font-mono text-[12px] text-[#d3c5ac] uppercase">Gallery View</span>
          <span className="font-mono text-[#ffd782] tracking-widest">[ 01 ] - ATMOSPHERE</span>
        </div>
      </section>

      {/* Masonry / Bento Gallery Grid */}
      <section className="w-full px-6 lg:px-16 pb-24 relative z-20 max-w-[1200px] mx-auto">
        <div className="w-full grid grid-cols-2 md:grid-cols-4 gap-4 auto-rows-[180px] md:auto-rows-[280px]">
          {galleryItems.map((item) => (
            <div
              key={item.id}
              onClick={() => setLightboxImage(item.src)}
              className={item.gridClass}
            >
              <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent z-10 opacity-70 group-hover:opacity-40 transition-opacity duration-500"></div>
              
              <div
                className="w-full h-full bg-cover bg-center transition-transform duration-700 ease-out group-hover:scale-105 filter brightness-90 group-hover:brightness-100"
                style={{ backgroundImage: `url('${item.src}')` }}
              ></div>

              <div className="absolute bottom-6 left-6 z-20 flex flex-col items-start">
                <span className="font-mono text-[10px] px-3.5 py-1 bg-[#131313]/90 backdrop-blur-md text-[#ffd782] uppercase inline-block mb-2 rounded shadow-md border border-white/5">
                  {item.tag}
                </span>
                <h3 className="font-['Anton'] text-lg text-[#e5e2e1] uppercase m-0 leading-none tracking-wide">
                  {item.title}
                </h3>
              </div>

              {/* Hover Zoom-in Button Effect */}
              <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20">
                <div className="w-12 h-12 bg-[#ffd782]/95 backdrop-blur-md rounded-full flex items-center justify-center shadow-[0_0_20px_rgba(255,215,130,0.5)]">
                  <span className="material-symbols-outlined text-[#3f2e00] font-bold">zoom_in</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Interactive Lightbox Overlay Modal */}
      {lightboxImage && (
        <div
          onClick={() => setLightboxImage(null)}
          className="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 backdrop-blur-2xl transition-opacity duration-300"
        >
          <button
            onClick={() => setLightboxImage(null)}
            className="absolute top-8 right-8 w-14 h-14 bg-[#201f1f] rounded-full flex items-center justify-center hover:bg-[#ffd782] group transition-colors duration-300 shadow-xl z-50 border border-white/10"
          >
            <span className="material-symbols-outlined text-[#e5e2e1] group-hover:text-[#3f2e00] transition-colors">
              close
            </span>
          </button>
          
          <div
            className="relative w-full max-w-4xl max-h-[85vh] p-4 flex items-center justify-center"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="absolute inset-0 bg-[#ffd782]/5 blur-[100px] -z-10 rounded-full"></div>
            <img
              src={lightboxImage}
              alt="Expanded gallery view"
              className="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl border border-white/10"
            />
          </div>
        </div>
      )}
    </div>
  );
}
