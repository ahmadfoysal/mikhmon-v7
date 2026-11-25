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

	// Secret key used to encrypt router passwords. Default: <tenant_root>/.mkl-key.php
	// 'key_file' => '/www/mikhmon-data/mkl-key.php',
);
