# PharmaLead AI — Connector Specification

| Item | Value |
|---|---|
| Document | 07_CONNECTOR_SPEC |
| Version | 1.0 |
| Status | Authoritative for implementation |
| Last updated | 2026-10-22 |
| Applies to | Laravel 11 ingestion layer, MySQL 8 production, SQLite dev/test |
| External system | IndiaMART seller dashboard and whatever machine-readable access the reseller contract permits |
| Rule | Every portal capability in this document is marked "must be verified in P0". Do not code against an assumed portal behaviour |

## 1. Purpose

Deliver exactly the contracted 75 pre-paid buyer leads per week into one canonical store, exactly once, with enough provenance to answer "where did this lead come from and why is the count different". The connector never scores, never sends, and never purchases.

The connector must work on day one even if no API exists. Machine-readable ingestion is preferred, but manual entry is a first-class adapter, not a temporary hack.

## 2. Shared definitions

| Term | Definition |
|---|---|
| Source | One configured lead origin: the portal API, a CSV export, or manual entry. |
| Adapter | An implementation of the `LeadSource` interface for one source. |
| Batch | All leads pulled for one source and one `week_key`. |
| `week_key` | ISO week, for example `2026-W43`, evaluated in Asia/Kolkata. Week starts Monday 00:00 IST. |
| Manifest checksum | SHA-256 over the canonicalised batch file or payload, defined in 6.2. |
| Accepted lead | A committed lead within the first 75 positions of the week. |
| Overflow lead | A committed lead beyond position 75. Stored, gated as `allowance_exhausted`, not in the inbox. |
| Quarantined row | A row that failed row-level validation. Stored with its errors, not scored, not counted as accepted. |
| Replay | A pull for a week whose manifest checksum matches the stored batch. |

## 3. LeadSource interface

Every adapter implements the same interface. The ingestion service depends only on this interface.

| Method | Signature | Returns | Side effects |
|---|---|---|---|
| `key` | `key(): string` | Stable adapter key: `imart_api`, `imart_csv`, `manual` | None |
| `pull` | `pull(WeekKey $week): PullResult` | Raw records, manifest checksum, fetched timestamp, adapter metadata | None; never writes to the database |
| `fetchDetails` | `fetchDetails(string $externalLeadId): LeadPayload` | One canonical payload | None; must be safe to call repeatedly |
| `health` | `health(): SourceHealth` | State, last success, last error, credential expiry, next scheduled check | None |

### 3.1 Contract every implementation must honour

1. **Read-only.** `pull` and `fetchDetails` never insert, update, or delete application data.
2. **Idempotent.** Calling `pull` twice for the same week returns the same manifest checksum when the source content is unchanged.
3. **Deterministic ordering.** Sort records by `posted_at` ascending, then `external_lead_id` ascending. If `posted_at` is missing, use `fetched_at`.
4. **Bounded.** Maximum 500 records per pull. Reject a larger response with `batch_too_large`.
5. **Timed.** Connect timeout 5 seconds, total timeout 30 seconds. Abort and raise `SourceTimeout`.
6. **Encoded.** API responses must be UTF-8. CSV files may be UTF-8, UTF-8 with BOM, or Windows-1252; convert Windows-1252 to UTF-8 and record `encoding_converted = true`. Reject any other encoding.
7. **Secret-safe.** Never log API keys, cookies, full phone numbers, or full email addresses. Log the last 4 characters of a key and a hashed lead id.
8. **Typed failures.** Raise one of `SourceUnavailable`, `SourceAuthExpired`, `SourceRateLimited`, `SourceSchemaChanged`, `SourceTimeout`, `SourceInvalidPayload`, or `SourceConfigError`. Do not throw generic exceptions.
9. **Rate-aware.** Respect the portal's advertised limits and any `Retry-After` header. When no limit is known, use the conservative defaults in 7.3.
10. **No inference.** Do not invent a field the source does not provide. Leave it null and let the normaliser derive what it can.
11. **Provenance.** Return the raw payload for every record, byte-for-byte where possible, for audit and replay.
12. **Week honesty.** Return records for the requested week only. If the source cannot filter by week, return the full set and let the ingestion service filter by `posted_at`, recording `week_filter = client_side`.

## 4. Adapters

| Adapter | Trigger | Source of truth | Manual effort per week | Implementation cost |
|---|---|---|---|---|
| API adapter (`imart_api`) | Scheduled poll: Monday 00:05 IST, then every 15 minutes from 08:00 to 20:00 IST until the batch reaches 75; hourly health check otherwise | Portal API response | 0 minutes when healthy; 10 minutes to investigate an alert | 6 days after P0 |
| CSV export adapter (`imart_csv`) | Watch folder every 60 seconds, plus a manual upload button | The exported file placed in the ingest folder | 5 minutes: download one export and save it to the folder | 3 days |
| Manual entry adapter (`manual`) | Operator opens `Ingestion > Manual entry` and pastes or types | The operator's transcription of the seller dashboard | 20 to 30 minutes for 75 leads | 2 days |

Total implementation after P0: 11 days. The P0 spike itself is 2 days and is not counted in adapter cost.

### 4.1 API adapter

| Item | Specification |
|---|---|
| Trigger | Scheduler, every 15 minutes in the delivery window; one health check per hour outside it |
| Authentication | Must be verified in P0. Support a bearer token, API key header, or session cookie only after the portal documents one |
| Endpoint | Must be verified in P0. Do not hard-code a path |
| Pagination | Must be verified in P0. Support cursor and page-number styles behind configuration |
| Incremental filter | Must be verified in P0. Prefer a posted-after parameter; fall back to client-side week filtering |
| Rate limit | Must be verified in P0. Default to 1 request per 2 seconds and 30 requests per minute until confirmed |
| Webhooks | Must be verified in P0. Do not build a webhook receiver before the portal confirms support and signing |
| Allowance exposure | Must be verified in P0. If the portal exposes remaining allowance, read it and reconcile against local counts; never trust it as the only source |
| Detail fetch | Call `fetchDetails` only when the list payload lacks a required canonical field |
| Credential storage | Encrypted at rest in `lead_sources.credentials`; never returned by any API response |
| Credential rotation | Supported without redeploy through Settings; old credential kept for 24 hours to finish in-flight calls |

If the API can return the full canonical field set in the list response, skip `fetchDetails` entirely. If it cannot, cap detail calls at 75 per week and 1 per 2 seconds.

### 4.2 CSV export adapter

| Item | Specification |
|---|---|
| Trigger | File watcher on `D:\PharmaLead\drop\`, polled every 60 seconds, plus `Upload export` in the UI |
| Accepted formats | `.csv` and `.xlsx`, maximum 10 MB, maximum 500 rows |
| Source of truth | The exported file; the operator confirms the export covers the intended week |
| File naming | `<week_key>_<anything>.csv` or `.xlsx`; the week is taken from the file content first, then the name |
| Processing | Move the file to `D:\PharmaLead\drop\processed\YYYY-MM-DD\` on success; move it to `D:\PharmaLead\drop\rejected\` on failure; never delete the original |
| Partial files | Ignore files modified in the last 10 seconds; wait for the next poll |
| Duplicate files | Identical checksum and same week is a replay and becomes a no-op |
| Header mapping | Case-insensitive; trim whitespace; accept known aliases from the mapping table |
| Encoding | UTF-8 with or without BOM, or Windows-1252 converted to UTF-8 with a warning; reject other encodings with `encoding_unsupported` |
| Manual effort | Download the export from the seller dashboard, save it to `D:\PharmaLead\drop\`, confirm the week in the UI if the file name is ambiguous |
| Fallback | If the portal changes the export columns, the adapter raises `SourceSchemaChanged` and the manual adapter keeps the week fed |

The exact export menu path, available columns, and whether contact details are included must be verified in P0. Save one unmodified sample export as the fixture for adapter tests.

### 4.3 Manual entry adapter

| Item | Specification |
|---|---|
| Trigger | Operator action on `Ingestion > Manual entry` |
| Source of truth | The operator reading the seller dashboard |
| Modes | Single-lead form, and bulk paste of tab-separated rows with a header |
| Required fields | Company, requirement text, and at least one of phone or email |
| Week assignment | From `posted_at` when present; otherwise the week selected by the operator |
| Duplicate handling | Same rules as every other adapter, using the derived `external_lead_id` |
| Manual effort | 20 to 30 minutes for 75 leads; 5 minutes for a top-up of 10 leads |
| Validation | Identical canonical validation; no bypass for manual rows |
| Audit | Store `entered_by`, `entered_at`, and `source = manual` on every row |
| Bulk paste format | Header row required, tab-separated, columns in the order in 5.1. Blank lines ignored. A second header row is rejected |

Manual rows are never marked verified. Set `verified_business = false` unless the operator explicitly ticks `Verified on portal`.

## 5. Canonical field mapping

### 5.1 Fields

| Canonical field | Type | Required | Notes |
|---|---|---|---|
| `external_lead_id` | string, max 64 | yes | Source identifier. If absent, derive it as in 5.2. Unique with the source id |
| `company_name` | string, max 200 | yes | Trim and collapse whitespace. If absent, use the buyer name and set `company_derived = true`; if both are absent, quarantine the row |
| `buyer_name` | string, max 120 | no | May be a person or a role |
| `buyer_phone` | string, E.164 | conditional | Required when `buyer_email` is empty. Store digits with a leading `+`. Accept 8 to 15 digits after normalisation |
| `buyer_email` | string, max 254 | conditional | Required when `buyer_phone` is empty. One address only. Reject header injection characters |
| `requirement_text` | text, max 8,000 | yes | The buyer's own words. Preserve spelling and language. Quarantine rows shorter than 15 characters |
| `posted_at` | datetime, UTC | no | If absent, use `fetched_at` and set `posted_at_derived = true`. The original timezone must be verified in P0 |
| `category` | string, max 120 | no | Portal category, for example `Pharmaceuticals`. Used as a weak hint only |
| `product_hint` | string, max 300 | no | Portal product field, if any. Never treated as truth over the inquiry text |
| `quantity` | decimal(18,3) | no | Numeric only. Keep the raw token in `quantity_raw`. Must be greater than 0 |
| `unit` | string, max 40 | no | Raw unit as supplied. M1 maps it to a canonical unit |
| `location` | string, max 200 | no | City and state as supplied. M1 resolves the country |
| `country` | string, ISO2 | no | Use the source value when it is a valid ISO2. Otherwise resolve via M1 and set `country_source = derived` |
| `lead_type` | string, max 60 | no | Portal lead type. Never used as the buyer-type decision; M1 decides |
| `verified_business` | boolean | no | `true` only when the source explicitly marks the buyer as verified. Default `false` |

Also store `raw_payload` as JSON, `source_row_number`, `source_file_checksum`, and `fetched_at` for every row.

### 5.2 Deriving `external_lead_id`

When the source provides no identifier:

1. Normalise `company_name`: lowercase, strip punctuation, collapse whitespace.
2. Normalise the timestamp: ISO 8601 in UTC to the second. If `posted_at` is missing, use the batch's `received_at` truncated to the minute.
3. Take the first 200 characters of the normalised `requirement_text`: lowercase, collapse whitespace.
4. Compute `sha256(company + "|" + timestamp + "|" + text)`.
5. Use `gen_` followed by the first 24 hexadecimal characters.
6. If `company_name` is empty, substitute the normalised `buyer_phone`; if both are empty, quarantine the row before hashing.
7. If the derived id already exists with identical company, timestamp, and text, treat the row as a replay.
8. If the derived id already exists with different content, raise `id_collision`, quarantine the row, and alert the admin. Never append a counter automatically.

The derivation is deterministic across retries. Do not include `fetched_at` or a random value.

### 5.3 Mapping rules

1. Map by canonical name first, then by a case-insensitive alias list. Trim and collapse whitespace before matching.
2. Never map two source columns to one canonical field without an explicit priority order stored in the source configuration.
3. Unknown source columns are preserved in `raw_payload` and ignored.
4. A required canonical field missing from the source mapping blocks the batch at validation and shows the missing field in the error report.
5. The default mapping is stored per source and editable in Settings, so a portal column rename does not require a deploy.
6. The mapping configuration is versioned. Every batch records `mapping_version`.

## 6. Ingestion state machine

### 6.1 States

Batch states:

| State | Meaning | Allowed next states |
|---|---|---|
| `received` | Pull returned data and a checksum | `validated`, `rejected` |
| `validated` | Schema and batch-level checks passed | `committed`, `rejected` |
| `committed` | Batch, rows, allowance increment, and audit written in one transaction | `scored`, `quarantined` |
| `scored` | M1 to M3 completed for every non-quarantined row | `available`, `scored` (retry) |
| `available` | Leads visible in the inbox | `conflict` |
| `quarantined` | Committed with one or more invalid rows | `scored` |
| `conflict` | Same week arrived with a different checksum | `available` after resolution |
| `rejected` | Batch not written at all | terminal |

Row states:

| State | Meaning |
|---|---|
| `staged` | Parsed and held in memory |
| `valid` | Passed row-level validation |
| `invalid` | Failed row-level validation; quarantined with errors |
| `duplicate` | Matched an existing source id; not inserted |
| `committed` | Inserted in the batch transaction |
| `scored` | Scored by the engine |
| `gated` | Hard-rejected by the engine, including `allowance_exhausted` |
| `overflow` | Beyond position 75 in the week; stored but not accepted |

### 6.2 Stage validations

| Stage | Checks | Failure handling |
|---|---|---|
| `received` | Checksum present; payload non-empty; size at most 10 MB; at most 500 rows; UTF-8 valid; declared week matches `week_key` or is derivable | Reject the whole batch with `SourceInvalidPayload`; write nothing |
| `validated` | Every canonical required field present; types and lengths valid; at least one contact channel; `posted_at` parseable; country valid or null; duplicate `external_lead_id` within the batch detected | Row-level failures become `invalid`; a missing required mapping or an empty batch rejects the whole batch |
| `committed` | Unique constraints; allowance row exists or is created; accepted position computed; audit row written | Any database error rolls back the entire batch; no partial batch |
| `scored` | M1 normalisation, M2 match, M3 score for every `committed` row | A scoring failure leaves the batch in `scored` retry state; it never rolls back the committed rows |
| `available` | All rows are `scored`, `gated`, or `invalid`; inbox cache invalidated | Retry until complete; never show a half-scored batch |

### 6.3 Atomicity guarantee

1. The batch header, all rows, the allowance increment, and the audit entry are written in **one database transaction**.
2. If any write fails, the transaction rolls back and the batch remains absent. The operator sees `Batch failed. Nothing was imported.`
3. Row-level validation failures do not roll back the batch. They are written as `invalid` rows inside the same transaction with their error JSON.
4. Scoring runs after commit in a separate transaction per row group. It is idempotent and retriable.
5. A batch is never visible in the inbox until its state is `available`.
6. The accepted count is computed inside the commit transaction using a row lock on `weekly_allotments`, so two concurrent batches cannot both claim the same 75 positions.

### 6.4 Accepted-position rule

1. Order all valid, non-duplicate rows by `posted_at` ascending, then `external_lead_id` ascending.
2. The first `max(0, 75 - existing_accepted_count)` rows become `committed` accepted leads.
3. Every remaining row becomes `overflow`, is stored, and is gated as `allowance_exhausted` at scoring.
4. Never drop an overflow row silently. Show `overflow_count` on the batch detail and in the ingestion alert.
5. A quarantined row does not consume an accepted position.

## 7. Idempotency, duplicates, retries, and health

### 7.1 Idempotency keys

| Key | Scope | Behaviour |
|---|---|---|
| `lead_sources.id + external_lead_id` | Global per source | Unique index. A repeat is `duplicate`, not an error |
| `lead_sources.id + week_key` | Batch | Unique index. Only one active batch per source per week |
| Manifest checksum | Batch content | Determines replay versus conflict |
| `content_hash` | Per row | SHA-256 of the canonical field values. Detects a changed row with the same id |

### 7.2 Manifest checksum

1. Build a canonical line per record: `external_lead_id`, `company_name`, `buyer_phone`, `buyer_email`, `requirement_text`, `posted_at`, `quantity`, `unit`, `country`, joined by a unit separator.
2. Sort lines by `external_lead_id` ascending.
3. Join with a newline, encode UTF-8, and compute SHA-256.
4. Store the checksum on the batch and in the audit entry.
5. The checksum is stable across retries and independent of file formatting, column order, and whitespace differences.

### 7.3 Duplicate and conflict handling

| Situation | Behaviour |
|---|---|
| Same source, same `external_lead_id`, identical `content_hash` | Replay. No insert. Return the existing lead id. Increment `replay_count` |
| Same source, same `external_lead_id`, different `content_hash` | Quarantine the row as `id_collision`. Do not update the existing lead. Alert admin |
| Same week, same manifest checksum | Replay. The whole batch is a no-op. Return the existing batch id and `replayed = true` |
| Same week, different manifest checksum | Set batch state `conflict`. Write nothing. Alert admin with both counts and both checksums |
| Different week, same `external_lead_id` | Treat as a new lead only if the source explicitly reuses ids across weeks; otherwise flag `cross_week_duplicate` and quarantine. The reuse rule must be verified in P0 |
| Near-duplicate text, different id and contact | Let M1 flag `near_duplicate` and route to review; do not auto-suppress |

### 7.4 Conflict resolution

The admin alert offers three choices. Nothing is written until one is chosen.

| Choice | Behaviour | Guard |
|---|---|---|
| Keep existing | Close the conflict, mark the new payload `discarded`, keep the original batch | Always allowed |
| Replace batch | Delete the existing unscored batch and write the new one in one transaction | Allowed only when 0 leads in the batch are actioned. Otherwise disabled with the reason |
| Merge as supplement | Insert only `external_lead_id` values not already present; recompute accepted positions and overflow | Default choice when 1 or more leads are actioned |

Every resolution records the actor, timestamp, chosen option, and both checksums.

### 7.5 Retry and backoff

| Attempt | Delay before retry | Notes |
|---|---|---|
| 1 | Immediate | Initial call |
| 2 | 30 seconds | |
| 3 | 2 minutes | |
| 4 | 10 minutes | |
| 5 | 30 minutes | |
| Final | No further retry | Move to dead letter; raise `Ingestion failure` alert |

Add jitter of plus or minus 20% to every delay. Honour `Retry-After` when present; it overrides the table. Maximum total retry window: 45 minutes. Do not retry `SourceAuthExpired`, `SourceSchemaChanged`, `SourceInvalidPayload`, or `SourceConfigError`; these require a human.

### 7.6 Rate limiting

1. Respect the portal's published limit. It must be verified in P0.
2. Until confirmed, use a token bucket of 1 request per 2 seconds and 30 requests per minute per source.
3. On HTTP 429 or an equivalent signal, stop for the `Retry-After` duration or 5 minutes when absent, then retry with backoff.
4. Never run two pulls for the same source concurrently. Use a database lock with a 10-minute expiry.
5. Log every request count per minute on the source health page.

### 7.7 Health states

| State | Trigger | Operator-visible message | Automatic recovery |
|---|---|---|---|
| `healthy` | Last pull succeeded within the expected window | `Healthy. Last pull 08:45.` | Not applicable |
| `degraded` | 1 or 2 consecutive failures, or latency above 20 seconds | `Degraded. Retrying.` | Yes |
| `rate_limited` | A 429 or equivalent within the last 15 minutes | `Rate limited. Next attempt in 4 minutes.` | Yes |
| `credential_expiring` | Credential expires in 7 days or fewer | `Credential expires in 3 days. Renew it.` | No; alert only |
| `auth_expired` | Authentication rejected, or expiry date passed | `Credential expired. Ingestion stopped.` | No; requires new credentials |
| `unreachable` | Connection refused, DNS failure, or 5 consecutive failures | `Portal unreachable. Using fallback.` | Yes, with backoff |
| `schema_changed` | Required columns or fields missing, or a renamed field above threshold | `Export format changed. Mapping needs review.` | No; requires mapping update |
| `misconfigured` | Missing source id, invalid mapping, or invalid path | `Source is not configured correctly.` | No |
| `paused` | Operator paused ingestion in Settings | `Ingestion paused by <user> at <time>.` | No |

Credential expiry tracking: store `credential_expires_at` on the source. Run a daily check at 07:00 IST and raise alerts at 7, 3, and 1 day. On the expiry day, set `credential_expiring`; after expiry or a rejected call, set `auth_expired` and stop the scheduler for that source.

### 7.8 Health page

Show per source: state, last successful pull, last attempt, consecutive failures, next scheduled attempt, credential expiry, requests in the last hour, accepted and overflow counts for the current week, and the last three errors with codes only. Provide `Retry now`, `Pause`, and `Edit credentials` actions.

## 8. P0 discovery spike

The spike is 2 days. Complete all four items and record the answers in `docs/p0-findings.md` with screenshots and one sample export. Do not write adapter code before items 1 and 2 are answered.

### 8.1 Numbered checklist

1. **Find the export and prove the fields.** Log in to the seller dashboard, open the leads view, export one week, and save the file unchanged. Record the exact menu path, file format, row count, column names, and whether buyer phone, email, city, country, quantity, and posted time are present. Confirm the export covers exactly the contracted week.
2. **Ask the account manager in writing.** Request API access, allowance exposure, webhooks, rate limits, fee basis, expiry rule, and permission to store and export enquiry data. Keep the written reply. Do not accept a verbal answer for the allowance or expiry rules.
3. **Confirm the week boundary and delivery moment.** Establish the exact day and time the week opens and closes in Asia/Kolkata, and whether the 75 are available at 00:00 Monday, at a fixed business hour, or on a rolling basis.
4. **Confirm whether the 75 arrive together or trickle.** Record how many arrive per hour on a typical Monday and whether a late lead can arrive after Tuesday. This determines the polling window and the meaning of `received_count`.

### 8.2 Decision branches

| Finding | Decision |
|---|---|
| Item 1 finds a usable export with contact details | Build the CSV adapter first. Ship it in week 1. Treat the API as an optimisation |
| Item 2 grants API access with the canonical fields | Build the API adapter first, with CSV as the fallback. Keep the CSV adapter anyway |
| Item 2 grants API access but no contact details | Do not use the API alone. Use the export or manual entry for contact data |
| Item 1 export lacks phone or email | Manual entry becomes mandatory for contact details. Build the bulk paste flow first |
| Items 1 and 2 both fail | Ship manual entry only. Run the weekly procedure in 9.2 |
| Item 3 shows a non-Monday boundary | Configure `week_start_day` and `week_timezone`; do not hard-code Monday. Confirm the contract wording before changing |
| Item 3 shows the week opens at a fixed hour | Schedule the first pull 5 minutes after that hour |
| Item 4 shows a single batch | One pull, one digest, one batch notification |
| Item 4 shows a trickle | Poll every 15 minutes from 08:00 to 20:00 IST, count accepted leads on arrival, and send one digest per day |
| Item 2 refuses permission to store or export enquiry data | Escalate in writing. Do not store contact data beyond the minimum needed to display and contact the buyer. Pause the build until resolved |
| Item 2 reveals a per-lead fee basis or a different allowance | Stop. The shared project context says 75 pre-paid leads with no per-lead price. Reconcile the contract before writing code |

### 8.3 Operator-facing checklist wording

Copy the text below into the operator's task and record each answer. Do not paraphrase the questions sent to the account manager.

```
P0 discovery checklist — complete in 2 days

Who: operator, with the account manager on email.
Record every answer in docs/p0-findings.md, with a screenshot where possible.

1. Export test
   - Log in to the IndiaMART seller dashboard.
   - Open the leads view and export the current week.
   - Save the file unchanged as D:\PharmaLead\drop\2026-W43_sample.csv.
   - Write down: the exact menu path, the file format, the row count, every column name,
     and whether phone, email, city, country, quantity, and posted time are included.

2. Written request to the account manager
   Send exactly this and keep the reply:
   "We are building an internal tool for our own team to respond to our contracted leads faster.
   Please confirm in writing:
   (a) Is API access available for our account, and what is the authentication method?
   (b) Can we read remaining weekly allowance programmatically, and what is the exact reset time?
   (c) Are webhooks available for new leads, and how are they signed?
   (d) What are the API rate limits?
   (e) Is the commercial basis a fixed weekly fee for 75 leads, or is any part charged per lead?
   (f) What is the exact expiry rule for unused leads?
   (g) Do we have permission to store enquiry data and buyer contact details in our own database,
       and to export it to our internal team?"

3. Week boundary and delivery moment
   - Confirm the day and time the week opens and closes, in IST.
   - Confirm when the 75 leads become available: 00:00 Monday, a fixed business hour, or rolling.
   - Write down the answer in the operator's own words, not a summary.

4. Delivery shape
   - On the next Monday, record how many leads arrive by 10:00, 13:00, 17:00, and 20:00 IST.
   - Record whether any lead arrived after Tuesday.
   - Note whether the running total stops at exactly 75.

Do not share buyer phone numbers or emails with anyone outside the team.
Do not install browser extensions, scrapers, or automation tools on the portal.
```

## 9. Fallback operation

### 9.1 Manual entry with bulk paste

Use this when no machine-readable feed exists, when the export schema breaks, or when the API credential expires.

Bulk paste rules:

1. Open `Ingestion > Manual entry > Bulk paste`.
2. Select the target `week_key`.
3. Paste a header row followed by one row per lead, tab-separated.
4. Column order: `Company`, `Buyer`, `Phone`, `Email`, `Country`, `Product`, `Quantity`, `Unit`, `Posted at`, `Requirement`.
5. The first row must be the header. Blank lines are ignored. A second header row is rejected.
6. Preview shows parsed rows, derived country, derived `external_lead_id`, and validation errors before commit.
7. Commit writes the batch in one transaction with `source = manual`.
8. The operator sees `75 accepted, 0 overflow, 3 quarantined` and can download the quarantine report.

Example paste (tabs shown as separators, one header, one data row):

```
Company	Buyer	Phone	Email	Country	Product	Quantity	Unit	Posted at	Requirement
Lagos Pharma Ltd	Ada Okafor	+2348012345678	ada@example.com	NG	Paracetamol 500 mg tablet	5000	strip	2026-10-19 10:14	Need 5000 strips of Paracetamol 500mg tablets for Lagos.
```

Rules:

- Never auto-correct a phone number that fails validation. Quarantine the row.
- A missing country is allowed; M1 resolves it from the city or text. A missing requirement is not allowed.
- Manual rows never claim portal verification.
- Every manual batch records the operator, the paste checksum, and the row count.

### 9.2 Weekly operating procedure

| Time (IST) | Day | Action | Owner | Output |
|---|---|---|---|---|
| 00:05 | Monday | Scheduler opens the new week and runs the first pull, if a machine feed exists | System | Batch `received` or `healthy` status |
| 08:45 | Monday | Check the ingestion health page; if no batch, run the export or manual paste | Operator | Batch `available` |
| 09:00 | Monday | Confirm the count is 75; resolve any quarantine and overflow | Operator | `75 accepted` or an incident note |
| 09:15 | Monday | Work the inbox: `act_now` first, then `good` | Operator | Contacts logged |
| 11:00 | Monday | Midday check: at least 50% of the week actioned | Operator | Progress note |
| 14:00 | Monday | If the feed trickles, confirm the poll is still running | Operator | Health check |
| 14:00 | Thursday | Allowance risk check: action at least 60% of received | Operator | Contact remaining leads |
| 17:00 | Friday | Close-out note: untouched count, reasons, and waste | Operator | Weekly note in Analytics |
| 09:00 | Daily | Read the daily digest and clear due leads | Operator | Inbox zero for due items |
| 00:00 | Monday | Week closes; untouched allowance expires; new ledger row opens | System | Closed week record |

Fallback triggers: 2 consecutive ingestion failures, `auth_expired`, `schema_changed`, or a missing batch by 08:45 Monday. When a trigger fires, switch to manual entry for the week and do not wait for a fix. Record the fallback reason on the batch.

### 9.3 What fallback must never do

1. Never bypass canonical validation.
2. Never fabricate a lead to reach 75.
3. Never copy buyer data into email, chat, or a spreadsheet outside the approved folder.
4. Never mark a lead contacted without an actual human contact.
5. Never change the week boundary to make a late lead fit.
6. Never merge two weeks into one ledger row.

## 10. Error codes

| Code | Meaning | User message |
|---|---|---|
| `batch_too_large` | More than 500 records | `This file has more than 500 rows. Split it by week.` |
| `encoding_unsupported` | Not UTF-8 | `Save the file as UTF-8 and try again.` |
| `schema_changed` | Required column missing or renamed | `The export format changed. Review the column mapping.` |
| `missing_required_field` | A canonical required field is unmapped | `Map the required field <name> before importing.` |
| `invalid_contact` | No usable phone or email | `This lead has no valid phone or email.` |
| `id_collision` | Derived or source id exists with different content | `This lead id already exists with different content. Review it.` |
| `week_conflict` | Same week, different checksum | `This week already has 75 leads. Choose how to resolve the new file.` |
| `allowance_overflow` | More than 75 accepted leads | `3 leads are beyond the weekly allowance and will not enter the inbox.` |
| `source_timeout` | Pull exceeded 30 seconds | `The portal did not respond. Retrying.` |
| `auth_expired` | Credential rejected or expired | `The portal credential expired. Update it to resume.` |
| `rate_limited` | Portal rate limit hit | `The portal asked us to slow down. Next attempt in 5 minutes.` |
| `scoring_incomplete` | Some rows not scored | `Leads are imported. Scoring is still running.` |
| `transaction_failed` | Commit rolled back | `Import failed. Nothing was saved.` |
