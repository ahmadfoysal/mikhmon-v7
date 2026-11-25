#!/bin/bash
# Smoke test: run every page against the mock router in both date formats.
# Usage: tests/smoke.sh [workdir]
set -u
set -f
ROOT=$(cd "$(dirname "$0")/.." && pwd)
W=${1:-$(mktemp -d)}
mkdir -p "$W"
FAIL=0
for FMT in legacy iso; do
  APP="$W/app-$FMT"; rm -rf "$APP"; mkdir -p "$APP"
  cp -r "$ROOT"/. "$APP"/ && rm -rf "$APP/.git"
  [ "$FMT" = legacy ] && RP=18728 || RP=18729
  [ "$FMT" = legacy ] && WP=18080 || WP=18081
  export MKL_ERRLOG="$W/errors-$FMT.log"; : > "$MKL_ERRLOG"; : > "$W/router-$FMT.log"
  python3 "$ROOT/tests/mock_routeros.py" $RP $FMT "$W/router-$FMT.log" & MP=$!
  php -r "require '$APP/include/bootstrap.php'; echo mkl_router_save('test', array('host'=>'127.0.0.1:$RP','user'=>'admin','pass_plain'=>'secret','hotspotname'=>'Mock','dnsname'=>'mock.net','livereport'=>'enable')), PHP_EOL;" | grep -v '^ok$'
  (cd "$APP" && php -d auto_prepend_file="$ROOT/tests/prepend.php" -d display_errors=0 -S 127.0.0.1:$WP >"$W/php-$FMT.log" 2>&1) & PP=$!
  sleep 1.5
  C="curl -s --noproxy * -b $W/cj-$FMT -c $W/cj-$FMT"
  B="http://127.0.0.1:$WP"
  $C -o /dev/null --data "user=mikhmon&pass=1234&login=" "$B/admin.php?id=login"
  get() { $C -o "$W/out-$FMT-$1.html" -w "%{http_code} %{size_download} $1\n" "$B/$2" | grep -v "^200 " ; }
  M=$(date +%b | tr A-Z a-z); D=$(date +%d); Y=$(date +%Y)
  get sessions "admin.php?id=sessions"
  get connect "admin.php?id=connect&session=test"
  get dashboard "?session=test"
  get aload "dashboard/aload.php?session=test&load=sysresource"
  get live "report/livereport.php?session=test"
  get users "?hotspot=users&profile=all&session=test"
  get user1 "?hotspot-user=*U2&session=test"
  get user3 "?hotspot-user=*U3&session=test"
  get profiles "?hotspot=user-profiles&session=test"
  get profile "?user-profile=*P2&session=test"
  get addprofile "?user-profile=add&session=test"
  get adduser "?hotspot-user=add&session=test"
  get generate "?hotspot-user=generate&session=test"
  get active "?hotspot=active&session=test"
  get server "?hotspot=server&session=test"
  get hosts "?hotspot=hosts&session=test"
  get ipbinding "?hotspot=ipbinding&session=test"
  get cookies "?hotspot=cookies&session=test"
  get log "?hotspot=log&session=test"
  get dhcp "?hotspot=dhcp-leases&session=test"
  get scheduler "?system=scheduler&session=test"
  get sellmonth "?report=selling&idbl=$M$Y&session=test"
  get sellday "?report=selling&idhr=$M/$D/$Y&session=test"
  get sellall "?report=selling&session=test"
  get resume "report/resumereport.php?idbl=$M$Y&session=test"
  get userlog "?report=userlog&idbl=$M$Y&session=test"
  get rprint "report/print.php?idbl=$M$Y&session=test"
  get vprint "voucher/print.php?user=vc-abc123&qr=no&session=test"
  get status "status/index.php?session=test"
  get settings "admin.php?id=settings&session=test"
  get editor "admin.php?id=editor&template=default&session=test"
  get about "admin.php?id=about"
  get clear "?clear-user-data=*U1&session=test"
  get rmmac "?remove-hotspot-mac=*U2&session=test"
  get repair "?repair-scripts=all&session=test"
  $C -o "$W/out-$FMT-saveprofile.html" --data "name=7day&sharedusers=1&ratelimit=2M/2M&expmode=remc&validity=7d&price=5000&sprice=6000&ppool=none&lockunlock=Enable&parent=none&save=" "$B/?user-profile=add&session=test"
  $C -o "$W/out-$FMT-saveprofile2.html" --data "name=free2&sharedusers=1&ratelimit=&expmode=0&validity=&price=100&sprice=&ppool=none&lockunlock=Disable&parent=none&save=" "$B/?user-profile=add&session=test"
  post() { n=$1; u=$2; shift 2; $C -o "$W/out-$FMT-$n.html" "$@" "$B/$u"; }
  post genuser "?hotspot-user=generate&session=test" --data "qty=3&server=all&user=vc&userl=6&prefix=ab&char=mix&profile=1day&timelimit=1h&datalimit=100&mbgb=1048576&adcomment=test"
  post adduser1 "?hotspot-user=add&session=test" --data "server=all&name=newu&pass=newp&profile=1day&timelimit=&datalimit=&mbgb=1048576&comment=hello&disabled=no"
  post edituser "?hotspot-user=*U3&session=test" --data "server=all&name=isouser&pass=isouser&profile=1day&timelimit=&datalimit=&mbgb=1048576&comment=&h_comment=jan/14/2030 10:20:30&comment2=note2&disabled=no"
  post editprofile "?user-profile=*P2&session=test" --data "name=1day&sharedusers=1&ratelimit=1M/1M&expmode=ntfc&validity=1d&price=1000&sprice=1200&ppool=none&lockunlock=Disable&parent=none&save="
  get export "?hotspot=export-users&profile=all&export=csv&session=test"
  get quickprint "?hotspot=quick-print&session=test"
  get listquick "?hotspot=list-quick-print&session=test"
  get traffic "?interface=traffic-monitor&session=test"
  get userbyprofile "?hotspot=users&profile=1day&session=test"
  get userbycomment "?hotspot=users&comment=vc-123-01.14.24-&session=test"
  get remreport "?report=selling&idhr=$M/$D/$Y&session=test" 
  post remday "?report=selling&idhr=$M/$D/$Y&session=test" --data "remdata="
  post savesettings "admin.php?id=settings&session=test" --data "sessname=test&ipmik=127.0.0.1:$RP&usermik=admin&passmik=secret2&hotspotname=Mock2&dnsname=mock.net&currency=BDT&areload=10&iface=1&infolp=&idleto=10&livereport=enable&save="
  post saveadmin "admin.php?id=sessions" --data "useradm=boss&passadm=newpass1&qrbt=enable&save="
  get dashboard2 "?session=test"
  get sessions2 "admin.php?id=sessions"
  post tplbad "admin.php?id=editor&template=default&session=test" --data-urlencode "editor=<?php system('id'); ?>" --data "save="
  post tplgood "admin.php?id=editor&template=thermal&session=test" --data-urlencode "editor=<b>CUSTOM <?= \$username ?></b>" --data "save="
  get vprint2 "voucher/print.php?user=vc-abc123&qr=no&session=test"
  get vpreview "voucher/vpreview.php?usermode=up&qr=no&session=test"
  get theme "?session=test&set-theme=blue"
  get lang "admin.php?id=sessions&setlang=id"
  get newrouter "admin.php?id=settings&router=new-1234"
  get rmrouter "admin.php?id=remove-session&session=new-1234"
  get logout "admin.php?id=logout"
  $C -o /dev/null --data "user=mikhmon&pass=1234&login=" "$B/admin.php?id=login"
  get oldlogin "admin.php?id=sessions"
  $C -o /dev/null --data "user=boss&pass=newpass1&login=" "$B/admin.php?id=login"
  get newlogin "admin.php?id=sessions"
  # expectations
  chk() { if ! grep -q -- "$2" "$W/out-$FMT-$1.html"; then echo "EXPECT FAILED ($FMT) $1: $2"; FAIL=1; fi; }
  nochk() { if grep -q -- "$2" "$W/out-$FMT-$1.html"; then echo "UNEXPECTED ($FMT) $1: $2"; FAIL=1; fi; }
  chk live "3vcr : Rp 7.000"; chk live "4vcr : Rp 15.000"
  chk profiles "Repair scripts"; chk user3 "jan/14/2030 10:20:30"
  chk tplbad "Template not saved"; nochk tplbad "uid="
  chk vprint2 "CUSTOM abc123"; chk vprint "abc123"; chk vpreview "mikhmon"; nochk vpreview "CUSTOM"
  chk sessions2 "Mock2"; chk newlogin "Mock2"; nochk oldlogin "Mock2"
  grep -q '"theme": "blue"' "$APP/data/config.json.php" || { echo "EXPECT FAILED ($FMT) theme not stored"; FAIL=1; }
  grep -q '"lang": "id"' "$APP/data/config.json.php" || { echo "EXPECT FAILED ($FMT) lang not stored"; FAIL=1; }
  grep -q 'secret' "$APP/data/config.json.php" && { echo "EXPECT FAILED ($FMT) password stored in clear"; FAIL=1; }
  grep -q 'new-1234' "$APP/data/config.json.php" && { echo "EXPECT FAILED ($FMT) router not removed"; FAIL=1; }
  [ "$(curl -s --noproxy '*' -o /dev/null -w '%{http_code}' "$B/data/config.json.php")" = 404 ] || { echo "EXPECT FAILED ($FMT) config is downloadable"; FAIL=1; }
  kill $PP $MP 2>/dev/null; wait 2>/dev/null
  [ "$(grep -c mikhmonlive-2 "$W/router-$FMT.log")" -ge 4 ] || { echo "EXPECT FAILED ($FMT) engine scripts not sent"; FAIL=1; }
  if [ -s "$MKL_ERRLOG" ]; then echo "== PHP FATAL ERRORS ($FMT) =="; cat "$MKL_ERRLOG"; FAIL=1; fi
done
[ $FAIL = 0 ] && echo "smoke test passed (legacy + iso)"
exit $FAIL
