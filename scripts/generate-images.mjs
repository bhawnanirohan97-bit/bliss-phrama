import { mkdirSync, writeFileSync } from "node:fs";
import { resolve } from "node:path";

const publicRoot = resolve("public");

const categories = {
  antibiotics: "Antibiotics",
  analgesics: "Analgesics",
  antidiabetics: "Antidiabetics",
  cardiovascular: "Cardiovascular",
  gastrointestinal: "Gastrointestinal",
  respiratory: "Respiratory",
  vitamins: "Vitamins & Supplements",
  dermatological: "Dermatological",
};

function categorySvg(label) {
  const lines = label.length > 14 ? label.split(" & ") : [label];
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" role="img" aria-label="${label}">
  <rect width="512" height="512" fill="#eef5fb"/>
  <g fill="#ffffff">
    <rect x="196" y="176" width="120" height="120" rx="28"/>
    <rect x="258" y="242" width="120" height="120" rx="28"/>
  </g>
  <path d="M232 226h48M256 202v48" stroke="#1f5aa8" stroke-width="10" stroke-linecap="round"/>
  <path d="M318 292h40M338 272v40" stroke="#0e7c7b" stroke-width="12" stroke-linecap="round"/>
  <g font-family="Segoe UI, Arial, sans-serif" fill="#0e4d92" text-anchor="middle">
    <text x="256" y="404" font-size="30" font-weight="700">${lines[0]}</text>
    ${
      lines[1]
        ? `<text x="256" y="440" font-size="24" font-weight="600">${lines[1]}</text>`
        : ""
    }
  </g>
</svg>
`;
}

function facilitySvg() {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" role="img" aria-label="Company facility placeholder">
  <rect width="1200" height="800" fill="#eef5fb"/>
  <rect x="120" y="300" width="960" height="380" rx="12" fill="#b4d0ee"/>
  <rect x="120" y="300" width="960" height="28" rx="12" fill="#1f5aa8"/>
  ${[0, 1, 2, 3, 4, 5, 6].map((i) => `<rect x="${170 + i * 120}" y="360" width="80" height="120" rx="6" fill="#d9e7f7"/>`).join("\n  ")}
  <rect x="440" y="480" width="320" height="200" fill="#528dd2"/>
  <rect x="560" y="520" width="80" height="132" rx="6" fill="#2f6fc0"/>
  <circle cx="250" cy="610" r="56" fill="#2f6fc0"/>
  <rect x="880" y="520" width="160" height="84" rx="12" fill="#339990"/>
  <g font-family="Segoe UI, Arial, sans-serif" fill="#0b335f" text-anchor="middle">
    <text x="600" y="120" font-size="46" font-weight="700">Bliss Pharmex</text>
    <text x="600" y="172" font-size="26" font-weight="600">WHO-GMP Compliant Manufacturing</text>
  </g>
  <g font-family="Segoe UI, Arial, sans-serif" fill="#0e4d92" text-anchor="middle">
    <rect x="330" y="712" width="540" height="56" rx="28" fill="#ffffff"/>
    <text x="600" y="748" font-size="24" font-weight="600">Facility image placeholder — replace with client photos</text>
  </g>
</svg>
`;
}

mkdirSync(`${publicRoot}/images/categories`, { recursive: true });
mkdirSync(`${publicRoot}/images/company`, { recursive: true });

for (const [slug, label] of Object.entries(categories)) {
  writeFileSync(
    `${publicRoot}/images/categories/${slug}.svg`,
    categorySvg(label)
  );
}

writeFileSync(`${publicRoot}/images/company/facility.svg`, facilitySvg());

console.log("Placeholder images generated.");