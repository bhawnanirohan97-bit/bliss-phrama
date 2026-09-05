import { writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const BASE = "https://www.blisspharmex.com";

const FLAGSHIP = [
  "anti-cancer-tablets",
  "steroid-injections",
  "tadalafil-tablets",
  "sildenafil-tablets",
  "steroids-tablets",
  "anti-cancer-injection",
  "steroid-tablets-and-injections",
  "anti-cancer-capsule",
];

const NOISE = [
  "products-and-services.html",
  "profile.html",
  "contact-us.html",
  "contact.html",
  "enquiry.html",
  "testimonial.html",
  "real-time-status.html",
  "sitemap.html",
  "index.html",
  "about-us.html",
  "about.html",
  "home.html",
  "medi-software.html",
  "privacy-policy.html",
  "terms-condition.html",
  "payment.html",
  "header.html",
];

function slugify(value) {
  return value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .replace(/-{2,}/g, "-");
}

function clean(entity) {
  return entity.replace(/&nbsp;/g, " ").replace(/\s+/g, " ").trim();
}

function formatPrice(value) {
  return clean(value)
    .replace(/^Rs\s?/i, "\u20B9 ")
    .replace(/\s+/g, " ")
    .trim();
}

const SPEC_KEYS = {
  strength: ["Strength", "Dose/Strength", "Dosage/Strength", "Dosage"],
  form: ["Form", "Dosage Form", "Drug Form", "Dosage form"],
  packaging: [
    "Packaging Size",
    "Pack Size",
    "Tablets per Pack",
    "Capsules per Pack",
    "Pack  Size",
  ],
  composition: [
    "Composition",
    "Composition of",
    "Drug Composition",
    "Active Ingredient",
  ],
  manufacturer: ["Manufacturer", "Manufactured By", "Manufacturer Name"],
  uses: ["Treatment", "Uses", "Usage/Application", "Used For", "Indication"],
};

const FORM_HINTS = [
  ["tablet", "Tablets"],
  ["injection", "Injection"],
  ["capsule", "Capsules"],
  ["cream", "Cream"],
  ["gel", "Gel"],
  ["jelly", "Jelly"],
  ["solution", "Solution"],
  ["syrup", "Syrup"],
  ["inhaler", "Inhaler"],
  ["ointment", "Ointment"],
  ["drop", "Drops"],
  ["tablet", "Tablets"],
];

function buildSpecMap(specs) {
  const map = {};
  for (const spec of specs ?? []) {
    const key = spec.FK_IM_SPEC_MASTER_DESC;
    const value = spec.SUPPLIER_RESPONSE_DETAIL;
    if (key && value && !map[key]) map[key] = value;
  }
  return map;
}

function pick(specMap, keys) {
  for (const key of keys) {
    if (specMap[key]) return specMap[key];
  }
  return undefined;
}

const STRENGTH_RE = /(\d+(?:\.\d+)?)\s?(mg|mcg|gm|g|ml|iu|mg\/ml)/i;

function deriveStrength(name, specMap) {
  const known = pick(specMap, SPEC_KEYS.strength);
  if (known) return known;
  const match = name.match(STRENGTH_RE);
  return match ? `${match[1]} ${match[2]}` : "";
}

function deriveForm(name, categoryName, specMap) {
  const known = pick(specMap, SPEC_KEYS.form);
  if (known) return known;
  const needle = `${name} ${categoryName}`.toLowerCase();
  for (const [hint, form] of FORM_HINTS) {
    if (needle.includes(hint)) return form;
  }
  return "Tablets";
}

const PRICE_UNIT_RE = /\/(\s*)(strip|bottle|box|vial|tube|pack|unit|pieces?|capsules?|tablets?|injections?|blister|set)/i;

function derivePackaging(specMap, price, form) {
  const known = pick(specMap, SPEC_KEYS.packaging);
  if (known) return `${known}`;
  const match = formatPrice(price).match(PRICE_UNIT_RE);
  const base = form === "Tablets" ? "Strip" : form;
  return match ? `${match[1] ? "" : ""}${match[2]}` : `${base}`;
}

function buildDescription(name, specMap) {
  const bits = [];
  const composition = pick(specMap, SPEC_KEYS.composition);
  const manufacturer = pick(specMap, SPEC_KEYS.manufacturer);
  const uses = pick(specMap, SPEC_KEYS.uses);
  if (composition) bits.push(`Composition: ${composition}.`);
  if (uses) bits.push(`Indicated for ${uses}.`);
  if (manufacturer) bits.push(`Manufactured by ${manufacturer}.`);
  bits.push(
    "Supplied by Bliss Pharmex with certificates of analysis, stability data and export documentation on request."
  );
  return `${name} is a quality-assured pharmaceutical offered for B2B supply. ${bits.join(
    " "
  )}`;
}

function buildTags(specMap, dosageForm, categoryName) {
  const tags = new Set([dosageForm.toLowerCase()]);
  const composition = pick(specMap, SPEC_KEYS.composition);
  if (composition) {
    composition
      .split(/[(),\s]+/)
      .filter((w) => w.length > 2)
      .forEach((w) => tags.add(w.toLowerCase()));
  }
  const manufacturer = pick(specMap, SPEC_KEYS.manufacturer);
  if (manufacturer) tags.add(manufacturer.toLowerCase());
  tags.add(categoryName.split(" ")[0].toLowerCase());
  return [...tags];
}

async function fetchText(url) {
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
      if (response.ok) return await response.text();
    } catch (error) {
      if (attempt === 2) throw error;
    }
    await new Promise((resolve) => setTimeout(resolve, 1500));
  }
  throw new Error(`Undeliverable: ${url}`);
}

function extractData(html) {
  const match = html.match(
    /dataref1\s*=\s*eval\((\[[\s\S]*?\])\s*\);\s*var CAT_NAME = '([^']+)'/
  );
  if (!match) return null;
  const [, rawArray, categoryName] = match;
  let items;
  const sanitize = (text) =>
    text.replace(/\\'/g, "'").replace(/[\x00-\x1f]/g, " ");
  try {
    items = JSON.parse(sanitize(rawArray));
  } catch {
    items = JSON.parse(
      sanitize(rawArray).replace(/,\s*([}\]])/g, "$1")
    );
  }
  return { items, categoryName };
}

async function listCategories() {
  const html = await fetchText(`${BASE}/sitemap.html`);
  const slugs = new Set();
  for (const match of html.matchAll(/href="([a-z0-9-]+\.html)"/g)) {
    const file = match[1];
    if (file.endsWith(".html") && !NOISE.includes(file)) {
      slugs.add(file.replace(/\.html$/, ""));
    }
  }
  const rest = [...slugs]
    .filter((slug) => !FLAGSHIP.includes(slug))
    .sort((a, b) => a.localeCompare(b));
  return [...FLAGSHIP, ...rest];
}

async function main() {
  const slugs = await listCategories();
  const known = new Set();
  const products = [];
  const categories = [];

  for (const slug of slugs) {
    let html;
    try {
      html = await fetchText(`${BASE}/${slug}.html`);
    } catch (error) {
      console.log(`FETCH-FAIL ${slug} — ${error.message}`);
      continue;
    }
    const extracted = extractData(html);
    if (!extracted || extracted.items.length === 0) {
      console.log(`EMPTY ${slug}`);
      continue;
    }

    const { items, categoryName } = extracted;
    const categoryProducts = [];
    const appended = [];

    for (const raw of items) {
      const id = String(raw.prd_id ?? "");
      if (!id || known.has(id)) continue;
      known.add(id);

      const name = clean(raw.prd_name ?? "");
      if (!name) continue;

      const specMap = buildSpecMap(raw.isq_det_form);
      const dosageForm = deriveForm(name, categoryName, specMap);
      const strength = deriveStrength(name, specMap);
      const price = formatPrice(raw.prd_price ?? "");
      const packaging = derivePackaging(specMap, raw.prd_price ?? "", dosageForm);
      const description = buildDescription(name, specMap);
      const tags = buildTags(specMap, dosageForm, categoryName);
      const image =
        (raw.img_path ?? raw.img_path1 ?? "").replace(
          "-500x500",
          "-500x500"
        ) || "";

      let productSlug = slugify(name);
      let unique = productSlug;
      let counter = 2;
      while (known.has(`slug:${unique}`)) {
        unique = `${productSlug}-${counter}`;
        counter += 1;
      }
      known.add(`slug:${unique}`);

      const product = {
        id,
        slug: unique,
        name,
        categorySlug: slug,
        dosageForm,
        strength,
        packaging,
        moq: "As per requirement",
        price,
        description,
        image,
        featured: false,
        tags,
      };
      appended.push(product);
    }

    if (appended.length === 0) {
      console.log(`SKIP ${slug}`);
      continue;
    }

    if (appended.length > 0 && FLAGSHIP.includes(slug)) {
      appended[0].featured = true;
    }
    for (const product of appended) {
      products.push(product);
    }
    categoryProducts.push(...appended);

    categories.push({
      slug,
      name: categoryName,
      description: `Quality ${categoryName.toLowerCase()} formulations supplied by Bliss Pharmex for distributors, wholesalers, importers and institutional buyers across export markets.`,
      image: categoryProducts[0].image,
    });

    console.log(
      `OK ${slug.padEnd(36)} ${categoryName.padEnd(28)} ${String(appended.length).padStart(3)} products`
    );
    await new Promise((resolve) => setTimeout(resolve, 250));
  }

  const dataDir = path.join(ROOT, "src", "data");
  writeFileSync(
    path.join(dataDir, "products.json"),
    JSON.stringify(products, null, 2) + "\n"
  );
  writeFileSync(
    path.join(dataDir, "categories.json"),
    JSON.stringify(categories, null, 2) + "\n"
  );

  console.log("\nCategories: " + categories.length);
  console.log("Products: " + products.length);
  const featured = products.filter((p) => p.featured).length;
  console.log("Featured: " + featured);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});