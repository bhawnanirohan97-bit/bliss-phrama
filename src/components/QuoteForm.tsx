"use client";

import { useState } from "react";
import type { Product } from "@/lib/types";
import { getCompany } from "@/lib/data";
import { WhatsAppIcon } from "@/components/icons";

const inputClass =
  "w-full rounded-md border border-slate-700 bg-slate-900 px-3.5 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20";

const labelClass = "block text-sm font-semibold text-slate-300";

type Status = "idle" | "submitting" | "success";

export default function QuoteForm({
  products,
  initialProductSlug,
}: {
  products: Product[];
  initialProductSlug?: string;
}) {
  const company = getCompany();
  const [status, setStatus] = useState<Status>("idle");
  const [form, setForm] = useState({
    name: "",
    company: "",
    email: "",
    phone: "",
    productSlug: initialProductSlug ?? "",
    quantity: "",
    country: "",
    message: "",
  });

  const update =
    (key: keyof typeof form) =>
    (
      event: React.ChangeEvent<
        HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
      >
    ) =>
      setForm((prev) => ({ ...prev, [key]: event.target.value }));

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setStatus("submitting");
    window.setTimeout(() => setStatus("success"), 800);
  }

  const selectedProduct = products.find(
    (product) => product.slug === form.productSlug
  );

  const whatsappMessage = `Hello ${company.name}, I would like a quotation.%0A%0A${[
    form.name ? `Name: ${form.name}` : "",
    form.company ? `Company: ${form.company}` : "",
    form.country ? `Country: ${form.country}` : "",
    selectedProduct ? `Product: ${selectedProduct.name}` : "",
    form.quantity ? `Quantity: ${form.quantity}` : "",
    form.message ? `Requirements: ${form.message}` : "",
  ]
    .filter(Boolean)
    .join("%0A")}`;

  return (
    <div>
      {status === "success" ? (
        <div className="rounded-lg border border-green-500/30 bg-green-500/10 p-6">
          <h3 className="text-lg font-semibold text-green-200">
            Thank you — your quote request has been received
          </h3>
          <p className="mt-2 text-sm leading-6 text-green-300">
            Our exports team will prepare a quotation and contact you within one
            business day. For urgent requirements, message us directly.
          </p>
          <a
            href={`https://wa.me/${company.whatsappHref}?text=${whatsappMessage}`}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-4 inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-700"
          >
            <WhatsAppIcon className="h-4 w-4" />
            Send via WhatsApp
          </a>
        </div>
      ) : (
        <form onSubmit={handleSubmit} noValidate className="space-y-5">
          <div className="grid gap-5 sm:grid-cols-2">
            <div>
              <label htmlFor="quote-name" className={labelClass}>
                Full name <span className="text-red-500">*</span>
              </label>
              <input
                id="quote-name"
                type="text"
                required
                autoComplete="name"
                value={form.name}
                onChange={update("name")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="Your full name"
              />
            </div>
            <div>
              <label htmlFor="quote-company" className={labelClass}>
                Company <span className="text-red-500">*</span>
              </label>
              <input
                id="quote-company"
                type="text"
                required
                autoComplete="organization"
                value={form.company}
                onChange={update("company")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="Company name"
              />
            </div>
            <div>
              <label htmlFor="quote-email" className={labelClass}>
                Work email <span className="text-red-500">*</span>
              </label>
              <input
                id="quote-email"
                type="email"
                required
                autoComplete="email"
                value={form.email}
                onChange={update("email")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="you@company.com"
              />
            </div>
            <div>
              <label htmlFor="quote-phone" className={labelClass}>
                Phone / WhatsApp
              </label>
              <input
                id="quote-phone"
                type="tel"
                autoComplete="tel"
                value={form.phone}
                onChange={update("phone")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="+91 00000 00000"
              />
            </div>
            <div>
              <label htmlFor="quote-product" className={labelClass}>
                Product of interest
              </label>
              <select
                id="quote-product"
                value={form.productSlug}
                onChange={update("productSlug")}
                className={`mt-1.5 ${inputClass}`}
              >
                <option value="">General / multiple products</option>
                {products.map((product) => (
                  <option key={product.id} value={product.slug}>
                    {product.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label htmlFor="quote-quantity" className={labelClass}>
                Estimated quantity
              </label>
              <input
                id="quote-quantity"
                type="text"
                value={form.quantity}
                onChange={update("quantity")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="e.g. 50,000 tablets / 2,000 packs"
              />
            </div>
          </div>
          <div>
            <label htmlFor="quote-country" className={labelClass}>
              Country
            </label>
            <input
              id="quote-country"
              type="text"
              autoComplete="country-name"
              value={form.country}
              onChange={update("country")}
              className={`mt-1.5 ${inputClass}`}
              placeholder="Destination country"
            />
          </div>
          <div>
            <label htmlFor="quote-message" className={labelClass}>
              Requirement details <span className="text-red-500">*</span>
            </label>
            <textarea
              id="quote-message"
              required
              rows={5}
              value={form.message}
              onChange={update("message")}
              className={`mt-1.5 resize-y ${inputClass}`}
              placeholder="Packaging preference, regulatory documents needed, target markets…"
            />
          </div>
          <button
            type="submit"
            disabled={status === "submitting"}
            className="inline-flex items-center justify-center rounded-md bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700 disabled:opacity-60"
          >
            {status === "submitting" ? "Submitting…" : "Submit Quote Request"}
          </button>
        </form>
      )}
    </div>
  );
}