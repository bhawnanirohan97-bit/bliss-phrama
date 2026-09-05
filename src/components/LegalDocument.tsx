import Breadcrumbs from "@/components/Breadcrumbs";

export type Section = {
  heading: string;
  body: string;
};

export default function LegalDocument({
  title,
  updated,
  lead,
  sections,
}: {
  title: string;
  updated: string;
  lead: string;
  sections: Section[];
}) {
  return (
    <div className="bg-slate-50">
      <div className="border-b border-slate-200 bg-white">
        <div className="mx-auto w-full max-w-3xl px-5 py-10 sm:px-8">
          <Breadcrumbs items={[{ label: "Home", href: "/" }, { label: title }]} />
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900">
            {title}
          </h1>
          <p className="mt-3 text-sm font-medium text-slate-500">
            Last updated: {updated}
          </p>
          <p className="mt-4 text-base leading-7 text-slate-600">{lead}</p>
        </div>
      </div>

      <div className="mx-auto w-full max-w-3xl px-5 py-12 sm:px-8">
        <div className="space-y-10">
          {sections.map((section, index) => (
            <section key={section.heading}>
              <h2 className="text-xl font-semibold tracking-tight text-slate-900">
                {index + 1}. {section.heading}
              </h2>
              <p className="mt-3 text-base leading-7 text-slate-600">
                {section.body}
              </p>
            </section>
          ))}
        </div>
      </div>
    </div>
  );
}