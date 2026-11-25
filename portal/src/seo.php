<?php
/*
 *  MikhmonLive portal - search engine optimisation.
 *
 *  - Two indexable addresses for the landing page:
 *        https://mikhmonlive.com/          English
 *        https://mikhmonlive.com/?hl=bn    Bangla
 *    linked to each other with hreflang, each with its own canonical URL.
 *  - Title, description, Open Graph / Twitter cards, favicon.
 *  - Structured data (JSON-LD): Organization, WebSite, the service with
 *    its three packages as offers, and the FAQ.
 *  - /robots.txt and /sitemap.xml (served by index.php, see README).
 *  - Account pages of the portal and every customer subdomain are noindex.
 */

if (!function_exists('mkp_site_url')) {

	/** https://mikhmonlive.com (no trailing slash). */
	function mkp_site_url()
	{
		$u = rtrim((string)mkl_site('portal_url'), '/');
		return $u != '' ? $u : 'https://' . mkl_site('base_domain');
	}

	/** Address of the landing page in a language. */
	function mkp_landing_url($lang)
	{
		return mkp_site_url() . '/' . ($lang == 'bn' ? '?hl=bn' : '');
	}

	function mkp_seo_title()
	{
		return t('seo_title', array(mkl_site('brand')));
	}

	/** All head tags of the landing page. */
	function mkp_seo_head()
	{
		$lang = mkp_lang();
		$url = mkp_landing_url($lang);
		$title = mkp_seo_title();
		$desc = t('seo_desc', array((int)mkl_site('trial_days')));
		$img = mkp_site_url() . '/assets/og-image.jpg';
		$h = '<title>' . e($title) . '</title>'
			. '<meta name="description" content="' . e($desc) . '">'
			. '<meta name="robots" content="index,follow,max-image-preview:large">'
			. '<link rel="canonical" href="' . e($url) . '">'
			. '<link rel="alternate" hreflang="en" href="' . e(mkp_landing_url('en')) . '">'
			. '<link rel="alternate" hreflang="bn" href="' . e(mkp_landing_url('bn')) . '">'
			. '<link rel="alternate" hreflang="x-default" href="' . e(mkp_landing_url('en')) . '">'
			. '<meta name="theme-color" content="#07111f">'
			. '<link rel="icon" type="image/png" href="assets/favicon.png"><link rel="apple-touch-icon" href="assets/favicon.png">'
			// social cards (Facebook, WhatsApp, Messenger, LinkedIn, X)
			. '<meta property="og:type" content="website">'
			. '<meta property="og:site_name" content="' . e(mkl_site('brand')) . '">'
			. '<meta property="og:url" content="' . e($url) . '">'
			. '<meta property="og:title" content="' . e($title) . '">'
			. '<meta property="og:description" content="' . e($desc) . '">'
			. '<meta property="og:image" content="' . e($img) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">'
			. '<meta property="og:image:alt" content="' . e(t('m_alt')) . '">'
			. '<meta property="og:locale" content="' . ($lang == 'bn' ? 'bn_BD' : 'en_US') . '">'
			. '<meta property="og:locale:alternate" content="' . ($lang == 'bn' ? 'en_US' : 'bn_BD') . '">'
			. '<meta name="twitter:card" content="summary_large_image">'
			. '<meta name="twitter:title" content="' . e($title) . '"><meta name="twitter:description" content="' . e($desc) . '">'
			. '<meta name="twitter:image" content="' . e($img) . '">';
		// ownership checks of Google Search Console and Bing Webmaster Tools
		if (mkl_site('google_site_verification') != '') {
			$h .= '<meta name="google-site-verification" content="' . e(mkl_site('google_site_verification')) . '">';
		}
		if (mkl_site('bing_site_verification') != '') {
			$h .= '<meta name="msvalidate.01" content="' . e(mkl_site('bing_site_verification')) . '">';
		}
		return $h . mkp_seo_jsonld();
	}

	/** Structured data for rich results. Only facts shown on the page. */
	function mkp_seo_jsonld()
	{
		$site = mkp_site_url();
		$lang = mkp_lang();
		$offers = array();
		foreach (mkp_packages() as $p) {
			$offers[] = array(
				'@type' => 'Offer',
				'name' => mkp_package_name($p),
				'description' => t($p['routers'] == 1 ? 'pkg_routers' : 'pkg_routers_n', array($p['routers'])) . ', ' . t('per_year'),
				'price' => (string)$p['price'],
				'priceCurrency' => mkl_site('currency'),
				'url' => mkp_landing_url($lang) . '#pricing',
				'availability' => 'https://schema.org/InStock',
				'category' => 'subscription',
			);
		}
		$faq = array();
		for ($i = 1; $i <= 8; $i++) {
			$faq[] = array('@type' => 'Question', 'name' => t('q' . $i),
				'acceptedAnswer' => array('@type' => 'Answer', 'text' => t('a' . $i, array((int)mkl_site('trial_days')))));
		}
		$org = array('@type' => 'Organization', '@id' => $site . '/#org', 'name' => mkl_site('brand'), 'url' => $site . '/', 'logo' => $site . '/assets/favicon.png');
		if (mkl_site('support_contact') != '' && strpos(mkl_site('support_contact'), '@') !== false) {
			$org['email'] = mkl_site('support_contact');
		}
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph' => array(
				$org,
				array('@type' => 'WebSite', '@id' => $site . '/#web', 'name' => mkl_site('brand'), 'url' => $site . '/',
					'inLanguage' => array('en', 'bn'), 'publisher' => array('@id' => $site . '/#org')),
				array('@type' => 'SoftwareApplication', '@id' => $site . '/#app', 'name' => mkl_site('brand'),
					'description' => t('seo_desc', array((int)mkl_site('trial_days'))),
					'applicationCategory' => 'BusinessApplication', 'applicationSubCategory' => 'Hotspot management',
					'operatingSystem' => 'Web browser; MikroTik RouterOS v6, v7',
					'url' => mkp_landing_url($lang), 'image' => $site . '/assets/dashboard.jpg', 'screenshot' => $site . '/assets/dashboard.jpg',
					'inLanguage' => $lang, 'publisher' => array('@id' => $site . '/#org'),
					'featureList' => array(t('f1'), t('f2'), t('f3'), t('f4'), t('f5'), t('f6'), t('f7'), t('f8'), t('f9')),
					'offers' => $offers),
				array('@type' => 'FAQPage', '@id' => mkp_landing_url($lang) . '#faq', 'inLanguage' => $lang, 'mainEntity' => $faq),
			),
		);
		// "</" must not appear inside a script element
		return '<script type="application/ld+json">' . str_replace('</', '<\/', json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</script>';
	}

	function mkp_robots_txt()
	{
		header('Content-Type: text/plain; charset=utf-8');
		echo "User-agent: *\n"
			. "Allow: /\n"
			// account pages carry noindex as well; this saves crawl time
			. "Disallow: /?p=\n"
			. "\nSitemap: " . mkp_site_url() . "/sitemap.xml\n";
	}

	function mkp_sitemap_xml()
	{
		$last = date('Y-m-d', max(filemtime(__DIR__ . '/views_landing.php'), filemtime(__DIR__ . '/lang_landing.php')));
		header('Content-Type: application/xml; charset=utf-8');
		$alt = '<xhtml:link rel="alternate" hreflang="en" href="' . e(mkp_landing_url('en')) . '"/>'
			. '<xhtml:link rel="alternate" hreflang="bn" href="' . e(mkp_landing_url('bn')) . '"/>'
			. '<xhtml:link rel="alternate" hreflang="x-default" href="' . e(mkp_landing_url('en')) . '"/>';
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
		foreach (array('en' => '1.0', 'bn' => '0.9') as $l => $prio) {
			echo '<url><loc>' . e(mkp_landing_url($l)) . '</loc><lastmod>' . $last . '</lastmod><changefreq>weekly</changefreq><priority>' . $prio . '</priority>' . $alt . "</url>\n";
		}
		echo "</urlset>\n";
	}
}
