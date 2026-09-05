import type { Metadata } from "next";
import LegalDocument from "@/components/LegalDocument";

export const metadata: Metadata = {
  title: "Terms & Conditions",
  description:
    "Terms and conditions governing the use of the Bliss Pharmex B2B pharmaceutical sourcing website.",
  alternates: { canonical: "/terms-and-conditions" },
};

export default function TermsConditionsPage() {
  return (
    <LegalDocument
      title="Terms & Conditions"
      updated="September 2026"
      lead="By accessing and using this website you agree to the following terms and conditions. This website is a B2B procurement platform for pharmaceutical and nutraceutical products; it does not offer medicines for consumer sale."
      sections={[
        {
          heading: "Business use only",
          body: "This website is intended for business users — distributors, wholesalers, importers, healthcare organizations and institutional buyers. Products shown are supplied on a B2B basis subject to applicable regulatory requirements in the destination country.",
        },
        {
          heading: "No consumer medical advice",
          body: "Content on this website is for professional procurement purposes only and does not constitute medical advice, diagnosis or treatment recommendations. Consumers should consult a qualified healthcare professional before using any medicine.",
        },
        {
          heading: "Product availability",
          body: "Product listings indicate representative formulations. Availability, specifications, pricing, MOQs and registration status are confirmed individually by our sales team during the quotation process and are subject to change without notice.",
        },
        {
          heading: "Intellectual property",
          body: "All content on this website, including text, graphics, logos and imagery, is the property of Bliss Pharmex Pvt. Ltd. unless otherwise stated. Any regulatory-sensitive or accreditation claims remain subject to client approval before release.",
        },
        {
          heading: "Limitation of liability",
          body: "We work to keep website information accurate and current, but do not warrant that content is error-free or complete. To the fullest extent permitted by law, we disclaim liability for indirect or consequential loss arising from use of this website.",
        },
        {
          heading: "Governing law",
          body: "These terms are governed by the laws of the Republic of India. Any disputes shall be subject to the jurisdiction of the courts of New Delhi.",
        },
      ]}
    />
  );
}