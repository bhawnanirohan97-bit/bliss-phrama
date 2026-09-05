import Link from "next/link";
import SiteNav from "@/components/SiteNav";

export default function Header() {
  return (
    <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-5 sm:px-8">
        <Link href="/" className="flex items-center gap-2.5">
          <span className="flex h-9 w-9 items-center justify-center rounded-md bg-brand-700">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2}
              className="h-5 w-5 text-white"
              aria-hidden
            >
              <path d="M12 6v12M6 12h12" />
            </svg>
          </span>
          <span className="flex flex-col leading-tight">
            <span className="text-base font-semibold tracking-tight text-slate-900">
              Bliss Lifesciences
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