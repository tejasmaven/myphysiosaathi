# Selected common action items: 22 October 2026

Scope: COM-02, COM-03, COM-04, COM-05, COM-06, COM-07, COM-08, COM-22 and COM-33 in the **Common Action Items** tab of the [task tracker](https://docs.google.com/spreadsheets/d/1TPWsd8QmAeIaMKz6rSzwFVw6CsQFhWPpOx4UPYvzQy4/edit).

No sales copy, commercial offer, feature claims, permissions or AI-training choices were changed. Existing private report access controls and assessment noindex markup remain unchanged. Repository preparation is distinguished from production deployment. No item is fully complete while its stated acceptance criteria remain unmet.

| ID | Work performed | Remaining condition |
| --- | --- | --- |
| COM-02 | Inspected existing files and live robots response. No crawler-specific or training rules introduced. | Owner must approve search/training crawler choices. The sitemap-only robots file does not complete that policy. |
| COM-03 | Read-only requests with ordinary, simulated Googlebot and simulated Bingbot user agents returned HTTP 200 on `/`, `/index.php` and `/pricing.php`. Direct PHP pages contained normal page content. | Hosting/CDN/WAF configuration, verified crawler requests/logs and the final permitted crawler list are unavailable. User-agent simulation cannot verify real crawler-origin access. The canonical homepage routing issue below also remains. |
| COM-04 | Verified existing initial HTML and added repeatable regression checks for all six FAQ questions/answers, six workflow steps, role labels and key pricing inclusions. No click or JavaScript fetch is needed for those texts. Prepared a directory-index reset in the Apache merge snippet. | Live `/` returns the noindex redirect stub rather than the real homepage. Merge routing fix into production `.htaccess`, preserving existing rules, then rerun the public check. No separate product-detail pages currently exist. |
| COM-05 | Added XML sitemap containing only `/` and `/pricing.php`, with lastmod based on the last significant page-content/navigation commit. Added sitemap discovery in robots.txt and validation in the local test. No assessment, endpoint, report or patient URLs are listed. | Upload both files, fix root routing, verify live responses, then submit the sitemap in Google Search Console and Bing Webmaster Tools and record the results. |
| COM-06 | Checked available integrations. No authenticated Search Console or Bing Webmaster Tools access is available. | Owner access/verification and console sitemap submissions and page inspections are required. GA and Clarity tags do not establish this. |
| COM-07 | Reviewed current H1 and page headings. | Skipped because the requested explicit category headline and product definition require content changes. |
| COM-08 | Reviewed the six current FAQ answers in initial HTML. | Skipped because seven revised buyer answers require content changes and approved product/commercial facts. |
| COM-22 | Checked live and repository homepage/pricing markup: neither currently contains an `application/ld+json` block. | Structured-data implementation in other tasks must exist before syntax, fact consistency and applicable search-display validation can be completed. No identities, offers or review data were invented to satisfy a validator. |
| COM-33 | No llms.txt created. | Skipped because publishing this optional experiment requires a decision. |

## Production observations

Read-only HTTP GET checks were run on 22 October 2026. They did not execute JavaScript, submit forms or send email.

| URL path | Observed HTTP result | Initial HTML observation |
| --- | --- | --- |
| `/` | 200 | 300-character compatibility page; `noindex,follow`; meta refresh to `index.php`; no homepage FAQ or product body. |
| `/index.php` | 200 | Full homepage content, six FAQ answers and workflow explanations present; canonical points to `/`; no JSON-LD. |
| `/pricing.php` | 200 | Package prices, renewal and support inclusions present; canonical points to `/pricing.php`; no JSON-LD. |
| `/index.html` | 200 | Same noindex compatibility redirect page. |
| `/robots.txt` | 404 | Not published at the time of review. |
| `/sitemap.xml` | 404 | Not published at the time of review. |

Simulated Googlebot and Bingbot request headers received the same root redirect stub and direct-page content. These checks did not originate from Google/Bing crawler IP ranges, so they do not establish the actual firewall treatment of those crawlers.

## Deployment handoff

1. Upload `sitemap.xml` and `robots.txt` to `/var/www/html/myphysiosaathi/`. The robots file only advertises the sitemap; the unapproved search/training policy remains open.
2. Merge `apache-page-routes.conf.example` into the **existing** `/var/www/html/myphysiosaathi/.htaccess`. Preserve HTTPS, www, private-access and other existing directives. In particular, apply the pair:

   ```apache
   DirectoryIndex disabled
   DirectoryIndex index.php index.html
   ```

   The snippet also retains permanent redirects from the two old `.html` URLs. Do not upload the snippet under its example filename and expect Apache to apply it, and do not replace the existing `.htaccess` wholesale.
3. Run the read-only verification:

   ```bash
   python tests/public_discovery.py --base-url https://www.myphysiosaathi.in/
   ```

   All checks should pass. Verify `/` directly serves the complete homepage without meta refresh/noindex, robots and sitemap are HTTP 200, and the PHP pages remain functional. This test does not execute analytics, create enquiries or send email.
4. From the owner's authenticated Google Search Console and Bing Webmaster Tools accounts, verify ownership, submit `https://www.myphysiosaathi.in/sitemap.xml`, inspect `/` and `/pricing.php`, and record actual submission/inspection results. Do not use the retired Google sitemap ping endpoint.
5. Finish hosting-policy checks with the hosting administrator and verified crawler logs. Then update only the items whose complete acceptance criteria are satisfied.

## Verification and reference limits

Local PHP HTTP tests run the new discovery checks together with existing navigation, tracking, canonical and PHPMailer capture-SMTP integration checks. Local success does not prove the Apache configuration or live console state. No SMTP credentials are read or committed by the discovery checker.

The sitemap dates are 9 October 2026, the significant page-content/navigation update in commit `5acdf7c`. Later analytics-only updates are not used to manufacture a content-freshness date. The date validation checks format and future dates; the documented Git provenance supplies the content-history basis.

Implementation references:

- [Google: build and submit a sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)
- [Google: robots.txt syntax and sitemap field](https://developers.google.com/crawling/docs/robots-txt/robots-txt-spec)
- [Google: verify crawler requests](https://developers.google.com/crawling/docs/crawlers-fetchers/verify-google-requests)
- [Apache: DirectoryIndex list reset](https://httpd.apache.org/docs/2.4/mod/mod_dir.html#directoryindex)

No rich-result eligibility or citation benefit has been asserted. COM-22 remains unfulfilled because JSON-LD has not yet been implemented, rather than being treated as a successful empty validation.
