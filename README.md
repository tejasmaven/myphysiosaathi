# My Physio Saathi

Marketing website for https://www.myphysiosaathi.in/, with a public pricing page and an unlisted clinic assessment page.

## Deploy

Upload the website files and assets to the PHP-enabled server. Preserve ignored private configuration, the server's root `.htaccess` and virtual-host settings. Homepage enquiries and clinic assessments use bundled PHPMailer without Composer. Configure the shared SMTP settings using `SMTP_SETUP.md`; existing `assessment/config.php` and `assessment/config.local.php` remain supported and ignored by Git. The old browser email API is no longer required for enquiry delivery.

The hidden `free-assesment.php` page generates deterministic, versioned customer reports from structured questionnaire answers. It requires durable private storage outside DocumentRoot. See `assessment/README.md` for configuration, report rules, private persistence, retries and regression tests. Uploading source alone does not configure SMTP or private storage.

## Content and interaction

- Six landing-page sections: opening, clinic problems, patient journey, role features, FAQs, contact.
- Red/white card hover states and reduced-motion support.
- Six stacked workflow steps with changing images on hover, tap, click and keyboard focus.
- One FAQ open at a time with matching question/answer backgrounds.
- Six illustrative SVG placeholders to replace with approved application screenshots.
- Canonical and social-sharing URLs use the official www domain.

No real patient details, invented testimonials or appointment/reminder claims are included.

## Editing

Page copy: `index.php` and `pricing.php`. Shared navigation/footer: `includes/header.php`, `includes/footer.php` and `shared-chrome.css`. Shared tracking: `includes/tracking.php`. Styling: `styles.css`. Form and interactions: `site.js`. Workflow images: `assets/`. Sharing card: `og.png`. Assessment/report implementation and configuration: `assessment/`. Regression checks: `tests/`.

## Public pricing, shared layout and tracking

The homepage now runs as `index.php`; pricing runs as `pricing.php` and is linked from the main navigation. Both use the same PHP header and footer. The assessment uses them too. Navigation stays available on phones. `index.html` and `pricing.html` are small compatibility redirect pages only.

Upload `index.php`, `pricing.php`, the updated `index.html` / `pricing.html` redirect files, `free-assesment.php`, `enquiry.php`, `site.js`, `shared-chrome.css`, the entire `includes/` directory, and `assessment/mail.php`. Keep all existing assets and private SMTP configuration. Merge the three lines in `apache-page-routes.conf.example` into the existing root `.htaccess`, preserving your HTTPS/www rules. `DirectoryIndex index.php index.html` keeps `/` as the homepage; the redirects send old `.html` URLs permanently to the current pages. On Nginx, set `index index.php index.html` and equivalent permanent redirects. Do not replace your existing server configuration with the example snippet. Deny direct HTTP access to `includes/` on Nginx; the included Apache `.htaccess` does this on Apache.

Every real HTML page includes `includes/tracking.php` twice: once at the end of the head and once immediately inside the body. Paste Google Analytics or the GTM container script in its HEAD branch; paste GTM's matching noscript iframe in its BODY branch if used. This one file updates all three pages. No tag ID or analytics library is configured by default. Avoid adding the same analytics tag both directly and through GTM. Do not pass clinic contact details, answers, notes or report references to analytics. Future HTML pages should use the same two includes. JSON/form-processing endpoints do not emit tracking markup.

Homepage and pricing have individual titles, descriptions, keywords, canonical URLs and social-sharing metadata. Pricing no longer carries `noindex,nofollow`. The unlisted assessment retains `noindex,nofollow`. Existing `og.png` is used for social sharing. Page keywords are metadata, not a promise of search ranking.

## Assessment invitation email

A `Free clinic workflow audit` enquiry, or the supported `Free clinic workflow assessment` value, sends the normal admin enquiry and a separate branded assessment-link email to the submitted email address. Product demos send only the admin enquiry. The invitation uses navy/red My Physio Saathi branding, an HTML button, the full assessment URL, a plain-text alternative and the Tejas contact footer. It does not include patient data, fake claims or an attachment before the assessment has been completed. Template: `includes/enquiry-mail.php`. Link: `https://www.myphysiosaathi.in/free-assesment.php`.

Both flows reuse the same PHPMailer factory and private SMTP configuration as assessment reports, without Composer. Session delivery flags track the admin and invitation sends separately. After a partial failure, the response explains that the enquiry was received and asks the visitor to retry the link send. Matching retries in the same session reuse their request ID and do not repeat successful sends. A changed payload with the same ID is rejected. These flags persist in the PHP session, not a durable delivery queue. SMTP acceptance is not guaranteed inbox receipt; a crash between SMTP acceptance and saving the flag can still require manual confirmation.

Run `PHP_COMMAND=php python3 tests/site-integration.py` for HTTP pages, shared tracking placement, metadata, footer/navigation matching and actual PHPMailer messages through a local capture SMTP server. It covers demo-only delivery, HTML escaping, separate recipients, admin/link failures and repeat submissions without duplicate successful sends. Also run `tests/submission-integration.py` and `php tests/report-regression.php` to verify the assessment report flow still works. No real emails are sent by these tests.
