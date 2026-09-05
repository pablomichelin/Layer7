<?php
/**
 * Harness V8 — render isolado da view de layer7_diagnostics.php (sem prefixo/exec/rede).
 */

if (!defined("L7HD_ROOT")) {
	define("L7HD_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HD_DIAGNOSTICS")) {
	define(
		"L7HD_DIAGNOSTICS",
		L7HD_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php"
	);
}
if (!defined("L7HD_BASELINE")) {
	define(
		"L7HD_BASELINE",
		L7HD_ROOT . "/tests/functional/baseline-v8-diagnostics/layer7_diagnostics.php"
	);
}

if (!function_exists("l7_t")) {
	function l7_t($s)
	{
		if (isset($GLOBALS["l7hd_l7_t_fixture"]) && is_array($GLOBALS["l7hd_l7_t_fixture"]) &&
		    array_key_exists($s, $GLOBALS["l7hd_l7_t_fixture"])) {
			return (string)$GLOBALS["l7hd_l7_t_fixture"][$s];
		}
		return $s;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($tab)
	{
		echo "<!-- L7HD_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
	}
}

if (!function_exists("layer7_render_styles")) {
	function layer7_render_styles()
	{
		echo "<!-- L7HD_STYLES -->\n";
	}
}

if (!function_exists("layer7_render_footer")) {
	function layer7_render_footer()
	{
		echo "<!-- L7HD_FOOTER -->\n";
	}
}

if (!function_exists("layer7_gui_mode_badge_html")) {
	function layer7_gui_mode_badge_html($enf)
	{
		$mode = is_array($enf) && isset($enf["effective_mode"]) ? (string)$enf["effective_mode"] : "monitor";
		return '<span class="label label-info">' . htmlspecialchars($mode) . "</span>";
	}
}

if (!function_exists("l7hd_default_error_report_ctx")) {
	function l7hd_default_error_report_ctx()
	{
		return array(
			"pkg_version" => "1.9.79-harness",
			"daemon" => "running",
			"daemon_version" => "layer7d-harness",
			"enabled" => "true",
			"mode" => "monitor",
			"enforcement_model" => "legacy_global",
			"interface_count" => "2",
			"mitm" => "off",
		);
	}
}

if (!function_exists("l7hd_fixture_vars")) {
	function l7hd_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		return array(
			"sigusr1_sent" => !empty($opts["sigusr1_sent"]),
			"sighup_sent" => !empty($opts["sighup_sent"]),
			"anti_doh_result" => array_key_exists("anti_doh_result", $opts)
			    ? $opts["anti_doh_result"] : null,
			"status_ok" => array_key_exists("status_ok", $opts) ? (bool)$opts["status_ok"] : true,
			"pid" => array_key_exists("pid", $opts) ? $opts["pid"] : 4242,
			"status_out" => isset($opts["status_out"])
			    ? (string)$opts["status_out"]
			    : "Servico layer7d em execucao (PID 4242).",
			"layer7d_ver" => isset($opts["layer7d_ver"]) ? (string)$opts["layer7d_ver"] : "1.9.79-harness",
			"enf" => isset($opts["enf"]) && is_array($opts["enf"])
			    ? $opts["enf"] : array("effective_mode" => "monitor"),
			"cfg_enforcement_model" => isset($opts["cfg_enforcement_model"])
			    ? (string)$opts["cfg_enforcement_model"] : "legacy_global",
			"pf_scoped_active" => !empty($opts["pf_scoped_active"]),
			"pf_scoped_ok" => array_key_exists("pf_scoped_ok", $opts) ? (bool)$opts["pf_scoped_ok"] : true,
			"cfg_ifaces" => isset($opts["cfg_ifaces"]) && is_array($opts["cfg_ifaces"])
			    ? $opts["cfg_ifaces"] : array("lan"),
			"protos_exists" => !empty($opts["protos_exists"]),
			"protos_path" => isset($opts["protos_path"])
			    ? (string)$opts["protos_path"] : "/usr/local/etc/layer7-protos.txt",
			"protos_rules" => isset($opts["protos_rules"]) ? (int)$opts["protos_rules"] : 0,
			"cfgpath" => isset($opts["cfgpath"])
			    ? (string)$opts["cfgpath"] : "/usr/local/etc/layer7.json",
			"log_path" => isset($opts["log_path"])
			    ? (string)$opts["log_path"] : "/var/log/layer7d.log",
			"pf_helper" => isset($opts["pf_helper"])
			    ? (string)$opts["pf_helper"] : "/usr/local/libexec/layer7-pfctl",
			"pf_helper_executable" => array_key_exists("pf_helper_executable", $opts)
			    ? (bool)$opts["pf_helper_executable"] : true,
			"pf_rules_exists" => !empty($opts["pf_rules_exists"]),
			"pf_rules" => isset($opts["pf_rules"])
			    ? (string)$opts["pf_rules"] : "/tmp/layer7.rules",
			"pf_rules_sample" => isset($opts["pf_rules_sample"])
			    ? (string)$opts["pf_rules_sample"] : "/usr/local/etc/layer7/pf.conf.sample",
			"pf_rules_sample_exists" => array_key_exists("pf_rules_sample_exists", $opts)
			    ? (bool)$opts["pf_rules_sample_exists"] : true,
			"pf_hook_ready" => array_key_exists("pf_hook_ready", $opts) ? (bool)$opts["pf_hook_ready"] : true,
			"pf_rules_debug_path" => isset($opts["pf_rules_debug_path"])
			    ? (string)$opts["pf_rules_debug_path"] : "/tmp/rules.debug",
			"pf_rules_debug_exists" => array_key_exists("pf_rules_debug_exists", $opts)
			    ? (bool)$opts["pf_rules_debug_exists"] : false,
			"pf_rules_debug_has_layer7" => !empty($opts["pf_rules_debug_has_layer7"]),
			"pf_active_any_rules_loaded" => !empty($opts["pf_active_any_rules_loaded"]),
			"pf_enforcement_real_ok" => !empty($opts["pf_enforcement_real_ok"]),
			"pf_active_block_rules_loaded" => !empty($opts["pf_active_block_rules_loaded"]),
			"pf_required_tables_ok" => array_key_exists("pf_required_tables_ok", $opts)
			    ? (bool)$opts["pf_required_tables_ok"] : true,
			"pf_block_count" => isset($opts["pf_block_count"]) ? (int)$opts["pf_block_count"] : 0,
			"pf_block_entries" => isset($opts["pf_block_entries"]) && is_array($opts["pf_block_entries"])
			    ? $opts["pf_block_entries"] : array(),
			"pf_block_ready" => array_key_exists("pf_block_ready", $opts) ? (bool)$opts["pf_block_ready"] : true,
			"pf_block_dst_count" => isset($opts["pf_block_dst_count"]) ? (int)$opts["pf_block_dst_count"] : 0,
			"pf_block_dst_entries" => isset($opts["pf_block_dst_entries"]) && is_array($opts["pf_block_dst_entries"])
			    ? $opts["pf_block_dst_entries"] : array(),
			"pf_block_dst_ready" => array_key_exists("pf_block_dst_ready", $opts) ? (bool)$opts["pf_block_dst_ready"] : true,
			"pf_tag_count" => isset($opts["pf_tag_count"]) ? (int)$opts["pf_tag_count"] : -1,
			"pf_tag_entries" => isset($opts["pf_tag_entries"]) && is_array($opts["pf_tag_entries"])
			    ? $opts["pf_tag_entries"] : array(),
			"pf_tag_ready" => array_key_exists("pf_tag_ready", $opts) ? (bool)$opts["pf_tag_ready"] : false,
			"pf_bld_tables" => isset($opts["pf_bld_tables"]) && is_array($opts["pf_bld_tables"])
			    ? $opts["pf_bld_tables"] : array(),
			"pf_any_missing" => !empty($opts["pf_any_missing"]),
			"pf_repair_result" => array_key_exists("pf_repair_result", $opts)
			    ? $opts["pf_repair_result"] : null,
			"pf_anti_dot_loaded" => array_key_exists("pf_anti_dot_loaded", $opts)
			    ? (bool)$opts["pf_anti_dot_loaded"] : false,
			"unbound_anti_doh" => !empty($opts["unbound_anti_doh"]),
			"pf_rules_preview" => isset($opts["pf_rules_preview"]) && is_array($opts["pf_rules_preview"])
			    ? $opts["pf_rules_preview"] : array(),
			"pf_generated_preview" => isset($opts["pf_generated_preview"]) && is_array($opts["pf_generated_preview"])
			    ? $opts["pf_generated_preview"] : array(),
			"pf_rules_debug_hits" => isset($opts["pf_rules_debug_hits"]) && is_array($opts["pf_rules_debug_hits"])
			    ? $opts["pf_rules_debug_hits"] : array(),
			"pf_active_any_rules_hits" => isset($opts["pf_active_any_rules_hits"]) && is_array($opts["pf_active_any_rules_hits"])
			    ? $opts["pf_active_any_rules_hits"] : array(),
			"error_report_summary" => isset($opts["error_report_summary"])
			    ? (string)$opts["error_report_summary"] : "",
			"error_report_ctx" => isset($opts["error_report_ctx"]) && is_array($opts["error_report_ctx"])
			    ? $opts["error_report_ctx"] : l7hd_default_error_report_ctx(),
			"error_report_copy_text" => isset($opts["error_report_copy_text"])
			    ? (string)$opts["error_report_copy_text"] : "",
			"recent_logs" => isset($opts["recent_logs"]) && is_array($opts["recent_logs"])
			    ? $opts["recent_logs"] : array("harness log line"),
		);
	}
}

if (!function_exists("l7hd_apply_fixtures")) {
	function l7hd_apply_fixtures($opts)
	{
		if (isset($opts["l7_t_fixture"]) && is_array($opts["l7_t_fixture"])) {
			$GLOBALS["l7hd_l7_t_fixture"] = $opts["l7_t_fixture"];
		} else {
			unset($GLOBALS["l7hd_l7_t_fixture"]);
		}
		return l7hd_fixture_vars($opts);
	}
}

if (!function_exists("l7hd_view_source")) {
	function l7hd_view_source($path)
	{
		static $cache = array();
		if (!isset($cache[$path])) {
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
			$slice = preg_replace('/<\?php\s+layer7_render_footer\s*\(\s*\)\s*;\s*\?>/', '/* footer stub */', $slice);
			$slice = str_replace('is_executable($pf_helper)', '!empty($pf_helper_executable)', $slice);
			$slice = str_replace('file_exists($pf_rules_sample)', '!empty($pf_rules_sample_exists)', $slice);
			$slice = str_replace('file_exists($pf_rules)', '!empty($pf_rules_exists)', $slice);
			$slice = str_replace('file_exists($pf_rules_debug_path)', '!empty($pf_rules_debug_exists)', $slice);
			$cache[$path] = $slice;
		}
		return $cache[$path];
	}
}

if (!function_exists("l7hd_render_file")) {
	function l7hd_render_file($path, $opts)
	{
		$vars = l7hd_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7hd_view_source($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7hd_render")) {
	function l7hd_render($opts)
	{
		return l7hd_render_file(L7HD_DIAGNOSTICS, $opts);
	}
}

if (!function_exists("l7hd_render_baseline")) {
	function l7hd_render_baseline($opts)
	{
		return l7hd_render_file(L7HD_BASELINE, $opts);
	}
}
