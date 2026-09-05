<?php
/**
 * Harness V14 — render isolado da view de layer7_blacklists.php (sem download/rede real).
 */

if (!defined("L7BL_ROOT")) {
	define("L7BL_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7BL_PAGE")) {
	define(
		"L7BL_PAGE",
		L7BL_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_blacklists.php"
	);
}
if (!defined("L7BL_BASELINE")) {
	define(
		"L7BL_BASELINE",
		L7BL_ROOT . "/tests/functional/baseline-v14-blacklists/layer7_blacklists.php"
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
		echo "<!-- L7BL_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
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

if (!function_exists("layer7_bl_official_manifest_url")) {
	function layer7_bl_official_manifest_url()
	{
		return "https://example.test/layer7/blacklists/manifest.json";
	}
}

if (!function_exists("layer7_bl_official_mirror_urls")) {
	function layer7_bl_official_mirror_urls()
	{
		return array(
			"https://mirror1.example.test/manifest.json",
			"https://mirror2.example.test/manifest.json",
		);
	}
}

if (!function_exists("layer7_bl_download_status")) {
	function layer7_bl_download_status()
	{
		return isset($GLOBALS["l7bl_download_status"])
		    ? (string)$GLOBALS["l7bl_download_status"] : "SYNTH-DOWNLOAD-LOG";
	}
}

if (!function_exists("l7bl_default_config")) {
	function l7bl_default_config()
	{
		return array(
			"enabled" => false,
			"auto_update" => false,
			"update_interval_hours" => 24,
			"max_entries" => 5000000,
			"mem_percent" => 25,
			"whitelist" => array("never.block.example"),
			"rules" => array(),
			"category_custom" => array(),
		);
	}
}

if (!function_exists("l7bl_default_categories")) {
	function l7bl_default_categories()
	{
		return array(
			array("id" => "adult", "domains_count" => 1200, "custom_domains_count" => 0, "custom_only" => false),
			array("id" => "games", "domains_count" => 450, "custom_domains_count" => 2, "custom_only" => false),
			array("id" => "custom_local", "domains_count" => 3, "custom_domains_count" => 3, "custom_only" => true),
		);
	}
}

if (!function_exists("l7bl_fixture_vars")) {
	function l7bl_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$bl_config = isset($opts["bl_config"]) && is_array($opts["bl_config"])
		    ? $opts["bl_config"] : l7bl_default_config();
		$custom_map = isset($opts["custom_map"]) && is_array($opts["custom_map"])
		    ? $opts["custom_map"]
		    : (isset($bl_config["category_custom"]) ? $bl_config["category_custom"] : array());
		if (empty($custom_map) && !empty($opts["with_custom"])) {
			$custom_map = array("custom_local" => array("a.example", "b.example"));
			$bl_config["category_custom"] = $custom_map;
		}
		$merged_categories = isset($opts["merged_categories"]) && is_array($opts["merged_categories"])
		    ? $opts["merged_categories"] : l7bl_default_categories();
		$rules = isset($opts["rules"]) && is_array($opts["rules"])
		    ? $opts["rules"] : (isset($bl_config["rules"]) ? $bl_config["rules"] : array());
		if (empty($rules) && !empty($opts["with_rules"])) {
			$rules = array(array(
				"name" => "Lab",
				"enabled" => true,
				"force_dns" => true,
				"categories" => array("adult"),
				"src_cidrs" => array("192.168.10.0/24"),
				"except_ips" => array("192.168.10.1"),
			));
			$bl_config["rules"] = $rules;
		}
		$edit_idx = array_key_exists("edit_idx", $opts) ? (int)$opts["edit_idx"] : -1;
		if (!empty($opts["add_rule"])) {
			$edit_idx = -2;
		}
		$cat_edit = isset($opts["cat_edit"]) ? (string)$opts["cat_edit"] : "";
		if ($cat_edit === "" && !empty($opts["cat_editing"])) {
			$cat_edit = "custom_local";
		}
		$bl_max_entries = (int)($bl_config["max_entries"] ?? 5000000);
		return array(
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
			"savemsg" => isset($opts["savemsg"]) ? (string)$opts["savemsg"] : "",
			"bl_config" => $bl_config,
			"discovered" => isset($opts["discovered"]) ? $opts["discovered"] : array("categories" => array()),
			"custom_map" => $custom_map,
			"merged_categories" => $merged_categories,
			"bl_stats" => isset($opts["bl_stats"]) ? $opts["bl_stats"] : array(
				"rules_active" => count($rules),
				"categories_active" => 1,
				"domains_loaded" => 1500,
				"lookups" => 42,
				"hits" => 7,
				"top_categories" => array(array("cat" => "adult", "hits" => 5)),
			),
			"last_update" => isset($opts["last_update"]) ? $opts["last_update"] : "2026-01-01T00:00:00Z",
			"runtime_state" => isset($opts["runtime_state"]) ? $opts["runtime_state"] : array(
				"snapshot_id" => "snap-synth",
				"manifest_url" => "https://example.test/manifest.json",
				"source_role" => "primary",
			),
			"fallback_state" => isset($opts["fallback_state"]) ? $opts["fallback_state"] : array(
				"status" => "healthy",
				"mode" => "-",
				"safe_state" => "active",
				"reason" => "-",
				"operator_action" => "-",
			),
			"lkg_state" => isset($opts["lkg_state"]) ? $opts["lkg_state"] : array("snapshot_id" => "lkg-synth"),
			"content_sub" => isset($opts["content_sub"]) ? $opts["content_sub"] : array(
				"status" => "ok",
				"ok" => true,
				"message" => "fixture",
				"exp" => 1893456000,
			),
			"rules" => $rules,
			"bl_phys" => isset($opts["bl_phys"]) ? (int)$opts["bl_phys"] : 4 * 1024 * 1024 * 1024,
			"bl_budget" => isset($opts["bl_budget"]) ? (int)$opts["bl_budget"] : 1024 * 1024 * 1024,
			"bl_max_entries" => $bl_max_entries,
			"bl_union_domains" => isset($opts["bl_union_domains"]) ? (int)$opts["bl_union_domains"] : 1200,
			"edit_idx" => $edit_idx,
			"cat_edit" => $cat_edit,
			"bl_download_poll" => !empty($opts["bl_download_poll"]),
		);
	}
}

if (!function_exists("l7bl_apply_fixtures")) {
	function l7bl_apply_fixtures($opts)
	{
		global $input_errors, $savemsg;
		$vars = l7bl_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		$savemsg = $vars["savemsg"];
		$GLOBALS["l7bl_download_status"] = isset($opts["download_status"])
		    ? (string)$opts["download_status"] : "SYNTH-DOWNLOAD-LOG";
		return $vars;
	}
}

if (!function_exists("l7bl_prepare_view_slice")) {
	function l7bl_prepare_view_slice($path)
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

if (!function_exists("l7bl_render_file")) {
	function l7bl_render_file($path, $opts)
	{
		$vars = l7bl_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7bl_prepare_view_slice($path));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7bl_render")) {
	function l7bl_render($opts)
	{
		return l7bl_render_file(L7BL_PAGE, $opts);
	}
}

if (!function_exists("l7bl_render_baseline")) {
	function l7bl_render_baseline($opts)
	{
		return l7bl_render_file(L7BL_BASELINE, $opts);
	}
}
