import Image from "next/image";
import Link from "next/link";
import SiteNav from "@/components/SiteNav";

export default function Header() {
  return (
    <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-5 sm:px-8">
        <Link href="/" className="flex items-center gap-2.5">
          <span className="relative h-10 w-10 overflow-hidden">
            <Image
              src="/images/company/logo.png"
              alt="Bliss Pharmex"
              fill
              sizes="40px"
              className="object-contain"
            />
          </span>
          <span className="flex flex-col leading-tight">
            <span className="text-base font-semibold tracking-tight text-slate-900">
              Bliss Pharmex
            </span>
            <span className="text-[11px] font-medium uppercase tracking-widest text-brand-700">
              Pharma Exporter
            </span>
          </span>
        </Link>
        <SiteNav />
      </div>
    </header>
  );
}