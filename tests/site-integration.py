"""Local HTTP and PHPMailer checks. Captures email locally, never sends customer mail."""
from pathlib import Path
import email
from email import policy
import http.cookiejar
import json
import os
import re
import shlex
import socket
import socketserver
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.request
import urllib.parse

root = Path(__file__).resolve().parents[1]
captured = []
fail_once = set()

class SMTP(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 localhost test\r\n')
        recipients = []
        while True:
            line = self.rfile.readline()
            if not line:
                return
            command = line.decode().strip()
            if command.startswith(('EHLO', 'HELO')):
                self.wfile.write(b'250 localhost\r\n')
            elif command.startswith('MAIL'):
                recipients = []
                self.wfile.write(b'250 OK\r\n')
            elif command.startswith('RCPT'):
                rejected = next((address for address in fail_once if address in command), None)
                if rejected:
                    fail_once.remove(rejected)
                    self.wfile.write(b'550 simulated rejection\r\n')
                else:
                    recipients.append(command)
                    self.wfile.write(b'250 OK\r\n')
            elif command == 'DATA':
                self.wfile.write(b'354 send\r\n')
                parts = []
                while True:
                    data = self.rfile.readline()
                    if not data:
                        return
                    if data == b'.\r\n':
                        break
                    parts.append(data[1:] if data.startswith(b'..') else data)
                captured.append(email.message_from_bytes(b''.join(parts), policy=policy.default))
                self.wfile.write(b'250 accepted\r\n')
            elif command == 'QUIT':
                self.wfile.write(b'221 bye\r\n')
                return
            else:
                self.wfile.write(b'250 OK\r\n')

smtp = socketserver.ThreadingTCPServer(('127.0.0.1', 0), SMTP)
threading.Thread(target=smtp.serve_forever, daemon=True).start()
with tempfile.TemporaryDirectory(prefix='mps-site-test-') as directory:
    directory = Path(directory)
    (directory / 'sessions').mkdir()
    config = directory / 'smtp.php'
    config.write_text("<?php return ['enabled'=>true,'smtp_host'=>'127.0.0.1','smtp_port'=>"
                     + str(smtp.server_address[1])
                     + ",'smtp_encryption'=>'','smtp_auth'=>false,'from_email'=>'sender@example.test',"
                     "'admin_email'=>'admin@example.test','state_directory'=>'"
                     + str(directory / 'rates') + "','report_directory'=>'"
                     + str(directory / 'reports') + "'];")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    php = shlex.split(os.environ.get('PHP_COMMAND', 'php'))
    env = os.environ.copy()
    env['ASSESSMENT_CONFIG_PATH'] = str(config)
    log = open(directory / 'php.log', 'w')
    process = subprocess.Popen(php + ['-d', 'session.save_path=' + str(directory / 'sessions'),
                                     '-S', f'127.0.0.1:{port}', '-t', str(root)],
                               env=env, stdout=log, stderr=log)
    base = f'http://127.0.0.1:{port}/'
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(path):
        return client.open(base + path).read().decode()

    def post(fields):
        request = urllib.request.Request(base + 'enquiry.php', json.dumps(fields).encode(),
                                         {'Content-Type': 'application/json'})
        try:
            response = client.open(request)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, json.loads(response.read())

    try:
        for _ in range(50):
            try:
                home = get('index.php')
                break
            except (OSError, urllib.error.URLError):
                time.sleep(0.1)
        else:
            raise RuntimeError('PHP server did not start')
        pages = {'index.php': home, 'pricing.php': get('pricing.php'),
                 'free-assesment.php': get('free-assesment.php')}
        for path, html in pages.items():
            assert html.count('Shared tracking: HEAD') == html.count('Shared tracking: BODY') == 1
            assert html.index('Shared tracking: HEAD') < html.index('</head>')
            assert html.index('<body>') < html.index('Shared tracking: BODY') < html.index('<header')
            assert 'href="pricing.php"' in html and 'class="site-header"' in html
            assert 'Tejas P Mehta' in html and 'href="tel:+919825647083"' in html
            assert '<?php' not in html
        for path, html in pages.items():
            header = re.search(r'<header.*?</header>', html, re.S).group()
            for href in re.findall(r'href="([^"]+)"', header):
                target = urllib.parse.urlsplit(urllib.parse.urljoin(base + path, href))
                destination = get(target.path.lstrip('/'))
                if target.fragment:
                    assert f'id="{target.fragment}"' in destination, (path, href)
        footers = [re.search(r'<footer.*?</footer>', pages[p], re.S).group()
                   for p in ('index.php', 'pricing.php')]
        assert footers[0] == footers[1]
        assert 'aria-current="page"' in pages['pricing.php']
        for path in ('index.php', 'pricing.php'):
            assert '<meta name="keywords"' in pages[path]
            assert '<meta name="description"' in pages[path]
            assert '<link rel="canonical"' in pages[path]
            assert 'noindex' not in pages[path]
        assert 'noindex' in pages['free-assesment.php']
        assert 'url=index.php' in get('index.html') and 'url=pricing.php' in get('pricing.html')
        assert 'My Physio Saathi' in get('')

        setup = json.loads(get('enquiry.php'))
        assert setup['configured']
        data = {'csrf': setup['csrf'], 'request_id': 'demo-test-request-00001',
                'full_name': '<script>alert(1)</script>', 'clinic_name': '<b>Example Clinic</b>',
                'city': 'Surat', 'mobile_number': '9876543210', 'email': 'clinic@example.test',
                'enquiry_type': 'Product demo', 'message': '', 'website': ''}
        time.sleep(2.1)
        assert post(data)[0] == 200 and len(captured) == 1
        assert post(data)[0] == 200 and len(captured) == 1
        changed = dict(data, city='Vadodara')
        assert post(changed)[0] == 409
        invalid = dict(data, request_id='invalid-test-request-0001', email='invalid')
        assert post(invalid)[0] == 422 and len(captured) == 1

        audit = dict(data, request_id='audit-test-request-00001', enquiry_type='Free clinic workflow audit')
        fail_once.add('clinic@example.test')
        status, result = post(audit)
        assert status == 503 and 'enquiry has been received' in result['message']
        assert len(captured) == 2
        assert post(audit)[0] == 200 and len(captured) == 3
        assert post(audit)[0] == 200 and len(captured) == 3
        invitation = captured[-1]
        assert invitation['To'].endswith('<clinic@example.test>')
        assert 'assessment' in invitation['Subject']
        html = invitation.get_body(preferencelist=('html',)).get_content()
        plain = invitation.get_body(preferencelist=('plain',)).get_content()
        assert '<script>' not in html and '&lt;script&gt;' in html and '&lt;b&gt;Example Clinic&lt;/b&gt;' in html
        assert 'https://www.myphysiosaathi.in/free-assesment.php' in html and 'free-assesment.php' in plain
        assert 'My Physio Saathi' in html and '#0f172b' in html and '#e1201b' in html
        assert 'not a clinical, security or compliance certification' in html
        assert 'admin@example.test' not in invitation['To']

        assessment = dict(audit, request_id='assessment-request-00001', enquiry_type='Free clinic workflow assessment')
        fail_once.add('admin@example.test')
        assert post(assessment)[0] == 500 and len(captured) == 3
        assert post(assessment)[0] == 200 and len(captured) == 5
        assert post(assessment)[0] == 200 and len(captured) == 5
    finally:
        process.terminate()
        process.wait(timeout=5)
        log.close()
        smtp.shutdown()
        smtp.server_close()
print('PASS: shared navigation/footer/tracking, public pricing SEO, redirects, demo-only email, branded assessment email, escaping, recipient isolation and partial-send retries.')
