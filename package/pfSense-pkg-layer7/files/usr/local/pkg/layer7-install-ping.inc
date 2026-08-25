<?php
/**
 * BG-162 / ADR-0036 — inventário de instalação (fail-open).
 * BG-163: não carregar config.inc (fatal em CLI); curl por caminho absoluto;
 * throttle de retry. Testável sem pfSense: passar $config e $opts.
 */

function layer7_install_ping_url()
{
	return "https://license.systemup.inf.br/api/license/install-ping";
}

function layer7_install_ping_state_dir()
{
	$dir = "/var/db/layer7";
	if (defined("LAYER7_TEST_ROOT") && LAYER7_TEST_ROOT) {
		$dir = rtrim((string)LAYER7_TEST_ROOT, "/") . "/var/db/layer7";
	}
	return $dir;
}

function layer7_install_ping_state_path()
{
	return layer7_install_ping_state_dir() . "/install-ping.json";
}

function layer7_install_ping_id_path()
{
	return layer7_install_ping_state_dir() . "/install-id";
}

function layer7_install_ping_putenv_path()
{
	$extra = "/sbin:/bin:/usr/sbin:/usr/bin:/usr/local/sbin:/usr/local/bin";
	$cur = getenv("PATH");
	if (!is_string($cur) || $cur === "") {
		putenv("PATH=" . $extra);
		return;
	}
	if (strpos($cur, "/usr/local/bin") === false) {
		putenv("PATH=" . $cur . ":" . $extra);
	}
}

function layer7_install_ping_cmd($cmd)
{
	if (!is_string($cmd) || $cmd === "") {
		return "";
	}
	if (function_exists("exec")) {
		$lines = array();
		$rc = 0;
		@exec($cmd . " 2>/dev/null", $lines, $rc);
		if (is_array($lines) && $lines !== array()) {
			return implode("\n", $lines);
		}
		return "";
	}
	if (function_exists("shell_exec")) {
		$out = @shell_exec($cmd . " 2>/dev/null");
		return is_string($out) ? $out : "";
	}
	return "";
}

function layer7_install_ping_read_trimmed($path, $max = 128)
{
	if (!is_string($path) || $path === "" || !is_readable($path)) {
		return "";
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw)) {
		return "";
	}
	$raw = trim(str_replace(array("\r", "\n"), "", $raw));
	if (strlen($raw) > $max) {
		$raw = substr($raw, 0, $max);
	}
	return $raw;
}

function layer7_install_ping_sysctl($name, $max = 128)
{
	$name = preg_replace('/[^a-zA-Z0-9._-]/', "", (string)$name);
	if ($name === "") {
		return "";
	}
	$bin = is_executable("/sbin/sysctl") ? "/sbin/sysctl" : "sysctl";
	$out = layer7_install_ping_cmd($bin . " -n " . escapeshellarg($name));
	$out = trim($out);
	if (strlen($out) > $max) {
		$out = substr($out, 0, $max);
	}
	return $out;
}

function layer7_install_ping_kenv($key, $max = 128)
{
	$key = preg_replace('/[^a-zA-Z0-9._-]/', "", (string)$key);
	if ($key === "") {
		return "";
	}
	$bin = is_executable("/bin/kenv") ? "/bin/kenv" : "kenv";
	$out = layer7_install_ping_cmd($bin . " -q " . escapeshellarg($key));
	$out = trim($out);
	if (strlen($out) > $max) {
		$out = substr($out, 0, $max);
	}
	return $out;
}

function layer7_install_ping_valid_ipv4($ip)
{
	return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
		&& $ip !== "0.0.0.0" && $ip !== "127.0.0.1";
}

function layer7_install_ping_valid_ipv6($ip)
{
	if (!is_string($ip) || $ip === "") {
		return false;
	}
	$ip = explode("%", $ip)[0];
	if ($ip === "::1") {
		return false;
	}
	if (stripos($ip, "fe80:") === 0) {
		return false;
	}
	return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
}

function layer7_install_ping_ifconfig_ip($real, $family = 4)
{
	$real = preg_replace('/[^a-zA-Z0-9_.-]/', "", (string)$real);
	if ($real === "") {
		return "";
	}
	$bin = is_executable("/sbin/ifconfig") ? "/sbin/ifconfig" : "ifconfig";
	$out = layer7_install_ping_cmd($bin . " " . escapeshellarg($real));
	if ($out === "") {
		return "";
	}
	if ($family === 4) {
		if (preg_match('/\binet\s+(\d+\.\d+\.\d+\.\d+)\b/', $out, $m)
		    && layer7_install_ping_valid_ipv4($m[1])) {
			return $m[1];
		}
		return "";
	}
	if (preg_match_all('/\binet6\s+([0-9a-fA-F:]+)/', $out, $mm)) {
		foreach ($mm[1] as $cand) {
			if (layer7_install_ping_valid_ipv6($cand)) {
				return explode("%", $cand)[0];
			}
		}
	}
	return "";
}

function layer7_install_ping_iface_ip($ifname, $cfg, $family = 4)
{
	if ($family === 4) {
		$raw = isset($cfg["ipaddr"]) ? trim((string)$cfg["ipaddr"]) : "";
		if (layer7_install_ping_valid_ipv4($raw)) {
			return $raw;
		}
		if (function_exists("get_interface_ip")) {
			$got = get_interface_ip($ifname);
			if (layer7_install_ping_valid_ipv4($got)) {
				return $got;
			}
		}
		$real = isset($cfg["if"]) ? (string)$cfg["if"] : "";
		return layer7_install_ping_ifconfig_ip($real, 4);
	}
	$raw = isset($cfg["ipaddrv6"]) ? trim((string)$cfg["ipaddrv6"]) : "";
	if (layer7_install_ping_valid_ipv6($raw)) {
		return explode("%", $raw)[0];
	}
	if (function_exists("get_interface_ipv6")) {
		$got = get_interface_ipv6($ifname);
		if (layer7_install_ping_valid_ipv6($got)) {
			return explode("%", (string)$got)[0];
		}
	}
	$real = isset($cfg["if"]) ? (string)$cfg["if"] : "";
	return layer7_install_ping_ifconfig_ip($real, 6);
}

function layer7_install_ping_xml_decode($value)
{
	$value = html_entity_decode(trim((string)$value), ENT_QUOTES, "UTF-8");
	return $value;
}

function layer7_install_ping_xml_inner($raw, $tag)
{
	$tag = preg_replace('/[^a-zA-Z0-9_]/', "", (string)$tag);
	if ($tag === "" || !is_string($raw)) {
		return "";
	}
	if (!preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/s', $raw, $m)) {
		return "";
	}
	return $m[1];
}

function layer7_install_ping_xml_leaf($raw, $tag)
{
	$tag = preg_replace('/[^a-zA-Z0-9_]/', "", (string)$tag);
	if ($tag === "" || !is_string($raw) || $raw === "") {
		return "";
	}
	if (!preg_match('/<' . $tag . '>([^<]*)<\/' . $tag . '>/', $raw, $m)) {
		return "";
	}
	return layer7_install_ping_xml_decode($m[1]);
}

function layer7_install_ping_config_xml_paths()
{
	$paths = array("/cf/conf/config.xml", "/conf/config.xml");
	if (defined("LAYER7_TEST_ROOT") && LAYER7_TEST_ROOT) {
		$root = rtrim((string)LAYER7_TEST_ROOT, "/");
		array_unshift($paths, $root . "/cf/conf/config.xml", $root . "/conf/config.xml");
	}
	return $paths;
}

function layer7_install_ping_load_pfsense_config()
{
	$raw = "";
	foreach (layer7_install_ping_config_xml_paths() as $path) {
		if (!is_readable($path)) {
			continue;
		}
		$raw = @file_get_contents($path);
		if (is_string($raw) && $raw !== "") {
			break;
		}
	}
	if ($raw === "") {
		return array();
	}

	$config = array(
		"system" => array(),
		"interfaces" => array(),
		"gateways" => array(),
	);

	$system = layer7_install_ping_xml_inner($raw, "system");
	$hostname = layer7_install_ping_xml_leaf($system, "hostname");
	$domain = layer7_install_ping_xml_leaf($system, "domain");
	if ($hostname !== "") {
		$config["system"]["hostname"] = $hostname;
	}
	if ($domain !== "") {
		$config["system"]["domain"] = $domain;
	}

	$ifaces_blob = layer7_install_ping_xml_inner($raw, "interfaces");
	if ($ifaces_blob !== ""
	    && preg_match_all('/<(wan|lan|opt[0-9]+)>(.*?)<\/\1>/s', $ifaces_blob, $mm, PREG_SET_ORDER)) {
		foreach ($mm as $block) {
			$id = $block[1];
			$body = $block[2];
			$entry = array();
			foreach (array("if", "descr", "ipaddr", "ipaddrv6", "gateway") as $field) {
				$val = layer7_install_ping_xml_leaf($body, $field);
				if ($val !== "") {
					$entry[$field] = $val;
				}
			}
			$config["interfaces"][$id] = $entry;
		}
	}

	$gw_blob = layer7_install_ping_xml_inner($raw, "gateways");
	if ($gw_blob !== "") {
		$dgw = layer7_install_ping_xml_leaf($gw_blob, "defaultgw4");
		if ($dgw !== "") {
			$config["gateways"]["defaultgw4"] = $dgw;
		}
		$items = array();
		if (preg_match_all('/<gateway_item>(.*?)<\/gateway_item>/s', $gw_blob, $gm, PREG_SET_ORDER)) {
			foreach ($gm as $item) {
				$items[] = array(
					"name" => layer7_install_ping_xml_leaf($item[1], "name"),
					"gateway" => layer7_install_ping_xml_leaf($item[1], "gateway"),
				);
			}
		}
		if ($items !== array()) {
			$config["gateways"]["gateway_item"] = $items;
		}
	}

	return $config;
}

function layer7_install_ping_ensure_install_id()
{
	$path = layer7_install_ping_id_path();
	$dir = dirname($path);
	if (!is_dir($dir)) {
		@mkdir($dir, 0750, true);
	}
	$existing = layer7_install_ping_read_trimmed($path, 64);
	if ($existing !== "" && preg_match('/^[a-f0-9-]{8,64}$/i', $existing)) {
		return strtolower($existing);
	}
	$id = bin2hex(random_bytes(16));
	@file_put_contents($path, $id . "\n");
	@chmod($path, 0640);
	return $id;
}

function layer7_install_ping_load_state()
{
	$path = layer7_install_ping_state_path();
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return array();
	}
	$j = json_decode($raw, true);
	return is_array($j) ? $j : array();
}

function layer7_install_ping_save_state($state)
{
	$dir = layer7_install_ping_state_dir();
	if (!is_dir($dir)) {
		@mkdir($dir, 0750, true);
	}
	$path = layer7_install_ping_state_path();
	$tmp = $path . ".tmp";
	$json = json_encode($state, JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		return false;
	}
	if (@file_put_contents($tmp, $json . "\n") === false) {
		return false;
	}
	@chmod($tmp, 0640);
	return @rename($tmp, $path);
}

function layer7_install_ping_package_version()
{
	$bin = "/usr/local/sbin/layer7d";
	if (is_executable($bin)) {
		$out = trim(layer7_install_ping_cmd(escapeshellarg($bin) . " -V"));
		if ($out !== "" && strlen($out) <= 32) {
			return $out;
		}
	}
	if (function_exists("layer7_pkg_version")) {
		$pkg = trim((string)layer7_pkg_version());
		if ($pkg !== "" && strlen($pkg) <= 32) {
			return $pkg;
		}
	}
	$out = array();
	$rc = 0;
	exec("/usr/sbin/pkg query %v pfSense-pkg-layer7 2>/dev/null", $out, $rc);
	if ($rc === 0 && !empty($out[0])) {
		$v = trim((string)$out[0]);
		if ($v !== "" && strlen($v) <= 32) {
			return $v;
		}
	}
	return "unknown";
}

function layer7_install_ping_resolve_hardware_id($opts = array())
{
	if (!is_array($opts)) {
		$opts = array();
	}
	if (isset($opts["hardware_id"])) {
		$hw = strtolower(trim((string)$opts["hardware_id"]));
		if (preg_match('/^[a-f0-9]{64}$/', $hw)) {
			return $hw;
		}
	}
	$hw = layer7_install_ping_fingerprint();
	if ($hw !== "") {
		return $hw;
	}
	$state = layer7_install_ping_load_state();
	$prev = isset($state["last_hardware_id"])
		? strtolower(trim((string)$state["last_hardware_id"])) : "";
	if (preg_match('/^[a-f0-9]{64}$/', $prev)) {
		return $prev;
	}
	return "";
}

function layer7_install_ping_fingerprint()
{
	$bin = "/usr/local/sbin/layer7d";
	if (is_executable($bin)) {
		$out = strtolower(trim(layer7_install_ping_cmd(escapeshellarg($bin) . " --fingerprint")));
		if (preg_match('/^[a-f0-9]{64}$/', $out)) {
			return $out;
		}
	}
	return "";
}

function layer7_install_ping_hardware_id_fallback()
{
	$parts = array(
		layer7_install_ping_read_trimmed("/etc/hostid", 128),
		layer7_install_ping_read_trimmed("/var/db/uniqueid", 128),
	);
	if (defined("LAYER7_TEST_ROOT") && LAYER7_TEST_ROOT) {
		$root = rtrim((string)LAYER7_TEST_ROOT, "/");
		$parts[] = layer7_install_ping_read_trimmed($root . "/etc/hostid", 128);
		$parts[] = layer7_install_ping_read_trimmed($root . "/var/db/uniqueid", 128);
	}
	$parts[] = layer7_install_ping_sysctl("kern.hostuuid", 128);
	$seed = implode(":", array_filter($parts, function ($v) {
		return is_string($v) && $v !== "";
	}));
	if ($seed === "") {
		$seed = layer7_install_ping_ensure_install_id();
	}
	return hash("sha256", "layer7-install-ping-fallback:" . $seed);
}

function layer7_install_ping_license_key()
{
	$path = "/var/db/layer7-checkin.json";
	if (defined("LAYER7_TEST_ROOT") && LAYER7_TEST_ROOT) {
		$path = rtrim((string)LAYER7_TEST_ROOT, "/") . "/var/db/layer7-checkin.json";
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw)) {
		return "";
	}
	$j = json_decode($raw, true);
	if (!is_array($j) || empty($j["license_key"])) {
		return "";
	}
	$key = strtolower(trim((string)$j["license_key"]));
	return preg_match('/^[a-f0-9]{32}$/', $key) ? $key : "";
}

function layer7_install_ping_classify_event($state, $package_version)
{
	$prev = is_array($state) && isset($state["last_version"])
		? (string)$state["last_version"] : "";
	if ($prev === "") {
		return "install";
	}
	if ($prev !== (string)$package_version) {
		return "upgrade";
	}
	return "heartbeat";
}

function layer7_install_ping_should_send($state, $now = null)
{
	if (!is_array($state)) {
		$state = array();
	}
	$now = $now === null ? time() : (int)$now;
	$last_ok = isset($state["last_ok"]) ? (int)$state["last_ok"] : 0;
	$last_http = isset($state["last_http"]) ? (int)$state["last_http"] : 0;
	$last_attempt = isset($state["last_attempt"]) ? (int)$state["last_attempt"] : 0;

	if ($last_ok > 0 && ($now - $last_ok) < 86400 && $last_http >= 200 && $last_http < 300) {
		return false;
	}
	if ($last_attempt > 0 && ($now - $last_attempt) < 900) {
		return false;
	}
	return true;
}

function layer7_install_inventory($config = null, $opts = array())
{
	if (!is_array($config)) {
		global $config;
	}
	if (!is_array($config) || $config === array()) {
		$loaded = layer7_install_ping_load_pfsense_config();
		if (is_array($loaded) && $loaded !== array()) {
			$config = $loaded;
		}
	}
	if (!is_array($config)) {
		$config = array();
	}
	if (!is_array($opts)) {
		$opts = array();
	}

	$system = isset($config["system"]) && is_array($config["system"])
		? $config["system"] : array();
	$hostname = isset($opts["hostname"])
		? (string)$opts["hostname"]
		: (isset($system["hostname"]) ? (string)$system["hostname"] : "");
	$domain = isset($opts["domain"])
		? (string)$opts["domain"]
		: (isset($system["domain"]) ? (string)$system["domain"] : "");
	$hostname = substr(trim($hostname), 0, 255);
	$domain = substr(trim($domain), 0, 255);
	$fqdn = $hostname;
	if ($hostname !== "" && $domain !== "") {
		$fqdn = $hostname . "." . $domain;
	}

	$ifaces = array();
	$wan_v4 = "";
	$wan_v6 = "";
	$cfg_ifaces = isset($config["interfaces"]) && is_array($config["interfaces"])
		? $config["interfaces"] : array();
	foreach ($cfg_ifaces as $id => $icfg) {
		if (count($ifaces) >= 32) {
			break;
		}
		if (!is_array($icfg)) {
			continue;
		}
		$id = preg_replace('/[^a-zA-Z0-9_.-]/', "", (string)$id);
		if ($id === "" || strlen($id) > 32) {
			continue;
		}
		$real = isset($icfg["if"]) ? preg_replace('/[^a-zA-Z0-9_.-]/', "", (string)$icfg["if"]) : "";
		$descr = isset($icfg["descr"]) ? substr(trim((string)$icfg["descr"]), 0, 64) : "";
		$ipv4 = layer7_install_ping_iface_ip($id, $icfg, 4);
		$ipv6 = layer7_install_ping_iface_ip($id, $icfg, 6);
		$entry = array("id" => $id);
		if ($real !== "") {
			$entry["real"] = substr($real, 0, 32);
		}
		if ($descr !== "") {
			$entry["descr"] = $descr;
		}
		if ($ipv4 !== "") {
			$entry["ipv4"] = $ipv4;
		}
		if ($ipv6 !== "") {
			$entry["ipv6"] = $ipv6;
		}
		$ifaces[] = $entry;
		if ($id === "wan" || (isset($icfg["gateway"]) && $icfg["gateway"] !== "")) {
			if ($wan_v4 === "" && $ipv4 !== "") {
				$wan_v4 = $ipv4;
			}
			if ($wan_v6 === "" && $ipv6 !== "") {
				$wan_v6 = $ipv6;
			}
		}
	}

	$gateway = isset($opts["gateway_v4"]) ? (string)$opts["gateway_v4"] : "";
	if ($gateway === "" && isset($config["gateways"]["defaultgw4"])
	    && is_string($config["gateways"]["defaultgw4"])) {
		$gwname = $config["gateways"]["defaultgw4"];
		if (layer7_install_ping_valid_ipv4($gwname)) {
			$gateway = $gwname;
		} elseif (isset($config["gateways"]["gateway_item"])
		    && is_array($config["gateways"]["gateway_item"])) {
			foreach ($config["gateways"]["gateway_item"] as $item) {
				if (!is_array($item)) {
					continue;
				}
				if (isset($item["name"]) && $item["name"] === $gwname) {
					$gwv = isset($item["gateway"]) ? (string)$item["gateway"] : "";
					if (layer7_install_ping_valid_ipv4($gwv)) {
						$gateway = $gwv;
					}
					break;
				}
			}
		}
	}

	$package_version = isset($opts["package_version"])
		? (string)$opts["package_version"]
		: layer7_install_ping_package_version();
	$hardware_id = layer7_install_ping_resolve_hardware_id($opts);

	$uniqueid_path = "/var/db/uniqueid";
	$hostid_path = "/etc/hostid";
	$version_path = "/etc/version";
	$version_patch_path = "/etc/version.patch";
	$platform_path = "/etc/platform";
	if (defined("LAYER7_TEST_ROOT") && LAYER7_TEST_ROOT) {
		$root = rtrim((string)LAYER7_TEST_ROOT, "/");
		$uniqueid_path = $root . "/var/db/uniqueid";
		$hostid_path = $root . "/etc/hostid";
		$version_path = $root . "/etc/version";
		$version_patch_path = $root . "/etc/version.patch";
		$platform_path = $root . "/etc/platform";
	}

	$payload = array(
		"hardware_id" => $hardware_id,
		"install_id" => isset($opts["install_id"])
			? (string)$opts["install_id"]
			: layer7_install_ping_ensure_install_id(),
		"event" => isset($opts["event"])
			? (string)$opts["event"]
			: layer7_install_ping_classify_event(
				layer7_install_ping_load_state(),
				$package_version
			),
		"package_version" => substr(trim($package_version), 0, 32),
		"hostname" => $hostname,
		"domain" => $domain,
		"fqdn" => substr($fqdn, 0, 255),
		"pfsense_version" => isset($opts["pfsense_version"])
			? (string)$opts["pfsense_version"]
			: layer7_install_ping_read_trimmed($version_path, 64),
		"pfsense_version_patch" => isset($opts["pfsense_version_patch"])
			? (string)$opts["pfsense_version_patch"]
			: layer7_install_ping_read_trimmed($version_patch_path, 32),
		"platform" => isset($opts["platform"])
			? (string)$opts["platform"]
			: layer7_install_ping_read_trimmed($platform_path, 64),
		"uniqueid" => isset($opts["uniqueid"])
			? (string)$opts["uniqueid"]
			: layer7_install_ping_read_trimmed($uniqueid_path, 128),
		"system_serial" => isset($opts["system_serial"])
			? (string)$opts["system_serial"]
			: layer7_install_ping_kenv("smbios.system.serial", 128),
		"os_release" => isset($opts["os_release"])
			? (string)$opts["os_release"]
			: layer7_install_ping_sysctl("kern.osrelease", 64),
		"hw_model" => isset($opts["hw_model"])
			? (string)$opts["hw_model"]
			: layer7_install_ping_sysctl("hw.model", 128),
		"ncpu" => isset($opts["ncpu"])
			? (int)$opts["ncpu"]
			: (int)layer7_install_ping_sysctl("hw.ncpu", 8),
		"mem_mb" => isset($opts["mem_mb"])
			? (int)$opts["mem_mb"]
			: (int)floor(((int)layer7_install_ping_sysctl("hw.physmem", 24)) / 1048576),
		"wan_ipv4" => $wan_v4,
		"wan_ipv6" => $wan_v6,
		"gateway_v4" => $gateway,
		"interfaces" => $ifaces,
	);

	if ($payload["uniqueid"] === "" && is_readable($hostid_path)) {
		$payload["uniqueid"] = layer7_install_ping_read_trimmed($hostid_path, 128);
	}

	if ($payload["ncpu"] < 1) {
		unset($payload["ncpu"]);
	}
	if ($payload["mem_mb"] < 1) {
		unset($payload["mem_mb"]);
	}

	$key = isset($opts["license_key"])
		? (string)$opts["license_key"]
		: layer7_install_ping_license_key();
	if ($key !== "") {
		$payload["license_key"] = $key;
	}

	foreach (array_keys($payload) as $k) {
		if ($payload[$k] === "" || $payload[$k] === null) {
			unset($payload[$k]);
		}
	}

	return $payload;
}

function layer7_install_ping_curl_bin()
{
	foreach (array("/usr/local/bin/curl", "/usr/bin/curl") as $bin) {
		if (is_executable($bin)) {
			return $bin;
		}
	}
	return "curl";
}

function layer7_install_ping_post($payload, $url = null)
{
	if (!is_array($payload) || empty($payload["hardware_id"])) {
		return array("ok" => false, "error" => "hardware_id ausente", "http" => 0);
	}
	$url = $url ?: layer7_install_ping_url();
	$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		return array("ok" => false, "error" => "json", "http" => 0);
	}

	$ver = layer7_install_ping_package_version();
	$ua = "layer7-install-ping/" . ($ver !== "" ? $ver : "unknown");
	$ext_error = "";
	if (function_exists("curl_init")) {
		$ch = curl_init($url);
		if ($ch !== false) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				"User-Agent: " . $ua,
			));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			@curl_exec($ch);
			$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$cerr = curl_error($ch);
			curl_close($ch);
			if ($http > 0) {
				$result = array(
					"ok" => ($http >= 200 && $http < 300),
					"http" => $http,
				);
				return $result;
			}
			$ext_error = $cerr !== "" ? substr($cerr, 0, 200) : "curl_ext_http_0";
		}
	}

	$cmd = layer7_install_ping_curl_bin()
		. " -sS -o /dev/null -w '%{http_code}'"
		. " --connect-timeout 10 --max-time 30"
		. " -H " . escapeshellarg("Content-Type: application/json")
		. " -H " . escapeshellarg("User-Agent: " . $ua)
		. " --data-binary " . escapeshellarg($json)
		. " " . escapeshellarg($url);
	$code = trim(layer7_install_ping_cmd($cmd));
	$http = (int)$code;
	return array(
		"ok" => ($http >= 200 && $http < 300),
		"http" => $http,
		"error" => $http === 0 ? ($ext_error !== "" ? $ext_error : "curl_http_0") : "",
	);
}

function layer7_install_ping_note($msg)
{
	$msg = preg_replace('/[^a-zA-Z0-9 _.:\\/=-]/', "", (string)$msg);
	if ($msg === "") {
		return;
	}
	if (is_executable("/usr/bin/logger")) {
		layer7_install_ping_cmd("/usr/bin/logger -t layer7-install-ping " . escapeshellarg($msg));
	}
}

function layer7_install_ping_run($config = null, $opts = array())
{
	if (!is_array($opts)) {
		$opts = array();
	}
	layer7_install_ping_putenv_path();

	$force = !empty($opts["force"]) || getenv("LAYER7_INSTALL_PING_FORCE") === "1";
	$state = layer7_install_ping_load_state();
	if (!$force && !layer7_install_ping_should_send($state)) {
		return array("ok" => true, "skipped" => true, "http" => isset($state["last_http"]) ? (int)$state["last_http"] : 0);
	}

	$payload = layer7_install_inventory($config, $opts);
	if (empty($payload["hardware_id"])) {
		layer7_install_ping_note("event=skip reason=hardware_id");
		return array(
			"ok" => true,
			"skipped" => true,
			"reason" => "hardware_id",
			"http" => 0,
		);
	}
	$result = layer7_install_ping_post($payload, isset($opts["url"]) ? $opts["url"] : null);
	$state = layer7_install_ping_load_state();
	$state["last_attempt"] = time();
	$state["last_event"] = isset($payload["event"]) ? $payload["event"] : "";
	$state["last_http"] = isset($result["http"]) ? (int)$result["http"] : 0;
	if (!empty($result["error"])) {
		$state["last_error"] = substr((string)$result["error"], 0, 200);
	} else {
		unset($state["last_error"]);
	}
	if (!empty($result["ok"])) {
		$state["last_ok"] = time();
		$state["last_version"] = isset($payload["package_version"])
			? $payload["package_version"] : "";
		$state["last_hardware_id"] = (string)$payload["hardware_id"];
	}
	layer7_install_ping_save_state($state);
	layer7_install_ping_note(
		"event=" . (isset($payload["event"]) ? $payload["event"] : "?")
		. " http=" . $state["last_http"]
	);
	return $result;
}
