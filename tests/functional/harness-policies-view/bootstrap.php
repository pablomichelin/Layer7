<?php
/**
 * Bootstrap isolado — renderiza a fonte REAL de layer7_policies.php.
 * Não carrega guiconfig.inc nem /usr/local/pkg/layer7.inc.
 * layer7_load_profiles() devolve [] por defeito (V4-A). Definir
 * $GLOBALS["l7hp_profiles_catalog"] para harness V4-B1 biblioteca.
 * save_json = false: sem persistência, redirect ou PF.
 *
 * Form_* = pin oficial 9363ac5b (vendor Devices + Textarea/Select).
 */

if (!defined("L7HP_ROOT")) {
	define("L7HP_ROOT", dirname(__DIR__, 3));
}
if (!defined("L7HP_CANDIDATE")) {
	define(
		"L7HP_CANDIDATE",
		L7HP_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php"
	);
}
if (!defined("L7HP_BASELINE")) {
	$cap = "/tmp/layer7-v4a-capture/layer7_policies.php";
	$local = __DIR__ . "/source-baseline.php";
	define("L7HP_BASELINE", is_file($local) ? $local : $cap);
}
if (!defined("L7HP_FORM_DIR")) {
	define("L7HP_FORM_DIR", dirname(__DIR__) . "/harness-devices-view/vendor/pfsense-form");
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

$GLOBALS["l7hp_form_noise"] = 0;
$GLOBALS["l7hp_form_noise_unexpected"] = array();

if (!function_exists("l7hp_form_php83_noise")) {
	function l7hp_form_php83_noise($errno, $errstr, $errfile, $errline)
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
					$GLOBALS["l7hp_form_noise"]++;
					return true;
				}
			}
			$GLOBALS["l7hp_form_noise_unexpected"][] =
				(string)$errstr . " @ " . (string)$errfile . ":" . (int)$errline;
			return false;
		}
		return false;
	}
	set_error_handler("l7hp_form_php83_noise");
}

if (!function_exists("l7hp_load_form_classes")) {
	function l7hp_load_form_classes()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$base = L7HP_FORM_DIR;
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
		return $key;
	}
}

if (!function_exists("layer7_entitlements")) {
	function layer7_entitlements()
	{
		$on = !isset($GLOBALS["l7hp_identity"]) || $GLOBALS["l7hp_identity"];
		return array("has_identity" => $on);
	}
}

if (!function_exists("layer7_load_or_default")) {
	function layer7_load_or_default()
	{
		return isset($GLOBALS["l7hp_data"]) && is_array($GLOBALS["l7hp_data"])
			? $GLOBALS["l7hp_data"]
			: array("layer7" => array("policies" => array(), "groups" => array()));
	}
}

if (!function_exists("layer7_load_groups")) {
	function layer7_load_groups()
	{
		$data = layer7_load_or_default();
		return isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"])
			? $data["layer7"]["groups"] : array();
	}
}

if (!function_exists("l7hp_repo_profiles")) {
	function l7hp_repo_profiles()
	{
		static $cache = null;
		if ($cache !== null) {
			return $cache;
		}
		$path = L7HP_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/profiles.json";
		if (!is_file($path)) {
			$cache = array();
			return $cache;
		}
		$j = json_decode((string)file_get_contents($path), true);
		$cache = (is_array($j) && isset($j["profiles"]) && is_array($j["profiles"]))
			? $j["profiles"] : array();
		return $cache;
	}
}

if (!function_exists("layer7_load_profiles")) {
	function layer7_load_profiles($include_hidden = false)
	{
		if (isset($GLOBALS["l7hp_profiles_catalog"]) && is_array($GLOBALS["l7hp_profiles_catalog"])) {
			$visible = $GLOBALS["l7hp_profiles_catalog"];
			if (!$include_hidden) {
				return $visible;
			}
			$hidden = isset($GLOBALS["l7hp_profiles_hidden"]) && is_array($GLOBALS["l7hp_profiles_hidden"])
				? $GLOBALS["l7hp_profiles_hidden"] : array();
			return array_merge($visible, $hidden);
		}
		return array();
	}
}

if (!function_exists("layer7_read_stats")) {
	function layer7_read_stats()
	{
		return isset($GLOBALS["l7hp_stats"]) && is_array($GLOBALS["l7hp_stats"])
			? $GLOBALS["l7hp_stats"] : array();
	}
}

if (!function_exists("layer7_profiles_custom_load")) {
	function layer7_profiles_custom_load()
	{
		return isset($GLOBALS["l7hp_profiles_custom"]) && is_array($GLOBALS["l7hp_profiles_custom"])
			? $GLOBALS["l7hp_profiles_custom"]
			: array("custom" => array(), "overrides" => array());
	}
}

if (!function_exists("layer7_profiles_catalog")) {
	function layer7_profiles_catalog()
	{
		$apps = array();
		$cats = array();
		foreach (l7hp_repo_profiles() as $p) {
			if (!is_array($p)) {
				continue;
			}
			if (isset($p["ndpi_apps"]) && is_array($p["ndpi_apps"])) {
				foreach ($p["ndpi_apps"] as $a) {
					$a = trim((string)$a);
					if ($a !== "") {
						$apps[$a] = true;
					}
				}
			}
			if (isset($p["ndpi_categories"]) && is_array($p["ndpi_categories"])) {
				foreach ($p["ndpi_categories"] as $c) {
					$c = trim((string)$c);
					if ($c !== "") {
						$cats[$c] = true;
					}
				}
			}
		}
		$app_list = array_keys($apps);
		$cat_list = array_keys($cats);
		sort($app_list);
		sort($cat_list);
		return array(
			"apps" => $app_list,
			"categories" => $cat_list,
			"apps_set" => $apps,
			"cats_set" => $cats,
		);
	}
}

if (!function_exists("layer7_profile_custom_id_valid")) {
	function layer7_profile_custom_id_valid($id)
	{
		return is_string($id) && preg_match('/^c-[a-z0-9-]{1,62}$/', $id) === 1;
	}
}

if (!function_exists("layer7_profile_has_override")) {
	function layer7_profile_has_override($profile_id, $custom = null)
	{
		if (!is_array($custom)) {
			$custom = layer7_profiles_custom_load();
		}
		return isset($custom["overrides"][$profile_id]);
	}
}

if (!function_exists("layer7_profile_icon_html")) {
	function layer7_profile_icon_html($icon)
	{
		$icon = is_string($icon) && preg_match('/^fa-[a-z0-9-]{1,40}$/', $icon) ? $icon : "fa-cube";
		return '<i class="fa ' . htmlspecialchars($icon, ENT_QUOTES) . '" aria-hidden="true"></i>';
	}
}

if (!function_exists("layer7_profile_hit_counts")) {
	function layer7_profile_hit_counts($profiles, $stats)
	{
		$out = array();
		if (!is_array($profiles)) {
			return $out;
		}
		foreach ($profiles as $p) {
			$id = (string)($p["id"] ?? "");
			if ($id !== "") {
				$out[$id] = 0;
			}
		}
		return $out;
	}
}

if (!function_exists("layer7_vip_exception_id")) {
	function layer7_vip_exception_id()
	{
		return "vip-isentos";
	}
}

if (!function_exists("layer7_is_managed_vip_exception")) {
	function layer7_is_managed_vip_exception($exception)
	{
		return is_array($exception) &&
		    isset($exception["id"]) &&
		    (string)$exception["id"] === layer7_vip_exception_id();
	}
}

if (!function_exists("layer7_find_vip_exception")) {
	function layer7_find_vip_exception($data)
	{
		if (array_key_exists("l7hp_vip_exception", $GLOBALS)) {
			$v = $GLOBALS["l7hp_vip_exception"];
			return ($v === false || $v === null) ? null : $v;
		}
		if (!is_array($data) || !isset($data["layer7"]["exceptions"]) ||
		    !is_array($data["layer7"]["exceptions"])) {
			return null;
		}
		foreach ($data["layer7"]["exceptions"] as $exc) {
			if (layer7_is_managed_vip_exception($exc)) {
				return $exc;
			}
		}
		return null;
	}
}

if (!function_exists("layer7_ndpi_list")) {
	function layer7_ndpi_list()
	{
		return isset($GLOBALS["l7hp_ndpi"]) && is_array($GLOBALS["l7hp_ndpi"])
			? $GLOBALS["l7hp_ndpi"]
			: array(
				"protocols" => array("BitTorrent", "HTTP", "YouTube"),
				"categories" => array("Media", "Web"),
			);
	}
}

if (!function_exists("layer7_get_pfsense_interfaces")) {
	function layer7_get_pfsense_interfaces()
	{
		return isset($GLOBALS["l7hp_ifaces"]) && is_array($GLOBALS["l7hp_ifaces"])
			? $GLOBALS["l7hp_ifaces"]
			: array(
				array("ifid" => "lan", "real" => "em1", "descr" => "LAN"),
				array("ifid" => "opt1", "real" => "em2", "descr" => "OPT1"),
			);
	}
}

if (!function_exists("layer7_real_interface_name")) {
	function layer7_real_interface_name($ifid)
	{
		foreach (layer7_get_pfsense_interfaces() as $ifc) {
			if ($ifc["ifid"] === $ifid || $ifc["real"] === $ifid) {
				return $ifc["real"];
			}
		}
		return "";
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

if (!function_exists("layer7_policy_id_valid")) {
	function layer7_policy_id_valid($id)
	{
		return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
			&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
	}
}

if (!function_exists("layer7_pf_table_name_valid")) {
	function layer7_pf_table_name_valid($name)
	{
		return is_string($name) && strlen($name) >= 1 && strlen($name) <= 63
			&& preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
	}
}

if (!function_exists("layer7_group_id_valid")) {
	function layer7_group_id_valid($id)
	{
		return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
			&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
	}
}

if (!function_exists("layer7_split_csv_tokens")) {
	function layer7_split_csv_tokens($s, $max, $maxlen)
	{
		$s = trim((string)$s);
		if ($s === "") {
			return array();
		}
		$parts = preg_split('/\s*,\s*/', $s);
		$out = array();
		foreach ($parts as $p) {
			$p = trim($p);
			if ($p === "") {
				continue;
			}
			if (strlen($p) > $maxlen) {
				return null;
			}
			$out[] = $p;
			if (count($out) >= $max) {
				break;
			}
		}
		return $out;
	}
}

if (!function_exists("layer7_parse_ip_textarea")) {
	function layer7_parse_ip_textarea($text)
	{
		$out = array();
		foreach (preg_split('/[\r\n]+/', trim((string)$text)) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			if (filter_var($line, FILTER_VALIDATE_IP) && count($out) < 64) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_cidr_textarea")) {
	function layer7_parse_cidr_textarea($text)
	{
		$out = array();
		foreach (preg_split('/[\r\n]+/', trim((string)$text)) as $line) {
			$line = trim($line);
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			if (strpos($line, "/") !== false && count($out) < 16) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_host_textarea")) {
	function layer7_parse_host_textarea($text, $max = 16)
	{
		$out = array();
		foreach (preg_split('/[\r\n,]+/', trim((string)$text)) as $line) {
			$line = strtolower(trim($line));
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			if ($line[0] === ".") {
				$line = substr($line, 1);
			}
			if ($line !== "" && count($out) < $max) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_ad_users_textarea")) {
	function layer7_parse_ad_users_textarea($text, $max = 16)
	{
		$out = array();
		foreach (preg_split('/[\r\n,]+/', trim((string)$text)) as $line) {
			$line = trim((string)$line);
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			if (count($out) < $max) {
				$out[] = strtolower($line);
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_ad_groups_textarea")) {
	function layer7_parse_ad_groups_textarea($text, $max = 16)
	{
		$out = array();
		foreach (preg_split('/[\r\n,]+/', trim((string)$text)) as $line) {
			$line = strtolower(trim((string)$line));
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			if (strlen($line) > 63) {
				continue;
			}
			if (count($out) < $max) {
				$out[] = $line;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("layer7_parse_schedule_post")) {
	function layer7_parse_schedule_post($prefix)
	{
		$days = array();
		foreach (array("mon", "tue", "wed", "thu", "fri", "sat", "sun") as $d) {
			if (isset($_POST[$prefix . "_sched_" . $d])) {
				$days[] = $d;
			}
		}
		$start = trim($_POST[$prefix . "_sched_start"] ?? "");
		$end = trim($_POST[$prefix . "_sched_end"] ?? "");
		if (empty($days) || $start === "" || $end === "") {
			return null;
		}
		if (!preg_match('/^\d{1,2}:\d{2}$/', $start) || !preg_match('/^\d{1,2}:\d{2}$/', $end)) {
			return null;
		}
		return array("days" => $days, "start" => $start, "end" => $end);
	}
}

if (!function_exists("layer7_schedule_summary")) {
	function layer7_schedule_summary($policy)
	{
		if (!isset($policy["schedule"]) || !is_array($policy["schedule"])) {
			return l7_t("Sempre activa");
		}
		$s = $policy["schedule"];
		$days = isset($s["days"]) && is_array($s["days"]) ? $s["days"] : array();
		$start = isset($s["start"]) ? $s["start"] : "";
		$end = isset($s["end"]) ? $s["end"] : "";
		if (empty($days) || $start === "" || $end === "") {
			return l7_t("Sempre activa");
		}
		$day_labels = array(
			"mon" => "Seg", "tue" => "Ter", "wed" => "Qua",
			"thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom",
		);
		$labels = array();
		foreach ($days as $d) {
			$labels[] = isset($day_labels[$d]) ? $day_labels[$d] : $d;
		}
		return implode(",", $labels) . " " . $start . "-" . $end;
	}
}

if (!function_exists("layer7_policy_block_valid")) {
	function layer7_policy_block_valid($policy)
	{
		if (!is_array($policy)) {
			return false;
		}
		if (!isset($policy["action"]) || (string)$policy["action"] !== "block") {
			return true;
		}
		if (!empty($policy["scope_global"]) || !empty($policy["quarantine_origin"]) ||
		    !empty($policy["quarantine"])) {
			return true;
		}
		$match = (isset($policy["match"]) && is_array($policy["match"]))
			? $policy["match"] : array();
		return !empty($match["hosts"]) || !empty($match["ndpi_app"]) ||
			!empty($match["ndpi_category"]);
	}
}

if (!function_exists("layer7_enforcement_is_scoped_hybrid")) {
	function layer7_enforcement_is_scoped_hybrid($data)
	{
		return !empty($GLOBALS["l7hp_scoped"]);
	}
}

if (!function_exists("layer7_policy_scoped_block_valid")) {
	function layer7_policy_scoped_block_valid($rule, $data)
	{
		return true;
	}
}

if (!function_exists("layer7_policy_src_include_exclude_conflict")) {
	function layer7_policy_src_include_exclude_conflict($rule, $groups)
	{
		return false;
	}
}

if (!function_exists("layer7_policy_stamp_adulto_match_mode")) {
	function layer7_policy_stamp_adulto_match_mode(&$rule)
	{
	}
}

if (!function_exists("layer7_render_styles")) {
	function layer7_render_styles()
	{
		echo "<!-- L7HP_STYLES -->\n";
	}
}

if (!function_exists("layer7_render_tabs")) {
	function layer7_render_tabs($active = "")
	{
		echo "<!-- L7HP_TABS " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_policies_subnav")) {
	function layer7_render_policies_subnav($active = "")
	{
		echo "<!-- L7HP_SUBNAV " . htmlspecialchars((string)$active) . " -->\n";
	}
}

if (!function_exists("layer7_render_footer")) {
	function layer7_render_footer()
	{
		echo "<!-- L7HP_FOOTER -->\n";
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

if (!function_exists("l7hp_policy")) {
	function l7hp_policy($id, $opts = array())
	{
		$p = array(
			"id" => $id,
			"name" => isset($opts["name"]) ? $opts["name"] : $id,
			"enabled" => array_key_exists("enabled", $opts) ? !empty($opts["enabled"]) : true,
			"action" => isset($opts["action"]) ? $opts["action"] : "monitor",
			"priority" => isset($opts["priority"]) ? (int)$opts["priority"] : 50,
			"match" => isset($opts["match"]) && is_array($opts["match"]) ? $opts["match"] : array(),
		);
		foreach (array("interfaces", "tag_table", "schedule", "scope_global", "quarantine_origin") as $k) {
			if (isset($opts[$k])) {
				$p[$k] = $opts[$k];
			}
		}
		return $p;
	}
}

if (!function_exists("l7hp_data")) {
	function l7hp_data($policies, $groups = array(), $exceptions = array())
	{
		$layer7 = array("policies" => $policies, "groups" => $groups);
		if (!empty($exceptions)) {
			$layer7["exceptions"] = $exceptions;
		}
		return array("layer7" => $layer7);
	}
}

if (!function_exists("l7hp_full_policy")) {
	function l7hp_full_policy()
	{
		return l7hp_policy("p-lab-001", array(
			"name" => "Politica lab",
			"enabled" => true,
			"action" => "block",
			"priority" => 10,
			"interfaces" => array("em1"),
			"tag_table" => "layer7_lab",
			"scope_global" => true,
			"quarantine_origin" => true,
			"schedule" => array(
				"days" => array("mon", "wed"),
				"start" => "08:00",
				"end" => "18:00",
			),
			"match" => array(
				"ndpi_app" => array("YouTube"),
				"ndpi_category" => array("Media"),
				"hosts" => array("youtube.com"),
				"src_hosts" => array("192.0.2.10"),
				"src_cidrs" => array("192.0.2.0/24"),
				"ad_users" => array("joao"),
				"ad_groups" => array("ti"),
				"groups" => array("lab"),
				"src_exclude_cidrs" => array("192.0.2.50/32"),
				"src_exclude_groups" => array("vip"),
			),
		));
	}
}

if (!function_exists("l7hp_xss_policy")) {
	function l7hp_xss_policy()
	{
		return l7hp_policy("p-xss", array(
			"name" => 'pol<script>x</script>',
			"action" => "monitor",
			"priority" => 1,
			"match" => array("hosts" => array('<img src=x onerror=alert(1)>')),
		));
	}
}

if (!function_exists("l7hp_groups")) {
	function l7hp_groups()
	{
		return array(
			array("id" => "lab", "name" => "Lab"),
			array("id" => "vip", "name" => "VIP"),
		);
	}
}

if (!function_exists("l7hp_groups_n")) {
	function l7hp_groups_n($n)
	{
		$n = (int)$n;
		if ($n < 0) {
			$n = 0;
		}
		$out = array();
		for ($i = 0; $i < $n; $i++) {
			$out[] = array("id" => "g" . $i, "name" => "Grupo " . $i);
		}
		return $out;
	}
}

if (!function_exists("l7hp_vip_exception")) {
	function l7hp_vip_exception($opts = array())
	{
		return array(
			"id" => layer7_vip_exception_id(),
			"action" => "allow",
			"enabled" => true,
			"hosts" => isset($opts["hosts"]) ? (array)$opts["hosts"] : array("192.168.1.50"),
			"cidrs" => isset($opts["cidrs"]) ? (array)$opts["cidrs"] : array("10.0.0.0/24"),
			"source_groups" => isset($opts["source_groups"]) ? (array)$opts["source_groups"] : array("vip"),
		);
	}
}

if (!function_exists("l7hp_strip_function_if_defined")) {
	function l7hp_strip_function_if_defined($raw, $func_name)
	{
		if (!function_exists($func_name)) {
			return $raw;
		}
		$needle = "function " . $func_name . "(";
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
						. "/* harness: {$func_name} ja definido */\n"
						. substr($raw, $i + 1);
				}
			}
		}
		return $raw;
	}
}

if (!function_exists("l7hp_strip_policies_helpers")) {
	function l7hp_strip_policies_helpers($raw)
	{
		$funcs = array(
			"layer7_policies_posted_text",
			"layer7_policies_posted_list",
			"layer7_policies_posted_checked",
			"layer7_policies_lines",
			"layer7_policies_iface_selected",
			"layer7_policies_ifaces_html",
			"layer7_policies_group_boxes_html",
			"layer7_policies_ndpi_list_html",
			"layer7_policies_schedule_html",
			"layer7_policies_find_profile",
			"layer7_profile_options_state",
			"layer7_profile_options_ifaces_html",
			"layer7_profile_options_groups_html",
			"layer7_render_profile_options_form",
			"layer7_profile_edit_connected",
			"layer7_profile_edit_hidden_flag",
			"layer7_profile_edit_state",
			"layer7_profile_edit_confirm_map",
			"layer7_profile_edit_filter_html",
			"layer7_profile_edit_set_dom_hidden",
			"layer7_profile_edit_checkbox_html",
			"layer7_render_profile_edit_form",
		);
		foreach ($funcs as $fn) {
			$raw = l7hp_strip_function_if_defined($raw, $fn);
		}
		return $raw;
	}
}

if (!function_exists("l7hp_strip_match_summary")) {
	function l7hp_strip_match_summary($raw)
	{
		if (!function_exists("layer7_policy_match_summary")) {
			return $raw;
		}
		$needle = "function layer7_policy_match_summary(";
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
						. "/* harness: layer7_policy_match_summary ja definido */\n"
						. substr($raw, $i + 1);
				}
			}
		}
		return $raw;
	}
}

if (!function_exists("l7hp_view_source")) {
	function l7hp_view_source($path)
	{
		static $cache = array();
		if (isset($cache[$path])) {
			return l7hp_strip_policies_helpers(l7hp_strip_match_summary($cache[$path]));
		}
		if (!is_file($path)) {
			fwrite(STDERR, "FAIL fonte em falta: {$path}\n");
			exit(1);
		}
		$loaded = file_get_contents($path);
		if (!is_string($loaded) || $loaded === "") {
			fwrite(STDERR, "FAIL nao foi possivel ler a view: {$path}\n");
			exit(1);
		}
		if (strncmp($loaded, "<?php", 5) === 0) {
			$loaded = substr($loaded, 5);
		}
		$loaded = preg_replace('/require_once\s*\([^)]+\);/', "/* require stubbed */", $loaded);
		$loaded = str_replace('include("head.inc");', 'echo "<!-- L7HP_HEAD -->\n";', $loaded);
		$loaded = str_replace('include("foot.inc");', 'echo "<!-- L7HP_FOOT -->\n";', $loaded);
		$cache[$path] = $loaded;
		return l7hp_strip_policies_helpers(l7hp_strip_match_summary("global \$input_errors, \$savemsg;\n" . $loaded));
	}
}

if (!function_exists("l7hp_render")) {
	function l7hp_render($opts)
	{
		global $input_errors, $savemsg, $user_settings;
		l7hp_load_form_classes();
		$input_errors = (isset($opts["input_errors"]) && is_array($opts["input_errors"]))
			? $opts["input_errors"] : array();
		$savemsg = "";
		$_GET = isset($opts["get"]) && is_array($opts["get"]) ? $opts["get"] : array();
		$_POST = isset($opts["post"]) && is_array($opts["post"]) ? $opts["post"] : array();
		$_SERVER["REQUEST_URI"] = "/packages/layer7/layer7_policies.php";
		$GLOBALS["l7hp_data"] = isset($opts["data"]) ? $opts["data"] : l7hp_data(array());
		$GLOBALS["l7hp_identity"] = !isset($opts["identity"]) || $opts["identity"];
		$GLOBALS["l7hp_scoped"] = !empty($opts["scoped"]);
		if (isset($opts["ndpi"])) {
			$GLOBALS["l7hp_ndpi"] = $opts["ndpi"];
		} else {
			unset($GLOBALS["l7hp_ndpi"]);
		}
		if (isset($opts["ifaces"])) {
			$GLOBALS["l7hp_ifaces"] = $opts["ifaces"];
		} else {
			unset($GLOBALS["l7hp_ifaces"]);
		}
		if (array_key_exists("vip_exception", $opts)) {
			$GLOBALS["l7hp_vip_exception"] = $opts["vip_exception"];
		} else {
			unset($GLOBALS["l7hp_vip_exception"]);
		}
		$path = isset($opts["source"]) ? $opts["source"] : L7HP_CANDIDATE;
		ob_start();
		eval(l7hp_view_source($path));
		return ob_get_clean();
	}
}

if (!function_exists("l7hp_attr")) {
	function l7hp_attr($attrs, $name)
	{
		if (preg_match('/\b' . preg_quote($name, "/") . '\s*=\s*"([^"]*)"/i', $attrs, $m)) {
			return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
		}
		if (preg_match('/\b' . preg_quote($name, "/") . '\s*=\s*\'([^\']*)\'/i', $attrs, $m)) {
			return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
		}
		if (preg_match('/\b' . preg_quote($name, "/") . '\b(?!\s*=)/i', $attrs)) {
			return true;
		}
		return null;
	}
}

if (!function_exists("l7hp_extract_fields")) {
	function l7hp_extract_fields($html)
	{
		$out = array(
			"names" => array(),
			"values" => array(),
			"checked" => array(),
			"selected" => array(),
			"limits" => array(),
			"ids" => array(),
		);
		if (preg_match_all('/<input\b([^>]*)>/i', $html, $ins, PREG_SET_ORDER)) {
			foreach ($ins as $in) {
				$a = $in[1];
				$name = l7hp_attr($a, "name");
				if (!is_string($name) || $name === "") {
					continue;
				}
				$type = l7hp_attr($a, "type");
				$type = is_string($type) ? strtolower($type) : "text";
				$out["names"][$name] = $type;
				$val = l7hp_attr($a, "value");
				if ($type === "checkbox" || $type === "radio") {
					$checked = l7hp_attr($a, "checked") !== null;
					$v = is_string($val) ? $val : "on";
					if (!isset($out["checked"][$name])) {
						$out["checked"][$name] = array();
					}
					$out["checked"][$name][$v] = $checked;
				} elseif ($type !== "submit" && $type !== "button") {
					$out["values"][$name] = is_string($val) ? $val : "";
				} else {
					$out["values"][$name] = is_string($val) ? $val : "";
				}
				$id = l7hp_attr($a, "id");
				if (is_string($id) && $id !== "") {
					$out["ids"][$name] = $id;
				}
				$lim = array();
				foreach (array("min", "max", "maxlength", "pattern", "required", "disabled") as $k) {
					$av = l7hp_attr($a, $k);
					if ($av !== null) {
						$lim[$k] = ($av === true) ? "1" : (string)$av;
					}
				}
				if ($lim) {
					$out["limits"][$name] = $lim;
				}
			}
		}
		if (preg_match_all('/<textarea\b([^>]*)>([\s\S]*?)<\/textarea>/i', $html, $tas, PREG_SET_ORDER)) {
			foreach ($tas as $ta) {
				$name = l7hp_attr($ta[1], "name");
				if (!is_string($name) || $name === "") {
					continue;
				}
				$out["names"][$name] = "textarea";
				$out["values"][$name] = html_entity_decode($ta[2], ENT_QUOTES, "UTF-8");
				$id = l7hp_attr($ta[1], "id");
				if (is_string($id) && $id !== "") {
					$out["ids"][$name] = $id;
				}
			}
		}
		if (preg_match_all('/<select\b([^>]*)>([\s\S]*?)<\/select>/i', $html, $sels, PREG_SET_ORDER)) {
			foreach ($sels as $sel) {
				$name = l7hp_attr($sel[1], "name");
				if (!is_string($name) || $name === "") {
					continue;
				}
				$out["names"][$name] = "select";
				$opts = array();
				$picked = array();
				if (preg_match_all('/<option\b([^>]*)>([\s\S]*?)<\/option>/i', $sel[2], $ops, PREG_SET_ORDER)) {
					foreach ($ops as $op) {
						$ov = l7hp_attr($op[1], "value");
						$ov = is_string($ov) ? $ov : trim(strip_tags($op[2]));
						$opts[] = $ov;
						if (l7hp_attr($op[1], "selected") !== null) {
							$picked[] = $ov;
						}
					}
				}
				$out["values"][$name] = $opts;
				$out["selected"][$name] = $picked;
				$id = l7hp_attr($sel[1], "id");
				if (is_string($id) && $id !== "") {
					$out["ids"][$name] = $id;
				}
			}
		}
		if (preg_match_all('/<button\b([^>]*)>([\s\S]*?)<\/button>/i', $html, $bts, PREG_SET_ORDER)) {
			foreach ($bts as $bt) {
				$name = l7hp_attr($bt[1], "name");
				if (!is_string($name) || $name === "") {
					continue;
				}
				$out["names"][$name] = "submit";
				$val = l7hp_attr($bt[1], "value");
				$out["values"][$name] = is_string($val) ? $val : trim(strip_tags($bt[2]));
			}
		}
		return $out;
	}
}

if (!function_exists("l7hp_form_action")) {
	function l7hp_form_action($html, $needle)
	{
		if (preg_match('/<form\b[^>]*action="([^"]*' . preg_quote($needle, "/") . '[^"]*)"/i', $html, $m)) {
			return $m[1];
		}
		return "";
	}
}
