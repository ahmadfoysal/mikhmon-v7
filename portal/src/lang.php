<?php
/*
 *  MikhmonLive portal - interface texts (English and Bangla).
 *  t('key') returns the text in the visitor's language; %s marks a value.
 */

if (!function_exists('t')) {

	function mkp_lang()
	{
		return (isset($_SESSION['lang']) && $_SESSION['lang'] == 'bn') ? 'bn' : 'en';
	}

	function t($key, $args = array())
	{
		static $txt = null;
		if ($txt === null) {
			$txt = mkp_texts();
		}
		$lang = mkp_lang();
		$s = isset($txt[$key][$lang]) ? $txt[$key][$lang] : (isset($txt[$key]['en']) ? $txt[$key]['en'] : $key);
		return $args ? vsprintf($s, $args) : $s;
	}

	function mkp_texts()
	{
		return array(
			// navigation
			'nav_home' => array('en' => 'Home', 'bn' => 'হোম'),
			'nav_login' => array('en' => 'Log in', 'bn' => 'লগইন'),
			'nav_register' => array('en' => 'Create account', 'bn' => 'অ্যাকাউন্ট খুলুন'),
			'nav_dashboard' => array('en' => 'Dashboard', 'bn' => 'ড্যাশবোর্ড'),
			'nav_vpn' => array('en' => 'Connect router', 'bn' => 'রাউটার সংযোগ'),
			'nav_billing' => array('en' => 'Billing', 'bn' => 'বিলিং'),
			'nav_account' => array('en' => 'Account', 'bn' => 'অ্যাকাউন্ট'),
			'nav_admin' => array('en' => 'Admin', 'bn' => 'অ্যাডমিন'),
			'nav_logout' => array('en' => 'Log out', 'bn' => 'লগআউট'),

			// landing
			'hero_title' => array('en' => 'Mikhmon in the cloud, for every RouterOS version', 'bn' => 'ক্লাউডে Mikhmon, সব RouterOS ভার্সনের জন্য'),
			'hero_text' => array('en' => 'Manage hotspot vouchers, users and sales reports from anywhere. Works with RouterOS v6 and v7, including v7.10 and newer.',
				'bn' => 'যেকোনো জায়গা থেকে hotspot voucher, user আর বিক্রির রিপোর্ট সামলান। RouterOS v6 ও v7 (v7.10 ও তার পরের ভার্সনসহ) সবগুলোতে চলে।'),
			'feat_1' => array('en' => 'Your own address', 'bn' => 'আপনার নিজের ঠিকানা'),
			'feat_1_text' => array('en' => 'You get a private Mikhmon at yourname.%s.', 'bn' => 'yourname.%s ঠিকানায় আপনার আলাদা Mikhmon।'),
			'feat_2' => array('en' => 'No public IP needed', 'bn' => 'Public IP লাগে না'),
			'feat_2_text' => array('en' => 'Paste one script into the router. It connects to us through a secure tunnel.', 'bn' => 'রাউটারে একটা স্ক্রিপ্ট paste করুন। নিরাপদ tunnel দিয়ে সেটা আমাদের সাথে যুক্ত হবে।'),
			'feat_3' => array('en' => 'Your data stays on your router', 'bn' => 'ডেটা আপনার রাউটারেই থাকে'),
			'feat_3_text' => array('en' => 'Vouchers and sales records are stored on the router, exactly like classic Mikhmon.', 'bn' => 'Voucher আর বিক্রির রেকর্ড রাউটারেই থাকে, ঠিক পুরনো Mikhmon-এর মতো।'),
			'price_line' => array('en' => '%s per router, per year', 'bn' => 'প্রতি রাউটার, প্রতি বছর %s'),
			'trial_line' => array('en' => '%s days free trial, no payment needed to start.', 'bn' => '%s দিন ফ্রি ট্রায়াল, শুরু করতে পেমেন্ট লাগে না।'),

			// forms
			'name' => array('en' => 'Your name', 'bn' => 'আপনার নাম'),
			'email' => array('en' => 'Email', 'bn' => 'ইমেইল'),
			'phone' => array('en' => 'Mobile number', 'bn' => 'মোবাইল নম্বর'),
			'password' => array('en' => 'Password', 'bn' => 'পাসওয়ার্ড'),
			'password_hint' => array('en' => 'At least 8 characters.', 'bn' => 'কমপক্ষে ৮ অক্ষর।'),
			'subdomain' => array('en' => 'Choose your address', 'bn' => 'আপনার ঠিকানা বেছে নিন'),
			'subdomain_hint' => array('en' => '3-32 characters: a-z, 0-9 and dash. It cannot be changed later.', 'bn' => '৩-৩২ অক্ষর: a-z, 0-9 আর ড্যাশ। পরে বদলানো যাবে না।'),
			'register_btn' => array('en' => 'Create my account', 'bn' => 'অ্যাকাউন্ট তৈরি করুন'),
			'login_btn' => array('en' => 'Log in', 'bn' => 'লগইন'),
			'have_account' => array('en' => 'Already have an account?', 'bn' => 'আগে থেকেই অ্যাকাউন্ট আছে?'),
			'no_account' => array('en' => 'No account yet?', 'bn' => 'এখনো অ্যাকাউন্ট নেই?'),
			'save' => array('en' => 'Save', 'bn' => 'সেভ করুন'),

			// errors
			'err_name_email' => array('en' => 'Please enter your name and a valid email address.', 'bn' => 'আপনার নাম আর সঠিক ইমেইল দিন।'),
			'err_password' => array('en' => 'The password needs at least 8 characters.', 'bn' => 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।'),
			'err_subdomain' => array('en' => 'This address is not valid. Use 3-32 characters: a-z, 0-9 and dash.', 'bn' => 'ঠিকানাটি সঠিক নয়। ৩-৩২ অক্ষর ব্যবহার করুন: a-z, 0-9 আর ড্যাশ।'),
			'err_email_taken' => array('en' => 'An account with this email already exists.', 'bn' => 'এই ইমেইলে আগে থেকেই একটি অ্যাকাউন্ট আছে।'),
			'err_subdomain_taken' => array('en' => 'This address is already taken. Please choose another one.', 'bn' => 'এই ঠিকানা আগেই নেওয়া হয়েছে। অন্য একটি বেছে নিন।'),
			'err_login' => array('en' => 'Wrong email or password.', 'bn' => 'ইমেইল বা পাসওয়ার্ড ভুল।'),
			'err_wait' => array('en' => 'Too many attempts. Please wait a few minutes and try again.', 'bn' => 'অনেকবার চেষ্টা হয়েছে। কয়েক মিনিট পর আবার চেষ্টা করুন।'),
			'err_order' => array('en' => 'This order is not valid.', 'bn' => 'অর্ডারটি সঠিক নয়।'),
			'err_order_peers' => array('en' => 'You have %s connected routers. Remove a connection first or order at least that many.', 'bn' => 'আপনার %sটি রাউটার সংযুক্ত আছে। আগে একটি সংযোগ মুছুন, অথবা অন্তত ততগুলো রাউটার অর্ডার করুন।'),
			'err_trx' => array('en' => 'Please enter the transaction ID of your payment.', 'bn' => 'আপনার পেমেন্টের Transaction ID দিন।'),
			'err_vpn_name' => array('en' => 'Please enter a short name for the router (letters, digits, dash).', 'bn' => 'রাউটারের একটি ছোট নাম দিন (অক্ষর, সংখ্যা, ড্যাশ)।'),
			'err_vpn_type' => array('en' => 'This connection type is not available.', 'bn' => 'এই ধরনের সংযোগ এখন পাওয়া যাচ্ছে না।'),
			'err_vpn_limit' => array('en' => 'Your plan has no free router left. Order more routers under Billing.', 'bn' => 'আপনার প্ল্যানে আর রাউটার যোগ করা যাবে না। বিলিং থেকে আরও রাউটার অর্ডার করুন।'),
			'err_vpn_exists' => array('en' => 'You already have a router with this name.', 'bn' => 'এই নামে আপনার একটি রাউটার আগে থেকেই আছে।'),
			'err_vpn_full' => array('en' => 'No free address left. Please contact support.', 'bn' => 'আর কোনো ঠিকানা খালি নেই। সাপোর্টে যোগাযোগ করুন।'),
			'err_pay_start' => array('en' => 'Online payment could not be started: %s', 'bn' => 'অনলাইন পেমেন্ট শুরু করা যায়নি: %s'),

			// dashboard
			'welcome' => array('en' => 'Welcome, %s', 'bn' => 'স্বাগতম, %s'),
			'your_mikhmon' => array('en' => 'Your Mikhmon', 'bn' => 'আপনার Mikhmon'),
			'open_mikhmon' => array('en' => 'Open Mikhmon', 'bn' => 'Mikhmon খুলুন'),
			'mikhmon_login' => array('en' => 'Direct login there: user %s and your portal password.', 'bn' => 'সরাসরি লগইন: user %s আর আপনার পোর্টালের পাসওয়ার্ড।'),
			'subscription' => array('en' => 'Subscription', 'bn' => 'সাবস্ক্রিপশন'),
			'state_trial' => array('en' => 'Free trial', 'bn' => 'ফ্রি ট্রায়াল'),
			'state_active' => array('en' => 'Active', 'bn' => 'সক্রিয়'),
			'state_expired' => array('en' => 'Expired', 'bn' => 'মেয়াদ শেষ'),
			'state_unverified' => array('en' => 'Waiting for email confirmation', 'bn' => 'ইমেইল নিশ্চিতকরণের অপেক্ষায়'),
			'state_suspended' => array('en' => 'Suspended', 'bn' => 'স্থগিত'),
			'valid_until' => array('en' => 'Valid until %s (%s days left)', 'bn' => '%s পর্যন্ত (%s দিন বাকি)'),
			'ended_on' => array('en' => 'Ended on %s', 'bn' => '%s তারিখে শেষ হয়েছে'),
			'routers_used' => array('en' => 'Routers: %s of %s used', 'bn' => 'রাউটার: %2$sটির মধ্যে %1$sটি ব্যবহৃত'),
			'renew' => array('en' => 'Renew or add routers', 'bn' => 'নবায়ন করুন বা রাউটার বাড়ান'),
			'next_steps' => array('en' => 'Getting started', 'bn' => 'শুরু করুন'),
			'step_1' => array('en' => 'Connect your router under "Connect router" (one script to paste).', 'bn' => '"রাউটার সংযোগ" থেকে রাউটার যুক্ত করুন (একটি স্ক্রিপ্ট paste করতে হবে)।'),
			'step_2' => array('en' => 'Open Mikhmon and choose the router.', 'bn' => 'Mikhmon খুলে রাউটারটি বেছে নিন।'),
			'step_3' => array('en' => 'In User Profile, press "Repair scripts" if the router was used with an older Mikhmon.', 'bn' => 'রাউটারটি আগে পুরনো Mikhmon-এ ব্যবহার হয়ে থাকলে User Profile-এ "Repair scripts" চাপুন।'),

			// billing
			'order_title' => array('en' => 'Order', 'bn' => 'অর্ডার'),
			'routers' => array('en' => 'Number of routers', 'bn' => 'রাউটারের সংখ্যা'),
			'years' => array('en' => 'Years', 'bn' => 'বছর'),
			'total' => array('en' => 'Total', 'bn' => 'মোট'),
			'order_note' => array('en' => 'A paid order sets your plan to this number of routers and adds the years to your subscription.', 'bn' => 'পেমেন্ট হলে আপনার প্ল্যান এই সংখ্যক রাউটারে সেট হবে এবং সাবস্ক্রিপশনে এই বছরগুলো যোগ হবে।'),
			'pay_online' => array('en' => 'Pay online', 'bn' => 'অনলাইনে পেমেন্ট করুন'),
			'pay_manual' => array('en' => 'Manual payment', 'bn' => 'ম্যানুয়াল পেমেন্ট'),
			'pay_method' => array('en' => 'Payment method', 'bn' => 'পেমেন্টের মাধ্যম'),
			'manual_sender' => array('en' => 'Number you paid from', 'bn' => 'যে নম্বর থেকে পাঠিয়েছেন'),
			'manual_trx' => array('en' => 'Transaction ID', 'bn' => 'Transaction ID'),
			'manual_submit' => array('en' => 'Submit payment for approval', 'bn' => 'অনুমোদনের জন্য জমা দিন'),
			'manual_wait' => array('en' => 'Thank you. Your payment will be checked and your subscription activated shortly.', 'bn' => 'ধন্যবাদ। আপনার পেমেন্ট যাচাই করে শীঘ্রই সাবস্ক্রিপশন চালু করা হবে।'),
			'orders' => array('en' => 'Your orders', 'bn' => 'আপনার অর্ডার'),
			'no_orders' => array('en' => 'No orders yet.', 'bn' => 'এখনো কোনো অর্ডার নেই।'),
			'invoice' => array('en' => 'Invoice', 'bn' => 'ইনভয়েস'),
			'date' => array('en' => 'Date', 'bn' => 'তারিখ'),
			'amount' => array('en' => 'Amount', 'bn' => 'পরিমাণ'),
			'status' => array('en' => 'Status', 'bn' => 'অবস্থা'),
			'st_pending' => array('en' => 'Waiting', 'bn' => 'অপেক্ষমাণ'),
			'st_paid' => array('en' => 'Paid', 'bn' => 'পরিশোধিত'),
			'st_rejected' => array('en' => 'Rejected', 'bn' => 'বাতিল'),
			'st_cancelled' => array('en' => 'Cancelled', 'bn' => 'বাতিল করা'),
			'check_payment' => array('en' => 'Check payment', 'bn' => 'পেমেন্ট যাচাই'),
			'cancel' => array('en' => 'Cancel', 'bn' => 'বাতিল'),
			'pay_ok' => array('en' => 'Payment received. Your subscription is active.', 'bn' => 'পেমেন্ট পাওয়া গেছে। আপনার সাবস্ক্রিপশন সক্রিয়।'),
			'pay_pending' => array('en' => 'The payment is not confirmed yet. If you paid, press "Check payment" in a minute.', 'bn' => 'পেমেন্ট এখনো নিশ্চিত হয়নি। পেমেন্ট করে থাকলে এক মিনিট পর "পেমেন্ট যাচাই" চাপুন।'),
			'pay_failed' => array('en' => 'The payment was not completed.', 'bn' => 'পেমেন্ট সম্পন্ন হয়নি।'),
			'pay_error' => array('en' => 'The payment gateway could not be reached. Please try "Check payment" again.', 'bn' => 'পেমেন্ট গেটওয়ের সাথে যোগাযোগ করা যায়নি। আবার "পেমেন্ট যাচাই" চাপুন।'),

			// vpn
			'vpn_title' => array('en' => 'Connect a router', 'bn' => 'রাউটার যুক্ত করুন'),
			'vpn_intro' => array('en' => 'Each router connects to MikhmonLive through a private tunnel, so it needs no public IP. Create a connection, paste the script into the router terminal, and the router appears in your Mikhmon.',
				'bn' => 'প্রতিটি রাউটার একটি private tunnel দিয়ে MikhmonLive-এ যুক্ত হয়, তাই public IP লাগে না। একটি সংযোগ তৈরি করুন, স্ক্রিপ্টটি রাউটারের terminal-এ paste করুন, রাউটারটি আপনার Mikhmon-এ চলে আসবে।'),
			'vpn_off' => array('en' => 'Router tunnels are not available yet. You can still add a router with a public IP inside Mikhmon.', 'bn' => 'রাউটার tunnel এখনো চালু হয়নি। Public IP থাকলে Mikhmon-এর ভেতর থেকে রাউটার যোগ করতে পারেন।'),
			'vpn_name' => array('en' => 'Router name', 'bn' => 'রাউটারের নাম'),
			'vpn_type' => array('en' => 'RouterOS version', 'bn' => 'RouterOS ভার্সন'),
			'vpn_wg' => array('en' => 'RouterOS v7 (WireGuard)', 'bn' => 'RouterOS v7 (WireGuard)'),
			'vpn_l2tp' => array('en' => 'RouterOS v6 (L2TP/IPsec)', 'bn' => 'RouterOS v6 (L2TP/IPsec)'),
			'vpn_add' => array('en' => 'Create connection', 'bn' => 'সংযোগ তৈরি করুন'),
			'vpn_created' => array('en' => 'Connection created. Paste the script below into your router.', 'bn' => 'সংযোগ তৈরি হয়েছে। নিচের স্ক্রিপ্টটি আপনার রাউটারে paste করুন।'),
			'vpn_list' => array('en' => 'Your connections', 'bn' => 'আপনার সংযোগ'),
			'vpn_none' => array('en' => 'No router connected yet.', 'bn' => 'এখনো কোনো রাউটার যুক্ত হয়নি।'),
			'vpn_address' => array('en' => 'Tunnel address', 'bn' => 'Tunnel ঠিকানা'),
			'vpn_show' => array('en' => 'Show script', 'bn' => 'স্ক্রিপ্ট দেখুন'),
			'vpn_delete' => array('en' => 'Remove', 'bn' => 'মুছুন'),
			'vpn_delete_confirm' => array('en' => 'Remove this connection? The router will lose its tunnel.', 'bn' => 'এই সংযোগ মুছবেন? রাউটারের tunnel বন্ধ হয়ে যাবে।'),
			'vpn_deleted' => array('en' => 'Connection removed.', 'bn' => 'সংযোগ মুছে ফেলা হয়েছে।'),
			'vpn_script' => array('en' => 'Setup script for %s', 'bn' => '%s-এর সেটআপ স্ক্রিপ্ট'),
			'vpn_script_how' => array('en' => 'In WinBox open New Terminal, paste the whole script and press Enter. The tunnel comes up within a minute. Keep this script secret: it contains the keys of this router.',
				'bn' => 'WinBox-এ New Terminal খুলে পুরো স্ক্রিপ্টটি paste করে Enter চাপুন। এক মিনিটের মধ্যে tunnel চালু হবে। স্ক্রিপ্টটি গোপন রাখুন: এতে এই রাউটারের key আছে।'),
			'vpn_unscript' => array('en' => 'To undo it on the router', 'bn' => 'রাউটার থেকে সরিয়ে ফেলতে'),
			'copy' => array('en' => 'Copy', 'bn' => 'কপি'),
			'copied' => array('en' => 'Copied', 'bn' => 'কপি হয়েছে'),

			// account
			'account_title' => array('en' => 'Account', 'bn' => 'অ্যাকাউন্ট'),
			'new_password' => array('en' => 'New password', 'bn' => 'নতুন পাসওয়ার্ড'),
			'current_password' => array('en' => 'Current password', 'bn' => 'বর্তমান পাসওয়ার্ড'),
			'password_changed' => array('en' => 'Password changed. It is also your Mikhmon password now.', 'bn' => 'পাসওয়ার্ড বদলানো হয়েছে। এটি এখন আপনার Mikhmon-এর পাসওয়ার্ডও।'),
			'err_current_password' => array('en' => 'The current password is not correct.', 'bn' => 'বর্তমান পাসওয়ার্ড সঠিক নয়।'),
			'saved' => array('en' => 'Saved.', 'bn' => 'সেভ হয়েছে।'),
			'forgot_link' => array('en' => 'Forgot your password?', 'bn' => 'পাসওয়ার্ড ভুলে গেছেন?'),
			'forgot_title' => array('en' => 'Reset your password', 'bn' => 'পাসওয়ার্ড রিসেট'),
			'forgot_text' => array('en' => 'Enter the email address of your account. We send you a link to choose a new password.', 'bn' => 'আপনার অ্যাকাউন্টের ইমেইল ঠিকানা দিন। নতুন পাসওয়ার্ড বেছে নেওয়ার একটি লিংক পাঠানো হবে।'),
			'forgot_btn' => array('en' => 'Send the link', 'bn' => 'লিংক পাঠান'),
			'forgot_sent' => array('en' => 'If an account with this email exists, a link has been sent. Please check your inbox and spam folder.', 'bn' => 'এই ইমেইলে কোনো অ্যাকাউন্ট থাকলে একটি লিংক পাঠানো হয়েছে। আপনার inbox ও spam ফোল্ডার দেখুন।'),
			'reset_title' => array('en' => 'Choose a new password', 'bn' => 'নতুন পাসওয়ার্ড বেছে নিন'),
			'reset_ok' => array('en' => 'Your password was changed. You can log in now.', 'bn' => 'আপনার পাসওয়ার্ড বদলানো হয়েছে। এখন লগইন করতে পারেন।'),
			'link_bad' => array('en' => 'This link is not valid any more. Please request a new one.', 'bn' => 'এই লিংকটি আর কার্যকর নেই। নতুন একটি লিংক চেয়ে নিন।'),
			'verify_ask' => array('en' => 'Please confirm your email address: we sent a link to %s.', 'bn' => 'আপনার ইমেইল ঠিকানা নিশ্চিত করুন: %s ঠিকানায় একটি লিংক পাঠানো হয়েছে।'),
			'verify_ask_closed' => array('en' => 'Your Mikhmon opens as soon as you confirm your email address: we sent a link to %s. Please also check the spam folder.',
				'bn' => 'ইমেইল ঠিকানা নিশ্চিত করলেই আপনার Mikhmon চালু হবে: %s ঠিকানায় একটি লিংক পাঠানো হয়েছে। Spam ফোল্ডারও দেখুন।'),
			'verify_ok_trial' => array('en' => 'Thank you, your email address is confirmed. Your %s day free trial starts now.', 'bn' => 'ধন্যবাদ, আপনার ইমেইল ঠিকানা নিশ্চিত হয়েছে। আপনার %s দিনের ফ্রি ট্রায়াল এখন শুরু হলো।'),
			'verify_first' => array('en' => 'Please confirm your email address first.', 'bn' => 'আগে আপনার ইমেইল ঠিকানা নিশ্চিত করুন।'),
			'verify_resend' => array('en' => 'Send again', 'bn' => 'আবার পাঠান'),
			'verify_sent' => array('en' => 'A new link was sent to %s.', 'bn' => '%s ঠিকানায় নতুন লিংক পাঠানো হয়েছে।'),
			'verify_ok' => array('en' => 'Thank you, your email address is confirmed.', 'bn' => 'ধন্যবাদ, আপনার ইমেইল ঠিকানা নিশ্চিত হয়েছে।'),
			'mail_failed' => array('en' => 'The email could not be sent. Please try again later.', 'bn' => 'ইমেইল পাঠানো যায়নি। কিছুক্ষণ পর আবার চেষ্টা করুন।'),
			'forgot' => array('en' => 'Forgot your password? Contact support: %s', 'bn' => 'পাসওয়ার্ড ভুলে গেছেন? সাপোর্টে যোগাযোগ করুন: %s'),
		);
	}
}
