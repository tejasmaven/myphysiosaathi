# Homepage enquiries via PHPMailer

Upload these files beside the existing homepage:
- index.html
- site.js
- enquiry.php

The existing assessment/lib/PHPMailer/ folder must remain in place.

The homepage now submits to enquiry.php on the same website. The external email API and config.js are no longer loaded or used by the homepage.

The endpoint reuses the working assessment SMTP configuration:
- ASSESSMENT_CONFIG_PATH environment variable, if configured.
- Otherwise assessment/config.local.php, then assessment/config.php.
- SMTP environment variables override the loaded settings; see SMTP_SETUP.md.

Keep your actual private configuration unchanged. No SMTP password or API key is committed. Both forms use the same sender and admin_email.

The admin receives the demo or free-audit enquiry. Reply-To is the clinic contact's email. A confirmation is shown on the webpage only after PHPMailer successfully submits the message to SMTP. This flow sends no PDF and does not send a separate acknowledgement email to the visitor.

Server and client validation, session CSRF protection, a honeypot and rate limiting are included. Successful requests are remembered in the session to avoid duplicate messages after a network retry. Failed SMTP submissions show an error and allow retry.

Local tests passed: JavaScript syntax and request flow; PHP syntax; mock SMTP delivery for demo and audit; admin recipient and Reply-To; invalid data; CSRF; honeypot; delivery failure and retry; successful duplicate prevention; unavailable configuration. No real Gmail email was sent during development.

After uploading, submit one controlled enquiry from the homepage, confirm inbox receipt and the on-page success state. Test on the PHP-enabled HTTPS server, not by opening index.html as a local file. Existing page design and assessment form are unchanged.
