# Revised clinic assessment and customer report

Entry page: `free-assesment.php` (existing spelling). It stays hidden from landing-page navigation and uses `noindex,nofollow`.

## Active specification

This release implements the developer specification dated 9 October 2026. There is one active revised 25-question assessment. No historical question support, migration or score conversion is included. Assessment/question revision: `2026-10-09.2`; scoring and recommendation revisions: `2.0.0`; roadmap measurement revision: `2.0.0`; presentation revision: `3.0.0`.

`questions.json` preserves the exact revised question text and domain titles. `rating-scale.php` supplies the form's levels and descriptions. It is also snapshotted in scoring rules for report reproducibility. Scores describe how reliably a process is carried out and checked, not whether the clinic purchased software. Paper processes can score 5. Scores are never forced below 4 for lead qualification.

The meaning of several IDs changed: A1 retrieval with absence cover; A3 duplicate-file prevention; A5 file returns; B1 treatment goals; B2 notes before visit completion; B3 written handover; B4 treatment changes; B5 progress review at the doctor-chosen point; D1 daily completeness checks; D5 checking corrections; E1 public viewing; E2 staff access. Every recommendation and KPI mapping has been updated accordingly.

## Deployment

Upload `free-assesment.php`, the entire `assessment/` directory, the shared `includes/` directory and `shared-chrome.css`, beside the landing page. The assessment header, footer and tracking now depend on those shared includes. Preserve ignored SMTP configuration and existing root `.htaccess` / virtual-host settings. The live server is not deployed automatically by a GitHub push.

Requirements: PHP 8.1+, sessions, OpenSSL, iconv, zlib, HTTPS and outbound SMTP access. PHPMailer 7.1.1 and FPDF 1.86 remain bundled without Composer. No external AI API, database or build process is introduced.

Use `SMTP_SETUP.md` for the shared assessment/homepage SMTP configuration. `assessment/config.php` and `config.local.php` remain supported and ignored by Git. Keep credentials outside source control. Durable private report storage must be outside DocumentRoot: set `report_directory` in private configuration or `ASSESSMENT_REPORT_DIRECTORY` in the PHP environment. Default: `myphysiosaathi-private/reports` beside DocumentRoot. Pre-create it for the PHP user with mode 0700. Report files are mode 0600. A public-directory path, including a symlink resolving there, is rejected; there is no public storage fallback.

Configure Apache AllowOverride for `assessment/.htaccess`, which exposes only `form.css` and `form.js`. On Nginx configure equivalent denial of direct internal-file access. Do not upload `tests/` to a public document root. Verify permissions, access rules, durable storage and both real Gmail inboxes before release.

## Input and evidence

All seven clinic fields and exactly the 25 expected question IDs are required. Telephone numbers remain strings. Beds/treatment couches become a non-negative integer; zero is valid. Unsupported, blank, array-valued or incomplete answers are rejected before PDF generation, with question-specific form errors. Numeric strings 1-5 become integers. Supported `unknown` / `unsure` values become `unknown`; `na` stays nonnumeric. Unsupported values are never clamped or guessed.

There is one optional explanation after each domain, stored in `domain_notes` keyed A-E. The limit is 2,000 Unicode characters per domain, not 2,000 bytes. Blank notes neither block submission nor reduce a score. Customer content is escaped in HTML and rendered as inert plain PDF text. Do not submit patient names or personal information.

For each 4-5 rating, a blank domain note yields `Reported score, example not provided`; a supplied note yields `Domain explanation supplied; not independently verified`. The compact report prints a labelled excerpt of longer domain explanations, with a warning that a domain note does not verify every question. Complete original notes remain in the immutable private submission. Long profile fields may also display labelled excerpts to keep page 1 readable. No automatic evidence verification or score adjustment occurs.

Standard bundled PDF fonts support English and Western European text. Gujarati/Hindi may be transliterated or unsupported. Full local-script rendering needs a separate PDF-font upgrade; this release retains the existing engine.

## Scores and status

`report.php` uses original structured form data, never text extracted from PDFs. Domain averages use numeric answers only. Overall average is question-weighted. No numeric answers yields null. Coverage is numeric count divided by 25 minus N/A count; all-N/A coverage is null. Coverage is labelled `Numeric coverage excluding respondent-selected N/A`, not confidence or verified performance.

`scoring-rules.php` contains configurable unrounded interpretation thresholds: below 2 established-process need; 2 to below 3 inconsistent practice; 3 to below 4 working foundation; 4 to below 4.5 reliable reported practice; 4.5-5 strong reported practice. Display rounds to two decimals only.

A domain interpretation requires at least three numeric responses. Overall interpretation and customer-facing overall average require at least 80% numeric coverage and at least three numeric responses in every domain. Otherwise the report status and interpretation are `Insufficient information`, while eligible domain findings remain. If eligible, unknown/unreviewed N/A gives `Provisional`; all-numeric gives `Complete self-reported assessment`. Insufficient information takes precedence. These are internal product eligibility rules, not validated industry thresholds.

## Findings, recommendations and roadmap

`recommendations.php` covers every exact question, domain, control flag, conditional consequence, actions for scores 1-5, unknown verification, N/A review, role, timing and completion check. Scores 1 establish; 2 standardise responsibility; 3 add completion checks/exceptions; 4 maintain checks; 5 recognise strength and maintain periodic review. No example note is treated as independent evidence.

Controls C1/C2/C4 and E1-E5 scoring 1-2 are prominently flagged regardless of average. Numeric priorities sort low-scoring controls, other 1s, other 2s, then 3s, with score/ID tie-breaks. Executive summary shows up to three supported numeric priorities, without inventing gaps. Unknown controls lead a separate confirmation list. N/A always requires applicability review. D2 may genuinely not apply without a reception function; D5 asks why checking corrections is considered inapplicable.

`roadmap-catalogue.php` defines suggested KPIs, formulas, evidence and targets for all 25 revised IDs. `roadmap.php` retains full structured domain plans, KRA/KPI definitions, proposed targets, a Suggested 90-day roadmap and later-review data in the private report record. Presentation version 4.0.0 consolidates the customer PDF into exactly eight pages: page 1 profile in a shaded-label table; page 2 executive summary, domain scorecard and confirmation IDs; pages 3-7 one domain per page with all five exact questions, actual responses, concise actions, suggested owners/timings, completion checks and KRAs/KPIs; page 8 up to five actions, a concise 90-day roadmap, review instructions and optional next step. Duplicated registers and repeated domain summaries are removed. Full catalogue recommendations, measurement formulas and proposed targets remain in the frozen private report; the PDF uses concise actions and asks the clinic to agree targets after measuring baselines. No text is silently clipped; overflow is an explicit generation failure. The five domain pages keep at least 9-point text. KRA/KPI labels are explained in plain language. Ratings are not KPI baselines. Baselines display `Baseline to be measured`; suggested targets require clinic confirmation. Zero opportunities mean `No eligible cases`, not 0% or 100%. Later baseline, agreed target and actual results remain null until a human review supplies them. No achieved outcomes, future score increases or savings are promised.

The immediate action plan has up to five supported improvement or verification actions. All-4/5 clinics receive strengths and maintenance guidance without fabricated problems; they can be low-need leads. Suggested timings and roles are proposals, not clinic commitments.

The explicit verified software capability allowlist is empty. Only independently verified capabilities deliberately added to the allowlist may be mentioned in question-specific product notes. Do not claim automated reminders, follow-up, reconciliation, backups or recovery testing. All recommendations remain useful with paper or software and without a purchase.

## Private persistence and email retries

`report-store.php` assigns customer references as `DDMMYYYY-number`, for example `09102026-1`, using the submission date in Asia/Kolkata and a durable lock-protected daily counter. The sequence restarts each India calendar day, and retries never allocate another reference. Keep the private `.seq` counters with the records when moving or restoring storage; do not reset them on deployment. Interrupted allocation can leave a harmless sequence gap. The reference appears in the PDF, receipt, email subject and attachment name. Private JSON/PDF/lock filenames still use a separate unpredictable 128-bit storage ID; human references never become public download paths. `report-store.php` saves that storage ID and customer reference, canonical structured submission, domain notes, version identifiers, complete question/rule/rating/catalogue snapshots, report data, separate PDF status and separate admin/user delivery statuses. SMTP credentials are not copied into records. A record is stored before generation; failed generation retries retain the same reference. Generated PDF bytes are stored with an integrity hash.

PHPMailer sends separate copies to the configured administrator and submitter. Success is displayed only after both sends succeed. A retry reloads the original frozen submission, reuses the same PDF and sends only recipients not marked sent. A private record lock serialises retries. The original session provides the pending reference; there is no public report-download or retry-by-reference endpoint. SMTP acceptance cannot guarantee inbox receipt. A crash after SMTP acceptance but before status persistence can require manual delivery verification before retry, since SMTP and file storage are not one atomic transaction.

Agree private retention, deletion and backup arrangements operationally. No automatic backup guarantee or fixed retention period is introduced by this code.

## Acceptance checks

Run `php tests/report-regression.php` with iconv enabled. Optional `REPORT_TEST_OUTPUT=/private/path/fixture.pdf` retains the fictional synthetic fixture PDF. Run `PHP_COMMAND='php' python3 tests/submission-integration.py` for the local HTTP and real PHPMailer fake-SMTP integration test. Optional PyMuPDF checks PDF text. No real customer messages are sent by the tests.

Checks cover all-3, all-5 without notes, all-4 with domain explanations, all-1, all unknown, all N/A, insufficient domain/overall coverage, revised meanings, invalid/missing/extra/array answers, zero/invalid beds, 2,000-character Unicode notes and evidence labels without score reduction. The synthetic fixture remains sum 78, count 22, averages A3.40/B4.50/C2.50/D3.50/E3.80, overall3.55 and coverage91.67%, with C4/C5/D1 priorities, C1/B5 confirmations and D5 applicability review. Persistence checks cover version snapshots, PDF failure, private permissions, partial delivery and immutable retries.

The page budget is checked for the supplied fixture, all score levels, unknown/N/A, maximum-length notes and profile fields, and 100 deterministic mixed-answer cases. `REPORT_TEST_DIRECTORY` retains fictional layout fixtures. `PHP_COMMAND=php python3 tests/reference-concurrency.py` checks simultaneous reference allocation and the India-date boundary.

## Production processing errors

`assessment/diagnostics.php` records `MPS_ASSESSMENT_FAILURE` in the PHP error log with a random support code, stage, safe failure category, exception class, source basename and line. The browser displays the same support code. Raw exception text, submitted fields, answers and SMTP credentials are not logged by this helper. Upload `backend.php` and `diagnostics.php` together. Keep production `display_errors` off.

Look up the matching support code in the web server/PHP-FPM error log. `storage.public_path` requires a report path outside DocumentRoot; `storage.unavailable`, `storage.write` or `reference.permissions` requires writable private storage owned by the PHP worker. `catalogue.mismatch` normally means question/rule/recommendation files were uploaded from different revisions. `runtime.iconv` or `runtime.zlib` requires enabling the extension for the web PHP runtime, not just the CLI. `runtime.missing_file` requires uploading the complete bundled dependencies. `pdf.layout` identifies a report-generation layout failure, not an SMTP setting problem. Do not reset daily reference counters or delete saved assessments to troubleshoot.
