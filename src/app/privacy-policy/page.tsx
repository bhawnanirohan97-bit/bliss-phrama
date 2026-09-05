import type { Metadata } from "next";
import LegalDocument from "@/components/LegalDocument";

export const metadata: Metadata = {
  title: "Privacy Policy",
  description:
    "How Bliss Lifesciences collects, uses and protects personal information submitted through this website.",
  alternates: { canonical: "/privacy-policy" },
};

export default function PrivacyPolicyPage() {
  return (
    <LegalDocument
      title="Privacy Policy"
      updated="September 2026"
      lead="This Privacy Policy explains how Bliss Lifesciences Pvt. Ltd. collects, uses and safeguards information provided through this website. This is a B2B website for pharmaceutical sourcing inquiries; it is not an online pharmacy."
      sections={[
        {
          heading: "Information we collect",
          body: "We collect the information you choose to provide through our contact and quotation forms, including name, company, email address, phone number, country and the details of your inquiry. We may also collect basic technical information such as browser type and pages visited to improve site performance.",
        },
        {
          heading: "How we use your information",
          body: "Information submitted through forms is used solely to respond to your business inquiry, prepare quotations, and provide sales and export support. Technical data is used to maintain and improve the functionality, security and usability of the website.",
        },
        {
          heading: "Sharing your information",
          body: "We do not sell or rent your personal information. Information is shared within our organization and with manufacturing partners only to the extent necessary to respond to your inquiry or fulfill your order, and where required by law.",
        },
        {
          heading: "Data retention and security",
          body: "Inquiry data is retained only as long as necessary to serve your business request and meet legal obligations. We apply reasonable administrative, technical and physical safeguards to protect the information we hold.",
        },
        {
          heading: "Your rights",
          body: "You may request access to, correction of, or deletion of the personal information you have submitted by contacting us using the details on our Contact page. We will respond to such requests in accordance with applicable law.",
        },
        {
          heading: "Contact",
          body: "For privacy-related questions, contact our exports office through the details listed on the Contact page of this website.",
        },
      ]}
    />
  );
}