import Link from "next/link";
import SiteNav from "@/components/SiteNav";

export default function Header() {
  return (
    <header className="sticky top-0 z-40 border-b border-slate-800 bg-slate-950/90 backdrop-blur">
      <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-5 sm:px-8">
        <Link href="/" className="flex items-center gap-3">
          <img
            src="/images/company/logo.png"
            alt="Bliss Pharmex"
            style={{
              height: "40px",
              width: "auto",
              borderRadius: "6px",
              background: "#ffffff",
              padding: "4px",
            }}
          />
        </Link>
        <SiteNav />
      </div>
    </header>
  );
}