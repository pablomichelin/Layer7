<?php
/**
 * BG-162 / ADR-0036 — inventário de instalação (fail-open).
 * Testável sem pfSense: passar $config e $opts.
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
	$out = @shell_exec("sysctl -n " . escapeshellarg($name) . " 2>/dev/null");
	if (!is_string($out)) {
		return "";
	}
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
	$out = @shell_exec("kenv -q " . escapeshellarg($key) . " 2>/dev/null");
	if (!is_string($out)) {
		return "";
	}
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
	return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
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
		return "";
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
	return "";
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
		$out = @shell_exec(escapeshellarg($bin) . " -V 2>/dev/null");
		if (is_string($out)) {
			$out = trim($out);
			if ($out !== "" && strlen($out) <= 32) {
				return $out;
			}
		}
	}
	return "1.9.71";
}

function layer7_install_ping_fingerprint()
{
	$bin = "/usr/local/sbin/layer7d";
	if (!is_executable($bin)) {
		return "";
	}
	$out = @shell_exec(escapeshellarg($bin) . " --fingerprint 2>/dev/null");
	if (!is_string($out)) {
		return "";
	}
	$out = strtolower(trim($out));
	if (preg_match('/^[a-f0-9]{64}$/', $out)) {
		return $out;
	}
	return "";
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

function layer7_install_inventory($config = null, $opts = array())
{
	if (!is_array($config)) {
		global $config;
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
	$hardware_id = isset($opts["hardware_id"])
		? (string)$opts["hardware_id"]
		: layer7_install_ping_fingerprint();

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
			: layer7_install_ping_read_trimmed("/etc/version", 64),
		"pfsense_version_patch" => isset($opts["pfsense_version_patch"])
			? (string)$opts["pfsense_version_patch"]
			: layer7_install_ping_read_trimmed("/etc/version.patch", 32),
		"platform" => isset($opts["platform"])
			? (string)$opts["platform"]
			: layer7_install_ping_read_trimmed("/etc/platform", 64),
		"uniqueid" => isset($opts["uniqueid"])
			? (string)$opts["uniqueid"]
			: layer7_install_ping_read_trimmed("/var/db/uniqueid", 128),
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

function layer7_install_ping_post($payload, $url = null)
{
	if (!is_array($payload) || empty($payload["hardware_id"])) {
		return array("ok" => false, "error" => "hardware_id ausente");
	}
	$url = $url ?: layer7_install_ping_url();
	$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		return array("ok" => false, "error" => "json");
	}

	$cmd = "curl -sS -o /dev/null -w '%{http_code}'"
		. " --connect-timeout 10 --max-time 30"
		. " -H " . escapeshellarg("Content-Type: application/json")
		. " -H " . escapeshellarg("User-Agent: layer7-install-ping/1.9.71")
		. " --data-binary " . escapeshellarg($json)
		. " " . escapeshellarg($url);
	$code = trim((string)@shell_exec($cmd . " 2>/dev/null"));
	$http = (int)$code;
	return array(
		"ok" => ($http >= 200 && $http < 300),
		"http" => $http,
	);
}

function layer7_install_ping_run($config = null, $opts = array())
{
	$payload = layer7_install_inventory($config, $opts);
	$result = layer7_install_ping_post($payload, isset($opts["url"]) ? $opts["url"] : null);
	$state = layer7_install_ping_load_state();
	$state["last_attempt"] = time();
	$state["last_event"] = isset($payload["event"]) ? $payload["event"] : "";
	$state["last_http"] = isset($result["http"]) ? (int)$result["http"] : 0;
	if (!empty($result["ok"])) {
		$state["last_ok"] = time();
		$state["last_version"] = isset($payload["package_version"])
			? $payload["package_version"] : "";
	}
	layer7_install_ping_save_state($state);
	return $result;
}
