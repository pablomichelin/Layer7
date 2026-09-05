<?php
/**
 * Bootstrap isolado — renderiza layer7_exceptions.php (candidato) ou baseline V6a.
 * Handlers/VIP/JS do produto nao sao reimplementados; stubs interceptam persistencia.
 */

if (!defined("L7HE_ROOT")) {
	define("L7HE_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HE_EXCEPTIONS")) {
	define(
		"L7HE_EXCEPTIONS",
		L7HE_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php"
	);
}
if (!defined("L7HE_BASELINE_V6B1")) {
	define(
		"L7HE_BASELINE_V6B1",
		L7HE_ROOT . "/tests/functional/baseline-v6b1-vip/layer7_exceptions.php"
	);
}
if (!defined("L7HE_BASELINE_V6B2A")) {
	define(
		"L7HE_BASELINE_V6B2A",
		L7HE_ROOT . "/tests/functional/baseline-v6b2a-dhcp/layer7_exceptions.php"
	);
}
if (!defined("L7HE_BASELINE_V6B2B")) {
	define(
		"L7HE_BASELINE_V6B2B",
		L7HE_ROOT . "/tests/functional/baseline-v6b2b-vip-import/layer7_exceptions.php"
	);
}
if (!defined("L7HE_BASELINE_V6B2A_SHA256")) {
	define(
		"L7HE_BASELINE_V6B2A_SHA256",
		"e3d216918399a3f2a54538f425e197d24b15ae2d4cd34758aa9801e7ccdfa7ea"
	);
}
if (!defined("L7HE_BASELINE_V6B2B_SHA256")) {
	define(
		"L7HE_BASELINE_V6B2B_SHA256",
		"c72a5db5c4d61f8c3dbb0405a6932707664f90f929a4020686123e97ecb64754"
	);
}
if (!defined("L7HE_BASELINE_V6B1_SHA256")) {
	define(
		"L7HE_BASELINE_V6B1_SHA256",
		"b0efcd8be554147c0594ad6f341daf3bdf37256f5f8b50f483da0ec71b7669cc"
	);
}
if (!defined("L7HE_BASELINE")) {
	define(
		"L7HE_BASELINE",
		L7HE_ROOT . "/tests/functional/baseline-v6a-exceptions/layer7_exceptions.php"
	);
}
if (!defined("L7HE_FORM_DIR")) {
	define("L7HE_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
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

/* Ambiente pfSense minimo — layer7_real_interface_name (layer7.inc) usa estas APIs. */
if (!function_exists("get_real_interface")) {
	function get_real_interface($ifid)
	{
		$map = array(
			"lan" => "em0",
			"wan" => "em1",
		);
		$ifid = trim((string)$ifid);
		return isset($map[$ifid]) ? $map[$ifid] : "";
	}
}
if (!function_exists("convert_friendly_interface_to_real_interface_name")) {
	function convert_friendly_interface_to_real_interface_name($ifid)
	{
		return get_real_interface($ifid);
	}
}

$GLOBALS["l7he_form_noise"] = 0;
$GLOBALS["l7he_form_noise_unexpected"] = array();
$GLOBALS["l7he_save_result"] = false;
$GLOBALS["l7he_save_calls"] = array();
$GLOBALS["l7he_resync_calls"] = array();

require_once __DIR__ . "/inc-pure.php";

if (!function_exists("l7he_form_php83_noise")) {
	function l7he_form_php83_noise($errno, $errstr, $errfile, $errline)
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
					$GLOBALS["l7he_form_noise"]++;
					return true;
				}
			}
			return false;
		}
		return false;
	}
	set_error_handler("l7he_form_php83_noise");
}

if (!function_exists("l7he_load_form_classes")) {
	function l7he_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HE_FORM_DIR;
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
		if (isset($GLOBALS["l7he_l7_t_fixture"]) && is_array($GLOBALS["l7he_l7_t_fixture"]) &&
		    array_key_exists($key, $GLOBALS["l7he_l7_t_fixture"])) {
			return (string)$GLOBALS["l7he_l7_t_fixture"][$key];
		}
		return $key;
	}
}

if (!function_exists("layer7_load_or_default")) {
	function layer7_load_or_default()
	{
		return isset($GLOBALS["l7he_data"]) && is_array($GLOBALS["l7he_data"])
			? $GLOBALS["l7he_data"] : array("layer7" => array("exceptions" => array()));
	}
}

if (!function_exists("layer7_save_json")) {
	function layer7_save_json($data)
	{
		$GLOBALS["l7he_save_calls"][] = $data;
		if (!empty($GLOBALS["l7he_save_result"])) {
			$GLOBALS["l7he_data"] = $data;
		}
		return !empty($GLOBALS["l7he_save_result"]);
	}
}

if (!function_exists("layer7_pf_config_resync")) {
	function layer7_pf_config_resync($force = false)
	{
		$GLOBALS["l7he_resync_calls"][] = $force;
		return true;
	}
}

if (!function_exists("layer7_get_pfsense_interfaces")) {
	function layer7_get_pfsense_interfaces()
	{
		return array(
			array("ifid" => "lan", "real" => "em0", "descr" => "LAN"),
			array("ifid" => "wan", "real" => "em1", "descr" => "WAN"),
		);
	}
}

/* DNS mode efectivo depende de Unbound/sync — fora do escopo do harness de render. */
if (!function_exists("layer7_vip_dns_mode_get")) {
	function layer7_vip_dns_mode_get($data = null)
	{
		if (isset($GLOBALS["l7he_vip_dns_mode_fixture"])) {
			return (string)$GLOBALS["l7he_vip_dns_mode_fixture"];
		}
		return "";
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HE_TABS " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_policies_subnav")) {
	function layer7_render_policies_subnav($active = "")
	{
		echo "<!-- L7HE_SUBNAV " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_styles")) {
	function layer7_render_styles()
	{
		echo "<!-- L7HE_STYLES -->\n";
	}
}

if (!function_exists("layer7_render_footer")) {
	function layer7_render_footer()
	{
		echo "<!-- L7HE_FOOTER -->\n";
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

if (!function_exists("l7he_exc")) {
	function l7he_exc($id, $opts = array())
	{
		$e = array(
			"id" => $id,
			"enabled" => array_key_exists("enabled", $opts) ? (bool)$opts["enabled"] : true,
			"priority" => isset($opts["priority"]) ? (int)$opts["priority"] : 500,
			"action" => isset($opts["action"]) ? (string)$opts["action"] : "allow",
		);
		if (!empty($opts["hosts"])) {
			$e["hosts"] = $opts["hosts"];
		}
		if (!empty($opts["host"])) {
			$e["host"] = $opts["host"];
		}
		if (!empty($opts["cidrs"])) {
			$e["cidrs"] = $opts["cidrs"];
		}
		if (!empty($opts["cidr"])) {
			$e["cidr"] = $opts["cidr"];
		}
		if (!empty($opts["interfaces"])) {
			$e["interfaces"] = $opts["interfaces"];
		}
		return $e;
	}
}

if (!function_exists("l7he_data")) {
	function l7he_data($exceptions)
	{
		return array("layer7" => array("exceptions" => $exceptions));
	}
}

if (!function_exists("l7he_vip_data")) {
	function l7he_vip_data($entries, $exceptions_extra = array(), $opts = array())
	{
		$hosts = array();
		$cidrs = array();
		$labels = array();
		foreach ((array)$entries as $row) {
			$target = trim((string)($row["target"] ?? ""));
			$desc = (string)($row["description"] ?? "");
			if ($target === "") {
				continue;
			}
			if (layer7_ip_valid($target)) {
				$hosts[] = $target;
			} elseif (layer7_cidr_any_valid($target)) {
				$cidrs[] = $target;
			}
			if ($desc !== "") {
				$labels[$target] = $desc;
			}
		}
		$vip_exc = array(
			"id" => layer7_vip_exception_id(),
			"enabled" => true,
			"priority" => 9000,
			"action" => "allow",
			"managed_by" => "profiles",
		);
		if (!empty($hosts)) {
			$vip_exc["hosts"] = array_values(array_unique($hosts));
		}
		if (!empty($cidrs)) {
			$vip_exc["cidrs"] = array_values(array_unique($cidrs));
		}
		if (!empty($opts["source_groups"]) && is_array($opts["source_groups"])) {
			$vip_exc["source_groups"] = array_values($opts["source_groups"]);
		}
		$exceptions = array($vip_exc);
		foreach ((array)$exceptions_extra as $ex) {
			$exceptions[] = $ex;
		}
		$data = array("layer7" => array("exceptions" => $exceptions));
		if (!empty($labels)) {
			$data["layer7"]["vip_meta"] = array("labels" => $labels);
		}
		return $data;
	}
}

if (!function_exists("l7he_vip_build_full")) {
	function l7he_vip_build_full($opts = array())
	{
		$hosts = array();
		$labels = array();
		for ($i = 1; $i <= 32; $i++) {
			$h = "10.0.0." . $i;
			$hosts[] = $h;
			$labels[$h] = "host" . $i;
		}
		$cidrs = array();
		for ($i = 0; $i < 16; $i++) {
			$c = "192.168." . $i . ".0/24";
			$cidrs[] = $c;
			$labels[$c] = "cidr" . $i;
		}
		$vip_exc = array(
			"id" => layer7_vip_exception_id(),
			"enabled" => true,
			"priority" => 9000,
			"action" => "allow",
			"managed_by" => "profiles",
			"hosts" => $hosts,
			"cidrs" => $cidrs,
		);
		if (!empty($opts["source_groups"]) && is_array($opts["source_groups"])) {
			$vip_exc["source_groups"] = array_values($opts["source_groups"]);
		}
		$exceptions = array($vip_exc);
		if (!empty($opts["neighbors"]) && is_array($opts["neighbors"])) {
			foreach ($opts["neighbors"] as $ex) {
				$exceptions[] = $ex;
			}
		}
		return array(
			"layer7" => array(
				"exceptions" => $exceptions,
				"vip_meta" => array("labels" => $labels),
			),
		);
	}
}

if (!function_exists("l7he_vip_dhcp_config")) {
	function l7he_vip_dhcp_config()
	{
		return array(
			"dhcpd" => array(
				"lan" => array(
					"staticmap" => array(
						array("ipaddr" => "192.168.1.50", "mac" => "aa:bb:cc:dd:ee:01", "descr" => "LAN host"),
					),
				),
				"wan" => array(
					"staticmap" => array(
						array("ipaddr" => "192.168.2.50", "mac" => "aa:bb:cc:dd:ee:02", "descr" => "WAN host"),
					),
				),
			),
			"interfaces" => array(
				"lan" => array("descr" => "LAN"),
				"wan" => array("descr" => "WAN"),
			),
		);
	}
}

if (!function_exists("l7he_strip_product_helper_if_defined")) {
	function l7he_strip_product_helper_if_defined($raw)
	{
		if (!function_exists("layer7_exc_target_summary")) {
			return $raw;
		}
		$needle = "function layer7_exc_target_summary(";
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
						. "/* harness: helper do produto ja definido */\n"
						. substr($raw, $i + 1);
				}
			}
		}
		return $raw;
	}
}

if (!function_exists("l7he_view_source")) {
	function l7he_view_source($path)
	{
		static $cache = array();
		if (!isset($cache[$path])) {
			$loaded = file_get_contents($path);
			if (!is_string($loaded) || $loaded === "") {
				fwrite(STDERR, "FAIL nao foi possivel ler {$path}\n");
				exit(1);
			}
			if (strncmp($loaded, "<?php", 5) === 0) {
				$loaded = substr($loaded, 5);
			}
			$loaded = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $loaded);
			$loaded = str_replace('include("head.inc");', 'echo "<!-- L7HE_HEAD -->\n";', $loaded);
			$loaded = str_replace('include("foot.inc");', 'echo "<!-- L7HE_FOOT -->\n";', $loaded);
			$loaded = str_replace('require_once("foot.inc");', 'echo "<!-- L7HE_FOOT -->\n";', $loaded);
			$cache[$path] = $loaded;
		}
		return l7he_strip_product_helper_if_defined("global \$input_errors, \$savemsg;\n" . $cache[$path]);
	}
}

if (!function_exists("l7he_reset_tracking")) {
	function l7he_reset_tracking()
	{
		$GLOBALS["l7he_save_calls"] = array();
		$GLOBALS["l7he_resync_calls"] = array();
		$GLOBALS["l7he_save_result"] = false;
	}
}

if (!function_exists("l7he_effects")) {
	function l7he_effects()
	{
		return array(
			"save_calls" => isset($GLOBALS["l7he_save_calls"]) ? count($GLOBALS["l7he_save_calls"]) : 0,
			"resync_calls" => isset($GLOBALS["l7he_resync_calls"]) ? count($GLOBALS["l7he_resync_calls"]) : 0,
			"resync_forces" => isset($GLOBALS["l7he_resync_calls"]) ? $GLOBALS["l7he_resync_calls"] : array(),
			"save_payloads" => isset($GLOBALS["l7he_save_calls"]) ? $GLOBALS["l7he_save_calls"] : array(),
		);
	}
}

if (!function_exists("l7he_last_save")) {
	function l7he_last_save()
	{
		if (empty($GLOBALS["l7he_save_calls"])) {
			return null;
		}
		return $GLOBALS["l7he_save_calls"][count($GLOBALS["l7he_save_calls"]) - 1];
	}
}

if (!function_exists("l7he_saved_exceptions")) {
	function l7he_saved_exceptions($save = null)
	{
		if ($save === null) {
			$save = l7he_last_save();
		}
		if (!is_array($save) || !isset($save["layer7"]["exceptions"]) || !is_array($save["layer7"]["exceptions"])) {
			return array();
		}
		return $save["layer7"]["exceptions"];
	}
}

if (!function_exists("l7he_exc_by_id")) {
	function l7he_exc_by_id($exceptions, $id)
	{
		foreach ((array)$exceptions as $ex) {
			if (isset($ex["id"]) && (string)$ex["id"] === (string)$id) {
				return $ex;
			}
		}
		return null;
	}
}

if (!function_exists("l7he_render_file")) {
	function l7he_render_file($path, $opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7he_load_form_classes();
		l7he_reset_tracking();
		$input_errors = array();
		$savemsg = isset($opts["savemsg"]) ? (string)$opts["savemsg"] : "";
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		$_POST = isset($opts["post"]) && is_array($opts["post"]) ? $opts["post"] : array();
		$_FILES = isset($opts["files"]) && is_array($opts["files"]) ? $opts["files"] : array();
		if (isset($opts["request_method"])) {
			$_SERVER["REQUEST_METHOD"] = strtoupper((string)$opts["request_method"]);
		} else {
			$_SERVER["REQUEST_METHOD"] = !empty($_POST) ? "POST" : "GET";
		}
		$_SERVER["REQUEST_URI"] = "/packages/layer7/layer7_exceptions.php";
		$GLOBALS["l7he_data"] = isset($opts["data"]) ? $opts["data"] : l7he_data(array());
		$GLOBALS["l7he_save_result"] = !empty($opts["save_result"]);
		if (array_key_exists("vip_dns_mode", $opts)) {
			$GLOBALS["l7he_vip_dns_mode_fixture"] = (string)$opts["vip_dns_mode"];
		} else {
			unset($GLOBALS["l7he_vip_dns_mode_fixture"]);
		}
		if (isset($opts["l7_t_fixture"]) && is_array($opts["l7_t_fixture"])) {
			$GLOBALS["l7he_l7_t_fixture"] = $opts["l7_t_fixture"];
		} else {
			unset($GLOBALS["l7he_l7_t_fixture"]);
		}
		global $config;
		if (isset($opts["config"]) && is_array($opts["config"])) {
			$config = $opts["config"];
		} else {
			$config = array();
		}
		ob_start();
		eval(l7he_view_source($path));
		return ob_get_clean();
	}
}

if (!function_exists("l7he_render")) {
	function l7he_render($opts)
	{
		return l7he_render_file(L7HE_EXCEPTIONS, $opts);
	}
}

/* Contrato V6a (render-parity.php / test_exceptions_payload.js): baseline geral V6a. */
if (!function_exists("l7he_render_baseline")) {
	function l7he_render_baseline($opts)
	{
		return l7he_render_file(L7HE_BASELINE, $opts);
	}
}

/* Baseline VIP V6b1 pinada — separada; usar render-vip-parity.php / test_exceptions_vip_payload.js. */
if (!function_exists("l7he_render_v6b1_baseline")) {
	function l7he_render_v6b1_baseline($opts)
	{
		return l7he_render_file(L7HE_BASELINE_V6B1, $opts);
	}
}

/* Baseline VIP V6b2a (pré-modo DHCP exclusivo) — render-vip-dhcp-parity.php. */
if (!function_exists("l7he_render_v6b2a_baseline")) {
	function l7he_render_v6b2a_baseline($opts)
	{
		return l7he_render_file(L7HE_BASELINE_V6B2A, $opts);
	}
}

/* Baseline VIP V6b2b (pré-modos bulk/import exclusivos) — render-vip-bulk-parity.php. */
if (!function_exists("l7he_render_v6b2b_baseline")) {
	function l7he_render_v6b2b_baseline($opts)
	{
		return l7he_render_file(L7HE_BASELINE_V6B2B, $opts);
	}
}

if (!function_exists("l7he_vip_dhcp_large_config")) {
	function l7he_vip_dhcp_large_config()
	{
		$dhcpd = array();
		$interfaces = array();
		$ifaces = array("lan", "wan", "opt1", "opt2");
		$n = 0;
		foreach ($ifaces as $ifid) {
			$maps = array();
			for ($j = 1; $j <= 8; $j++) {
				$n++;
				$oct = 10 + $n;
				$maps[] = array(
					"ipaddr" => "192.168." . $oct . ".10",
					"mac" => sprintf("aa:bb:cc:dd:ee:%02x", $n),
					"descr" => 'Host "' . $ifid . '" & <b>' . $j . "</b>",
				);
			}
			$dhcpd[$ifid] = array("staticmap" => $maps);
			$interfaces[$ifid] = array("descr" => strtoupper($ifid));
		}
		$dhcpdv6 = array(
			"lan6" => array(
				"staticmap" => array(
					array(
						"ipaddrv6" => "2001:db8::1",
						"mac" => "aa:bb:cc:dd:ee:f1",
						"descr" => "IPv6 lab",
					),
				),
			),
		);
		$interfaces["lan6"] = array("descr" => "LAN6");
		return array("dhcpd" => $dhcpd, "dhcpdv6" => $dhcpdv6, "interfaces" => $interfaces);
	}
}

if (!function_exists("l7he_saved_json")) {
	function l7he_saved_json($save = null)
	{
		if ($save === null) {
			$save = l7he_last_save();
		}
		return is_array($save) ? $save : array();
	}
}
