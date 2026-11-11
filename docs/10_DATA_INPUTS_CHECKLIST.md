# PharmaLead AI — Data Inputs Checklist

Version 1.0 · Owner of every item: **the office operator** · Prepared by: developer · Phase map: M0–M7

---

## 1. Purpose

This is the operator-facing checklist of everything the office must supply before the system can be built, tuned and promoted. Each item names its exact format and columns, the phase it unblocks, whether it blocks progress, and what happens if it is unavailable. Supply items in ID order; I1–I5 unblock the v1 build, I6–I10 unblock go-live and v1.1.

Two rules govern every item:

1. **No invented data.** If a value is unknown, leave the cell blank and mark it in the status column. A guessed price, country status or API field is worse than a blank.
2. **Redact before sharing.** Buyer names, phone numbers and emails are replaced with the placeholders in section 13 before any file leaves the operator's machine. The inquiry text itself stays verbatim.

Status legend: `Supplied` · `Partial` · `Pending` · `Not applicable`.

---

## 2. Master input table

| ID | Item | Format and exact column specification | Phase unblocked | Blocking? | Fallback if unavailable |
|---|---|---|---|---|---|
| I1 | Product master | CSV, UTF-8, header: `sku,brand_name,generic_name,strength,dosage_form,pack_size,unit,indicative_price,export_eligible,restricted_countries` | M2 catalogue and matcher | Yes | None. The matcher cannot run without the catalogue |
| I2 | Country rules | CSV, UTF-8, header: `iso2,name,status,reason,is_proven_seller` | M3 policy engine | Yes | None. Missing rows default to `review`, which would hard-stop most drafts |
| I3 | Synonyms | CSV, UTF-8, header: `term,kind,sku` | M2 matcher | No | Generic-plus-strength matching only; expect top-1 accuracy below the 85% gate |
| I4 | Evaluation dataset | CSV, UTF-8, header: `id,inquiry_text,expected_sku,expected_destination_iso2,expected_buyer_type,expected_intent,label,source_week` | M0 harness; all gates | Yes | None. No metric is trusted without it |
| I5 | Live portal export | CSV or XLSX exactly as the seller portal produces it, plus a field mapping note | M1 normaliser; S3 contract tests | Yes | API access (I7) if the portal has no export; otherwise blocked |
| I6 | Commercial terms | Filled table (section 8) | M3 allowance ledger and waste metric | Yes for waste reporting; no for allocation | Allocation still works with `allotted = 75`; waste shows as unknown until the fee is supplied |
| I7 | API access confirmation | Written answers to the questions in section 9 | M1 connector | No | Manual portal export (I5) as the ingest source |
| I8 | Proven-seller countries | CSV or a marked column in I2 (`is_proven_seller = true`) | M3 destination suitability | No | Default every country to `false`; the destination component scores conservatively |
| I9 | AI data-egress decision | Written decision record (section 11) | M4 LLM extraction (v1.1) | No for v1 | `AI_PROVIDER=null`; deterministic-only |
| I10 | Sales roster | CSV or a filled table (section 12) | M3 RBAC; pilot contact | Yes for pilot | A single admin account; no sales or viewer separation |

---

## 3. Item I1 — Product master CSV

### 3.1 Exact header row

```csv
sku,brand_name,generic_name,strength,dosage_form,pack_size,unit,indicative_price,export_eligible,restricted_countries
```

### 3.2 Column rules

| Column | Type | Rule |
|---|---|---|
| `sku` | string, unique | Internal stable identifier. Never reuse a retired SKU. Example: `SKU-AMOX-500-CAP` |
| `brand_name` | string | Our brand name exactly as it appears on the pack. Leave blank for generic-only entries |
| `generic_name` | string | INN or common generic name, lower case. Example: `amoxicillin` |
| `strength` | string | Value plus unit, no space. Example: `500mg`, `250mg/5ml` |
| `dosage_form` | enum | One of `tablet`, `capsule`, `syrup`, `injection`, `cream`, `drops`, `inhaler`, `sachet`, `suppository` |
| `pack_size` | integer | Number of units in one pack. Example: `10` for a strip of 10 tablets, `100` for a bottle of 100 |
| `unit` | enum | One of `tablet`, `capsule`, `ml`, `vial`, `tube`, `sachet`, `piece`, `box` |
| `indicative_price` | decimal, 2 places | Indicative price in INR for one pack. Powers the commercial-value component. Leave blank only if genuinely unknown |
| `export_eligible` | boolean | `true` or `false`. `false` means the product must never receive a draft |
| `restricted_countries` | pipe-separated ISO2 | Countries where this product is restricted, even if the country is otherwise approved. Example: `US\|CA\|AU`. Empty means no product-specific restriction |

### 3.3 Worked sample rows

```csv
sku,brand_name,generic_name,strength,dosage_form,pack_size,unit,indicative_price,export_eligible,restricted_countries
SKU-AMOX-500-CAP,Amoxil-500,amoxicillin,500mg,capsule,10,capsule,85.00,true,
SKU-METF-500-TAB,Glycomet-500,metformin,500mg,tablet,15,tablet,42.50,true,
SKU-PARA-500-TAB,Calpol-500,paracetamol,500mg,tablet,15,tablet,28.00,true,US|CA
```

### 3.4 Guidance

- **Include pack size.** Buyers write "500 tabs" or "5000 strips", not SKUs. Pack size lets the matcher convert a buyer quantity into packs and lets the draft state a comparable unit. A catalogue without pack size forces every quantity to be guessed.
- **Include indicative price.** It powers the 20-point commercial-value component and makes the draft's price band defensible. An approximate INR price is acceptable; a blank is not, unless the product genuinely has none.
- **Mark `export_eligible = false`** for anything not exportable: unregistered formulations, controlled substances, products with no export paperwork. This is a hard gate. A false `false` blocks a valid sale; a missed `false` ships an illegal draft. Prefer `false` when uncertain and correct it later.
- **List restricted countries explicitly.** A product can be legal to export but restricted in one destination. Use ISO2 codes separated by `|`. The gate checks the destination against both the country rule and this column.
- One row per sellable pack. The same molecule in two pack sizes is two rows. Never merge strengths into one row.
- Keep the file under 5 MB; the full 1000+ SKU catalogue usually lands around 300–600 KB.

**Fallback:** none. Without I1 the matcher cannot run and v1 cannot ship.

---

## 4. Item I2 — Country rules CSV

### 4.1 Exact header row

```csv
iso2,name,status,reason,is_proven_seller
```

| Column | Type | Rule |
|---|---|---|
| `iso2` | string, 2 letters, upper case | ISO 3166-1 alpha-2 |
| `name` | string | Country name in English |
| `status` | enum | `approved`, `blocked`, `review` |
| `reason` | string | Short justification. Required for `blocked` and `review` |
| `is_proven_seller` | boolean | `true` if we have shipped to this country before |

### 4.2 Allowed status values

| Status | Meaning | Effect on scoring and drafts |
|---|---|---|
| `approved` | We sell here and hold the required registrations | Destination suitability can score full points; drafts allowed if all other gates pass |
| `blocked` | We do not sell here, or export is prohibited or sanctioned | Hard reject; no score, no draft, no send log |
| `review` | Not yet assessed, or needs a human decision per order | Not auto-contacted; routed to `review` band; no draft until an admin changes the status |

**Every country defaults to `review`.** A country with no row is treated as `review`, never `approved`. This guarantees a missing row can never cause an accidental purchase or an illegal draft. Only an explicit `approved` row unlocks automatic drafting.

### 4.3 Ten worked sample rows

```csv
iso2,name,status,reason,is_proven_seller
IN,India,approved,domestic sales and dispatch,true
AE,United Arab Emirates,approved,registered distributor and prior shipments,true
NG,Nigeria,approved,active distributor and prior shipments,true
KE,Kenya,approved,registered products and prior shipments,true
TZ,Tanzania,approved,registered products,false
VN,Vietnam,approved,registered products,false
GB,United Kingdom,review,regulated market; registrations not held,false
SA,Saudi Arabia,review,SFDA registration pending,false
US,United States,blocked,regulated market; FDA registrations not held,false
RU,Russian Federation,blocked,export restrictions and payment constraints,false
```

### 4.4 Guidance

- Treat `GB`, `US`, `EU` members and other regulated markets as `review` or `blocked` until a registration is actually held. Do not mark a country `approved` because a buyer asked.
- Add a row for every country that has ever appeared in an inquiry. Do not leave a frequent destination absent and rely on the default.
- Update `reason` whenever a status changes; the reason is shown to the operator and stored in the audit trail.
- The CSV uses three operator-facing statuses. The engine maps them to its internal classes: `approved` + `is_proven_seller = true` → `allowed_proven`; `approved` + `is_proven_seller = false` → `allowed_unproven`; `review` → `restricted`; `blocked` → `blocked`; a missing row → `unknown`, treated exactly as `review`.

**Fallback:** none. The policy engine needs an explicit allowlist to avoid accidental drafts.

---

## 5. Item I3 — Synonyms CSV

### 5.1 Exact header row

```csv
term,kind,sku
```

| Column | Type | Rule |
|---|---|---|
| `term` | string, lower case | The word or phrase as buyers write it |
| `kind` | enum | `generic`, `brand`, `trade`, `misspell`, `short`, `regional`, `strength_alias` |
| `sku` | string | Must exist in I1. One term maps to one SKU; list multiple rows for multiple mappings |

### 5.2 Kinds

| Kind | Meaning | Example |
|---|---|---|
| `generic` | Generic name variant | `amoxycillin` → amoxicillin |
| `brand` | Our own brand name | `amoxil` |
| `trade` | Another manufacturer's trade name buyers use | `mox` |
| `misspell` | Common misspelling | `amoxicilin`, `metformine` |
| `short` | Abbreviation or shorthand | `amox`, `met` |
| `regional` | Regional or Hinglish term | `bukhar ki goli` (fever tablet) |
| `strength_alias` | Different strength expression | `500` meaning 500mg when the generic is unambiguous |

### 5.3 Three worked rows

```csv
term,kind,sku
amox,short,SKU-AMOX-500-CAP
amoxicilin,misspell,SKU-AMOX-500-CAP
glycomet,trade,SKU-METF-500-TAB
```

### 5.4 Guidance

- Build synonyms from the catalogue and from the training split of I4 only. Never add a synonym after reading a holdout row; that is leakage.
- A term that maps to two SKUs is ambiguous. Do not add it; fix the catalogue or add the strength to the term (`metformin 500`).
- Revisit synonyms after each evaluation run; add only those that fix a real, recorded miss.

**Fallback:** generic-plus-strength matching only. Expect top-1 accuracy below 85% and a failed build gate.

---

## 6. Item I4 — Evaluation dataset CSV

### 6.1 Exact header row

```csv
id,inquiry_text,expected_sku,expected_destination_iso2,expected_buyer_type,expected_intent,label,source_week
```

| Column | Type | Rule |
|---|---|---|
| `id` | string, unique | `EV-0001` style |
| `inquiry_text` | string | **Verbatim** enquiry text after contact redaction. Do not correct spelling, grammar or Hinglish |
| `expected_sku` | string or blank | The correct SKU, or blank when the correct answer is no match |
| `expected_destination_iso2` | string or blank | Two-letter destination, or blank when the inquiry does not state one |
| `expected_buyer_type` | enum | `importer`, `distributor`, `wholesaler`, `pharmacy`, `hospital`, `personal`, `job_vendor`, `unknown` |
| `expected_intent` | enum | `purchase`, `price_enquiry`, `sample_request`, `job`, `spam`, `unknown` |
| `label` | enum | `good`, `no_match`, `junk`, `personal` |
| `source_week` | ISO week | `2026-W40` style |
| `expected_quantity` | string, optional add-on | Only if the file is extended; the base header above is authoritative |

### 6.2 Allowed labels

| Label | Meaning | Examples |
|---|---|---|
| `good` | Matches a product we sell, destination we sell to, and the buyer is a legitimate business contact | Importer asking for a quote on a catalogue SKU |
| `no_match` | A legitimate pharma enquiry, but no catalogue product or no approved destination | Asks for a molecule we do not carry |
| `junk` | Not a buyer: job seeker, vendor pitch, spam, unrelated | "We supply packaging material, interested?" |
| `personal` | End consumer wanting a small personal quantity | "Need 2 strips for my father" |

### 6.3 Four worked sample rows

```csv
id,inquiry_text,expected_sku,expected_destination_iso2,expected_buyer_type,expected_intent,label,source_week
EV-0001,"Need Amoxy 500 capsules 10000 pcs urgent. Nairobi Kenya. Pls send best price",SKU-AMOX-500-CAP,KE,importer,purchase,good,2026-W41
EV-0002,"metformin hcl 500 mg tablets chahiye 5000 strips delhi",SKU-METF-500-TAB,IN,distributor,purchase,good,2026-W41
EV-0003,"Looking for azithromycin 250 mg, 2 strips for personal use, my father is sick",,IN,personal,purchase,personal,2026-W41
EV-0004,"We are hiring sales executives. Kindly share your CV to hr@example.invalid",,,job_vendor,job,junk,2026-W41
```

### 6.4 Redaction rules

- Replace every buyer name with `BUYER_1`, `BUYER_2`, … in order of first appearance.
- Replace every Indian mobile number with `+91XXXXXXXXX`; replace other numbers with `+CCXXXXXXXXX` using the country code and the same number of masked digits.
- Replace every email with `buyerN@example.invalid`, where `N` matches the buyer index.
- Remove company names when they identify a person (for example `Sharma Traders` → `BUYER_COMPANY_1`); keep company names that do not identify an individual only if the operator confirms they are public.
- **Keep the inquiry text verbatim otherwise.** It is the model's input; correcting it destroys the signal the matcher must learn to handle.
- Do not redact product names, strengths, quantities or destinations; they are the label's evidence.

### 6.5 Size

| Rows | What the dataset supports |
|---|---|
| 30 | A working signal; debugging only |
| 100 | A decision; gate a phase promotion |
| 200 labelled outcomes | Outcome-based tuning of weights and calibration |

Start with at least 100 labelled rows for the first gate. Add rows every week from the live inbox; each row must be labelled before it is used.

**Fallback:** none. Without I4 no quality gate can be proven.

---

## 7. Item I5 — One live portal export

Supply one complete, unmodified export from the seller portal for a real week, plus a short field-mapping note.

### 7.1 Required fields to capture

| Field | Why it is needed |
|---|---|
| Portal lead id | `external_id` for idempotent ingest |
| Received timestamp (with time zone) | Arrival time and week assignment |
| Inquiry text | The matcher's primary input |
| Product or category text | Secondary matching signal |
| Quantity as written | Completeness and commercial value |
| Buyer name | Contact record |
| Buyer company | Buyer-type inference |
| Buyer type as stated by the portal (if any) | Cross-check |
| Buyer phone | Contact channel |
| Buyer email | Contact channel |
| Destination country as written | Country-rule lookup |
| Destination city (if present) | Secondary signal |
| Any portal-supplied lead score or badge | Baseline comparison only; never used as the system score |
| Lead source or campaign label | Provenance |
| Portal URL for the lead | Manual fallback |

### 7.2 Capture checklist

- [ ] Export covers a full week (75 rows, or the actual delivered count).
- [ ] Column headers are exactly as the portal emits them; do not rename.
- [ ] At least one row has a missing email; at least one has a non-Indian destination; at least one has no quantity.
- [ ] Timestamps include the time zone, or the export time zone is stated in the note.
- [ ] The file is saved without opening and re-saving in Excel (Excel can mangle UTF-8 and leading zeros).
- [ ] Buyer contacts are redacted before the file is shared with the developer; the unredacted copy stays on the operator's machine.
- [ ] The field-mapping note says which portal column maps to which internal field, and which required fields are absent.

**Fallback:** API access (I7). If neither exists, the ingest source is unresolved and M1 is blocked.

---

## 8. Item I6 — Commercial terms

Fill every cell. These values drive the allowance ledger, the derived unit cost and the waste metric; they are not used to price individual leads, because there is no per-lead transaction.

| Term | Value | Notes |
|---|---|---|
| Weekly fee (INR) | `__________` | The contracted amount per week for the 75-lead allowance |
| Contract period | `__________` | Start and end date |
| Leads per week | `75` | Fixed by contract; do not change without a written amendment |
| Delivery day and time | `__________` | Example: Monday 09:00 Asia/Kolkata |
| Week start | `Monday 00:00 Asia/Kolkata` | Fixed. Drives `week_start` on every allowance row |
| Expiry moment | `Next Monday 00:00 Asia/Kolkata` | Fixed. Unused allowance expires at this instant |
| Per-lead cost basis | `weekly fee / 75` | Derived, not a purchase price. Example: fee 37,500 → 500 per lead |
| Waste definition | `untouched allowance x per-lead cost basis` | Reported weekly; never recovered |
| Payment terms | `__________` | Example: monthly in advance |
| Renewal terms | `__________` | Example: auto-renew unless 30 days' notice |
| Billing contact | `__________` | Name and email |

**Fallback:** allocation still works with `allotted = 75`. The waste metric shows "unknown" until the weekly fee is supplied.

---

## 9. Item I7 — API access confirmation

Ask the account manager these exact questions and record the answers verbatim. **IndiaMART API details must be verified in P0**; do not design against assumed behaviour.

1. Is there a documented API for a reseller to pull the weekly lead allocation? If yes, provide the base URL and documentation.
2. What authentication method is used (API key, OAuth 2.0 client credentials, signed request, IP allowlist)? What are the exact header or parameter names?
3. What are the rate limits (requests per minute, per day) and the retry/backoff guidance?
4. Which fields are returned per lead: id, timestamp, inquiry text, product/category, quantity, buyer name, company, type, phone, email, destination, city, source?
5. Does the API return the same fields as the portal export, field for field? If not, list the differences.
6. Is there a sandbox or test account, and how do we get credentials for it?
7. Is there a webhook or push notification for new leads, or is polling the only option? If polling, what is the recommended interval?
8. How does pagination work (page size, cursor, total count), and what is the maximum page size?
9. How long are leads retained and retrievable via the API? Can we re-fetch a past week?
10. What are the documented error codes and their meanings, especially for auth failure, quota exhaustion and partial responses?
11. Is there an endpoint or field that states the weekly allotted count and the used count? If yes, how is it updated?
12. Who is the technical support contact, and what is the expected response time?
13. Do the terms permit storing buyer contact data in our own system for the purpose of contacting those buyers? Provide the clause reference.

### 9.1 Answer record

| Question | Answer | Date confirmed | Confirmed by |
|---|---|---|---|
| 1 | | | |
| 2 | | | |
| 3 | | | |
| 4 | | | |
| 5 | | | |
| 6 | | | |
| 7 | | | |
| 8 | | | |
| 9 | | | |
| 10 | | | |
| 11 | | | |
| 12 | | | |
| 13 | | | |

**Fallback:** manual export (I5). Record the decision to use manual export and its weekly time cost.

---

## 10. Item I8 — Proven-seller countries

List the countries where the office has actually shipped before. Set `is_proven_seller = true` in I2 for each. This is an input to the destination-suitability component (20 points); it is recorded now and consumed by M3.

| ISO2 | Country | Evidence of prior shipment (invoice or BL reference) | Last shipment year | Set in I2 |
|---|---|---|---|---|
| | | | | |
| | | | | |
| | | | | |

Rules:

- Mark `true` only with documentary evidence. A buyer's claim that they import does not count.
- Leaving a country `false` is safe: the destination component scores conservatively and the lead may land in `review`.
- Review this list quarterly. A country with no shipment for 24 months should be reconsidered.

**Fallback:** default all to `false`. The system still runs, with more leads routed to review.

---

## 11. Item I9 — AI data-egress decision

The AI provider receives inquiry text. That text may contain buyer names, company names and contact details unless redacted. Choose one option and record it. v1 ships with `AI_PROVIDER=null`; this decision governs v1.1.

| Option | What leaves the office | Cost | Quality | Contract and privacy consequence |
|---|---|---|---|---|
| A. Local only (`ollama`) | Nothing. All inference runs on the office PC | Hardware cost only; no per-request fee | Weakest extraction on small local models; slower | No third-party processor. Buyer data stays on the machine. Requires adequate RAM and CPU/GPU |
| B. Free-tier cloud (`gemini`) | Redacted inquiry text and a catalogue excerpt | Zero within the free tier; hard monthly cap enforced | Strong extraction for mixed and noisy naming | The provider is a data processor. Free tiers may use inputs to improve their services depending on the plan; review the current terms before enabling. Egress over HTTPS |
| C. Paid cloud (`anthropic`) | Same as B, plus request metadata | Per-request fee; hard monthly cap enforced | Strong extraction; stable output | Paid terms typically exclude training on inputs; confirm in the current agreement. Egress over HTTPS |

Decision record:

| Field | Value |
|---|---|
| Chosen option (A / B / C) | `__________` |
| AI provider key in `.env` | `__________` (for A: `null`; B: `gemini`; C: `anthropic`) |
| Monthly spend cap (USD) | `__________` |
| Buyer contact fields sent to the provider | `none` (redact before the call) |
| Decision date | `__________` |
| Decided by | `__________` |
| Review date | `__________` |

Mandatory controls for B and C:

- Redact buyer name, phone and email before every provider call. The provider never receives a contact field.
- Send only the inquiry text and the minimum catalogue excerpt needed for the match.
- Set `AI_MONTHLY_CAP_USD` and `AI_MAX_REQUESTS_PER_DAY`; the system refuses calls once either is reached and continues deterministic-only.
- Keep `AI_SHADOW_MODE=true` for the first full weekly cycle; the operator sees deterministic results only.

**Fallback:** `AI_PROVIDER=null`. Deterministic-only scoring and template drafts remain fully functional.

---

## 12. Item I10 — Sales roster

Supply one row per person who will use the system. Passwords are created by the admin after the account exists; do not send passwords in this file.

| Name | Work email | Role | Phone (optional) | Active from | Active to |
|---|---|---|---|---|---|
| | | `admin` / `sales` / `viewer` | | | |
| | | | | | |
| | | | | | |
| | | | | | |

### 12.1 Role definitions

| Role | Who | Can read | Can write | Never sees |
|---|---|---|---|---|
| `admin` | Office operator and the developer | All leads, full buyer contact details, allowance ledger, audit log, system health | Ingest, assign, edit any draft, change status with reason, manage catalogue, country rules, users, feature flags | Raw `.env` or provider keys through the UI |
| `sales` | Staff who contact buyers | Ranked inbox; full contact details for assigned leads; drafts | Edit drafts, mark contacted (assigned leads only), log activities | Other users' assignments, system settings, feature flags, audit log beyond own actions |
| `viewer` | Oversight, management | Ranked inbox with masked contacts; full contact details only for leads explicitly assigned to them; allowance summary | Nothing | Editable fields, exports, user management, settings, unmasked contacts for leads not assigned to them |

Rules:

- At least one `admin` must exist before go-live.
- Use a named account per person. Do not share logins; the contact trail must identify the human who sent each message.
- Deactivate a leaver the same day. Do not delete the account, so their activity history stays intact.
- The developer holds a separate `admin` account used only for deployment and diagnosis; it is not used for day-to-day contact.

**Fallback:** a single admin account. This removes role separation and the assigned-lead restriction, so record it as a temporary risk.

---

## 13. Redaction and privacy rules

These apply to every item above, not only I4.

| Data | Placeholder | Applies to |
|---|---|---|
| Buyer name | `BUYER_n` | All files shared with the developer or used in tests |
| Buyer company (if it identifies a person) | `BUYER_COMPANY_n` | I4, I5 |
| Indian mobile | `+91XXXXXXXXX` | I4, I5, screenshots |
| Non-Indian phone | `+CCXXXXXXXXX` | I4, I5 |
| Email | `buyerN@example.invalid` | I4, I5, screenshots |
| Address | Remove entirely | I4, I5 |
| Portal lead URL | Keep only the id, not the signed URL | I5 |

Additional rules:

- Store unredacted source files only on the office PC, in a folder restricted to the admin account. Never in a shared drive, chat, ticket or email.
- Do not paste buyer contact details into the evaluation set, test fixtures, logs, screenshots or the AI provider payload.
- Do not include buyer PII in the daily digest, alert emails or dashboard exports beyond what the role matrix allows.
- Retain redacted evaluation rows indefinitely for trend analysis; retain unredacted portal exports for no longer than 90 days, then delete.
- When in doubt, redact more. A redacted row still teaches the matcher; a leaked row cannot be un-leaked.

### 13.1 Legal basis statement

Buyer personal data obtained from the IndiaMART marketplace is handled under the reseller contract with the marketplace and under the operator's own privacy obligations as the data controller for the leads it receives. Use the data only to evaluate and respond to the buyer's inquiry. Do not sell it, share it with third parties beyond the contracted AI processor chosen in I9, or use it for unrelated marketing. Confirm the contract clause permitting local storage and contact (question 13 in I7) before the pilot begins.

---

## 14. Submission checklist

- [ ] I1 product master: header exact, 1000+ rows, unique SKUs, pack size and price populated.
- [ ] I2 country rules: header exact, every seen destination present, no country accidentally `approved`.
- [ ] I3 synonyms: header exact, every `sku` exists in I1, no ambiguous term.
- [ ] I4 evaluation dataset: at least 100 rows, four labels represented, contacts redacted, inquiry text verbatim.
- [ ] I5 live export: one complete week, field-mapping note attached, contacts redacted.
- [ ] I6 commercial terms: weekly fee and delivery day filled; per-lead basis confirmed as fee / 75.
- [ ] I7 API answers: all 13 questions answered or explicitly marked unknown, dated and signed.
- [ ] I8 proven-seller countries: evidence reference recorded for each `true`.
- [ ] I9 egress decision: option chosen, cap set, redaction control acknowledged.
- [ ] I10 roster: at least one admin, roles assigned, leavers removed.
- [ ] Every file is UTF-8, comma-delimited, with the exact header row and no merged cells.
- [ ] Every file has been redaction-checked before leaving the operator's machine.

Send completed items to the developer with the status column updated. Items marked `Pending` block the phase named against them; do not start tuning against partial data.
