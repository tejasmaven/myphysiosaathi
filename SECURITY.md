# Website enquiry and assessment security controls

This inventory describes the marketing website, enquiry endpoint and assessment/report flow. It does not certify the separate clinic management application or the server. Controls were checked in source and local regression tests. Live Apache access denial must be verified after deployment.

## Keep the production reports path

The supported setting is:

```php
'report_directory' => '/var/www/html/myphysiosaathi/reports',
```

Upload `reports/.htaccess` FIRST, then the updated `assessment/report-store.php` and `assessment/diagnostics.php`. Keep existing JSON, PDF, lock and daily `.seq` files. Preserve your private SMTP config. Update the example setting in your real config only if needed; `config.local.php` and environment overrides still take precedence.

For this in-site folder, the storage code checks that it is the dedicated reports directory and the web runtime identifies as Apache. Before creating a record, it creates the canonical deny file if missing and rejects an altered or symlinked deny file. The Apache rule is `Require all denied`, which must apply to direct file requests as well as directory requests. The application can verify the file contents, but cannot establish from PHP alone that Apache honours `.htaccess`. Apache must have `AllowOverride AuthConfig` or `AllowOverride All` for this folder. Your previously supplied site configuration uses `AllowOverride All`. For stronger server-level protection, merge `apache-report-protection.conf.example` into the Apache configuration for every vhost serving this directory, test the configuration and reload Apache.

On Ubuntu with PHP/Apache running as `www-data`, set ownership and directory permissions after uploading the access file:

```bash
sudo chown -R www-data:www-data /var/www/html/myphysiosaathi/reports
sudo chmod 700 /var/www/html/myphysiosaathi/reports
```

Verify with a harmless file before accepting clinic data:

```bash
printf 'access-check' | sudo tee /var/www/html/myphysiosaathi/reports/access-check.txt >/dev/null
curl -sS -o /dev/null -w '%{http_code}\n' https://www.myphysiosaathi.in/reports/access-check.txt
sudo rm /var/www/html/myphysiosaathi/reports/access-check.txt
```

The HTTPS request must return **403**. A 200 means the file is exposed; do not submit clinic data until Apache denial is fixed. A redirect or 404 is not proof that this existing file is protected. Do not relax the rule to allow PDF or JSON downloads. Out-of-site storage remains supported on any web server; in-site storage is rejected on non-Apache runtimes rather than assuming they honour `.htaccess`.

## Implemented controls

| Control | Exact behaviour / scope |
| --- | --- |
| Assessment attempt limit | Six validated new attempts per IP in a rolling 3,600-second window. The seventh is blocked. Counter entries are timestamps, stored in `state_directory/SHA256(IP).json`. Processing failures after the allowance is consumed still count. Invalid field/CSRF/honeypot submissions are rejected before this counter. |
| Enquiry attempt limit | Six validated new requests per IP per rolling hour, separately from assessments. File: `state_directory/enquiry-SHA256(IP).json`. Limit responses use HTTP 429. |
| Retry allowance | An assessment already saved in the same session can retry PDF/email processing without another new-attempt charge. Matching enquiry retries also bypass the new-request counter. |
| CSRF tokens | Both forms use random session-bound tokens and `hash_equals` checks. Assessment tokens rotate after successful delivery, rejecting a repeated POST with the old token. There is no custom fixed 30-minute token expiry. |
| Honeypot | Both forms reject a nonempty hidden `website` field. This is basic bot filtering, not CAPTCHA. |
| Minimum elapsed time | Assessment submission requires at least three seconds since session form setup; enquiry requires two seconds. This is session timing, not time actually spent answering every question. |
| Session cookies | Strict session mode, HttpOnly and SameSite=Lax. Secure is set when PHP identifies the request as HTTPS. Reverse-proxy HTTPS detection is a deployment responsibility. |
| Input validation | Required contact fields, valid email/mobile values, integer non-negative bed count and input length/type limits are checked server-side. Arrays and unsupported scalar values are not treated as text or numeric scores. |
| Assessment answer whitelist | Exactly the 25 expected question IDs. Canonical values are integers 1-5, `unknown` or `na`; supported form strings are normalised. Missing, extra or invalid answers are rejected. |
| Domain notes | At most 2,000 Unicode characters per domain. Notes must be valid UTF-8 text, with expected A-E keys. |
| Enquiry request size and methods | GET prepares the form; POST submits JSON. Other methods return 405. The endpoint caps declared body size at 40,000 bytes and limits its JSON read/decoding. Assessment POST size also depends on PHP/server configuration; it has no equivalent custom total-body check. |
| Output escaping | Submitted values are escaped when rendered in the assessment HTML and branded email. PDFs render plain text rather than customer HTML. |
| Report HTTP protection | Dedicated in-site reports use the Apache deny file described above. Website root and unrelated public folders are rejected. The folder is not a public download endpoint. Apache enforcement must be checked on the live server. |
| Internal file HTTP protection | `assessment/.htaccess` allows only `form.css` and `form.js`; configuration, PHP includes and bundled libraries are denied direct access. `includes/.htaccess` denies the shared include folder. These depend on Apache honouring overrides; Nginx needs equivalent rules. |
| Filesystem permissions | Report directories are set to 0700; newly written report JSON/PDF/temp/lock/counter files use 0600. A failure to secure the report directory/file stops processing. The PHP worker needs ownership. |
| Unpredictable private IDs | Stored report filenames use a separate random 128-bit hex ID. The date-based customer reference is not a filesystem path or download token. Storage-ID validation rejects traversal strings. |
| Atomic storage | JSON/PDF/counter writes use randomly named temporary files and rename on completion, reducing partial writes. File locks serialise daily reference allocation and processing of the same saved report. |
| Stored PDF integrity | A SHA-256 digest is checked before emailing the saved PDF. This detects changed/corrupt bytes; it is not a digital signature or protection against someone who can rewrite both the PDF and JSON. |
| Duplicate-send handling | Assessment retries reuse the saved reference and PDF and skip recipients marked sent. Enquiry delivery flags separately track admin notification and assessment invitation within the session. A changed enquiry payload with the same request ID is rejected. |
| Credential handling | SMTP settings remain server-side. Real config files and environment files are ignored by Git; browser responses do not expose SMTP credentials. Git ignores generated report contents while retaining the Apache deny file. |
| SMTP transport | Bundled PHPMailer, without Composer. The example uses Gmail TLS on port 587; certificate checks are not disabled by our code. Transport depends on the actual config; an empty encryption setting is supported for local tests. |
| Readiness checks | Submission is unavailable when SMTP settings are disabled/incomplete. This verifies configuration shape, not actual inbox delivery. |
| Response headers | Assessment and enquiry responses use private/no-store caching and `X-Content-Type-Options: nosniff`. Assessment also uses `Referrer-Policy: strict-origin-when-cross-origin`. |
| Diagnostic privacy | The assessment helper logs a random support code, processing stage, safe category, exception class, source basename and line. It does not log raw exception messages, submitted answers or SMTP secrets. It is operational diagnostics, not a full access audit trail. |
| Recipient separation | The assessment report is sent separately to the configured administrator and the supplied clinic email. The invitation goes only to the requester; its reply-to is the configured administrator. |

## Limitations and operating notes

- Limits use PHP's `REMOTE_ADDR`, not accounts. Visitors sharing an IP share an allowance. There is no trust of arbitrary client-supplied forwarded IP headers. Correct proxy configuration matters.
- Counters and report data are separate. Clearing one IP's rate file does not delete reports or reset assessment references. For testing, clear only that IP's assessment counter; do not delete the entire reports folder or daily counters.
- SMTP acceptance and local status storage are not one atomic transaction. A server crash between them can require manual delivery confirmation before retrying. Enquiry retry flags depend on the original session; they are not a durable queue.
- Stored report content is not encrypted by this application. There is no automated backup, recovery-test guarantee, retention deletion, admin login/MFA dashboard, CAPTCHA/WAF, malware scanner, or clinical/security/compliance certification added by this website.
- HTTPS redirects, HSTS, server patching, firewall rules, PHP request limits, OS permissions and secret management remain server/deployment responsibilities. No application-wide CSP or complete infrastructure security audit is claimed.
- If the site moves from Apache to Nginx, in-site report storage must not be assumed safe. This implementation rejects that mode; use out-of-site storage or implement and verify an explicit Nginx protection workflow.

## Verification

`tests/report-storage-security.php` checks the dedicated Apache mode, canonical guard creation/validation, malformed and symlinked guard rejection, directory/file permissions, complete PDF generation, private filename validation and non-Apache rejection. It tests PHP's safeguards with simulated server metadata, not live Apache HTTP enforcement. `tests/report-regression.php`, `tests/submission-integration.py`, `tests/site-integration.py` and `tests/reference-concurrency.py` cover scoring, structured validation, escaping, delivery retries and concurrent references. Tests use fictional data and local SMTP capture, without sending customer email.
