#!/bin/bash
# The root sync script must drop everything that is not a plain peer / account.
ROOT=$(cd "$(dirname "$0")/.." && pwd)
T=$(mktemp -d)
cat > "$T/wg-peers.conf" <<'X'
[Interface]
PostUp = touch /tmp/pwned
[Peer]
PublicKey = AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
AllowedIPs = 10.99.1.1/32
[Peer]
PublicKey = BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB=
AllowedIPs = 0.0.0.0/0
[Peer]
PublicKey = CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC=
AllowedIPs = 10.99.1.2/32
Endpoint = 1.2.3.4:5
[Peer]
PublicKey = DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD=
AllowedIPs = 10.99.1.1/32
[Peer]
PublicKey = short=
AllowedIPs = 10.99.1.3/32
X
cat > "$T/chap-secrets" <<'X'
"mlabcdefghij" * "ABCDEFGHJKLMNPQRSTUVWXYZ" 10.99.129.1
"root" * "x" *
"mlabcdefghik" * "ABCDEFGHJKLMNPQRSTUVWXYZ" 192.168.1.1
"mlabcdefghil" * "ABCDEFGHJKLMNPQRSTUVWXY2" 10.99.129.1
"mlabcdefghim" * "ABCDEFGHJKLMNPQRSTUVWXY3" 10.99.129.2 extra
X
WG=$(STATE=$T MKL_VPN_CONF=/nonexistent "$ROOT/tools/vpn/sync.sh" --print-wg)
CH=$(STATE=$T MKL_VPN_CONF=/nonexistent "$ROOT/tools/vpn/sync.sh" --print-chap)
FAIL=0
[ "$(echo "$WG" | grep -c '^\[Peer\]$')" = 2 ] || { echo "FAILED: wireguard peers"; FAIL=1; }
echo "$WG" | grep -qE 'PostUp|Interface|Endpoint|0\.0\.0\.0|short' && { echo "FAILED: unwanted wireguard lines kept"; FAIL=1; }
[ "$CH" = '"mlabcdefghij" * "ABCDEFGHJKLMNPQRSTUVWXYZ" 10.99.129.1' ] || { echo "FAILED: chap lines: $CH"; FAIL=1; }
rm -rf "$T"
[ $FAIL = 0 ] && echo "vpn sync filter test passed"
exit $FAIL
