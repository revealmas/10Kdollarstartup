# ApplyPilot recovery build — 13 October 2026

This is a new recovery build and integration kit. It is **not a modified or audited copy of the original MVP**. Both prior-chat source links failed when clicked; neither ZIP was present locally. The original should be attached and audited before merging. Screenshots from the original were not recovered.

Available now: responsive interface, useful browser-local CV word review, direct official employer board links, server-side DeepSeek adapter, fixed USD $9 Checkout flow, signature-verified webhook and owner-authorised fulfillment, private encrypted-storage implementation, 24-hour package access/deletion, aggregate metrics, Word/text exports and browser PDF printing, privacy/terms/refund/support preview pages, importer and cleanup scripts.

AI processing, paid checkout and unreviewed job feeds are disabled by default. There are no subscriptions, external fonts, tracking pixels, paid libraries or Supabase dependencies. Existing MAFSAN-DEMO was not accessed.

56 core security checks passed in PHP 8.3 WebAssembly with an in-memory store and mock Stripe/AI responses. This does **not** establish production readiness: native OpenSSL/file-lock/session tests, real DeepSeek output, Hostinger integration, real Stripe test Checkout/webhooks, licensed activity, tax and provider privacy terms remain required.

The paid recovery product is an application **review bundle**, with original supplied CV text, evidence-based guidance and a grounded cover-letter template. It does not yet reproduce the original MVP’s unknown CV rewriting feature. Word export contains the review bundle; PDF uses browser print. No full paid package is sent to the browser before verified payment.

Read [docs/STATUS.md](docs/STATUS.md), [docs/INTEGRATION.md](docs/INTEGRATION.md), and [docs/UAE-LAUNCH-REVIEW.md](docs/UAE-LAUNCH-REVIEW.md). Never upload the whole source kit into public_html. Only public/ belongs there; private/ stays outside the web root.
