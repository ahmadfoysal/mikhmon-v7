# MikhmonLive

Hotspot voucher management for MikroTik, based on [MIKHMON v3](https://github.com/laksa19/mikhmonv3) by Laksamadi Guko (GPL v2).

What is different from classic Mikhmon:

- **Works on every RouterOS version.** RouterOS 7.10 changed the date format from `jan/14/2024` to `2024-01-14`, which broke voucher expiry, the expiry monitor and the sales reports. The router scripts now detect the format themselves and all pages read both formats. See `lib/mikhmonlive.php`.
- **One code folder, many customers.** Each customer gets a subdomain and a private data folder. See `lib/mkl_store.php`.
- **PHP 7.4 – 8.3.**
- **Safe on a shared server.** No customer input is ever written to an executable file: settings are JSON, voucher templates are interpreted (not included), router passwords are AES-256-GCM encrypted, admin passwords are hashed.

## Single owner (like classic Mikhmon)

Upload the folder, open it in the browser, log in with `mikhmon` / `1234` and change the password. Data is kept in `./data/`.

To move an existing installation: `php tools/tenant.php import - /path/to/old/include/config.php`

## Many customers (cloud)

1. DNS: wildcard record `*.mikhmonlive.com` to the server. One website with the domain `*.mikhmonlive.com` (wildcard SSL certificate), document root = this folder, PHP 8.x.
2. Create the data folder outside the web root, owned by the web user:
   `mkdir -p /www/mikhmon-data/tenants && chown -R www:www /www/mikhmon-data`
3. `cp include/site.sample.php include/site.php` and edit it.
4. aaPanel limits PHP to the site folder. Add the data folder to `open_basedir` in the site's `.user.ini`:
   `open_basedir=/www/wwwroot/<site>/:/www/mikhmon-data/:/tmp/`
5. Add the deny rules from `nginx.conf` to the site configuration (nginx ignores `.htaccess`).
6. Create accounts (run as the web user, e.g. `sudo -u www php ...`):

```
php tools/tenant.php create shop1 --user=owner --pass=secret123 --routers=1 --expires=2027-12-31
php tools/tenant.php set    shop1 --routers=3          # change the router limit
php tools/tenant.php renew  shop1 --years=1            # extend the subscription
php tools/tenant.php set    shop1 --status=suspended
php tools/tenant.php import shop1 /path/to/old/include/config.php
php tools/tenant.php list
```

The customer portal calls the same functions (`mkl_tenant_create`, `mkl_tenant_write` in `lib/mkl_store.php`).

Router address: `host`, `host:port` or `ssl://host:port` (API-SSL). On a shared server private addresses are refused unless their network is listed in `private_nets` (for example the VPN network the routers connect to).

## Customer portal (registration, subscription, payment)

The portal is a second website for the bare domain. It uses the same code folder.

1. Website `mikhmonlive.com` (+ `www`), document root = `portal/public`, PHP 8.x with `pdo_sqlite`, `curl` and `sodium`. Give it the same `open_basedir` addition as above.
2. Fill in the portal section of `include/site.php` (price, trial, manual payment text, PayStation credentials).
3. Register yourself in the portal, then: `php tools/portal.php admin you@example.com`. The **Admin** page lists customers, approves manual payments and edits router limit, expiry and status.

How it works:

- Registration creates the customer's data folder at once (free trial, or closed until the first payment when `trial_days` is 0). The Mikhmon login is the subdomain name with the portal password; **Open Mikhmon** logs in with a one-time link.
- An order is *N routers for Y years* = `N x Y x price_per_router`. When it is paid the router limit becomes N and Y years are added to the subscription.
- Online payment (PayStation): the order is only marked paid after the server asked PayStation for the transaction and status, invoice and amount match. The browser return and the IPN are treated as hints.
- Manual payment: the customer enters method, sender number and transaction ID; you approve or reject it.
- The database is one SQLite file next to the tenant folder (`portal.sqlite`). Back it up together with the tenant folder and the key file.

Not included yet: e-mail (verification, password reset by mail, expiry reminders). A lost password is reset by the admin.

## VPN onboarding (routers without a public IP)

```
sudo bash tools/vpn/install.sh --data /www/mikhmon-data --web-user www --app /www/wwwroot/<site>
```

This installs WireGuard (RouterOS v7) and L2TP/IPsec (RouterOS v6) on the server, a firewall chain (routers cannot reach the server or each other; only the server opens connections to them) and a root service that applies what the portal writes. Then open UDP 51820, 500 and 4500 in the firewall, point a DNS name at the server and set `vpn_host` in `include/site.php`.

In the portal the customer opens **Connect router**, names the router, picks the RouterOS version and pastes the shown script into the router terminal. The script creates the tunnel and an API user that only the VPN server can use; the router is added to the customer's Mikhmon automatically. A customer can only reach the tunnel addresses of their own routers, and the tunnels of an expired account are switched off.

## Routers that already have Mikhmon profiles

Open **User Profile**. Profiles written by an older Mikhmon are marked with a wrench; **Repair scripts** rewrites their on-login script and expiry monitor. Price, validity, expired mode and lock are kept, existing users and sales records keep working.

## Tests

```
php tests/unit.php      # engine, storage, template interpreter
tests/smoke.sh          # every page against a mock router, old and new date format
tests/tenant.sh         # two customers on one code folder stay isolated
python3 tests/portal_test.py   # portal: registration, payments (mock PayStation), VPN, admin
tests/vpn_sync.sh       # the root VPN sync script drops injected settings
```

The mock router does not run RouterOS scripts. Check these on a real router of each kind (v6.x, v7.0–7.9, v7.10+):

1. Add a profile with expired mode *Remove & Record*, validity `5m`, a price, lock enabled.
2. Generate a voucher and log in with it on the hotspot.
3. Within ~10 seconds the user comment becomes the expiry, e.g. `jan/14/2024 10:25:30`, and the MAC address is filled in.
4. The sale shows up under Report → Selling (today and this month) and on the dashboard.
5. After the validity passes (plus up to ~3 minutes) the user is removed and disconnected.
6. Repeat with *Notice & Record*: the user stays, with `limit-uptime=1s`.

The mock router does not run RouterOS scripts, and nothing here starts a real VPN server or talks to the real PayStation. Before going live also check:

- `tools/vpn/install.sh` on the real server, then one v7 router (WireGuard) and one v6 router (L2TP/IPsec) with the pasted script: the tunnel comes up and Mikhmon connects.
- One PayStation sandbox payment end to end.

Do not deploy `tests/` and `tools/` to a public web root without the deny rules above.
