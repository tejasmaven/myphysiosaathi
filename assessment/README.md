# Free clinic assessment

Entry page: `free-assesment.php` (the requested spelling). Upload that file and the entire `assessment/` folder beside the existing `index.html` and `styles.css`. The landing page is unchanged and does not link to this page. The assessment uses `noindex,nofollow`.

## Server requirements

- PHP 8.1 or later, with sessions, OpenSSL, iconv and zlib enabled.
- HTTPS, writable private PHP session storage, and outbound access to Gmail SMTP.
- Apache 2.4 with AllowOverride enabled. The included assessment/.htaccess allows only form.css and form.js to be accessed directly. On Nginx, configure equivalent denial of direct access to every other path inside /assessment/.
- No Composer or database required. PHPMailer 7.1.1 and FPDF 1.86 are included, with upstream licenses.

## Configure Gmail delivery

1. Copy `assessment/config.example.php` to a private path outside DocumentRoot, for example `/var/www/private/myphysiosaathi-assessment.php`.
2. Enter the Gmail username, Gmail App Password, matching from_email and the admin recipient. Leave `enabled` false while preparing.
3. Set the Apache environment variable `ASSESSMENT_CONFIG_PATH` to that absolute private file path. Alternatively use the ignored `assessment/config.local.php`; keeping credentials outside the web directory is recommended.
4. Use smtp.gmail.com, port 587 and tls, or port 465 and ssl. Keep certificate verification enabled.
5. Ensure state_directory is writable by the PHP user and is outside DocumentRoot. The application writes rate-limit timestamps here, not reports or answers.
6. Set enabled to true for a controlled real test. Complete the form, verify both inboxes and their PDF attachments, and confirm the success message. Keep the page unavailable to visitors until this test passes. Disable again if a test fails.

Keep the App Password out of GitHub. The application is disabled unless configuration is complete. Uploading this repository alone does not configure SMTP.

## Fields and answers

The seven clinic fields are required, including email and number of beds/treatment couches. Zero beds is valid. All 25 questions from My_Physio_Saathi_Clinic_Workflow_Assessment.pdf require one response. The PDF's Not sure and N/A responses are preserved. Notes are optional, up to 600 bytes per question, and should contain no patient identifiers or medical details.

The six-page PDF normally contains clinic details and one page per domain. Long notes may add pages. This is a completed questionnaire, without an invented score, recommendations or certification. Standard PDF fonts cover English and Western European text; names or notes in Gujarati/Hindi may be transliterated or display unsupported glyphs. Use English for this version. If local-script PDF support is needed, replace the font/PDF engine before launch.

## Submission behavior

Server-side and browser validation, escaped output, CSRF tokens, honeypot and a six-valid-submissions-per-hour-per-IP limit are included. Client-provided proxy headers are not trusted. HTML radio groups support keyboard selection. The form remains functional without JavaScript.

Each validated submission generates a reference and PDF. Separate PHPMailer messages go to the admin and submitter. A visible success message is shown only after both SMTP sends succeed. SMTP acceptance does not guarantee final inbox delivery.

If only one email succeeds, retry sends only the failed copy. Validated answers are frozen in the private PHP session after the first send attempt. They are removed from that session on success. Pending submissions are retained until retry or session expiry. No public PDF URL or permanent report archive is created. Configure normal session garbage collection and short retention on the server.

PHP server-side processing is required. This page will not process submissions when opened from a local file or deployed to static-only hosting. Existing homepage form delivery remains independent.

## Checks completed

PHP syntax checks on application and vendor files; local SMTP integration tests for successful dual delivery, both PDF attachments, partial failure and retry without duplicate delivery; CSRF rejection; missing and invalid rating rejection; invalid email; zero-bed input; all 25 question groups and all seven answer choices; generated PDF content and rendered layout review.

Real Gmail delivery, inbox receipt, server access-denial rules and browser desktop/mobile review must be checked on deployment. SMTP credentials and admin recipient are still required. A README is included so these remaining checks are explicit.

Upstream sources:
- https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1
- https://github.com/Setasign/FPDF/tree/1.8.6
