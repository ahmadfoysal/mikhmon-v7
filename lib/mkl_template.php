<?php
/*
 *  MikhmonLive safe voucher templates.
 *  Based on MIKHMON, Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * ---------------------------------------------------------------------------
 *  Classic Mikhmon saved the voucher template as a .php file and included
 *  it, so whoever could edit a template could run any code on the server.
 *
 *  Here a template is only DATA. This file reads the same template syntax
 *  and interprets a small, harmless part of it:
 *
 *      <?= $username; ?>              <?php echo $price; ?>
 *      <?= " [$num]"; ?>              <?= date("Y-m-d"); ?>
 *      <?php if ($usermode == "vc") { ?> ... <?php } elseif (...) { ?> ...
 *      <?php } else { ?> ... <?php } ?>
 *      <?php $color = "#f00"; ?>      (simple variables)
 *
 *  Operators: . == != === !== < > <= >= && || !    Functions: see below.
 *  Anything else is rejected when the template is saved. Nothing is ever
 *  passed to eval/include.
 */

if (!function_exists('mkl_tpl_render')) {

	/** Pure functions a template may call. */
	function mkl_tpl_functions()
	{
		return array('date', 'strtoupper', 'strtolower', 'ucfirst', 'ucwords', 'trim', 'strlen', 'substr',
			'number_format', 'str_replace', 'htmlspecialchars', 'nl2br', 'str_pad', 'round', 'intval', 'floatval',
			'empty', 'isset');
	}

	class MklTplError extends Exception
	{
	}

	/** Turn the template into a flat token list. */
	function mkl_tpl_tokens($src)
	{
		$tokens = array();
		$parts = preg_split('/(<\?(?:php\b|=)?.*?\?>)/s', (string)$src, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach ($parts as $n => $part) {
			if ($n % 2 == 0) {
				if (strpos($part, '<?') !== false) {
					throw new MklTplError('Unclosed <?php tag');
				}
				if ($part !== '') {
					$tokens[] = array('text', $part);
				}
				continue;
			}
			preg_match('/^<\?(php\b|=)?(.*)\?>$/s', $part, $m);
			$code = $m[2];
			if ($m[1] == '=') {
				$tokens[] = array('word', 'echo');
			}
			$len = strlen($code);
			$i = 0;
			while ($i < $len) {
				$c = $code[$i];
				if (ctype_space($c)) {
					$i++;
				} elseif ($c == '/' && substr($code, $i, 2) == '//') {
					$e = strpos($code, "\n", $i);
					$i = $e === false ? $len : $e;
				} elseif ($c == '/' && substr($code, $i, 2) == '/*') {
					$e = strpos($code, '*/', $i);
					$i = $e === false ? $len : $e + 2;
				} elseif ($c == '$') {
					if (!preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)/', $code, $v, 0, $i)) {
						throw new MklTplError('Bad variable near: ' . substr($code, $i, 20));
					}
					$tokens[] = array('var', $v[1]);
					$i += strlen($v[0]);
				} elseif ($c == '"' || $c == "'") {
					$j = $i + 1;
					$s = '';
					while ($j < $len && $code[$j] != $c) {
						if ($code[$j] == '\\' && $j + 1 < $len) {
							$n2 = $code[$j + 1];
							if ($c == '"' && $n2 == 'n') {
								$s .= "\n";
							} elseif ($c == '"' && $n2 == 't') {
								$s .= "\t";
							} elseif ($n2 == $c || $n2 == '\\' || ($c == '"' && $n2 == '$')) {
								$s .= ($n2 == '$') ? "\x00" : $n2; // \x00 marks an escaped dollar
							} else {
								$s .= '\\' . $n2;
							}
							$j += 2;
						} else {
							$s .= $code[$j++];
						}
					}
					if ($j >= $len) {
						throw new MklTplError('Unclosed string');
					}
					$tokens[] = array($c == '"' ? 'dstr' : 'str', $s);
					$i = $j + 1;
				} elseif (ctype_digit($c)) {
					preg_match('/\G\d+(?:\.\d+)?/', $code, $v, 0, $i);
					$tokens[] = array('num', $v[0] + 0);
					$i += strlen($v[0]);
				} elseif (ctype_alpha($c) || $c == '_') {
					preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $code, $v, 0, $i);
					$tokens[] = array('word', strtolower($v[0]));
					$i += strlen($v[0]);
				} else {
					$op = '';
					foreach (array('===', '!==', '==', '!=', '<=', '>=', '&&', '||', '{', '}', '(', ')', ';', ',', '.', '!', '<', '>', '=', ':') as $o) {
						if (substr($code, $i, strlen($o)) === $o) {
							$op = $o;
							break;
						}
					}
					if ($op === '') {
						throw new MklTplError('Not allowed in a template: ' . substr($code, $i, 20));
					}
					$tokens[] = array('op', $op);
					$i += strlen($op);
				}
			}
			/* the closing tag ends a statement like ";" does */
			$tokens[] = array('op', ';');
		}
		return $tokens;
	}

	/** Recursive descent parser producing a small syntax tree. */
	class MklTplParser
	{
		private $t;
		private $p = 0;

		public function __construct($tokens)
		{
			$this->t = $tokens;
		}

		private function peek($type = null, $val = null)
		{
			if (!isset($this->t[$this->p])) {
				return false;
			}
			$k = $this->t[$this->p];
			return ($type === null || $k[0] === $type) && ($val === null || $k[1] === $val);
		}

		private function take($type, $val = null)
		{
			if (!$this->peek($type, $val)) {
				$got = isset($this->t[$this->p]) ? (is_scalar($this->t[$this->p][1]) ? $this->t[$this->p][1] : '') : 'end of template';
				throw new MklTplError('Expected "' . ($val === null ? $type : $val) . '" but found "' . substr((string)$got, 0, 20) . '"');
			}
			return $this->t[$this->p++][1];
		}

		private function skipSemi()
		{
			while ($this->peek('op', ';')) {
				$this->p++;
			}
		}

		public function program()
		{
			$body = $this->statements(false);
			if (isset($this->t[$this->p])) {
				throw new MklTplError('Unexpected "}"');
			}
			return $body;
		}

		private function statements($inBlock)
		{
			$out = array();
			while (true) {
				$this->skipSemi();
				if (!isset($this->t[$this->p])) {
					if ($inBlock) {
						throw new MklTplError('Missing "}"');
					}
					return $out;
				}
				if ($this->peek('op', '}')) {
					return $out;
				}
				$out[] = $this->statement();
			}
		}

		private function block()
		{
			$this->skipSemi();
			$this->take('op', '{');
			$body = $this->statements(true);
			$this->take('op', '}');
			return $body;
		}

		private function statement()
		{
			if ($this->peek('text')) {
				return array('text', $this->take('text'));
			}
			if ($this->peek('word', 'echo') || $this->peek('word', 'print')) {
				$this->p++;
				$list = array($this->expr());
				while ($this->peek('op', ',')) {
					$this->p++;
					$list[] = $this->expr();
				}
				return array('echo', $list);
			}
			if ($this->peek('word', 'if')) {
				$this->p++;
				$branches = array();
				$this->take('op', '(');
				$cond = $this->expr();
				$this->take('op', ')');
				$branches[] = array($cond, $this->block());
				$else = array();
				while (true) {
					$save = $this->p;
					$this->skipSemi();
					if ($this->peek('word', 'elseif')) {
						$this->p++;
					} elseif ($this->peek('word', 'else') && isset($this->t[$this->p + 1]) && $this->t[$this->p + 1] === array('word', 'if')) {
						$this->p += 2;
					} elseif ($this->peek('word', 'else')) {
						$this->p++;
						$else = $this->block();
						break;
					} else {
						$this->p = $save;
						break;
					}
					$this->take('op', '(');
					$cond = $this->expr();
					$this->take('op', ')');
					$branches[] = array($cond, $this->block());
				}
				return array('if', $branches, $else);
			}
			if ($this->peek('var') && isset($this->t[$this->p + 1]) && $this->t[$this->p + 1] === array('op', '=')) {
				$name = $this->take('var');
				$this->p++;
				return array('set', $name, $this->expr());
			}
			$got = $this->t[$this->p][1];
			throw new MklTplError('Not allowed in a template: ' . substr(is_scalar($got) ? (string)$got : '', 0, 20));
		}

		private function expr()
		{
			$l = $this->andExpr();
			while ($this->peek('op', '||') || $this->peek('word', 'or')) {
				$this->p++;
				$l = array('or', $l, $this->andExpr());
			}
			return $l;
		}

		private function andExpr()
		{
			$l = $this->notExpr();
			while ($this->peek('op', '&&') || $this->peek('word', 'and')) {
				$this->p++;
				$l = array('and', $l, $this->notExpr());
			}
			return $l;
		}

		private function notExpr()
		{
			if ($this->peek('op', '!')) {
				$this->p++;
				return array('not', $this->notExpr());
			}
			return $this->cmp();
		}

		private function cmp()
		{
			$l = $this->concat();
			foreach (array('===', '!==', '==', '!=', '<=', '>=', '<', '>') as $op) {
				if ($this->peek('op', $op)) {
					$this->p++;
					return array('cmp', $op, $l, $this->concat());
				}
			}
			return $l;
		}

		private function concat()
		{
			$l = $this->term();
			while ($this->peek('op', '.')) {
				$this->p++;
				$l = array('cat', $l, $this->term());
			}
			return $l;
		}

		private function term()
		{
			if ($this->peek('var')) {
				return array('var', $this->take('var'));
			}
			if ($this->peek('str')) {
				return array('lit', $this->take('str'));
			}
			if ($this->peek('dstr')) {
				return array('dstr', $this->take('dstr'));
			}
			if ($this->peek('num')) {
				return array('lit', $this->take('num'));
			}
			if ($this->peek('op', '(')) {
				$this->p++;
				$e = $this->expr();
				$this->take('op', ')');
				return $e;
			}
			if ($this->peek('word')) {
				$name = $this->take('word');
				if ($name == 'true' || $name == 'false' || $name == 'null') {
					return array('lit', $name == 'true' ? true : ($name == 'false' ? false : null));
				}
				if (!in_array($name, mkl_tpl_functions(), true)) {
					throw new MklTplError('Function not allowed in a template: ' . $name);
				}
				$this->take('op', '(');
				$args = array();
				if (!$this->peek('op', ')')) {
					$args[] = $this->expr();
					while ($this->peek('op', ',')) {
						$this->p++;
						$args[] = $this->expr();
					}
				}
				$this->take('op', ')');
				return array('call', $name, $args);
			}
			$got = isset($this->t[$this->p]) ? $this->t[$this->p][1] : 'end of template';
			throw new MklTplError('Unexpected "' . substr(is_scalar($got) ? (string)$got : '', 0, 20) . '"');
		}
	}

	/** Parse a template. Throws MklTplError when it uses anything not allowed. */
	function mkl_tpl_compile($src)
	{
		$p = new MklTplParser(mkl_tpl_tokens($src));
		return $p->program();
	}

	/** '' when the template is acceptable, otherwise the reason it is not. */
	function mkl_tpl_check($src)
	{
		if (strlen((string)$src) > 200000) {
			return 'Template is too large.';
		}
		try {
			mkl_tpl_compile($src);
		} catch (MklTplError $e) {
			return $e->getMessage();
		}
		return '';
	}

	function mkl_tpl_str($v)
	{
		if (is_bool($v)) {
			return $v ? '1' : '';
		}
		return is_scalar($v) ? (string)$v : '';
	}

	function mkl_tpl_eval($e, &$vars)
	{
		switch ($e[0]) {
			case 'lit':
				return $e[1];
			case 'var':
				return isset($vars[$e[1]]) && is_scalar($vars[$e[1]]) ? $vars[$e[1]] : '';
			case 'dstr':
				$s = preg_replace_callback('/\{\$([A-Za-z_][A-Za-z0-9_]*)\}|\$([A-Za-z_][A-Za-z0-9_]*)/', function ($m) use (&$vars) {
					$n = $m[1] !== '' ? $m[1] : $m[2];
					return isset($vars[$n]) ? mkl_tpl_str($vars[$n]) : '';
				}, $e[1]);
				return str_replace("\x00", '$', $s);
			case 'cat':
				return mkl_tpl_str(mkl_tpl_eval($e[1], $vars)) . mkl_tpl_str(mkl_tpl_eval($e[2], $vars));
			case 'not':
				return !mkl_tpl_eval($e[1], $vars);
			case 'and':
				return mkl_tpl_eval($e[1], $vars) && mkl_tpl_eval($e[2], $vars);
			case 'or':
				return mkl_tpl_eval($e[1], $vars) || mkl_tpl_eval($e[2], $vars);
			case 'cmp':
				$a = mkl_tpl_eval($e[2], $vars);
				$b = mkl_tpl_eval($e[3], $vars);
				switch ($e[1]) {
					case '==': return $a == $b;
					case '!=': return $a != $b;
					case '===': return $a === $b;
					case '!==': return $a !== $b;
					case '<': return $a < $b;
					case '>': return $a > $b;
					case '<=': return $a <= $b;
					default: return $a >= $b;
				}
			case 'call':
				if ($e[1] == 'empty' || $e[1] == 'isset') {
					$v = isset($e[2][0]) ? mkl_tpl_eval($e[2][0], $vars) : '';
					return $e[1] == 'empty' ? empty($v) : ($v !== '' && $v !== null);
				}
				$args = array();
				foreach ($e[2] as $a) {
					$args[] = mkl_tpl_eval($a, $vars);
				}
				try {
					return call_user_func_array($e[1], $args);
				} catch (Throwable $t) {
					return '';
				}
		}
		return '';
	}

	function mkl_tpl_run($nodes, &$vars)
	{
		$out = '';
		foreach ($nodes as $n) {
			if ($n[0] == 'text') {
				$out .= $n[1];
			} elseif ($n[0] == 'echo') {
				foreach ($n[1] as $e) {
					$out .= mkl_tpl_str(mkl_tpl_eval($e, $vars));
				}
			} elseif ($n[0] == 'set') {
				$vars[$n[1]] = mkl_tpl_eval($n[2], $vars);
			} elseif ($n[0] == 'if') {
				$done = false;
				foreach ($n[1] as $b) {
					if (mkl_tpl_eval($b[0], $vars)) {
						$out .= mkl_tpl_run($b[1], $vars);
						$done = true;
						break;
					}
				}
				if (!$done) {
					$out .= mkl_tpl_run($n[2], $vars);
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/*  Storage                                                            */
	/* ------------------------------------------------------------------ */

	/** default | thermal | small */
	function mkl_tpl_kind($kind)
	{
		return in_array($kind, array('thermal', 'small'), true) ? $kind : 'default';
	}

	/** Built-in template shipped with the application. */
	function mkl_tpl_builtin($kind)
	{
		$map = array('default' => 'default.php', 'thermal' => 'default-thermal.php', 'small' => 'default-small.php');
		return (string)file_get_contents(MKL_ROOT . '/voucher/' . $map[mkl_tpl_kind($kind)]);
	}

	function mkl_tpl_file($kind)
	{
		return MKL_DATA . '/tpl-' . mkl_tpl_kind($kind) . '.tpl';
	}

	/** The customer's template, or the built-in one when none was saved. */
	function mkl_tpl_load($kind)
	{
		$f = mkl_tpl_file($kind);
		return is_file($f) ? (string)file_get_contents($f) : mkl_tpl_builtin($kind);
	}

	/** Save a template. Returns '' on success or the reason it was refused. */
	function mkl_tpl_save($kind, $src)
	{
		$err = mkl_tpl_check($src);
		if ($err != '') {
			return $err;
		}
		return file_put_contents(mkl_tpl_file($kind), (string)$src, LOCK_EX) === false ? 'Cannot write the template file.' : '';
	}

	/**
	 * Render one voucher.
	 * @param string $kind default | thermal | small
	 * @param array  $vars values the template may print
	 */
	function mkl_tpl_render($kind, $vars)
	{
		static $cache = array();
		$kind = mkl_tpl_kind($kind);
		if (!isset($cache[$kind])) {
			try {
				$cache[$kind] = mkl_tpl_compile(mkl_tpl_load($kind));
			} catch (MklTplError $e) {
				// a broken saved template never blocks printing
				$cache[$kind] = mkl_tpl_compile(mkl_tpl_builtin($kind));
			}
		}
		return mkl_tpl_run($cache[$kind], $vars);
	}

	/** Names of the values handed to a voucher template. */
	function mkl_tpl_varnames()
	{
		return array('username', 'password', 'profile', 'timelimit', 'datalimit', 'comment', 'validity', 'price',
			'hotspotname', 'dnsname', 'logo', 'qrcode', 'num', 'usermode', 'qr', 'small', 'userp', 'urilogin',
			'currency', 'getprice', 'getsprice', 'session');
	}
}
