#!/usr/bin/env python3
"""Minimal RouterOS API mock for smoke tests.

Usage: mock_routeros.py PORT legacy|iso LOGFILE
  legacy -> dates like jan/14/2024 (RouterOS <= 7.9)
  iso    -> dates like 2024-01-14  (RouterOS >= 7.10)
Every add/set/remove sentence is appended to LOGFILE as JSON.
"""
import json, socket, socketserver, sys, datetime

PORT, FMT, LOG = int(sys.argv[1]), sys.argv[2], sys.argv[3]
NOW = datetime.datetime.now()
MON = "jan feb mar apr may jun jul aug sep oct nov dec".split()

def d(dt, fmt=None):
    fmt = fmt or FMT
    return dt.strftime("%Y-%m-%d") if fmt == "iso" else "%s/%02d/%04d" % (MON[dt.month - 1], dt.day, dt.year)

def owner(dt, fmt):
    return "%02d%04d" % (dt.month, dt.year) if fmt == "iso" else "%s%04d" % (MON[dt.month - 1], dt.year)

def sale(i, dt, fmt, price):
    name = "-|-".join([d(dt, fmt), "10:20:3%d" % i, "user%d" % i, str(price), "10.0.0.%d" % i,
                       "AA:BB:CC:00:00:0%d" % i, "1d", "1day", "vc-123-01.14.24-"])
    return {".id": "*S%d" % i, "name": name, "owner": owner(dt, fmt), "source": d(dt, fmt), "comment": "mikhmon"}

YDAY = NOW - datetime.timedelta(days=1)
OLD_ONLOGIN = ':put (",remc,1000,1d,1200,,Enable,"); {:local date [ /system clock get date ];:local year [ :pick $date 7 11 ]}'
DATA = {
    "/system/identity/print": [{"name": "MockTik"}],
    "/system/clock/print": [{"date": d(NOW), "time": NOW.strftime("%H:%M:%S"), "time-zone-name": "Asia/Dhaka"}],
    "/system/resource/print": [{"version": "7.16 (stable)" if FMT == "iso" else "6.49.10 (long-term)", "uptime": "1d2h3m4s",
                                "cpu-load": "5", "free-memory": "100000000", "total-memory": "200000000",
                                "free-hdd-space": "100000000", "total-hdd-space": "200000000", "board-name": "CHR"}],
    "/system/routerboard/print": [{"model": "CHR"}],
    "/ip/hotspot/print": [{".id": "*1", "name": "hotspot1", "interface": "bridge", "address-pool": "hs-pool",
                           "profile": "hsprof1", "idle-timeout": "5m", "addresses-per-mac": "2", "disabled": "false"}],
    "/ip/hotspot/user/profile/print": [
        {".id": "*P1", "name": "default", "shared-users": "1"},
        {".id": "*P2", "name": "1day", "shared-users": "1", "rate-limit": "1M/1M", "on-login": OLD_ONLOGIN},
        {".id": "*P3", "name": "free", "shared-users": "1", "on-login": ':put (",,0,,,noexp,Disable,")'},
    ],
    "/ip/hotspot/user/print": [
        {".id": "*U1", "name": "abc123", "password": "abc123", "profile": "1day", "uptime": "1h2m", "bytes-in": "1000",
         "bytes-out": "2000", "comment": "vc-123-01.14.24-", "disabled": "false", "limit-uptime": "0s"},
        {".id": "*U2", "name": "olduser", "password": "x", "profile": "1day", "uptime": "0s", "bytes-in": "0",
         "bytes-out": "0", "comment": "jan/14/2030 10:20:30", "disabled": "false", "mac-address": "AA:BB:CC:00:00:01"},
        {".id": "*U3", "name": "isouser", "password": "isouser", "profile": "1day", "uptime": "0s", "bytes-in": "0",
         "bytes-out": "0", "comment": "2030-01-14 10:20:30 note", "disabled": "false"},
    ],
    "/ip/hotspot/active/print": [{".id": "*A1", "user": "abc123", "address": "10.0.0.5", "mac-address": "AA:BB:CC:00:00:05",
                                  "uptime": "5m", "bytes-in": "10", "bytes-out": "20", "server": "hotspot1", "login-by": "http-chap"}],
    "/ip/hotspot/cookie/print": [{".id": "*C1", "user": "abc123"}, {".id": "*C2", "user": "abc123"}],
    "/system/scheduler/print": [{".id": "*M1", "name": "1day", "disabled": "false", "interval": "2m10s",
                                 "start-time": "01:10:10", "on-event": "old", "comment": "Monitor Profile 1day",
                                 "next-run": d(NOW) + " 23:59:59"}],
    # sales written in BOTH formats: 2 today legacy, 1 today iso, 1 yesterday iso
    "/system/script/print": [sale(1, NOW, "legacy", 1000), sale(2, NOW, "legacy", 2000),
                             sale(3, NOW, "iso", 4000), sale(4, YDAY, "iso", 8000)],
    "/ip/pool/print": [{".id": "*1", "name": "hs-pool"}],
    "/interface/print": [{".id": "*1", "name": "ether1", "type": "ether"}],
    "/log/print": [{".id": "*1", "time": d(NOW) + " 10:00:00", "topics": "hotspot,info,debug", "message": "abc123 (10.0.0.5): logged in"}],
}

def enc_len(n):
    if n < 0x80: return bytes([n])
    if n < 0x4000: return (n | 0x8000).to_bytes(2, "big")
    if n < 0x200000: return (n | 0xC00000).to_bytes(3, "big")
    return (n | 0xE0000000).to_bytes(4, "big")

class H(socketserver.BaseRequestHandler):
    def rd(self, n):
        b = b""
        while len(b) < n:
            c = self.request.recv(n - len(b))
            if not c: raise EOFError
            b += c
        return b
    def word(self):
        b = self.rd(1)[0]
        if b & 0x80 == 0: n = b
        elif b & 0xC0 == 0x80: n = ((b & 0x3F) << 8) | self.rd(1)[0]
        elif b & 0xE0 == 0xC0: n = ((b & 0x1F) << 16) | int.from_bytes(self.rd(2), "big")
        else: n = ((b & 0x0F) << 24) | int.from_bytes(self.rd(3), "big")
        return self.rd(n).decode("utf-8", "replace")
    def send(self, words):
        out = b""
        for w in words:
            w = w.encode()
            out += enc_len(len(w)) + w
        self.request.sendall(out + b"\0")
    def handle(self):
        try:
            while True:
                s = []
                while True:
                    w = self.word()
                    if w == "": break
                    s.append(w)
                if s: self.reply(s)
        except (EOFError, ConnectionError):
            pass
    def reply(self, s):
        cmd, args = s[0], s[1:]
        if cmd.endswith("/print"):
            rows = DATA.get(cmd, [])
            conds = [a[1:].split("=", 1) for a in args if a.startswith("?") and not a.startswith("?#")]
            conds = [c for c in conds if len(c) == 2]
            is_or = any(a == "?#|" for a in args)
            if conds:
                f = (lambda r: any(r.get(k, "") == v for k, v in conds)) if is_or else \
                    (lambda r: all(r.get(k, "") == v for k, v in conds))
                rows = [r for r in rows if f(r)]
            if any(a.startswith("=count-only=") for a in args):
                return self.send(["!done", "=ret=%d" % len(rows)])
            for r in rows:
                self.send(["!re"] + ["=%s=%s" % kv for kv in r.items()])
            return self.send(["!done"])
        if cmd != "/login":
            with open(LOG, "a") as f:
                f.write(json.dumps(s) + "\n")
        self.send(["!done", "=ret=*NEW"] if cmd.endswith("/add") else ["!done"])

class S(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True

S(("127.0.0.1", PORT), H).serve_forever()
