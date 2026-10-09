"""Local-only SMTP and HTTP integration. No real customer email is sent.
Run: PHP_COMMAND='php' python3 tests/submission-integration.py
Optional PDF extraction checks require PyMuPDF (test dependency only).
"""
from pathlib import Path
import threading, socketserver, subprocess, time, urllib.request, urllib.parse
import http.cookiejar, re, json, email, os, tempfile, shlex, socket
root=Path(__file__).resolve().parents[1]
captured=[]; fail_admin=[False]
class SMTP(socketserver.StreamRequestHandler):
 def handle(self):
  self.wfile.write(b'220 localhost test\r\n');rcpt=[]
  while True:
   line=self.rfile.readline()
   if not line:return
   cmd=line.decode().strip()
   if cmd.startswith(('EHLO','HELO')):self.wfile.write(b'250 localhost\r\n')
   elif cmd.startswith('MAIL'):rcpt=[];self.wfile.write(b'250 OK\r\n')
   elif cmd.startswith('RCPT'):
    if 'admin@example.test' in cmd and fail_admin[0]:fail_admin[0]=False;self.wfile.write(b'550 test rejection\r\n')
    else:rcpt.append(cmd);self.wfile.write(b'250 OK\r\n')
   elif cmd=='DATA':
    self.wfile.write(b'354 send\r\n');parts=[]
    while True:
     b=self.rfile.readline()
     if not b:return
     if b==b'.\r\n':break
     parts.append(b[1:] if b.startswith(b'..') else b)
    captured.append((rcpt,b''.join(parts)));self.wfile.write(b'250 accepted\r\n')
   elif cmd=='QUIT':self.wfile.write(b'221 bye\r\n');return
   else:self.wfile.write(b'250 OK\r\n')
server=socketserver.ThreadingTCPServer(('127.0.0.1',0),SMTP)
threading.Thread(target=server.serve_forever,daemon=True).start()
with tempfile.TemporaryDirectory(prefix='mps-integration-') as temp:
 temp=Path(temp);(temp/'sessions').mkdir()
 config=temp/'smtp.php'
 config.write_text("<?php return ['enabled'=>true,'smtp_host'=>'127.0.0.1','smtp_port'=>"+str(server.server_address[1])+",'smtp_encryption'=>'','smtp_auth'=>false,'from_email'=>'sender@example.test','admin_email'=>'admin@example.test','state_directory'=>'"+str(temp/'rates')+"','report_directory'=>'"+str(temp/'reports')+"'];")
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 php=shlex.split(os.environ.get('PHP_COMMAND','php'))+['-d','session.save_path='+str(temp/'sessions')]
 env=os.environ.copy();env['ASSESSMENT_CONFIG_PATH']=str(config)
 log=open(temp/'php.log','w')
 proc=subprocess.Popen(php+['-S','127.0.0.1:'+str(port),'-t',str(root)],env=env,stdout=log,stderr=log)
 base='http://127.0.0.1:'+str(port)+'/free-assesment.php'
 jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 def get():return client.open(base).read().decode()
 def post(data):return client.open(base,urllib.parse.urlencode(data).encode()).read().decode()
 try:
  for attempt in range(50):
   try:html=get();break
   except (urllib.error.URLError,ConnectionError):time.sleep(.1)
  assert html.count('<fieldset')==25 and html.count('type="radio"')==175
  token=re.search(r'name="csrf" value="([^"]+)"',html)[1];time.sleep(3.1)
  data={'csrf':token,'clinic_name':'Example Clinic <script>alert(1)</script>','doctor_name':'Dr Example','location':'Example area','city':'Surat','mobile':'9876543210','email':'clinic@example.test','beds':'0','website':''}
  fixture={'A':[3,3,3,4,4],'B':[4,4,5,5,'unsure'],'C':['unknown',4,3,2,1],'D':[2,3,4,5,'na'],'E':[4,3,5,3,4]}
  for domain,values in fixture.items():
   for i,value in enumerate(values):data['answers['+domain+str(i+1)+']']=str(value)
  data['notes[A3]']='Optional note <script>alert(2)</script>'
  bad=dict(data);bad['csrf']='bad';assert 'session has expired' in post(bad) and not captured
  bad=dict(data);del bad['answers[A1]'];html=post(bad);assert 'Choose one response' in html and '&lt;script&gt;' in html and '<script>alert(1)</script>' not in html
  bad=dict(data);bad['email']='bad';assert 'valid email address' in post(bad)
  bad=dict(data);bad['answers[A1]']='999';assert 'invalid or too long' in post(bad)
  fail_admin[0]=True;html=post(data);assert 'Retry report delivery' in html and len(captured)==1
  records=list((temp/'reports').glob('*.json'));assert len(records)==1
  first=json.loads(records[0].read_text());ref=first['reference'];pdf=(temp/'reports'/ (ref+'.pdf')).read_bytes()
  assert first['pdf']['status']=='generated' and first['email']['admin']['status']=='failed' and first['email']['user']['status']=='sent'
  assert first['submission']['answers']['A1']==3 and first['submission']['answers']['B5']=='unknown'
  assert first['report']['numeric_sum']==78 and first['report']['counts']['numeric']==22
  assert 'smtp_password' not in records[0].read_text()
  # Mutating a retry must not replace the frozen original structured answers.
  data['answers[A1]']='5';html=post(data);assert 'Your clinic workflow report has been sent.' in html and len(captured)==2
  assert len(list((temp/'reports').glob('*.json')))==1 and pdf==(temp/'reports'/(ref+'.pdf')).read_bytes()
  final=json.loads(records[0].read_text());assert final['submission']['answers']['A1']==3
  assert all(final['email'][target]['status']=='sent' for target in ('admin','user'))
  for rcpt,raw in captured:
   m=email.message_from_bytes(raw);attachment=next(p for p in m.walk() if p.get_content_type()=='application/pdf')
   assert attachment.get_payload(decode=True)==pdf and ref in m['Subject']
  try:
   import fitz
   doc=fitz.open(stream=pdf,filetype='pdf');text=''.join(page.get_text() for page in doc)
   for required in ['3.55 / 5','91.67%','Provisional','IMPORTANT CONTROL GAP','C4','C5','D1','C1','B5','D5','Suggested 30-day action plan','Scoring rules: 1.0.0','Full KRA and KPI register','Day-90 outcome review','A practical clinic improvement roadmap']:assert required in text,required
   assert '125' not in text and 'why routine owner review' in text
  except ImportError:print('PyMuPDF unavailable: attachment bytes checked, text extraction skipped.')
  # Replayed successful POST has an expired token and cannot create another record.
  assert 'session has expired' in post(data) and len(list((temp/'reports').glob('*.json')))==1
  print('PASS: real form validation/escaping, legacy normalization, fixture scoring, PHPMailer dual attachments, partial SMTP failure, immutable retry, one private record and replay rejection.')
 finally:
  proc.terminate();proc.wait(timeout=5);server.shutdown();server.server_close();log.close()
