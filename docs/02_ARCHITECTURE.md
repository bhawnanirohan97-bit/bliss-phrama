# PharmaLead AI — System Architecture

**Document status:** Approved
**Version:** 1.0
**Date:** 2026-10-22
**Owner:** Solo full-time developer
**Companion documents:** `01_PRD_AMENDMENTS.md`, `13_RISKS_OPEN_QUESTIONS.md`

All section references in this document point to PRD v1.1, which is PRD v1.0 as amended by `01_PRD_AMENDMENTS.md`. All dates and weekly boundaries are in Asia/Kolkata unless stated otherwise. Anything about the IndiaMART portal that has not been observed on the real account is marked as requiring verification in Phase P0 and must not be treated as fact.

---

## 1. Overview and architecture principles

PharmaLead AI is a Windows-only Laravel 11 application serving a React 19 single-page application from the same origin, backed by MySQL 8 in production and SQLite in development and tests. It ingests 75 pre-paid B2B pharmaceutical buyer inquiries per week, scores them deterministically against a catalogue of 1000+ SKUs and a country eligibility table, ranks them into one inbox, drafts three outreach messages per eligible lead, and prices the allowance that expires unused. It never purchases a lead and never sends a message.

### 1.1 The one invariant

> **AI evaluates commercial relevance; the backend policy decides; a human acts; the database records evidence.**

Every design decision below is subordinate to that ordering. The pipeline may lose information when a component fails. It may never gain permissiveness. A model cannot raise a band, clear a hard gate, alter a score component, or cause a message to be sent.

### 1.2 Principles

| # | Principle | Consequence in code |
|---|---|---|
| P1 | Deterministic by default | The Null AI provider is a valid, fully functional deployment. Every model phase is additive and flag-gated. |
| P2 | Hard gates are structural | Gates live in `Policy\GateEvaluator` and are evaluated before scoring. No caller can pass an override into a gate. Override is a separate, audited human action. |
| P3 | One writer per fact | The allowance ledger is written only by `Allowance\Allocator`. Scores are written only by `Policy\Assessor`. Contact facts are written only by `Crm\ContactLogger`. |
| P4 | Idempotent by construction | Every ingest path is safe to re-run. Uniqueness is enforced in the database, not only in PHP. |
| P5 | Append-only evidence | Assessments, allocations, model runs and audit events are immutable. Corrections are new rows that supersede. |
| P6 | Degrade, never fail closed into silence | A missing provider, a dead portal or a locked database leaves the existing inbox usable and states plainly what is stale. |
| P7 | Local-only | One host, one origin, LAN binding, loopback database, no internet ingress. |
| P8 | Shadow before live | Every non-deterministic component writes to a shadow channel first and is compared against the operator before promotion (PRD Section 2.4.1). |
| P9 | Buyer PII is radioactive | Encrypted at rest, blind-indexed for search, redacted before egress, never in logs, never in the SPA bundle. |
| P10 | Operator time is the budget | Every screen exists to reduce the number of decisions the operator makes per lead. |

### 1.3 Layers

The backend is layered, and dependencies point strictly downward:

```
HTTP / Console entry points
        |
        v
  Controllers, Jobs, Console Commands        (transport)
        |
        v
  Modules: Connector, Catalog, Normaliser, Matcher,
           Qualifier, Policy, Ranker, Allowance,
           Crm, Drafter, Notifier, Analytics, Auditing, Auth
        |
        v
  Domain value objects and contracts         (pure PHP, no framework)
        |
        v
  Eloquent models and repositories           (persistence)
        |
        v
  MySQL 8 / SQLite
```

A module may call another module's public interface. A module may not reach into another module's models. All cross-module calls go through the interface listed in Section 3.

---

## 2. Runtime topology on Windows

### 2.1 Diagram

```
                       OFFICE LAN  192.168.1.0/24
                       (no internet exposure, no port forward)

   +------------------+                    +------------------+
   |  Operator PC     |                    |  Sales team PC   |
   |  (browser)       |                    |  (browser)       |
   +--------+---------+                    +--------+---------+
            |                                       |
            |              HTTP  :8080              |
            +------------------+--------------------+
                               |
                               v
  +===============================================================+
  |            OFFICE HOST   (Windows 11 Pro, always on)          |
  |                                                               |
  |  +---------------------------------------------------------+  |
  |  |  Nginx (or Apache) for Windows            :8080         |  |
  |  |                                                         |  |
  |  |    GET  /               ->  SPA bundle (Vite build)     |  |
  |  |    ANY  /api/*          ->  Laravel 11 front controller |  |
  |  |    GET  /up             ->  health endpoint             |  |
  |  +----------------------------+----------------------------+  |
  |                               |                               |
  |                               v  (PHP 8.3, php-cgi/FastCGI)  |
  |  +---------------------------------------------------------+  |
  |  |  Laravel application                                    |  |
  |  |    HTTP kernel  |  queue worker  |  console scheduler   |  |
  |  +------+------------------+------------------+------------+  |
  |         |                  |                  |               |
  |         |                  |                  |               |
  |         v                  v                  v               |
  |  +-------------+   +----------------+   +---------------+     |
  |  | Queue worker|   | Scheduler      |   | MySQL 8       |     |
  |  | queue:work  |   | schedule:run   |   | 127.0.0.1     |     |
  |  | --daemon    |   | every 60 s     |   | :3306         |     |
  |  | (1 process) |   | (1 process)    |   | (loopback)    |     |
  |  +------+------+   +-------+--------+   +-------+-------+     |
  |         |                  |                    ^             |
  |         +------------------+--------------------+             |
  |                            |                                  |
  |  +-------------------------v-------------------------------+  |
  |  | Windows Task Scheduler  (host-level triggers)           |  |
  |  |   at startup  : start web, worker, scheduler            |  |
  |  |   08:00 daily : php artisan leads:pull                  |  |
  |  |   16:00 daily : php artisan leads:pull                  |  |
  |  |   Mon 00:05   : php artisan allowance:close-week        |  |
  |  |   02:00 daily : backup.ps1  (mysqldump)                 |  |
  |  |   03:00 daily : php artisan retention:purge             |  |
  |  |   every 5 min : php artisan system:health-check         |  |
  |  |   day 1, 04:00: php artisan backup:verify-restore       |  |
  |  +-------------------------+-------------------------------+  |
  |                            |                                  |
  |                            v                                  |
  |  +---------------------------------------------------------+  |
  |  | Backup primary  C:\PharmaLead\backups\*.sql.gz         |  |
  |  | 14 daily + 8 weekly; weekly mirrored to D: drive        |  |
  |  +---------------------------------------------------------+  |
  +===============================================================+

  Listening sockets:
    :8080   bound to the LAN interface, Windows Firewall private profile,
            scoped to the office subnet. This is the only LAN-facing port.
    :3306   bound to 127.0.0.1 only. Never reachable from the LAN.
    none    No inbound internet, no router port forward, no tunnel in v1.
```

### 2.2 Why this shape

| Choice | Reason |
|---|---|
| One origin for SPA and API | No CORS surface, no cross-origin cookie problems, one firewall rule, one TLS decision to defer |
| Laravel serves `/api/*` and Vite builds `public/spa` | Keeps a single deployable artefact directory and makes rollback a directory switch (PRD Amendment Section 21.6) |
| Database sessions | A worker or scheduler restart must not log the operator out mid-triage |
| Database queue | Removes Redis as a correctness dependency; the queue volume is under 1000 jobs per week |
| One queue worker process | The workload is 75 leads per week plus drafts; a single worker is sufficient and makes ordering easier to reason about. The concurrency gate in PRD Section 19.1 (G3) is exercised by a test harness that spawns 20 workers, not by production running 20 |
| Windows Task Scheduler for triggers | Survives reboot, survives a crashed scheduler process, and is observable in the Windows Event Log |
| Scheduler *and* Task Scheduler | Task Scheduler owns host-level cadence and boot behaviour; Laravel's scheduler owns application-level cadence. They do not duplicate work: Task Scheduler invokes `leads:pull`, the Laravel scheduler invokes internal housekeeping |

### 2.3 Ports and process inventory

| Process | Port | Started by | Restart behaviour |
|---|---|---|---|
| Nginx / Apache | 8080 | Windows service | Automatic |
| php-cgi / FastCGI | 9000 (loopback) | Windows service | Automatic |
| MySQL 8 | 3306 (loopback) | Windows service | Automatic |
| Queue worker | none | Task Scheduler at startup, wrapped in a restart loop | Restart on exit with a 10-second delay, max 50 restarts per day |
| Scheduler | none | Task Scheduler at startup | Same restart policy |
| Backup script | none | Task Scheduler 02:00 | Missed run re-triggered on next boot |

---

## 3. Module map

Fourteen modules. Each entry gives the responsibility, the public surface that other modules may call, and the modules or infrastructure it depends on. Nothing outside the listed public surface is callable from another module.

### 3.1 Auth

**Responsibility.** Authenticate users, hold the session, resolve the current actor, and authorise every route and every critical configuration change. Owns the role-to-permission mapping and the re-authentication challenge that guards allowance fee, country classification, feature flags, AI provider and monthly cap changes (PRD Amendment Section 13.8).

**Public methods.**

| Method | Contract |
|---|---|
| `login(string $email, string $password, Request $request): Session` | Verifies the hash, applies the rate limiter, rotates the session id, writes an audit event |
| `logout(): void` | Invalidates the session and the CSRF token |
| `current(): ?Actor` | Returns id, name, email, role and the resolved permission set |
| `can(Actor $actor, string $permission): bool` | Single authorisation predicate used by every policy |
| `requireReauth(Actor $actor, string $reason): void` | Throws unless a re-authentication occurred in the last 5 minutes |
| `issuePasswordReset(string $email): void` | Queues a reset token |
| `resetPassword(string $token, string $password): void` | Consumes the token, invalidates all sessions for the user |

**Depends on.** Auditing, the `users`, `permissions` and `role_permission` tables, Laravel's session and hashing services.

### 3.2 Catalog

**Responsibility.** Own the 1000+ SKU catalogue, its synonyms, its destination restrictions, and the country eligibility table. Provide product lookup and search. Enforce the uniqueness of a SKU identity. Produce the completeness report that tells the operator which SKUs are missing export eligibility or restrictions.

**Public methods.**

| Method | Contract |
|---|---|
| `findProduct(int $id): ?Product` | Single SKU with synonyms and restrictions eager-loaded |
| `searchProducts(ProductQuery $query): Paginator` | Filter by brand, generic, strength, form, category, eligibility, active |
| `upsertFromRow(CatalogRow $row): Product` | Idempotent import: match on the natural key, create or update, never duplicate |
| `importBatch(CatalogBatch $batch): ImportReport` | Bulk upsert; returns counts of created, updated, skipped, rejected |
| `synonymsFor(Product $product): array` | Ordered synonym list for the matcher |
| `restrictionsFor(Product $product, string $countryCode): ?Restriction` | The applicable restriction record, if any |
| `country(string $code): ?Country` | Country row, or null when unclassified |
| `classifyCountry(string $code): CountryClass` | `allowed_proven`, `allowed_unproven`, `restricted`, `blocked`, `unknown` |
| `completenessReport(): CompletenessReport` | Counts of SKUs missing eligibility, restrictions, price, MOQ or pack size |
| `candidatesForTokens(array $tokens, int $limit): array` | Token-level candidate retrieval used by the Matcher |

**Depends on.** Auditing, `products`, `product_synonyms`, `product_restrictions`, `countries`.

### 3.3 Normaliser

**Responsibility.** Convert a raw source record into the canonical internal form without losing the original. Parse strength and dosage form out of product strings; parse quantity and unit; map country names, codes and misspellings to ISO 3166-1 alpha-2; classify buyer-type vocabulary; strip boilerplate greetings; and detect the language mix (English, Hinglish, broken English). Deterministic only; no model in v1.

**Public methods.**

| Method | Contract |
|---|---|
| `normalise(RawLead $raw): NormalisedLead` | Returns the canonical form plus a list of per-field normalisation warnings |
| `parseProduct(string $text): ProductPhrase` | Brand-or-generic phrase, strength, strength unit, dosage form, confidence |
| `parseQuantity(string $text): ?Quantity` | Numeric value, unit, and whether the unit was explicit |
| `parseCountry(string $text): ?string` | ISO alpha-2 or null |
| `parseBuyerType(string $text): BuyerType` | Enum including `personal` and `job_vendor` |
| `parseIntent(string $text): Intent` | Enum from PRD Amendment Section 5.2.5 |
| `detectLanguage(string $text): LanguageProfile` | `english`, `hinglish`, `broken_english`, `mixed`, with a confidence |
| `stripBoilerplate(string $text): string` | Removes portal signatures, greetings and tracking strings |

**Depends on.** Catalog (for synonym material and country aliases), `Normalisation\Lexicon` static data.

**Invariant.** `Normaliser` never writes to the database. It is a pure function of its input and the lexicon, which is what makes the evaluation harness reproducible.

### 3.4 Matcher

**Responsibility.** Rank catalogue candidates for a normalised product phrase. Produces up to five candidates, each with a match state (exact, strong, partial, weak, category-only, none) and a score. Applies the matcher floor. Never decides the band; only supplies the product-match input to the Policy module.

**Public methods.**

| Method | Contract |
|---|---|
| `match(ProductPhrase $phrase): MatchResult` | Top-1 candidate plus up to four alternatives, each with state and score |
| `score(Product $product, ProductPhrase $phrase): MatchCandidate` | Score one SKU against one phrase |
| `floor(): float` | Current matcher floor, read from configuration |
| `explain(MatchResult $result): string` | Which tokens matched and which did not |

**Depends on.** Catalog, `Normalisation\Tokeniser`, `Normalisation\StrengthComparator`.

**Invariant.** Every candidate is persisted to `lead_matches` with `is_top_1`, so top-1 accuracy (PRD Section 19.1, G1) is measurable from stored data rather than recomputed.

### 3.5 Qualifier

**Responsibility.** Assemble the qualification facts: buyer type, intent, destination and its class, requested quantity and unit, and the missing-information list. Supplies the inputs to the seven hard-gate identifiers (six conditions, with buyer type split into two) but does not apply the gates themselves. Emits risk flags.

**Public methods.**

| Method | Contract |
|---|---|
| `qualify(NormalisedLead $lead, MatchResult $match): Qualification` | Full fact set with a provenance note per field |
| `missingInformation(Qualification $q): array` | Subset of the fixed 11-field vocabulary |
| `riskFlags(Qualification $q, MatchResult $match): array` | Subset of the fixed 9-flag vocabulary |
| `confidence(Qualification $q, MatchResult $match): float` | Raw 0.0–1.0 signal, uncalibrated in v1 |

**Depends on.** Catalog, Normaliser output, Matcher output.

### 3.6 Policy

**Responsibility.** The decision layer. Evaluates the seven hard-gate identifiers, computes the five scoring components, sums to a total, applies the band admission rules, and records the decision with a `policy_version`. This is the only module permitted to write `lead_assessments`.

**Public methods.**

| Method | Contract |
|---|---|
| `assess(Qualification $q, MatchResult $match, LeadContext $ctx): Assessment` | Full assessment: gates, components, total, band, explanation |
| `evaluateGates(Qualification $q, MatchResult $match, LeadContext $ctx): GateSet` | The seven gates and the first failing gate, if any |
| `scoreProductMatch(MatchResult $m): int` | 0–35, per PRD Amendment Section 5.2.2 |
| `scoreCommercialValue(Qualification $q, Product $p): int` | 0–20, per Section 5.2.3 |
| `scoreDestination(Qualification $q, MatchResult $m): int` | 0–20, per Section 5.2.4 |
| `scoreBuyerTypeIntent(Qualification $q): int` | 0–15, per Section 5.2.5 |
| `scoreCompleteness(Qualification $q): int` | 0–10, per Section 5.2.6 |
| `band(int $total, array $components, GateSet $gates): Band` | Applies thresholds and the component admission minimums |
| `policyVersion(): string` | Immutable version string, incremented on any rule change |

**Depends on.** Catalog, Matcher output, `lead_assessments`, `feature_flags` (for shadow routing), Auditing.

**Invariant.** `band()` is pure. It receives the total, the components and the gates, and returns a band. There is no code path in which a model output, an environment variable or a request parameter reaches it.

### 3.7 Ranker

**Responsibility.** Produce the ordering key for the inbox and the ordering key for the review queue. Pure ordering, no scoring and no persistence decisions. Ordering is band first, then total score descending, then arrival time ascending, then an id tiebreak so the order is total and stable.

**Public methods.**

| Method | Contract |
|---|---|
| `sortKey(Assessment $assessment, Lead $lead): SortKey` | Computed integer tuple |
| `order(iterable $leads): array` | Stable sort inside one week |
| `reviewQueue(iterable $leads): array` | Leads in `review` band plus leads with `multiple_product_candidates` |
| `bandRank(Band $band): int` | `act_now` 6, `good` 5, `review` 4, `low` 3, `skip` 2, `hard_reject` 0 |

**Depends on.** Policy output only. No database access.

### 3.8 Allowance

**Responsibility.** The weekly quota ledger and the atomic allocation. Owns `allowance_weeks` and `allowance_allocations`. Computes the derived unit cost and the waste. Closes the week. This is the module that replaced the entire financial control layer of PRD v1.0 (PRD Amendment Section 6).

**Public methods.**

| Method | Contract |
|---|---|
| `weekFor(CarbonImmutable $at): Week` | The ISO week containing the instant, in Asia/Kolkata; creates the row if absent |
| `current(): WeekView` | Current week plus utilisation, waste units and waste cost |
| `allocate(Lead $lead, string $correlationId): Allocation` | **The only writer.** Opens a transaction, locks the week row `FOR UPDATE`, checks `allocated_count < quota_total`, inserts the allocation and increments the counter. Throws `AllowanceExhausted` when full |
| `release(Allocation $allocation, string $reason): void` | Reverses an allocation that failed downstream, with an audit record. Used only inside the ingest transaction |
| `closeWeek(CarbonImmutable $weekStart): WeekSummary` | Sets `expired_count`, freezes derived figures, writes an audit event, marks the week closed |
| `utilisation(Week $week): float` | `contacted_count / quota_total` |
| `waste(Week $week): Waste` | Units and `unit_cost_minor`-derived cost |
| `unitCost(Week $week): Money` | `round(weekly_fee_minor / 75)` |
| `history(int $weeks): array` | Trailing week views |

**Depends on.** `allowance_weeks`, `allowance_allocations`, `leads`, Settings, Auditing.

**Concurrency contract.** Two workers allocating against the same full week must result in one success and one `AllowanceExhausted`, never two successes. The uniqueness constraint on `allowance_allocations (source_id, external_lead_id)` is the second line of defence behind the row lock.

### 3.9 Crm

**Responsibility.** Human-side state. Assignment, status, the contact log, and the follow-up task engine. Owns `crm_activities`, `crm_tasks` and `follow_ups`. This module is the only writer of `leads.first_contact_at` and `leads.owner_id`, which is what makes the arrival-to-contact KPI trustworthy.

**Public methods.**

| Method | Contract |
|---|---|
| `assign(Lead $lead, User $owner, string $reason): void` | Sets the owner and writes an audit event |
| `logContact(Lead $lead, ActivityType $type, Channel $channel, string $description, User $actor): Activity` | Idempotent on `(lead_id, actor_id, channel, occurred_at)`; sets `first_contact_at` on the first contact |
| `setStatus(Lead $lead, LeadStatus $status, User $actor, ?string $note): void` | Validates the transition, records it |
| `createTask(Lead $lead, TaskDraft $draft): CrmTask` | Due date derived from band and inquiry |
| `snoozeTask(CrmTask $task, CarbonImmutable $until, User $actor): void` | Records the reason |
| `completeTask(CrmTask $task, User $actor, string $result): void` | Requires an actor; there is no automatic completion |
| `openTasksFor(User $owner): Collection` | Ordered by due date |
| `overdueTasks(): Collection` | Used by the daily reminder |

**Depends on.** `leads`, `crm_activities`, `crm_tasks`, `follow_ups`, Auth, Auditing, Notifier.

### 3.10 Drafter

**Responsibility.** Generate the three outreach artifacts for an eligible lead — WhatsApp message, email subject and body, phone opener — from versioned templates anchored to the matched SKU and the buyer's stated facts. Run the fact check. Owns `drafts`. Hosts the AI provider abstraction's *consumers*, but the provider clients themselves live in the supporting AI kernel (Section 3.15).

**Public methods.**

| Method | Contract |
|---|---|
| `draft(Lead $lead, Assessment $assessment, MatchResult $match): DraftSet` | Three drafts plus a per-draft fact-check result |
| `regenerate(Draft $draft, User $actor): Draft` | Rebuilds from current templates and SKU; old draft is superseded, not overwritten |
| `factCheck(Draft $draft, Lead $lead, Product $product): FactCheckResult` | Every factual claim must trace to the inquiry or the SKU record |
| `approve(Draft $draft, User $actor): void` | Records human approval |
| `markSent(Draft $draft, User $actor, Channel $channel, ?string $channelRef): void` | Records that a human sent it; **does not transmit** |
| `templates(): array` | Template identifiers and current versions |

**Depends on.** Catalog, Policy output, `drafts`, `TemplateEngine`, AI kernel (optional, flag-gated), Auditing.

**Invariant.** There is no method on this module that performs an outbound network call to WhatsApp, SMTP or a telephony provider. `markSent` records an event. Sending is the operator's act in their own client.

### 3.11 Auditing

**Responsibility.** The append-only record of who did what, when, to which entity, with what result and under which correlation id, without secrets. Also provides the redaction filter used by the logging stack.

**Public methods.**

| Method | Contract |
|---|---|
| `record(AuditEvent $event): void` | Appends; never updates |
| `forEntity(string $type, int $id): Collection` | Entity history, newest first |
| `forActor(User $actor, Period $period): Collection` | Actor history |
| `forCorrelation(string $correlationId): Collection` | End-to-end trace |
| `redact(array $context): array` | Strips phone, email, keys, session ids, `APP_KEY`-derived values |
| `policyChange(string $entity, array $before, array $after, User $actor, string $reason): void` | Critically-typed write used by every settings mutation |

**Depends on.** `audit_events`, the logging stack.

**Invariant.** `record` is inside the same transaction as the change it describes whenever the change is transactional. An audited change that rolls back leaves no audit row.

### 3.12 Notifier

**Responsibility.** Deliver operator-facing signals: a new week is ranked, a lead needs review, a task is overdue, ingestion failed, the AI cap was reached, a backup failed. Writes to the in-app notification table, the log, the Windows Event Log and email. Never messages a buyer.

**Public methods.**

| Method | Contract |
|---|---|
| `notify(Recipient $recipient, Notification $n): void` | Fan-out to the enabled channels. `Recipient` wraps either a single User or a Role |
| `notifyAll(Notification $n): void` | All active users in a role |
| `digest(User $recipient): DailyDigest` | The 08:00 summary: new leads, overdue tasks, unlogged contacts |
| `alert(AlertLevel $level, string $code, array $context): void` | Operational alert path, independent of user preferences |
| `health(): HealthStatus` | Whether each channel is currently deliverable |

**Depends on.** `users`, `notifications`, the mail transport, the Windows Event Log writer.

**Degradation.** If email fails, the in-app and log channels still fire. Alerts are never allowed to depend on a single channel.

### 3.13 Analytics

**Responsibility.** Compute the seven KPIs of PRD Amendment Section 18 and the supporting metrics, reproducibly, from stored records. Read-only. No module writes through Analytics.

**Public methods.**

| Method | Contract |
|---|---|
| `summary(Period $period): Summary` | Dashboard aggregate |
| `allowanceSeries(int $weeks): array` | Utilisation and waste per week |
| `wasteLeads(Week $week): Collection` | The untouched leads and what they cost |
| `triageAgreement(Period $period): AgreementReport` | System band vs operator decision |
| `arrivalToContact(Period $period): Distribution` | Median, p90, count of measurables |
| `funnel(Period $period): Funnel` | Contacted, replied, quoted, ordered — labelled as incomplete where outcomes are not recorded |
| `aiSpend(Period $period): SpendReport` | Per provider, per phase, against cap |

**Depends on.** Every read model it needs; no writes.

**Invariant.** Every number on the dashboard must be recomputable by calling `Analytics` against the same period. If the dashboard shows a number the Analytics module cannot reproduce, the dashboard is wrong.

### 3.14 Connector

**Responsibility.** Acquire raw leads from a source and report source health. Hosts the adapter registry and the ingest run ledger. Owns `lead_sources` and `source_runs`. Does not normalise, score or allocate.

**Public methods.**

| Method | Contract |
|---|---|
| `resolve(string $sourceKey): LeadSourceAdapter` | Returns the configured adapter |
| `pull(string $sourceKey, Window $window): LeadBatch` | Delegates to the adapter; records a `source_runs` row |
| `fetchDetails(string $sourceKey, string $externalId): RawLead` | Delegated detail fetch |
| `health(string $sourceKey): HealthStatus` | Delegated health, plus last successful run |
| `healthAll(): array` | Every configured source, for the dashboard |
| `register(LeadSourceAdapter $adapter): void` | Binds an adapter at boot |
| `reconcile(SourceRun $run, int $portalCount): Reconciliation` | Records the count difference against the seller dashboard |

**Depends on.** `lead_sources`, `source_runs`, `Filesystem` (for the watch folder), the adapter implementations in Section 4.

### 3.15 Supporting components (not modules)

These are kernel services. They are not part of the fourteen-module map and no business rule lives in them.

| Component | Responsibility | Why it is separate |
|---|---|---|
| `Ai\ProviderRegistry` | Resolve the active provider from four implementations: `GeminiProvider`, `AnthropicProvider`, `OllamaProvider`, `NullProvider`. Enforce the monthly cap and the per-lead call ceiling before any request is made | Mandated by PRD Amendment FR-23 and decision D13: the abstraction ships in v1 even though v1 makes no model call |
| `Ai\CostMeter` | Write an `ai_runs` row per call with tokens, cost, latency and status; increment `ai_budget_periods` atomically | The cap must be enforced from stored data, not from an in-memory counter |
| `Ai\CircuitBreaker` | Open for 15 minutes after three consecutive validation failures or provider errors | Prevents a degraded provider from slowing every lead (PRD Amendment Section 5.5.3) |
| `Evaluation\Harness` | Run a dataset version through the pipeline, compute the metrics, compare against thresholds, write `evaluation_runs` | The promotion gate in PRD Amendment Section 2.4.1 needs a repeatable, stored result |
| `Flags\FlagStore` | Read and write `feature_flags`, including shadow state and evidence reference | Every phase must be independently disableable within one request |
| `Scheduling\Kernel` | Register the scheduled jobs listed in Section 7 | Keeps cadence in one reviewable place |
| `Pii\BlindIndex` | HMAC-SHA256 the normalised email and phone for search and dedupe | Lets search work without decrypting PII |

---

## 4. Data flow from source to inbox

### 4.1 The eleven stages

A lead moves through eleven pipeline stages. Each stage is a discrete, testable step with an explicit input and output. Stages 1 to 10 run inside the ingest job; stage 11 fans out.

| # | Stage | Module | Input | Output | Side effects |
|---|---|---|---|---|---|
| 1 | **pull** | Connector | Source key, window | `LeadBatch` of `RawLead` | `source_runs` row opened |
| 2 | **normalise** | Normaliser | `RawLead` | `NormalisedLead` | none (pure) |
| 3 | **validate** | Connector + Normaliser | `NormalisedLead` | `ValidatedLead` or `RejectedRecord` | `source_runs.records_rejected` |
| 4 | **dedupe** | Connector | `ValidatedLead` | `NewLead` or `DuplicateLead` | `source_runs.records_duplicate` |
| 5 | **match** | Matcher | `ProductPhrase` | `MatchResult` | `lead_matches` rows |
| 6 | **qualify** | Qualifier | `NormalisedLead` + `MatchResult` | `Qualification` | none |
| 7 | **policy** | Policy | `Qualification` + `MatchResult` + `LeadContext` | `Assessment` (band + gates) | `lead_assessments` row |
| 8 | **rank** | Ranker | `Assessment` + lead timestamps | `SortKey` | none |
| 9 | **allocate** | Allowance | Lead + week | `Allocation` or `AllowanceExhausted` | `allowance_allocations` row, `allowance_weeks.allocated_count` +1 |
| 10 | **persist** | Connector + Crm | Everything above | `Lead` row committed | `leads` row, audit event |
| 11 | **notify** | Notifier | Committed lead | Notification | `notifications` row, optional email |

**Transaction boundary.** Stages 5 to 10 for a single lead execute inside one database transaction. Either the lead, its matches, its assessment and its allocation all commit, or none of them do. Stage 11 runs after commit; a notification failure never rolls back a lead.

**Ordering note.** Stage 8 (rank) computes a pure sort key that does not depend on the allocation. Stage 9 binds the lead to a week and consumes a unit. This ordering is deliberate: ranking is a function of the assessment, while allocation is a function of the quota. Keeping them separate means a lead can be fully assessed and ranked even in a week where the quota is already full, which is what makes the `quota_exceeded` report informative rather than empty.

### 4.2 Pipeline state machine (machine-driven)

Stored in `leads.status`. Only the pipeline writes these states. Each transition is written in the same transaction as the stage that caused it.

```
                      +-----------+
                      | received  |
                      +-----+-----+
                            | normalise
                            v
                      +-----------+
                      | normalised|
                      +-----+-----+
                            | validate
                 +----------+----------+
                 |                     |
                 v                     v
          +-----------+        +--------------+
          | validated |        | rejected     |  (terminal)
          +-----+-----+        +--------------+
                | dedupe
       +--------+--------+
       |                 |
       v                 v
  +-----------+   +-------------+
  | deduped   |   | duplicate   |  (terminal, excluded)
  +-----+-----+   +-------------+
        | match
        v
  +-----------+
  | matched   |
  +-----+-----+
        | qualify
        v
  +-----------+
  | qualified |
  +-----+-----+
        | policy
   +----+----------------+
   |                     |
   v                     v
+----------------+  +--------------+
| policy_passed  |  | hard_rejected|  (terminal, excluded)
+-------+--------+  +--------------+
        | rank
        v
  +-----------+
  | ranked    |
  +-----+-----+
        | allocate
   +----+----------------+
   |                     |
   v                     v
+-------------+   +------------------+
| allocated   |   | quota_exceeded   |  (terminal, excluded)
+------+------+   +------------------+
       | persist
       v
  +-----------+
  | persisted |
  +-----+-----+
        | notify
        v
  +-----------+
  | ready     |   <-- the lead is now in the inbox
  +-----------+
```

**Terminal states** are `rejected`, `duplicate`, `hard_rejected` and `quota_exceeded`. A lead in a terminal state is never deleted and never re-enters the pipeline for the same source identifier. It may be manually released by an authorised user, which writes a new state and an audit event, and which is always visible in the agreement metric.

**Failure handling.** A stage that throws leaves the lead in the state it entered that stage with. The job retries with exponential backoff (1 min, 5 min, 30 min), maximum 3 attempts, after which a `stage_failed` event is written and the lead is surfaced in the health panel. There is no partial commit.

### 4.3 Engagement state machine (human-driven)

Stored in `leads.status` after `ready`, and owned by the Crm module. The pipeline never drives these transitions.

```
 ready
   |
   | logContact (first contact)
   v
 contacted
   |
   +--> replied --> quoted --> negotiation --> ordered
   |                 |            |
   |                 +-->         +--> lost
   |
   +--> no_response --> (task due) --> follow_up_due --> contacted
   |
   +--> disqualified  (hard gate confirmed by a human, or buyer said no)
   |
   +--> archived      (week closed and untouched; captured in the waste report)
```

`archived` is the state that makes the waste metric auditable: the set of leads in `archived` at week close is exactly the set counted in `expired_count`. `ordered` and `lost` exist so that outcomes can be imported later (open question OQ-06) without a schema change, but v1 does not report on them and does not claim conversion insight.

### 4.4 Where the AI sits

In v1, nowhere. The path from stage 1 to stage 11 is entirely deterministic.

In v1.1, the AI kernel can be invoked at exactly two points, both flag-gated and both shadow-only until promoted:

1. Between stage 2 and stage 5, to propose `normalised_fields` when the deterministic normaliser reports low confidence.
2. At stage 5, to propose `match_candidates` when the matcher returns more than one candidate at the top score.

At both points the model returns an envelope that the Policy module may read as *additional evidence* but cannot read as a *decision*. The deterministic result is computed regardless and is the one that ships. If the model is unavailable, slow, over budget or returns an invalid schema, the deterministic result is used and the shadow comparison records a failure.

---

## 5. Connector abstraction

### 5.1 The interface

```php
interface LeadSourceAdapter
{
    public function key(): string;

    public function pull(Window $window): LeadBatch;

    public function fetchDetails(string $externalId): RawLead;

    public function health(): HealthStatus;
}
```

| Method | Contract | Tested by |
|---|---|---|
| `key()` | Stable identifier, matches `lead_sources.name` | Config test |
| `pull(Window $window)` | Return every lead that became available inside the window. Must be safe to call twice over the same window; the second call must return the same set, not a delta. | Contract test against recorded fixtures |
| `fetchDetails(string $externalId)` | Return the complete raw record, including the untouched source payload. Must not mutate state. | Contract test |
| `health()` | `ok`, `degraded` or `unavailable`, with a reason and the last success timestamp. Must not write. | Unit test with injected failures |

**Non-negotiable properties.** `pull` is read-only with respect to the source. No adapter writes to the portal. No adapter normalises. No adapter allocates. No adapter holds a credential in code; credentials come from the environment through a config resolver.

### 5.2 The three adapters

#### 5.2.1 API adapter

| Property | Detail |
|---|---|
| Mechanism | HTTP pull against an authorised endpoint |
| Availability | Only after P0 confirms that an authorised machine-readable feed exists for our seller account and that the account terms permit programmatic retrieval. **Must be verified in P0.** |
| Authentication | Method, header shape, token lifetime and refresh behaviour are **unknown until P0** |
| Pagination | Page size, cursor semantics and rate limits are **unknown until P0** |
| Fields | Whether buyer phone, buyer email, full inquiry text and arrival timestamp are present is **unknown until P0** |
| Failure modes | 401/403 credential failure, 429 rate limit, 5xx, malformed JSON, partial payload, empty window |
| Retry | Exponential backoff, maximum 3 attempts, no retry on 4xx other than 429 |
| Fallback | On sustained failure, the source is marked `degraded` and the CSV adapter becomes the primary path for that week |

The adapter is written against the interface, not against a guessed contract. If P0 closes negatively, the class is not implemented at all and the registry resolves CSV as primary.

#### 5.2.2 CSV export adapter

| Property | Detail |
|---|---|
| Mechanism | A watch folder and a manual upload, sharing one parser |
| Watch folder | `D:\PharmaLead\drop\` — the operator exports from the seller dashboard and saves the file here |
| Manual upload | The same parser invoked from `POST /api/leads/import` with a multipart file |
| Detection | Polled every 60 seconds by the Laravel scheduler; a file is claimed by atomic rename to `*.processing` before parsing |
| Format | A column mapping defined in `config/leadsources/csv.php`, versioned and changeable without a deploy. The actual column set is **recorded in P0** from a real export |
| Encoding | UTF-8 with a BOM tolerance; Windows-1252 accepted and converted |
| Deduplication | Natural key is `(source_id, external_lead_id)`; if the export has no stable identifier, a deterministic synthetic key is `sha256(source_id + normalised_buyer_email_or_phone + normalised_inquiry_text + arrival_date)` |
| Idempotency | Re-importing the same file produces zero new leads. Verified by test |
| Quarantine | A file that fails header validation is moved to `D:\PharmaLead\drop\rejected\` with a sidecar reason file, and an alert fires |
| Archive | A successfully parsed file is moved to `D:\PharmaLead\drop\processed\YYYY-MM-DD\` |

The CSV adapter is the production default. It is built first, hardened first, and is never removed even if the API adapter is later enabled.

#### 5.2.3 Manual entry adapter

| Property | Detail |
|---|---|
| Mechanism | An operator form at `POST /api/leads/import` with `mode=manual` |
| Fields | External id (auto-generated if absent), inquiry text, buyer name, company, email, phone, destination, quantity, quantity unit, arrival timestamp |
| Provenance | `source_id` set to the manual source; `created_by` recorded; `origin = manual` stored on the lead |
| Validation | Same validation as any other adapter, with the addition that the inquiry text must be at least 20 characters |
| Idempotency | Manual entries are idempotent on the submitted external id; a duplicate submission returns the existing lead rather than creating a second |
| Use | Overflow, first-run data, and the path when both automated routes are down |

### 5.3 Adapter registry and health

Adapters are bound in `ConnectorServiceProvider` and resolved by key. `healthAll()` merges the adapter's own health with the last `source_runs` outcome, so the dashboard can distinguish "the adapter says it is fine" from "the adapter has not produced a lead in six days". Both signals are shown.

### 5.4 Reconciliation with the seller dashboard

Every pull records `records_received`, `records_ingested`, `records_duplicate`, `records_rejected` and `records_excluded`. Once per pull, optionally with an operator-supplied portal count, the Connector writes a `Reconciliation` row with the difference. A non-zero difference that persists for two consecutive runs raises an alert. The reconciliation is a monitoring signal, not a correctness gate: the system cannot know what the portal showed, it can only record what it was told.

---

## 6. Failure and degradation policy

Each row states the behaviour, the user-visible consequence, the automatic recovery, and the alert. No row permits the system to silently mark leads as ready, to raise a band, or to clear a gate.

| Failure | Pipeline behaviour | Inbox behaviour | Recovery | Alert |
|---|---|---|---|---|
| **Portal error** (401/403) | Pull aborts before any write; the batch is not partially ingested | Existing inbox unchanged and fully operable; a stale-data banner shows the last successful pull | Retry with backoff; source marked `degraded`; CSV path becomes primary after 3 consecutive failures | Yes, immediate: "source authentication failed" |
| **Portal error** (5xx / timeout / malformed) | Pull aborts; `source_runs` records the reason | Same as above | Retry 3 times with backoff; then daily retry at the next scheduled slot | Yes, on the third consecutive failure |
| **CSV file malformed** | File quarantined; zero leads ingested | Unchanged | Operator fixes the export and re-drops; no automatic retry of a quarantined file | Yes: "import rejected, see quarantine reason" |
| **LLM error** (timeout, 5xx, invalid schema) | Model output discarded; deterministic assessment stands and is the one used | Unchanged; the lead is fully triaged deterministically | `Ai\CircuitBreaker` opens for 15 minutes after 3 consecutive failures; one retry per lead before circuit opening | Yes, if the circuit opens |
| **AI budget cap reached** | No further model calls are made; every remaining lead in the batch and every lead until the next month processes deterministically | Unchanged; a banner states that model assistance is off until the period resets | Automatic at the start of the next calendar month; an Administrator may raise the cap with re-authentication | Yes, at 80 per cent of the cap and again at 100 per cent |
| **LLM returns a gate override attempt** | Output discarded; the attempt is recorded as a security event | Unchanged | None needed; the gate is structural | Yes: "model attempted to influence a gate" |
| **Database unavailable** | Ingest job fails at the transaction boundary; no partial writes; the queue job is released back for retry | The SPA shows an explicit database-unavailable error page, not a blank inbox | Queue retries with backoff; MySQL service recovery; health probe detects the return and clears the page | Yes, immediate |
| **Database locked / deadlock** | Transaction rolls back; the job retries | Unchanged | Retry up to 3 times with jitter | Yes, on the third failure |
| **Duplicate weekly batch** | The uniqueness constraint rejects every duplicate; zero new leads, zero new allocations; `records_duplicate` equals `records_received` | Unchanged; no visible change | None required | Informational only: "batch already ingested" |
| **Duplicate batch with one new lead** | The new lead is ingested, the rest are duplicates | One new lead appears | None | Informational |
| **Allowance exhausted mid-batch** | Remaining leads persist as `quota_exceeded`, are not allocated, are not drafted, are excluded from the inbox | No new actionable leads appear | None; the week is full by contract | Yes: "batch exceeded weekly allowance by N leads" — this indicates a contract or delivery problem |
| **Queue worker down** | Jobs accumulate in `jobs` | Inbox shows committed leads; nothing new is scored | Task Scheduler restarts the worker; on restart the queue drains in order | Yes, if the worker uptime is below 5 minutes in the last 15 |
| **Scheduler down** | No pulls, no week close, no purge, no backup | Unchanged, but data goes stale | Task Scheduler restarts; the health probe reports the last run per task | Yes, if any scheduled task is overdue by more than 2 intervals |
| **Host reboot / power loss** | In-flight transactions roll back; committed data is intact | No access until the host is back | Services start automatically; missed Task Scheduler runs are re-triggered; catch-up pull runs once | Yes, on boot with the duration of the outage |
| **Disk nearly full** | Backups fail; MySQL may refuse writes | Inbox shows a storage error | Operator must free space; the purge job runs nightly | Yes, at 15 per cent free |
| **Model provider revoked / key invalid** | Same as LLM error | Unchanged | Switch provider or fall back to Null with re-authentication | Yes |
| **Encryption key missing** | Application refuses to boot | Hard error page | Restore the key from the secure offline copy | Yes, critical |

### 6.1 Degradation ordering

When several components fail at once, the system degrades in this order and stops degrading at the last level that still works:

1. Model assistance off — deterministic triage continues.
2. Notification channels down — in-app notifications continue.
3. Portal unavailable — last week's inbox remains readable and actionable.
4. Queue worker down — already-committed leads remain readable and contactable.
5. Database down — nothing works; the system states this plainly rather than serving stale cached screens.

The one thing that never degrades is the hard-gate evaluation. If the Policy module cannot run, leads are not allocated and do not enter the inbox as ready.

---

## 7. Scheduling and background work

### 7.1 Cadence

| Job | Cadence | Trigger | Why this cadence | Idempotent |
|---|---|---|---|---|
| `leads:pull` | 08:00 and 16:00 daily | Windows Task Scheduler | Two pulls catch a morning and an afternoon delivery without polling a portal that may rate-limit. Delivery timing is verified in P0 | Yes |
| CSV watch-folder scan | Every 60 seconds | Laravel scheduler | Low cost, fast operator feedback after a manual drop | Yes |
| `leads:process` | On pull, queued | Queue worker | Processing is decoupled from retrieval so a slow score never blocks the next pull | Yes |
| `drafts:generate` | Queued per allocated lead | Queue worker | Keeps ingest fast; drafts can lag by seconds without harming triage | Yes |
| `allowance:close-week` | Mondays 00:05 | Windows Task Scheduler | Five minutes after the IST week boundary, after any late Monday-midnight delivery has landed | Yes, guarded by `status` |
| `crm:remind-overdue` | 09:00 daily | Laravel scheduler | One reminder a day is enough; more trains the team to ignore it | Yes |
| `crm:remind-unlogged` | 17:30 daily | Laravel scheduler | Catches contacts made but not logged before the day ends | Yes |
| `retention:purge` | 03:00 daily | Windows Task Scheduler | Outside working hours; touches encrypted PII in bulk | Yes |
| `backup:run` | 02:00 daily | Windows Task Scheduler | After the purge window, before the working day | Yes |
| `backup:verify-restore` | Day 1 of each month, 04:00 | Windows Task Scheduler | Monthly proof that the backup is real; runs against a scratch schema | Yes |
| `system:health-check` | Every 5 minutes | Windows Task Scheduler | Feeds the dashboard health panel and the alerting path | Yes |
| `evaluation:run` | On demand and nightly in shadow-promotion weeks | Laravel scheduler and manual trigger | The harness is expensive relative to a normal job; nightly is enough | Yes |
| `ai:reset-budget` | Day 1 of each month, 00:10 | Laravel scheduler | Opens the new spend period | Yes |

### 7.2 Why Task Scheduler and the Laravel scheduler both exist

The Laravel scheduler is a PHP process. If PHP dies, the scheduler dies with it and nothing restarts it unless something outside PHP notices. Windows Task Scheduler is the outside observer. It owns the jobs whose absence would be invisible for a week — pulls, week close, backup, purge, health — and it re-triggers missed runs after a reboot, which the Laravel scheduler cannot do. The Laravel scheduler owns jobs whose absence is visible within a day and whose cadence needs application context: reminders, the watch-folder scan and the budget reset.

### 7.3 Queue topology

One queue, `default`, processed by one worker with `--tries=3 --backoff=60 --timeout=120 --sleep=3`. The volume is 75 leads per week plus three drafts each, roughly 300 jobs per week. One worker is enough and makes ordering predictable. The 20-worker concurrency gate in PRD Section 19.1 (G3) is a test-harness scenario, not a production configuration; it exists to prove that the allocation transaction is safe under the worst case the schema will ever see.

Failed jobs land in `failed_jobs` and are visible in the health panel. They are never silently discarded.

### 7.4 Week boundary handling

The week is defined once, in `Allowance\Week`, as the ISO week containing the instant, evaluated in `Asia/Kolkata`, starting Monday 00:00:00 and ending Sunday 23:59:59. Every module that needs a week asks this class; no module computes a week boundary independently. This is the single most important piece of date logic in the system, because the quota, the waste and the week-close job all depend on it agreeing with the contract.

---

## 8. Security boundary

### 8.1 Trust boundaries

```
  +----------------------------------------------------------+
  |  UNTRUSTED                                               |
  |    buyer inquiry text, buyer names, company names,       |
  |    any string that arrives from a portal or a CSV file   |
  +----------------------------+-----------------------------+
                               | validated, length-capped,
                               | schema-checked
                               v
  +----------------------------------------------------------+
  |  SEMI-TRUSTED                                            |
  |    authenticated operator session in a browser on the LAN|
  +----------------------------+-----------------------------+
                               | CSRF, session, permission
                               v
  +----------------------------------------------------------+
  |  TRUSTED                                                 |
  |    Laravel application, queue worker, scheduler          |
  |    MySQL on loopback                                     |
  |    .env on disk, readable only by the service account    |
  +----------------------------------------------------------+
                               |
                               v  (only if explicitly enabled)
  +----------------------------------------------------------+
  |  EXTERNAL, MINIMAL DATA                                  |
  |    AI provider receives redacted inquiry text only       |
  +----------------------------------------------------------+
```

### 8.2 Secrets

| Rule | Implementation |
|---|---|
| Secrets live only in `api/.env` | No secret in the database, in `settings`, in the SPA bundle, in a migration, or in a fixture |
| The SPA never sees a credential | Source configuration endpoints return `configured: true/false`, a masked hint and the last four characters of nothing. Never a URL with a key, never a token |
| Logs are redacted | `Auditing::redact()` runs in the log processor, not at the call site, so a forgotten call site cannot leak |
| `.env` is excluded from Git | Verified by `.gitignore` and by a pre-commit check |
| Rotation is a documented procedure | Changing a key means updating `.env`, restarting the worker, and recording the event. No hot reload |
| The encryption key is separate from `APP_KEY` | A dedicated `PII_ENCRYPTION_KEY` and a dedicated `PII_BLIND_INDEX_KEY` so that rotating the app key does not orphan the contact data |

### 8.3 Session and transport

| Setting | Value | Reason |
|---|---|---|
| `SESSION_DRIVER` | `database` | Worker and scheduler restarts must not log out the operator |
| `SESSION_LIFETIME` | 480 minutes | One working day |
| Inactivity timeout | 30 minutes on inbox, lead detail, settings and audit | Limits an unattended workstation |
| `SESSION_HTTP_ONLY` | `true` | No script access to the cookie |
| `SESSION_SAME_SITE` | `lax` | Blocks cross-site form posts while allowing LAN navigation |
| `SESSION_SECURE_COOKIE` | `false` while HTTP-on-LAN; `true` the day HTTPS is introduced | Tracked with open question OQ-10 |
| CSRF | Required on every mutating route | Laravel default, not disabled for API routes |
| CORS | Not enabled; same origin | There is no cross-origin consumer |
| Login rate limit | 5 attempts per minute per IP, 15-minute lockout | Brute-force resistance without locking out a typist |
| Re-authentication | Required within 5 minutes for allowance fee, country class, feature flags, provider and cap | These are the five settings that can change money or safety |

### 8.4 Buyer PII

| Control | Detail |
|---|---|
| Columns | `buyer_name_encrypted`, `buyer_email_encrypted`, `buyer_phone_encrypted`, `buyer_company_encrypted` |
| Cipher | Laravel encrypted cast, AES-256-CBC with the dedicated `PII_ENCRYPTION_KEY` |
| Search | `buyer_email_hash`, `buyer_phone_hash` — HMAC-SHA256 of the normalised value with `PII_BLIND_INDEX_KEY` |
| Decryption | Only inside a request that passed the `lead.contact.view` permission; every decryption writes an audit event with actor, lead id and correlation id |
| Logs | Phone and email never logged; inquiry text logged truncated and only at debug |
| Exports | Require `lead.contact.export`; the export itself is audit-logged with row count |
| AI egress | Contact fields are stripped and email/phone patterns are redacted before any request leaves the process |
| Retention | 180 days after last activity, then anonymised; inquiry text 365 days; assessments and audit 3 years |
| Backups | Contain encrypted columns; the backup drive is physically separate and access-controlled by the Windows account |

### 8.5 LAN boundary

| Control | Detail |
|---|---|
| Bind address | The LAN interface only, never `0.0.0.0` |
| Firewall | Inbound rule for port 8080, private profile only, scoped to `192.168.1.0/24` |
| Router | No port forward, no UPnP, no DMZ |
| MySQL | `bind-address = 127.0.0.1` |
| Remote access | If ever required, through the office VPN only, never by publishing the port. Tracked as open question OQ-10 |
| Host account | The web service, the worker and the scheduler run under a dedicated service account with read access to `api/` and read-write access only to `storage/` and the drop and backup directories |

---

## 9. Local deployment topology

### 9.1 Who reaches what

| Actor | Path | What they see |
|---|---|---|
| Operator | `http://pharmalead.local:8080` or `http://192.168.1.20:8080` from the office LAN | The full application |
| Sales executive | The same URL, role `sales_executive` | Assigned leads, drafts, contact logging, tasks. No settings, no allowance fee, no audit |
| Sales manager | The same URL, role `sales_manager` | Team views, allowance and waste, utilisation, agreement, exports |
| Administrator | The same URL, role `administrator` | Everything, including re-authenticated settings |

A hostname entry is added to the office router's local DNS, or to each workstation's `hosts` file, so the team does not have to remember an IP. If the host's LAN address is DHCP-assigned, reserve it on the router so the hostname does not drift.

### 9.1.1 Filesystem layout

Everything this system owns lives under one install root, outside any sync folder such as OneDrive. A sync client must never hold the MySQL data directory or the `.env`.

| Path | Contents | Access |
|---|---|---|
| `C:\PharmaLead\api` | Laravel application, `.env`, `storage` | Service account read-write; no sync |
| `C:\PharmaLead\api\public\spa` | Built React SPA, served as the document root's SPA entry | Read-only |
| `D:\PharmaLead\drop` | CSV watch folder: `incoming`, `processed\YYYY-MM-DD`, `rejected` | Operator write, service account read-write |
| `C:\PharmaLead\backups` | Daily and weekly `mysqldump` output, 14 daily + 8 weekly | Service account write |
| `C:\PharmaLead\ops` | `backup.ps1`, restore scripts, restore-drill log | Service account write |
| `D:\PharmaLead\backups\weekly` | Mirror of the weekly backup set | Service account write |
| `D:\PharmaLead\mysql` (or the configured data path) | MySQL 8 data directory | MySQL service account only |

### 9.2 If the office PC is off

| Duration | Consequence | Behaviour on restart |
|---|---|---|
| Under 5 minutes | Nothing; no scheduled job is due | Services start; no catch-up needed |
| 5 minutes to 12 hours | A pull slot, a reminder or the health probe is missed | Task Scheduler re-triggers the missed run at boot; one catch-up pull runs immediately |
| 12 to 72 hours | A full working day of triage is lost; leads pile up in the portal, not in the system | Catch-up pull runs at boot over the full missed window; leads are ingested and scored; the inbox is ranked the moment ingest completes; the operator sees a "catch-up complete" notice with counts |
| Over 72 hours | The missed week's allowance is on the far side of a week boundary and may already have expired | The system ingests everything it can from the window, allocates against the week in which each lead arrived, and reports any week that closed under-utilised. It does **not** re-open a closed week or move allowance between weeks |

**Catch-up is bounded by the week.** If the host was down across a Monday boundary, the leads that arrived in the previous week are allocated against that previous week if it is still open, and reported as waste if it closed. Moving allowance between weeks is explicitly out of scope and would breach the contract.

**Boot sequence.** Windows starts MySQL, then the web server, then the queue worker and the scheduler via Task Scheduler. A boot script waits for MySQL to accept connections, runs `php artisan migrate --force`, clears the config cache, verifies the health endpoint, and only then releases the worker. A migration failure stops the worker and raises an alert rather than serving an application against a half-migrated schema.

### 9.3 What is not deployed

No Docker. No Kubernetes. No cloud database. No CDN. No Redis requirement. No message broker. No TLS terminator in v1. Every one of these would add a moving part to a single-host system operated by one person, and none of them addresses a constraint the business actually has.

---

## 10. Extension points

The four extensions below are the ones the business has named. Each is specified so that adding it does not require editing v1 business logic. The rule for all four: extend at the boundary, never inside `Policy`, `Allowance` or `Drafter`.

### 10.1 A second portal

| Step | Change | Files touched |
|---|---|---|
| 1 | Implement `LeadSourceAdapter` in a new namespace, e.g. `Connector\TradeIndia\TradeIndiaAdapter` | New adapter class only |
| 2 | Register it in `ConnectorServiceProvider` | One binding |
| 3 | Add a `lead_sources` row with `connector_type = tradeindia` | Data, not code |
| 4 | Add a column mapping under `config/leadsources/` if the format differs | Configuration |
| 5 | Write the adapter's contract tests against recorded fixtures | Tests |

`Policy`, `Allowance`, `Ranker`, `Crm` and `Drafter` are untouched, because they operate on the normalised internal model, not on the source. The only shared surface a new portal touches is the `RawLead` shape, which is deliberately generic: external id, inquiry text, buyer fields, destination, quantity, arrival timestamp, source payload. A portal whose feed cannot be expressed in that shape is a signal to extend `RawLead` with an optional field, not to fork the pipeline.

**What the second portal must not do.** It must not introduce a second quota model. If the second portal has its own allowance, it gets its own `allowance_weeks` dimension keyed by `(source_id, week_start)`, which is a schema migration but not a business-logic change.

### 10.2 An outcome-analytics module

| Step | Change | Why it is additive |
|---|---|---|
| 1 | Add `lead_outcomes` (lead_id, stage, value_minor, currency, occurred_at, source, confidence) | New table |
| 2 | Add an importer for quotes and orders (open question OQ-06) behind an adapter | New adapter on the same pattern as `LeadSourceAdapter` |
| 3 | Add `Outcome\Analytics` consuming the new table | New read-only module |
| 4 | Add outcome labels to the evaluation dataset | Data |
| 5 | Only after 500 labelled leads, add a learned ranker behind `flag.learned_ranker` | New strategy class implementing the existing `Ranker` interface |

`Policy` does not change. The learned ranker would sit where `Ranker` sits — ordering only — until the business is ready for it to influence a band, which would itself be a PRD amendment and a calibration gate, not a code change.

**What blocks this today.** There are no outcome labels in v1 (decision D3). The schema above is designed so that the labels can arrive later without a rewrite, but no v1 code path may read a column that is always null.

### 10.3 A per-lead purchasing capability

This is the one extension that changes the product's economics, and it is deliberately the hardest to bolt on. It is not an adapter. It is a return to a transaction model the system was removed from.

| Step | Change | Gate |
|---|---|---|
| 1 | The business signs a contract with a per-lead price | Commercial, not technical |
| 2 | A new PRD amendment reinstates a purchase policy, a spend ledger, atomic reservations, idempotency against the portal and reconciliation | Signed review; the whole of PRD Amendment Section 6 is replaced |
| 3 | `Policy` gains a *purchase eligibility* output alongside the *fit* assessment; the two are separate and neither can override the other | New gate set |
| 4 | A `Purchase` module is added with the same single-writer discipline as `Allowance` | New module |
| 5 | `Allowance` is extended to a `(source_id)` dimension, or retired for that source | Migration |

**Why it is additive-ish.** `Allowance` already owns "how many units may this week consume" and enforces it with a locked row. A purchase ledger is the same shape with a monetary unit instead of a lead unit, so the concurrency pattern, the append-only discipline and the week-close job all transfer. What does not transfer is the decision: `Policy` must learn a *spend* decision it does not currently make, and that is a genuine product change, not a wiring change.

### 10.4 A second AI provider, or a local model

Already accommodated. `Ai\ProviderRegistry` resolves a provider by key from four implementations. Adding a fifth means adding one class implementing `Ai\Provider` and one registry entry. The cap, the circuit breaker, the cost meter and the shadow comparison are all provider-agnostic and are not touched.

### 10.5 What must not be extended

| Anti-extension | Why |
|---|---|
| A second scoring engine | Two score sources make the agreement metric meaningless and the policy version ambiguous |
| Automatic sending from the server | Breaks the human-act invariant (decision D5) and removes the record of who sent what |
| An extra hard gate added in a controller | Gates must live in `Policy\GateEvaluator` so that the gate precision metric covers them |
| A quota bypass for "urgent" leads | Any bypass makes the utilisation and waste metrics untrustworthy and breaches the allowance contract |
| A per-source scoring weight set | The band comparison across sources becomes meaningless; if sources differ, they differ in the catalogue and the country table, not in the weights |
