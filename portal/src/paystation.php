<?php
/*
 *  MikhmonLive portal - PayStation (Bangladesh) online payment.
 *
 *  Endpoints used (see https://paystation.com.bd/documentation):
 *    POST /initiate-payment     form encoded, merchantId + password in the body
 *    POST /transaction-status   form encoded, merchantId in the header
 *
 *  The browser coming back from PayStation proves nothing. An order is only
 *  marked paid after the server itself asked PayStation for the transaction
 *  and the status, invoice and amount all match.
 */

if (!function_exists('mkp_ps_enabled')) {

	function mkp_ps_enabled()
	{
		return mkl_site('paystation_merchant') != '' && mkl_site('paystation_password') != '' && function_exists('curl_init');
	}

	function mkp_ps_base()
	{
		if (mkl_site('paystation_url') != '') {
			return rtrim(mkl_site('paystation_url'), '/');
		}
		return mkl_site('paystation_sandbox') ? 'https://sandbox.paystation.com.bd' : 'https://api.paystation.com.bd';
	}

	/** POST and decode JSON. Returns array (possibly empty) plus '_error'. */
	function mkp_ps_call($path, $fields, $headers = array())
	{
		$ch = curl_init(mkp_ps_base() . $path);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($fields),
			CURLOPT_HTTPHEADER => array_merge(array('Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'), $headers),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT => 45,
		));
		$raw = curl_exec($ch);
		$err = curl_error($ch);
		curl_close($ch);
		$j = is_string($raw) ? json_decode($raw, true) : null;
		if (!is_array($j)) {
			$j = array();
		}
		$j['_error'] = $err != '' ? $err : (is_string($raw) && $raw != '' && count($j) == 0 ? 'Unexpected answer from the payment gateway.' : '');
		return $j;
	}

	/**
	 * Start a hosted checkout for an order.
	 * @return array array(payment url, '') or array('', error text)
	 */
	function mkp_ps_start($order, $user)
	{
		$r = mkp_ps_call('/initiate-payment', array(
			'merchantId' => mkl_site('paystation_merchant'),
			'password' => mkl_site('paystation_password'),
			'invoice_number' => $order['invoice'],
			'currency' => mkl_site('currency'),
			'payment_amount' => (int)$order['amount'],
			'pay_with_charge' => 0,
			'reference' => $user['tenant'],
			'cust_name' => $user['name'],
			'cust_phone' => $user['phone'] != '' ? $user['phone'] : '01700000000',
			'cust_email' => $user['email'],
			'cust_address' => 'Bangladesh',
			'callback_url' => mkp_abs('pay-return', array('invoice' => $order['invoice'])),
			'checkout_items' => mkl_site('brand') . ' ' . ($order['package'] != '' ? ucfirst($order['package']) . ' ' : '') . (int)$order['routers'] . ' router x ' . (int)$order['years'] . ' year',
		));
		$ok = isset($r['status_code'], $r['status']) && (string)$r['status_code'] == '200' && strtolower((string)$r['status']) == 'success';
		$url = isset($r['payment_url']) ? (string)$r['payment_url'] : '';
		if (!$ok || !preg_match('~^https://~i', $url)) {
			$msg = $r['_error'] != '' ? $r['_error'] : (isset($r['message']) ? (string)$r['message'] : 'Payment could not be started.');
			return array('', $msg);
		}
		return array($url, '');
	}

	/**
	 * Ask PayStation about an order and apply it when it was really paid.
	 * @return string 'paid' | 'pending' | 'failed' | 'error'
	 */
	function mkp_ps_verify($order)
	{
		if ($order['status'] == 'paid') {
			return 'paid';
		}
		if ($order['method'] != 'paystation' || $order['status'] != 'pending') {
			return 'failed';
		}
		$r = mkp_ps_call('/transaction-status', array('invoice_number' => $order['invoice']),
			array('merchantId: ' . mkl_site('paystation_merchant')));
		if ($r['_error'] != '') {
			return 'error';
		}
		$d = isset($r['data']) && is_array($r['data']) ? $r['data'] : array();
		$status = strtolower(isset($d['trx_status']) ? (string)$d['trx_status'] : '');
		if ((string)(isset($r['status_code']) ? $r['status_code'] : '') != '200' || $status == '') {
			return 'pending'; // not found yet: the customer may still be paying
		}
		if (in_array($status, array('success', 'successful'), true)) {
			$sameInvoice = isset($d['invoice_number']) && (string)$d['invoice_number'] === (string)$order['invoice'];
			$amount = isset($d['payment_amount']) ? (float)$d['payment_amount'] : 0;
			if (!$sameInvoice || $amount + 0.001 < (float)$order['amount']) {
				return 'failed';
			}
			mkp_order_paid($order['id'], isset($d['trx_id']) ? (string)$d['trx_id'] : '');
			return 'paid';
		}
		if (in_array($status, array('failed', 'cancelled', 'canceled', 'refund', 'refunded'), true)) {
			return 'failed';
		}
		return 'pending';
	}
}
