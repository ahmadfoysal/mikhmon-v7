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

## Routers that already have Mikhmon profiles

Open **User Profile**. Profiles written by an older Mikhmon are marked with a wrench; **Repair scripts** rewrites their on-login script and expiry monitor. Price, validity, expired mode and lock are kept, existing users and sales records keep working.

## Tests

```
php tests/unit.php      # engine, storage, template interpreter
tests/smoke.sh          # every page against a mock router, old and new date format
tests/tenant.sh         # two customers on one code folder stay isolated
```

The mock router does not run RouterOS scripts. Check these on a real router of each kind (v6.x, v7.0–7.9, v7.10+):

1. Add a profile with expired mode *Remove & Record*, validity `5m`, a price, lock enabled.
2. Generate a voucher and log in with it on the hotspot.
3. Within ~10 seconds the user comment becomes the expiry, e.g. `jan/14/2024 10:25:30`, and the MAC address is filled in.
4. The sale shows up under Report → Selling (today and this month) and on the dashboard.
5. After the validity passes (plus up to ~3 minutes) the user is removed and disconnected.
6. Repeat with *Notice & Record*: the user stays, with `limit-uptime=1s`.

Do not deploy `tests/` and `tools/` to a public web root without the deny rules above.
