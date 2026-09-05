<?php
/**
 * Bootstrap isolado — renderiza layer7_allowlist.php actual.
 * Não carrega guiconfig.inc nem /usr/local/pkg/layer7.inc.
 * Stubs nunca persistem efeitos reais em PF/daemon.
 */

if (!defined("L7HA_ROOT")) {
	define("L7HA_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HA_ALLOWLIST")) {
	define(
		"L7HA_ALLOWLIST",
		L7HA_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_allowlist.php"
	);
}
if (!defined("L7HA_BASELINE")) {
	define(
		"L7HA_BASELINE",
		L7HA_ROOT . "/tests/functional/baseline-v5-allowlist/layer7_allowlist.php"
	);
}
if (!defined("L7HA_FORM_DIR")) {
	define("L7HA_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
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

$GLOBALS["l7ha_form_noise"] = 0;
$GLOBALS["l7ha_form_noise_unexpected"] = array();
$GLOBALS["l7ha_save_calls"] = 0;
$GLOBALS["l7ha_apply_calls"] = 0;
$GLOBALS["l7ha_reload_calls"] = 0;
$GLOBALS["l7ha_filter_calls"] = 0;
$GLOBALS["l7ha_save_result"] = true;
$GLOBALS["l7ha_apply_result"] = 3;

if (!function_exists("l7ha_form_php83_noise")) {
	function l7ha_form_php83_noise($errno, $errstr, $errfile, $errline)
	{
		$known = array(
			"Undefined variable \$hidden",
			"Undefined variable \$help",
			"Undefined variable \$title",
			"Undefined variable \$target",
			"Undefined array key \"id\"",
			"Undefined array key \"type\"",
		);
		$in_vendor = strpos((string)$errfile, "/vendor/pfsense-form/") !== false;
		if ($in_vendor && ($errno === E_WARNING || $errno === E_NOTICE)) {
			foreach ($known as $k) {
				if (strpos((string)$errstr, $k) !== false) {
					$GLOBALS["l7ha_form_noise"]++;
					return true;
				}
			}
			$GLOBALS["l7ha_form_noise_unexpected"][] =
				(string)$errstr . " @ " . (string)$errfile . ":" . (int)$errline;
			return false;
		}
		return false;
	}
	set_error_handler("l7ha_form_php83_noise");
}

if (!function_exists("l7ha_load_form_classes")) {
	function l7ha_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HA_FORM_DIR;
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
			if (!is_file($f)) {
				fwrite(STDERR, "FAIL Form pin em falta: {$f}\n");
				exit(1);
			}
			require_once $f;
		}
		$loaded = true;
	}
}

if (!function_exists("l7_t")) {
	function l7_t($key)
	{
		return $key;
	}
}

if (!function_exists("l7ha_extract_php_function")) {
	function l7ha_extract_php_function($src, $name)
	{
		$needle = "function {$name}(";
		$pos = strpos($src, $needle);
		if ($pos === false) {
			return null;
		}
		$brace = strpos($src, "{", $pos);
		if ($brace === false) {
			return null;
		}
		$depth = 0;
		$len = strlen($src);
		for ($i = $brace; $i < $len; $i++) {
			if ($src[$i] === "{") {
				$depth++;
			} elseif ($src[$i] === "}") {
				$depth--;
				if ($depth === 0) {
					return substr($src, $pos, $i - $pos + 1);
				}
			}
		}
		return null;
	}
}

if (!function_exists("l7ha_load_validators_from_inc")) {
	function l7ha_load_validators_from_inc()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$inc = L7HA_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
		if (!is_file($inc)) {
			fwrite(STDERR, "FAIL layer7.inc em falta para validadores reais\n");
			exit(1);
		}
		$src = (string)file_get_contents($inc);
		foreach (array(
			"layer7_ipv4_valid",
			"layer7_ipv6_valid",
			"layer7_cidr_valid",
			"layer7_cidr6_valid",
		) as $fn) {
			if (function_exists($fn)) {
				continue;
			}
			$code = l7ha_extract_php_function($src, $fn);
			if ($code === null) {
				fwrite(STDERR, "FAIL nao foi possivel extrair {$fn} de layer7.inc\n");
				exit(1);
			}
			eval($code);
		}
		$loaded = true;
	}
}

if (!function_exists("l7ha_strip_functions_if_defined")) {
	function l7ha_strip_functions_if_defined($raw, array $names)
	{
		foreach ($names as $name) {
			if (!function_exists($name)) {
				continue;
			}
			$needle = "function {$name}(";
			$pos = strpos($raw, $needle);
			if ($pos === false) {
				continue;
			}
			$brace = strpos($raw, "{", $pos);
			if ($brace === false) {
				continue;
			}
			$depth = 0;
			$len = strlen($raw);
			for ($i = $brace; $i < $len; $i++) {
				if ($raw[$i] === "{") {
					$depth++;
				} elseif ($raw[$i] === "}") {
					$depth--;
					if ($depth === 0) {
						$raw = substr($raw, 0, $pos)
							. "/* harness: {$name} ja definido; corpo omitido */\n"
							. substr($raw, $i + 1);
						break;
					}
				}
			}
		}
		return $raw;
	}
}

if (!function_exists("l7ha_form_submit_payload")) {
	function l7ha_form_submit_payload($html)
	{
		$dom = new DOMDocument();
		if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html) === false) {
			return null;
		}
		$xpath = new DOMXPath($dom);
		$form = $xpath->query("//form[contains(@action,'layer7_allowlist.php')]")->item(0);
		if (!$form instanceof DOMElement) {
			return null;
		}
		$fields = array();
		foreach ($xpath->query(".//input[@name]|.//textarea[@name]|.//select[@name]", $form) as $el) {
			if (!$el instanceof DOMElement) {
				continue;
			}
			$name = $el->getAttribute("name");
			if ($name === "") {
				continue;
			}
			if ($el->tagName === "textarea") {
				$fields[$name] = $el->textContent;
			} else {
				$fields[$name] = $el->getAttribute("value");
			}
		}
		$named_submit = $xpath->query(
			".//button[@type='submit' and @name!='']|.//input[@type='submit' and @name!='']",
			$form
		);
		ksort($fields);
		return array(
			"method" => strtolower($form->getAttribute("method") ?: "get"),
			"action" => $form->getAttribute("action"),
			"fields" => $fields,
			"named_submit" => ($named_submit !== false && $named_submit->length > 0),
		);
	}
}

if (!function_exists("layer7_load_or_default")) {
	function layer7_load_or_default()
	{
		return isset($GLOBALS["l7ha_data"]) && is_array($GLOBALS["l7ha_data"])
			? $GLOBALS["l7ha_data"]
			: array("layer7" => array("dst_allowlist" => array()));
	}
}

if (!function_exists("layer7_dst_allowlist_seed_entries")) {
	function layer7_dst_allowlist_seed_entries()
	{
		return isset($GLOBALS["l7ha_seed"]) && is_array($GLOBALS["l7ha_seed"])
			? $GLOBALS["l7ha_seed"] : array();
	}
}

if (!function_exists("layer7_save_json")) {
	function layer7_save_json($data)
	{
		$GLOBALS["l7ha_save_calls"]++;
		$GLOBALS["l7ha_last_saved"] = $data;
		if (!empty($GLOBALS["l7ha_save_result"])) {
			$GLOBALS["l7ha_data"] = $data;
			return true;
		}
		return false;
	}
}

if (!function_exists("layer7_dst_allowlist_apply_to_pf")) {
	function layer7_dst_allowlist_apply_to_pf()
	{
		$GLOBALS["l7ha_apply_calls"]++;
		return (int)$GLOBALS["l7ha_apply_result"];
	}
}

if (!function_exists("layer7_signal_reload")) {
	function layer7_signal_reload()
	{
		$GLOBALS["l7ha_reload_calls"]++;
		return true;
	}
}

if (!function_exists("layer7_filter_configure_safe")) {
	function layer7_filter_configure_safe()
	{
		$GLOBALS["l7ha_filter_calls"]++;
		return true;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HA_TABS " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_styles")) {
	function layer7_render_styles()
	{
		echo "<!-- L7HA_STYLES stub -->\n";
	}
}

if (!function_exists("layer7_render_footer")) {
	function layer7_render_footer()
	{
		echo "<!-- L7HA_FOOTER stub -->\n";
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
	function print_info_box($msg, $class = "success")
	{
		echo '<div class="alert alert-' . htmlspecialchars((string)$class) . '" id="l7-savemsg">';
		echo htmlspecialchars((string)$msg);
		echo "</div>\n";
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

if (!function_exists("l7ha_data")) {
	function l7ha_data($entries = array())
	{
		return array("layer7" => array("dst_allowlist" => $entries));
	}
}

if (!function_exists("l7ha_prepare_source")) {
	function l7ha_prepare_source($path)
	{
		$loaded = file_get_contents($path);
		if (!is_string($loaded) || $loaded === "") {
			fwrite(STDERR, "FAIL nao foi possivel ler: {$path}\n");
			exit(1);
		}
		if (strncmp($loaded, "<?php", 5) === 0) {
			$loaded = substr($loaded, 5);
		}
		$loaded = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $loaded);
		$loaded = str_replace('include("head.inc");', 'echo "<!-- L7HA_HEAD -->\n";', $loaded);
		$loaded = str_replace('include("foot.inc");', 'echo "<!-- L7HA_FOOT -->\n";', $loaded);
		$loaded = str_replace('layer7_render_styles();', 'layer7_render_styles();', $loaded);
		$loaded = str_replace('layer7_render_footer();', 'layer7_render_footer();', $loaded);
		return $loaded;
	}
}

if (!function_exists("l7ha_view_source")) {
	function l7ha_view_source()
	{
		static $raw = null;
		if ($raw === null) {
			$raw = l7ha_prepare_source(L7HA_ALLOWLIST);
		}
		return l7ha_strip_functions_if_defined(
			"global \$input_errors, \$savemsg;\n" . $raw,
			array("l7_allow_normalize_input", "l7_allow_classify")
		);
	}
}

if (!function_exists("l7ha_baseline_view_source")) {
	function l7ha_baseline_view_source()
	{
		static $raw = null;
		if ($raw === null) {
			$raw = l7ha_prepare_source(L7HA_BASELINE);
		}
		return l7ha_strip_functions_if_defined(
			"global \$input_errors, \$savemsg;\n" . $raw,
			array("l7_allow_normalize_input", "l7_allow_classify")
		);
	}
}

if (!function_exists("l7ha_reset_side_effects")) {
	function l7ha_reset_side_effects()
	{
		$GLOBALS["l7ha_save_calls"] = 0;
		$GLOBALS["l7ha_apply_calls"] = 0;
		$GLOBALS["l7ha_reload_calls"] = 0;
		$GLOBALS["l7ha_filter_calls"] = 0;
		$GLOBALS["l7ha_save_result"] = true;
		$GLOBALS["l7ha_apply_result"] = 3;
		unset($GLOBALS["l7ha_last_saved"]);
	}
}

if (!function_exists("l7ha_apply_opts")) {
	function l7ha_apply_opts($opts)
	{
		global $input_errors, $savemsg;
		l7ha_reset_side_effects();
		$input_errors = array();
		$savemsg = "";
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		$_POST = isset($opts["post"]) && is_array($opts["post"]) ? $opts["post"] : array();
		$_SERVER["REQUEST_URI"] = "/packages/layer7/layer7_allowlist.php";
		$GLOBALS["l7ha_data"] = isset($opts["data"]) ? $opts["data"] : l7ha_data(array());
		$GLOBALS["l7ha_seed"] = isset($opts["seed"]) && is_array($opts["seed"]) ? $opts["seed"] : array();
		if (array_key_exists("save_result", $opts)) {
			$GLOBALS["l7ha_save_result"] = (bool)$opts["save_result"];
		}
		if (array_key_exists("apply_result", $opts)) {
			$GLOBALS["l7ha_apply_result"] = (int)$opts["apply_result"];
		}
	}
}

if (!function_exists("l7ha_render")) {
	function l7ha_render($opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7ha_load_form_classes();
		l7ha_load_validators_from_inc();
		l7ha_apply_opts($opts);
		ob_start();
		eval(l7ha_view_source());
		return ob_get_clean();
	}
}

if (!function_exists("l7ha_render_baseline")) {
	function l7ha_render_baseline($opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7ha_load_validators_from_inc();
		l7ha_apply_opts($opts);
		ob_start();
		eval(l7ha_baseline_view_source());
		return ob_get_clean();
	}
}
