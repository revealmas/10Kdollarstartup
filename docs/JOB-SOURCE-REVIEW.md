# Public job source review — 15 October 2026

Chosen source: Jobicy public REST API, https://jobicy.com/jobs-rss-feed and https://github.com/Jobicy/remote-jobs-api . The provider explicitly permits career tools, job boards and AI products without individual permission, retaining source attribution and canonical Jobicy links. No key, wallet or commercial API is used.

Implementation: one first-page request (100 recent listings) per six hours through hourly maintenance. Cache only role, employer, eligible geography, source and original URL; display no copied descriptions, logos or personal data. This is a bounded selection of remote jobs, not a worldwide comprehensive feed. Jobicy delays results by three hours and covers a seven-day publication window. Remote does not mean eligible worldwide. No query, CV, email or applicant data is sent to this source. Listings remain free without login or email collection. Follow original listing links to check current eligibility and availability.

Failed refreshes do not renew freshness; the existing service hides stale results after twelve hours. A successful empty feed removes cached results. Refresh reservations prevent duplicate concurrent requests. The app never follows URLs supplied by the response on the server, and only displays HTTPS canonical jobicy.com/jobs/ links.

Remotive and Arbeitnow were evaluated but not imported: broad site terms contain commercial reuse restrictions despite public API documentation. No permission was assumed from technical accessibility alone.
