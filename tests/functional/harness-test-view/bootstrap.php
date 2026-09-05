<?php
/**
 * Harness V9 — render isolado da view de layer7_test.php (sem DNS/exec/handlers reais).
 */

if (!defined("L7HT_ROOT")) {
	define("L7HT_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HT_TEST")) {
	define(
		"L7HT_TEST",
		L7HT_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_test.php"
	);
}
if (!defined("L7HT_BASELINE")) {
	define(
		"L7HT_BASELINE",
		L7HT_ROOT . "/tests/functional/baseline-v9-test/layer7_test.php"
	);
}
if (!defined("L7HT_FORM_DIR")) {
	define("L7HT_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
}

require_once dirname(__DIR__) . "/harness-categories-view/bootstrap.php";

if (!defined("COLLAPSIBLE")) {
	define("COLLAPSIBLE", 0x08);
}
if (!defined("SEC_CLOSED")) {
	define("SEC_CLOSED", 0x04);
}
if (!defined("SEC_OPEN")) {
	define("SEC_OPEN", 0x00);
}

if (!function_exists("gettext")) {
	function gettext($s)
	{
		return $s;
	}
}

if (!isset($user_settings) || !is_array($user_settings)) {
	$user_settings = array("webgui" => array("webguileftcolumnhyper" => true));
}

$GLOBALS["l7ht_form_noise"] = 0;

if (!function_exists("l7ht_form_php83_noise")) {
	function l7ht_form_php83_noise($errno, $errstr, $errfile, $errline)
	{
		$known = array(
			"Undefined variable \$hidden",
			"Undefined variable \$help",
			"Undefined variable \$title",
			"Undefined variable \$target",
		);
		$in_vendor = strpos((string)$errfile, "/vendor/pfsense-form/") !== false;
		if ($in_vendor && ($errno === E_WARNING || $errno === E_NOTICE)) {
			foreach ($known as $k) {
				if (strpos((string)$errstr, $k) !== false) {
					$GLOBALS["l7ht_form_noise"]++;
					return true;
				}
			}
		}
		return false;
	}
	set_error_handler("l7ht_form_php83_noise");
}

if (!function_exists("l7ht_load_form_classes")) {
	function l7ht_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HT_FORM_DIR;
		foreach (array(
			$base . "/Form/Element.class.php",
			$base . "/Form/Input.class.php",
			$base . "/Form/Button.class.php",
			$base . "/Form/StaticText.class.php",
			$base . "/Form/Textarea.class.php",
			$base . "/Form/Select.class.php",
			$base . "/Form/Group.class.php",
			$base . "/Form/Section.class.php",
			$base . "/Form.class.php",
		) as $f) {
			require_once $f;
		}
		$loaded = true;
	}
}

if (!function_exists("l7_t")) {
	function l7_t($s)
	{
		if (isset($GLOBALS["l7ht_l7_t_fixture"]) && is_array($GLOBALS["l7ht_l7_t_fixture"]) &&
		    array_key_exists($s, $GLOBALS["l7ht_l7_t_fixture"])) {
			return (string)$GLOBALS["l7ht_l7_t_fixture"][$s];
		}
		return $s;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($tab)
	{
		echo "<!-- L7HT_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
	}
}

if (!function_exists("layer7_render_policies_subnav")) {
	function layer7_render_policies_subnav($active)
	{
		echo "<!-- L7HT_SUBNAV:" . htmlspecialchars((string)$active) . " -->\n";
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

if (!function_exists("layer7_render_messages")) {
	function layer7_render_messages()
	{
		global $input_errors;
		if (!empty($input_errors) && function_exists("print_input_errors")) {
			print_input_errors($input_errors);
		}
	}
}

if (!function_exists("l7ht_default_ndpi_catalog")) {
	/**
	 * Fixture injectado V3 (472 protocolos / 20 categorias) — não catálogo nDPI do appliance.
	 */
	function l7ht_default_ndpi_catalog()
	{
		static $cat = null;
		if ($cat !== null) {
			return $cat;
		}
		$ndpi = l7hc_fixture_catalog_472();
		$protos = isset($ndpi["protocols"]) && is_array($ndpi["protocols"]) ? $ndpi["protocols"] : array();
		$cats = isset($ndpi["categories"]) && is_array($ndpi["categories"]) ? $ndpi["categories"] : array();
		sort($protos);
		sort($cats);
		$cat = array("protocols" => $protos, "categories" => $cats);
		return $cat;
	}
}

if (!function_exists("l7ht_fixture_vars")) {
	function l7ht_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		$catalog = l7ht_default_ndpi_catalog();
		$protos = isset($opts["ndpi_protos"]) && is_array($opts["ndpi_protos"])
		    ? $opts["ndpi_protos"] : $catalog["protocols"];
		$cats = isset($opts["ndpi_cats"]) && is_array($opts["ndpi_cats"])
		    ? $opts["ndpi_cats"] : $catalog["categories"];
		$protos = array_values($protos);
		$cats = array_values($cats);
		sort($protos);
		sort($cats);
		return array(
			"test_domain" => isset($opts["test_domain"]) ? (string)$opts["test_domain"] : "",
			"test_src_ip" => isset($opts["test_src_ip"]) ? (string)$opts["test_src_ip"] : "",
			"test_ndpi_app" => isset($opts["test_ndpi_app"]) ? (string)$opts["test_ndpi_app"] : "",
			"test_ndpi_cat" => isset($opts["test_ndpi_cat"]) ? (string)$opts["test_ndpi_cat"] : "",
			"test_results" => array_key_exists("test_results", $opts) ? $opts["test_results"] : null,
			"ndpi_protos" => $protos,
			"ndpi_cats" => $cats,
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
		);
	}
}

if (!function_exists("l7ht_apply_fixtures")) {
	function l7ht_apply_fixtures($opts)
	{
		global $input_errors;
		if (isset($opts["l7_t_fixture"]) && is_array($opts["l7_t_fixture"])) {
			$GLOBALS["l7ht_l7_t_fixture"] = $opts["l7_t_fixture"];
		} else {
			unset($GLOBALS["l7ht_l7_t_fixture"]);
		}
		$vars = l7ht_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		return $vars;
	}
}

if (!function_exists("l7ht_prepare_view_slice")) {
	function l7ht_prepare_view_slice($path, $load_form)
	{
		static $cache = array();
		$key = $path . ":" . ($load_form ? "1" : "0");
		if (isset($cache[$key])) {
			return $cache[$key];
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
		if ($load_form) {
			$slice = str_replace(
				'require_once("classes/Form.class.php");',
				'l7ht_load_form_classes();',
				$slice
			);
		}
		$cache[$key] = $slice;
		return $slice;
	}
}

if (!function_exists("l7ht_render_file")) {
	function l7ht_render_file($path, $opts, $load_form)
	{
		$vars = l7ht_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		if ($load_form) {
			l7ht_load_form_classes();
		}
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7ht_prepare_view_slice($path, $load_form));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7ht_render")) {
	function l7ht_render($opts)
	{
		return l7ht_render_file(L7HT_TEST, $opts, true);
	}
}

if (!function_exists("l7ht_render_baseline")) {
	function l7ht_render_baseline($opts)
	{
		return l7ht_render_file(L7HT_BASELINE, $opts, false);
	}
}

if (!function_exists("l7ht_fixture_verdict")) {
	function l7ht_fixture_verdict($action, $enforce, $extra_rows)
	{
		$rows = is_array($extra_rows) ? $extra_rows : array();
		$label = strtoupper($action);
		if ($action === "block") {
			$label = "BLOQUEADO";
		} elseif ($action === "allow") {
			$label = "PERMITIDO";
		} elseif ($action === "monitor") {
			$label = "MONITORIZADO";
		}
		$rows[] = array(
			"type" => "verdict",
			"action" => $action,
			"label" => $label,
			"reason" => "fixture reason " . $action,
			"detail" => "fixture detail " . $action,
			"enforce" => (bool)$enforce,
		);
		return array(
			"results" => $rows,
			"resolved_ips" => array(),
			"mode" => $enforce ? "enforce" : "monitor",
		);
	}
}
