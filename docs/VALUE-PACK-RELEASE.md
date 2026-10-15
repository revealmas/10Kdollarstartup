# Application pack upgrade — 15 October 2026

The old delivery was a review report with a generic cover-letter template. New preparations must contain a validated, job-specific CV plus a complete application pack before an order can be created.

## What customers can assess

- Public fictional example at `/demo.html`, with source CV and posting, explained before/after edits, full CV, cover letter, interview answer outlines and a specific requirement action plan.
- Free personal AI preview (when processing is enabled): one actual prepared edit, its reason and a verbatim job requirement, alongside fit evidence and readiness. Full CV, letter and interview answers remain private until verified payment.
- Exactly USD 9, one-time. Existing launch flags and customer-data controls remain in force.

## Deliverables

- CV: editable Word, selectable-text PDF, text.
- Tailored cover letter: Word, PDF, text; contact fields are added locally.
- Three to five evidence-linked interview answer outlines with prompts for truthful specifics.
- Zero to five actionable requirements to verify; no forced invented gaps.
- Explained edits linked to the prepared CV and quoted job requirements.

The example is an editorial, fictional fixture, not a live provider result or customer testimonial. Validation checks exact evidence, literal role/employer and numeric claims. These checks cannot prove that every AI paraphrase is semantically faithful; users must review it.

## Verification and remaining limits

Local PHP: core/payment access controls; 14 application-pack cases; AI, sandbox and fulfillment regression tests. Frontend: full pack clearing on refund/expiry, stale download denial, CV/letter Word and PDF output. Browser: desktop demo tabs and keyboard navigation, homepage and actual examples. Word and PDF cover-letter renders visually inspected.

Actual isolated Stripe sandbox: a USD 9 test checkout was paid, the ordinary signed webhook unlocked the complete fictional pack, Word/plan downloads succeeded, and the full refund revoked access. Resending the original paid event did not restore access. No real money moved. Native CI passed for deployed code commit 5a53f59. The temporary code updater self-deleted and its scheduled job was removed; only hourly maintenance remains. The 15 October dashboard confirms expired package cleanup and three revoked synthetic paid orders.

Actual DeepSeek: the expanded pack passed cached validation on 14 October. Manual content review then found unsupported negative experience wording and elaborated interview stories. Deployed guards now remove unsupported negative letter paragraphs and assemble interview starting answers from verified source quotes. These guards have regression coverage. The old synthetic cache expired after 24 hours. One fresh synthetic acceptance request on 15 October failed with provider unavailable before a usable response was retained. Therefore final live-provider acceptance of the latest guards remains incomplete. No customer order or charge was created. The displayed extra schema diagnostic on this transport failure is misleading because no cached report exists; it is not evidence that DeepSeek returned a malformed summary.

Diagnostic limits: two calls per hour, four per rolling 24 hours, shared daily allowance and 30 cumulative pilot calls. Six provider attempts have now been made, failures included. The 6,000 output-token ceiling is not a monetary spending cap. No automatic provider retry was performed.

Ten reel scripts and ten silent 25-second portrait MP4 drafts are delivered separately. They are caption-card drafts, not finished voiced footage productions; no music or scheduling. The scripts include fuller voiceover and shot directions. Social account/Metricool destination must be identified before publishing.

Commercial launch remains disabled pending exact licensed activity evidence, applicant-data processing/transfer review, tax/sales-jurisdiction and live merchant checks, and a successful final provider acceptance test. Job discovery now includes a bounded Jobicy feed and employer links; 100 Jobicy listings were imported on Hostinger at 08:07 Dubai on 15 October, and an actual browser search for engineer returned 21 results with attribution and geography. See JOB-SOURCE-REVIEW.md. Mobile browser sizing was not successfully verified. Local bounded load checks do not establish production capacity. This release is not exhaustive penetration testing or a revenue guarantee.

## Follow-up fixes

Provider diagnostics fixed and deployed: transport failure no longer claims a missing summary. Actual test now identifies a timeout; DeepSeek official status reports an API incident. See PROVIDER-INCIDENT.md. Jobicy feed and source review added, with thirteen passing refresh/URL/attribution tests.
