<?php
/**
 * Harness V13 — render isolado da view de layer7_mitm.php (sem CA/rede/secrets reais).
 */

if (!defined("L7HM_ROOT")) {
	define("L7HM_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HM_MITM")) {
	define(
		"L7HM_MITM",
		L7HM_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_mitm.php"
	);
}
if (!defined("L7HM_BASELINE")) {
	define(
		"L7HM_BASELINE",
		L7HM_ROOT . "/tests/functional/baseline-v13-mitm/layer7_mitm.php"
	);
}

if (!function_exists("l7_t")) {
	function l7_t($s)
	{
		return $s;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($tab)
	{
		echo "<!-- L7HM_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
	}
}

if (!function_exists("print_input_errors")) {
	function print_input_errors($errs)
	{
		echo '<div class="alert alert-danger" id="l7-input-errors"><ul>';
		foreach ((array)$errs as $e) {
			echo "<li>" . htmlspecialchars((string)$e) . "</li>";
		}
		echo "</ul></div>\n";
	}
}

if (!function_exists("print_info_box")) {
	function print_info_box($msg, $type)
	{
		echo '<div class="alert alert-' . htmlspecialchars((string)$type) . '">' .
		    htmlspecialchars((string)$msg) . "</div>\n";
	}
}

if (!function_exists("l7hm_mitm_defaults")) {
	function l7hm_mitm_defaults()
	{
		return array(
			"enabled" => false,
			"quic_mode" => "bypass",
			"ca" => array(
				"present" => false,
				"cn" => "Layer7 MITM CA",
				"subject" => "",
				"fingerprint_sha256" => "",
				"not_after" => "",
			),
			"window" => array(
				"max_minutes" => 15,
				"deadline_unix" => 0,
			),
			"intercept" => array(
				"source_cidr" => array(),
				"dest_cidr" => array(),
				"block_sni" => array(),
			),
			"bypass" => array(
				"sni" => array(),
				"cidr" => array("127.0.0.1/32", "::1/128"),
			),
		);
	}
}

if (!function_exists("l7hm_fixture_vars")) {
	function l7hm_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$mitm = isset($opts["mitm"]) && is_array($opts["mitm"])
		    ? $opts["mitm"] : l7hm_mitm_defaults();
		$ca_ok = array_key_exists("ca_ok", $opts)
		    ? (bool)$opts["ca_ok"] : !empty($mitm["ca"]["present"]);
		$unlocked = array_key_exists("unlocked", $opts) ? (bool)$opts["unlocked"] : true;
		$runtime_ok = array_key_exists("runtime_ok", $opts)
		    ? (bool)$opts["runtime_ok"] : false;
		$effective = array_key_exists("effective", $opts)
		    ? (bool)$opts["effective"] : false;
		$toggle_ok = array_key_exists("toggle_ok", $opts)
		    ? (bool)$opts["toggle_ok"] : ($unlocked && $ca_ok);
		$win_status = isset($opts["win_status"]) && is_array($opts["win_status"])
		    ? $opts["win_status"] : array(
			"source_cidr" => $mitm["intercept"]["source_cidr"] ?? array(),
			"dest_cidr" => $mitm["intercept"]["dest_cidr"] ?? array(),
			"block_sni" => $mitm["intercept"]["block_sni"] ?? array(),
			"quic_mode" => $mitm["quic_mode"] ?? "bypass",
			"max_minutes" => (int)($mitm["window"]["max_minutes"] ?? 15),
			"until_off" => ((int)($mitm["window"]["max_minutes"] ?? 15) === 0),
			"remaining_sec" => 0,
			"expired" => false,
		    );
		$sup_status = isset($opts["sup_status"]) && is_array($opts["sup_status"])
		    ? $opts["sup_status"] : array("armed" => false);
		return array(
			"unlocked" => $unlocked,
			"mitm" => $mitm,
			"runtime_ok" => $runtime_ok,
			"effective" => $effective,
			"ca_ok" => $ca_ok,
			"toggle_ok" => $toggle_ok,
			"win_status" => $win_status,
			"sup_status" => $sup_status,
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
			"savemsg" => isset($opts["savemsg"]) ? (string)$opts["savemsg"] : "",
		);
	}
}

if (!function_exists("l7hm_apply_fixtures")) {
	function l7hm_apply_fixtures($opts)
	{
		global $input_errors, $savemsg;
		$vars = l7hm_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		$savemsg = $vars["savemsg"];
		return $vars;
	}
}

if (!function_exists("l7hm_prepare_view_slice")) {
	function l7hm_prepare_view_slice($path)
	{
		static $cache = array();
		if (isset($cache[$path])) {
			return $cache[$path];
		}
		$loaded = file_get_contents($path);
		if (!is_string($loaded) || $loaded === "") {
			fwrite(STDERR, "FAIL nao foi possivel ler {$path}\n");
			exit(1);
		}
		$start = strpos($loaded, '$pgtitle = array(');
		if ($start === false) {
			fwrite(STDERR, "FAIL marcador pgtitle ausente em {$path}\n");
			exit(1);
		}
		$slice = substr($loaded, $start);
		$slice = preg_replace('/include\s*\(\s*"head\.inc"\s*\)\s*;/', '/* head stub */', $slice);
		$slice = preg_replace('/require_once\s*\(\s*"foot\.inc"\s*\)\s*;/', '/* foot stub */', $slice);
		$slice = preg_replace('/layer7_render_styles\s*\(\s*\)\s*;/', '/* styles stub */', $slice);
		$slice = preg_replace('/layer7_render_footer\s*\(\s*\)\s*;/', '/* footer stub */', $slice);
		$cache[$path] = $slice;
		return $slice;
	}
}

if (!function_exists("l7hm_render_file")) {
	function l7hm_render_file($path, $opts)
	{
		$vars = l7hm_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7hm_prepare_view_slice($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7hm_render")) {
	function l7hm_render($opts)
	{
		return l7hm_render_file(L7HM_MITM, $opts);
	}
}

if (!function_exists("l7hm_render_baseline")) {
	function l7hm_render_baseline($opts)
	{
		return l7hm_render_file(L7HM_BASELINE, $opts);
	}
}
