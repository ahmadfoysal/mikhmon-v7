#!/bin/bash
# MikhmonLive VPN sync - applies the state written by the portal.
#
# The portal (web user) only writes two files into the state folder:
#   wg-peers.conf   WireGuard peers
#   chap-secrets    L2TP accounts
# This script runs as root (systemd path unit + timer), checks every line
# against a strict pattern and applies it. Lines that do not match are dropped,
# so the web user can never inject other settings.
#
# Settings come from /etc/mikhmonlive-vpn.conf (written by install.sh).
#   sync.sh              apply
#   sync.sh --print-wg   only print the checked WireGuard peers
#   sync.sh --print-chap only print the checked L2TP accounts
set -u
CONF=${MKL_VPN_CONF:-/etc/mikhmonlive-vpn.conf}
[ -r "$CONF" ] && . "$CONF"
STATE=${STATE:-/www/mikhmon-data/vpn}
PREFIX=${PREFIX:-10.99}          # first two numbers of every tunnel address
WGIF=${WGIF:-wgml}
PFX=${PREFIX//./\\.}

wg_peers() {
	[ -r "$STATE/wg-peers.conf" ] || return 0
	# a peer is exactly: [Peer] / PublicKey / AllowedIPs (one /32 inside the VPN range)
	# (no {n,m} repeats: old mawk versions do not know them)
	awk -v pfx="^AllowedIPs = $PFX\\\\.[0-9]+\\\\.[0-9]+/32$" '
		/^\[Peer\]$/ { key = ""; next }
		/^PublicKey = [A-Za-z0-9+\/]+=$/ { key = (length($3) == 44) ? $0 : ""; next }
		$0 ~ pfx { if (key != "" && !seen[key]++ && !ips[$0]++) print "[Peer]\n" key "\n" $0 "\n"; key = ""; next }
	' "$STATE/wg-peers.conf"
}

chap_lines() {
	[ -r "$STATE/chap-secrets" ] || return 0
	grep -E "^\"ml[a-z0-9]{10}\" \* \"[A-Za-z0-9]{24}\" $PFX\.[0-9]{1,3}\.[0-9]{1,3}\$" "$STATE/chap-secrets" | awk '!u[$1]++ && !i[$4]++'
}

case "${1:-}" in
	--print-wg) wg_peers; exit 0 ;;
	--print-chap) chap_lines; exit 0 ;;
esac

[ "$(id -u)" = 0 ] || { echo "run as root" >&2; exit 1; }

# ---- WireGuard -------------------------------------------------------------
if [ -r /etc/wireguard/$WGIF.base ] && command -v wg >/dev/null; then
	umask 077
	{ cat /etc/wireguard/$WGIF.base; echo; wg_peers; } > /etc/wireguard/$WGIF.conf.new
	mv /etc/wireguard/$WGIF.conf.new /etc/wireguard/$WGIF.conf
	if ip link show "$WGIF" >/dev/null 2>&1; then
		wg syncconf "$WGIF" <(wg-quick strip "$WGIF")
	fi
fi

# ---- L2TP ------------------------------------------------------------------
if [ -d /etc/ppp ]; then
	umask 077
	touch /etc/ppp/chap-secrets
	{
		sed '/^# BEGIN mikhmonlive$/,/^# END mikhmonlive$/d' /etc/ppp/chap-secrets
		echo "# BEGIN mikhmonlive"
		chap_lines
		echo "# END mikhmonlive"
	} > /etc/ppp/chap-secrets.new
	mv /etc/ppp/chap-secrets.new /etc/ppp/chap-secrets
	# hang up sessions whose account is gone
	allowed=" $(chap_lines | awk '{print $4}' | tr '\n' ' ')"
	for pidfile in /run/ppp*.pid /var/run/ppp*.pid; do
		[ -r "$pidfile" ] || continue
		ifc=$(basename "$pidfile" .pid)
		peer=$(ip -o -4 addr show dev "$ifc" 2>/dev/null | sed -n 's/.* peer \([0-9.]*\).*/\1/p')
		case "$peer" in
			$PREFIX.*) case "$allowed" in *" $peer "*) ;; *) kill "$(cat "$pidfile")" 2>/dev/null ;; esac ;;
		esac
	done
fi
exit 0
