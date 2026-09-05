<?php
/**
 * Harness V12 — render isolado da view de layer7_identity.php (sem LDAP/rede/secrets reais).
 */

if (!defined("L7HI_ROOT")) {
	define("L7HI_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HI_IDENTITY")) {
	define(
		"L7HI_IDENTITY",
		L7HI_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_identity.php"
	);
}
if (!defined("L7HI_BASELINE")) {
	define(
		"L7HI_BASELINE",
		L7HI_ROOT . "/tests/functional/baseline-v12-identity/layer7_identity.php"
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
		echo "<!-- L7HI_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
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

if (!function_exists("l7hi_identity_defaults")) {
	function l7hi_identity_defaults()
	{
		return array(
			"enabled" => false,
			"ldap" => array(
				"enabled" => false,
				"server" => "",
				"port" => 636,
				"use_tls" => true,
				"bind_dn" => "",
				"base_dn" => "",
				"user_filter" => "(&(objectCategory=person)(objectClass=user))",
				"group_filter" => "(objectClass=group)",
				"group_depth" => 5,
				"max_members" => 4096,
			),
			"radius" => array(
				"enabled" => false,
				"listen_port" => 1813,
				"bind_address" => "0.0.0.0",
				"nas_acl" => array(),
			),
			"dc_agent" => array(
				"enabled" => false,
				"listen_port" => 8743,
				"bind_address" => "127.0.0.1",
				"skew_sec" => 300,
				"dc_acl" => array(),
			),
		);
	}
}

if (!function_exists("l7hi_fixture_vars")) {
	function l7hi_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$identity = isset($opts["identity"]) && is_array($opts["identity"])
		    ? $opts["identity"] : l7hi_identity_defaults();
		$ldap = isset($identity["ldap"]) && is_array($identity["ldap"])
		    ? $identity["ldap"] : l7hi_identity_defaults()["ldap"];
		$radius = isset($identity["radius"]) && is_array($identity["radius"])
		    ? $identity["radius"] : l7hi_identity_defaults()["radius"];
		$dc = isset($identity["dc_agent"]) && is_array($identity["dc_agent"])
		    ? $identity["dc_agent"] : l7hi_identity_defaults()["dc_agent"];
		$nas_acl_text = isset($opts["nas_acl_text"])
		    ? (string)$opts["nas_acl_text"]
		    : (is_array($radius["nas_acl"] ?? null) ? implode(", ", $radius["nas_acl"]) : "");
		$dc_acl_text = isset($opts["dc_acl_text"])
		    ? (string)$opts["dc_acl_text"]
		    : (is_array($dc["dc_acl"] ?? null) ? implode(", ", $dc["dc_acl"]) : "");
		return array(
			"unlocked" => array_key_exists("unlocked", $opts) ? (bool)$opts["unlocked"] : true,
			"identity" => $identity,
			"ldap" => $ldap,
			"radius" => $radius,
			"dc" => $dc,
			"nas_acl_text" => $nas_acl_text,
			"dc_acl_text" => $dc_acl_text,
			"pwd_set" => !empty($opts["pwd_set"]),
			"radius_secret_set" => !empty($opts["radius_secret_set"]),
			"dc_secret_set" => !empty($opts["dc_secret_set"]),
			"dc_token_once" => isset($opts["dc_token_once"]) ? (string)$opts["dc_token_once"] : "",
			"ldap_test" => array_key_exists("ldap_test", $opts) ? $opts["ldap_test"] : null,
			"l7_feat_raw" => isset($opts["l7_feat_raw"]) ? (string)$opts["l7_feat_raw"] : "identity",
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
			"savemsg" => isset($opts["savemsg"]) ? (string)$opts["savemsg"] : "",
		);
	}
}

if (!function_exists("l7hi_apply_fixtures")) {
	function l7hi_apply_fixtures($opts)
	{
		global $input_errors, $savemsg;
		$vars = l7hi_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		$savemsg = $vars["savemsg"];
		return $vars;
	}
}

if (!function_exists("l7hi_prepare_view_slice")) {
	function l7hi_prepare_view_slice($path)
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

if (!function_exists("l7hi_render_file")) {
	function l7hi_render_file($path, $opts)
	{
		$vars = l7hi_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7hi_prepare_view_slice($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7hi_render")) {
	function l7hi_render($opts)
	{
		return l7hi_render_file(L7HI_IDENTITY, $opts);
	}
}

if (!function_exists("l7hi_render_baseline")) {
	function l7hi_render_baseline($opts)
	{
		return l7hi_render_file(L7HI_BASELINE, $opts);
	}
}
