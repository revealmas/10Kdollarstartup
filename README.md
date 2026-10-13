# ApplyPilot recovery build — 13 October 2026

This is a new recovery build and integration kit. It is **not a modified or audited copy of the original MVP**. Both prior-chat source links failed when clicked; neither ZIP was present locally. The original should be attached and audited before merging. Screenshots from the original were not recovered.

Available now: responsive interface, useful browser-local CV word review, direct official employer board links, server-side DeepSeek adapter, fixed USD $9 Checkout flow, signature-verified webhook and owner-authorised fulfillment, private encrypted-storage implementation, 24-hour package access/deletion, aggregate metrics, Word/text exports and browser PDF printing, privacy/terms/refund/support preview pages, importer and cleanup scripts.

AI processing, paid checkout and unreviewed job feeds are disabled by default. There are no subscriptions, external fonts, tracking pixels, paid libraries or Supabase dependencies. Existing MAFSAN-DEMO was not accessed.

Native CI passed 65 mocked core checks, 11 Stripe diagnostic checks, 14 AI diagnostic checks, 25 HTTP access-control checks, authenticated encrypted storage/settings, four writers with 100 commits/no lost updates, and frontend/session-expiry/Word ZIP checks. Local PHP WebAssembly passed 36 checks. Bounded native local load: 100 requests/concurrency 10, p50 10 ms / p95 29 ms, 54 HTTP 200 and 46 HTTP 429; this is not production capacity. Actual Hostinger checks passed one synthetic DeepSeek generation with valid JSON/exact CV evidence, and creation/retrieval/expiration of an unpaid USD 9 Stripe sandbox Checkout. Broader AI/adversarial quality, signed webhook delivery and actual paid fulfillment/refund/dispute tests remain pending. See docs/STATUS.md for scope and evidence.

The live owner panel can save DeepSeek/model and Stripe test/webhook credentials in encrypted server storage without returning secret values or enabling paid features. Owner credentials are kept out of source archives. Commercial processing remains disabled pending licence, privacy, tax, merchant and provider verification.

The isolated Supabase project “10K Dollar Startup” has an empty, encrypted-state schema with RLS and service-only access; security and performance advisors reported no findings. Its server credentials are not configured. MAFSAN-DEMO was not accessed.

The paid recovery product is an application **review bundle**, with original supplied CV text, evidence-based guidance and a grounded cover-letter template. It does not yet reproduce the original MVP’s unknown CV rewriting feature. Word export contains the review bundle; PDF uses browser print. No full paid package is sent to the browser before verified payment.

Read [docs/STATUS.md](docs/STATUS.md), [docs/INTEGRATION.md](docs/INTEGRATION.md), and [docs/UAE-LAUNCH-REVIEW.md](docs/UAE-LAUNCH-REVIEW.md). Never upload the whole source kit into public_html. Only public/ belongs there; private/ stays outside the web root.
