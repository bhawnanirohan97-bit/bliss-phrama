"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { CloseIcon, MenuIcon } from "@/components/icons";

const NAV_LINKS = [
  { href: "/", label: "Home" },
  { href: "/products", label: "Products" },
  { href: "/contact", label: "Contact" },
] as const;

function isActive(pathname: string, href: string): boolean {
  if (href === "/") return pathname === "/";
  return pathname.startsWith(href);
}

export default function SiteNav() {
  const pathname = usePathname();
  const [open, setOpen] = useState(false);

  return (
    <>
      <nav className="hidden items-center gap-1 md:flex" aria-label="Main">
        {NAV_LINKS.map((link) => (
          <Link
            key={link.href}
            href={link.href}
            aria-current={isActive(pathname, link.href) ? "page" : undefined}
            className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
              isActive(pathname, link.href)
                ? "text-brand-800"
                : "text-slate-600 hover:bg-slate-50 hover:text-brand-700"
            }`}
          >
            {link.label}
          </Link>
        ))}
        <Link
          href="/request-quote"
          className="ml-3 inline-flex items-center rounded-md bg-brand-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
        >
          Request a Quote
        </Link>
      </nav>

      <button
        type="button"
        className="inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-700 hover:bg-slate-100 md:hidden"
        onClick={() => setOpen(true)}
        aria-expanded={open}
        aria-label="Open menu"
      >
        <MenuIcon className="h-6 w-6" />
      </button>

      {open && (
        <div className="fixed inset-0 z-50 bg-white md:hidden">
          <div className="flex h-full flex-col">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
              <Link
                href="/"
                className="flex items-center gap-2.5"
                onClick={() => setOpen(false)}
              >
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
                <span className="text-lg font-semibold tracking-tight text-slate-900">
                  Bliss Lifesciences
                </span>
              </Link>
              <button
                type="button"
                className="inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-700 hover:bg-slate-100"
                onClick={() => setOpen(false)}
                aria-label="Close menu"
              >
                <CloseIcon className="h-6 w-6" />
              </button>
            </div>
            <nav className="flex flex-col gap-1 px-5 py-6" aria-label="Mobile">
              {NAV_LINKS.map((link) => (
                <Link
                  key={link.href}
                  href={link.href}
                  onClick={() => setOpen(false)}
                  className={`rounded-md px-3 py-3 text-base font-medium ${
                    isActive(pathname, link.href)
                      ? "bg-brand-50 text-brand-800"
                      : "text-slate-700 hover:bg-slate-50"
                  }`}
                >
                  {link.label}
                </Link>
              ))}
              <Link
                href="/request-quote"
                onClick={() => setOpen(false)}
                className="mt-4 inline-flex items-center justify-center rounded-md bg-brand-700 px-4 py-3 text-base font-semibold text-white hover:bg-brand-800"
              >
                Request a Quote
              </Link>
            </nav>
          </div>
        </div>
      )}
    </>
  );
}