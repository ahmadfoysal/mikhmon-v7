<?php
/*
 * Copy this file to include/site.php to run MikhmonLive for many customers
 * (one subdomain and one data folder each). Without include/site.php the
 * application runs for a single owner and keeps its data in ./data/.
 */
return array(
	// Folder that holds one sub folder per customer. Keep it OUTSIDE the web root.
	'tenant_root' => '/www/mikhmon-data/tenants',

	// Customers are reached at https://<name>.<base_domain>
	'base_domain' => 'mikhmonlive.com',

	// Where customers register and renew (shown when an account is expired).
	'portal_url' => 'https://mikhmonlive.com',

	// Routers on the public internet are always allowed. Private addresses are
	// refused unless listed here, e.g. the VPN network your routers dial in to.
	// 'private_nets' => array('10.99.0.0/16'),

	// ---------------- customer portal (website root: portal/public) ----------------
	'brand' => 'MikhmonLive',
	'currency' => 'BDT',
	'price_per_router' => 1000,     // one router, one year
	'trial_days' => 7,              // 0 = no free trial: the account opens after the first payment
	'trial_routers' => 1,
	// With e-mail switched on, a new account stays closed until the customer confirms
	// the address; the trial starts on that day. false = open at once.
	'require_verified' => true,
	'support_contact' => 'support@mikhmonlive.com',

	// Manual payment: shown on the billing page; the customer sends the transaction ID
	// and you approve the order in the portal (Admin). '' switches manual payment off.
	'manual_payment' => "bKash / Nagad (Send Money): 01XXXXXXXXX",

	// PayStation online payment. Leave the merchant empty to switch it off.
	// Set the IPN URL in the PayStation panel to: https://mikhmonlive.com/?p=pay-ipn
	'paystation_merchant' => '',
	'paystation_password' => '',
	'paystation_sandbox' => false,

	// ---------------- e-mail ----------------
	// SMTP account used for verification, password reset, receipts and expiry
	// reminders. Leave smtp_host empty to run without e-mail.
	'smtp_host' => '',                 // e.g. mail.mikhmonlive.com
	'smtp_port' => 465,
	'smtp_secure' => 'ssl',            // ssl (465) | tls (587, STARTTLS)
	'smtp_user' => '',                 // e.g. noreply@mikhmonlive.com
	'smtp_pass' => '',
	'mail_from' => '',                 // e.g. noreply@mikhmonlive.com
	'admin_email' => '',               // you: gets a notice for every manual payment

	// ---------------- VPN onboarding (after tools/vpn/install.sh) ----------------
	// Public DNS name of this server for the router tunnels. '' = VPN page off.
	'vpn_host' => '',

	// Secret key used to encrypt router passwords. Default: <tenant_root>/.mkl-key.php
	// 'key_file' => '/www/mikhmon-data/mkl-key.php',
);
