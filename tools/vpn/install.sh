#!/bin/bash
# MikhmonLive VPN server setup (Debian / Ubuntu, run as root).
#
#   sudo bash tools/vpn/install.sh --data /www/mikhmon-data --web-user www --app /www/wwwroot/mikhmonlive
#
# What it sets up:
#   WireGuard  (UDP 51820)            for RouterOS v7 routers   10.99.0.0/17
#   L2TP/IPsec (UDP 500, 4500, 1701)  for RouterOS v6 routers   10.99.128.0/17
#   a firewall chain: routers can not reach the server or each other; only
#   connections the server opens to a router (the API) are allowed
#   a root service that applies the state files the portal writes
#
# Safe to run again: existing keys are kept.
# Afterwards open UDP 51820, 500 and 4500 in the aaPanel / cloud firewall and
# set 'vpn_host' in include/site.php.
set -euo pipefail

DATA=/www/mikhmon-data
WEBUSER=www
APP=""
PREFIX=10.99
WGPORT=51820
while [ $# -gt 0 ]; do
	case "$1" in
		--data) DATA=$2; shift 2 ;;
		--web-user) WEBUSER=$2; shift 2 ;;
		--app) APP=$2; shift 2 ;;
		--prefix) PREFIX=$2; shift 2 ;;
		--wg-port) WGPORT=$2; shift 2 ;;
		*) echo "unknown option $1" >&2; exit 1 ;;
	esac
done
[ "$(id -u)" = 0 ] || { echo "run as root" >&2; exit 1; }
id "$WEBUSER" >/dev/null 2>&1 || { echo "web user $WEBUSER does not exist" >&2; exit 1; }
HERE=$(cd "$(dirname "$0")" && pwd)
STATE=$DATA/vpn
SERVER=$PREFIX.0.1
WGIF=wgml

echo "== packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq wireguard-tools strongswan xl2tpd ppp iptables

echo "== state folder $STATE"
mkdir -p "$STATE"
chown "$WEBUSER":"$WEBUSER" "$STATE"
chmod 750 "$STATE"
cat > /etc/mikhmonlive-vpn.conf <<CONF
STATE=$STATE
PREFIX=$PREFIX
WGIF=$WGIF
CONF

echo "== WireGuard"
umask 077
mkdir -p /etc/wireguard
[ -s /etc/wireguard/$WGIF.key ] || wg genkey > /etc/wireguard/$WGIF.key
cat > /etc/wireguard/$WGIF.base <<CONF
[Interface]
Address = $SERVER/17
ListenPort = $WGPORT
PrivateKey = $(cat /etc/wireguard/$WGIF.key)
CONF
[ -s /etc/wireguard/$WGIF.conf ] || cp /etc/wireguard/$WGIF.base /etc/wireguard/$WGIF.conf
umask 022
wg pubkey < /etc/wireguard/$WGIF.key > "$STATE/server.pub"

echo "== L2TP/IPsec"
umask 077
[ -s /etc/mikhmonlive-l2tp.psk ] || head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 24 > /etc/mikhmonlive-l2tp.psk
PSK=$(cat /etc/mikhmonlive-l2tp.psk)
umask 022
printf '%s\n' "$PSK" > "$STATE/l2tp.psk"
chown "$WEBUSER":"$WEBUSER" "$STATE/server.pub" "$STATE/l2tp.psk"
chmod 640 "$STATE/server.pub" "$STATE/l2tp.psk"

touch /etc/ipsec.secrets /etc/ipsec.conf
sed -i '/^# BEGIN mikhmonlive$/,/^# END mikhmonlive$/d' /etc/ipsec.secrets /etc/ipsec.conf
cat >> /etc/ipsec.secrets <<CONF
# BEGIN mikhmonlive
%any %any : PSK "$PSK"
# END mikhmonlive
CONF
chmod 600 /etc/ipsec.secrets
cat >> /etc/ipsec.conf <<CONF
# BEGIN mikhmonlive
conn mikhmonlive-l2tp
	type=transport
	keyexchange=ikev1
	authby=secret
	left=%defaultroute
	leftprotoport=17/1701
	right=%any
	rightprotoport=17/%any
	ike=aes256-sha1-modp2048,aes256-sha1-modp1024,aes128-sha1-modp1024,3des-sha1-modp1024!
	esp=aes256-sha1,aes128-sha1,3des-sha1!
	dpdaction=clear
	dpddelay=30s
	rekey=no
	auto=add
# END mikhmonlive
CONF

cat > /etc/xl2tpd/xl2tpd.conf <<CONF
; written by MikhmonLive install.sh
[global]
port = 1701

[lns default]
; every account has a fixed address in chap-secrets; this range is only a fallback
ip range = $PREFIX.255.2-$PREFIX.255.250
local ip = $SERVER
require chap = yes
refuse pap = yes
require authentication = yes
name = mikhmonlive
pppoptfile = /etc/ppp/options.mikhmonlive
length bit = yes
CONF
cat > /etc/ppp/options.mikhmonlive <<CONF
name mikhmonlive
require-mschap-v2
refuse-pap
refuse-chap
refuse-mschap
auth
noccp
nobsdcomp
nodeflate
nodefaultroute
mtu 1400
mru 1400
lcp-echo-interval 30
lcp-echo-failure 4
CONF

echo "== firewall"
cat > /usr/local/sbin/mikhmonlive-vpn-fw <<'FW'
#!/bin/bash
# Routers may not start connections to the server or to each other.
. /etc/mikhmonlive-vpn.conf
WGPORT=$(sed -n 's/^ListenPort = //p' /etc/wireguard/$WGIF.base)
ins() { iptables -C "$@" 2>/dev/null || iptables -I "$@"; }
iptables -N MKL-VPN 2>/dev/null || iptables -F MKL-VPN
iptables -A MKL-VPN -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
iptables -A MKL-VPN -p icmp --icmp-type echo-request -j ACCEPT
iptables -A MKL-VPN -j DROP
for ifc in "$WGIF" ppp+; do
	ins INPUT -i "$ifc" -j MKL-VPN
	ins FORWARD -i "$ifc" -j DROP
done
# L2TP is only accepted inside IPsec
ins INPUT -p udp --dport 1701 -m policy --dir in --pol none -j DROP
ins INPUT -p udp --dport 1701 -m policy --dir in --pol ipsec -j ACCEPT
ins INPUT -p udp -m multiport --dports 500,4500,"$WGPORT" -j ACCEPT
ins INPUT -p esp -j ACCEPT
FW
chmod 755 /usr/local/sbin/mikhmonlive-vpn-fw
install -m 755 "$HERE/sync.sh" /usr/local/sbin/mikhmonlive-vpn-sync

echo "== services"
cat > /etc/systemd/system/mikhmonlive-vpn-fw.service <<CONF
[Unit]
Description=MikhmonLive VPN firewall
After=network-online.target wg-quick@$WGIF.service
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/local/sbin/mikhmonlive-vpn-fw

[Install]
WantedBy=multi-user.target
CONF
cat > /etc/systemd/system/mikhmonlive-vpn-sync.service <<CONF
[Unit]
Description=MikhmonLive VPN sync

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/mikhmonlive-vpn-sync
CONF
cat > /etc/systemd/system/mikhmonlive-vpn-sync.path <<CONF
[Unit]
Description=Apply MikhmonLive VPN changes written by the portal

[Path]
PathChanged=$STATE/wg-peers.conf
PathChanged=$STATE/chap-secrets

[Install]
WantedBy=multi-user.target
CONF
cat > /etc/systemd/system/mikhmonlive-vpn-sync.timer <<CONF
[Unit]
Description=MikhmonLive VPN sync (safety net)

[Timer]
OnBootSec=1min
OnUnitActiveSec=5min

[Install]
WantedBy=timers.target
CONF
systemctl daemon-reload
systemctl enable --now wg-quick@$WGIF
systemctl enable --now mikhmonlive-vpn-fw.service mikhmonlive-vpn-sync.path mikhmonlive-vpn-sync.timer
systemctl enable xl2tpd
systemctl restart xl2tpd
systemctl enable strongswan-starter 2>/dev/null || systemctl enable strongswan 2>/dev/null || true
ipsec restart >/dev/null 2>&1 || systemctl restart strongswan-starter 2>/dev/null || systemctl restart strongswan 2>/dev/null || true
/usr/local/sbin/mikhmonlive-vpn-sync

if [ -n "$APP" ] && [ -f "$APP/tools/portal.php" ]; then
	# once a day: tunnels of expired accounts are switched off
	echo "17 0 * * * $WEBUSER php $APP/tools/portal.php vpn-sync >/dev/null 2>&1" > /etc/cron.d/mikhmonlive-vpn
fi

echo
echo "Done."
echo "  WireGuard public key : $(cat "$STATE/server.pub")"
echo "  Server tunnel address: $SERVER"
echo "Next: open UDP $WGPORT, 500 and 4500 in the firewall, point a DNS name at this"
echo "server and set 'vpn_host' => '<that name>' in include/site.php."
