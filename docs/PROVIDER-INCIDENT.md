# Provider incident — 15 October 2026

At 08:06 Dubai, DeepSeek's official https://status.deepseek.com/ reported a partial Web/API outage, with a fix being implemented and Flash API affected. This supports a provider-side explanation for the unavailable response, but does not prove the cause of our individual request.

Deployed diagnostic patch 8f23b61 separates fixed safe transport categories (timeout, network, credentials, credits, format, rate limit, service failure) without returning error bodies, request headers or credentials. It also stops reporting a fictitious missing-summary schema error when transport fails or only an older cached response exists. Native CI passed with 27 AI diagnostic checks. Provider error meanings checked against https://api-docs.deepseek.com/quick_start/error_codes/.

Do not automatically retry generation or purchase new credits. A final synthetic acceptance test is still required once service is available, within existing usage caps. No customer checkout should be enabled based solely on a passing mocked test or a public status-page recovery.

The sixth cumulative synthetic attempt returned `Provider request timed out` on the actual Hostinger owner screen. No schema error was appended, confirming the diagnostic fix. No further provider retry was made during the incident.
