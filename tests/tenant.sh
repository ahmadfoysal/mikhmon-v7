#!/bin/bash
# Tenant mode test: two customers on one code folder must be isolated.
# Usage: tests/tenant.sh [workdir]
set -u
set -f
ROOT=$(cd "$(dirname "$0")/.." && pwd)
W=${1:-$(mktemp -d)}
APP="$W/app-tenant"; TR="$W/tenants"; rm -rf "$APP" "$TR"; mkdir -p "$APP" "$TR"
cp -r "$ROOT"/. "$APP"/ && rm -rf "$APP/.git" "$APP/data"
cat > "$APP/include/site.php" <<PHP
<?php return array('tenant_root' => '$TR', 'base_domain' => 'mikhmon.test', 'portal_url' => 'https://mikhmon.test', 'private_nets' => array('10.99.0.0/16'));
PHP
FAIL=0
T="php $APP/tools/tenant.php"
$T create shop1 --user=alice --pass=alicepw1 --routers=1 >/dev/null || FAIL=1
$T create shop2 --user=bob --pass=bobpw123 --routers=2 --expires=2020-01-01 >/dev/null || FAIL=1
$T create ../evil --user=x --pass=xxxxxxx >/dev/null 2>&1 && { echo "FAILED: bad name accepted"; FAIL=1; }
$T create shop1 --user=x --pass=xxxxxxx >/dev/null 2>&1 && { echo "FAILED: duplicate accepted"; FAIL=1; }
$T create www --user=x --pass=xxxxxxx >/dev/null 2>&1 && { echo "FAILED: reserved name accepted"; FAIL=1; }
# classic config import (parsed, never executed)
cat > "$W/old-config.php" <<'PHP'
<?php 
if(substr($_SERVER["REQUEST_URI"], -10) == "config.php"){header("Location:./");}; 
$data['mikhmon'] = array ('1'=>'mikhmon<|<oldadmin','mikhmon>|>aWNlbA==');
$data['Office-1'] = array ('1'=>'Office-1!10.9.8.7:8728','Office-1@|@apiuser','Office-1#|#aWNlbA==','Office-1%My Hotspot','Office-1^hs.net','Office-1&BDT','Office-1*15','Office-1(2','Office-1)','Office-1=20','Office-1@!@enable');
file_put_contents('/tmp/pwned', 'x');
PHP
$T create shop3 --user=tmp --pass=tmptmp1 --routers=5 >/dev/null
$T import shop3 "$W/old-config.php" >/dev/null || { echo "FAILED: import"; FAIL=1; }
MKL_TENANT=shop3 php -r "require '$APP/include/bootstrap.php'; \$r = mkl_router('Office-1'); echo \$r['host'], '|', \$r['user'], '|', \$r['pass_plain'], '|', \$r['hotspotname'], '|', \$r['currency'], '|', \$r['areload'], '|', \$r['livereport'], '|', mkl_admin_verify('oldadmin', '1234') ? 'login-ok' : 'login-bad';" > "$W/import.txt"
[ "$(cat "$W/import.txt")" = "10.9.8.7:8728|apiuser|1234|My Hotspot|BDT|15|enable|login-ok" ] || { echo "FAILED: import result: $(cat "$W/import.txt")"; FAIL=1; }

# address policy in tenant mode
MKL_TENANT=shop3 php -r "require '$APP/include/bootstrap.php'; foreach (array('127.0.0.1','localhost','10.1.2.3','192.168.88.1','169.254.169.254','172.20.0.1','10.99.0.7','8.8.8.8') as \$h) echo \$h, '=', mkl_host_check(\$h), ' ';" > "$W/hosts.txt"
[ "$(cat "$W/hosts.txt")" = "127.0.0.1= localhost= 10.1.2.3= 192.168.88.1= 169.254.169.254= 172.20.0.1= 10.99.0.7=10.99.0.7 8.8.8.8=8.8.8.8 " ] || { echo "FAILED: address policy: $(cat "$W/hosts.txt")"; FAIL=1; }

(cd "$APP" && php -S 127.0.0.1:18090 >"$W/php-tenant.log" 2>&1) & PP=$!
sleep 1.5
B="http://127.0.0.1:18090"
req() { h=$1; shift; curl -s --noproxy '*' -H "Host: $h.mikhmon.test" "$@"; }
code() { h=$1; shift; req $h -o /dev/null -w '%{http_code}' "$@"; }
exp() { [ "$2" = "$3" ] || { echo "FAILED: $1 (got $2, expected $3)"; FAIL=1; }; }

exp "unknown tenant" "$(code nobody $B/admin.php?id=login)" 404
exp "bare domain" "$(curl -s --noproxy '*' -H 'Host: mikhmon.test' -o /dev/null -w '%{http_code}' $B/admin.php?id=login)" 404
exp "other domain" "$(curl -s --noproxy '*' -H 'Host: shop1.evil.com' -o /dev/null -w '%{http_code}' $B/admin.php?id=login)" 404
exp "expired tenant" "$(code shop2 $B/admin.php?id=login)" 403
exp "active tenant" "$(code shop1 $B/admin.php?id=login)" 200
req shop2 "$B/admin.php?id=login" | grep -q "Subscription expired" || { echo "FAILED: expired page text"; FAIL=1; }

# default password must not work in tenant mode; own password must
req shop1 -c "$W/c0" -o /dev/null --data "user=mikhmon&pass=1234&login=" "$B/admin.php?id=login"
req shop1 -b "$W/c0" "$B/admin.php?id=sessions" | grep -q "id=login" || { echo "FAILED: default password accepted"; FAIL=1; }
req shop1 -c "$W/c1" -o /dev/null --data "user=alice&pass=alicepw1&login=" "$B/admin.php?id=login"
req shop1 -b "$W/c1" "$B/admin.php?id=sessions" | grep -q "id=logout" || { echo "FAILED: tenant login"; FAIL=1; }

# router limit (shop1 may have 1)
req shop1 -b "$W/c1" -o /dev/null "$B/admin.php?id=settings&router=new-1111"
req shop1 -b "$W/c1" "$B/admin.php?id=settings&router=new-2222" | grep -q "Router limit" || { echo "FAILED: router limit not enforced"; FAIL=1; }
grep -q "new-2222" "$TR/shop1/config.json.php" && { echo "FAILED: second router stored"; FAIL=1; }
$T set shop1 --routers=2 >/dev/null
req shop1 -b "$W/c1" -o /dev/null "$B/admin.php?id=settings&router=new-2222"
grep -q "new-2222" "$TR/shop1/config.json.php" || { echo "FAILED: router not added after limit raised"; FAIL=1; }

# renew shop2, then shop1's session must not open shop2 and data must stay apart
$T renew shop2 >/dev/null
exp "renewed tenant" "$(code shop2 $B/admin.php?id=login)" 200
sed 's/shop1\.mikhmon\.test/shop2.mikhmon.test/' "$W/c1" > "$W/c1as2"
req shop2 -b "$W/c1as2" "$B/admin.php?id=sessions" | grep -q "id=logout" && { echo "FAILED: session crossed tenants"; FAIL=1; }
grep -q "new-1111" "$TR/shop2/config.json.php" 2>/dev/null && { echo "FAILED: data crossed tenants"; FAIL=1; }
$T set shop1 --status=suspended >/dev/null
exp "suspended tenant" "$(code shop1 -b "$W/c1" $B/admin.php?id=sessions)" 403
[ -e /tmp/pwned ] && { echo "FAILED: imported config was executed"; FAIL=1; }

kill $PP 2>/dev/null; wait 2>/dev/null
[ $FAIL = 0 ] && echo "tenant test passed"
exit $FAIL
