Give your developer this spec.

Build a small WordPress plugin that connects **Mailjet bounce events** to **FluentCRM contact statuses** so FluentCRM stops emailing bad addresses and can track campaign-level bounce data. FluentCRM already supports statuses like `bounced`, `complained`, and `unsubscribed`, and contacts in `bounced` status should not receive further emails. Mailjet can provide the needed delivery events through its Event Tracking API, including `bounce`, `blocked`, `spam`, and `unsub`, and the payload includes Mailjet IDs plus `customcampaign` when `X-Mailjet-Campaign` is set. ([FluentCRM][1])

## Goal

Implement a **Mailjet → WordPress webhook → FluentCRM sync** with a **polling fallback**.

The webhook path is the primary solution because Mailjet can push events in near real time. Use polling only as a reconciliation job or fallback. Mailjet’s docs also note that hard-bounced addresses should be cleaned up and that bounce rate should stay below 5%, so syncing these statuses back into FluentCRM is important for deliverability. ([documentation.mailjet.com][2])

## Deliverables

Create a custom plugin, for example `fluentcrm-mailjet-bounce-sync`, with these parts:

### 1) Outbound mail tagging

Add Mailjet SMTP headers to FluentCRM emails before they leave WordPress.

Use WordPress mail hooks to inject custom headers. WordPress supports custom mail headers through `wp_mail()` and customization via `phpmailer_init`.

Attach these headers to every FluentCRM campaign email:

* `X-Mailjet-Campaign: fcrm_campaign_{campaign_id}`
* `X-MJ-CustomID: fcrm_contact_{contact_id}` or a more granular per-send ID if available
* `X-MJ-EventPayload: {"fcrm_campaign_id":123,"fcrm_contact_id":456,"source":"fluentcrm"}`

Mailjet exposes `customcampaign` in webhook events as the value of `X-Mailjet-Campaign`, which makes campaign attribution practical. ([documentation.mailjet.com][2])

Implementation note for the developer:
Use the most reliable hook available in your stack to detect that the outgoing email originated from FluentCRM. If FluentCRM exposes campaign/send context internally, use that. Otherwise fall back to a pattern based on subject, mail metadata, or the execution context around campaign sending.

### 2) Webhook endpoint

Register a custom WordPress REST endpoint, for example:

`POST /wp-json/fcrm-mailjet/v1/events`

This endpoint must:

* accept Mailjet event POSTs
* validate a shared secret
* log the raw payload
* process events idempotently
* return HTTP 200 quickly

Mailjet retries non-200 responses, so the endpoint should acknowledge success fast and avoid slow processing in-line. ([documentation.mailjet.com][2])

### 3) Event mapping rules

Map Mailjet events to FluentCRM statuses like this:

* `bounce` with `hard_bounce=true` → set FluentCRM contact status to `bounced`
* `bounce` with `hard_bounce=false` → do not immediately mark bounced; increment a soft-bounce counter and only escalate after a threshold
* `blocked` → usually suppress; default behavior should be mark as `bounced` when the error indicates a durable problem like preblocked/repeated bounce history
* `spam` → set status to `complained`
* `unsub` → set status to `unsubscribed`

These are aligned with Mailjet’s event fields and FluentCRM’s supported statuses. FluentCRM documents `bounced` and `complained` as real statuses, and Mailjet includes `hard_bounce`, `blocked`, `error`, and `customcampaign` in event data. ([FluentCRM][1])

Use these default rules:

* Hard bounce: immediate permanent suppression in FluentCRM
* Soft bounce: store metadata only; mark `bounced` after 3 soft bounces within 30 days
* Spam complaint: immediate `complained`
* Unsubscribe: immediate `unsubscribed`
* Blocked:

  * if error is `preblocked`, `sender blocked`, or other durable reputation-related condition, suppress
  * if it looks transient/system-related, log only

### 4) FluentCRM update layer

Update contacts inside WordPress directly rather than calling the external REST API unless there is a strong reason not to.

Preferred approach:
Use FluentCRM’s internal model/API inside the plugin to locate a contact by email or stored contact ID and update the status.

Fallback approach:
Use FluentCRM REST API only if internal APIs are unavailable.

Why:
Running inside WordPress avoids authentication overhead and is simpler operationally. FluentCRM supports the statuses we need. ([FluentCRM][1])

### 5) Storage

Create custom tables for reliability and auditability.

Recommended tables:

`wp_fcrm_mailjet_events`

* id
* mailjet_message_id
* event_type
* event_time
* email
* mj_campaign_id
* mj_contact_id
* customcampaign
* custom_id
* payload_json
* processed_at
* processing_result
* unique_hash

`wp_fcrm_mailjet_campaign_map`

* id
* fluentcrm_campaign_id
* mailjet_customcampaign
* mailjet_campaign_id
* first_seen_at
* last_seen_at

`wp_fcrm_mailjet_contact_meta`

* id
* fluentcrm_contact_id
* last_soft_bounce_at
* soft_bounce_count_30d
* last_hard_bounce_at
* last_blocked_at
* last_spam_at
* last_unsub_at

Use a unique index on a dedupe key such as:
`sha1(mailjet_message_id + event_type + event_time)`

That gives idempotency when Mailjet retries webhook deliveries.

## Processing flow

For each incoming event:

1. Verify request secret.
2. Parse JSON body.
3. Normalize fields.
4. Build dedupe key and skip if already processed.
5. Resolve FluentCRM contact in this order:

   * `fcrm_contact_id` from `X-MJ-EventPayload`
   * `X-MJ-CustomID`
   * recipient email
6. Resolve FluentCRM campaign from:

   * `fcrm_campaign_id` in payload
   * parse `customcampaign` like `fcrm_campaign_123`
7. Apply mapping rules.
8. Write audit row.
9. Return 200.

## Admin settings page

Add a WordPress admin screen with:

* Mailjet webhook secret
* soft bounce threshold
* soft bounce window in days
* whether `blocked` should auto-suppress
* whether to update via internal FluentCRM API or REST
* logging level
* test webhook button
* replay event button from logs

Also include read-only info:

* webhook endpoint URL
* last event received time
* last event processing status
* total hard bounces synced
* total complaints synced
* total unsubscribes synced

## Polling fallback job

Add a WP-Cron task that runs nightly.

Purpose:

* reconcile missed webhook events
* backfill if the site was down
* verify webhook integrity

Use Mailjet’s statistics/message-events APIs to fetch bounce-related records for a moving window. Your original research also identified `/bouncestatistics` as the most relevant event log endpoint and `/statcounters` for rollups. Mailjet’s event system remains the primary source of truth; polling is for recovery and reporting. ([documentation.mailjet.com][2])

Nightly job behavior:

* fetch events from the last 48 hours
* compare against `wp_fcrm_mailjet_events`
* insert missing events
* re-run normal processing
* generate a reconciliation summary in logs

## Campaign reporting

Support campaign attribution in two ways.

First, use your local event table:

* count hard bounces, soft bounces, complaints, unsubscribes by FluentCRM campaign ID

Second, optionally map Mailjet campaign IDs for later aggregate reporting:

* when you receive events containing both `customcampaign` and `mj_campaign_id`, persist the mapping

This lets you later compare your local event log against Mailjet campaign stats if needed. Mailjet’s `customcampaign` exists specifically to support campaign grouping from SMTP-sent mail. ([documentation.mailjet.com][2])

## Security requirements

Use all of these:

* HTTPS only
* shared secret header or query param validated server-side
* rate limiting on the endpoint
* reject non-POST methods
* strict JSON parsing
* sanitize and escape all stored text before rendering in admin
* capability checks on admin screens
* no public log exposure

Do not rely only on obscurity of the URL.

## Logging and observability

Log every state-changing event with:

* email
* FluentCRM contact ID
* FluentCRM campaign ID
* Mailjet message ID
* Mailjet campaign/contact IDs
* event type
* hard/soft classification
* status before
* status after
* reason code / error
* received timestamp
* processed timestamp

Add a CSV export of the event log.

## Testing plan

Developer should test at least these cases:

* hard bounce marks contact `bounced`
* soft bounce increments counter but does not immediately suppress
* repeated soft bounces cross threshold and then mark `bounced`
* spam complaint marks `complained`
* unsubscribe marks `unsubscribed`
* duplicate webhook delivery does not double-process
* unknown contact logs error but does not 500
* endpoint returns 200 quickly
* polling can backfill a missed event
* campaign attribution is preserved through `customcampaign`

Also confirm the resulting contact behavior in FluentCRM: `bounced` and `complained` contacts should no longer receive normal campaign mail. FluentCRM documents that bounced contacts do not receive further emails unless resubscribed. ([FluentCRM][1])

## Recommended implementation order

Phase 1:

* webhook endpoint
* raw event logging
* hard bounce / spam / unsub handling by email lookup

Phase 2:

* outbound Mailjet headers
* accurate campaign/contact attribution
* admin settings and logs

Phase 3:

* soft bounce threshold logic
* blocked-event classification
* nightly reconciliation job
* campaign analytics screen

## Acceptance criteria

The feature is done when:

* Mailjet webhook events update FluentCRM contacts automatically
* hard bounces become `bounced`
* spam complaints become `complained`
* unsubscribes become `unsubscribed`
* duplicate webhook deliveries are harmless
* every processed event is auditable
* FluentCRM campaigns can be tied back to Mailjet events through `X-Mailjet-Campaign`
* there is a fallback reconciliation job

Here is the one-sentence brief for the developer:

**Build a WordPress plugin that adds Mailjet SMTP correlation headers to FluentCRM emails, receives Mailjet webhook events, maps hard bounces/spam/unsubs to FluentCRM statuses, stores an auditable event log with idempotency, and runs a nightly reconciliation job for missed events.**

I can also turn this into a formal developer handoff doc with implementation checklist and pseudo-code.

[1]: https://fluentcrm.com/docs/contact-statuses/?utm_source=chatgpt.com "Contact Statuses - FluentCRM"
[2]: https://documentation.mailjet.com/hc/en-us/articles/360043578154-Event-Tracking-API?utm_source=chatgpt.com "Event Tracking API – Mailjet Help Center"
