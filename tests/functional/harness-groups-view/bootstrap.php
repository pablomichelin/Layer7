<?php
/**
 * Bootstrap isolado — renderiza layer7_groups.php actual.
 * Não carrega guiconfig.inc nem /usr/local/pkg/layer7.inc.
 * Form_* = pin oficial (vendor Devices + Textarea/Select no mesmo commit).
 * Stubs nunca persistem. save_json devolve false (evita redirect/exit).
 */

if (!defined("L7HG_ROOT")) {
	define("L7HG_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HG_GROUPS")) {
	define(
		"L7HG_GROUPS",
		L7HG_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php"
	);
}
if (!defined("L7HG_FORM_DIR")) {
	define("L7HG_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
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

$GLOBALS["l7hg_form_noise"] = 0;
$GLOBALS["l7hg_form_noise_unexpected"] = array();

if (!function_exists("l7hg_form_php83_noise")) {
	function l7hg_form_php83_noise($errno, $errstr, $errfile, $errline)
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
					$GLOBALS["l7hg_form_noise"]++;
					return true;
				}
			}
			$GLOBALS["l7hg_form_noise_unexpected"][] =
				(string)$errstr . " @ " . (string)$errfile . ":" . (int)$errline;
			return false;
		}
		return false;
	}
	set_error_handler("l7hg_form_php83_noise");
}

if (!function_exists("l7hg_load_form_classes")) {
	function l7hg_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HG_FORM_DIR;
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

if (!function_exists("layer7_device_mac_valid")) {
	function layer7_device_mac_valid($mac)
	{
		return (bool)preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', strtolower((string)$mac));
	}
}

if (!function_exists("layer7_normalize_macs")) {
	function layer7_normalize_macs($raw, $max = 64)
	{
		$lines = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string)$raw);
		$out = array();
		foreach ($lines as $ln) {
			$m = strtolower(trim((string)$ln));
			if ($m !== "" && layer7_device_mac_valid($m) && count($out) < $max) {
				$out[$m] = true;
			}
		}
		return array_keys($out);
	}
}

if (!function_exists("layer7_group_id_valid")) {
	function layer7_group_id_valid($id)
	{
		return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
			&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
	}
}

if (!function_exists("layer7_parse_cidr_textarea")) {
	function layer7_parse_cidr_textarea($text)
	{
		$out = array();
		foreach (preg_split('/[\r\n]+/', trim((string)$text)) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === '#') {
				continue;
			}
			if (strpos($line, "/") !== false && count($out) < 16) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_ip_textarea")) {
	function layer7_parse_ip_textarea($text)
	{
		$out = array();
		foreach (preg_split('/[\r\n]+/', trim((string)$text)) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === '#') {
				continue;
			}
			if (filter_var($line, FILTER_VALIDATE_IP) && count($out) < 64) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_load_or_default")) {
	function layer7_load_or_default()
	{
		return isset($GLOBALS["l7hg_data"]) && is_array($GLOBALS["l7hg_data"])
			? $GLOBALS["l7hg_data"] : array("layer7" => array("groups" => array(), "policies" => array()));
	}
}

if (!function_exists("layer7_device_inventory")) {
	function layer7_device_inventory()
	{
		return isset($GLOBALS["l7hg_inventory"]) && is_array($GLOBALS["l7hg_inventory"])
			? $GLOBALS["l7hg_inventory"] : array();
	}
}

if (!function_exists("layer7_resolve_macs_to_ips")) {
	function layer7_resolve_macs_to_ips($macs)
	{
		$map = isset($GLOBALS["l7hg_mac_ips"]) && is_array($GLOBALS["l7hg_mac_ips"])
			? $GLOBALS["l7hg_mac_ips"] : array();
		$out = array();
		foreach ((array)$macs as $m) {
			$m = strtolower((string)$m);
			if (isset($map[$m])) {
				$out[] = $map[$m];
			}
		}
		return $out;
	}
}

if (!function_exists("layer7_save_json")) {
	function layer7_save_json($data)
	{
		return false;
	}
}

if (!function_exists("layer7_pf_config_resync")) {
	function layer7_pf_config_resync()
	{
		return true;
	}
}

if (!function_exists("layer7_devices_resync")) {
	function layer7_devices_resync()
	{
		return isset($GLOBALS["l7hg_resync_n"]) ? (int)$GLOBALS["l7hg_resync_n"] : 0;
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HG_TABS " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_policies_subnav")) {
	function layer7_render_policies_subnav($active = "")
	{
		echo "<!-- L7HG_SUBNAV " . htmlspecialchars((string)$active) . " -->\n";
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

if (!function_exists("l7hg_group")) {
	function l7hg_group($id, $opts = array())
	{
		$g = array(
			"id" => $id,
			"name" => isset($opts["name"]) ? $opts["name"] : $id,
		);
		if (!empty($opts["cidrs"])) {
			$g["cidrs"] = $opts["cidrs"];
		}
		if (!empty($opts["hosts"])) {
			$g["hosts"] = $opts["hosts"];
		}
		if (!empty($opts["device_macs"])) {
			$g["device_macs"] = $opts["device_macs"];
		}
		if (isset($opts["device_ips"])) {
			$g["device_ips"] = $opts["device_ips"];
		}
		return $g;
	}
}

if (!function_exists("l7hg_data")) {
	function l7hg_data($groups, $policies = array())
	{
		return array("layer7" => array("groups" => $groups, "policies" => $policies));
	}
}

if (!function_exists("l7hg_strip_product_helper_if_defined")) {
	function l7hg_strip_product_helper_if_defined($raw)
	{
		if (!function_exists("layer7_group_policy_count")) {
			return $raw;
		}
		$needle = "function layer7_group_policy_count(";
		$pos = strpos($raw, $needle);
		if ($pos === false) {
			return $raw;
		}
		$brace = strpos($raw, "{", $pos);
		if ($brace === false) {
			return $raw;
		}
		$depth = 0;
		$len = strlen($raw);
		for ($i = $brace; $i < $len; $i++) {
			if ($raw[$i] === "{") {
				$depth++;
			} elseif ($raw[$i] === "}") {
				$depth--;
				if ($depth === 0) {
					return substr($raw, 0, $pos)
						. "/* harness: helper do produto ja definido; corpo nao reimplementado */\n"
						. substr($raw, $i + 1);
				}
			}
		}
		return $raw;
	}
}

if (!function_exists("l7hg_view_source")) {
	function l7hg_view_source()
	{
		static $raw = null;
		if ($raw === null) {
			$loaded = file_get_contents(L7HG_GROUPS);
			if (!is_string($loaded) || $loaded === "") {
				fwrite(STDERR, "FAIL nao foi possivel ler a view\n");
				exit(1);
			}
			if (strncmp($loaded, "<?php", 5) === 0) {
				$loaded = substr($loaded, 5);
			}
			$loaded = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $loaded);
			$loaded = str_replace('include("head.inc");', 'echo "<!-- L7HG_HEAD -->\n";', $loaded);
			$loaded = str_replace('include("foot.inc");', 'echo "<!-- L7HG_FOOT -->\n";', $loaded);
			$raw = $loaded;
		}
		return l7hg_strip_product_helper_if_defined("global \$input_errors, \$savemsg;\n" . $raw);
	}
}

if (!function_exists("l7hg_render")) {
	function l7hg_render($opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7hg_load_form_classes();
		$input_errors = array();
		$savemsg = "";
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		$_POST = isset($opts["post"]) && is_array($opts["post"]) ? $opts["post"] : array();
		$_SERVER["REQUEST_URI"] = "/packages/layer7/layer7_groups.php";
		$GLOBALS["l7hg_data"] = isset($opts["data"]) ? $opts["data"] : l7hg_data(array());
		$GLOBALS["l7hg_inventory"] = isset($opts["inventory"]) ? $opts["inventory"] : array();
		$GLOBALS["l7hg_mac_ips"] = isset($opts["mac_ips"]) ? $opts["mac_ips"] : array();
		$GLOBALS["l7hg_resync_n"] = isset($opts["resync_n"]) ? (int)$opts["resync_n"] : 2;
		ob_start();
		eval(l7hg_view_source());
		return ob_get_clean();
	}
}
