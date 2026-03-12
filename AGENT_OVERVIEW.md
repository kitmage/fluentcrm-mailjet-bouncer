# AGENT OVERVIEW

## Project Goal
Build a production-oriented WordPress plugin that syncs Mailjet bounce and suppression events into FluentCRM contact status updates, with webhook-first processing and polling reconciliation fallback.

## Phase Plan

### Phase 1 — Discovery & Architecture
- Review `README.md` requirements and convert them into implementation slices.
- Define plugin structure, main classes, hooks, and data model.
- Define event schema normalization, dedupe key strategy, and mapping rules.
- Output: architecture notes, execution checklist, and first log entry.

### Phase 2 — Plugin Bootstrap & Data Layer
- Create plugin bootstrap file and autoloading of feature classes.
- Implement activation routine that creates custom database tables with indexes.
- Implement settings storage defaults and option registration.
- Output: installable plugin skeleton with persistence model.

### Phase 3 — Inbound Event Ingestion
- Register REST endpoint: `POST /wp-json/fcrm-mailjet/v1/events`.
- Implement secret validation, strict JSON parsing, and fast acknowledgment path.
- Persist raw normalized events with unique dedupe hash.
- Output: secure idempotent ingestion pipeline.

### Phase 4 — Event Processing & FluentCRM Sync
- Resolve contact and campaign identifiers from payload and headers.
- Apply mapping rules (hard/soft bounce, blocked, spam, unsub).
- Update FluentCRM contact status with internal APIs where available.
- Persist processing outcome and contact-meta counters.
- Output: reliable status sync logic.

### Phase 5 — Admin UX & Operations
- Build settings/admin page with controls and read-only health metrics.
- Add event replay/testing utilities and logging controls.
- Add nightly WP-Cron reconciliation scaffold and reporting summary.
- Output: operator-ready management features.

### Phase 6 — Validation, Hardening & Release
- Run lint/validation checks and add basic docs for setup.
- Verify graceful behavior if FluentCRM is absent.
- Finalize change log and release notes.
- Output: stable, documented plugin ready for deployment.
