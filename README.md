# My Physio Saathi

Marketing website for https://www.myphysiosaathi.in/, plus hidden pricing and clinic assessment pages.

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

Page copy: `index.html`. Styling: `styles.css`. Form and interactions: `site.js`. Workflow images: `assets/`. Sharing card: `og.png`. Assessment/report implementation and configuration: `assessment/`. Regression checks: `tests/`.
