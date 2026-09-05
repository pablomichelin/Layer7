<?php
/**
 * Harness V10 — render isolado da view de layer7_reports.php (sem SQLite/ingestão/rede).
 */

if (!defined("L7HR_ROOT")) {
	define("L7HR_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HR_REPORTS")) {
	define(
		"L7HR_REPORTS",
		L7HR_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_reports.php"
	);
}
if (!defined("L7HR_BASELINE")) {
	define(
		"L7HR_BASELINE",
		L7HR_ROOT . "/tests/functional/baseline-v10-reports/layer7_reports.php"
	);
}

if (!function_exists("l7_t")) {
	function l7_t($s)
	{
		if (isset($GLOBALS["l7hr_l7_t_fixture"]) && is_array($GLOBALS["l7hr_l7_t_fixture"]) &&
		    array_key_exists($s, $GLOBALS["l7hr_l7_t_fixture"])) {
			return (string)$GLOBALS["l7hr_l7_t_fixture"][$s];
		}
		return $s;
	}
}

if (!function_exists("resolveIdentityByIp")) {
	function resolveIdentityByIp($ip)
	{
		if (isset($GLOBALS["l7hr_identity"][(string)$ip]) &&
		    is_array($GLOBALS["l7hr_identity"][(string)$ip])) {
			return $GLOBALS["l7hr_identity"][(string)$ip];
		}
		return array("display_name" => (string)$ip);
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($tab)
	{
		echo "<!-- L7HR_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
	}
}

if (!function_exists("layer7_render_messages")) {
	function layer7_render_messages()
	{
	}
}

if (!function_exists("l7hr_default_summary")) {
	function l7hr_default_summary()
	{
		return array(
			"total_events" => 1200,
			"blocked_events" => 480,
			"allowed_events" => 600,
			"monitor_events" => 120,
			"unique_devices" => 42,
			"unique_sites" => 88,
		);
	}
}

if (!function_exists("l7hr_fixture_vars")) {
	function l7hr_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$from_ts = isset($opts["from_ts"]) ? (int)$opts["from_ts"] : 1704067200;
		$to_ts = isset($opts["to_ts"]) ? (int)$opts["to_ts"] : 1704153599;
		$filters = array(
			"src_ip" => isset($opts["filters"]["src_ip"]) ? (string)$opts["filters"]["src_ip"] : "",
			"host" => isset($opts["filters"]["host"]) ? (string)$opts["filters"]["host"] : "",
			"action" => isset($opts["filters"]["action"]) ? (string)$opts["filters"]["action"] : "",
			"q" => isset($opts["filters"]["q"]) ? (string)$opts["filters"]["q"] : "",
		);
		if (isset($opts["filters"]) && is_array($opts["filters"])) {
			foreach ($opts["filters"] as $k => $v) {
				if (array_key_exists($k, $filters)) {
					$filters[$k] = (string)$v;
				}
			}
		}
		$summary = isset($opts["summary"]) && is_array($opts["summary"])
		    ? $opts["summary"] : l7hr_default_summary();
		$timeline = isset($opts["timeline"]) && is_array($opts["timeline"]) ? $opts["timeline"] : array(
			array("ts" => $from_ts, "total_events" => 10, "blocked_events" => 4, "allowed_events" => 6),
			array("ts" => $to_ts, "total_events" => 12, "blocked_events" => 5, "allowed_events" => 7),
		);
		$top_devices = isset($opts["top_devices"]) && is_array($opts["top_devices"]) ? $opts["top_devices"] : array(
			array("src_ip" => "10.0.0.5", "blocked_events" => 40, "total_events" => 55),
		);
		$top_sites = isset($opts["top_sites"]) && is_array($opts["top_sites"]) ? $opts["top_sites"] : array(
			array("host" => "youtube.com", "blocked_events" => 30, "total_events" => 45),
		);
		$rows = isset($opts["rows"]) && is_array($opts["rows"]) ? $opts["rows"] : array(
			array(
				"ts_text" => "01/01/2024 12:00",
				"src_ip" => "10.0.0.5",
				"host" => "evil.com",
				"host_inferred" => true,
				"app" => "HTTP",
				"action" => "block",
			),
		);
		$page = isset($opts["page"]) ? max(1, (int)$opts["page"]) : 1;
		$page_size = 50;
		$total_rows = isset($opts["total_rows"]) ? (int)$opts["total_rows"] : count($rows);
		$total_pages = max(1, (int)ceil($total_rows / $page_size));
		$total_events = (int)($summary["total_events"] ?? 0);
		$blocked_events = (int)($summary["blocked_events"] ?? 0);
		$allowed_events = (int)($summary["allowed_events"] ?? 0);
		$monitor_events = (int)($summary["monitor_events"] ?? 0);
		$unique_devices = (int)($summary["unique_devices"] ?? 0);
		$unique_sites = (int)($summary["unique_sites"] ?? 0);
		$block_rate = $total_events > 0 ? round(($blocked_events / $total_events) * 100, 1) : 0;
		return array(
			"clear_msg" => isset($opts["clear_msg"]) ? (string)$opts["clear_msg"] : "",
			"range" => isset($opts["range"]) ? (string)$opts["range"] : "24h",
			"custom_from" => isset($opts["custom_from"]) ? (string)$opts["custom_from"] : "",
			"custom_to" => isset($opts["custom_to"]) ? (string)$opts["custom_to"] : "",
			"page" => $page,
			"page_size" => $page_size,
			"filters" => $filters,
			"from_ts" => $from_ts,
			"to_ts" => $to_ts,
			"rpt_enabled" => array_key_exists("rpt_enabled", $opts) ? (bool)$opts["rpt_enabled"] : true,
			"rpt_detail_enabled" => array_key_exists("rpt_detail_enabled", $opts) ? (bool)$opts["rpt_detail_enabled"] : true,
			"rpt_event_retention" => isset($opts["rpt_event_retention"]) ? (int)$opts["rpt_event_retention"] : 15,
			"rpt_detail_ifaces" => isset($opts["rpt_detail_ifaces"]) && is_array($opts["rpt_detail_ifaces"])
			    ? $opts["rpt_detail_ifaces"] : array(),
			"detail_range_truncated" => !empty($opts["detail_range_truncated"]),
			"ingest_failed" => !empty($opts["ingest_failed"]),
			"db_ready" => array_key_exists("db_ready", $opts) ? (bool)$opts["db_ready"] : true,
			"summary" => $summary,
			"timeline" => $timeline,
			"top_devices" => $top_devices,
			"top_sites" => $top_sites,
			"rows" => $rows,
			"events_page" => array("rows" => $rows, "total" => $total_rows, "page" => $page, "page_size" => $page_size),
			"total_rows" => $total_rows,
			"total_pages" => $total_pages,
			"total_events" => $total_events,
			"blocked_events" => $blocked_events,
			"allowed_events" => $allowed_events,
			"monitor_events" => $monitor_events,
			"unique_devices" => $unique_devices,
			"unique_sites" => $unique_sites,
			"block_rate" => $block_rate,
			"period_label" => date("d/m/Y H:i", $from_ts) . " - " . date("d/m/Y H:i", $to_ts),
		);
	}
}

if (!function_exists("l7hr_apply_fixtures")) {
	function l7hr_apply_fixtures($opts)
	{
		if (isset($opts["l7_t_fixture"]) && is_array($opts["l7_t_fixture"])) {
			$GLOBALS["l7hr_l7_t_fixture"] = $opts["l7_t_fixture"];
		} else {
			unset($GLOBALS["l7hr_l7_t_fixture"]);
		}
		if (isset($opts["identity"]) && is_array($opts["identity"])) {
			$GLOBALS["l7hr_identity"] = $opts["identity"];
		} else {
			$GLOBALS["l7hr_identity"] = array(
				"10.0.0.5" => array("display_name" => "Laptop-Pablo"),
			);
		}
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		return l7hr_fixture_vars($opts);
	}
}

if (!function_exists("l7hr_prepare_view_slice")) {
	function l7hr_prepare_view_slice($path)
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
		$slice = preg_replace('/include\s*\(\s*"foot\.inc"\s*\)\s*;/', '/* foot stub */', $slice);
		$slice = preg_replace('/layer7_render_styles\s*\(\s*\)\s*;/', '/* styles stub */', $slice);
		$slice = preg_replace('/layer7_render_footer\s*\(\s*\)\s*;/', '/* footer stub */', $slice);
		$cache[$path] = $slice;
		return $slice;
	}
}

if (!function_exists("l7hr_render_file")) {
	function l7hr_render_file($path, $opts)
	{
		$vars = l7hr_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7hr_prepare_view_slice($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7hr_render")) {
	function l7hr_render($opts)
	{
		return l7hr_render_file(L7HR_REPORTS, $opts);
	}
}

if (!function_exists("l7hr_render_baseline")) {
	function l7hr_render_baseline($opts)
	{
		return l7hr_render_file(L7HR_BASELINE, $opts);
	}
}
