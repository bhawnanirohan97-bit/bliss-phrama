import {
  CheckIcon,
  DocumentIcon,
  GlobeIcon,
  ShieldIcon,
} from "@/components/icons";

const ITEMS = [
  {
    icon: ShieldIcon,
    title: "WHO-GMP Quality",
    text: "Manufactured in WHO-GMP compliant facilities",
  },
  {
    icon: DocumentIcon,
    title: "Full Documentation",
    text: "Dossiers, CoA, stability & registration support",
  },
  {
    icon: GlobeIcon,
    title: "Global Supply",
    text: "Exporting to 30+ markets across 5 regions",
  },
  {
    icon: CheckIcon,
    title: "Verified Compliance",
    text: "ISO 9001:2015 certified quality systems",
  },
];

export default function TrustStrip() {
  return (
    <section className="border-b border-slate-800 bg-slate-950">
      <div className="mx-auto grid w-full max-w-7xl grid-cols-1 gap-6 px-5 py-10 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
        {ITEMS.map((item) => (
          <div key={item.title} className="flex items-start gap-3.5">
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white/[0.05] text-brand-400 ring-1 ring-slate-800">
              <item.icon className="h-5 w-5" />
            </span>
            <div>
              <h3 className="text-sm font-semibold text-slate-100">
                {item.title}
              </h3>
              <p className="mt-1 text-sm leading-5 text-slate-400">{item.text}</p>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}