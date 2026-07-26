export default function Footer() {
  return (
    <footer className="w-full bg-[#0e0e0e] border-t border-white/10 py-12 mt-20 relative z-10 font-sans">
      <div className="max-w-[1200px] mx-auto px-6 lg:px-16 grid grid-cols-1 md:grid-cols-3 gap-12">
        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-[#ffd782] flex items-center justify-center rounded">
              <span className="material-symbols-outlined text-[#3f2e00] text-[20px] font-bold">sports_bar</span>
            </div>
            <span className="font-['Anton'] text-[20px] uppercase tracking-wider text-[#ffd782]">
              CHIT HOLE
            </span>
          </div>
          <p className="font-sans text-[#d3c5ac] text-sm max-w-xs leading-relaxed">
            Uncompromising Thai craft beer culture. High energy, workshop vibes, and nocturnal light.
          </p>
        </div>
        <div className="flex flex-col gap-4">
          <h4 className="font-['Anton'] text-[20px] uppercase text-[#ffd782] tracking-wider">San Sai Branch</h4>
          <p className="font-sans text-[#d3c5ac] text-sm leading-relaxed">
            San Sai District, Chiang Mai<br />
            Open Daily • 17:00 - 00:00
          </p>
        </div>
        <div className="flex flex-col gap-4">
          <h4 className="font-['Anton'] text-[20px] uppercase text-[#ffd782] tracking-wider">Follow Us</h4>
          <div className="flex gap-4">
            <span className="material-symbols-outlined text-[#d3c5ac] hover:text-[#ffd782] cursor-pointer text-[24px]">
              share
            </span>
            <span className="material-symbols-outlined text-[#d3c5ac] hover:text-[#ffd782] cursor-pointer text-[24px]">
              local_activity
            </span>
            <span className="material-symbols-outlined text-[#d3c5ac] hover:text-[#ffd782] cursor-pointer text-[24px]">
              rss_feed
            </span>
          </div>
        </div>
      </div>
      <div className="max-w-[1200px] mx-auto px-6 lg:px-16 mt-12 pt-8 border-t border-white/5 text-center text-[#605f5e] font-mono text-[11px] uppercase tracking-wider">
        © 2026 CHIT HOLE BREWING CO. ENGINEERED FOR IMPACT.
      </div>
    </footer>
  );
}
