# Shared SMTP configuration

Both free-assesment.php and enquiry.php now use assessment/config-loader.php.

## Your existing setup

You can keep assessment/config.php exactly where it is. It must return the configuration array, with enabled set to true and the existing SMTP values. No rename is required. Do not upload a blank example over your working file.

Upload assessment/config-loader.php, assessment/backend.php and enquiry.php together. Refresh the assessment after uploading. The Submit button reflects the server's configuration readiness, not the answer count. POST validation still requires all 25 questions.

## Lookup order

1. If ASSESSMENT_CONFIG_PATH is set, load that exact private file. An invalid explicit path does not silently fall back to another credential set.
2. Otherwise use assessment/config.local.php if present.
3. Otherwise use assessment/config.php if present.
4. Apply supported environment overrides to the loaded configuration. With no file, the environment variables can configure the system on their own.

An old config.local.php can override your config.php. Remove or update only that stale override if it is not the intended configuration.

## Environment variables

Set these in the Apache/PHP process environment. A .env file is not loaded automatically.

| Variable | Configuration key |
| --- | --- |
| ASSESSMENT_ENABLED | enabled |
| SMTP_HOST | smtp_host |
| SMTP_PORT | smtp_port |
| SMTP_ENCRYPTION | smtp_encryption |
| SMTP_AUTH | smtp_auth |
| SMTP_USERNAME | smtp_username |
| SMTP_PASSWORD | smtp_password |
| SMTP_FROM_EMAIL | from_email |
| SMTP_FROM_NAME | from_name |
| SMTP_ADMIN_EMAIL | admin_email |
| ASSESSMENT_STATE_DIRECTORY | state_directory |

Boolean flags accept true/false, 1/0, yes/no or on/off. Explicit false disables delivery. The default is disabled. Encryption supports tls or ssl; an empty value is only for a local development SMTP server. Gmail requires authenticated encrypted SMTP. Default host is smtp.gmail.com, with port 587 for tls or 465 for ssl. Keep server certificate verification enabled.

ASSESSMENT_ENABLED=true, valid sender/admin email addresses and complete SMTP authentication settings are required for live Gmail delivery. SMTP usernames, passwords, addresses and paths stay server-side. No public diagnostics endpoint is added.

## Git exclusions

.gitignore excludes config.php, config.local.php, local configuration variants, SMTP credential files and existing .env patterns. Blank config.example.php and the configuration-loader source remain tracked. The working private configuration should be supplied on each server independently.

Neither assessment/config.php nor assessment/config.local.php was tracked when this fix was prepared. An ignore rule cannot erase credentials from old commits if someone adds them later.

## Verification

Tests passed for config.php loading, config.local.php precedence, an explicit external path, environment-only setup, environment overrides, string boolean flags, invalid configuration and both form submission flows. Assessment PDF generation and dual delivery, retry behavior and homepage PHPMailer delivery were retested using a local SMTP test server.

After deploying, refresh the assessment and submit one controlled test to confirm real Gmail receipt. Preserve your real SMTP settings.

## Customer report storage

The assessment customer report also requires durable private storage outside DocumentRoot. Set `ASSESSMENT_REPORT_DIRECTORY` in the PHP environment, or `report_directory` in the ignored SMTP/configuration PHP file. Default: `myphysiosaathi-private/reports` beside DocumentRoot. Pre-create it for the PHP user with mode 0700. Generated JSON/PDF files are mode 0600 and must never be committed or served publicly. See `assessment/README.md` for lifecycle, retention and retry details. This setting does not change SMTP credentials or homepage enquiry delivery.
