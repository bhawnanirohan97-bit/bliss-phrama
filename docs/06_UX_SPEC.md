# PharmaLead AI — UX Specification

| Item | Value |
|---|---|
| Document | 06_UX_SPEC |
| Version | 1.0 |
| Status | Authoritative for implementation |
| Last updated | 2026-10-22 |
| Applies to | React 19, Vite, TypeScript, Tailwind, shadcn/ui |
| Audience | One solo full-time developer, one primary operator, one admin |
| Screen count | 9 |

## 1. Design language

### 1.1 Principles

1. Professional light B2B SaaS. Dense enough for 75 leads per week, calm enough for all-day use.
2. Evidence over decoration. Every number on screen traces to a table and a timestamp.
3. One primary working screen. The Action Inbox is the product; every other screen supports it.
4. No fabricated production data. Empty states show zeros and a plain reason, never sample numbers.
5. Status is never conveyed by colour alone. Every chip carries a text label and an icon.
6. Human acts, system drafts. No control may send a message automatically.

### 1.2 Typography

| Token | Value | Use |
|---|---|---|
| `font-sans` | Inter, then system-ui, Segoe UI, sans-serif | All UI |
| `text-xs` | 12px / 16px | Metadata, table captions, helper text |
| `text-sm` | 14px / 20px | Body, forms, table cells |
| `text-base` | 16px / 24px | Lead inquiry text, dialog body |
| `text-lg` | 20px / 28px | Section titles |
| `text-xl` | 24px / 32px | Page titles |
| `text-3xl` | 32px / 40px | Dashboard KPI values |

Use `font-variant-numeric: tabular-nums` for every score, count, currency amount, duration, and date column. Limit prose line length to 72 characters. Do not use font weights below 400 or above 700.

### 1.3 Spacing and layout

| Token | Value |
|---|---|
| `space-1` | 4px |
| `space-2` | 8px |
| `space-3` | 12px |
| `space-4` | 16px |
| `space-6` | 24px |
| `space-8` | 32px |
| `space-12` | 48px |

- Left sidebar: 240px fixed, collapsible to 64px. Top bar: 64px.
- Content max width: 1440px, centred, with 24px gutters at 1280px and above.
- Card radius: 8px. Inputs and buttons: 6px. Pills: 999px.
- Border: 1px `#E2E8F0`. Card shadow: `0 1px 2px rgba(15, 23, 42, 0.06)`.
- Table row height: 56px comfortable, 44px compact. Default compact for the inbox.
- Motion: 150ms ease-out for hover and focus, 200ms for sheets and dialogs. Respect `prefers-reduced-motion` by disabling non-essential transitions.

### 1.4 Colour tokens

| Token | Hex | Meaning | Where used |
|---|---|---|---|
| `surface` | `#FFFFFF` | Card and sheet background | All panels |
| `canvas` | `#F8FAFC` | App background | Behind cards |
| `border` | `#E2E8F0` | Dividers and borders | Tables, cards |
| `text` | `#0F172A` | Primary text | Headings, values |
| `muted` | `#64748B` | Secondary text | Labels, metadata |
| `primary` | `#0F766E` | Product accent | Primary buttons, active nav, `act_now` |
| `status-new` | `#475569` | New, not actioned | Slate chip and left row edge |
| `status-action` | `#0F766E` | Act now, due action | Teal chip |
| `status-contacted` | `#15803D` | Contacted, and won when outcome capture exists | Green chip |
| `status-review` | `#B45309` | Review required, or follow-up due | Amber chip |
| `status-reject` | `#B91C1C` | Hard reject | Red chip |
| `status-muted` | `#94A3B8` | Skip, low, inactive | Grey chip |

Status meanings are fixed:

- Green means contacted. From M8 onward, green also means won. v1 has no won/lost label, so green is never shown for an untouched lead.
- Amber means review required or follow-up due.
- Red means hard reject. Red is never used for a low score.
- Slate means new and untouched.

Contrast: all text on the tokens above meets WCAG 2.1 AA at 4.5:1 for normal text and 3:1 for large text. Verify each pairing with a contrast checker during implementation. Never place white text on `status-muted`.

### 1.5 Component conventions

| Component | Convention |
|---|---|
| Band chip | Text plus dot: `Act now`, `Good`, `Review`, `Low`, `Skip`, `Hard reject` |
| Score | Number to 1 decimal in the inbox, 2 decimals in detail, plus a 40px bar |
| Score bar | Filled width equals `score`; colour follows the band token |
| Table | Sticky header, zebra-free, 1px row divider, sortable headers with `aria-sort` |
| Empty state | Title, one sentence, one action. No illustration required |
| Toast | Bottom right, 6-second auto-dismiss, `aria-live="polite"` |
| Alert banner | Top of content, persistent until dismissed or resolved |
| Dialog | Max width 560px, focus trapped, Escape closes unless destructive |
| Bottom sheet | Mobile only, 90vh max, drag handle, sticky footer |

### 1.6 Accessibility

1. Meet WCAG 2.1 AA.
2. Full keyboard operation. Visible focus ring: 2px `#0F766E` with 2px offset.
3. Every table has a `<caption>` or an `aria-label`, and every column header uses `scope="col"`.
4. Every form control has a visible label tied by `for` and `id`. Placeholder text is never the only label.
5. Every icon-only button has an `aria-label` and a tooltip. Contact icons also carry visible text in the detail view.
6. Announce async results with `aria-live`. Announce score changes and contact logging.
7. Do not rely on colour alone. Pair status colour with text and an icon.
8. Touch targets at least 44x44px on screens below 768px.
9. Keyboard shortcuts are documented in a `?` dialog and never override typing in inputs.
10. Tables support horizontal scroll with a visible scrollbar and a frozen first column on tablet.
11. Support 200% browser zoom without horizontal scrolling on 1280px viewports.
12. All charts include a table alternative behind a "View data" toggle.

### 1.7 No fabricated data rule

1. Every KPI, chart, count, and badge queries production tables on each load. No hard-coded numbers.
2. Zero rows render as `0` with an empty-state explanation, for example `No leads received for 2026-W43 yet.`
3. Demo data is available only when `DEMO_MODE=true`, uses a separate SQLite database, and renders a persistent amber banner: `Demo data. Not production.`
4. Screenshots and documentation must use demo mode. Never seed fake rows into the production database.
5. Percentages with a zero denominator render as `—`, not `0%`.
6. Currency always includes the code: `INR 1,920.00`. Never a bare number with a rupee symbol only.

## 2. Screen inventory

| Screen | Route | Primary user | Default refresh |
|---|---|---|---|
| Dashboard | `/` | Operator, admin | 60 seconds |
| Action Inbox | `/inbox` | Operator | 30 seconds |
| Lead Detail | `/inbox/:leadId` | Operator | 30 seconds |
| Catalogue | `/catalogue` | Admin | Manual |
| Countries | `/countries` | Admin | Manual |
| Allowance and Spend | `/allowance` | Admin, operator | 5 minutes |
| Analytics | `/analytics` | Admin | 5 minutes |
| Settings | `/settings` | Admin | Manual |
| Login | `/login` | All | Not applicable |

Navigation order in the sidebar: Dashboard, Inbox, Allowance, Catalogue, Countries, Analytics, Settings. Show an unread badge on Inbox equal to the count of `act_now` plus `good` leads that are new.

### 2.1 Permission matrix

| Screen | Admin | Operator | Viewer |
|---|---|---|---|
| Dashboard | Full | Full | Read-only, allowance ring hidden |
| Action Inbox | Full | Full | Read-only, no contact or bulk actions |
| Lead Detail | Full | Full | Read-only |
| Catalogue | Edit and import | Read | Read |
| Countries | Edit and import | Read | Read |
| Allowance and Spend | All weeks and months | Current week only | Hidden |
| Analytics | Full | Read-only | Hidden |
| Settings | Full | Hidden | Hidden |

An unauthenticated request to any route redirects to Login with a return path. A viewer who reaches a hidden route directly gets the screen-level permission-denied state, not a blank page.

## 3. Dashboard

### 3.1 Purpose

Give the operator a 10-second answer to four questions: how much of this week's 75 is left, how fast are we replying, what needs action today, and is anything broken.

### 3.2 Data shown

| Panel | Data | Source tables | Definition |
|---|---|---|---|
| Allowance ring | Received, actioned, untouched, waste in INR | `weekly_allotments`, `leads` | Received and actioned counts for the current `week_key` |
| Speed panel | Median arrival-to-contact minutes, p90 minutes, SLA breach count | `leads`, `lead_activities` | Arrival is `received_at`; contact is the first outbound activity; median over contacted leads this week |
| Due today | Count and top 5 by `due_at` | `leads`, `lead_scores` | Leads in `act_now`, `good`, `review` that are not actioned and whose `due_at` falls before end of today |
| Funnel | Received, qualified, actioned, responded | `leads`, `lead_activities` | Qualified means band `act_now` or `good`; responded means a manual `replied` activity |
| Top products | Top 5 matched SKUs by lead count this week | `lead_scores`, `catalogue_skus` | Count distinct leads per chosen SKU |
| Top destinations | Top 5 countries by lead count this week | `leads`, `countries` | Count distinct leads per resolved country |
| Alerts | Active operational alerts | `alerts` | Unread or unresolved alerts, newest first |

The allowance ring is a 75-tick ring. Each tick represents one lead. Green ticks are actioned, amber ticks are received but not actioned, grey ticks are unreceived. The centre shows `actioned / 75`. The legend shows all four numbers, with waste as `INR X` and a tooltip explaining `(75 - actioned) x unit cost`.

### 3.3 Primary actions

- Click a ring segment to open Allowance and Spend filtered to the current week.
- Click a due-today row to open Lead Detail.
- Click `Open inbox` to load the inbox with the default filter.
- Click an alert to open its target with context.
- Click `Dismiss` on an alert that supports dismissal.

### 3.4 States

| State | Behaviour |
|---|---|
| Empty | Ring renders 75 grey ticks, centre `0 / 75`, copy `No leads received for 2026-W43 yet.` Speed panel shows `—`. Funnel shows zeros with `No activity yet.` |
| Loading | Skeleton for the ring, four panel blocks, and the funnel. Preserve layout height to avoid shift. |
| Error | Each panel fails independently with `Could not load this panel. Retry.` Do not blank the whole page. |
| Permission denied | Viewers see the dashboard read-only without action buttons. If the role lacks allowance access, hide the ring and show `Allowance is visible to operators and admins.` |

### 3.5 Responsive

- 1280px and above: 12-column grid. Ring and speed panel span 3 columns each, due-today spans 6. Second row: funnel 6, top products 3, top destinations 3. Alerts full width.
- 768px to 1279px: two columns. Ring and speed side by side, due-today full width, funnel full width, top lists side by side.
- Below 768px: single column in the order ring, speed, due today, alerts, funnel, top products, top destinations. The ring shrinks to 200px.

## 4. Action Inbox

### 4.1 Purpose

The primary working screen. Rank the week's leads, let the operator contact the right buyers fast, and make it impossible to lose track of a lead.

### 4.2 Default view

- Week: current `week_key`.
- Bands: `act_now`, `good`, `review`.
- Status: `new` and `in_progress`.
- Sort: band order, then score descending, then age ascending.
- Page size: 50.

### 4.3 Desktop table columns

| Order | Column | Content | Sortable |
|---|---|---|---|
| 1 | Select | Checkbox with row label | no |
| 2 | Rank | Position in the current sort | no |
| 3 | Band | Chip with text and dot | yes |
| 4 | Company and buyer | Company name on line 1, buyer name on line 2 | yes |
| 5 | Country | ISO2 plus country name; `Unresolved` in amber when null | yes |
| 6 | Product match | Brand, generic, strength, form; method chip `S1` to `S5` or `Manual` | yes |
| 7 | Score | Number to 1 decimal plus a 40px bar | yes |
| 8 | Quantity | Normalised value and unit; `Missing` in amber | yes |
| 9 | Age | Time since `received_at`, for example `2h 14m` | yes |
| 10 | Contact | WhatsApp, email, phone icons with accessible labels; dimmed when unavailable | no |
| 11 | Owner | Assigned user initials | yes |
| 12 | Actions | Open, Contact, Discard | no |

Show `Ambiguous` as an amber inline chip next to the product match when `is_ambiguous = true`. Show `Duplicate` as a grey chip when the lead is a near-duplicate.

### 4.4 Filters

| Filter | Control | Default |
|---|---|---|
| Band | Multi-select chips | act_now, good, review |
| Status | Multi-select: new, in progress, contacted, follow-up due, discarded | new, in progress |
| Country | Searchable multi-select | all |
| Product | Searchable select bound to SKU | all |
| Buyer type | Multi-select | all |
| Owner | Select | all |
| Week | Select of available `week_key` values | current |
| Contactability | Toggle: has phone, has email | off |
| Flags | Toggle: ambiguous, country conflict, duplicate, AI unavailable | off |
| Date range | Arrival date | current week |

Persist filters in the URL so the view is shareable. Provide three saved views: `My queue`, `Unassigned`, `Needs review`.

### 4.5 Bulk actions

| Action | Behaviour |
|---|---|
| Assign owner | Set owner for all selected; audited per lead |
| Mark contacted | Open one confirmation dialog showing the count and channel; log one activity per lead on confirm |
| Discard | Require a reason from a fixed list; hard-rejected leads cannot be discarded |
| Add tag | Apply a free-text tag up to 40 characters |
| Export CSV | Export the selected rows with the visible columns plus score breakdown summary |

Bulk actions never send messages. `Mark contacted` records that a human made contact outside the system.

### 4.6 Mobile card layout

Below 768px, replace the table with cards, one per lead. Each card shows:

- Left 4px band stripe.
- Line 1: company name, band chip on the right.
- Line 2: country and product, truncated to one line each.
- Line 3: score, quantity, age.
- Footer: WhatsApp, Email, Call buttons, and `Open`.

Tapping the card opens the bottom sheet. Scroll position is preserved on close. Implement infinite scroll in pages of 25 with a `Load more` fallback.

### 4.7 Bottom sheet lead detail

- Opens at 90vh, drag handle, backdrop at 40% black.
- Header: company, band, score, close button.
- Content: original inquiry, parsed fields summary, match summary, draft tabs.
- Sticky footer: `WhatsApp`, `Email`, `Call`, `Mark contacted`, `Open full detail`.
- Swipe down or Escape closes. Focus returns to the originating card.
- The full detail route remains available for deep work.

### 4.8 Row states

| State | Visual | Behaviour |
|---|---|---|
| New | Slate left edge, `New` chip | Default queue |
| Act now | Teal left edge, `Act now` chip, subtle teal tint on hover | Pinned to top of band order |
| Review | Amber left edge, `Review` chip | Cannot be bulk-marked contacted without opening |
| Due | Amber clock icon beside age, `Due` chip | Sorted by `due_at` within band when the Due filter is on |
| Contacted | Green left edge, `Contacted` chip, muted row | Remains visible until closed |
| Hard reject | Red left edge, `Hard reject` chip | Hidden unless the Hard reject filter is on; no contact buttons |
| Duplicate | Grey chip `Duplicate`, 60% opacity | Hidden by default; visible with the Duplicate flag filter |

Never show a red row for `low` or `skip`. Those use grey and appear only when the band filter includes them.

### 4.9 States

| State | Behaviour |
|---|---|
| Empty | `No leads match these filters.` with `Clear filters`. If the week has no leads at all: `No leads received for 2026-W43 yet.` |
| Loading | 8 skeleton rows, header remains interactive |
| Error | Inline banner `Could not load leads. Retry.` with a retry button; filters remain usable |
| Permission denied | Viewers get a read-only table without checkboxes, bulk actions, or contact buttons |

### 4.10 Keyboard

| Key | Action |
|---|---|
| `j` / `k` | Move selection down / up |
| `o` or `Enter` | Open focused lead |
| `w` | Open WhatsApp for focused lead |
| `e` | Open email for focused lead |
| `c` | Open phone link for focused lead |
| `x` | Toggle row selection |
| `/` | Focus search |
| `?` | Show shortcut help |
| `Escape` | Close sheet or dialog |

## 5. Lead Detail

### 5.1 Purpose

One place to verify the engine's reasoning, correct it, contact the buyer, and leave an audit trail.

### 5.2 Layout

Desktop: two columns. Main column 8/12 with inquiry, parsed fields, match panel, score breakdown, drafts, and timeline. Side column 4/12 with country status, risk flags, missing information, follow-up scheduler, and audit trail. Below 1024px, stack the side column above the timeline.

### 5.3 Header

Sticky header with:

- Company name and buyer name.
- Band chip and total score to 2 decimals.
- Week key and arrival time.
- Primary actions: `WhatsApp`, `Email`, `Call`, `Copy`, `Mark contacted`.
- Overflow menu: `Reassign`, `Snooze`, `Discard`, `Rescore`, `Copy link`.

Disable contact actions with an inline reason when the lead is hard rejected, has no usable contact channel, or is a duplicate.

### 5.4 Original inquiry

- Show `raw_text` verbatim, preserving line breaks, in a bordered block.
- Show `received_at`, source adapter, and `external_lead_id`.
- Provide `Copy text`.
- Never render HTML from the inquiry. Escape everything.

### 5.5 Editable parsed fields

Each field shows the value, a method chip (`Rule`, `Model`, `Manual`), and an explanation popover with the matched span or dictionary entry.

| Field | Control | Validation |
|---|---|---|
| Product | Searchable SKU select plus free text | Required for contact; free text allowed |
| Strength | Text | Optional; pattern `number + unit` |
| Dosage form | Select | Optional |
| Quantity | Number | Greater than 0 |
| Unit | Select from canonical units | Required when quantity is present |
| Destination country | Searchable country select | Optional; drives gates |
| Buyer type | Select | Required; `personal` and `job_vendor` warn before save |
| Intent | Select | Required |
| Phone | Tel input | 8 to 15 digits; E.164 stored |
| Email | Email input | Single address |

Saving a correction creates a `field_corrections` row and, for product, a `human_override`. Show a toast: `Saved. This lead was rescored with policy score-1.3.0.` If a save would fire a hard gate, show a confirm dialog naming the gate and requiring a reason.

### 5.6 Product match panel

- Chosen SKU card: brand, generic, strength, form, pack, export eligibility, indicative price if permitted.
- Match method: stage chip, `match_score` to 4 decimals, and source (`deterministic`, `fused`, `model_only`, `manual`).
- Candidate list: up to 12 rows with radio selection, score, stage, and restriction count. Highlight the top two and show the gap when `is_ambiguous`.
- `Change SKU` opens a catalogue search. Selecting a SKU opens the override dialog with a required reason.
- Show `Model-only match capped at 0.85` when `model_only = true`.

### 5.7 Score breakdown

Render five horizontal bars in fixed order: Product 35, Commercial 20, Destination 20, Buyer 15, Completeness 10. Each bar shows awarded, maximum, and a plain-language reason from the engine. Show a total line and the policy version. Include a `Why this band` disclosure listing the band threshold and the exact points needed for the next band.

If the lead is hard rejected, replace the bars with a red gate panel listing every fired gate, the primary gate first, and the evidence. Do not show a total.

### 5.8 Risk flags and missing information

| Flag | Copy | Severity |
|---|---|---|
| Ambiguous match | `Top two candidates are within 0.05. Confirm the SKU before sending.` | amber |
| Country conflict | `Parser and model disagree on the destination. Review required.` | amber |
| Missing quantity | `No quantity found. Ask the buyer before quoting.` | amber |
| Missing contact channel | `No usable phone or email.` | red |
| Domestic lead | `Destination is India. Export suitability is zero.` | grey |
| Duplicate | `Near-duplicate of lead 2026-W43-018.` | grey |
| AI unavailable | `Model output unavailable. Showing deterministic result only.` | grey |
| Allowance exhausted | `This lead is beyond the 75-lead weekly allowance.` | red |

Missing information lists only fields that matter for the next action: product, strength, quantity, destination, and email. Each row has a `Ask in draft` toggle that feeds the clarifying-question template.

### 5.9 Country status

Show country name, ISO2, status (`allowed`, `review`, `blocked`), the reason text, and the rule source. Link to Countries. For blocked countries, show the hard-gate explanation and hide draft generation.

### 5.10 Drafts

- Tabs: `WhatsApp`, `Email`.
- Each tab shows an editable textarea, character count against the limit, template name, and guard status.
- Actions: `Regenerate`, `Copy`, `Open WhatsApp`, `Open email`, `Restore previous`.
- Show a guard banner when a draft was blocked: `Draft blocked: price not in catalogue.`
- Never auto-populate a recipient beyond the lead's normalised phone or email.
- Preserve draft versions; `Restore previous` loads the prior version into the editor without deleting history.

### 5.11 Activity timeline

Chronological list of ingested, scored, rescored, viewed, contact opened, contact logged, note added, follow-up scheduled, field edited, SKU overridden, and discarded events. Each entry shows actor, timestamp in Asia/Kolkata, and a short description. Newest first. Notes support plain text up to 1,000 characters.

### 5.12 Follow-up scheduler

- Date and time picker, default tomorrow 10:00 Asia/Kolkata.
- Optional note up to 200 characters.
- Reminder channels: in-app and email.
- Show `Due` in the inbox when the follow-up time passes.
- Allow one active follow-up per lead; rescheduling replaces it and records an audit entry.

### 5.13 Audit trail

A collapsible panel listing immutable events with before and after values for field edits, score versions, policy versions, overrides, contact logs, and permission changes. Read-only for all roles. Exportable as CSV by admins.

### 5.14 States

| State | Behaviour |
|---|---|
| Empty | Not applicable; a missing id shows a 404 panel |
| Loading | Skeleton for header, inquiry, bars, and drafts |
| Error | `Could not load this lead. Retry.` Keep the back link |
| Permission denied | Viewers see all read-only panels; contact and edit actions are hidden |
| Not found | `Lead not found or outside your access.` with a link to the inbox |

## 6. Catalogue

### 6.1 Purpose

Maintain the 1000+ SKU catalogue that the matcher depends on, without a spreadsheet round trip for small edits.

### 6.2 Data shown

Table columns: SKU, brand, generic, strength, form, pack size, indicative price INR, export eligible, restricted countries count, synonyms count, last updated. Default sort by brand then generic. Page size 50.

### 6.3 Primary actions

- Search by SKU, brand, or generic with 300ms debounce.
- Filter by export eligibility, dosage form, and restricted country.
- Inline edit brand, generic, strength, form, pack size, indicative price, and export eligibility.
- Open a SKU drawer for synonyms and restricted countries.
- Import CSV or XLSX.
- Export the current filtered view as CSV.

### 6.4 Import flow

1. Upload a CSV or XLSX up to 10 MB. Reject other formats.
2. Show a mapping preview: source column to canonical field, with sample values and a required/optional marker.
3. Validate every row. Show counts for valid, warning, and error rows.
4. Show an error report table with row number, column, value, and reason. Offer `Download errors as CSV`.
5. Commit only valid rows when the operator confirms. Warnings require acknowledgement.
6. Show a summary: inserted, updated, skipped, failed, duration.
7. Write an import audit record with file name, checksum, row counts, and actor.

Validation rules: SKU is required and unique; brand and generic are required; strength must match `number + unit`; price must be zero or positive; export eligibility must be boolean; restricted countries must be valid ISO2 codes. An unknown country blocks that row, not the whole file.

### 6.5 Synonym sub-table

Inside the SKU drawer, list synonyms with source (`seed`, `import`, `correction_promotion`), status (`active`, `pending`, `rejected`), and created date. Admins can add, deactivate, and approve pending synonyms. Approving a synonym warns that it bumps the dictionary version and triggers the M2 evaluation.

### 6.6 Export eligibility and restrictions

- Toggle export eligibility with a confirmation that names the SKU and warns that ineligible SKUs hard-reject matching leads.
- Restricted country picker: multi-select with search, chips, and an `All countries` shortcut. Store ISO2 codes. Warn when a restriction overlaps an allowed country.

### 6.7 States

| State | Behaviour |
|---|---|
| Empty | `No SKUs yet. Import a catalogue to start.` with `Import` as the primary action |
| Loading | 10 skeleton rows; keep the search box usable |
| Error | `Could not load the catalogue. Retry.` Import stays disabled until the list loads |
| Permission denied | Viewers see search and export only; edit, import, and toggle controls are hidden |

### 6.8 Responsive

Below 1024px, hide low-priority columns (synonyms, last updated) and keep SKU, brand, generic, strength, form, price, eligibility. Below 768px, switch to cards with search and a SKU drawer. Import remains desktop-only with a message on mobile.

## 7. Countries

### 7.1 Purpose

Control destination eligibility with a short, auditable list and enough context to set rules sensibly.

### 7.2 Data shown

Table columns: country name, ISO2, status (`allowed`, `review`, `blocked`), leads last 8 weeks, actioned last 8 weeks, notes, updated by, updated at. Sort by status then country name. The lead-volume columns are hints only; they never change a rule automatically.

### 7.3 Primary actions

- Change status inline with a confirmation.
- Add a country not present in the dictionary.
- Bulk import CSV with columns `iso2,status,notes`.
- Export CSV.
- Filter by status and search by name or ISO2.

### 7.4 States

| State | Behaviour |
|---|---|
| Empty | `No country rules yet. All destinations are unresolved and route to review.` with `Import` and `Add country` |
| Loading | Skeleton table |
| Error | `Could not load country rules. Retry.` |
| Permission denied | Viewers see the table read-only |

### 7.5 Responsive

Below 768px, show cards with country, ISO2, status, and 8-week lead count. Bulk import is desktop-only.

## 8. Allowance and Spend

### 8.1 Purpose

Make the weekly quota and its waste visible in rupees without pretending there is a per-lead price.

### 8.2 Data shown

Weekly table columns:

| Column | Definition |
|---|---|
| Week | `week_key` |
| Allotted | 75 |
| Received | Accepted leads |
| Actioned | Leads with at least one logged contact |
| Untouched | `received - actioned` |
| Unreceived | `max(0, 75 - received)` |
| Unit cost | `weekly_fee_inr / 75`, 2 decimals |
| Waste | `max(0, 75 - actioned) x unit_cost`, 2 decimals |

Monthly rollup: total allotted (weeks x 75), total actioned, total waste, mean unit cost, and action rate. Show the weekly fee and its effective date in the header.

### 8.3 Plain-language waste statement

Render one sentence per selected week, generated from real values:

`In week 2026-W43, 12 of 75 contracted leads were not actioned. At INR 160.00 per lead, INR 1,920.00 of the weekly fee produced no contact.`

When all 75 are actioned: `In week 2026-W43, all 75 contracted leads were actioned. No allowance was wasted.`

When fewer than 75 arrived: `In week 2026-W43, the portal delivered 70 of 75 leads and 65 were actioned. INR 1,600.00 of the weekly fee produced no contact, including 5 leads that were not delivered.`

Never call waste a loss, penalty, or fine. It is the cost of unused allowance.

### 8.4 Primary actions

- Switch week and month.
- Export the table as CSV.
- Open a week to filter the inbox to that week.
- Edit the weekly fee in Settings; the change applies from the selected week forward and never rewrites history.

### 8.5 States

| State | Behaviour |
|---|---|
| Empty | `No allowance records for this month yet.` Show zeros in the header |
| Loading | Skeleton rows and header |
| Error | `Could not load allowance data. Retry.` |
| Permission denied | Operators see the current week only; viewers see nothing and get `Allowance is visible to operators and admins.` |

### 8.6 Responsive

Below 768px, show one week card at a time with a week selector. The waste statement stays directly under the card. The monthly rollup collapses into a disclosure.

## 9. Analytics

### 9.1 Purpose

Show operational truth about throughput, matching, and AI cost. Do not present conversion forecasts or promises.

### 9.2 Sections

| Section | Chart | Table alternative |
|---|---|---|
| Funnel | Received, qualified, actioned, responded | Counts and stage-to-stage movement within the week |
| By product | Horizontal bar, top 10 SKUs by lead count | SKU, count, share |
| By destination | Horizontal bar, top 10 countries | Country, count, share |
| By buyer type | Donut with 6 or fewer slices, grouped remainder | Buyer type, count, share |
| Response-time histogram | Buckets: under 15m, 15 to 30m, 30 to 60m, 1 to 4h, 4 to 24h, over 24h | Bucket, count, share |
| Match-accuracy trend | Weekly line of operator correction rate and ambiguity rate | Week, correction rate, ambiguity rate |
| AI usage and cost | Calls, tokens, USD, cache hit rate, p50 latency | Provider, model, calls, USD, cache hit rate |

Every chart has a date range defaulting to the last 8 weeks, a week selector, and an export button. Never show a single data point as a trend line.

### 9.3 States

| State | Behaviour |
|---|---|
| Empty | `Not enough data for this range.` Charts render axes with no series and a table of zeros |
| Loading | Chart skeletons at fixed height |
| Error | Per-section error with retry; other sections continue |
| Permission denied | Viewers see `Analytics is available to operators and admins.` Operators see read-only charts without export. |

### 9.4 Responsive

Charts stack to one per row below 1024px. Tables become horizontally scrollable with the first column frozen. The table alternative is always available behind the same toggle.

## 10. Settings

### 10.1 Purpose

Configure policy, people, AI budget, ingestion, backups, and audit access from one auditable place.

### 10.2 Tabs

| Tab | Fields and actions |
|---|---|
| Policy | Band thresholds (85, 70, 55, 40), SLA minutes per band (30, 240, 1440), weekly fee INR, quote indicative price toggle, clarifying-question threshold 0.60 of 1.00 (6.0 of 10 completeness points) |
| Users and roles | Invite user, set role (`admin`, `operator`, `viewer`), deactivate, reset password |
| AI provider | Provider select, API key (masked, write-only), model id, prompt version display, daily request cap 200, monthly USD cap 5.00, per-lead token cap 2000, circuit breaker state, `Reset circuit` |
| Ingestion | Pause switch, poll interval, CSV watch folder path, last batch status, `Run ingestion now` |
| Backup | Last successful backup, size, destination, schedule, `Run backup now`, last restore test |
| Audit log | Filters by actor, action, entity, and date; CSV export; read-only |

### 10.3 Change rules

1. Policy changes require a confirmation dialog listing the old and new values.
2. A policy change triggers a rescore preview: count of leads affected and band movement summary. Confirm to apply.
3. Lowering the monthly AI cap below current spend does not cancel in-flight calls; it blocks new calls.
4. The ingestion pause switch requires typing `PAUSE` to confirm. Resume requires no typing.
5. API keys are never displayed after save. Show only the last 4 characters.
6. Every change writes an audit entry with actor, timestamp, old value, and new value.

### 10.4 States

| State | Behaviour |
|---|---|
| Empty | Not applicable; every tab has defaults |
| Loading | Skeleton form fields; disable save until loaded |
| Error | `Could not load settings. Retry.` Save stays disabled |
| Permission denied | All tabs admin-only. Others see `Settings is available to admins.` |
| Validation error | Inline field error, summary at the top, focus moves to the first invalid field |

### 10.5 Responsive

Tabs become a horizontal scroll list below 768px. Forms are single column. The audit log table becomes cards with actor, action, entity, and time.

## 11. Login

### 11.1 Purpose

Authenticate local users on the office PC. No public sign-up.

### 11.2 Data shown and actions

- Email and password fields.
- `Remember me` for 30 days.
- `Sign in` primary action.
- `Forgot password` sends a reset link through the local mail configuration.
- Show the app name and environment badge (`Production` or `Demo`).

### 11.3 States

| State | Behaviour |
|---|---|
| Empty | Standard form |
| Loading | Button shows `Signing in` with a spinner; fields disabled |
| Error | `Email or password is incorrect.` Do not reveal which field failed. After 5 failures, lock for 15 minutes with `Too many attempts. Try again in 15 minutes.` |
| Permission denied | Not applicable; deactivated accounts get `This account is inactive. Contact an admin.` |

### 11.4 Responsive

Centred card, max width 400px, 24px side padding on mobile. Inputs use `autocomplete="username"` and `autocomplete="current-password"`.

## 12. Interaction specifications

### 12.1 WhatsApp deep link

1. Normalise the phone to E.164 digits without `+`, spaces, or dashes.
2. Build `https://wa.me/<digits>?text=<encodeURIComponent(draft)>`.
3. Open in a new tab with `noopener,noreferrer`.
4. If the draft exceeds 4,000 encoded characters, show `Message is too long for WhatsApp. Shorten it or copy instead.` and do not open.
5. If the phone is missing or invalid, disable the action with the reason `No valid phone number`.
6. After opening, show the send-log dialog. Never log automatically.
7. If the tab is blocked, show `Could not open WhatsApp. Copy the message and number instead.` with both copy buttons.

### 12.2 Email flow

1. Build `mailto:<email>?subject=<encodeURIComponent(subject)>&body=<encodeURIComponent(body)>`.
2. Keep the total URL under 2,000 characters. If longer, truncate the body at a sentence boundary, show `Body shortened for your mail app`, and keep the full text available to copy.
3. Open the operating system's default mail client.
4. If the email is missing or invalid, disable the action with `No valid email address`.
5. After opening, show the send-log dialog.

### 12.3 Phone flow

1. Build `tel:<E164>`.
2. Open the default handler.
3. After returning, show the send-log dialog with channel preset to `phone`.
4. If no handler exists, show the number with a copy button.

### 12.4 Copy fallback

1. Copy the draft body to the clipboard. If that fails, open a dialog with a selectable textarea and `Select all`.
2. Show `Copied message` for 3 seconds.
3. Offer `Copy number` separately for WhatsApp fallback.
4. After a successful copy, show the send-log dialog once. Do not show it again for the same draft version unless the operator clicks `Log contact`.

### 12.5 Send-log confirmation dialog

Title: `Log this contact?`

Body: `We opened WhatsApp for <company>. Confirm only after you send the message. Logging records the contact time and removes this lead from the due queue.`

Fields:

- Channel: `WhatsApp`, `Email`, `Phone`, pre-selected from the action.
- Outcome: `Sent`, `Not sent`, `Cancel`.
- Optional note up to 200 characters.

Buttons: `Sent` (primary), `Not sent` (secondary), `Cancel` (ghost).

Behaviour:

- `Sent` writes a `lead_activities` row with channel, draft version, actor, and timestamp; sets the lead to `contacted`; stops SLA timers; closes the dialog; shows `Contact logged.`
- `Not sent` writes no contact row; keeps the lead in its current state; closes the dialog.
- `Cancel` closes with no write.
- Closing by Escape or backdrop behaves like `Cancel`.
- Never infer "sent" from the app switch or the deep link opening.

### 12.6 Regenerate draft

1. Confirm `Regenerate replaces the current draft. The previous version stays in history.`
2. Generate a new version with the same template and inputs unless the operator changes the template.
3. Show the previous version timestamp above the editor.
4. If the fact-check guard blocks the new draft, keep the old draft visible and show the guard reason.

### 12.7 Discard

Require a reason from `wrong_product`, `wrong_country`, `personal`, `spam`, `duplicate`, `other`. `other` requires a note of at least 10 characters. Discard is reversible for 24 hours by an admin.

## 13. Notifications catalogue

Channels: in-app bell and email. In-app notifications persist until read. Email uses the configured local mail transport. All timestamps are Asia/Kolkata.

### 13.1 Instant alerts

| Alert | Trigger | Channel | Recipient | Copy |
|---|---|---|---|---|
| New week batch received | A batch commits with at least 1 lead | In-app plus email | Admin, operator | `2026-W43 batch received: 75 leads. 52 act now or good. Open the inbox.` |
| Partial delivery | A batch commits with fewer than 75 leads | In-app plus email | Admin | `2026-W43 delivered 70 of 75 leads. 5 allowance units are unreceived.` |
| Ingestion failure | A pull fails after the final retry | In-app plus email | Admin | `Ingestion failed for 2026-W43 after 5 attempts. Last error: <code>. Leads are not updated.` |
| Checksum conflict | The same week arrives with a different manifest checksum | In-app plus email | Admin | `2026-W43 arrived again with a different checksum. Existing 75 leads, new file 78. Choose which count is right before anything is merged.` |
| Credential expiry | Portal credential expires in 7, 3, or 1 day | In-app plus email | Admin | `IndiaMART credential expires in 3 days. Renew it to keep ingestion running.` |
| SLA breach | A lead passes its band SLA without a logged contact | In-app, batched every 15 minutes | Operator, admin | `4 leads passed their response target. Oldest: <company>, 38 minutes.` |
| Allowance risk | Thursday 14:00 IST and actioned is below 60% of received | In-app plus email | Admin, operator | `42 of 75 leads actioned with 2 days left. 33 untouched, INR 5,280.00 at risk.` |
| Hard-reject spike | More than 20% of a batch is hard rejected | In-app plus email | Admin | `18 of 75 leads hard rejected in 2026-W43. Top gate: blocked_destination (11). Review the country rules.` |
| AI budget 80% | Monthly AI spend reaches 80% of cap | In-app plus email | Admin | `AI spend is USD 4.00 of USD 5.00. New model calls stop at the cap; deterministic scoring continues.` |
| AI budget 100% | Monthly AI spend reaches the cap | In-app plus email | Admin, operator | `AI budget reached. Model calls are stopped until next month or until an admin raises the cap. Leads still score deterministically.` |
| Provider degraded | 3 consecutive provider failures or 10 minutes of downtime | In-app plus email | Admin | `AI provider unavailable for 12 minutes. Leads are scoring with deterministic rules only.` |
| Backup failure | Nightly backup fails | In-app plus email | Admin | `Backup failed at 02:15. Last successful backup: 2026-10-21 02:15.` |
| New hard-gate rule conflict | A country rule change would block leads already in the inbox | In-app | Admin | `This change would hard reject 7 leads in the current week. Confirm to apply.` |

### 13.2 Daily digest

Send at 09:00 IST every day to all active users. Subject: `PharmaLead daily: 12 due, 33 untouched, 4 contacted`.

Body sections:

1. `Due today`: count of `act_now` and `good` leads not actioned, with the top 3 companies.
2. `Week so far`: received, actioned, untouched, waste in INR.
3. `Yesterday`: contacts logged, replies logged, new leads received.
4. `Needs review`: count of leads in `review` with ambiguity or country conflict.
5. `AI cost`: calls and USD month to date.
6. `System`: last ingestion status and next scheduled pull.

Digest copy example:

`12 leads are due today. 33 of 75 leads are untouched this week, equal to INR 5,280.00. 4 contacts were logged yesterday. 6 leads need review. AI spend is USD 1.24 this month. Last ingestion succeeded at 08:45.`

Do not include buyer phone numbers or emails in email notifications. Link back to the app instead.

### 13.3 Notification rules

1. Never send an email for every new lead. One batch notification per batch.
2. Group SLA breach alerts into one message per 15 minutes.
3. Suppress duplicate alerts with the same key for 60 minutes.
4. Respect each user's channel preference in Settings, except security and backup alerts which always email admins.
5. Include a deep link to the relevant filtered view in every in-app notification.
6. Never include inquiry text longer than 120 characters in an email.
7. Mark an alert resolved automatically when its condition clears; do not require manual dismissal.

## 14. Cross-screen consistency

| Data | Must match everywhere |
|---|---|
| Band label and colour | Engine band table; no screen invents a band |
| Score | 1 decimal in lists, 2 decimals in detail and exports |
| Allowance counts | `weekly_allotments`; never recomputed differently per screen |
| Waste | Same formula and rounding as 04_AI_ENGINE_SPEC 2.3 |
| SLA | Settings values; 30, 240, and 1440 minutes by default |
| Week key | ISO week, Asia/Kolkata, displayed as `2026-W43` |
| Timestamps | Stored UTC, displayed Asia/Kolkata with `IST` in tooltips |
| Currency | `INR` code always shown, 2 decimals, Western digit grouping everywhere, for example `INR 500,000.00` |

Choose Western digit grouping (`1,920.00`) for all tables and exports. Use the same grouping in prose. Do not mix `1,920` and `1,920.00` on the same screen.
