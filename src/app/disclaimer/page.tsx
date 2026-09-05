import type { Metadata } from "next";
import LegalDocument from "@/components/LegalDocument";

export const metadata: Metadata = {
  title: "Disclaimer",
  description:
    "Disclaimer for the Bliss Pharmex B2B pharmaceutical sourcing website.",
  alternates: { canonical: "/disclaimer" },
};

export default function DisclaimerPage() {
  return (
    <LegalDocument
      title="Disclaimer"
      updated="September 2026"
      lead="Please read this disclaimer carefully. It clarifies the scope of information provided on this website."
      sections={[
        {
          heading: "Regulatory and accreditation claims",
          body: "Quality, certification, infrastructure and export-related claims displayed on this website will be published only where documented and client-approved. Information may be updated when further verification becomes available.",
        },
        {
          heading: "Not an online pharmacy",
          body: "This website does not sell medicines to consumers, does not handle prescriptions and does not provide online purchase or payment facilities. All supply is B2B and subject to a formal quotation and purchase process.",
        },
        {
          heading: "Professional guidance",
          body: "Product information is intended for professional buyers and procurement teams. It is not a substitute for professional medical advice and should not be used to make health or treatment decisions by consumers.",
        },
        {
          heading: "External links",
          body: "This website may reference external resources such as regulatory bodies. We are not responsible for the content or accuracy of third-party websites.",
        },
      ]}
    />
  );
}