import Header from "../../components/Header";
import Footer from "../../components/Footer";

export default function CustomerLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <Header />
      <main className="w-full pt-20 flex-grow bg-[#121414] text-[#e3e2e2] font-['Hanken_Grotesk']">
        {children}
      </main>
      <Footer />
    </>
  );
}
