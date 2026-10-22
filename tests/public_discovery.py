"""Read-only initial-HTML and sitemap checks. No JavaScript or form submissions.

Usage: python tests/public_discovery.py --base-url https://www.myphysiosaathi.in/
The same checks run against the local PHP server in site-integration.py.
"""
import argparse
from datetime import date
from html.parser import HTMLParser
import json
import re
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

ORIGIN = 'https://www.myphysiosaathi.in/'
PUBLIC = [ORIGIN, ORIGIN + 'pricing.php']


class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.words = []
        self.canonical = []
        self.robots = []
        self.refresh = False
        self.ignore = None

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag in ('style', 'script'):
            self.ignore = tag
        if tag == 'link' and 'canonical' in attrs.get('rel', '').lower().split():
            self.canonical.append(attrs.get('href'))
        if tag == 'meta':
            if attrs.get('name', '').lower() in ('robots', 'googlebot', 'bingbot'):
                self.robots.append(attrs.get('content', '').lower())
            if attrs.get('http-equiv', '').lower() == 'refresh':
                self.refresh = True

    def handle_endtag(self, tag):
        if tag == self.ignore:
            self.ignore = None

    def handle_data(self, value):
        if self.ignore is None:
            self.words.append(value)

    @property
    def text(self):
        return ' '.join(' '.join(self.words).split())


def audit_public_site(base_url):
    """Return explicit PASS/FAIL evidence; network failures never count as passes."""
    base_url = base_url.rstrip('/') + '/'
    checks = []

    def check(name, passed, detail):
        checks.append({'check': name, 'passed': bool(passed), 'detail': detail})

    def fetch(path):
        url = urllib.parse.urljoin(base_url, path)
        try:
            request = urllib.request.Request(url, headers={'User-Agent': 'MPSPublicDiscoveryCheck/1.0'})
            with urllib.request.urlopen(request, timeout=20) as response:
                return response.read(1_000_000).decode('utf-8'), response.headers
        except (OSError, UnicodeError, urllib.error.URLError) as error:
            check('HTTP ' + (path or '/'), False, str(error))
            return None, None

    for path, canonical in [('', PUBLIC[0]), ('pricing.php', PUBLIC[1])]:
        html, headers = fetch(path)
        if html is None:
            continue
        page = Page()
        page.feed(html)
        label = path or '/'
        directives = ','.join(page.robots) + ',' + headers.get('X-Robots-Tag', '').lower()
        check(label + ' indexable HTML', not page.refresh and not re.search(r'\b(noindex|none)\b', directives),
              'Must serve content without a meta refresh or noindex directive.')
        check(label + ' canonical', page.canonical == [canonical], str(page.canonical))
        if not path:
            expected = [
                'Register patient', 'Create treatment episode', 'Record a session',
                'Reuse and adapt', 'Track charges and payments', 'Review patient history',
                'Clinic Admin', 'Doctor or Therapist', 'Receptionist', 'Patient',
                'Does the software include appointment booking or automated reminders?',
                'Can patients see their treatment and payment history?',
                'Can we bring our existing paper or spreadsheet records into the system?',
                'What is involved in setup and staff training?', 'How much does it cost?',
                'How are patient data, backups, and access managed?',
                'No. Appointment booking and automated reminders are not current features.',
                'The documented patient portal lets patients view their own treatment episodes',
                'Migration scope must be reviewed during the demonstration.',
                'The setup and training process will be discussed based on your clinic',
                'Pricing depends on the agreed setup, hosting, onboarding, and support scope.',
                'Patient-data handling, hosting, backups, recovery, and access responsibilities',
            ]
        else:
            expected = [
                'Clinic Essential', 'Clinic Launch', 'Clinic Launch Plus',
                '₹31,000', '₹36,000', '₹49,000', '₹6,500', '+ tax',
                'Annual renewal from Year 2', 'Two sessions of 30 minutes each',
                'Four sessions of 30 minutes each', 'Guided system demo',
                'Existing patient-record assistance', 'Additional work',
            ]
        missing = [text for text in expected if text not in page.text]
        check(label + ' initial answer HTML', not missing, 'Missing: ' + repr(missing))

    xml, _ = fetch('sitemap.xml')
    if xml is not None:
        try:
            root = ET.fromstring(xml)
            ns = '{http://www.sitemaps.org/schemas/sitemap/0.9}'
            entries = root.findall(ns + 'url')
            urls = [entry.findtext(ns + 'loc') for entry in entries]
            check('Sitemap public URLs', root.tag == ns + 'urlset' and urls == PUBLIC,
                  'Only the canonical homepage and pricing page: ' + repr(urls))
            dates = [entry.findtext(ns + 'lastmod') for entry in entries]
            valid_dates = all(value and date.fromisoformat(value) <= date.today() for value in dates)
            check('Sitemap modification dates', bool(entries) and valid_dates, repr(dates))
        except (ET.ParseError, ValueError) as error:
            check('Sitemap XML', False, str(error))
    robots, _ = fetch('robots.txt')
    if robots is not None:
        sitemap_lines = [line.strip() for line in robots.splitlines()
                         if line.strip().lower().startswith('sitemap:')]
        check('Robots sitemap discovery', 'Sitemap: ' + ORIGIN + 'sitemap.xml' in sitemap_lines,
              repr(sitemap_lines))
    return checks


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--base-url', required=True)
    args = parser.parse_args()
    checks = audit_public_site(args.base_url)
    print(json.dumps(checks, indent=2, ensure_ascii=False))
    raise SystemExit(0 if all(check['passed'] for check in checks) else 1)
