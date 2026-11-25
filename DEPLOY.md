# MikhmonLive: aaPanel সার্ভারে deploy করার গাইড

এই গাইড ক্রম অনুযায়ী সাজানো। পুরো ব্যবস্থা একটাই VPS-এ চলে:

| ঠিকানা | কী | কোথায় |
|---|---|---|
| `mikhmonlive.com` | Portal (landing page, registration, billing, admin) | `/www/wwwroot/mikhmonlive/portal/public` |
| `*.mikhmonlive.com` | গ্রাহকদের Mikhmon | `/www/wwwroot/mikhmonlive` |
| `vpn.mikhmonlive.com` | রাউটারের tunnel (WireGuard + L2TP/IPsec) | একই VPS |
| — | সব গ্রাহকের ডেটা, অর্ডারের database, এনক্রিপশন key | `/www/mikhmon-data` (web root-এর বাইরে) |

> এই ধাপগুলো কোডের প্রয়োজন আর aaPanel-এর সাধারণ গঠন থেকে লেখা; আপনার aaPanel ভার্সনে কোনো মেনুর নাম একটু আলাদা হতে পারে।

---

## ১. যা লাগবে

- Ubuntu 22.04/24.04 VPS, aaPanel ইনস্টল করা, একটা public IP। VPN script শুধু Ubuntu/Debian-এ চলে।
- aaPanel App Store থেকে **Nginx** আর **PHP 8.2** (বা 8.3)। Database লাগবে না।
- PHP-তে এই extension-গুলো থাকতে হবে: `curl`, `openssl`, `pdo_sqlite`, `sodium`, `mbstring`। দেখতে:

  ```bash
  /www/server/php/82/bin/php -m
  ```

  কোনোটা না থাকলে: App Store → PHP 8.2 → Setting → Install extensions।
- একই জায়গায় **Disabled functions**-এর তালিকায় `stream_socket_client` আর `fsockopen` থাকলে সরিয়ে দিন। এগুলো ছাড়া Mikhmon রাউটারে আর SMTP-তে যুক্ত হতে পারবে না।

## ২. DNS

চারটা **A record**, সবগুলো সার্ভারের IP-তে:

```
mikhmonlive.com        → সার্ভারের IP
www.mikhmonlive.com    → সার্ভারের IP
*.mikhmonlive.com      → সার্ভারের IP
vpn.mikhmonlive.com    → সার্ভারের IP
```

Cloudflare ব্যবহার করলে অন্তত `vpn` রেকর্ডটা **DNS only (ধূসর মেঘ)** রাখুন; Cloudflare VPN-এর UDP পার করে না। শুরুতে সবগুলো DNS only রাখাই সহজ।

## ৩. কোড আর ডেটা ফোল্ডার

aaPanel → Terminal (বা SSH, root হিসেবে):

```bash
cd /www/wwwroot && git clone https://github.com/ahmadfoysal/mikhmon-v7 mikhmonlive
```

```bash
mkdir -p /www/mikhmon-data/tenants
```

```bash
chown -R www:www /www/wwwroot/mikhmonlive /www/mikhmon-data
```

Repo private হলে clone-এর সময় GitHub username আর একটা Personal Access Token চাইবে।

## ৪. সেটিংস ফাইল

```bash
cd /www/wwwroot/mikhmonlive && cp include/site.sample.php include/site.php
```

aaPanel → Files দিয়ে `include/site.php` খুলে এগুলো বসান:

- `tenant_root` → `/www/mikhmon-data/tenants` (যেমন আছে)
- `base_domain` → `mikhmonlive.com`
- `portal_url` → `https://mikhmonlive.com`
- `packages`, `trial_days`, `support_contact`, `manual_payment` (আপনার bKash/Nagad নম্বর)
- ইমেইল, PayStation আর VPN-এর লাইনগুলো আপাতত খালি রাখুন; ধাপ ৯–১১-এ ভরবেন।
- `private_nets` বন্ধই রাখুন। প্রতিটি গ্রাহকের নিজের tunnel ঠিকানা আপনা থেকেই অনুমোদিত হয়।

এই ফাইল Git-এ যায় না, তাই পরে `git pull` করলেও মুছবে না।

## ৫. প্রথম সাইট: গ্রাহকদের Mikhmon (`*.mikhmonlive.com`)

1. **Website → Add site:**
   - Domain: `*.mikhmonlive.com`
   - Root directory: `/www/wwwroot/mikhmonlive`
   - PHP: 8.2, Database: none
2. aaPanel root-এ `index.html` বা `404.html` বানিয়ে থাকলে মুছে দিন, নইলে Mikhmon-এর বদলে সেগুলো খুলবে।
3. **open_basedir:** aaPanel PHP-কে শুধু সাইটের ফোল্ডারে আটকে রাখে, তাই ডেটা ফোল্ডারটা যোগ করতে হবে। ফাইলটা lock করা থাকে:

   ```bash
   chattr -i /www/wwwroot/mikhmonlive/.user.ini
   ```

   ফাইলের ভেতরের লাইনটা এভাবে বদলান:

   ```
   open_basedir=/www/wwwroot/mikhmonlive/:/www/mikhmon-data/:/tmp/
   ```

   ```bash
   chattr +i /www/wwwroot/mikhmonlive/.user.ini
   ```

4. **Site → Config file:** `server {` ব্লকের ভেতরে, PHP-র `include enable-php-82.conf;` লাইনের **উপরে** যোগ করুন। এগুলো tools, tests আর portal-এর source কোড বাইরে থেকে খোলা বন্ধ করে:

   ```
   location ~ ^/(data|tests|tools|portal|\.git)(/|$) { return 404; }
   location ~ \.(tpl|json|md|sh|py|sample\.php)$ { return 404; }
   ```

5. **SSL:** wildcard সার্টিফিকেটের জন্য Let's Encrypt-এ **DNS verification** লাগে।
   - SSL → Let's Encrypt → DNS verification → আপনার DNS provider বেছে নিন (Cloudflare হলে API token দিন)।
   - Domain `*.mikhmonlive.com`।
   - তারপর **Force HTTPS** চালু করুন।

## ৬. দ্বিতীয় সাইট: Portal (`mikhmonlive.com`)

1. **Website → Add site:**
   - Domain: `mikhmonlive.com` আর `www.mikhmonlive.com`
   - Root directory: `/www/wwwroot/mikhmonlive/portal/public`
   - PHP: 8.2
2. **open_basedir:** portal-এর কোড উপরের ফোল্ডার থেকে লাইব্রেরি পড়ে, তাই এখানে **পুরো** কোড ফোল্ডার দিতে হবে:

   ```bash
   chattr -i /www/wwwroot/mikhmonlive/portal/public/.user.ini
   ```

   ```
   open_basedir=/www/wwwroot/mikhmonlive/:/www/mikhmon-data/:/tmp/
   ```

   ```bash
   chattr +i /www/wwwroot/mikhmonlive/portal/public/.user.ini
   ```

3. **Config file**-এ (SEO-র জন্য) যোগ করুন:

   ```
   location = /robots.txt  { rewrite ^ /index.php?p=robots last; }
   location = /sitemap.xml { rewrite ^ /index.php?p=sitemap last; }
   ```

4. **SSL:** Let's Encrypt (সাধারণ file verification যথেষ্ট), Force HTTPS চালু।
5. **Redirect:** `www.mikhmonlive.com` → `https://mikhmonlive.com` (301)।
6. দুটো `.user.ini` বদলের পর PHP restart দিন: App Store → PHP 8.2 → Restart।

## ৭. প্রথম পরীক্ষা

- `https://mikhmonlive.com` খুললে landing page আসার কথা।
- `https://abc.mikhmonlive.com` খুললে "Account not found" আসার কথা; এর মানে wildcard সাইট ঠিক আছে।
- কোনো পেজ সাদা বা 500 দেখালে: Website → Logs, আর `/www/server/php/82/var/log/php-fpm.log`। বেশিরভাগ ক্ষেত্রে কারণ `open_basedir`।

## ৮. নিজেকে admin বানানো

1. Portal-এ নিজের ইমেইল দিয়ে register করুন।
2. Terminal-এ (ইমেইলটা আপনারটা দিন):

   ```bash
   cd /www/wwwroot/mikhmonlive && sudo -u www /www/server/php/82/bin/php tools/portal.php admin you@example.com
   ```

3. আবার লগইন করলে উপরের মেনুতে **Admin** দেখাবে।

## ৯. ইমেইল

1. aaPanel Mail Server বা যেকোনো SMTP সার্ভিসে `noreply@mikhmonlive.com` mailbox খুলুন।
2. `site.php`-তে `smtp_host`, `smtp_port`, `smtp_secure`, `smtp_user`, `smtp_pass`, `mail_from` আর `admin_email` বসান।
3. পরীক্ষা করুন:

   ```bash
   cd /www/wwwroot/mikhmonlive && sudo -u www /www/server/php/82/bin/php tools/portal.php mail-test you@example.com
   ```

4. ডোমেইনের DNS-এ **SPF** আর **DKIM** record দিন, নইলে ইমেইল spam-এ যাবে। ইমেইল চালু থাকলে নতুন গ্রাহক ইমেইল নিশ্চিত না করা পর্যন্ত আটকে থাকে, তাই এটা জরুরি।

## ১০. PayStation

1. `site.php`-তে `paystation_merchant` আর `paystation_password` বসান, `paystation_sandbox` → `true`।
2. PayStation panel-এ IPN URL দিন `https://mikhmonlive.com/?p=pay-ipn`।
3. নিজে একটা প্যাকেজ কিনে দেখুন, অর্ডার "Paid" হয় কি না।
4. ঠিক থাকলে live credentials বসিয়ে `paystation_sandbox` → `false`।

## ১১. VPN (public IP ছাড়া রাউটারের জন্য)

1. Install script চালান:

   ```bash
   cd /www/wwwroot/mikhmonlive && bash tools/vpn/install.sh --data /www/mikhmon-data --web-user www --app /www/wwwroot/mikhmonlive
   ```

2. aaPanel → **Security**-তে UDP `51820`, `500`, `4500` খুলুন। VPS provider-এর firewall থাকলে সেখানেও খুলুন।
3. `site.php`-তে `'vpn_host' => 'vpn.mikhmonlive.com'` বসান।
4. Portal → Connect router থেকে একটা v7 রাউটার আর একটা v6 রাউটার যুক্ত করে দেখুন: tunnel ওঠে কি না, আর Mikhmon-এ রাউটার "Connected" হয় কি না।

### VPN কীভাবে কাজ করে

```
                       আপনার একটাই VPS (aaPanel)
        ┌──────────────────────────────────────────────────────┐
        │  Nginx + PHP                                         │
        │   ├─ mikhmonlive.com      → portal                   │
        │   └─ *.mikhmonlive.com    → গ্রাহকদের Mikhmon ─┐      │
        │                                               │ API  │
        │  VPN server (সার্ভারের tunnel ঠিকানা 10.99.0.1)   ▼ 8728 │
        │   ├─ WireGuard    UDP 51820   ← v7 রাউটার            │
        │   └─ L2TP/IPsec   UDP 500,4500 ← v6 রাউটার           │
        └───────────────▲───────────────────────▲──────────────┘
                        │ এনক্রিপ্টেড tunnel     │
              (রাউটার নিজেই বাইরে কল করে, তাই NAT-এর পেছনেও চলে)
                        │                       │
          রহিমের রাউটার (v7)            করিমের রাউটার (v6)
          tunnel ঠিকানা 10.99.1.1        tunnel ঠিকানা 10.99.129.1
```

1. গ্রাহক portal-এ "Connect router" চাপলে portal রাউটারের জন্য একটা স্থায়ী tunnel ঠিকানা আর key (v7) বা username/password (v6) ঠিক করে। v7 রাউটার পায় `10.99.1.x` থেকে, v6 রাউটার পায় `10.99.129.x` থেকে।
2. গ্রাহক স্ক্রিপ্টটা রাউটারে paste করে। রাউটার নিজেই `vpn.mikhmonlive.com`-এ সংযোগ করে, ২৫ সেকেন্ড পরপর সংকেত পাঠিয়ে NAT বা মোবাইল ইন্টারনেটেও tunnel খোলা রাখে।
3. Mikhmon (PHP) ওই tunnel ঠিকানায়, যেমন `10.99.1.1:8728`-এ, রাউটারের API-তে কথা বলে।
4. Portal নিজে সার্ভারের VPN সেটিংস বদলায় না। সে শুধু `/www/mikhmon-data/vpn/` ফোল্ডারে দুটো তালিকা লেখে; একটা root service সেগুলো যাচাই করে প্রয়োগ করে। তাই web সাইট হ্যাক হলেও কেউ VPN-এ উল্টাপাল্টা সেটিং ঢোকাতে পারবে না।

**কেন একই সার্ভারে:** Mikhmon-এর PHP সরাসরি `10.99.x.x` ঠিকানায় সংযোগ করে, আর ঠিকানাগুলো আছে শুধু সেই মেশিনে যেখানে VPN চলছে। tunnel দিয়ে শুধু Mikhmon-এর কমান্ড যায়, হটস্পট ইউজারদের ইন্টারনেট যায় না, তাই চাপ খুব কম। একটা মাঝারি VPS-এ কয়েকশো রাউটার স্বচ্ছন্দে চলবে।

**নিরাপত্তা:**

- কোনো রাউটার VPN দিয়ে সার্ভারে বা অন্য গ্রাহকের রাউটারে ঢুকতে পারে না; শুধু সার্ভার রাউটারের দিকে সংযোগ খোলে (firewall chain `MKL-VPN`)।
- রাউটারের API user শুধু `10.99.0.1` (সার্ভার) থেকে লগইন করতে পারে।
- একজন গ্রাহকের Mikhmon শুধু নিজের রাউটারের tunnel ঠিকানায় যেতে পারে।
- সাবস্ক্রিপশন শেষ হলে tunnel বন্ধ হয়, নবায়নে আবার চালু হয়।

**সার্ভারের IP বদলালে:** শুধু DNS-এ `vpn` রেকর্ড বদলাবেন; রাউটার নাম ধরে সংযোগ করে, তাই রাউটারে কিছু করতে হবে না। তবে নতুন সার্ভারে ধাপ ১৩-এর backup ফেরত আনতে হবে, নইলে সব রাউটারে নতুন স্ক্রিপ্ট বসাতে হবে।

**পরে আলাদা VPN সার্ভার:** অনেক বড় হলে (কয়েক হাজার রাউটার) VPN আলাদা মেশিনে নেওয়া সম্ভব। তখন web সার্ভার আর VPN সার্ভারের মাঝে একটা স্থায়ী লিংক দিয়ে `10.99.0.0/16` রুট করতে হবে। এখনকার কোডে এটা তৈরি নেই, আর শুরুতে দরকারও নেই।

## ১২. Cron (aaPanel → Cron → Shell Script)

প্রতিদিন একবার, যেমন সকাল ৯:৩০:

```
cd /www/wwwroot/mikhmonlive
sudo -u www /www/server/php/82/bin/php tools/portal.php reminders
sudo -u www /www/server/php/82/bin/php tools/portal.php vpn-sync
sudo -u www /www/server/php/82/bin/php tools/portal.php purge-unverified 7
```

- `reminders`: মেয়াদ শেষের ইমেইল পাঠায়।
- `vpn-sync`: মেয়াদ শেষ হওয়া গ্রাহকের tunnel বন্ধ করে।
- `purge-unverified 7`: ৭ দিনেও ইমেইল নিশ্চিত না করা খালি অ্যাকাউন্ট মুছে subdomain খালি করে।

## ১৩. Backup (খুব জরুরি)

aaPanel → Cron → **Backup Directory** → `/www/mikhmon-data`, প্রতিদিন, ৭টা কপি রাখুন। সম্ভব হলে সার্ভারের বাইরেও (Google Drive/S3) রাখুন।

এই ফোল্ডারে সব গ্রাহক, অর্ডারের database (`portal.sqlite`) আর এনক্রিপশন key (`tenants/.mkl-key.php`) আছে। **Key হারালে সব গ্রাহকের রাউটার পাসওয়ার্ড আর পড়া যাবে না।**

এর সাথে VPN-এর এই দুটো ফাইলও রাখুন (root ছাড়া পড়া যায় না):

```
/etc/wireguard/wgml.key
/etc/mikhmonlive-l2tp.psk
```

এগুলো হারালে নতুন সার্ভারে সব রাউটারে নতুন স্ক্রিপ্ট বসাতে হবে।

## ১৪. Live-এর আগে শেষ যাচাই

- **রাউটার:** v6, v7.0–7.9 আর v7.10+ রাউটারে `README.md`-এর ৬ ধাপের টেস্ট চালান (voucher-এর মেয়াদ, report)।
- **Google:** [Search Console](https://search.google.com/search-console)-এ "HTML tag" পদ্ধতিতে সাইট যাচাই করুন (কোডটা `site.php`-এর `google_site_verification`-এ), তারপর `https://mikhmonlive.com/sitemap.xml` জমা দিন।
- **দাম:** প্যাকেজের দাম আর bKash/Nagad নম্বর আরেকবার দেখে নিন।

## পরে আপডেট করতে

```bash
cd /www/wwwroot/mikhmonlive && git pull && chown -R www:www /www/wwwroot/mikhmonlive
```

`include/site.php` Git-এ নেই, তাই আপডেটে আপনার সেটিংস বদলাবে না।

## সমস্যা হলে

| লক্ষণ | সম্ভাব্য কারণ |
|---|---|
| সাদা পেজ বা 500 error | `.user.ini`-এর `open_basedir`-এ `/www/mikhmon-data/` বা পুরো কোড ফোল্ডার নেই; PHP restart দেওয়া হয়নি |
| `abc.mikhmonlive.com` খোলে না | wildcard DNS (`*`) বা wildcard সাইট/SSL নেই |
| সব রাউটারে "Not connected" | `stream_socket_client` disabled; রাউটারের API (8728) বন্ধ; tunnel ওঠেনি |
| VPN tunnel ওঠে না | UDP 51820 / 500 / 4500 বন্ধ; `vpn` DNS রেকর্ড Cloudflare-এর কমলা মেঘে |
| ইমেইল যায় না | `/www/mikhmon-data/mail-errors.log` দেখুন; SMTP পোর্ট বা পাসওয়ার্ড ভুল |
| ইমেইল spam-এ যায় | SPF / DKIM record নেই |
| `/sitemap.xml` 404 | portal সাইটের config-এ ধাপ ৬-এর rewrite লাইন নেই |
