# Clinic workflow assessment and customer report

Entry page: `free-assesment.php` (existing spelling). The landing page remains unchanged and does not link to it. The page uses `noindex,nofollow`.

## Deployment

Upload `free-assesment.php` and the whole `assessment/` directory beside the existing landing page. Preserve the server's ignored SMTP configuration and existing root `.htaccess` and virtual-host settings. Requirements: PHP 8.1+, sessions, OpenSSL, iconv, zlib, HTTPS and outbound SMTP access. PHPMailer 7.1.1 and FPDF 1.86 remain bundled with licenses. No Composer, database or external AI service is used.

Configure SMTP as described in `SMTP_SETUP.md`. The assessment and homepage enquiry share the existing configuration loader. Existing `config.php` and `config.local.php` remain supported; neither should be committed.

**New deployment requirement:** durable private report storage outside the web document root. Set `report_directory` in the private PHP configuration, or `ASSESSMENT_REPORT_DIRECTORY` in the PHP environment, for example `/var/www/private/myphysiosaathi/reports`. If omitted, the default is `myphysiosaathi-private/reports` beside DocumentRoot. Pre-create the directory and give the PHP user write access with directory mode 0700. Files are mode 0600. There is no fallback into a public directory. A configured path inside DocumentRoot, including a symlink resolving there, is rejected. The directory must survive deployments and server restarts. An unwritable directory is a deployment blocker.

Configure Apache 2.4 AllowOverride for `assessment/.htaccess`, which exposes only `form.css` and `form.js` directly. Configure equivalent internal-file denial on Nginx. Reports themselves stay outside DocumentRoot and have no public download route. Confirm access restrictions on the actual host.

The seven clinic fields and all 25 form responses remain required. Zero beds is valid. Optional notes are limited to 600 UTF-8 bytes. Do not submit patient identifiers or medical information. Standard FPDF fonts support English and Western European text. Gujarati/Hindi may be transliterated or unsupported; local-script support requires a separate PDF font upgrade.

## Deterministic report generation

`questions.json` remains the question catalogue. `report.php` operates on structured answers, never PDF-extracted text. Numeric strings `1` through `5` become integers. Legacy `unsure` becomes `unknown`; canonical `unknown` and `na` remain nonnumeric. Blank, null, boolean, float, array, out-of-range and unsupported string values are rejected. Missing question keys are retained as missing for imported/test assessments; the public form still rejects incomplete submissions.

`scoring-rules.php` contains versioned thresholds, important control IDs, the minimum three numeric responses per domain and the 80% overall coverage requirement. Update its version when rules change. All averages use numeric responses only and interpretation uses unrounded values. Overall scoring is question-weighted. Coverage is numeric count divided by 25 minus respondent-selected N/A count. An all-N/A assessment has no coverage percentage. Unknown, missing and unreviewed N/A answers make a report Provisional. All-numeric reports remain self-reported.

An available-response average can be displayed for an incomplete assessment, clearly labelled, but an overall interpretation requires both coverage and domain thresholds. No score out of 125 is used. Strongest/weakest domains require at least three numeric responses; tied domains are selected by stable domain ID order.

`recommendations.php` contains all 25 question recommendations, consequences, unknown-response verification tasks, suggested roles, timings and completion checks. Update its version when wording changes. Numeric priorities sort important low-scoring controls first, then other scores 1, 2 and 3, with score/ID tie-breaks. Unknown/missing items are separate; N/A responses require applicability review, including an explicit D5 owner-review question. The action plan includes up to three leading numeric gaps, then important verification and applicability tasks, with up to five actions total.

The explicit `verified_capability_allowlist` is empty by default. Add a question's `software_capability` and `software_note` only after verifying the capability and adding its key to that allowlist. Do not add unverified reminders, automated reconciliation, backup or recovery-test claims. Generic branded discussion/demo invitations do not assert a product capability. Recommendations remain useful without purchasing software.

`pdf.php` reuses the existing FPDF design, navy/red treatment, bundled fonts and contact footer. Its customer report contains cover, executive summary, scorecard, every question's findings, confirmation/applicability items, a suggested 30-day plan and an optional discussion invitation. All submitted answers and optional notes are retained in the detailed report. Page counts depend on response text and notes. The earlier raw-answer PDF function remains available but is no longer the attachment in the assessment flow.

## Persistence and delivery

`report-store.php` saves one unpredictable 128-bit reference, immutable structured submission, canonical answers, assessment/scoring/catalogue versions, full question/rule/catalogue snapshots, deterministic report data, separate PDF status and separate admin/user email statuses. SMTP credentials are never copied into a report record. A versioned snapshot allows later reproduction without depending on an edited catalogue.

A report is saved before PDF generation. Generation failure records `pdf.status=failed`; retry retains the same reference. Successfully generated PDF bytes are stored privately with a SHA-256 integrity check. PHPMailer sends two separate messages using the existing SMTP configuration. The success page is displayed only after both SMTP sends succeed. SMTP acceptance is not a guarantee of inbox receipt.

The session stores the pending reference. Retry reloads the saved structured data, retains the original answers even if fields are edited, reuses the same PDF bytes and sends only recipients not marked sent. A record lock serialises concurrent retries. Report-generation and email-delivery status are independent. A fresh submission after success creates a new assessment; replaying its old form token fails CSRF validation.

The automated retry button requires the original session. After session expiry, a trusted server-side administrator can call `assessment_process_record()` with the private reference and the same PDF/sender callbacks used in `backend.php`. There is intentionally no public report retrieval or retry-by-reference endpoint. Never infer consent for extra marketing messages from this report flow.

Deploy when no old in-session assessment deliveries are pending: the previous questionnaire-only session format is not migrated to the new customer report. Preserve private report storage thereafter. Configure owner-approved retention, private backups and deletion procedures for these contact/answer/report records. No automatic deletion period or backup guarantee is introduced here.

File persistence and SMTP cannot share an atomic transaction. If a process crashes after SMTP accepts a message but before its sent status is saved, verify delivery before manually retrying. Routine failures and retries are covered by the tests; absolute exactly-once external email delivery is not claimed.

## Verification

Run `php tests/report-regression.php` with iconv enabled. Set `REPORT_TEST_OUTPUT=/private/path/fixture.pdf` to retain the fictional fixture PDF for review. Run `PHP_COMMAND='php' python3 tests/submission-integration.py` for an actual local PHP HTTP/PHPMailer SMTP test. Python uses its standard library; optional PyMuPDF adds PDF text checks. These tests send only to a local fake SMTP server, not Gmail.

The regression checks the supplied 78/22 fixture, five domain averages, 3.55/5, 91.67% coverage, control priorities, unknown/N/A review, all-1/all-5/no-numeric/missing/all-N/A responses, insufficient domain coverage, invalid values, exact interpretation boundaries and configurable thresholds. Persistence tests cover PDF failure, immutable snapshots, private permissions, unsafe directory rejection and partial-email retry with unchanged PDF bytes. HTTP integration checks validation, escaped customer text, legacy normalisation, attachments to both recipients, success/error states and replay rejection.

Before production use: verify private-directory permissions and durable storage; test the configured Gmail route and both inboxes; confirm the server's access-denial rules. This implementation does not change the live server's SMTP settings or deploy itself there.

## Roadmap presentation, version 2

The presentation now adds a one-page overview for each domain with priority observations or confirmation items, a delivery plan, and suggested KRAs/KPIs. All original questions, actual responses, notes, consequences and recommendations remain in the detailed observation register. Navy/red headers, score panels, shaded cards and repeating table headings follow the report design while keeping readable type. Longer names, notes or catalogue text can add pages.

`roadmap-catalogue.php` defines 25 proposed measurement definitions, evidence sources, review cadence and suggested targets. `recommendations.php` includes it in the immutable catalogue snapshot (recommendation version 1.1.0; roadmap measures 1.0.0). `roadmap.php` builds domain plans, a full 25-item KRA/KPI register, three 30/60/90-day phases and an unfilled Day-90 review template. Presentation version 2.0.0 is retained in new report metadata. No scoring thresholds or numeric-priority rules have changed.

KRA means area of responsibility; KPI means the measure used to check progress. Operational baselines are explicitly unmeasured by the questionnaire. Targets are proposals, not clinic agreements or guaranteed outcomes. Unknown/missing answers require verification before planning; N/A measures require an applicability review before adoption. Zero event denominators mean not measurable, not 100 percent performance. Relevant process tests can be agreed when events such as staff exits do not occur. Actual baseline, agreed target and Day-90 result remain null in generated review data until a later evidence review. This PDF is a static review worksheet; no new KPI data-entry application is introduced.

The proposed 90-day outcome is conditional on carrying out agreed actions. It does not claim future score increases, financial returns or completed improvements. The immediate 30-day plan and early control-gap timings remain in place. Reports created with earlier catalogue snapshots do not receive invented new planning content on retries, and successfully generated reports continue to reuse their original PDF bytes.
