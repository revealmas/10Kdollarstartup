<?php
// Copy outside public_html as config.php. Set secrets here through Hostinger's
// private file editor or environment variables; never put them in a public ZIP.
return [
    'base_url' => 'https://mybillflow.online',
    'storage_file' => __DIR__ . '/data/state.enc',
    'storage_key' => getenv('APPLYPILOT_STORAGE_KEY') ?: '', // base64 of 32 random bytes
    'stripe_key' => getenv('STRIPE_SECRET_KEY') ?: '',
    'stripe_webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
    'stripe_live' => false,
    'stripe_payment_method_configuration' => 'pmc_1UP59cJ6SCsBHGQtemFRBLih', // verified TEST configuration; replace for live mode
    'stripe_integration_identifier' => 'applypilot-review-ivtjjhmo',
    'deepseek_key' => getenv('DEEPSEEK_API_KEY') ?: '',
    'deepseek_model' => '', // confirm model available in user's account
    'daily_ai_limit' => 20, // reserve before calls, including failed calls
    'support_email' => '',
    'merchant_descriptor' => '', // verified actual descriptor shown by Stripe
    'licence_confirmed' => false,
    'privacy_confirmed' => false,
    'tax_confirmed' => false,
    'merchant_confirmed' => false,
    'production_tests_passed' => false,
    'ai_enabled' => false,
    'checkout_enabled' => false,
    'admin_password_hash' => '', // password_hash of a long owner-only password
    'storage_backend' => 'file', // 'supabase' uses encrypted compare-and-swap storage
    'supabase_url' => 'https://mzizcvhbyqoytzydkarf.supabase.co',
    'supabase_service_key' => getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '', // SERVER ONLY
    // Manual inclusive tax rates keyed by billing country, after tax review.
    // Empty string means documented no tax to collect for that country.
    // No country is enabled by default. Global launch needs global tax assessment.
    'country_tax_rates' => [],
    'feeds' => [], // see docs/INTEGRATION.md: explicit provider permission review
];
