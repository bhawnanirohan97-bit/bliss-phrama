# PharmaLead AI — PRD Amendments (Amendment 1)

**Document status:** Approved
**Base document:** PharmaLead AI — Product Requirements Document, Version 1.0
**Amendment version:** 1.0 (produces effective PRD v1.1)
**Date:** 2026-10-22
**Owner:** Solo full-time developer (Product, Engineering, Operations)
**Companion documents:** `02_ARCHITECTURE.md`, `13_RISKS_OPEN_QUESTIONS.md`

---

## 1. Purpose of this Amendment

This document is the authoritative change list against PRD v1.0. The business arrangement that PRD v1.0 was written against no longer holds. The office holds a reseller contract that delivers exactly 75 pre-paid IndiaMART B2B pharmaceutical buyer leads per week; the leads arrive already paid for, with buyer contact details attached, and there is no per-lead purchase transaction and no per-lead price. The binding constraint on this business is therefore weekly quota, not money: unused allowance expires at the end of each week (Monday 00:00 Asia/Kolkata to Sunday 23:59 Asia/Kolkata) and cannot be banked, so a lead that lands and is never contacted is money already spent on nothing. PRD v1.0 was built around the opposite assumption — that the system chooses which leads to buy — and its machinery for atomic spend reservations, portal-side idempotency keys, charge reconciliation, refunds and a payment emergency stop has no object to act on. Those sections are deleted rather than reworked, because keeping them would produce a product that models a transaction that cannot occur and that would silently distort every metric, threshold and alert in the document. What replaces them is a weekly allowance ledger with atomic allocation, a derived unit cost computed as weekly fee divided by 75, and a waste metric that prices the allowance left untouched. The rest of this amendment aligns scoring, gates, functional requirements, data model, APIs, security, testing, KPIs, acceptance criteria, deployment and timeline to that reality while keeping the stack, the architecture principle and the operator-centred definition of a good lead exactly as they were. Every IndiaMART-specific assertion in the original document is preserved as a verification item to be closed in Phase P0, because no portal API detail has yet been confirmed.

---

## 2. Summary of changes

| PRD section | Title | Verdict | Reason |
|---|---|---|---|
| 1 | Executive Summary | Amend | Describes purchasing and conversion tracking; rewrite around allowance and triage. |
| 2.1, 2.2, 2.3 | Problem statement, goals, non-goals | Amend | Goals are stated as spending limits and automatic purchasing; replace with quota and responsiveness goals. Non-goals gain "no automatic buyer contact". |
| 2.4 | Release Phases | Amend | Phase 3 (purchasing integration) deleted; phases re-cut around deterministic core, connectors, model phases and shadow promotion. |
| 3 | Users and Permissions | Amend | Finance/Purchasing Manager role deleted; roles reduced to Administrator, Sales Manager, Sales Executive, System Operator. |
| 4 | End-to-End Workflow | Amend | Step 7 (purchase execution) replaced by allowance allocation and draft generation; step 8 kept as CRM creation; step 9 (feedback) restated because v1 has no outcome labels. |
| 5.1 | AI Qualification Engine principles | Keep | Retained verbatim; consistent with the new scope. |
| 5.2 | Scoring Weights | Amend | Weights replaced with the five deterministic components totalling 100. |
| 5.3 | Score Categories | Amend | Bands replaced with act_now / good / review / low / skip / hard_reject. |
| 5.4 | Structured AI Output | Amend | Output schema rewritten for deterministic fields plus a shadow-only model output envelope. |
| 5.5 | Failure and Injection Handling | Amend | Injection handling retained and extended; purchase-blocking clauses replaced with allowance and hard-gate clauses. |
| 5.6 | Future Improvement | Amend | Learned conversion models are not planned for v1; calibration and outcome capture are. |
| 6 | Purchase Policy and Financial Controls | Replace | Becomes "Allowance Policy and Cost Controls" (weekly ledger, atomic allocation, derived unit cost, waste). |
| 7 | Portal Integration Requirements | Replace | Becomes "Lead Source Integration" (connector interface, three adapters, no purchasing states). |
| 8 | Functional Requirements | Amend | FR-9 and FR-10 rewritten; FR-6, FR-8, FR-11, FR-13, FR-14, FR-17 restated because their v1.0 wording presumes a per-lead purchase; FR-19 to FR-24 added. |
| 9 | Dashboard and UI | Amend | Spend, transaction and conversion panels replaced with allowance, triage and waste panels. |
| 10 | Database Design | Amend | purchase_policies, purchase_attempts and budget_ledger deleted; allowance, match, draft, task, AI-run, evaluation and feature-flag tables added. |
| 11 | Backend Architecture and APIs | Amend | Module list restated; endpoint table replaced with the allowance/triage API surface. |
| 12 | Technology Stack | Amend | Stack unchanged; cloud/HTTPS deployment replaced by local Windows deployment; provider abstraction, Windows Task Scheduler and backup tooling added. |
| 13 | Security and Privacy | Amend | Existing subsections retained; subsections 13.6 to 13.12 added for LAN exposure, PII encryption, session flags, log redaction, retention and purge, AI egress and secret handling. |
| 14 | Pharmaceutical Export Compliance | Replace | Becomes "Destination and Product Eligibility" with a country/SKU matrix and the hard-gate mapping. |
| 15 | Monitoring and Observability | Amend | Spend, reservation and reconciliation signals replaced by allowance, triage, latency and cost signals. |
| 16 | Testing Strategy | Amend | Financial safety tests replaced by allowance concurrency, dedupe and waste tests; evaluation thresholds quantified. |
| 17 | Non-Functional Requirements | Amend | All four categories converted from qualitative to quantified targets. |
| 18 | KPIs | Amend | Replaced with a new set of seven KPIs (see Section 18 of this amendment). |
| 19 | Acceptance Criteria | Amend | Purchase acceptance criteria deleted; the ten build hard gates and the twenty functional criteria added. |
| 20 | Risks and Mitigation | Amend | Risk table rewritten against the new scope. |
| 21 | Deployment Plan | Amend | Cloud staging replaced by a single local Windows host with LAN access, nightly backup and restore drill. |
| 22 | Suggested Development Timeline | Amend | Replaced with the P0-P7 roadmap totalling 40 working days plus 5.5 days for v1.1. |
| 23 | Future Enhancements | Amend | Per-lead purchasing and conversion prediction moved to a separate, explicitly gated list. |
| 24 | Final Product Definition | Replace | Restated as a triage-and-contact product with the architecture principle intact. |
| Appendix A | Initial Build Checklist | Amend | Rebuilt around the new phase structure. |
| Appendix B | Development Principles | Amend | Principles 4, 5 and 6 restated; principles covering quota and drafts added. |

---

## 3. Amended sections

### 3.1 Section 1 — Executive Summary (amended)

Replace Section 1 in full with:

> **1. Executive Summary**
>
> PharmaLead AI is a lead triage and response system for pharmaceutical exporters. Once a week, 75 pre-paid buyer inquiries land in a single ranked inbox. Each inquiry is normalised, matched against the company's catalogue of 1000+ pharmaceutical SKUs, evaluated against destination and buyer-type rules, scored on a deterministic 100-point scale, and — where it passes every hard gate — accompanied by a drafted WhatsApp message, an email subject and body, and a phone call opener. The operator reads one screen instead of 75 portal pages, and contacts a buyer within minutes rather than hours.
>
> **1.1 Core Idea**
> The system answers one question per lead: is this buyer worth talking to? A good lead is one whose request matches a product the company sells and whose destination country the company sells to. Everything else is noise.
>
> **1.2 Business Constraint**
> The 75 leads per week are pre-paid. There is no purchase decision and no per-lead price. Unused allowance expires every Sunday at 23:59 Asia/Kolkata. The cost of the system is therefore not what it spends — it is what it lets expire untouched.
>
> **1.3 Product Vision**
> Build an independent, auditable system with its own dashboard, backend, database, scoring engine, product catalogue, allowance ledger, connectors, CRM and analytics. The system must not depend on the office's existing lead-buyer software. It must also never send a message to a buyer: it drafts, a human sends.

---

### 3.2 Section 2 — Problem Statement, Goals and Scope (amended)

Replace Sections 2.1, 2.2 and 2.3 with:

> **2.1 Business Problems**
> - Seventy-five inquiries arrive every week and are read one at a time in the seller dashboard, costing roughly one minute of operator attention each.
> - Approximately 70 per cent of inquiries are worth talking to; the remaining 30 per cent consume attention that should go to the good ones.
> - Response speed is the operator's first-ranked pain: a buyer contacted within minutes is materially more likely to be engaged than one contacted the next day.
> - Leads are lost between the portal and the sales team because there is no single list, no owner and no status.
> - Spend visibility is the operator's third-ranked pain: nobody can say how much of the weekly allowance has been used, how much has expired, or what an unused lead cost.
> - Inquiry text is noisy — English with Hinglish and broken-English constructions — and buyer naming is inconsistent: some inquiries use our brand names, others use generic name plus strength.
>
> **2.2 Primary Goals**
> - Deliver all 75 weekly inquiries into one ranked inbox within 5 minutes of arrival.
> - Keep median arrival-to-contact under 30 minutes.
> - Keep weekly allowance utilisation above 90 per cent, measured as leads with a logged first contact divided by 75.
> - Keep hard-gate precision at 100 per cent: never hard-reject a lead the operator would have worked.
> - Keep product-match top-1 accuracy at or above 85 per cent on the reviewed evaluation set.
> - Keep the catalogue authoritative at 1000+ SKUs with export eligibility and destination restrictions maintained.
> - Keep every scoring decision explainable and auditable, with a policy version attached to every assessment.
> - Keep monthly AI spend at or below the configured cap, with deterministic-only degradation when the cap is reached.
> - Keep no lead unowned and no lead without a recorded disposition.
>
> **2.3 Non-Goals for the Initial Release**
> - Training a large language model from scratch.
> - Guaranteeing that a lead will convert into an order. No outcome labels exist in v1.
> - Purchasing leads. The allowance is contracted and pre-paid; there is no per-lead purchase to execute.
> - Sending any message to a buyer automatically. The system drafts; a human sends.
> - Bypassing portal access restrictions, CAPTCHAs, or payment controls.
> - Using unauthorized scraping or restricted data.
> - Ranking or prioritising repeat buyers. Every inquiry in v1 is treated as a new-buyer inquiry.
> - Replacing export-compliance professionals or sending unrestricted bulk messages.
> - Any outbound transaction of money, credit, balance or reservation.

---

### 3.3 Section 2.4 — Release Phases (amended)

Replace Section 2.4 in full with:

> **2.4 Release Phases**
>
> 1. **Phase A — Deterministic core (v1).** Authentication and roles; product catalogue with 1000+ SKUs; ingestion through the CSV-export and manual-entry adapters; deterministic normalisation; catalogue product matcher; deterministic qualifier and scorer; policy engine with hard gates; weekly allowance ledger with atomic allocation; ranked inbox; template-anchored draft generation; follow-up task engine; dashboard; audit log; monitoring; nightly backup.
> 2. **Phase B — Incremental source adapters (v1).** The API connector, enabled only after P0 confirms that an authorised machine-readable feed exists and that the account terms permit programmatic retrieval. The CSV and manual adapters remain the fallback path and must continue to work if the API adapter is never enabled.
> 3. **Phase C — CRM depth (v1).** Assignment rules, follow-up history, contact logging, and per-operator workload views.
> 4. **Phase D — Model phases M4, M5 and M6 (v1.1).** LLM field extraction, LLM adjudication and evidence fusion, and confidence calibration. Each phase ships behind its own feature flag, runs shadow-first, and is promoted only on the evidence defined in Section 2.4.1.
> 5. **Phase E — Optimisation (post-v1.1).** Learned ranking on captured outcomes, a second marketplace connector, and a per-lead purchasing capability.
>
> **2.4.1 Model phases and promotion gate**
>
> | Phase | Scope | Estimate | Ships in | Feature flag |
> |---|---|---|---|---|
> | M0 | Evaluation harness and reviewed dataset | 1 day | v1 | `flag.evaluation_harness` |
> | M1 | Deterministic normaliser | 1.5 days | v1 | `flag.normaliser_v1` |
> | M2 | Catalogue and product matcher | 2 days | v1 | `flag.matcher_v1` |
> | M3 | Scorer and policy engine | 1.5 days | v1 | `flag.scorer_v1` |
> | M4 | LLM field extraction | 2.5 days | v1.1 | `flag.llm_extraction` |
> | M5 | LLM adjudication and evidence fusion | 1.5 days | v1.1 | `flag.llm_adjudication` |
> | M6 | Confidence engineering and calibration | 1.5 days | v1.1 | `flag.confidence_calibration` |
> | M7 | Template-anchored draft generation | 2 days | v1 | `flag.drafter_v1` |
>
> Promotion rule, applied per phase and enforced by the release checklist: promote only when the phase has run in shadow mode for **at least 50 leads or at least 3 days of shadow operation, whichever is later**, **and** shows **at least 90 per cent agreement** with the operator's own recorded decisions on the shadow sample, **and** a **signed review of every disagreement** is attached to the promotion record. A phase that fails any limb stays in shadow and is reported as such.

---

### 3.4 Section 3 — Users and Permissions (amended)

Replace Section 3 with:

> **3. Users and Permissions**
>
> **3.1 Administrator.** Manages users, lead sources, product catalogue, country eligibility rules, allowance configuration, feature flags, AI provider settings, audit logs and system health.
> **3.2 Sales Manager.** Reviews lead quality, allowance utilisation, waste, team follow-ups and performance by product, country and operator.
> **3.3 Sales Executive.** Views assigned leads, reads drafted messages, contacts buyers over WhatsApp, email and phone, records the contact, updates status and completes follow-up tasks.
> **3.4 System Operator.** Monitors connectors, queues, ingestion runs, AI spend, latency and service health. Does not reassign or discard leads.
>
> Role-based access control must prevent unauthorised users from changing allowance configuration, catalogue eligibility, feature flags or provider settings, and from viewing integration secrets. Permissions for financial-policy mutation are deleted with the financial policy.

---

### 3.5 Section 4 — End-to-End Workflow (amended)

Replace Section 4 with:

> **4. End-to-End Workflow**
>
> The stage order is fixed and is the same order the pipeline executes in `02_ARCHITECTURE.md` Section 4: pull, normalise, validate, dedupe, match, qualify, policy, rank, allocate, persist, notify.
>
> 1. **Pull (retrieval).** Acquire permitted data through an authorised API, a permitted portal export, or manual entry, by adapter, behind one connector interface. Preserve the source payload verbatim.
> 2. **Normalise.** Standardise inquiry text, country naming, buyer naming, quantity and unit, and product naming into the internal canonical form. Normalisation is deterministic and writes nothing.
> 3. **Validate.** Verify source, external identifier, inquiry text, arrival timestamp, buyer location, quantity and contact presence on the canonical record. Reject records that fail structural validation and record the reason.
> 4. **Deduplicate.** Identify repeated external identifiers, repeated events and likely cross-source duplicates. Flag uncertain matches for review rather than guessing.
> 5. **Product matching.** Compare the normalised request against catalogue brand names, generic names, strengths, dosage forms, synonyms and export rules, and produce ranked candidates with a match confidence for each.
> 6. **Qualification.** Derive buyer type, intent, destination country, requested quantity and missing-information fields deterministically.
> 7. **Policy evaluation.** Apply the hard gates in Section 5.2.1, then compute the five scoring components and the band. A hard gate that fires produces `hard_reject` regardless of the numeric score.
> 8. **Ranking.** Compute the ordering key — band, then score, then arrival time — so the week's leads have one total order.
> 9. **Allowance allocation.** Atomically consume one unit from the current week's allowance and bind the lead to that week. A lead that cannot be allocated because the week's allowance is exhausted is persisted as `quota_exceeded` and never enters the inbox.
> 10. **Persistence.** Commit the lead, its matches, its assessment and its allocation in one transaction. Either all four exist or none does.
> 11. **Drafting.** Generate a WhatsApp message, an email subject and body, and a phone call opener from templates anchored to the matched SKU and the buyer's stated facts.
> 12. **Notification.** Alert the operator that a new week of leads is ranked and ready, and that specific leads need review or contact.
> 13. **Human action and evidence.** A named operator contacts the buyer over an approved channel, logs the contact, sets a status and schedules follow-up. The database records the evidence: who acted, when, on which channel, with which draft.
>
> **Essential rule:** a missing or uncertain required condition sends the lead for review. It never authorises a message to be sent and never silently drops a lead.

---

### 3.6 Section 5.1 — AI Qualification Engine principles (kept)

Section 5.1 is retained verbatim. It already restricts the model to structured extraction, and the architecture principle is unchanged:

> **5.1 Principles**
> The engine must be explainable, consistent, configurable, auditable, and resistant to misleading text. It combines deterministic features with an LLM for structured inquiry extraction; the LLM does not execute purchases.

Note for the implementer: the second sentence is retained as a statement of the invariant, not as a statement that v1 calls a model. In v1 the LLM component is absent and the deterministic path alone produces every field.

---

### 3.7 Section 5.2 — Scoring Weights (amended)

Replace Section 5.2 in full with:

> **5.2 Scoring Weights**
>
> Scoring is deterministic and totals 100 points. No model output can add to, subtract from or reorder a component score. The model, when enabled in v1.1, may only supply fields that feed a component; it may never supply a component value directly.
>
> | # | Component | Weight | Derived from |
> |---|---|---|---|
> | 1 | Product match | 35 | Ranked catalogue candidates for the normalised request |
> | 2 | Commercial value | 20 | Requested quantity against catalogue MOQ and indicative price |
> | 3 | Destination suitability | 20 | Country eligibility class for the matched SKU and buyer location |
> | 4 | Buyer type and intent | 15 | Buyer classification and the presence of a concrete request |
> | 5 | Inquiry completeness | 10 | Presence of product, quantity, destination, company and contact fields |
> | | **Total** | **100** | |
>
> **5.2.1 Hard gates (evaluated before scoring, never overridable)**
>
> A lead that fires any hard gate is recorded as `hard_reject` with the gate identifier. No model, no score band, no operator override flag and no configuration switch can reverse a hard gate. Six conditions are encoded as seven gate identifiers so that buyer-type precision can be measured separately for each type:
>
> | Gate id | Condition | Reason |
> |---|---|---|
> | `destination_blocked` | Buyer destination country is in the blocked class | We do not sell to that country |
> | `product_not_export_eligible` | Matched SKU has `export_eligible = false` | SKU cannot be exported |
> | `product_restricted_in_destination` | Matched SKU lists the destination country in its restriction list | SKU may not be sold into that country |
> | `buyer_personal` | Buyer classified as personal (individual, not a business) | Not a B2B inquiry |
> | `buyer_job_vendor` | Buyer classified as a job vendor, labour seeker, student or similar | Not a buying inquiry |
> | `contact_missing` | No buyer contact channel present, or no contact detail reachable | Cannot respond |
> | `allowance_exhausted` | The week's allowance has no remaining unit at allocation time | Nothing left to allocate this week |
>
> **5.2.2 Product match sub-scores (0–35)**
>
> | Match state | Points | Rule |
> |---|---|---|
> | Exact | 35 | Brand, strength and dosage form all match one catalogue SKU |
> | Strong | 28 | Generic name and strength match a SKU whose dosage form is compatible |
> | Partial | 21 | Brand or generic matched but strength differs, or strength matched but form differs |
> | Weak | 14 | Molecule family or therapeutic category matched only |
> | Category-only | 7 | Therapeutic category matched, no product-level candidate |
> | None | 0 | No candidate at or above the matcher floor |
>
> The matcher floor is the minimum normalised-token overlap that may be recorded as a candidate. It is a configuration value, initially 0.55, and it is measured by the M0 harness. A lead whose best candidate scores below the floor is recorded as a no-match, and no-match precision is measured against the operator's own judgement.
>
> **5.2.3 Commercial value sub-scores (0–20)**
>
> | Condition | Points |
> |---|---|
> | Quantity stated and at least 10x the SKU's minimum order quantity | 20 |
> | Quantity stated and at least 3x the SKU's minimum order quantity | 15 |
> | Quantity stated and at least 1x the SKU's minimum order quantity | 10 |
> | Quantity stated, below the SKU's minimum order quantity | 5 |
> | Quantity not stated | 0 |
> | Quantity unparseable after normalisation | 0 |
>
> The indicative price recorded on the SKU affects the commercial-value points only through the quantity bands above. It never produces a price-based purchase decision, because v1 has no per-lead price.
>
> **5.2.4 Destination suitability sub-scores (0–20)**
>
> | Country class | Points |
> |---|---|
> | Allowed and a proven seller destination | 20 |
> | Allowed but not yet a proven seller destination | 14 |
> | Unproven and therefore treated as restricted until proven | 8 |
> | Not resolvable from the inquiry | 0 |
> | Blocked | 0 and gate `destination_blocked` fires |
>
> **5.2.5 Buyer type and intent sub-scores (0–15)**
>
> Buyer type contributes 0–10 and intent contributes 0–5.
>
> | Buyer type | Points |
> |---|---|
> | Exporter or distributor with a presence in the destination country | 10 |
> | Wholesaler | 9 |
> | Importer | 8 |
> | Pharmacy, hospital or institutional procurement | 7 |
> | Trading company, not a distributor | 5 |
> | Manufacturer buying finished form | 4 |
> | Unknown | 4 |
> | Personal individual | 0 and gate `buyer_personal` fires |
> | Job vendor, labour seeker, student | 0 and gate `buyer_job_vendor` fires |
>
> | Intent | Points |
> |---|---|
> | Explicit purchase request with quantity and a target date | 5 |
> | Explicit purchase request with quantity | 4 |
> | Price or availability enquiry with a specific product | 3 |
> | General availability question naming a product | 2 |
> | Non-commercial message | 0 |
>
> **5.2.6 Inquiry completeness sub-scores (0–10)**
>
> | Fields present | Points |
> |---|---|
> | Product, quantity, destination, company name and at least one contact channel | 10 |
> | Product, quantity, destination and at least one contact channel | 8 |
> | Product, quantity and at least one contact channel | 6 |
> | Product and at least one contact channel | 4 |
> | Product only | 2 |
> | No identifiable product | 0 |
>
> A score indicates estimated suitability against the catalogue and the country rules. It is not a conversion prediction, and v1 contains no outcome labels from which one could be learned.

---

### 3.8 Section 5.3 — Score Categories (amended)

Replace Section 5.3 in full with:

> **5.3 Score Categories**
>
> | Band | Total score | Component minimums | Operator meaning |
> |---|---|---|---|
> | `act_now` | 85–100 | product match >= 21, destination >= 14 | Contact today |
> | `good` | 70–84 | product match >= 14, destination >= 8 | Contact today if the list is short |
> | `review` | 55–69 | none | Read and decide by hand |
> | `low` | 40–54 | none | Contact only on request |
> | `skip` | 0–39 | none | Do not contact |
> | `hard_reject` | not applicable | any hard gate fired | Never contact; excluded from the inbox |
>
> Component minimums are admission rules, not extra gates: a lead that misses a component minimum cannot enter the band even if its total clears the threshold. They exist because a lead with an unresolvable destination can otherwise clear 80 points on the strength of product match, buyer type and completeness alone, and such a lead has no commercial meaning.
>
> The band a lead lands in is computed once at assessment time and stored with the assessment. It is recomputed only when the policy version changes or an operator explicitly requests reassessment. Bands are ordinal for ranking. `hard_reject` is a separate state, not a low score.

---

### 3.9 Section 5.4 — Structured AI Output (amended)

Replace Section 5.4 in full with:

> **5.4 Structured AI Output**
>
> **5.4.1 Deterministic output (v1, authoritative)**
>
> Every assessment produces the following validated structure. Types, ranges and allowed values are enforced on the backend. A field that cannot be determined is recorded as `null` with a reason in `missing_information`; it is never guessed.
>
> | Field | Type | Domain |
> |---|---|---|
> | `lead_id` | integer | existing lead |
> | `product_match` | object | `sku_id`, `match_state`, `match_points`, `candidates[]` |
> | `candidates` | array | up to 5 catalogue candidates, each with `sku_id`, `match_state`, `score`, `matched_tokens` |
> | `buyer_type` | enum | `exporter_distributor`, `wholesaler`, `importer`, `institution`, `trader`, `manufacturer`, `personal`, `job_vendor`, `unknown` |
> | `purchase_intent` | enum | `explicit_with_date`, `explicit_with_quantity`, `price_enquiry`, `availability_enquiry`, `non_commercial` |
> | `destination_country` | string or null | ISO 3166-1 alpha-2, `null` if unresolvable |
> | `destination_class` | enum | `allowed_proven`, `allowed_unproven`, `restricted`, `blocked`, `unknown` |
> | `requested_quantity` | integer or null | normalised units; `null` if absent or unparseable |
> | `requested_quantity_unit` | enum | `strip`, `tablet`, `capsule`, `bottle`, `vial`, `box`, `kg`, `litre`, `unknown` |
> | `missing_information` | array of strings | drawn from a fixed vocabulary of 11 field names |
> | `score_components` | object | `product_match`, `commercial_value`, `destination`, `buyer_type_intent`, `completeness`, each 0 to its weight |
> | `overall_score` | integer | 0–100, equal to the sum of the components |
> | `band` | enum | `act_now`, `good`, `review`, `low`, `skip`, `hard_reject` |
> | `hard_gates_fired` | array of strings | subset of the seven gate identifiers in Section 5.2.1; empty when none fired |
> | `confidence` | object | `value` 0.0–1.0, `band` `low`/`medium`/`high`, `calibrated` boolean, `basis` string |
> | `explanation` | string | human-readable rationale naming the components that drove the result |
> | `risk_flags` | array of strings | drawn from a fixed vocabulary of 9 flags |
> | `policy_version` | string | immutable identifier of the policy and weights that produced the assessment |
> | `model_version` | string or null | `null` in v1 for every deterministic-only assessment |
> | `assessed_at` | timestamp | UTC, stored with the application timezone offset |
>
> The fixed vocabulary for `missing_information` is: `product`, `quantity`, `quantity_unit`, `destination`, `buyer_type`, `company_name`, `buyer_name`, `email`, `phone`, `target_date`, `grade_or_registration`.
>
> The fixed vocabulary for `risk_flags` is: `ambiguous_product`, `ambiguous_quantity`, `multiple_product_candidates`, `no_product_candidate`, `unusual_buyer_language`, `restricted_sku`, `unproven_destination`, `contact_incomplete`, `duplicate_suspected`.
>
> **5.4.2 Model output envelope (v1.1, shadow-only)**
>
> When a model phase is enabled in shadow mode, the model returns a strictly typed envelope that is validated against a schema and stored separately from the deterministic assessment. The envelope may carry `normalised_fields`, `match_candidates`, `confidence_signal` and `explanation_sentences`. It may not carry a score, a band, a gate decision or any ordering instruction. A schema violation, a timeout, an empty response or an unsupported model status causes the model output to be discarded and the deterministic assessment to stand. A discarded model output is recorded as a shadow failure and counted against the phase's agreement rate.
>
> **5.4.3 Output validation**
>
> Every model response is parsed against the schema before it is stored. Responses that fail validation, exceed the maximum token length, or contain fields outside the schema are rejected, counted and logged without the response body.

---

### 3.10 Section 5.5 — Failure and Injection Handling (amended)

Replace Section 5.5 in full with:

> **5.5 Failure and Injection Handling**
>
> **5.5.1 Untrusted input.** Inquiry text, buyer names, company names and any other buyer-supplied string are treated as untrusted data. They must not override system instructions, alter policy, disclose secrets, change the output schema, or influence the hard gates. Buyer-supplied text is never interpolated into a system prompt or into a template that carries an instruction.
>
> **5.5.2 Model isolation.** No model receives portal credentials, API keys, database connection strings, encryption keys, the allowance ledger, or any purchasing or messaging tool. The model receives only the fields listed in the output envelope schema.
>
> **5.5.3 Failure semantics.**
>
> | Failure | Behaviour |
> |---|---|
> | Invalid model response | Discard, count, log without body, deterministic result stands |
> | Model timeout | Discard, count, deterministic result stands, retry at most 2 times with exponential backoff |
> | Model unavailable or rate-limited | Deterministic result stands, circuit opens for 15 minutes, remaining leads process deterministically |
> | Model indicates an unsupported capability | Discard, count, deterministic result stands |
> | Normaliser fails to parse a field | Field set to `null` with a `missing_information` entry, lead continues |
> | Matcher finds no candidate | Lead recorded as `no_product_candidate` with a risk flag, score computed with product match at 0 |
> | Policy evaluation throws | Transaction rolled back, lead left in `pulled`, job retried with backoff, no partial write |
> | Allowance allocation fails | Transaction rolled back, no lead persisted as allocated, retry |
>
> **5.5.4 No unsafe escalation.** Under no failure condition may a lead gain a higher band, lose a hard gate, or be presented as ready to send. Failures reduce information; they never increase permissiveness.

---

### 3.11 Section 5.6 — Future Improvement (amended)

Replace Section 5.6 with:

> **5.6 Future Improvement**
>
> **5.6.1 In scope for v1.1.** Confidence engineering and calibration (M6): map the deterministic agreement rate and the match confidence onto a calibrated probability, publish the reliability curve, and keep `calibrated = false` until the curve is validated against at least 200 operator-labelled leads.
>
> **5.6.2 In scope post-v1.1.** Learned models — logistic regression, gradient-boosted trees, calibrated classifiers and embeddings — become possible only once outcome labels exist. v1 deliberately captures the raw material for them (product match, band, operator decision, timestamps) without training anything.
>
> **5.6.3 Explicitly out of scope.** Conversion prediction and ranking by predicted revenue are out of scope until the team records contact, reply, quotation and order outcomes at a volume that supports calibration. A claim of predictive accuracy before that point would be unfounded.
>
> **5.6.4 Change control.** Any change that alters the score weights, the band thresholds, the hard gates, the product-match rules or the country classification requires a written reason, a version increment of `policy_version`, a re-run of the evaluation harness, and an audit record. A change that touches the hard gates additionally requires a signed review.

---

### 3.12 Section 6 — Purchase Policy and Financial Controls (replaced)

Delete Section 6 in full and replace with:

> **6. Allowance Policy and Cost Controls**
>
> **6.1 The constraint.** The office holds a reseller contract delivering exactly 75 pre-paid IndiaMART B2B pharmaceutical buyer leads per week. The allowance is delivered as a weekly block, resets at 00:00 Asia/Kolkata each Monday, and expires at 23:59:59 Asia/Kolkata on Sunday. Unused allowance does not roll over. There is no per-lead purchase transaction and no per-lead price.
>
> **6.2 Weekly ledger.** One row per ISO week in `allowance_weeks`:
>
> | Field | Meaning |
> |---|---|
> | `week_start` | Date of the Monday that starts the week, in Asia/Kolkata |
> | `quota_total` | 75 |
> | `allocated_count` | Leads allocated against the week so far |
> | `contacted_count` | Allocated leads with at least one logged human contact |
> | `expired_count` | Allowance units unused at week close |
> | `weekly_fee_minor` | The contracted weekly fee in the minor units of `currency` |
> | `unit_cost_minor` | Derived: `weekly_fee_minor` divided by 75, rounded to the nearest minor unit |
> | `currency` | `INR` |
> | `status` | `open`, `closed` |
>
> `weekly_fee_minor` is configured by the Administrator. The figure itself is tracked as open question OQ-01 in `13_RISKS_OPEN_QUESTIONS.md`; until it is answered the ledger still functions, and every derived figure is reported as derived from a configured input rather than as a known amount.
>
> **6.3 Derived unit cost and waste.**
> - `unit_cost_minor = round(weekly_fee_minor / 75)`.
> - `waste_units = quota_total - contacted_count`.
> - `waste_cost_minor = waste_units * unit_cost_minor`.
> - `utilisation = contacted_count / quota_total`.
>
> Waste is the headline cost metric of the system. It is the value of allowance that was paid for, delivered, and then never used. Report waste per week, per month, and as a trailing 4-week figure.
>
> **6.4 Atomic allocation.**
> - Allocation happens in the same database transaction as the write of the lead record.
> - The allocation reads the `allowance_weeks` row for the target week with `SELECT ... FOR UPDATE` and increments `allocated_count` before the transaction commits.
> - A unique constraint on `(source_id, external_lead_id)` makes a second allocation of the same source lead impossible at the database level, independent of application logic.
> - A second unique constraint on `(week_start, lead_id)` prevents one lead from being bound to two weeks.
> - Twenty concurrent workers processing the same batch must produce exactly 20 allocations and never exceed 75 for the week.
> - A lead that arrives when `allocated_count` has reached 75 is persisted with `status = quota_exceeded`, is excluded from the inbox, is not scored as actionable, and is reported daily. It is retained for audit.
>
> **6.5 Week close.** A scheduled job closes the week at 00:05 Asia/Kolkata on Monday: it sets `expired_count`, `status = closed`, freezes the derived figures and writes an audit record. Closed weeks are never mutated.
>
> **6.6 What is deleted.**
> The following are removed from the product with no replacement, because no transaction exists for them to control:
> - Maximum cost per lead.
> - Daily, weekly and monthly spend budgets.
> - Maximum purchases per day.
> - Per-portal budgets and balances.
> - Spend reservations and release of reservations.
> - Idempotency keys issued to a portal for purchase requests.
> - Charge reconciliation, refunds, reversals and adjusting entries.
> - A payment emergency stop.
> - Cost-per-lead purchase price, average purchase price and return on acquisition.
>
> **6.7 Replaced controls.** The following controls replace them, and they are the only cost-side controls in v1: the weekly allowance ledger, atomic allocation, the derived unit cost, the waste metric, the allowance-exhausted hard gate, a manual ingest pause, and the monthly AI spend cap.
>
> **6.8 Ingest pause.** An Administrator may pause ingestion immediately. A paused system continues to score, rank, display and draft already-allocated leads; it stops pulling new batches. The pause is an operator control, not a safety interlock, and it is recorded in the audit log with actor and timestamp.

---

### 3.13 Section 7 — Portal Integration Requirements (replaced)

Delete Section 7 in full and replace with:

> **7. Lead Source Integration**
>
> **7.1 Connector design.** Every lead source is implemented as an adapter behind one internal interface with three operations:
>
> | Operation | Signature | Contract |
> |---|---|---|
> | `pull` | `pull(Window $window): LeadBatch` | Return every lead that became available inside the window. Must be safe to call repeatedly over the same window. |
> | `fetchDetails` | `fetchDetails(string $externalId): RawLead` | Return the full raw record for one lead, including the source payload. Must not mutate any state. |
> | `health` | `health(): HealthStatus` | Return `ok`, `degraded` or `unavailable`, with a reason and the last successful run timestamp. Must not perform a write. |
>
> The interface returns raw, unnormalised data. Normalisation is the next stage's job and lives in one place, not inside adapters.
>
> **7.2 IndiaMART verification checklist (P0).** The following must be verified in Phase P0 and written down before any API adapter is written. Until each item is confirmed, treat it as unknown:
> - Whether an authorised machine-readable feed of the contracted BuyLeads exists for our seller account, and under what terms.
> - Whether access covers only paid BuyLeads or also general inbound enquiries.
> - Authentication method, available fields, rate limits, pagination, delivery schedule and whether the 75 weekly leads arrive as one batch or spread across the week.
> - Whether the export or feed includes buyer phone, buyer email, full inquiry text and the arrival timestamp.
> - Whether a webhook or push notification exists for new lead arrival, or whether polling is the only option.
> - Whether programmatic access is permitted under our account terms.
>
> No portal API detail may be assumed, coded against or documented as fact before this checklist is closed. If P0 closes it negatively, the CSV-export adapter becomes the primary path and the API adapter is never built.
>
> **7.3 Adapters.**
>
> | Adapter | Mechanism | When used | Notes |
> |---|---|---|---|
> | API adapter | `pull` over an authorised endpoint | Only after P0 confirms an authorised feed | Terms of access recorded alongside the adapter |
> | CSV export adapter | Watch folder plus manual upload | Default path while the API is unconfirmed | Operator exports from the seller dashboard and drops the file, or uploads it in the UI |
> | Manual entry adapter | UI form | Overflow, first run, or when both automated paths fail | Every manual entry is marked with its origin and its author |
>
> **7.4 Connector states.**
>
> | State | Meaning |
> |---|---|
> | `not_configured` | No credentials, no export path |
> | `awaiting_credentials` | Configured but not validated |
> | `connected` | Transport validated, retrieval not yet proven |
> | `retrieval_tested` | At least one full week retrieved and reconciled against the portal |
> | `production_enabled` | In use for the live weekly allowance |
> | `degraded` | Running with reduced capability, for example the API adapter failing while the CSV adapter succeeds |
> | `suspended` | Disabled by an Administrator |
> | `error` | Last run failed; reason recorded |
>
> **7.5 Failure handling.** Handle authentication failure, rate limiting, timeouts, malformed responses, expired credentials, missing fields, duplicate events and portal outages. Use exponential backoff with a maximum of 3 retries per pull. Respect the portal's stated limits. A failed pull never partially allocates allowance: either the batch is fully ingested or it is not.
>
> **7.6 Batch reconciliation.** After each pull, record the count of records received, the count ingested, the count deduplicated, the count rejected by validation and the count excluded by allowance. Compare the count received against the count visible in the seller dashboard for the same week and record the difference. A non-zero difference is a monitoring alert, not an automatic failure.

---

### 3.14 Section 8 — Functional Requirements (amended)

> **Numbering policy.** FR identifiers 1 to 18 are preserved from PRD v1.0 so that the evaluation harness, the test suite, the pilot sign-off sheet and the audit trail keep their references. Ten requirements are carried forward verbatim. Six (FR-6, FR-8, FR-11, FR-13, FR-14, FR-17) are restated because their v1.0 wording presumes a per-lead purchase transaction and cannot coexist with the allowance-based scope defined in Section 6. FR-9 and FR-10 are amended as instructed. FR-19 to FR-24 are new.

| # | Requirement | Status in v1.1 |
|---|---|---|
| 1 | Authentication: secure login/logout, session expiration, and secure recovery. | verbatim |
| 2 | Role-based access: restrict features and log critical policy changes. | verbatim |
| 3 | Product catalog: manage products, synonyms, categories, restrictions, and active status. | verbatim |
| 4 | Lead ingestion: accept records from configured connectors and authorized imports. | verbatim |
| 5 | Normalization: standardize fields while preserving permitted source data. | verbatim |
| 6 | Duplicate detection: prevent repeat ingestion and repeat allocation of the same lead, and flag uncertain matches. | restated |
| 7 | AI scoring: provide score, explanation, missing information, risk flags, and model/policy version. | verbatim |
| 8 | Policy rules: enforce all mandatory limits, hard gates and restrictions server-side. | restated |
| 9 | Shadow mode: run the pipeline end to end without promoting its results to the live inbox; label every shadow result clearly and compare it against the operator's recorded decision. | amended |
| 10 | Drafting only: the system drafts a WhatsApp message, an email and a call opener for a lead and never transmits a message to a buyer; a named human sends. | amended |
| 11 | Allowance history: record every allocation against a week, the unit cost in force, the operator contacts and the resulting waste, append-only. | restated |
| 12 | CRM: assign leads, update status, add notes, schedule follow-ups, and record outcomes. | verbatim |
| 13 | Notifications: alert on allowance thresholds, ingestion failures, duplicates, review needs, stale tasks and AI budget exhaustion. | restated |
| 14 | Reporting: calculate allowance utilisation, waste cost, triage agreement, arrival-to-contact time, funnel metrics and AI spend. | restated |
| 15 | Audit logging: record actor/process, timestamp, action, entity, result, and correlation ID without secrets. | verbatim |
| 16 | Configuration: manage policies and connectors securely. | verbatim |
| 17 | Ingest pause: suspend new batch ingestion while leaving allocated leads fully operable. | restated |
| 18 | Exports: allow authorized CSV/Excel reports while respecting permissions and privacy. | verbatim |
| 19 | **Draft message generation:** generate, for every lead that clears the hard gates, a WhatsApp message, an email subject and body, and a phone call opener, each anchored to the matched catalogue SKU and containing only facts present in the inquiry or on the SKU record. Every draft passes an automated fact check before it is shown, and a draft that fails the fact check is withheld. | new |
| 20 | **Lead source adapters:** support a pull API adapter, a CSV export adapter with a watch folder and a manual upload path, and a manual entry adapter, all behind the single connector interface defined in Section 7.1, with per-adapter health reporting. | new |
| 21 | **Allowance tracking:** maintain a weekly allowance ledger that allocates at most 75 leads per week under an atomic transaction, computes the derived unit cost and the weekly waste, expires untouched allowance at week close and reports utilisation every week. | new |
| 22 | **Follow-up task engine:** create, assign, snooze, complete and cancel follow-up tasks with a due date derived from the inquiry and the lead's band; the engine never closes a task without a recorded human action. | new |
| 23 | **AI provider abstraction and cost caps:** route every model call through one provider interface supporting Google Gemini free tier as the default, Anthropic Claude paid as an optional upgrade, a local Ollama provider and a Null provider that degrades to deterministic-only; enforce a hard monthly spend cap and a per-lead model-call ceiling; refuse to exceed the cap and degrade rather than fail. | new |
| 24 | **Evaluation dataset gate:** maintain a reviewed evaluation dataset and block the promotion of any model phase until it satisfies the accuracy, precision and agreement thresholds in Section 19. | new |

---

### 3.15 Section 9 — Dashboard and UI (amended)

Replace Section 9 with:

> **9. Dashboard and UI**
>
> **9.1 Overview dashboard.** Show: allowance used this week (count and percentage of 75); allowance remaining; allowance wasted this week and its cost in derived unit-cost terms; median and 90th-percentile arrival-to-contact time for the current week; the count of leads in each band; the count of leads awaiting human contact; the count of open follow-up tasks; the connector status of every lead source; the outcome of the last ingestion run; and current monthly AI spend against the cap.
>
> **9.2 Inbox.** One ranked list for the week. Each row shows: band, total score, product match with the matched SKU name and strength, buyer type, destination country and its class, quantity, arrival timestamp, time since arrival, the age of the newest activity, the assigned owner, the status, and a one-line explanation. Filters: band, product, destination country, buyer type, status, owner, arrival window, contacted/not contacted. Sort: band, then score descending, then arrival time ascending. A "needs review" filter surfaces leads that fired no hard gate but scored between 55 and 69, plus leads whose best match had multiple candidates.
>
> **9.3 Lead detail.** Show: the source payload verbatim; the normalised fields; the score breakdown as five component bars with the points each contributed; the hard gates that fired and the specific field that caused each; the matched SKU with its export eligibility and restriction list; up to five candidate products with their match states; the buyer's contact details, decrypted only for a user with the `lead.contact.view` permission and with the read logged to the audit trail; the drafts for all three channels with their fact-check result; the approval and send records; the contact history; the follow-up tasks; and the full assessment history with policy versions.
>
> **9.4 Allowance and waste.** Show, per week: allocated, contacted, expired, utilisation percentage, waste units and waste cost; a trailing 4-week trend; the list of leads that expired untouched with their scores and bands, so the operator can see exactly what was let go; and the allocation events with actor and timestamp.
>
> **9.5 Settings.** Catalogue management; country classification; lead source configuration and health; feature flags with their shadow/promoted state and evidence reference; AI provider selection, per-provider spend to date and the monthly cap; session and password settings; retention period for buyer contact data; backup and restore status.
>
> **9.6 UI design.** Use a professional B2B SaaS design: clean light interface, consistent typography and spacing, responsive desktop and tablet layouts, accessible tables and forms, clear statuses, explicit loading, empty and error states, confirmations for high-impact actions, and no fabricated production metrics. Every displayed number must come from a stored record; estimates and shadow results must be visibly labelled as such.

---

### 3.16 Section 10 — Database Design (amended)

Replace Section 10 with:

> **10. Database Design**
>
> Use MySQL 8 in production with migrations, foreign keys, indexes, constraints and appropriate transactions. Use SQLite for local development and automated tests. Keep the schema portable across both: no MySQL-only column types, no reliance on MySQL-only collation behaviour for correctness, and no full-text search assumptions.
>
> | Table | Core fields and purpose |
> |---|---|
> | `users` | id, name, email, password_hash, role, is_active, last_login_at, timestamps |
> | `permissions` | id, key, label, description — grants held by roles |
> | `role_permission` | role, permission_id — role-to-permission mapping |
> | `products` | id, brand_name, generic_name, strength, strength_unit, dosage_form, category, pack_size, indicative_price_minor, currency, moq_quantity, moq_unit, export_eligible, active, timestamps |
> | `product_synonyms` | id, product_id, synonym, normalised_synonym, locale — free-text and misspelling aliases |
> | `product_restrictions` | id, product_id, country_code, restriction_type, reason — per-SKU destination restrictions |
> | `countries` | country_code, name, eligibility_class, is_proven_seller, notes, updated_at — the destination rule table |
> | `lead_sources` | id, name, connector_type, integration_status, config_ref, last_success_at, timestamps — replaces `portals`; holds no secret |
> | `source_runs` | id, source_id, window_start, window_end, status, records_received, records_ingested, records_duplicate, records_rejected, records_excluded, error, started_at, finished_at — one row per pull |
> | `leads` | id, source_id, external_lead_id, week_start, buyer_name_encrypted, buyer_email_encrypted, buyer_phone_encrypted, buyer_company_encrypted, buyer_email_hash, buyer_phone_hash, inquiry_raw, inquiry_normalised, buyer_type, destination_country, destination_class, requested_quantity, requested_quantity_unit, status, band, score, owner_id, first_contact_at, last_activity_at, arrived_at, timestamps |
> | `lead_matches` | id, lead_id, product_id, match_state, match_points, matched_tokens, is_top_1 — ranked candidates per lead; supports top-1 accuracy measurement |
> | `lead_assessments` | id, lead_id, policy_version, model_version, score_components, overall_score, band, hard_gates_fired, confidence, explanation, risk_flags, missing_information, assessed_at, is_shadow |
> | `allowance_weeks` | id, week_start, quota_total, allocated_count, contacted_count, expired_count, weekly_fee_minor, unit_cost_minor, currency, status, closed_at — one row per ISO week |
> | `allowance_allocations` | id, week_id, lead_id, source_id, external_lead_id, allocated_at, correlation_id — append-only allocation evidence |
> | `drafts` | id, lead_id, channel, language, template_id, template_version, body, fact_check_status, fact_check_detail, model_version, provider, approved_by, approved_at, sent_at, sent_by, channel_ref, timestamps |
> | `crm_activities` | id, lead_id, user_id, activity_type, channel, description, occurred_at — the contact log |
> | `crm_tasks` | id, lead_id, owner_id, title, due_at, status, priority, snoozed_until, completed_at, completed_by, timestamps |
> | `follow_ups` | id, lead_id, task_id, sequence_no, channel, status, scheduled_at, executed_at, result |
> | `ai_runs` | id, lead_id, phase, provider, model, prompt_version, input_tokens, output_tokens, cost_minor, latency_ms, status, is_shadow, error, created_at — the basis for spend caps and evaluation |
> | `ai_budget_periods` | id, period_month, cap_minor, spent_minor, calls_made, calls_blocked, currency |
> | `evaluation_cases` | id, case_ref, source, inquiry_text, expected_product_id, expected_buyer_type, expected_destination, expected_band, expected_hard_gate, expected_operator_decision, reviewer, reviewed_at |
> | `evaluation_runs` | id, phase, dataset_version, policy_version, metrics, thresholds, passed, evidence_ref, started_at, finished_at |
> | `feature_flags` | key, description, enabled, shadow, promoted_at, evidence_ref, updated_by, updated_at |
> | `audit_events` | id, actor_type, actor_id, action, entity_type, entity_id, correlation_id, result, metadata, ip, created_at |
> | `settings` | key, value, updated_by, updated_at — non-secret configuration only |
>
> **10.1 Constraints.** Unique on `leads (source_id, external_lead_id)`. Unique on `allowance_allocations (week_id, lead_id)`. Unique on `allowance_allocations (source_id, external_lead_id)`. Unique on `products (brand_name, generic_name, strength, dosage_form)`. Unique on `countries (country_code)`. Unique on `allowance_weeks (week_start)`. Unique on `ai_budget_periods (period_month)`. Unique on `evaluation_runs (phase, dataset_version)`.
>
> **10.2 Indexes.** Index `leads` on `(week_start, band, score)`, `(status)`, `(owner_id, status)`, `(destination_country)`, `(arrived_at)` and on the two blind-index columns `buyer_email_hash` and `buyer_phone_hash`. Index `lead_matches` on `(lead_id, is_top_1)`. Index `ai_runs` on `(phase, created_at)` and `(provider, created_at)`. Index `crm_tasks` on `(owner_id, status, due_at)`. Index `audit_events` on `(created_at)` and `(entity_type, entity_id)`.
>
> **10.3 Encryption.** `buyer_name_encrypted`, `buyer_email_encrypted`, `buyer_phone_encrypted` and `buyer_company_encrypted` use Laravel encrypted casts with a dedicated key. `buyer_email_hash` and `buyer_phone_hash` are SHA-256 hashes of the normalised value with a dedicated HMAC key, and exist so that search and deduplication work without decrypting. Decryption is permitted only inside the view permission check and is audit-logged.
>
> **10.4 Immutability.** `lead_assessments`, `allowance_allocations`, `ai_runs` and `audit_events` are append-only. Corrections are made by writing a new row that supersedes the old one, never by overwriting. `allowance_weeks` rows are mutable only between creation and week close.

---

### 3.17 Section 11 — Backend Architecture and APIs (amended)

Replace Section 11 with:

> **11. Backend Architecture and APIs**
>
> **11.1 Modules.** Organise the backend into these modules: authentication and authorisation; product catalogue; lead source connectors; ingestion; normalisation; deduplication; product matching; qualification; policy and scoring; ranking; allowance; CRM; drafting; notifications; analytics; AI provider abstraction; evaluation; and audit logging. The module map and the public method of each module are specified in `02_ARCHITECTURE.md`.
>
> **11.2 Endpoints.** These are internal application endpoints. They are not portal APIs.
>
> | Endpoint | Purpose |
> |---|---|
> | `POST /api/auth/login` | Authenticate a user and open a session |
> | `POST /api/auth/logout` | Terminate the session |
> | `GET /api/auth/me` | Return the current user, role and permissions |
> | `GET /api/dashboard` | Overview metrics for the current week |
> | `GET /api/inbox` | The ranked lead list for a week, with filters and pagination |
> | `GET /api/leads` | Search and filter across all weeks |
> | `GET /api/leads/{id}` | Lead detail including assessments, matches, drafts, contacts and tasks |
> | `POST /api/leads/import` | Upload a CSV export batch or submit manual entries |
> | `POST /api/leads/{id}/reassess` | Re-run scoring and policy evaluation for one lead |
> | `GET /api/leads/{id}/drafts` | List the drafts for a lead |
> | `POST /api/leads/{id}/drafts/{draftId}/regenerate` | Regenerate a draft from the current templates and SKU record |
> | `POST /api/leads/{id}/drafts/{draftId}/approve` | Record a human approval of a draft |
> | `POST /api/leads/{id}/drafts/{draftId}/mark-sent` | Record that a human sent the draft on a given channel |
> | `GET /api/catalog/products` | Search and paginate the catalogue |
> | `POST /api/catalog/products` | Create a SKU |
> | `PUT /api/catalog/products/{id}` | Update a SKU, including export eligibility and restrictions |
> | `POST /api/catalog/products/import` | Bulk import or upsert SKUs from a spreadsheet |
> | `GET /api/catalog/countries` | List country classifications |
> | `PUT /api/catalog/countries/{code}` | Change a country's eligibility class or proven-seller flag |
> | `GET /api/allowance/current` | The current week's ledger and derived figures |
> | `GET /api/allowance/history` | Week-by-week utilisation and waste |
> | `GET /api/allowance/waste` | The leads that expired untouched in a given week |
> | `GET /api/tasks` | List follow-up tasks by owner, status or due window |
> | `POST /api/tasks` | Create a follow-up task |
> | `PATCH /api/tasks/{id}` | Update, snooze, complete or cancel a task |
> | `GET /api/crm/leads/{id}/activities` | The contact history for a lead |
> | `POST /api/crm/leads/{id}/activities` | Log a contact |
> | `GET /api/analytics/summary` | Aggregate metrics for a selected period |
> | `GET /api/analytics/triage` | Band distribution, operator agreement and review queue size |
> | `GET /api/evaluation/dataset` | The evaluation cases, filtered by phase |
> | `POST /api/evaluation/run` | Run the harness for a phase against a dataset version |
> | `GET /api/evaluation/runs` | Historical evaluation results and pass/fail |
> | `GET /api/ai/usage` | Spend to date per provider, against the cap |
> | `PUT /api/ai/provider` | Select the active provider and set the cap |
> | `GET /api/feature-flags` | Flag states, shadow status and evidence references |
> | `PATCH /api/feature-flags/{key}` | Enable, disable, shadow or promote a flag |
> | `GET /api/audit` | Query the audit trail |
> | `POST /api/system/ingest-pause` | Pause new batch ingestion |
> | `DELETE /api/system/ingest-pause` | Resume ingestion |
> | `GET /api/system/health` | Liveness, database, queue, connector and provider health |
>
> **11.3 Controls on all endpoints.** Authorisation on every route; server-side validation of every input; idempotency keys on the mutating routes that can be retried (`import`, `mark-sent`, `activities`); an audit record for every state change; rate limits on authentication; and correlation IDs propagated from the HTTP request through the queue and into the audit trail.

---

### 3.18 Section 12 — Technology Stack (amended)

> **12. Technology Stack**
>
> The stack is fixed by this document and must not be changed without a written amendment.
>
> **12.1 Frontend.** React 19 with TypeScript, Vite, Tailwind CSS and shadcn/ui. Component library, responsive styling, validated forms, sortable and filterable tables, accessible dialogs, and charts for the allowance and waste views. No frontend component library other than shadcn/ui and no state management library other than the React built-ins plus the query cache already implied by the API surface.
>
> **12.2 Backend.** PHP 8.3 with Laravel 11. Eloquent with migrations, foreign keys, indexes and uniqueness constraints; Form Requests for validation; policies for authorisation; queues for background work; the scheduler for recurring jobs; middleware for correlation IDs and request logging; and structured logging with a correlation ID on every line.
>
> **12.3 Database.** MySQL 8 in production, SQLite in development and automated tests. Schema portability rules in Section 10 apply to both.
>
> **12.4 AI.** A provider abstraction with four implementations: Google Gemini free tier as the default, Anthropic Claude as a paid optional upgrade, a local Ollama provider, and a Null provider that returns no model output and forces the deterministic path. Deterministic scoring and deterministic normalisation are the primary path in every version. A hard monthly spend cap is enforced in code, not only in configuration.
>
> **12.5 Infrastructure.** Local deployment on a single always-on Windows host. Nginx or Apache on Windows serving the built SPA and the Laravel API from one origin. MySQL as a Windows service. A Windows queue worker and a Windows scheduler process. Windows Task Scheduler for triggers. Nightly `mysqldump` backups with a second copy mirrored to a separate physical drive, and a monthly restore drill. No public exposure and no cloud deployment.
>
> **12.6 Removed from the stack.** Secure cloud or server deployment, HTTPS termination for LAN-only use, load balancers, Redis as a required dependency, message brokers, and multi-host orchestration. Redis remains an optional cache driver if it is already present and is not required for correctness.
>
> **12.7 Additions to the stack.**
>
> | Addition | Purpose | Section |
> |---|---|---|
> | Provider abstraction package | One interface, four backends, one cost meter | 5.4.2, FR-23 |
> | Windows Task Scheduler entries | Pull, week close, backup, purge, monitoring | 21 |
> | `mysqldump` backup script with retention | Restore-tested backups | 21, 16 |
> | Feature-flag store in the database | Shadow-first promotion with evidence | 2.4.1, FR-24 |
> | Correlation ID middleware | End-to-end traceability | 11.3 |
> | HMAC blind-index service | Search and dedupe over encrypted buyer PII | 10.3 |
> | Evaluation harness as an artisan command | Repeatable, versioned measurement | FR-24, 19 |

---

### 3.19 Section 13 — Security and Privacy (amended)

> **13. Security and Privacy**
>
> **13.1 Identity and access.** Retained from v1.0: secure password hashing and session handling; role-based authorisation and rate-limited login; stronger authentication for privileged users where supported. Add a 30-minute inactivity timeout on the inbox, lead-detail, settings and audit routes.
>
> **13.2 Secrets.** Retained from v1.0: credentials live in environment variables only; never exposed to React; never committed to Git; rotated and redacted from logs; least-privilege access. Add the explicit rule that the SPA receives only a boolean `configured` flag for each source, never a URL, a key or a credential.
>
> **13.3 Application security.** Retained from v1.0: server-side validation, parameterised queries, CSRF protection, XSS prevention, narrow CORS, request size and rate limits, append-only audit history. Replace the requirement to reserve budget atomically with the requirement to allocate allowance atomically.
>
> **13.4 AI security.** Retained from v1.0: treat inquiry text as untrusted; restrict the model to analysis; never provide credentials or tools; validate responses; fail safely when the model is unavailable. Extend with Section 13.11.
>
> **13.5 Privacy.** Retained from v1.0: collect only what is needed, restrict contact data, define retention and deletion, protect exports, respect privacy law and portal terms, never repurpose buyer data for model training.
>
> **13.6 Network exposure.** Bind the web server to the office LAN address only. Permit inbound traffic on the application port through Windows Firewall for the private profile only, scoped to the office subnet. Do not forward the port on the router and do not expose the host to the internet. The MySQL port is not opened to the LAN; the application connects to it over loopback. If the sales team must reach the system from outside the LAN, do so through the office VPN and never by publishing the port.
>
> **13.7 Buyer PII encryption.** Buyer name, email, phone and company are stored encrypted at rest using Laravel encrypted casts with a dedicated key that is separate from `APP_KEY`. Search and deduplication use the HMAC blind-index columns defined in Section 10.3. Plaintext PII never appears in logs, in the AI request path, in exports without the `lead.contact.export` permission, or in any API response returned to a user without the `lead.contact.view` permission. Decryption events are audit-logged.
>
> **13.8 Session hardening.** Set `SESSION_SECURE_COOKIE=false` while the system is HTTP-on-LAN, with the explicit intention to set it to `true` if HTTPS is introduced; `SESSION_HTTP_ONLY=true`; `SESSION_SAME_SITE=lax`; `SESSION_LIFETIME=480` minutes (one working day); session storage in the database so that a worker restart does not log everyone out. Re-authentication is required before changing the allowance fee, the country classification, the feature flags, the AI provider or the monthly cap. Rate-limit login to 5 attempts per minute per IP with a 15-minute lockout.
>
> **13.9 Log redaction.** A redaction filter removes buyer phone numbers, email addresses, API keys, portal credentials, session identifiers and `APP_KEY`-derived values from every log context before the line is written. Inquiry text is logged only in truncated form (first 120 characters) and only at debug level.
>
> **13.10 Retention and purge.** Buyer contact PII is retained for 180 days after the last activity on the lead, then anonymised: the encrypted columns are replaced with a hashed reference and the lead becomes non-contactable. Inquiry text is retained for 365 days, then blanked. Drafts are retained for 180 days. Assessment records, allowance allocations and audit events are retained for 3 years. The purge job runs nightly and writes a summary record of how many rows it touched.
>
> **13.11 AI data egress.** No third-party AI call may carry buyer name, email, phone or company name. The default request contains the inquiry body only, with contact details, email addresses, phone numbers and any occurrence of the company's own name redacted before it leaves the machine. A local Ollama provider makes no egress at all and is the only provider permitted to receive unredacted inquiry text. Whether redaction is sufficient, and whether third-party egress is acceptable at all, is tracked as open question OQ-11.
>
> **13.12 Single-host posture.** The application, the database and the backups share one physical host. This is accepted for v1 because the data volume is 75 leads per week and the business is local. It is a conscious risk recorded in `13_RISKS_OPEN_QUESTIONS.md` as R-07 and revisited if a second site is ever added.

---

### 3.20 Section 14 — Pharmaceutical Export Compliance (replaced)

Delete Section 14 in full and replace with:

> **14. Destination and Product Eligibility**
>
> **14.1 Principle.** A commercially interesting inquiry is not an eligible one. The system must never assume a product may be sold into a country because a buyer asked for it.
>
> **14.2 Country classes.** Every country carries exactly one eligibility class in the `countries` table:
>
> | Class | Meaning | Effect on scoring | Effect on gates |
> |---|---|---|---|
> | `allowed_proven` | We sell there and have done so | 20 points | none |
> | `allowed_unproven` | We are permitted to sell there, no deliveries yet | 14 points | none |
> | `restricted` | Permitted only for SKUs not listed as restricted there | 8 points | `product_restricted_in_destination` fires when the SKU is listed |
> | `blocked` | We do not sell there at all | 0 points | `destination_blocked` fires |
> | `unknown` | Not yet classified | 0 points | none, but the lead cannot reach `act_now` because the destination component minimum is 14 |
>
> A country with no row is treated as `unknown`. Unresolved destination is a zero, not a default-allow.
>
> **14.3 SKU eligibility.** Each SKU carries `export_eligible` and a list of restricted countries. A SKU with `export_eligible = false` is never actionable anywhere.
>
> **14.4 Eligibility matrix.**
>
> | SKU export-eligible | SKU restricted in destination | Country class | Result |
> |---|---|---|---|
> | Yes | No | `allowed_proven` or `allowed_unproven` | Eligible, scored normally |
> | Yes | No | `restricted` | Eligible with reduced destination points |
> | Yes | No | `unknown` | Not actionable, sent to review |
> | Any | Yes | Any | Hard gate `product_restricted_in_destination` |
> | No | Any | Any | Hard gate `product_not_export_eligible` |
> | Any | Any | `blocked` | Hard gate `destination_blocked` |
>
> **14.5 Human review.** A country in class `restricted` or `unknown`, an SKU with a restriction list, a buyer type that cannot be resolved confidently, and any inquiry matching a schedule-1 or schedule-2 category flag must be confirmed by a qualified human before the buyer is contacted. The system records the requirement and blocks the draft from being marked as sent until the review is recorded.
>
> **14.6 Scope of this release.** This section encodes the destination rules the office actually applies today as configured data. It is a screening aid and a record of what the system knew. It is not a regulatory determination, it does not replace the work of qualified compliance personnel, and it does not verify licences, registrations, certificates, import permits, sanctions or restricted-party status against any authority. Those checks remain outside the product's scope and are tracked as an open dependency, not as a feature.

---

### 3.21 Section 15 — Monitoring and Observability (amended)

Replace Section 15 with:

> **15. Monitoring and Observability**
>
> **15.1 Signals to record.**
>
> | Area | Signals |
> |---|---|
> | Ingestion | Leads retrieved, ingested, deduplicated, validation-rejected, allowance-excluded; per-source run duration; portal-versus-system count difference |
> | Processing | Per-stage duration; leads per band; no-match rate; multiple-candidate rate; hard-gate counts per gate; assessment failures |
> | Quality | Operator agreement rate; per-band operator override rate; most frequent disagreement cause; days since the last labelled case |
> | Allowance | Allocated, contacted, expired, utilisation, waste units, waste cost, week-close status, allocation conflict attempts |
> | Response | Median and 90th-percentile arrival-to-contact; open tasks past due; drafts approved but not marked sent |
> | AI | Calls per phase, per provider; tokens in and out; cost per call; spend against the monthly cap; blocked calls; circuit state; latency; validation failures |
> | Application | Queue depth, failed jobs, authentication failures, 5xx rate, request latency percentiles, disk free space, MySQL uptime |
> | Platform | Backup success, backup age, last successful restore drill, queue worker uptime |
>
> **15.2 Alerts.** Raise an alert on: a failed ingestion run; a portal-versus-system count difference that persists for two runs; allocation of more than 75 units in a week; utilisation below 85 per cent at 48 hours before week close; the AI monthly spend reaching 80 per cent of the cap; the AI cap being reached; more than three consecutive model validation failures; an operator agreement rate below 90 per cent over a rolling 50 leads; a backup failure or a backup older than 30 hours; a disk below 15 per cent free; a queue depth above 500; and a database restart during working hours.
>
> **15.3 Delivery.** On a single Windows host there is no external alerting service. Deliver alerts by writing to a dedicated log file, raising a Windows Task Scheduler event, and sending an email to the operator through the configured mail transport. The dashboard's health panel shows the same signals. The in-app health panel must be reachable without the email path working.
>
> **15.4 Honesty.** Confirmed facts, derived figures and shadow results must be visibly distinguishable. A shadow score must never be presented as the live score, and a derived waste cost must be labelled as derived from the configured weekly fee.

---

### 3.22 Section 16 — Testing Strategy (amended)

Replace Section 16 with:

> **16. Testing Strategy**
>
> **16.1 Unit tests.** Cover normalisation, product matching, every scoring component, every hard gate, band assignment, ranking order, the allowance arithmetic, the derived unit cost, waste, the draft fact check and the template engine. Target at least 80 per cent line coverage in the Scoring, Policy, Allowance and Drafter modules and at least 60 per cent elsewhere.
>
> **16.2 Integration tests.** Cover ingestion from each adapter, persistence, the deduplication constraint, the allocation transaction, the week-close job, the purge job, the evaluation harness, provider invocation and cost accounting, and notification dispatch.
>
> **16.3 Allowance and concurrency tests.**
> - Twenty concurrent workers ingesting the same 75-record batch must produce exactly 75 leads and exactly 75 allocations, with zero over-allocation.
> - Re-running the same batch must produce zero new leads and zero new allocations.
> - A week that reaches 75 allocations must reject the 76th with `quota_exceeded` and must not raise the inbox.
> - A failed allocation must roll back the lead write entirely.
>
> **16.4 Deduplication tests.** The same export imported twice, the same export imported through two adapters, and the same lead delivered twice by the source must each produce one lead row and one allocation.
>
> **16.5 Evaluation tests.** The M0 harness runs the reviewed dataset through the pipeline and reports product-match top-1 accuracy, no-match precision, hard-gate precision, band agreement and the fact-check pass rate. The dataset covers strong matches, unrelated requests, ambiguous requests, incomplete inquiries, Hinglish and broken-English constructions, misleading product names, prompt-injection attempts, duplicate submissions, and restricted destinations. Minimum sizes: 150 cases for the matcher, 40 cases for hard gates, of which at least 20 are hard-reject positives and at least 20 are hard-reject negatives.
>
> **16.6 Prompt-injection tests.** Submit at least 20 crafted inquiries containing instruction-like text, requests to disclose configuration, fake score instructions and fake gate overrides. Assert that no gate is overridden, no schema field is altered and no system text is echoed.
>
> **16.7 End-to-end tests.** Lead received, validated, normalised, deduplicated, matched, qualified, policy-checked, allocated, ranked, drafted, approved, marked sent, logged, tasked and closed.
>
> **16.8 Backup and restore tests.** Monthly, and after any schema change: restore the latest dump onto a scratch database, verify row counts against the source, and record the result. A restore that does not succeed onto a scratch database is a failed gate.
>
> **16.9 Contract tests.** For every adapter, a test suite runs against recorded fixtures so that a change in the source format is detected before it reaches production. The API adapter's contract tests cannot be written until P0 confirms the feed exists; the CSV adapter's contract tests are written against the actual export format observed during P0.

---

### 3.23 Section 17 — Non-Functional Requirements (amended)

Replace Section 17 with:

> **17. Non-Functional Requirements**
>
> **17.1 Performance.**
> - Overview dashboard: p95 below 800 ms with 75 leads in the current week.
> - Inbox list: p95 below 1200 ms for 75 leads with filters applied.
> - Lead detail: p95 below 900 ms including decryption of one buyer contact.
> - Full ingest of a 75-lead weekly batch from a CSV upload to a ranked, drafted inbox: within 5 minutes, measured end to end.
> - Deterministic assessment of one lead: below 250 ms.
> - Draft generation for three channels: below 3 seconds per lead.
> - Model-based extraction, when enabled: below 8 seconds per lead at p95; 20 seconds is the hard timeout.
> - Catalogue search across 1000+ SKUs: below 300 ms at p95.
>
> **17.2 Reliability.**
> - The service is available for at least 99.5 per cent of a 7-day window, measured by the health endpoint.
> - Recovery point objective for the database: 24 hours. Recovery time objective: 4 hours, including a restore drill.
> - Zero duplicate ingestion on re-run, enforced by a database constraint and verified by test.
> - Zero allowance over-allocation under 20 concurrent workers.
> - Queue jobs are retry-safe, carry a correlation ID, and are recoverable after a worker or host restart.
> - Backups are verified by a monthly restore onto a scratch database.
>
> **17.3 Scalability.**
> - 20 concurrent workers on one host without over-allocation or duplicate ingestion.
> - 1000+ SKUs with sub-second catalogue search.
> - Sustained volume of 75 to 500 leads per week without schema change.
> - Worker count configurable independently of the web process; the two scale separately.
>
> **17.4 Security and privacy.**
> - Buyer contact PII encrypted at rest; no plaintext PII in logs, exports without permission, or AI requests.
> - Session cookies carry `HttpOnly` and `SameSite=Lax`; the application binds to the LAN only.
> - All mutating endpoints require CSRF tokens, authorisation and an audit record.
> - Re-authentication before changes to allowance fee, country classification, feature flags, provider or cap.
>
> **17.5 Maintainability.**
> - PSR-12 formatting enforced by Pint; every module has a documented public interface in `02_ARCHITECTURE.md`.
> - Every schema change ships as a reversible migration; every policy change increments `policy_version`.
> - Every feature flag records its promotion evidence.
> - No source file exceeds 400 lines; no method exceeds 60 lines.
>
> **17.6 Accessibility.**
> - Meet WCAG 2.1 AA on the inbox, the lead detail and the settings pages.
> - Full keyboard operability of the inbox, including sorting and filtering.
> - Minimum 44 by 44 pixel touch targets on tablet layouts.
> - Every status conveyed by text as well as by colour.

---

### 3.24 Section 18 — KPIs (amended)

Replace Section 18 with:

> **18. KPIs**
>
> The system tracks exactly seven KPIs. Each is computed from stored records, is reproducible from the dashboard, and names its source tables.
>
> | # | KPI | Definition | Target | Source |
> |---|---|---|---|---|
> | 1 | **Weekly allowance utilisation** | Leads with at least one logged human contact divided by 75 | At or above 90 per cent | `allowance_weeks`, `crm_activities` |
> | 2 | **Weekly waste cost** | `(75 - contacted_count) * unit_cost_minor`, expressed in units and in derived currency | At most 7 units per week, at most 10 per cent of the weekly fee | `allowance_weeks` |
> | 3 | **Median arrival-to-contact** | Median minutes from `arrived_at` to the first entry in `crm_activities` for the same lead | Below 30 minutes | `leads`, `crm_activities` |
> | 4 | **Operator agreement rate** | Leads where the system's band agrees with the operator's recorded decision, divided by leads assessed | At or above 90 per cent | `lead_assessments`, `crm_activities` |
> | 5 | **Product-match top-1 accuracy** | Share of evaluation cases whose top-ranked catalogue candidate is the reviewer's expected SKU | At or above 85 per cent | `evaluation_runs` |
> | 6 | **Hard-gate precision** | Share of hard-rejected leads that the operator also judged not worth contacting | 100 per cent | `evaluation_runs`, `lead_assessments` |
> 7 | **Monthly AI spend against cap** | Sum of `ai_runs.cost_minor` for the calendar month against `ai_budget_periods.cap_minor` | At or below 100 per cent of the cap; alert at 80 per cent | `ai_runs`, `ai_budget_periods` |
>
> Supporting metrics that are tracked but are not KPIs: hard-gate firing counts per gate, no-match rate, multiple-candidate rate, review queue size, drafts approved but not marked sent, tasks overdue, ingest run duration, per-stage processing latency, and the portal-versus-system count difference.
>
> No KPI in this list measures conversion, revenue or return on acquisition. v1 has no outcome labels, so any such metric would be fabricated.

---

### 3.25 Section 19 — Acceptance Criteria (amended)

Replace Section 19 with:

> **19. Acceptance Criteria**
>
> **19.1 Build gates.** These are hard gates. If any is breached, stop and fix before proceeding to the next phase.
>
> | # | Gate | Threshold |
> |---|---|---|
> | G1 | Product-match top-1 accuracy on the evaluation set | At or above 85 per cent |
> | G2 | Hard-reject precision | 100 per cent |
> | G3 | Allowance over-allocation under 20 concurrent workers | Zero |
> | G4 | Duplicate ingest on re-run | Zero |
> | G5 | No-match precision | At or above 95 per cent |
> | G6 | Draft fact-check pass rate | 100 per cent |
> | G7 | Backup restored successfully onto a scratch database | Yes |
> | G8 | Monthly AI spend against the cap | Never above the cap |
> | G9 | Pilot median arrival-to-contact | Below 30 minutes |
> | G10 | Weekly allowance utilisation | Above 90 per cent |
>
> **19.2 Functional acceptance criteria.** Each is a pass/fail statement observable by a reviewer.
>
> | # | Criterion |
> |---|---|
> | 1 | Authorised users can log in and access only permitted features; unauthorised requests return 403. |
> | 2 | An Administrator can add, edit and retire catalogue SKUs, including export eligibility and country restrictions. |
> | 3 | The CSV adapter ingests a real export, and the manual entry path produces a lead that appears in the inbox. |
> | 4 | Re-importing the same export produces zero new leads. |
> | 5 | Every assessment stores a score, a component breakdown, an explanation, the policy version and the model version or `null`. |
> | 6 | A lead that fires any hard gate is excluded from the inbox, is labelled with the gate, and cannot be promoted by any configuration change or by any model output. |
> | 7 | The allowance ledger shows allocated, contacted, expired, utilisation, waste units and waste cost for the current week and for history. |
> | 8 | Twenty concurrent workers against one batch produce exactly 20 allocations and never exceed the weekly quota. |
> | 9 | A draft is produced for each channel for every eligible lead, and the fact check is displayed with its result. |
> | 10 | The system never transmits a message to a buyer; the only send path records a human action with a timestamp and an actor. |
> | 11 | The dashboard's figures are reproducible from the underlying records by the Analytics module. |
> | 12 | Connector status reflects verified capability and shows the last successful run. |
> | 13 | Secrets appear nowhere in the frontend bundle, in the logs, or in any API response. |
> | 14 | Buyer contact PII is encrypted at rest, redacted from logs, and absent from every AI request. |
> | 15 | A follow-up task cannot reach the closed state without a recorded human action. |
> | 16 | AI spend is metered per provider, displayed against the cap, and the system degrades to deterministic-only when the cap is reached. |
> | 17 | The evaluation harness produces a reproducible report with the thresholds in Section 19.1 and the promotion evidence in Section 2.4.1. |
> | 18 | The automated test suite covers the hard gates, the allocation transaction, the deduplication constraint and the draft fact check. |
> | 19 | A nightly backup runs unattended, and a restore onto a scratch database succeeds and is verified. |
> | 20 | With the office PC off for up to 72 hours, the system catches up on restart, ingests the missed batch, and does not allocate more than 75 units in the affected week. |

---

### 3.26 Section 20 — Risks and Mitigation (amended)

Replace Section 20 with:

> **20. Risks and Mitigation**
>
> | Risk | Mitigation |
> |---|---|
> | No machine-readable feed exists for the contracted leads | Build the CSV export adapter and the manual entry adapter first and make them production-grade; treat the API adapter as optional. Verified in P0, not assumed |
> | Free-tier AI limits are exhausted or revoked | The Null provider and the deterministic path are the default; every model phase is optional and shadow-first; the cap is enforced in code |
> | Product matching on 1000+ SKUs with mixed naming underperforms | The M0 harness measures it before M2 ships; synonym and alias tables; the M7 draft and the operator override feed corrections back into the catalogue |
> | Buyer text leaves the machine to a third-party AI | Redaction before egress; no PII in the request path; Ollama as the zero-egress option; the egress decision is tracked as an open question |
> | The weekly delivery does not match the 75 assumption | The allocation logic is driven by a configurable quota, not a constant; the count difference against the portal is monitored and alerted |
> | No outcome labels means no conversion insight | v1 captures the fields needed for later labelling; the objective is qualified and contacted leads, not conversion; no conversion claim is made anywhere |
> | The office PC is off, rebooting or on a Windows update | The host is configured for automatic restart on power loss; catch-up ingestion on boot; the backlog window is 72 hours; the queue and the allowance transaction are restart-safe |
> | Operators over-trust the score | The band is one input beside the inquiry text; the operator can override any band; overrides are recorded and feed the agreement metric; every lead shows its own reasoning |
> | Catalogue data quality is poor or stale | A completeness report on export eligibility and restrictions; a monthly review of the top 200 SKUs by match frequency; unmatched leads are surfaced weekly |
> | The team does not record contacts or outcomes | The contact log is one click in the same screen as the draft; a daily reminder lists unlogged contacts; the median arrival-to-contact KPI is meaningless without it |
> | A week of allowance is delivered differently than expected | Allowance allocation is idempotent and count-based; the difference against the portal count is monitored daily |
> | Single-host failure loses the business data | Nightly backups to `C:\PharmaLead\backups` with the weekly set mirrored to a second physical drive, 14-day retention, and a monthly restore drill onto a scratch database |
> | A hard gate is wrong and rejects a good lead | The gate list is configurable but every change is audit-logged and versioned; the operator can manually release a lead with a recorded reason; hard-gate precision is measured at 100 per cent |

---

### 3.27 Section 21 — Deployment Plan (amended)

Replace Section 21 with:

> **21. Deployment Plan**
>
> **21.1 Topology.** One always-on Windows host on the office LAN runs MySQL 8 as a service, a web server serving the built SPA and the Laravel API from one origin, a queue worker, a scheduler process, and the backup job. The operator and the sales team reach the system through the LAN by hostname or fixed IP. The host is never exposed to the internet.
>
> **21.2 Development.** Local environment, SQLite database, mock adapters, a fixture set of at least 200 historical inquiries, the evaluation harness, and the automated test suite. No production data is used in development.
>
> **21.3 Promotion to production.** Build the SPA, run the full test suite, run the evaluation harness against the current dataset version, apply migrations, restart the queue worker and the scheduler, verify the health endpoint, verify that a backup succeeded, and confirm the feature-flag state before switching traffic. Every promotion is recorded.
>
> **21.4 Windows Task Scheduler.** Register the following tasks, each set to run at startup with missed runs re-triggered:
>
> | Task | Cadence | Command |
> |---|---|---|
> | Start web server, worker and scheduler | At startup | Service start script |
> | Ingestion pull | Daily at 08:00 and 16:00 | `php artisan leads:pull` |
> | Week close | Mondays at 00:05 | `php artisan allowance:close-week` |
> | Backup | Daily at 02:00 | `backup.ps1` using `mysqldump` |
> | Retention purge | Daily at 03:00 | `php artisan retention:purge` |
> | Health probe | Every 5 minutes | `php artisan system:health-check`, writes a status file |
> | Restore drill | Monthly on day 1 at 04:00 | `php artisan backup:verify-restore` onto a scratch database |
>
> **21.5 Backups.** `mysqldump` with a single transaction flag, compressed, written to a dated file under `C:\PharmaLead\backups`, with 14 daily files and 8 weekly files retained. Weekly files are mirrored to a second physical drive so that a single-disk failure does not destroy both the database and every copy of it. Each run writes a success or failure marker. The health endpoint reports backup age.
>
> **21.6 Rollback.** Every release is deployed from a versioned artefact directory; the previous directory is retained. Rollback is a directory switch plus a migration revert when the migration is reversible, followed by a worker restart. Database migrations are never applied destructively; a failed release leaves the schema forward-compatible with the previous release.

---

### 3.28 Section 22 — Suggested Development Timeline (amended)

Replace Section 22 with:

> **22. Suggested Development Timeline**
>
> One solo full-time developer. Estimates are working days and include the phase's own testing.
>
> | Phase | Scope | Days | Cumulative |
> |---|---|---|---|
> | P0 | Discovery: verify the IndiaMART feed, export format and account terms; confirm the weekly fee, the delivery schedule, the proven-seller country list and the sales team size; freeze the catalogue export | 1 | 1 |
> | P1 | Foundation: project setup, authentication and roles, permissions, migrations, the module skeleton, the correlation ID middleware, the feature-flag store and the audit log | 5 | 6 |
> | P2 | Catalog: SKU model and import, synonyms, restrictions, the country table, catalogue search and the M0 evaluation harness with the reviewed dataset | 6 | 12 |
> | P3 | Pipeline: connectors (CSV, manual, API stub), ingestion, normalisation, deduplication, the product matcher, the qualifier, the scorer and the policy engine | 6 | 18 |
> | P4 | Workflow: the allowance ledger with atomic allocation and week close, the ranked inbox, template-anchored drafts with the fact check, the CRM, the contact log and the follow-up task engine | 6 | 24 |
> | P5 | Visibility: the dashboard, the allowance and waste views, the triage agreement view, exports, the monitoring signals and the alert delivery | 5 | 29 |
> | P6 | Hardening: the concurrency and dedupe test suites, the prompt-injection suite, the evaluation thresholds, the backup and restore drill, the retention purge, the security review and the PII encryption | 6 | 35 |
> | P7 | Pilot: 4 weeks of live operation against the real allowance, agreement measurement, disagreement review, tuning of the catalogue and the promotion evidence | 5 | 40 |
>
> **22.1 v1.1.** Model phases M4, M5 and M6 with shadow operation and promotion: 5.5 days.
>
> **22.2 Total.** v1 is approximately 40 working days. v1.1 adds 5.5 days. There is no fixed deadline, so the sequence is driven by the gates in Section 19.1 rather than by the calendar: a phase does not finish because its days ran out, it finishes when its gate passes.
>
> **22.3 Dependencies that can move the estimate.** The P0 verification outcomes are the largest single variable. If no authorised feed exists and the export format is unstable, P3 grows. If the catalogue arrives incomplete, P2 grows.

---

### 3.29 Section 23 — Future Enhancements (amended)

Replace Section 23 with:

> **23. Future Enhancements**
>
> Ordered by the value they unlock and gated by the evidence each requires.
>
> | # | Enhancement | Gate before it is attempted |
> |---|---|---|
> | 1 | Outcome capture: structured contact, reply, quotation and order statuses | Pilot proves the team logs contacts reliably |
> | 2 | Per-lead purchasing capability | The business obtains a contract with a variable per-lead price; the whole of Section 6 is then rewritten and reviewed |
> | 3 | Confidence calibration and a published reliability curve | At least 200 operator-labelled leads |
> | 4 | Learned ranking on outcomes | At least 500 labelled leads with a stable label definition |
> | 5 | A second marketplace connector | The first connector has run a full month and its contract tests are stable |
> | 6 | Multilingual inquiry extraction beyond English and Hinglish | Evidence that the non-English volume justifies it |
> | 7 | Lawful buyer-company enrichment | A lawful, vendor-provided source and a privacy review |
> | 8 | Sales assignment optimisation | At least three operators and six weeks of workload data |
> | 9 | Existing CRM integration | The team confirms which CRM and what it already stores |
> | 10 | A published per-portal performance comparison | Two portals with comparable volumes |
> | 11 | Advanced fraud and anomaly detection | Enough history to define an anomaly |
> | 12 | A per-lead acquisition cost model | The outcome labels that make it meaningful exist |
>
> Items 1 and 2 are the only two that change the product's economics. Item 2 requires a new amendment to this document before any code is written.

---

### 3.30 Section 24 — Final Product Definition (replaced)

Delete Section 24 in full and replace with:

> **24. Final Product Definition**
>
> PharmaLead AI is a controlled lead-triage and response system for pharmaceutical exporters. It ingests the weekly contracted allowance of 75 pre-paid buyer inquiries, normalises them, matches them against the catalogue of 1000+ SKUs, evaluates destination and buyer-type eligibility, scores them deterministically out of 100, ranks them into a single weekly inbox, drafts a WhatsApp message, an email and a call opener for each eligible lead, tracks the human follow-up, and prices the allowance that went untouched.
>
> **AI evaluates commercial relevance; the backend enforces the rules; a human contacts the buyer; the database records the evidence.**
>
> The system never buys a lead, because the allowance is bought in advance. The system never sends a message, because a human is faster and more careful than a template server. The system never overruns the quota, because the allocation is a single locked row in the database.
>
> The first milestone is a complete, testable application that ingests a real weekly batch, scores it, explains every decision, drafts every message, and prices the waste — with all ten gates in Section 19.1 passing. The API connector is added only if P0 proves that an authorised feed exists.

---

### 3.31 Appendix A — Initial Build Checklist (amended)

Replace Appendix A with:

> **Appendix A. Initial Build Checklist**
>
> **Phase P0 — Discovery.**
> - [ ] Verify the IndiaMART feed for the contracted BuyLeads: existence, authorisation, terms, fields, rate limits, delivery schedule.
> - [ ] Record whether the 75 leads arrive as one batch or spread across the week, and the day and time of arrival.
> - [ ] Capture one real CSV export and store it as a fixture.
> - [ ] Confirm the weekly fee figure and the currency.
> - [ ] Confirm the proven-seller country list and the current restricted and blocked lists.
> - [ ] Confirm the sales team size and the assignment rule.
> - [ ] Confirm the retention period acceptable for buyer contact data.
> - [ ] Obtain the catalogue of 1000+ SKUs with export eligibility and restrictions.
>
> **Phase P1 — Foundation.**
> - [ ] Authentication, roles, permissions, and the re-authentication rule for critical settings.
> - [ ] Database schema and migrations for every table in Section 10.
> - [ ] Correlation ID middleware, structured logging, log redaction.
> - [ ] Feature-flag store and the audit log.
> - [ ] The 14 Laravel modules and their documented public interfaces.
>
> **Phase P2 — Catalogue and evaluation.**
> - [ ] SKU import and upsert, synonyms, restrictions, the country table.
> - [ ] Catalogue search and the SKU completeness report.
> - [ ] The M0 evaluation harness and the reviewed dataset of at least 150 matcher cases and 40 gate cases.
>
> **Phase P3 — Pipeline.**
> - [ ] The three adapters behind one connector interface, with health reporting.
> - [ ] Ingestion, validation, normalisation, deduplication.
> - [ ] The product matcher with ranked candidates.
> - [ ] The qualifier, the scorer, the seven hard-gate identifiers and the band assignment.
>
> **Phase P4 — Workflow.**
> - [ ] The allowance ledger, the atomic allocation, the week-close job.
> - [ ] The ranked inbox, the filters, the lead detail.
> - [ ] The draft templates, the three channels, the fact check.
> - [ ] The CRM, the contact log, the task engine.
>
> **Phase P5 — Visibility.**
> - [ ] The dashboard, the allowance and waste views, the triage agreement view.
> - [ ] The monitoring signals, the alert delivery, the health endpoint.
> - [ ] The authorised exports.
>
> **Phase P6 — Hardening.**
> - [ ] The concurrency test at 20 workers and the dedupe re-run test.
> - [ ] The prompt-injection suite of at least 20 cases.
> - [ ] The PII encryption, the blind indexes, the retention purge.
> - [ ] The nightly backup and the successful restore onto a scratch database.
> - [ ] The evaluation thresholds enforced as a gate.
> - [ ] The security review.
>
> **Phase P7 — Pilot.**
> - [ ] Four weeks of live operation against the real allowance.
> - [ ] The agreement measurement and the signed review of every disagreement.
> - [ ] The catalogue corrections fed back from the disagreements.
> - [ ] The promotion evidence for each phase.
>
> **Before v1.1.**
> - [ ] The AI data-egress decision recorded.
> - [ ] The provider abstraction proven against all four providers, including the Null provider.

---

### 3.32 Appendix B — Development Principles (amended)

Replace Appendix B with:

> **Appendix B. Development Principles**
>
> 1. Never fabricate a portal API or claim an integration works before testing it. Anything about the portal that has not been observed is written as "must be verified in P0".
> 2. Never place credentials, connector logic or draft-template secrets in the frontend.
> 3. Never let a model output override a hard gate, a score component or a band.
> 4. Never exceed the weekly allowance. The allocation is one locked row, and the constraint is checked in the database, not in application logic alone.
> 5. Never present a shadow result, a derived figure or an estimate as a confirmed fact.
> 6. Never send a message to a buyer from the server. The system drafts; a human sends; the human's action is the evidence.
> 7. Keep every scoring decision explainable and auditable, with a policy version and a component breakdown attached.
> 8. Optimise for qualified and contacted leads, and for allowance utilisation. Do not optimise for lead volume, and do not claim conversion outcomes that no data supports.
> 9. Never let buyer contact details reach a third-party AI, a log line, an unauthorised export or the frontend bundle.
> 10. Run every phase shadow-first, and promote it only on the evidence in Section 2.4.1. A flag without evidence is not a promotion.
> 11. Prefer the deterministic path. Any model call must earn its place against a measured disagreement rate and must cost almost nothing.
> 12. Degrade, do not fail. When a source, a provider or the database is unavailable, the system keeps working on what it already knows and says plainly what it could not do.
> 13. Keep the operator's time as the scarce resource. Every screen must reduce decisions, not add them.
> 14. Record what happened, including the mistakes. An override, a skipped review and a failed job are all evidence.

---

## 4. Change control

From this point forward, PRD v1.0 as amended by this document — that is, PRD v1.1 — and this amendment document together are the contract for PharmaLead AI. Changes are not made by editing code and updating the docs afterwards; they are made here first.

Any change to PRD v1.1 requires all of the following:

1. A written reason that states what is wrong with the current text, what the change achieves, and what it costs in days.
2. An audit record in `audit_events` and a row in the decision log in `13_RISKS_OPEN_QUESTIONS.md`, carrying a new decision identifier and the date.
3. A version increment: `policy_version` for anything touching the weights, bands, gates, matching rules or country classification; a document version increment for everything else.
4. A re-run of the evaluation harness for any change that touches the scoring, the gates or the matcher, with the result attached to the change record.
5. A signed review by the operator for any change that touches the hard gates, the allowance arithmetic or the waste calculation.

The ten gates in Section 19.1 are not negotiable by agreement. If a change would breach one, the change does not proceed until the breach is fixed and the gate re-passes. The original PRD v1.0 is retained unchanged for audit purposes and is superseded; where the two documents disagree, PRD v1.1 wins.
