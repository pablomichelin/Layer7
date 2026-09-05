<?php
/**
 * Harness V11 — render isolado da view de layer7_removal.php (sem remoção real/rede).
 */

if (!defined("L7HR_ROOT")) {
	define("L7HR_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HR_REMOVAL")) {
	define(
		"L7HR_REMOVAL",
		L7HR_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php"
	);
}
if (!defined("L7HR_BASELINE")) {
	define(
		"L7HR_BASELINE",
		L7HR_ROOT . "/tests/functional/baseline-v11-removal/layer7_removal.php"
	);
}
if (!defined("L7HR_FORM_DIR")) {
	define("L7HR_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
}

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

$GLOBALS["l7hr_form_noise"] = 0;

if (!function_exists("l7hr_form_php83_noise")) {
	function l7hr_form_php83_noise($errno, $errstr, $errfile, $errline)
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
					$GLOBALS["l7hr_form_noise"]++;
					return true;
				}
			}
		}
		return false;
	}
	set_error_handler("l7hr_form_php83_noise");
}

if (!function_exists("l7hr_load_form_classes")) {
	function l7hr_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HR_FORM_DIR;
		foreach (array(
			$base . "/Form/Element.class.php",
			$base . "/Form/Input.class.php",
			$base . "/Form/Button.class.php",
			$base . "/Form/StaticText.class.php",
			$base . "/Form/Textarea.class.php",
			$base . "/Form/Select.class.php",
			$base . "/Form/Checkbox.class.php",
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
		if (isset($GLOBALS["l7hr_l7_t_fixture"]) && is_array($GLOBALS["l7hr_l7_t_fixture"]) &&
		    array_key_exists($s, $GLOBALS["l7hr_l7_t_fixture"])) {
			return (string)$GLOBALS["l7hr_l7_t_fixture"][$s];
		}
		return $s;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($tab)
	{
		echo "<!-- L7HR_TABS:" . htmlspecialchars((string)$tab) . " -->\n";
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

if (!function_exists("l7hr_fixture_vars")) {
	function l7hr_fixture_vars($opts)
	{
		$opts = is_array($opts) ? $opts : array();
		return array(
			"pkg_installed" => array_key_exists("pkg_installed", $opts) ? (bool)$opts["pkg_installed"] : true,
			"job_running" => array_key_exists("job_running", $opts) ? (bool)$opts["job_running"] : false,
			"log_rm" => isset($opts["log_rm"]) ? (string)$opts["log_rm"] : "/tmp/layer7_pkg_rm.log",
			"input_errors" => isset($opts["input_errors"]) && is_array($opts["input_errors"])
			    ? $opts["input_errors"] : array(),
		);
	}
}

if (!function_exists("l7hr_apply_fixtures")) {
	function l7hr_apply_fixtures($opts)
	{
		global $input_errors;
		if (isset($opts["l7_t_fixture"]) && is_array($opts["l7_t_fixture"])) {
			$GLOBALS["l7hr_l7_t_fixture"] = $opts["l7_t_fixture"];
		} else {
			unset($GLOBALS["l7hr_l7_t_fixture"]);
		}
		$_SERVER["REQUEST_URI"] = "/packages/layer7/layer7_removal.php";
		$vars = l7hr_fixture_vars($opts);
		$input_errors = $vars["input_errors"];
		return $vars;
	}
}

if (!function_exists("l7hr_prepare_view_slice")) {
	function l7hr_prepare_view_slice($path, $load_form)
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
				'l7hr_load_form_classes();',
				$slice
			);
		}
		$cache[$key] = $slice;
		return $slice;
	}
}

if (!function_exists("l7hr_render_file")) {
	function l7hr_render_file($path, $opts, $load_form)
	{
		$vars = l7hr_apply_fixtures($opts);
		extract($vars, EXTR_OVERWRITE);
		if ($load_form) {
			l7hr_load_form_classes();
		}
		$prev = error_reporting(E_ERROR | E_PARSE);
		ob_start();
		eval(l7hr_prepare_view_slice($path, $load_form));
		$html = ob_get_clean();
		error_reporting($prev);
		return $html;
	}
}

if (!function_exists("l7hr_render")) {
	function l7hr_render($opts)
	{
		return l7hr_render_file(L7HR_REMOVAL, $opts, true);
	}
}

if (!function_exists("l7hr_render_baseline")) {
	function l7hr_render_baseline($opts)
	{
		return l7hr_render_file(L7HR_BASELINE, $opts, false);
	}
}
