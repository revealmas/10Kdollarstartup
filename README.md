# ApplyPilot recovery build — 13 October 2026

This is a new recovery build and integration kit. It is **not a modified or audited copy of the original MVP**. Both prior-chat source links failed when clicked; neither ZIP was present locally. The original should be attached and audited before merging. Screenshots from the original were not recovered.

Available now: responsive interface, useful browser-local CV word review, direct official employer board links, server-side DeepSeek adapter, fixed USD $9 Checkout flow, signature-verified webhook and owner-authorised fulfillment, private encrypted-storage implementation, 24-hour package access/deletion, aggregate metrics, Word/text exports and browser PDF printing, privacy/terms/refund/support preview pages, importer and cleanup scripts.

AI processing, paid checkout and unreviewed job feeds are disabled by default. There are no subscriptions, external fonts, tracking pixels, paid libraries or Supabase dependencies. Existing MAFSAN-DEMO was not accessed.

65 core checks and 30 local PHP runtime checks passed. Native GitHub CI also passed authenticated encryption/tamper/wrong-key checks, encrypted integration settings, four writers with 100 commits and no lost updates, and 18 HTTP access-control checks. Its bounded local load ran 100 requests at concurrency 10: p50 10 ms, p95 17 ms, 55 HTTP 200 and 45 rate-limited HTTP 429. This does not measure production capacity. On Hostinger, 23 synthetic functional/security checks passed, including owner sign-in, private-path isolation and rejection of live Stripe credentials. Real DeepSeek quality and real Stripe test Checkout/webhook/refund flows remain pending.

The live owner panel can save DeepSeek/model and Stripe test/webhook credentials in encrypted server storage without returning secret values or enabling paid features. Owner credentials are kept out of source archives. Commercial processing remains disabled pending licence, privacy, tax, merchant and provider verification.

The isolated Supabase project “10K Dollar Startup” has an empty, encrypted-state schema with RLS and service-only access; security and performance advisors reported no findings. Its server credentials are not configured. MAFSAN-DEMO was not accessed.

The paid recovery product is an application **review bundle**, with original supplied CV text, evidence-based guidance and a grounded cover-letter template. It does not yet reproduce the original MVP’s unknown CV rewriting feature. Word export contains the review bundle; PDF uses browser print. No full paid package is sent to the browser before verified payment.

Read [docs/STATUS.md](docs/STATUS.md), [docs/INTEGRATION.md](docs/INTEGRATION.md), and [docs/UAE-LAUNCH-REVIEW.md](docs/UAE-LAUNCH-REVIEW.md). Never upload the whole source kit into public_html. Only public/ belongs there; private/ stays outside the web root.
