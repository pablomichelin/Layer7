<?php
/**
 * Harness V15 — render isolado da view de layer7_settings.php (sem rede/licença/update real).
 */

if (!defined("L7ST_ROOT")) {
	define("L7ST_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7ST_PAGE")) {
	define(
		"L7ST_PAGE",
		L7ST_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_settings.php"
	);
}
if (!defined("L7ST_BASELINE")) {
	define(
		"L7ST_BASELINE",
		L7ST_ROOT . "/tests/functional/baseline-v15-settings/layer7_settings.php"
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
		echo "<!-- L7ST_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
	}
}

if (!function_exists("layer7_render_messages")) {
	function layer7_render_messages()
	{
		global $input_errors, $savemsg;
		if (!empty($input_errors) && function_exists("print_input_errors")) {
			print_input_errors($input_errors);
		}
		if (!empty($savemsg) && function_exists("print_info_box")) {
			print_info_box($savemsg, "success");
		}
	}
}

if (!function_exists("print_input_errors")) {
	function print_input_errors($errs)
	{
		echo '<div class="alert alert-danger"><ul>';
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

if (!function_exists("layer7_read_license_status")) {
	function layer7_read_license_status()
	{
		global $l7st_license_status;
		return is_array($l7st_license_status) ? $l7st_license_status : array(
			"valid" => true,
			"expired" => false,
			"grace" => false,
			"dev_mode" => false,
			"clock_suspect" => false,
			"hardware_id" => "HW-SYNTH-001",
			"customer" => "Lab Fixture",
			"expiry" => "2030-12-31",
			"days_left" => 365,
			"error" => "",
		);
	}
}

if (!function_exists("layer7_content_subscription_status")) {
	function layer7_content_subscription_status($a, $b)
	{
		global $l7st_content_sub;
		return is_array($l7st_content_sub) ? $l7st_content_sub : array(
			"ok" => true,
			"status" => "ok",
			"message" => "fixture",
		);
	}
}

if (!function_exists("layer7_reports_config")) {
	function layer7_reports_config()
	{
		global $l7st_reports_cfg;
		if (is_array($l7st_reports_cfg)) {
			return $l7st_reports_cfg;
		}
		return array(
			"enabled" => false,
			"retention_days" => 30,
			"collect_interval" => 5,
			"event_log_enabled" => false,
			"event_retention_days" => 7,
			"event_max_mb" => 100,
			"event_interfaces" => array(),
		);
	}
}

if (!function_exists("layer7_reports_normalize_interfaces")) {
	function layer7_reports_normalize_interfaces($ifaces)
	{
		$out = array();
		foreach ((array)$ifaces as $x) {
			if (is_string($x) && $x !== "") {
				$out[] = $x;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_pkg_version")) {
	function layer7_pkg_version()
	{
		global $l7st_pkg_version;
		return isset($l7st_pkg_version) ? (string)$l7st_pkg_version : "1.9.79";
	}
}

if (!function_exists("layer7_daemon_version")) {
	function layer7_daemon_version()
	{
		global $l7st_daemon_version;
		return isset($l7st_daemon_version) ? (string)$l7st_daemon_version : "1.9.79";
	}
}

if (!function_exists("layer7_gui_enforce_reason_text")) {
	function layer7_gui_enforce_reason_text($enf)
	{
		return "enforce fixture reason";
	}
}

if (!function_exists("l7st_default_ifaces")) {
	function l7st_default_ifaces()
	{
		return array(
			array(
				"ifid" => "lan",
				"real" => "em0",
				"descr" => "LAN",
				"checked" => true,
			),
			array(
				"ifid" => "wan",
				"real" => "em1",
				"descr" => "WAN",
				"checked" => false,
			),
		);
	}
}

if (!function_exists("l7st_default_bp_cfg")) {
	function l7st_default_bp_cfg()
	{
		return array(
			"enabled" => false,
			"portal_ip" => "",
			"title" => "Bloqueado",
			"message" => "Acesso negado",
			"contact" => "",
			"show_host" => true,
			"show_policy" => false,
			"sinkhole_blacklists" => false,
			"blacklist_domain_limit" => 512,
			"force_dns" => false,
		);
	}
}

if (!function_exists("l7st_fixture_vars")) {
	function l7st_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$L = isset($opts["L"]) && is_array($opts["L"]) ? $opts["L"] : array(
			"language" => "pt",
			"license_key_mask" => "",
		);
		$bp_cfg = isset($opts["bp_cfg"]) && is_array($opts["bp_cfg"])
		    ? $opts["bp_cfg"] : l7st_default_bp_cfg();
		$enf = isset($opts["enf"]) && is_array($opts["enf"])
		    ? $opts["enf"]
		    : array(
			"requested_mode" => "monitor",
			"display_mode" => "monitor",
		    );
		$update_info = array_key_exists("update_info", $opts) ? $opts["update_info"] : null;
		return array(
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
			"savemsg" => isset($opts["savemsg"]) ? (string)$opts["savemsg"] : "",
			"backup_msg" => isset($opts["backup_msg"]) ? (string)$opts["backup_msg"] : "",
			"backup_err" => isset($opts["backup_err"]) ? (string)$opts["backup_err"] : "",
			"update_msg" => isset($opts["update_msg"]) ? (string)$opts["update_msg"] : "",
			"update_err" => isset($opts["update_err"]) ? (string)$opts["update_err"] : "",
			"update_info" => $update_info,
			"L" => $L,
			"en" => !empty($opts["enabled"]),
			"mode" => isset($opts["mode"]) ? (string)$opts["mode"] : "monitor",
			"enf" => $enf,
			"ll" => isset($opts["log_level"]) ? (string)$opts["log_level"] : "info",
			"sr" => !empty($opts["syslog_remote"]),
			"sr_host" => isset($opts["syslog_remote_host"]) ? (string)$opts["syslog_remote_host"] : "",
			"sr_port" => isset($opts["syslog_remote_port"]) ? (int)$opts["syslog_remote_port"] : 514,
			"log_file_max_mb" => isset($opts["log_file_max_mb"]) ? (int)$opts["log_file_max_mb"] : 5,
			"log_file_keep" => isset($opts["log_file_keep"]) ? (int)$opts["log_file_keep"] : 3,
			"dbgm" => isset($opts["debug_minutes"]) ? (int)$opts["debug_minutes"] : 0,
			"block_quic_ifaces" => isset($opts["block_quic_ifaces"]) && is_array($opts["block_quic_ifaces"])
			    ? $opts["block_quic_ifaces"] : array(),
			"block_dot_doq" => !empty($opts["block_dot_doq"]),
			"sni_inspection" => !empty($opts["sni_inspection"]),
			"enforcement_model" => isset($opts["enforcement_model"]) ? (string)$opts["enforcement_model"] : "legacy_global",
			"bp_cfg" => $bp_cfg,
			"bp_portal_detected" => isset($opts["bp_portal_detected"]) ? (string)$opts["bp_portal_detected"] : "192.168.1.1",
			"bp_cp_conflict" => isset($opts["bp_cp_conflict"]) && is_array($opts["bp_cp_conflict"])
			    ? $opts["bp_cp_conflict"] : array("conflict" => false),
			"bp_domain_info" => isset($opts["bp_domain_info"]) && is_array($opts["bp_domain_info"])
			    ? $opts["bp_domain_info"]
			    : array("domains" => array("blocked.example"), "truncated" => false),
			"cur_lang" => isset($opts["language"]) ? (string)$opts["language"] : "pt",
			"pfsense_ifaces" => isset($opts["pfsense_ifaces"]) && is_array($opts["pfsense_ifaces"])
			    ? $opts["pfsense_ifaces"] : l7st_default_ifaces(),
		);
	}
}

if (!function_exists("l7st_apply_fixtures")) {
	function l7st_apply_fixtures($opts)
	{
		global $input_errors, $savemsg;
		global $l7st_license_status, $l7st_content_sub, $l7st_reports_cfg;
		global $l7st_pkg_version, $l7st_daemon_version;
		$opts = is_array($opts) ? $opts : array();
		$vars = l7st_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		$savemsg = $vars["savemsg"];
		$l7st_license_status = isset($opts["license_status"]) && is_array($opts["license_status"])
		    ? $opts["license_status"] : null;
		$l7st_content_sub = isset($opts["content_sub"]) && is_array($opts["content_sub"])
		    ? $opts["content_sub"] : null;
		$l7st_reports_cfg = isset($opts["reports_cfg"]) && is_array($opts["reports_cfg"])
		    ? $opts["reports_cfg"] : null;
		$l7st_pkg_version = isset($opts["pkg_version"]) ? (string)$opts["pkg_version"] : "1.9.79";
		$l7st_daemon_version = isset($opts["daemon_version"]) ? (string)$opts["daemon_version"] : "1.9.79";
		return $vars;
	}
}

if (!function_exists("l7st_prepare_view_slice")) {
	function l7st_prepare_view_slice($path)
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

if (!function_exists("l7st_render_file")) {
	function l7st_render_file($path, $opts)
	{
		$vars = l7st_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7st_prepare_view_slice($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7st_render")) {
	function l7st_render($opts)
	{
		return l7st_render_file(L7ST_PAGE, $opts);
	}
}

if (!function_exists("l7st_render_baseline")) {
	function l7st_render_baseline($opts)
	{
		return l7st_render_file(L7ST_BASELINE, $opts);
	}
}
