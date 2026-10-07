# My Physio Saathi

Static single-page marketing website for https://www.myphysiosaathi.in/.

## Deploy

Upload index.html, styles.css, site.js, og.png, and assets/ into the server document root. No build, package installation, PHP, or database is required for the website. Email submission uses a separately hosted API.

### API configuration

For a fresh deployment, copy config.example.js to config.js and put the browser API key supplied in the private enquiry integration guide into emailApiKey. The endpoint is already set. config.js is ignored by Git and must never be committed to this public repository.

For the existing live deployment, preserve your working config.js when uploading or pulling changes. Also preserve the server's existing .htaccess and virtual-host settings; they are not managed by this repository.

Without a configured API key, the form remains disabled and shows a direct-call alternative. The integration requires email, maps snake_case fields, handles server validation/rate-limit/error responses, and clears inputs only after confirmed API success. Tejas confirmed receipt of the integration test email. Verify one submission after deploying to a new origin.

## Content and interaction

- Six sections: opening, clinic problems, patient journey, role features, FAQs, contact.
- Red/white card hover states and reduced-motion support.
- Six stacked workflow steps with changing images on hover, tap, click, and keyboard focus.
- One FAQ open at a time with matching question/answer backgrounds.
- Six illustrative SVG placeholders; replace with approved application screenshots later.
- Canonical and social-sharing URLs use the official www domain.

No real patient details, product testimonials, invented prices, or appointment/reminder claims are included.

## Editing

Page copy and image references: index.html. Styling: styles.css. Form and interactions: site.js. Workflow images: assets/. Sharing card: og.png.
