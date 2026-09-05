<?php
/**
 * Bootstrap isolado — renderiza o layer7_devices.php actual.
 *
 * Não carrega guiconfig.inc nem /usr/local/pkg/layer7.inc.
 * Stubs só de inventário/grupos/mensagens/handlers.
 * Form_* = classes oficiais pinadas (ver vendor/pfsense-form/CITATION.txt).
 * Não é pfSense, não é o appliance, não prova visual no host.
 */

if (!defined("L7H_ROOT")) {
	define("L7H_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7H_DIR")) {
	define("L7H_DIR", __DIR__);
}
if (!defined("L7H_DEVICES")) {
	define(
		"L7H_DEVICES",
		L7H_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php"
	);
}
if (!defined("L7H_FORM_DIR")) {
	define("L7H_FORM_DIR", L7H_DIR . "/vendor/pfsense-form");
}

/* Constantes oficiais de src/etc/inc/globals.inc (não são Form_*). */
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

if (!function_exists("l7h_form_php83_noise")) {
	function l7h_form_php83_noise($errno, $errstr, $errfile, $errline)
	{
		if (strpos((string)$errfile, "/vendor/pfsense-form/") !== false
			&& ($errno === E_WARNING || $errno === E_NOTICE)) {
			return true;
		}
		return false;
	}
	set_error_handler("l7h_form_php83_noise");
}

if (!isset($user_settings) || !is_array($user_settings)) {
	$user_settings = array(
		"webgui" => array(
			"webguileftcolumnhyper" => true,
		),
	);
}

if (!function_exists("l7h_load_form_classes")) {
	function l7h_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7H_FORM_DIR;
		$files = array(
			$base . "/Form/Element.class.php",
			$base . "/Form/Input.class.php",
			$base . "/Form/Button.class.php",
			$base . "/Form/StaticText.class.php",
			$base . "/Form/Group.class.php",
			$base . "/Form/Section.class.php",
			$base . "/Form.class.php",
		);
		foreach ($files as $f) {
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

if (!function_exists("layer7_device_inventory")) {
	function layer7_device_inventory()
	{
		return isset($GLOBALS["l7h_inventory"]) && is_array($GLOBALS["l7h_inventory"])
			? $GLOBALS["l7h_inventory"] : array();
	}
}

if (!function_exists("layer7_load_groups")) {
	function layer7_load_groups()
	{
		return isset($GLOBALS["l7h_groups"]) && is_array($GLOBALS["l7h_groups"])
			? $GLOBALS["l7h_groups"] : array();
	}
}

if (!function_exists("layer7_device_aliases_load")) {
	function layer7_device_aliases_load()
	{
		return isset($GLOBALS["l7h_aliases"]) && is_array($GLOBALS["l7h_aliases"])
			? $GLOBALS["l7h_aliases"] : array();
	}
}

if (!function_exists("layer7_load_or_default")) {
	function layer7_load_or_default()
	{
		return array(
			"layer7" => array(
				"groups" => layer7_load_groups(),
				"device_aliases" => layer7_device_aliases_load(),
			),
		);
	}
}

if (!function_exists("layer7_device_alias_save")) {
	function layer7_device_alias_save($mac, $alias)
	{
		return layer7_device_mac_valid($mac);
	}
}

if (!function_exists("layer7_save_json")) {
	function layer7_save_json($data)
	{
		return true;
	}
}

if (!function_exists("layer7_pf_config_resync")) {
	function layer7_pf_config_resync()
	{
		return true;
	}
}

if (!function_exists("layer7_resolve_macs_to_ips")) {
	function layer7_resolve_macs_to_ips($macs)
	{
		return array();
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HARNESS_TABS " . htmlspecialchars((string)$active) . " -->\n";
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

if (!function_exists("l7h_mac")) {
	function l7h_mac($i)
	{
		$i = (int)$i;
		return sprintf("aa:00:00:%02x:%02x:%02x", ($i >> 16) & 0xff, ($i >> 8) & 0xff, $i & 0xff);
	}
}

if (!function_exists("l7h_ip")) {
	function l7h_ip($i)
	{
		$i = (int)$i;
		$nets = array(
			array(192, 0, 2),
			array(198, 51, 100),
			array(203, 0, 113),
		);
		$net = $nets[($i - 1) % 3];
		$host = 1 + (int)floor(($i - 1) / 3) % 254;
		return $net[0] . "." . $net[1] . "." . $net[2] . "." . $host;
	}
}

if (!function_exists("l7h_inventory")) {
	function l7h_inventory($n, $extra = array())
	{
		$out = array();
		for ($i = 1; $i <= (int)$n; $i++) {
			$mac = l7h_mac($i);
			$online = ($i % 5 === 0) ? "offline" : "online";
			$host = "host-" . $i;
			$alias = ($i === 1) ? "lab-one" : "";
			if ($i === 2) {
				$host = 'host<script>x</script>';
				$alias = 'foo" onclick="x';
			}
			$out[] = array(
				"ip" => l7h_ip($i),
				"mac" => $mac,
				"hostname" => $host,
				"descr" => "",
				"vendor" => ($i === 1) ? "Vendor-RFC" : "",
				"iface" => "lan",
				"online" => $online,
				"source" => "dhcp",
				"alias" => $alias,
			);
		}
		if (!empty($extra)) {
			foreach ($extra as $row) {
				$out[] = $row;
			}
		}
		return $out;
	}
}

if (!function_exists("l7h_groups")) {
	function l7h_groups()
	{
		return array(
			array("id" => "g-lab", "name" => "Lab"),
		);
	}
}

if (!function_exists("l7h_view_source")) {
	function l7h_view_source()
	{
		static $src = null;
		if ($src !== null) {
			return $src;
		}
		if (!is_file(L7H_DEVICES)) {
			fwrite(STDERR, "FAIL view em falta: " . L7H_DEVICES . "\n");
			exit(1);
		}
		$raw = file_get_contents(L7H_DEVICES);
		if (!is_string($raw) || $raw === "") {
			fwrite(STDERR, "FAIL nao foi possivel ler a view\n");
			exit(1);
		}
		if (strncmp($raw, "<?php", 5) === 0) {
			$raw = substr($raw, 5);
		}
		$raw = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $raw);
		$raw = str_replace('include("head.inc");', 'echo "<!-- L7HARNESS_HEAD -->\n";', $raw);
		$raw = str_replace('include("foot.inc");', 'echo "<!-- L7HARNESS_FOOT -->\n";', $raw);
		$src = "global \$input_errors, \$savemsg;\n" . $raw;
		return $src;
	}
}

if (!function_exists("l7h_render")) {
	function l7h_render($opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7h_load_form_classes();
		$input_errors = array();
		$savemsg = "";
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		$_POST = isset($opts["post"]) && is_array($opts["post"]) ? $opts["post"] : array();
		$_SERVER["REQUEST_URI"] = isset($opts["uri"])
			? (string)$opts["uri"]
			: "/packages/layer7/layer7_devices.php";
		$GLOBALS["l7h_inventory"] = isset($opts["inventory"]) && is_array($opts["inventory"])
			? $opts["inventory"] : array();
		$GLOBALS["l7h_groups"] = isset($opts["groups"]) && is_array($opts["groups"])
			? $opts["groups"] : l7h_groups();
		$GLOBALS["l7h_aliases"] = isset($opts["aliases"]) && is_array($opts["aliases"])
			? $opts["aliases"] : array();
		$src = l7h_view_source();
		ob_start();
		eval($src);
		return ob_get_clean();
	}
}

if (!function_exists("l7h_form_inner")) {
	function l7h_form_inner($html, $id)
	{
		$q = preg_quote($id, "/");
		if (!preg_match('/<form\b[^>]*\bid="' . $q . '"[^>]*>(.*?)<\/form>/is', $html, $m)) {
			return "";
		}
		return $m[1];
	}
}

if (!function_exists("l7h_named_attrs")) {
	function l7h_named_attrs($html)
	{
		if ($html === "") {
			return array();
		}
		preg_match_all('/\bname="([^"]+)"/', $html, $m);
		return $m[1];
	}
}

if (!function_exists("l7h_count_names")) {
	function l7h_count_names($names, $needle)
	{
		$n = 0;
		foreach ($names as $nm) {
			if ($nm === $needle || strpos($nm, $needle) === 0) {
				$n++;
			}
		}
		return $n;
	}
}
