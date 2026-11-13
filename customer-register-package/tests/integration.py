"""Run against PHP 8.2 + PDO SQLite. Uses disposable, synthetic customer data only."""
from pathlib import Path
import tempfile,subprocess,sqlite3,json,uuid,os,time,re,urllib.request,urllib.error,http.cookiejar,csv,io
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BINARY','php')
import urllib.parse
# PHP_BINARY can be a wrapper that loads locally installed PDO extensions.
with tempfile.TemporaryDirectory() as directory:
    private=Path(directory)
    private.joinpath('app.php').write_text((ROOT/'private/app.php').read_text())
    db=sqlite3.connect(private/'test.sqlite')
    schema=(ROOT/'private/schema.sql').read_text()
    schema=re.sub(r'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY','INTEGER PRIMARY KEY AUTOINCREMENT',schema)
    schema=re.sub(r',?\n INDEX [^\n]+','',schema)
    schema=re.sub(r'\) ENGINE=InnoDB(?: DEFAULT CHARSET=utf8mb4)?;',');',schema)
    schema=schema.replace(',INDEX rate_lookup(key_hash,happened_at)','')
    db.executescript(schema);db.close()
    phash=subprocess.check_output([PHP,'-r',"echo password_hash('test-only-password', PASSWORD_DEFAULT);"],text=True)
    private.joinpath('config.php').write_text("<?php return "+"['dsn'=>'sqlite:"+str(private/'test.sqlite')+"','admin_username'=>'owner','admin_password_hash'=>'"+phash+"','secret'=>'"+'x'*48+"','allowed_origins'=>['http://127.0.0.1:8779'],'secure_cookies'=>false];")
    env=dict(os.environ,UNIQUE_CRM_PRIVATE_DIR=str(private))
    log=open(private/'server.log','w')
    server=subprocess.Popen([PHP,'-S','127.0.0.1:8779','-t',str(ROOT/'public')],env=env,stdout=log,stderr=log)
    base='http://127.0.0.1:8779'
    jar=http.cookiejar.CookieJar();admin=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    def call(path,data=None,opener=None,origin=None,json_data=False):
        headers={}
        if json_data: data=json.dumps(data).encode();headers['Content-Type']='application/json'
        elif data is not None: data=urllib.parse.urlencode(data).encode()
        if origin:headers['Origin']=origin
        r=urllib.request.Request(base+path,data=data,headers=headers)
        try:
            with (opener or urllib.request.build_opener()).open(r) as response:return response.status,response.read().decode('utf-8-sig'),response.headers
        except urllib.error.HTTPError as e:return e.code,e.read().decode('utf-8-sig'),e.headers
    def token(body):return re.search(r'name="csrf" value="([a-f0-9]+)"',body)[1]
    def submit(**override):
        data=dict(submissionId=str(uuid.uuid4()),name='Test Customer',phone='0501234567',email='test@example.test',company='',area='Arjan',service='Tailoring & alterations',details='Two trousers for shortening',marketing=False)
        data.update(override)
        return call('/api/enquiries.php',data,origin=base,json_data=True)
    try:
        for _ in range(50):
            try:code,body,_=call('/customer-register/',opener=admin);break
            except OSError:time.sleep(.1)
        assert 'Owner sign-in' in body and 'Test Customer' not in body
        assert call('/api/enquiries.php')[0]==405
        assert call('/api/enquiries.php',{},origin='https://evil.example',json_data=True)[0]==403
        assert submit(area='')[0]==422
        assert submit(phone='123')[0]==422
        assert submit(service='Corporate uniforms',company='')[0]==422
        first=str(uuid.uuid4())
        assert submit(submissionId=first)[0]==200
        assert submit(submissionId=first)[0]==200
        assert submit(phone='+971501234567')[0]==200
        db=sqlite3.connect(private/'test.sqlite')
        assert db.execute('select count(*) from customers').fetchone()[0]==1
        assert db.execute('select count(*) from enquiries').fetchone()[0]==2
        assert db.execute('select marketing from customers').fetchone()[0]==0
        assert call('/customer-register/',dict(action='login',username='owner',password='test-only-password',csrf='invalid'),admin)[0]==200
        assert not any(x.name=='unique_crm' and not x.has_nonstandard_attr('HttpOnly') for x in jar)
        body=call('/customer-register/',opener=admin)[1]
        _,body,_=call('/customer-register/',dict(action='login',username='owner',password='wrong',csrf=token(body)),admin)
        assert 'Incorrect username' in body
        _,body,headers=call('/customer-register/',dict(action='login',username='owner',password='test-only-password',csrf=token(body)),admin)
        assert 'Test Customer' in body and 'Customers by area and segment' in body
        assert "frame-ancestors 'none'" in headers['Content-Security-Policy']
        assert call('/customer-register/?area=Elsewhere',opener=admin)[1].count('0 matching customers')==1
        assert 'Test Customer' in call('/customer-register/?q=Test',opener=admin)[1]
        body=call('/customer-register/?customer=1',opener=admin)[1]
        assert 'Two trousers' in body
        _,body,_=call('/customer-register/?customer=1',dict(action='status',id=1,status='Quoted',notes='Ready to call',csrf=token(body)),admin)
        assert 'Enquiry updated.' in body
        assert db.execute('select status from enquiries where id=1').fetchone()[0]=='Quoted'
        assert call('/customer-register/',dict(action='export',csrf='bad',type='customers'),admin)[2].get('Content-Type','').startswith('text/html')
        _,body,_=call('/customer-register/',dict(action='add',csrf=token(body),submissionId=str(uuid.uuid4()),name='=1+1',phone='+971501234568',email='',company='',area='Dubai Hills Estate',service='Garment repairs',details='<script>alert(1)</script>',source='Phone'),admin)
        assert '&lt;script&gt;' in body and '<script>alert' not in body
        _,out,_=call('/customer-register/',dict(action='export',csrf=token(body),type='customers'),admin)
        rows=list(csv.reader(io.StringIO(out)));assert rows[1][1]=="'=1+1" and rows[1][2].startswith("'+971")
        _,out,_=call('/customer-register/',dict(action='export',csrf=token(body),type='areas'),admin)
        assert 'Arjan,Individual,1' in out
        assert 'Owner sign-in' in call('/customer-register/?customer=1')[1]
        print('PASS: required area/company/mobile, origin validation, idempotency, phone deduplication, anonymous isolation, login/CSRF, customer search/area filters, status/history, manual entry, escaped content, protected CSV/formula escaping and area totals.')
    finally:
        server.terminate();server.wait();log.close()
