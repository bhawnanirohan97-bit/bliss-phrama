"use client";

import { useState } from "react";
import { getCompany } from "@/lib/data";
import { WhatsAppIcon } from "@/components/icons";

const inputClass =
  "w-full rounded-md border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100";

const labelClass =
  "block text-sm font-semibold text-slate-700";

type Status = "idle" | "submitting" | "success";

export default function ContactForm() {
  const company = getCompany();
  const [status, setStatus] = useState<Status>("idle");
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    company: "",
    country: "",
    message: "",
  });

  const update =
    (key: keyof typeof form) =>
    (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
      setForm((prev) => ({ ...prev, [key]: event.target.value }));

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setStatus("submitting");
    window.setTimeout(() => setStatus("success"), 800);
  }

  const whatsappMessage = `Hello ${company.name}, my name is ${form.name}. ${form.message}`;

  return (
    <div>
      {status === "success" ? (
        <div className="rounded-lg border border-green-200 bg-green-50 p-6">
          <h3 className="text-lg font-semibold text-green-900">
            Thank you — message received
          </h3>
          <p className="mt-2 text-sm leading-6 text-green-800">
            Our sales team will get back to you shortly. For a faster response,
            reach us directly on WhatsApp.
          </p>
          <a
            href={`https://wa.me/${company.whatsappHref}?text=${encodeURIComponent(
              whatsappMessage
            )}`}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-4 inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-700"
          >
            <WhatsAppIcon className="h-4 w-4" />
            Continue on WhatsApp
          </a>
        </div>
      ) : (
        <form onSubmit={handleSubmit} noValidate className="space-y-5">
          <div className="grid gap-5 sm:grid-cols-2">
            <div>
              <label htmlFor="contact-name" className={labelClass}>
                Full name <span className="text-red-500">*</span>
              </label>
              <input
                id="contact-name"
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
              <label htmlFor="contact-email" className={labelClass}>
                Work email <span className="text-red-500">*</span>
              </label>
              <input
                id="contact-email"
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
              <label htmlFor="contact-phone" className={labelClass}>
                Phone / WhatsApp
              </label>
              <input
                id="contact-phone"
                type="tel"
                autoComplete="tel"
                value={form.phone}
                onChange={update("phone")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="+91 00000 00000"
              />
            </div>
            <div>
              <label htmlFor="contact-company" className={labelClass}>
                Company
              </label>
              <input
                id="contact-company"
                type="text"
                autoComplete="organization"
                value={form.company}
                onChange={update("company")}
                className={`mt-1.5 ${inputClass}`}
                placeholder="Company name"
              />
            </div>
          </div>
          <div>
            <label htmlFor="contact-country" className={labelClass}>
              Country
            </label>
            <input
              id="contact-country"
              type="text"
              autoComplete="country-name"
              value={form.country}
              onChange={update("country")}
              className={`mt-1.5 ${inputClass}`}
              placeholder="Your country"
            />
          </div>
          <div>
            <label htmlFor="contact-message" className={labelClass}>
              Message <span className="text-red-500">*</span>
            </label>
            <textarea
              id="contact-message"
              required
              rows={5}
              value={form.message}
              onChange={update("message")}
              className={`mt-1.5 resize-y ${inputClass}`}
              placeholder="Tell us about your requirement…"
            />
          </div>
          <button
            type="submit"
            disabled={status === "submitting"}
            className="inline-flex items-center justify-center rounded-md bg-brand-700 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800 disabled:opacity-60"
          >
            {status === "submitting" ? "Sending…" : "Send Message"}
          </button>
        </form>
      )}
    </div>
  );
}