#!/usr/bin/env python3
"""End-to-end test of the customer portal.

Starts the portal, the Mikhmon app and a mock PayStation, then walks through
registration, single sign-on, manual and online payment, VPN connections and
the admin pages.   Usage: python3 tests/portal_test.py [workdir]
"""
import base64, http.cookiejar, http.server, json, os, re, shutil, subprocess, sys, tempfile, threading, time
import urllib.error, urllib.parse, urllib.request

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
W = sys.argv[1] if len(sys.argv) > 1 else tempfile.mkdtemp()
APP, DATA = os.path.join(W, 'app-portal'), os.path.join(W, 'pdata')
for d in (APP, DATA):
    shutil.rmtree(d, ignore_errors=True)
shutil.copytree(ROOT, APP, ignore=shutil.ignore_patterns('.git', 'data'))
os.makedirs(os.path.join(DATA, 'tenants'))
os.makedirs(os.path.join(DATA, 'vpn'))
open(os.path.join(DATA, 'vpn', 'server.pub'), 'w').write('SERVERPUBLICKEYSERVERPUBLICKEYSERVERPUBLICK=\n')
open(os.path.join(DATA, 'vpn', 'l2tp.psk'), 'w').write('TestPsk123\n')
PP, AP, MP, SP = 18095, 18096, 18097, 18098
open(os.path.join(APP, 'include', 'site.php'), 'w').write("""<?php return array(
 'tenant_root' => '%s/tenants', 'base_domain' => 'mikhmon.test', 'portal_url' => 'http://mikhmon.test',
 'trial_days' => 7, 'manual_payment' => 'bKash 017xxxxxxxx',
 'paystation_merchant' => 'm1', 'paystation_password' => 'p1', 'paystation_url' => 'http://127.0.0.1:%d',
 'vpn_host' => 'vpn.mikhmon.test',
 'smtp_host' => '127.0.0.1', 'smtp_port' => %d, 'smtp_secure' => '', 'smtp_user' => 'mailer', 'smtp_pass' => 'mailpw',
 'mail_from' => 'noreply@mikhmon.test', 'admin_email' => 'owner@mikhmon.test');""" % (DATA, MP, SP))

# ---- mock PayStation -------------------------------------------------------
PS = {'tx': {}, 'init': []}

class Mock(http.server.BaseHTTPRequestHandler):
    def log_message(self, *a):
        pass
    def do_POST(self):
        body = urllib.parse.parse_qs(self.rfile.read(int(self.headers.get('Content-Length', 0))).decode())
        f = {k: v[0] for k, v in body.items()}
        if self.path == '/initiate-payment':
            PS['init'].append(f)
            ok = f.get('merchantId') == 'm1' and f.get('password') == 'p1'
            out = {'status_code': '200', 'status': 'success', 'payment_url': 'https://pay.example/' + f.get('invoice_number', '')} if ok \
                else {'status_code': '2001', 'status': 'failed', 'message': 'Invalid credentials'}
        elif self.path == '/transaction-status':
            tx = PS['tx'].get(f.get('invoice_number'))
            if self.headers.get('merchantId') != 'm1' or not tx:
                out = {'status_code': '1006', 'status': 'failed', 'message': 'Transaction not found in system'}
            else:
                out = {'status_code': '200', 'status': 'success', 'message': 'Transaction found', 'data': tx}
        else:
            out = {'status_code': '404'}
        raw = json.dumps(out).encode()
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

# ---- mock SMTP server ------------------------------------------------------
import email, email.header, socketserver
MAILS = []

class Smtp(socketserver.StreamRequestHandler):
    def handle(self):
        w = lambda s: self.wfile.write((s + '\r\n').encode())
        w('220 mock ESMTP')
        m = {'auth': False}
        while True:
            line = self.rfile.readline().decode('utf-8', 'replace').rstrip('\r\n')
            if not line:
                return
            c = line.upper()
            if c.startswith('EHLO'):
                w('250-mock'); w('250 AUTH LOGIN')
            elif c == 'AUTH LOGIN':
                w('334 VXNlcm5hbWU6'); u = base64.b64decode(self.rfile.readline().strip()).decode()
                w('334 UGFzc3dvcmQ6'); p = base64.b64decode(self.rfile.readline().strip()).decode()
                m['auth'] = (u, p) == ('mailer', 'mailpw')
                w('235 ok' if m['auth'] else '535 bad')
            elif c.startswith('MAIL FROM'):
                m['from'] = line; w('250 ok' if m['auth'] else '530 auth required')
            elif c.startswith('RCPT TO'):
                m['to'] = line[9:-1]; w('250 ok')
            elif c == 'DATA':
                w('354 go'); raw = b''
                while True:
                    l = self.rfile.readline()
                    if l in (b'.\r\n', b''):
                        break
                    raw += l
                msg = email.message_from_bytes(raw)
                subj = str(email.header.make_header(email.header.decode_header(msg['Subject'])))
                MAILS.append({'to': m['to'], 'subject': subj, 'body': msg.get_payload(decode=True).decode('utf-8'), 'raw': raw.decode('latin1')})
                w('250 queued')
            elif c == 'QUIT':
                w('221 bye'); return
            else:
                w('250 ok')

class SmtpSrv(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True
smtp = SmtpSrv(('127.0.0.1', SP), Smtp)
threading.Thread(target=smtp.serve_forever, daemon=True).start()
def mail_to(addr, word=''):
    time.sleep(0.2)
    return [x for x in MAILS if x['to'] == addr and word in x['subject']]
def link_in(mail):
    return re.search(r'http://mikhmon\.test/(\?\S+)', mail['body']).group(1)

srv = http.server.ThreadingHTTPServer(('127.0.0.1', MP), Mock)
threading.Thread(target=srv.serve_forever, daemon=True).start()
env = dict(os.environ, NO_PROXY='*', no_proxy='*')
procs = [subprocess.Popen(['php', '-S', '127.0.0.1:%d' % PP], cwd=os.path.join(APP, 'portal', 'public'), env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL),
         subprocess.Popen(['php', '-S', '127.0.0.1:%d' % AP], cwd=APP, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)]
time.sleep(1.5)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None

class Browser:
    def __init__(self, port, host):
        self.base, self.host = 'http://127.0.0.1:%d/' % port, host
        self.op = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect)
        self.html = ''
    def go(self, path, data=None, follow=True):
        url = urllib.parse.urljoin(self.base, path)
        for _ in range(6):
            req = urllib.request.Request(url, data=urllib.parse.urlencode(data).encode() if data else None, headers={'Host': self.host})
            try:
                r = self.op.open(req)
                self.code, self.loc = r.getcode(), ''
                self.html = r.read().decode('utf-8', 'replace')
            except urllib.error.HTTPError as e:
                self.code, self.loc = e.code, e.headers.get('Location', '')
                self.html = e.read().decode('utf-8', 'replace')
            if self.code in (301, 302, 303) and follow and not self.loc.startswith('http'):
                url, data = urllib.parse.urljoin(url, self.loc), None
                continue
            break
        return self.html
    def csrf(self):
        m = re.search(r'name="csrf" value="([0-9a-f]+)"', self.html)
        return m.group(1) if m else ''
    def post(self, path, data, follow=True, form=None):
        self.go(form if form is not None else path)
        return self.go(path, dict(data, csrf=self.csrf()), follow)

fails = []
def ok(cond, what):
    if not cond:
        fails.append(what)
        print('FAIL:', what)

def tenant(name):
    return json.load(open(os.path.join(DATA, 'tenants', name, 'tenant.json')))
def php(code, tenant_name=''):
    return subprocess.run(['php', '-r', "require '%s/portal/src/app.php'; mkl_boot(); %s" % (APP, code)], env=dict(env, MKL_TENANT=tenant_name), capture_output=True, text=True).stdout

try:
    today = time.strftime('%Y-%m-%d')
    a = Browser(PP, 'mikhmon.test')
    ok('Create account' in a.go('./') and 'Most popular' in a.html and '৳1,200' in a.html and '৳3,000' in a.html and '৳7,500' in a.html, 'landing page with packages')
    ok('save 10%' in a.html and 'save 15%' in a.html and '৳2,700' in a.html and '৳19,125' in a.html, 'multi-year prices on the landing page')
    a.go('?p=register&plan=business')
    ok('Selected package: Business' in a.html, 'package chosen on the landing page is remembered')
    ok(Browser(PP, 'alice1.mikhmon.test').go('./') and 'Not found' in Browser(PP, 'alice1.mikhmon.test').go('./'), 'portal refuses customer hosts')

    # registration
    a.post('?p=register', {'name': 'Alice', 'email': 'alice@example.com', 'phone': '0171', 'tenant': 'a', 'pass': 'alicepass1'})
    ok('not valid' in a.html, 'bad subdomain refused')
    a.post('?p=register', {'name': 'Alice', 'email': 'alice@example.com', 'phone': '0171', 'tenant': 'www', 'pass': 'alicepass1'})
    ok('reserved' in a.html, 'reserved subdomain refused')
    a.post('?p=register', {'name': 'Alice', 'email': 'alice@example.com', 'phone': '0171', 'tenant': 'alice1', 'pass': 'short'})
    ok('at least 8' in a.html, 'short password refused')
    a.post('?p=register', {'name': 'Alice <b>', 'email': 'Alice@Example.com', 'phone': '0171', 'tenant': 'alice1', 'pass': 'alicepass1'})
    ok('Welcome, Alice &lt;b&gt;' in a.html and 'Waiting for email confirmation' in a.html and 'p=open' not in a.html, 'registered, waiting for confirmation, name escaped')
    t = tenant('alice1')
    ok(t['plan'] == 'unverified' and t['expires'] < today, 'new account is closed until the address is confirmed: %s' % t)
    ok('Email not confirmed' in Browser(AP, 'alice1.mikhmon.test').go('admin.php?id=login'), 'Mikhmon is closed before confirmation')
    a.post('?p=vpn-add', {'name': 'Early', 'type': 'wg'}, form='?p=vpn')
    ok('confirm your email address first' in a.html and not os.path.exists(os.path.join(DATA, 'vpn', 'wg-peers.conf')), 'no router connection before confirmation')

    # e-mail verification
    vm = mail_to('alice@example.com', 'Confirm')
    ok(len(vm) == 1 and 'https://alice1.mikhmon.test' in vm[0]['body'] and 'Alice <b>' in vm[0]['body'], 'welcome mail sent: %s' % [x['subject'] for x in MAILS])
    ok('opens as soon as you confirm your email address' in a.go('?p=dashboard'), 'dashboard asks for confirmation')
    old_link = link_in(vm[0])
    a.go('?p=verify-resend', {'csrf': a.csrf()})
    vm = mail_to('alice@example.com', 'Confirm')
    ok(len(vm) == 2, 'verification mail sent again')
    anon = Browser(PP, 'mikhmon.test')
    ok('not valid any more' in anon.go(old_link), 'older verification link stops working')
    ok('email address is confirmed. Your 7 day free trial starts now' in anon.go(link_in(vm[1])), 'verification link works without login')
    t = tenant('alice1')
    week = time.strftime('%Y-%m-%d', time.localtime(time.time() + 7 * 86400))
    ok(t['max_routers'] == 1 and t['plan'] == 'trial' and t['expires'] == week, 'trial starts at confirmation: %s' % t)
    ok('not valid any more' in anon.go(link_in(vm[1])), 'verification link works once')
    ok('confirm your email address' not in a.go('?p=dashboard') and 'Free trial' in a.html and 'p=open' in a.html, 'confirmed: trial shown, Mikhmon can be opened')
    b = Browser(PP, 'mikhmon.test')
    b.post('?p=register', {'name': 'Bob', 'email': 'alice@example.com', 'phone': '', 'tenant': 'bob1', 'pass': 'bobpass12'})
    ok('already exists' in b.html, 'duplicate email refused')
    b.post('?p=register', {'name': 'Bob', 'email': 'bob@example.com', 'phone': '', 'tenant': 'alice1', 'pass': 'bobpass12'})
    ok('already taken' in b.html, 'duplicate subdomain refused')
    b.post('?p=register', {'name': 'Bob', 'email': 'bob@example.com', 'phone': '', 'tenant': 'bob1', 'pass': 'bobpass12'})
    ok('Welcome, Bob' in b.html, 'second customer registered')

    # the customer's Mikhmon accepts the same password
    m = Browser(AP, 'alice1.mikhmon.test')
    m.go('admin.php?id=login', {'user': 'alice1', 'pass': 'alicepass1', 'login': ''})
    ok('id=logout' in m.go('admin.php?id=sessions'), 'Mikhmon login with the portal password')

    # single sign-on
    a.go('?p=open', follow=False)
    ok(a.loc.startswith('https://alice1.mikhmon.test/admin.php?id=sso&token='), 'sso redirect')
    token = a.loc.split('token=')[1]
    s = Browser(AP, 'alice1.mikhmon.test')
    s.go('admin.php?id=sso&token=' + token)
    ok('id=logout' in s.go('admin.php?id=sessions'), 'sso logs in')
    s2 = Browser(AP, 'alice1.mikhmon.test')
    s2.go('admin.php?id=sso&token=' + token)
    ok('id=logout' not in s2.go('admin.php?id=sessions'), 'sso token works only once')
    s3 = Browser(AP, 'bob1.mikhmon.test')
    a.go('?p=open', follow=False)
    s3.go('admin.php?id=sso&token=' + a.loc.split('token=')[1])
    ok('id=logout' not in s3.go('admin.php?id=sessions'), 'sso token is bound to its tenant')

    # login / logout / throttling-free path
    a.go('?p=logout')
    a.post('?p=login', {'email': 'alice@example.com', 'pass': 'wrong'})
    ok('Wrong email or password' in a.html, 'wrong password refused')
    a.post('?p=login', {'email': 'alice@example.com', 'pass': 'alicepass1'})
    ok('Welcome, Alice' in a.html, 'login')
    ok(urllib.request.build_opener(urllib.request.ProxyHandler({})).open(urllib.request.Request('http://127.0.0.1:%d/?p=dashboard' % PP, headers={'Host': 'mikhmon.test'})).geturl().endswith('p=login'), 'dashboard needs login')
    a.go('?p=dashboard')
    a.go('?p=order', {'package': 'pro', 'years': '1', 'method': 'manual', 'manual_trx': 'X', 'csrf': 'bad'})
    ok(a.code == 400, 'post without csrf token refused')

    # manual payment -> admin approval
    ok('value="business" checked' in a.go('?p=billing') or "value=\"business\" checked" in a.html, 'billing preselects the package chosen on the landing page')
    a.post('?p=order', {'package': 'gold', 'years': '1', 'method': 'manual', 'manual_trx': 'X1'}, form='?p=billing')
    ok('This order is not valid' in a.html, 'unknown package refused')
    a.post('?p=order', {'package': 'pro', 'years': '4', 'method': 'manual', 'manual_trx': 'X1'}, form='?p=billing')
    ok('This order is not valid' in a.html, 'more than 3 years refused')
    a.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'manual', 'manual_method': 'bKash', 'manual_sender': '0171', 'manual_trx': ''}, form='?p=billing')
    ok('transaction ID' in a.html, 'manual order needs a transaction id')
    a.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'manual', 'manual_method': 'bKash', 'manual_sender': '0171', 'manual_trx': 'TRX123'}, form='?p=billing')
    ok('will be checked' in a.html and 'Waiting' in a.html and '1,200 BDT' in a.html, 'manual order pending')
    om = mail_to('owner@mikhmon.test', 'Manual payment')
    ok(len(om) == 1 and 'TRX123' in om[0]['body'] and 'alice1' in om[0]['body'], 'owner is told about the manual payment')
    ok(tenant('alice1')['plan'] == 'trial', 'pending order changes nothing')
    ok('Welcome' in a.go('?p=admin'), 'customer cannot open admin')
    subprocess.run(['php', os.path.join(APP, 'tools', 'portal.php'), 'admin', 'bob@example.com'], env=env, capture_output=True)
    b.go('?p=admin')
    ok('TRX123' in b.html and 'Orders waiting (1)' in b.html, 'admin sees the pending order')
    oid = re.search(r'name="id" value="(\d+)"', b.html).group(1)
    b.go('?p=admin-order', {'id': oid, 'do': 'approve', 'csrf': b.csrf()})
    t = tenant('alice1')
    year1 = time.strftime('%Y-%m-%d', time.localtime(time.time() + 364 * 86400))
    ok(t['max_routers'] == 1 and t['plan'] == 'starter' and t['expires'] >= year1, 'approval extends the subscription: %s' % t)
    exp1 = t['expires']
    pm = mail_to('alice@example.com', 'Payment received')
    ok(len(pm) == 1 and '1,200 BDT' in pm[0]['body'] and exp1 in pm[0]['body'], 'receipt mail after approval')
    b.go('?p=admin')
    b.go('?p=admin-order', {'id': oid, 'do': 'approve', 'csrf': b.csrf()})
    ok(tenant('alice1')['expires'] == exp1, 'approving twice does not extend twice')
    ok(len(mail_to('alice@example.com', 'Payment received')) == 1, 'no second receipt')
    b.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'manual', 'manual_trx': 'BADTRX'}, form='?p=billing')
    b.go('?p=admin')
    rid = re.search(r'BADTRX.*?name="id" value="(\d+)"', b.html, re.S).group(1)
    b.go('?p=admin-order', {'id': rid, 'do': 'reject', 'csrf': b.csrf()})
    ok(len(mail_to('bob@example.com', 'could not be confirmed')) == 1, 'rejection mail')

    # online payment
    a.post('?p=order', {'package': 'business', 'years': '2', 'method': 'paystation'}, follow=False, form='?p=billing')
    ok(a.loc.startswith('https://pay.example/ML'), 'redirect to the payment page: ' + a.loc)
    inv = a.loc.rsplit('/', 1)[1]
    f = PS['init'][-1]
    ok(f['payment_amount'] == '5400' and f['invoice_number'] == inv and 'pay-return' in f['callback_url'] and f['reference'] == 'alice1', 'initiate fields: %s' % f)
    a.go('?p=pay-return&invoice=' + inv + '&status=Successful&trx_id=FAKE')
    ok('not confirmed yet' in a.html and tenant('alice1')['max_routers'] == 1, 'forged return is not trusted')
    PS['tx'][inv] = {'invoice_number': inv, 'trx_status': 'Success', 'trx_id': 'PS1', 'payment_amount': 10}
    a.go('?p=pay-return&invoice=' + inv)
    ok(tenant('alice1')['max_routers'] == 1, 'under-payment is not accepted')
    PS['tx'][inv] = {'invoice_number': 'OTHER', 'trx_status': 'Success', 'trx_id': 'PS1', 'payment_amount': 5400}
    a.go('?p=pay-return&invoice=' + inv)
    ok(tenant('alice1')['max_routers'] == 1, 'payment for another invoice is not accepted')
    a.post('?p=order', {'package': 'business', 'years': '2', 'method': 'paystation'}, follow=False, form='?p=billing')
    inv = a.loc.rsplit('/', 1)[1]
    PS['tx'][inv] = {'invoice_number': inv, 'trx_status': 'Success', 'trx_id': 'PS2', 'payment_amount': 5400}
    a.go('?p=pay-return&invoice=' + inv)
    t = tenant('alice1')
    ok('Payment received' in a.html and t['max_routers'] == 3 and t['expires'][:4] == str(int(exp1[:4]) + 2), 'online payment applied on top of the paid period: %s' % t)
    exp2 = t['expires']
    urllib.request.build_opener(urllib.request.ProxyHandler({})).open(urllib.request.Request('http://127.0.0.1:%d/?p=pay-ipn' % PP, data=('invoice_number=' + inv).encode(), headers={'Host': 'mikhmon.test'})).read()
    a.go('?p=pay-return&invoice=' + inv)
    ok(tenant('alice1')['expires'] == exp2, 'repeated notification does not extend twice')
    # IPN alone settles an order the browser never returned from
    b.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'paystation'}, follow=False, form='?p=billing')
    invb = b.loc.rsplit('/', 1)[1]
    PS['tx'][invb] = {'invoice_number': invb, 'trx_status': 'Success', 'trx_id': 'PS3', 'payment_amount': '1200.00'}
    urllib.request.build_opener(urllib.request.ProxyHandler({})).open(urllib.request.Request('http://127.0.0.1:%d/?p=pay-ipn' % PP, data=('invoice_number=' + invb).encode(), headers={'Host': 'mikhmon.test'})).read()
    ok(tenant('bob1')['plan'] == 'starter', 'server notification settles the order')

    # VPN connections
    a.go('?p=vpn')
    ok('RouterOS v7 (WireGuard)' in a.html and 'RouterOS v6 (L2TP/IPsec)' in a.html, 'vpn page')
    a.post('?p=vpn-add', {'name': 'Office 1!', 'type': 'wg'}, form='?p=vpn')
    ok('Setup script for Office1' in a.html and '/interface wireguard add name=mikhmonlive private-key=' in a.html
       and 'endpoint-address=vpn.mikhmon.test' in a.html and 'address=10.99.1.1/32' in a.html, 'wireguard script')
    priv = re.search(r'private-key=&quot;([^&]+)&quot;', a.html).group(1)
    wg = open(os.path.join(DATA, 'vpn', 'wg-peers.conf')).read()
    pub = re.search(r'PublicKey = (\S+)', wg).group(1)
    ok(len(base64.b64decode(priv)) == 32 and len(base64.b64decode(pub)) == 32 and 'AllowedIPs = 10.99.1.1/32' in wg and priv not in wg, 'wireguard state file')
    check = subprocess.run(['php', '-r', 'echo base64_encode(sodium_crypto_scalarmult_base(base64_decode($argv[1])));', priv], capture_output=True, text=True).stdout
    ok(check == pub, 'public key belongs to the private key')
    a.post('?p=vpn-add', {'name': 'Shop-2', 'type': 'l2tp'}, form='?p=vpn')
    ok('/interface l2tp-client add name=mikhmonlive connect-to=vpn.mikhmon.test' in a.html and 'ipsec-secret=&quot;TestPsk123&quot;' in a.html, 'l2tp script')
    chap = open(os.path.join(DATA, 'vpn', 'chap-secrets')).read()
    ok(re.search(r'^"ml[a-z0-9]{10}" \* "[A-Za-z0-9]{24}" 10\.99\.129\.1$', chap, re.M) is not None, 'l2tp state file: ' + chap)
    ok(tenant('alice1')['vpn_ips'] == ['10.99.1.1', '10.99.129.1'], 'tenant may reach its tunnel addresses')
    ok(php("echo mkl_host_check('10.99.1.1'), '|', mkl_host_check('10.99.129.1'), '|', mkl_host_check('10.99.1.2');", 'alice1') == '10.99.1.1|10.99.129.1|', 'address policy for own tunnels')
    ok(php("echo mkl_host_check('10.99.1.1');", 'bob1') == '', 'another customer cannot reach the tunnel')
    ok(php("$r = mkl_router('Office1'); echo $r['host'], '|', $r['user'], '|', strlen($r['pass_plain']);", 'alice1') == '10.99.1.1|mikhmonlive|24', 'router added to the customer Mikhmon')
    m.go('admin.php?id=sessions')
    ok('Office1' in m.html and 'Shop-2' in m.html, 'routers listed in Mikhmon')
    a.post('?p=vpn-add', {'name': 'Third', 'type': 'wg'}, form='?p=vpn')
    a.post('?p=vpn-add', {'name': 'Fourth', 'type': 'wg'}, form='?p=vpn')
    ok('no free router left' in a.html, 'vpn limit follows the plan')
    a.post('?p=vpn-add', {'name': 'Third', 'type': 'wg'}, form='?p=vpn')
    ok('no free router left' in a.html or 'already have' in a.html, 'duplicate / over limit refused')
    pid = re.search(r'p=vpn&amp;show=(\d+)', a.go('?p=vpn')).group(1)
    ok('Setup script' not in b.go('?p=vpn&show=' + pid), "another customer cannot see the script")
    b.go('?p=vpn')
    b.go('?p=vpn-del', {'id': pid, 'csrf': b.csrf()})
    ok('10.99.1.1/32' in open(os.path.join(DATA, 'vpn', 'wg-peers.conf')).read(), 'another customer cannot delete the connection')
    a.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'manual', 'manual_trx': 'T2'}, form='?p=billing')
    ok('connected routers' in a.html, 'cannot order fewer routers than connected')
    a.go('?p=vpn')
    a.go('?p=vpn-del', {'id': pid, 'csrf': a.csrf()})
    ok('10.99.1.1/32' not in open(os.path.join(DATA, 'vpn', 'wg-peers.conf')).read() and '10.99.1.1' not in tenant('alice1')['vpn_ips'], 'connection removed')
    ok(php("echo mkl_router('Office1')['exists'] ? 'yes' : 'no';", 'alice1') == 'no', 'router removed from Mikhmon with its tunnel')

    # admin edits, expiry switches the tunnels off
    b.go('?p=admin')
    rows = re.findall(r'<tr><td><a href="https://(\w+)\.mikhmon\.test">.*?admin-user&amp;id=(\d+)', b.html, re.S)
    aid = dict(rows)['alice1']
    b.post('?p=admin-user&id=' + aid, {'what': 'sub', 'max_routers': '9', 'expires': '2020-01-01', 'status': 'active'})
    t = tenant('alice1')
    ok(t['max_routers'] == 9 and t['expires'] == '2020-01-01', 'admin edits the subscription')
    ok('10.99.' not in open(os.path.join(DATA, 'vpn', 'chap-secrets')).read().replace('# ', ''), 'expired account loses its tunnels')
    ok('Subscription expired' in Browser(AP, 'alice1.mikhmon.test').go('admin.php?id=login'), 'expired Mikhmon is closed')
    ok('Expired' in a.go('?p=dashboard') and 'p=open' not in a.html, 'portal shows the expired state')
    b.post('?p=admin-user&id=' + aid, {'what': 'sub', 'max_routers': '9', 'expires': '2031-01-01', 'status': 'active'})
    ok('10.99.129.1' in open(os.path.join(DATA, 'vpn', 'chap-secrets')).read(), 'renewed account gets its tunnels back')
    b.post('?p=admin-user&id=' + aid, {'what': 'pass', 'pass': 'resetpass9'})
    m2 = Browser(AP, 'alice1.mikhmon.test')
    m2.go('admin.php?id=login', {'user': 'alice1', 'pass': 'resetpass9', 'login': ''})
    ok('id=logout' in m2.go('admin.php?id=sessions'), 'admin password reset reaches Mikhmon')
    a.go('?p=logout')
    a.post('?p=login', {'email': 'alice@example.com', 'pass': 'resetpass9'})
    a.post('?p=account', {'what': 'password', 'current': 'resetpass9', 'pass': 'mynewpass7'})
    ok('Password changed' in a.html, 'customer changes the password')
    ok('বিলিং' in a.go('?p=lang&l=bn') and 'ড্যাশবোর্ড' in a.html, 'Bangla interface')
    ok('রাউটার: 9টির মধ্যে 2টি ব্যবহৃত' in a.html, 'Bangla text with values: ' + ''.join(re.findall(r'রাউটার:[^<]*', a.html)))
    ok(open(os.path.join(DATA, 'portal.sqlite'), 'rb').read().find(b'alicepass1') < 0, 'no clear text password in the database')

    # password reset by e-mail (Alice now uses Bangla, so the mail is Bangla)
    a.go('?p=logout')
    f = Browser(PP, 'mikhmon.test')
    f.post('?p=forgot', {'email': 'nobody@example.com'})
    ok('If an account with this email exists' in f.html and not mail_to('nobody@example.com'), 'unknown address: same answer, no mail')
    n0 = len(MAILS)
    f.post('?p=forgot', {'email': 'ALICE@example.com'})
    rm = mail_to('alice@example.com', 'পাসওয়ার্ড রিসেট')
    ok('If an account with this email exists' in f.html and len(rm) == 1 and 'প্রিয় Alice' in rm[0]['body'], 'reset mail in the customer language: %s' % [x['subject'] for x in MAILS[n0:]])
    ok('Subject: =?UTF-8?B?' in rm[0]['raw'] and 'Content-Transfer-Encoding: base64' in rm[0]['raw'], 'non-ASCII mail is encoded')
    first = link_in(rm[0])
    f.post('?p=forgot', {'email': 'alice@example.com'})
    rm = mail_to('alice@example.com', 'পাসওয়ার্ড রিসেট')
    ok('not valid any more' in f.go(first), 'a newer reset link replaces the older one')
    link = link_in(rm[-1])
    ok('Choose a new password' in f.go(link), 'reset form opens')
    f.go('?p=reset', {'token': link.split('token=')[1], 'pass': 'short', 'csrf': f.csrf()})
    ok('at least 8' in f.html, 'short new password refused')
    f.go('?p=reset', {'token': link.split('token=')[1], 'pass': 'frommail88', 'csrf': f.csrf()})
    ok('password was changed' in f.html, 'password reset done')
    ok('not valid any more' in f.go(link), 'reset link works once')
    f.post('?p=login', {'email': 'alice@example.com', 'pass': 'mynewpass7'})
    ok('Wrong email or password' in f.html or 'ভুল' in f.html, 'old password no longer works')
    f.post('?p=login', {'email': 'alice@example.com', 'pass': 'frommail88'})
    ok('Alice' in f.html and 'p=logout' in f.html, 'login with the new password')
    m3 = Browser(AP, 'alice1.mikhmon.test')
    m3.go('admin.php?id=login', {'user': 'alice1', 'pass': 'frommail88', 'login': ''})
    ok('id=logout' in m3.go('admin.php?id=sessions'), 'reset password also opens Mikhmon')
    ok('not valid any more' in Browser(PP, 'mikhmon.test').go('?p=reset&token=' + '0' * 48), 'made-up token refused')
    f.go('?p=logout')
    for i in range(5):
        f.post('?p=forgot', {'email': 'bob@example.com'})
    ok(1 <= len(mail_to('bob@example.com', 'Reset your password')) <= 3, 'reset mails are rate limited: %d' % len(mail_to('bob@example.com', 'Reset your password')))

    # accounts that never confirm
    import sqlite3
    c = Browser(PP, 'mikhmon.test')
    c.post('?p=register', {'name': 'Carol', 'email': 'carol@example.com', 'phone': '', 'tenant': 'carol1', 'pass': 'carolpass1'})
    d = Browser(PP, 'mikhmon.test')
    d.post('?p=register', {'name': 'Dave', 'email': 'dave@example.com', 'phone': '', 'tenant': 'dave1', 'pass': 'davepass12'})
    ok(tenant('carol1')['plan'] == 'unverified' and tenant('dave1')['plan'] == 'unverified', 'two more waiting accounts')
    ptool = lambda *args: subprocess.run(['php', os.path.join(APP, 'tools', 'portal.php')] + list(args), env=env, capture_output=True, text=True).stdout
    ok(ptool('purge-unverified', '7').startswith('0 account'), 'fresh waiting accounts are kept')
    b.go('?p=admin')
    did = dict(re.findall(r'<tr><td><a href="https://(\w+)\.mikhmon\.test">.*?admin-user&amp;id=(\d+)', b.html, re.S))['dave1']
    b.post('?p=admin-user&id=' + did, {'what': 'verify'})
    ok(tenant('dave1')['plan'] == 'trial' and tenant('dave1')['expires'] > today, 'admin can confirm an address by hand')
    db = sqlite3.connect(os.path.join(DATA, 'portal.sqlite'))
    db.execute("UPDATE users SET created_at = '2020-01-01 00:00:00'")
    db.commit(); db.close()
    out = ptool('purge-unverified', '7')
    ok(out.strip() == '1 account(s) removed: carol1', 'only the empty unconfirmed account is removed: ' + out)
    ok(not os.path.exists(os.path.join(DATA, 'tenants', 'carol1')) and os.path.exists(os.path.join(DATA, 'tenants', 'bob1')) and os.path.exists(os.path.join(DATA, 'tenants', 'dave1')), 'other accounts untouched')
    c2 = Browser(PP, 'mikhmon.test')
    c2.post('?p=register', {'name': 'Carol', 'email': 'carol@example.com', 'phone': '', 'tenant': 'carol1', 'pass': 'carolpass1'})
    ok('Welcome, Carol' in c2.html, 'the freed address can be registered again')
    c2.post('?p=order', {'package': 'starter', 'years': '1', 'method': 'paystation'}, follow=False, form='?p=billing')
    invc = c2.loc.rsplit('/', 1)[1]
    PS['tx'][invc] = {'invoice_number': invc, 'trx_status': 'Success', 'trx_id': 'PS9', 'payment_amount': 1200}
    c2.go('?p=pay-return&invoice=' + invc)
    ok(tenant('carol1')['plan'] == 'starter' and 'Email not confirmed' not in Browser(AP, 'carol1.mikhmon.test').go('admin.php?id=login'), 'a paid order opens an unconfirmed account')

    # expiry reminders
    tool = lambda *args: subprocess.run(['php', os.path.join(APP, 'tools', 'portal.php')] + list(args), env=env, capture_output=True, text=True).stdout
    def set_exp(days):
        d = time.strftime('%Y-%m-%d', time.localtime(time.time() + days * 86400))
        subprocess.run(['php', os.path.join(APP, 'tools', 'tenant.php'), 'set', 'alice1', '--expires=' + d], env=env, capture_output=True)
        return d
    # Dave's 7 day trial (started by the admin above) gets its first reminder now
    first = tool('reminders')
    ok(first.startswith('1 reminder') and MAILS[-1]['to'] == 'dave@example.com', 'trial ending reminder: ' + first)
    n0 = len(MAILS)
    ok(tool('reminders').startswith('0 reminder'), 'no reminder while the subscription runs long (and none for waiting accounts)')
    d7 = set_exp(6)
    ok(tool('reminders').startswith('1 reminder') and d7 in MAILS[-1]['body'] and MAILS[-1]['to'] == 'alice@example.com', 'reminder a week before the end')
    ok(tool('reminders').startswith('0 reminder'), 'the same reminder is not sent twice')
    set_exp(1)
    ok(tool('reminders').startswith('1 reminder'), 'reminder a day before the end')
    set_exp(-1)
    ok(tool('reminders').startswith('1 reminder') and 'শেষ হয়েছে' in MAILS[-1]['subject'], 'notice after the end: ' + MAILS[-1]['subject'])
    ok(tool('reminders').startswith('0 reminder'), 'only one notice after the end')
    set_exp(-30)
    ok(tool('reminders').startswith('0 reminder') and len(MAILS) == n0 + 3, 'old expired accounts are left alone')
    ok('Sent to' in tool('mail-test', 'check@example.com') and 'পরীক্ষামূলক' in mail_to('check@example.com')[0]['body'], 'mail-test command')
    ok(not any('\n' in x['subject'] for x in MAILS), 'subjects are single lines')
finally:
    for p in procs:
        p.terminate()
    srv.shutdown()
    smtp.shutdown()

print('portal test passed' if not fails else '%d check(s) failed' % len(fails))
sys.exit(1 if fails else 0)
