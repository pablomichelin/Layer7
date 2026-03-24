<?php
/*
 * Funcoes partilhadas Layer 7 (JSON em /usr/local/etc/layer7.json).
 */
if (defined("LAYER7_INC_LOADED")) {
	return;
}
define("LAYER7_INC_LOADED", true);

/**
 * Translation helper. Returns the translated string for the current language.
 * Keys are the original Portuguese strings. When lang=pt, returns the key as-is.
 * When lang=en (or other), looks up the translation file.
 */
function l7_t($key)
{
	static $strings = null;
	static $lang_loaded = "";

	$lang = layer7_get_language();
	if ($lang === "pt") {
		return $key;
	}

	if ($strings === null || $lang_loaded !== $lang) {
		$path = "/usr/local/etc/layer7/lang/{$lang}.php";
		if (file_exists($path)) {
			$L7_STRINGS = array();
			include($path);
			$strings = $L7_STRINGS;
		} else {
			$strings = array();
		}
		$lang_loaded = $lang;
	}

	return isset($strings[$key]) ? $strings[$key] : $key;
}

function layer7_get_language()
{
	$cfgpath = "/usr/local/etc/layer7.json";
	if (file_exists($cfgpath)) {
		$raw = @file_get_contents($cfgpath);
		$j = @json_decode($raw, true);
		if (is_array($j) && isset($j["layer7"]["language"])) {
			$l = $j["layer7"]["language"];
			if ($l === "en" || $l === "pt") {
				return $l;
			}
		}
	}
	return "pt";
}

function layer7_cfg_path()
{
	return "/usr/local/etc/layer7.json";
}

function layer7_sample_path()
{
	return "/usr/local/etc/layer7.json.sample";
}

function layer7_pf_helper_path()
{
	return "/usr/local/libexec/layer7-pfctl";
}

function layer7_pf_rules_path()
{
	return "/usr/local/etc/layer7/pf.conf";
}

function layer7_pf_rules_sample_path()
{
	return "/usr/local/etc/layer7/pf.conf.sample";
}

function layer7_pf_default_rules_text()
{
	return implode("\n", array(
		"# Layer7 PF snippet (package-managed)",
		"table <layer7_block> persist",
		"table <layer7_tagged> persist",
		"table <layer7_block_dst> persist",
		"",
		"# Anti-bypass: bloquear DoT (TCP 853) e DoQ (UDP 853).",
		"block drop quick inet proto tcp to port 853 label \"layer7:anti-dot\"",
		"block drop quick inet6 proto tcp to port 853 label \"layer7:anti-dot6\"",
		"block drop quick inet proto udp to port 853 label \"layer7:anti-doq\"",
		"block drop quick inet6 proto udp to port 853 label \"layer7:anti-doq6\"",
		"",
		"# Bloqueio por origem do cliente (quarentena/tag).",
		"block drop quick inet from <layer7_block> to any label \"layer7:block:src\"",
		"block drop quick inet6 from <layer7_block> to any label \"layer7:block:src6\"",
		"",
		"# Bloqueio por destino (sites/apps bloqueados via policies).",
		"block drop quick inet to <layer7_block_dst> label \"layer7:block:dst\"",
		"block drop quick inet6 to <layer7_block_dst> label \"layer7:block:dst6\"",
		"",
		"# layer7_tagged permanece disponivel para regras manuais/encadeadas."
	)) . "\n";
}

function layer7_pf_rules_text()
{
	$paths = array(
		layer7_pf_rules_path(),
		layer7_pf_rules_sample_path()
	);

	foreach ($paths as $path) {
		if (!is_string($path) || $path === "" || !is_readable($path)) {
			continue;
		}
		$raw = @file_get_contents($path);
		if (is_string($raw) && trim($raw) !== "") {
			return rtrim($raw, "\r\n") . "\n";
		}
	}

	return layer7_pf_default_rules_text();
}

function layer7_generate_rules($type)
{
	$type = (string)$type;
	if ($type !== "filter" && $type !== "rule") {
		return "";
	}

	$rules = layer7_pf_rules_text();

	$data = layer7_load_or_default();
	if (!empty($data["layer7"]["block_quic"])) {
		$rules .= "\n# Anti-QUIC: forcar fallback para TCP/TLS (melhor visibilidade SNI).\n";
		$rules .= "block drop quick inet proto udp to port 443 label \"layer7:anti-quic\"\n";
		$rules .= "block drop quick inet6 proto udp to port 443 label \"layer7:anti-quic6\"\n";
	}

	$bl_config = layer7_bl_config_load();
	if (!empty($bl_config["rules"])) {
		$rules .= "\n# Blacklist tables (persist across filter reloads)\n";
		foreach ($bl_config["rules"] as $idx => $brule) {
			$table = "layer7_bld_" . (int)$idx;
			$rules .= "table <{$table}> persist\n";
		}
		if (!empty($bl_config["enabled"])) {
			$rules .= "\n# Blacklist rules (per-source/CIDR)\n";
			foreach ($bl_config["rules"] as $idx => $brule) {
				if (empty($brule["enabled"])) {
					continue;
				}
				$table = "layer7_bld_" . (int)$idx;
				$rname = preg_replace('/[^a-zA-Z0-9_-]/', '', $brule["name"] ?? "rule{$idx}");
				if (!empty($brule["except_ips"])) {
					foreach ($brule["except_ips"] as $eip) {
						$eip = trim($eip);
						if ($eip !== "") {
							$rules .= "pass quick inet from {$eip} to <{$table}> label \"layer7:bl:except:{$rname}\"\n";
						}
					}
				}
				if (!empty($brule["src_cidrs"])) {
					foreach ($brule["src_cidrs"] as $cidr) {
						$cidr = trim($cidr);
						if ($cidr !== "") {
							$rules .= "block drop quick inet from {$cidr} to <{$table}> label \"layer7:bl:{$rname}\"\n";
						}
					}
				} else {
					$rules .= "block drop quick inet to <{$table}> label \"layer7:bl:{$rname}\"\n";
				}
			}
		}
	}

	return $rules;
}

function layer7_bare_config()
{
	return array(
		"layer7" => array(
			"enabled" => false,
			"mode" => "monitor",
			"log_level" => "info",
			"syslog_remote" => false,
			"syslog_remote_host" => "",
			"syslog_remote_port" => 514,
			"debug_minutes" => 0,
			"block_quic" => false,
			"interfaces" => array("lan"),
			"groups" => array(),
			"policies" => array(),
			"exceptions" => array()
		)
	);
}

function layer7_load_or_default()
{
	$cfgpath = layer7_cfg_path();
	$samplepath = layer7_sample_path();

	if (file_exists($cfgpath)) {
		$raw = file_get_contents($cfgpath);
		$j = json_decode($raw, true);
		if (is_array($j) && isset($j["layer7"]) && is_array($j["layer7"])) {
			return $j;
		}
	}
	if (file_exists($samplepath)) {
		$raw = file_get_contents($samplepath);
		$j = json_decode($raw, true);
		if (is_array($j) && isset($j["layer7"])) {
			return $j;
		}
	}
	return layer7_bare_config();
}

function layer7_save_json($data)
{
	global $input_errors;

	$cfgpath = layer7_cfg_path();
	$json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		$input_errors[] = l7_t("Erro ao serializar JSON.");
		return false;
	}

	if (!file_exists($cfgpath)) {
		$input_errors[] = l7_t("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
	if (!is_writable($cfgpath)) {
		$input_errors[] = l7_t("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
	if (file_put_contents($cfgpath, $json . "\n", LOCK_EX) === false) {
		$input_errors[] = l7_t("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
	return true;
}

function layer7_restart_service()
{
	$rc = "/usr/local/etc/rc.d/layer7d";
	if (!file_exists($rc)) {
		return false;
	}
	$cmd = "/bin/sh " . escapeshellarg($rc) . " onestop 2>/dev/null; sleep 1; /bin/sh " . escapeshellarg($rc) . " onestart 2>/dev/null";
	if (function_exists("mwexec")) {
		mwexec($cmd);
	} else {
		@shell_exec($cmd);
	}
	usleep(500000);
	$pidf = "/var/run/layer7d.pid";
	if (file_exists($pidf)) {
		$pid = trim(file_get_contents($pidf));
		if ($pid !== "" && ctype_digit($pid)) {
			exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $chk, $code);
			return ($code === 0);
		}
	}
	return false;
}

/**
 * Lista de dominios DoH/DoT para bloqueio via NXDOMAIN.
 */
function layer7_anti_doh_domains()
{
	return array(
		"mask.icloud.com", "mask-h2.icloud.com", "use-application-dns.net",
		"dns.google", "dns.google.com", "8888.google", "dns64.dns.google",
		"cloudflare-dns.com", "one.one.one.one", "1dot1dot1dot1.cloudflare-dns.com",
		"security.cloudflare-dns.com", "family.cloudflare-dns.com",
		"dns.quad9.net", "dns9.quad9.net", "dns10.quad9.net", "dns11.quad9.net",
		"dns.adguard.com", "dns-family.adguard.com", "dns-unfiltered.adguard.com",
		"doh.opendns.com", "doh.cleanbrowsing.org", "dns.nextdns.io",
		"doh.xfinity.com", "ordns.he.net"
	);
}

/**
 * Gera o bloco de texto anti-DoH para custom_options do Unbound.
 */
function layer7_anti_doh_block()
{
	$marker_start = "# --- Layer7 anti-DoH/Relay START ---";
	$marker_end = "# --- Layer7 anti-DoH/Relay END ---";
	$lines = array();
	$lines[] = $marker_start;
	$lines[] = "# Layer7 anti-DoH: NXDOMAIN para resolvers DoH conhecidos";
	foreach (layer7_anti_doh_domains() as $d) {
		$lines[] = "server:";
		$lines[] = "local-zone: \"{$d}.\" always_nxdomain";
	}
	$lines[] = $marker_end;
	return implode("\n", $lines) . "\n";
}

/**
 * Le custom_options do Unbound decodificando base64 (formato pfSense).
 * @return string texto decodificado (vazio se nao existir)
 */
function layer7_unbound_custom_options_read()
{
	global $config;
	if (!isset($config['unbound']['custom_options'])) {
		return "";
	}
	$raw = $config['unbound']['custom_options'];
	if (!is_string($raw) || $raw === "") {
		return "";
	}
	$decoded = @base64_decode($raw, true);
	if ($decoded === false) {
		return $raw;
	}
	return $decoded;
}

/**
 * Grava custom_options do Unbound codificando em base64 (formato pfSense).
 * @param string $text texto em claro
 */
function layer7_unbound_custom_options_write($text)
{
	global $config;
	$text = str_replace("\r\n", "\n", $text);
	$config['unbound']['custom_options'] = base64_encode($text);
}

/**
 * Configura Unbound anti-DoH via custom_options do pfSense config.xml.
 * Persiste entre reinicios do Unbound. Campo e base64 no config.xml.
 * @return array ("ok" => bool, "msg" => string)
 */
function layer7_configure_unbound_anti_doh()
{
	global $config;
	if (!function_exists("write_config")) {
		return array("ok" => false, "msg" => l7_t("Funcao write_config nao disponivel."));
	}
	if (!isset($config['unbound'])) {
		return array("ok" => false, "msg" => l7_t("Seccao Unbound nao encontrada no config.xml."));
	}
	$marker_start = "# --- Layer7 anti-DoH/Relay START ---";
	$existing = layer7_unbound_custom_options_read();
	if (strpos($existing, $marker_start) !== false) {
		return array("ok" => true, "msg" => l7_t("Overrides anti-DoH ja estao configurados."));
	}
	$block = layer7_anti_doh_block();
	$new_content = "";
	if ($existing !== "") {
		$new_content = rtrim($existing) . "\n\n";
	}
	$new_content .= $block;
	layer7_unbound_custom_options_write($new_content);
	write_config("Layer7: anti-DoH NXDOMAIN overrides");
	if (function_exists("services_unbound_configure")) {
		services_unbound_configure();
	}
	return array("ok" => true, "msg" => l7_t("Overrides anti-DoH configurados e Unbound reiniciado com sucesso."));
}

/**
 * Remove os overrides anti-DoH do custom_options do Unbound.
 * @return array ("ok" => bool, "msg" => string)
 */
function layer7_remove_unbound_anti_doh()
{
	global $config;
	if (!function_exists("write_config") || !isset($config['unbound'])) {
		return array("ok" => false, "msg" => l7_t("Config nao acessivel."));
	}
	$existing = layer7_unbound_custom_options_read();
	$marker_start = "# --- Layer7 anti-DoH/Relay START ---";
	$marker_end = "# --- Layer7 anti-DoH/Relay END ---";
	$pos_start = strpos($existing, $marker_start);
	if ($pos_start === false) {
		return array("ok" => true, "msg" => l7_t("Overrides anti-DoH nao encontrados."));
	}
	$pos_end = strpos($existing, $marker_end, $pos_start);
	if ($pos_end !== false) {
		$pos_end += strlen($marker_end);
		while ($pos_end < strlen($existing) && ($existing[$pos_end] === "\n" || $existing[$pos_end] === "\r")) {
			$pos_end++;
		}
		$existing = substr($existing, 0, $pos_start) . substr($existing, $pos_end);
	}
	layer7_unbound_custom_options_write(trim($existing));
	write_config("Layer7: anti-DoH overrides removed");
	if (function_exists("services_unbound_configure")) {
		services_unbound_configure();
	}
	return array("ok" => true, "msg" => l7_t("Overrides anti-DoH removidos do Unbound."));
}

/**
 * Verifica se os overrides anti-DoH estao configurados no Unbound.
 * Verifica no custom_options (base64) do config.xml e no ficheiro gerado.
 * @return bool
 */
function layer7_unbound_anti_doh_configured()
{
	global $config;
	$marker = "# --- Layer7 anti-DoH/Relay START ---";
	$decoded = layer7_unbound_custom_options_read();
	if ($decoded !== "" && strpos($decoded, $marker) !== false) {
		return true;
	}
	if (file_exists("/var/unbound/unbound.conf")) {
		$content = @file_get_contents("/var/unbound/unbound.conf");
		if ($content !== false && strpos($content, "Layer7 anti-DoH") !== false) {
			return true;
		}
	}
	return false;
}

function layer7_signal_reload()
{
	$pidf = "/var/run/layer7d.pid";
	if (!file_exists($pidf)) {
		return;
	}
	$pid = trim(file_get_contents($pidf));
	if ($pid !== "" && ctype_digit($pid)) {
		$cmd = "/bin/kill -HUP " . escapeshellarg($pid) . " 2>/dev/null";
		if (function_exists("mwexec")) {
			mwexec($cmd);
		} else {
			@shell_exec($cmd);
		}
	}
}

/**
 * CSV de interfaces (lan, opt1, ...) -> array unico; vazio -> [].
 * @return array|null null se invalido ou > $max itens
 */
function layer7_parse_interfaces_csv($csv, $max = 8)
{
	$s = trim((string)$csv);
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
		if (strlen($p) > 32 || preg_match('/^[a-zA-Z0-9_.]+$/', $p) !== 1) {
			return null;
		}
		if (count($out) >= $max) {
			return null;
		}
		if (!in_array($p, $out, true)) {
			$out[] = $p;
		}
	}
	return $out;
}

/* Saida de `layer7d -V` ou string vazia. */
function layer7_daemon_version()
{
	$bin = "/usr/local/sbin/layer7d";
	if (!is_executable($bin)) {
		return "";
	}
	exec(escapeshellarg($bin) . " -V 2>&1", $out, $code);
	return ($code === 0 && !empty($out[0])) ? trim($out[0]) : "";
}

/* id de politica: [A-Za-z0-9_-], 1-80 */
function layer7_policy_id_valid($id)
{
	return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
		&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
}

/* Nome de tabela PF (action=tag) */
function layer7_pf_table_name_valid($name)
{
	return is_string($name) && strlen($name) >= 1 && strlen($name) <= 63
		&& preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
}

/**
 * CSV -> array; cada token <= $maxlen; max. $max tokens.
 * @return array|null null se token demasiado longo
 */
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

/* IPv4 dotted quad (ex.: 10.0.0.1); cada octeto 0-255 */
function layer7_ipv4_valid($s)
{
	if (!is_string($s) || strlen($s) > 48) {
		return false;
	}
	if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $s, $m) !== 1) {
		return false;
	}
	for ($i = 1; $i <= 4; $i++) {
		if ((int)$m[$i] > 255) {
			return false;
		}
	}
	return true;
}

/* CIDR a.b.c.d/n (n 0-32) */
function layer7_cidr_valid($s)
{
	if (!is_string($s) || strlen($s) > 48) {
		return false;
	}
	if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d+)$/', $s, $m) !== 1) {
		return false;
	}
	$n = (int)$m[5];
	return $n >= 0 && $n <= 32;
}

/**
 * Host para syslog remoto (UDP): IPv4 ou hostname conservador.
 */
function layer7_syslog_remote_host_valid($s)
{
	if (!is_string($s)) {
		return false;
	}
	$s = trim($s);
	if (strlen($s) < 1 || strlen($s) > 255) {
		return false;
	}
	if (layer7_ipv4_valid($s)) {
		return true;
	}
	if (strpos($s, "..") !== false) {
		return false;
	}
	if ($s[0] === '.' || $s[strlen($s) - 1] === '.') {
		return false;
	}
	return preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9.-]{0,253}[a-zA-Z0-9])?$/', $s) === 1;
}

function layer7_policy_host_valid($s)
{
	if (!is_string($s)) {
		return false;
	}
	$s = strtolower(trim($s));
	if (strlen($s) < 1 || strlen($s) > 128) {
		return false;
	}
	if (strpos($s, "/") !== false || strpos($s, ":") !== false || strpos($s, " ") !== false) {
		return false;
	}
	if (strpos($s, "..") !== false) {
		return false;
	}
	if ($s[0] === '.') {
		$s = substr($s, 1);
	}
	if ($s === "" || $s[0] === '-' || substr($s, -1) === '-' || substr($s, -1) === '.') {
		return false;
	}
	return preg_match('/^[a-z0-9]([a-z0-9.-]{0,126}[a-z0-9])?$/', $s) === 1;
}

/**
 * Load nDPI protocol and category lists from layer7d --list-protos.
 * Caches result in /tmp for 1 hour.
 * @return array("protocols" => [...], "categories" => [...]) or empty arrays
 */
function layer7_ndpi_list()
{
	$cache = "/tmp/layer7-ndpi-protos.json";
	$bin = "/usr/local/sbin/layer7d";
	if (file_exists($cache) && (time() - filemtime($cache)) < 3600) {
		$j = json_decode(file_get_contents($cache), true);
		if (is_array($j) && isset($j["protocols"])) {
			return $j;
		}
	}
	if (!is_executable($bin)) {
		return array("protocols" => array(), "categories" => array());
	}
	$out = array();
	exec(escapeshellarg($bin) . " --list-protos 2>/dev/null", $out, $rc);
	if ($rc !== 0 || empty($out[0])) {
		return array("protocols" => array(), "categories" => array());
	}
	$j = json_decode($out[0], true);
	if (!is_array($j) || !isset($j["protocols"])) {
		return array("protocols" => array(), "categories" => array());
	}
	@file_put_contents($cache, $out[0]);
	return $j;
}

/**
 * Get pfSense configured interfaces as array of [ifid, descr, real].
 */
function layer7_get_pfsense_interfaces()
{
	global $config;

	$result = array();
	foreach (get_configured_interface_list(true) as $ifid => $ifdescr) {
		$real = get_real_interface($ifid);
		if (!$real) {
			continue;
		}

		$label = "";
		if (!empty($config["interfaces"][$ifid]["descr"]) && is_string($config["interfaces"][$ifid]["descr"])) {
			$label = trim($config["interfaces"][$ifid]["descr"]);
		}
		if ($label === "" && is_string($ifdescr)) {
			$label = trim($ifdescr);
		}
		if ($label === "") {
			$label = strtoupper((string)$ifid);
		}

		$result[] = array("ifid" => $ifid, "descr" => $label, "real" => $real);
	}
	return $result;
}

/**
 * Parse textarea with one IPv4 per line. Returns array of valid IPs (max 16).
 */
function layer7_parse_ip_textarea($text)
{
	$out = array();
	$lines = preg_split('/[\r\n]+/', trim((string)$text));
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === "" || $line[0] === '#')
			continue;
		if (layer7_ipv4_valid($line) && count($out) < 16) {
			$out[] = $line;
		}
	}
	return array_values(array_unique($out));
}

/**
 * Parse textarea with one CIDR per line. Returns array of valid CIDRs (max 8).
 */
function layer7_parse_cidr_textarea($text)
{
	$out = array();
	$lines = preg_split('/[\r\n]+/', trim((string)$text));
	foreach ($lines as $line) {
		$line = trim($line);
		if ($line === "" || $line[0] === '#')
			continue;
		if (layer7_cidr_valid($line) && count($out) < 8) {
			$out[] = $line;
		}
	}
	return array_values(array_unique($out));
}

function layer7_parse_host_textarea($text, $max = 16)
{
	$out = array();
	$lines = preg_split('/[\r\n,]+/', trim((string)$text));
	foreach ($lines as $line) {
		$line = strtolower(trim($line));
		if ($line === "" || $line[0] === '#') {
			continue;
		}
		if ($line[0] === '.') {
			$line = substr($line, 1);
		}
		if (layer7_policy_host_valid($line) && count($out) < $max) {
			$out[] = $line;
		}
	}
	return array_values(array_unique($out));
}

function layer7_stats_path()
{
	return "/tmp/layer7-stats.json";
}

/**
 * Trigger SIGUSR1 to refresh stats, then read the JSON file.
 * @return array|null parsed stats or null on failure
 */
function layer7_read_stats()
{
	$pidf = "/var/run/layer7d.pid";
	if (file_exists($pidf)) {
		$pid = trim(file_get_contents($pidf));
		if ($pid !== "" && ctype_digit($pid)) {
			$cmd = "/bin/kill -USR1 " . escapeshellarg($pid) . " 2>/dev/null";
			if (function_exists("mwexec")) {
				mwexec($cmd);
			} else {
				@shell_exec($cmd);
			}
			usleep(200000);
		}
	}
	$path = layer7_stats_path();
	if (!file_exists($path)) {
		return null;
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return null;
	}
	$j = json_decode($raw, true);
	return is_array($j) ? $j : null;
}

function layer7_profiles_path()
{
	return "/usr/local/etc/layer7/profiles.json";
}

/**
 * Load service profiles from profiles.json.
 * @return array of profile arrays, empty array on failure
 */
function layer7_load_profiles()
{
	$path = layer7_profiles_path();
	if (!file_exists($path)) {
		return array();
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return array();
	}
	$j = json_decode($raw, true);
	if (!is_array($j) || !isset($j["profiles"]) || !is_array($j["profiles"])) {
		return array();
	}
	return $j["profiles"];
}

/**
 * Parse schedule fields from POST into a schedule array for the policy JSON.
 * Returns the schedule array or null if no schedule configured.
 */
function layer7_parse_schedule_post($prefix)
{
	$days = array();
	$day_names = array("mon", "tue", "wed", "thu", "fri", "sat", "sun");
	foreach ($day_names as $d) {
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

/**
 * Format a schedule array for display in the policy list.
 */
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
		"thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom"
	);
	$labels = array();
	foreach ($days as $d) {
		$labels[] = isset($day_labels[$d]) ? $day_labels[$d] : $d;
	}
	return implode(",", $labels) . " " . $start . "-" . $end;
}

function layer7_group_id_valid($id)
{
	return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
		&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
}

function layer7_load_groups()
{
	$data = layer7_load_or_default();
	if (isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"])) {
		return $data["layer7"]["groups"];
	}
	return array();
}

function layer7_lic_path()
{
	return "/usr/local/etc/layer7.lic";
}

/**
 * Read license status from the daemon stats JSON.
 * Returns associative array with license_valid, license_expired, etc.
 * Falls back to running layer7d --fingerprint for hardware_id if stats missing.
 */
function layer7_read_license_status()
{
	$stats = layer7_read_stats();
	if (is_array($stats) && isset($stats["license_valid"])) {
		return array(
			"valid" => !empty($stats["license_valid"]),
			"expired" => !empty($stats["license_expired"]),
			"grace" => !empty($stats["license_grace"]),
			"dev_mode" => !empty($stats["license_dev_mode"]),
			"days_left" => isset($stats["license_days_left"]) ? (int)$stats["license_days_left"] : 0,
			"customer" => isset($stats["license_customer"]) ? $stats["license_customer"] : "",
			"expiry" => isset($stats["license_expiry"]) ? $stats["license_expiry"] : "",
			"hardware_id" => isset($stats["license_hardware_id"]) ? $stats["license_hardware_id"] : "",
			"error" => isset($stats["license_error"]) ? $stats["license_error"] : ""
		);
	}
	$hw = "";
	$bin = "/usr/local/sbin/layer7d";
	if (is_executable($bin)) {
		exec(escapeshellarg($bin) . " --fingerprint 2>/dev/null", $out, $rc);
		if ($rc === 0 && !empty($out[0])) {
			$hw = trim($out[0]);
		}
	}
	$lic_path = layer7_lic_path();
	$has_lic = file_exists($lic_path);
	return array(
		"valid" => false,
		"expired" => false,
		"grace" => false,
		"dev_mode" => false,
		"days_left" => 0,
		"customer" => "",
		"expiry" => "",
		"hardware_id" => $hw,
		"error" => $has_lic ? "daemon not running — cannot verify" : "no license file",
		"has_file" => $has_lic
	);
}

function layer7_nav_items()
{
	return array(
		"status" => array("label" => l7_t("Estado"), "href" => "layer7_status.php"),
		"settings" => array("label" => l7_t("Definicoes"), "href" => "layer7_settings.php"),
		"policies" => array("label" => l7_t("Politicas"), "href" => "layer7_policies.php"),
		"groups" => array("label" => l7_t("Grupos"), "href" => "layer7_groups.php"),
		"categories" => array("label" => l7_t("Categorias"), "href" => "layer7_categories.php"),
		"exceptions" => array("label" => l7_t("Excecoes"), "href" => "layer7_exceptions.php"),
		"test" => array("label" => l7_t("Teste"), "href" => "layer7_test.php"),
		"events" => array("label" => l7_t("Events"), "href" => "layer7_events.php"),
		"reports" => array("label" => l7_t("Relatorios"), "href" => "layer7_reports.php"),
		"diagnostics" => array("label" => l7_t("Diagnostics"), "href" => "layer7_diagnostics.php"),
		"blacklists" => array("label" => l7_t("Blacklists"), "href" => "layer7_blacklists.php")
	);
}

function layer7_render_styles()
{
	static $done = false;
	if ($done) {
		return;
	}
	$done = true;
	echo '<style>
	.layer7-page .panel-body { padding: 0; }
	.layer7-page .panel-title { font-size: 20px; font-weight: 600; }
	.layer7-page .layer7-content { padding: 24px 26px 26px; }
	.layer7-page .layer7-lead { color: #666; line-height: 1.55; margin: 0 0 22px; max-width: 980px; }
	.layer7-page .nav-tabs { margin: 0; padding: 10px 18px 0; border-bottom: 1px solid #e5e5e5; background: #fcfcfc; }
	.layer7-page .nav-tabs > li > a { padding: 11px 14px; font-weight: 600; }
	.layer7-page .form-horizontal .control-label { padding-top: 10px; }
	.layer7-page .form-control-static { padding-top: 8px; }
	.layer7-page .layer7-section { margin-top: 30px; }
	.layer7-page .layer7-section:first-child { margin-top: 0; }
	.layer7-page .layer7-section-title { margin: 0 0 16px; font-size: 24px; font-weight: 600; }
	.layer7-page .layer7-toolbar { margin: 18px 0 0; }
	.layer7-page .layer7-toolbar .btn { margin-right: 8px; margin-bottom: 8px; }
	.layer7-page .layer7-summary { margin-bottom: 18px; }
	.layer7-page .layer7-summary dt { width: 180px; }
	.layer7-page .layer7-summary dd { margin-left: 200px; margin-bottom: 10px; }
	.layer7-page .layer7-muted-note { margin-top: 16px; color: #777; }
	.layer7-page .layer7-table-actions .btn { margin-right: 6px; }
	.layer7-page .help-block { margin-bottom: 0; max-width: 760px; }
	.layer7-page .table > thead > tr > th { vertical-align: middle; }
	.layer7-page .pre-scrollable { margin-bottom: 0; background: #fbfbfb; border: 1px solid #ddd; padding: 12px; }
	.layer7-page .alert { margin-bottom: 18px; }
	.layer7-page .checkbox-inline,
	.layer7-page .radio-inline { padding-top: 0; }
	.layer7-page .layer7-inline-form .form-control { display: inline-block; width: auto; vertical-align: middle; }
	.layer7-page .layer7-inline-form .control-label { margin-right: 8px; }
	.layer7-page .layer7-inline-form .btn { margin-left: 8px; }
	.layer7-page .layer7-callout { background: #fbfbfb; border: 1px solid #e5e5e5; border-radius: 3px; padding: 16px 18px; }
	.layer7-page .layer7-callout + .layer7-callout { margin-top: 14px; }
	.layer7-page .table-responsive { margin-bottom: 0; }
	.layer7-page .l7-multiselect-wrap { border: 1px solid #ccc; border-radius: 3px; max-height: 220px; overflow-y: auto; padding: 4px 6px; background: #fff; }
	.layer7-page .l7-multiselect-wrap label { display: block; font-weight: normal; margin: 1px 0; padding: 2px 4px; cursor: pointer; }
	.layer7-page .l7-multiselect-wrap label:hover { background: #f0f5ff; }
	.layer7-page .l7-multiselect-wrap input[type=checkbox] { margin-right: 6px; }
	.layer7-page .l7-filter { margin-bottom: 6px; }
	.layer7-page .l7-bulk-tools { margin: 0 0 6px; }
	.layer7-page .l7-bulk-tools .btn { margin-right: 6px; margin-bottom: 6px; }
	.layer7-page .l7-detail-grid dt { width: 170px; }
	.layer7-page .l7-detail-grid dd { margin-left: 190px; margin-bottom: 10px; }
	</style>';
}

function layer7_render_tabs($active)
{
	$items = layer7_nav_items();
	echo '<ul class="nav nav-tabs">';
	foreach ($items as $key => $item) {
		$cls = ($key === $active) ? ' class="active"' : '';
		echo '<li' . $cls . '><a href="' . htmlspecialchars($item["href"]) . '">' .
		    htmlspecialchars($item["label"]) . '</a></li>';
	}
	echo '</ul>';
}

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

function layer7_render_footer()
{
	echo '<div style="text-align:center; padding:18px 0 8px; margin-top:24px; border-top:1px solid #e5e5e5; color:#888; font-size:12px;">';
	echo 'Layer7 para pfSense CE &mdash; ';
	echo '<a href="https://www.systemup.inf.br" target="_blank" style="color:#337ab7; text-decoration:none;">Systemup</a>';
	echo ' Solu&ccedil;&atilde;o em Tecnologia';
	echo '</div>';
}

/* ---- Blacklists helpers ---- */

define('L7_BL_DIR', '/usr/local/etc/layer7/blacklists');
define('L7_BL_CONFIG', L7_BL_DIR . '/config.json');
define('L7_BL_DISCOVERED', L7_BL_DIR . '/discovered.json');
define('L7_BL_PROGRESS', '/tmp/layer7-bl-progress.txt');
define('L7_BL_SCRIPT', '/usr/local/etc/layer7/update-blacklists.sh');

function layer7_bl_config_defaults()
{
	return array(
		"enabled" => false,
		"source_url" => "http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz",
		"auto_update" => false,
		"update_interval_hours" => 24,
		"whitelist" => array(),
		"rules" => array()
	);
}

function layer7_bl_config_load()
{
	$path = L7_BL_CONFIG;
	if (!file_exists($path)) {
		return layer7_bl_config_defaults();
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return layer7_bl_config_defaults();
	}
	$data = @json_decode($raw, true);
	if (!is_array($data)) {
		return layer7_bl_config_defaults();
	}
	$defaults = layer7_bl_config_defaults();
	foreach ($defaults as $k => $v) {
		if (!isset($data[$k])) {
			$data[$k] = $v;
		}
	}
	return $data;
}

function layer7_bl_config_save($config)
{
	@mkdir(L7_BL_DIR, 0755, true);
	$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	file_put_contents(L7_BL_CONFIG, $json . "\n", LOCK_EX);
}

function layer7_bl_discovered_load()
{
	$path = L7_BL_DISCOVERED;
	if (!file_exists($path)) {
		return null;
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return null;
	}
	$data = @json_decode($raw, true);
	if (!is_array($data) || !isset($data["categories"])) {
		return null;
	}
	return $data;
}

function layer7_bl_last_update()
{
	$path = L7_BL_DIR . '/last-update.txt';
	if (!file_exists($path)) {
		return null;
	}
	return trim(@file_get_contents($path));
}

function layer7_bl_download_start()
{
	@mkdir(L7_BL_DIR, 0755, true);
	$cmd = L7_BL_SCRIPT . " --download";
	if (function_exists("mwexec_bg")) {
		mwexec_bg($cmd);
	} else {
		@shell_exec($cmd . " > /dev/null 2>&1 &");
	}
}

function layer7_bl_download_status()
{
	if (file_exists(L7_BL_PROGRESS)) {
		return @file_get_contents(L7_BL_PROGRESS);
	}
	return "";
}

function layer7_bl_apply()
{
	if (function_exists("filter_configure")) {
		filter_configure();
	}
	$helper = layer7_pf_helper_path();
	if (is_executable($helper)) {
		@shell_exec($helper . " ensure 2>/dev/null");
	}
	layer7_signal_reload();
}

function layer7_bl_setup_cron($enabled, $hour)
{
	$cmd = L7_BL_SCRIPT;
	if (function_exists("install_cron_job")) {
		install_cron_job($cmd, $enabled, "0", (string)$hour, "*", "*", "*");
	}
}

function layer7_bl_get_stats()
{
	$stats = layer7_read_stats();
	if (!is_array($stats)) {
		return null;
	}
	$bl = array();
	$bl["enabled"] = isset($stats["bl_enabled"]) ? $stats["bl_enabled"] : false;
	$bl["domains_loaded"] = isset($stats["bl_domains_loaded"]) ? (int)$stats["bl_domains_loaded"] : 0;
	$bl["categories_active"] = isset($stats["bl_categories_active"]) ? (int)$stats["bl_categories_active"] : 0;
	$bl["lookups"] = isset($stats["bl_lookups"]) ? (int)$stats["bl_lookups"] : 0;
	$bl["hits"] = isset($stats["bl_hits"]) ? (int)$stats["bl_hits"] : 0;
	$bl["rules_active"] = isset($stats["bl_rules_active"]) ? (int)$stats["bl_rules_active"] : 0;
	$bl["top_categories"] = isset($stats["bl_top_categories"]) ? $stats["bl_top_categories"] : array();
	return $bl;
}

/* ---- Reports helpers ---- */

define('L7_REPORTS_DIR', '/usr/local/etc/layer7/reports');
define('L7_REPORTS_HISTORY', L7_REPORTS_DIR . '/stats-history.jsonl');
define('L7_REPORTS_COLLECT', '/usr/local/etc/layer7/layer7-stats-collect.sh');
define('L7_REPORTS_PURGE', '/usr/local/etc/layer7/layer7-stats-purge.sh');

function layer7_reports_defaults()
{
	return array(
		"enabled" => true,
		"retention_days" => 30,
		"collect_interval" => 5
	);
}

function layer7_reports_config()
{
	$data = layer7_load_or_default();
	if (isset($data["layer7"]["reports"]) && is_array($data["layer7"]["reports"])) {
		return array_merge(layer7_reports_defaults(), $data["layer7"]["reports"]);
	}
	return layer7_reports_defaults();
}

function layer7_reports_load_history($from_ts, $to_ts)
{
	$results = array();
	if (!file_exists(L7_REPORTS_HISTORY)) {
		return $results;
	}
	$fh = @fopen(L7_REPORTS_HISTORY, 'r');
	if (!$fh) {
		return $results;
	}
	while (($line = fgets($fh)) !== false) {
		$line = trim($line);
		if ($line === '') continue;
		$obj = @json_decode($line, true);
		if (!is_array($obj) || !isset($obj["ts"])) continue;
		$ts = (int)$obj["ts"];
		if ($ts >= $from_ts && $ts <= $to_ts) {
			$results[] = $obj;
		}
	}
	fclose($fh);
	return $results;
}

function layer7_reports_aggregate_traffic($data, $granularity = 3600)
{
	$buckets = array();
	foreach ($data as $row) {
		$ts = (int)$row["ts"];
		$bucket = intval(floor($ts / $granularity)) * $granularity;
		if (!isset($buckets[$bucket])) {
			$buckets[$bucket] = array("ts" => $bucket, "classified" => 0, "blocked" => 0, "allowed" => 0);
		}
		$buckets[$bucket]["classified"] = max($buckets[$bucket]["classified"], (int)($row["classified"] ?? 0));
		$buckets[$bucket]["blocked"] = max($buckets[$bucket]["blocked"], (int)($row["blocked"] ?? 0));
		$buckets[$bucket]["allowed"] = max($buckets[$bucket]["allowed"], (int)($row["allowed"] ?? 0));
	}
	ksort($buckets);

	$result = array();
	$prev = null;
	foreach ($buckets as $b) {
		$entry = array("ts" => $b["ts"]);
		if ($prev !== null) {
			$entry["classified"] = max(0, $b["classified"] - $prev["classified"]);
			$entry["blocked"] = max(0, $b["blocked"] - $prev["blocked"]);
			$entry["allowed"] = max(0, $b["allowed"] - $prev["allowed"]);
		} else {
			$entry["classified"] = 0;
			$entry["blocked"] = 0;
			$entry["allowed"] = 0;
		}
		$result[] = $entry;
		$prev = $b;
	}
	return $result;
}

function layer7_reports_aggregate_top($data, $field, $limit = 20)
{
	$merged = array();
	$last_snapshot = null;
	foreach ($data as $row) {
		if (!isset($row[$field]) || !is_array($row[$field])) continue;
		$last_snapshot = $row[$field];
	}
	if ($last_snapshot === null) return array();

	$key_field = ($field === "top_apps") ? "app" : (($field === "top_sources") ? "ip" : "cat");
	$count_field = ($field === "bl_top_cats") ? "hits" : "count";

	foreach ($data as $row) {
		if (!isset($row[$field]) || !is_array($row[$field])) continue;
		foreach ($row[$field] as $item) {
			if (!isset($item[$key_field])) continue;
			$k = $item[$key_field];
			$v = isset($item[$count_field]) ? (int)$item[$count_field] : 0;
			if (!isset($merged[$k]) || $v > $merged[$k]) {
				$merged[$k] = $v;
			}
		}
	}
	arsort($merged);
	$result = array();
	$i = 0;
	foreach ($merged as $name => $count) {
		if ($i >= $limit) break;
		$result[] = array("name" => $name, "count" => $count);
		$i++;
	}
	return $result;
}

function layer7_reports_parse_log($from_ts, $to_ts, $filters = array())
{
	$log_path = "/var/log/layer7d.log";
	$results = array();
	if (!file_exists($log_path)) return $results;

	$max_lines = 50000;
	$type_filter = isset($filters["type"]) ? $filters["type"] : null;
	$ip_filter = isset($filters["ip"]) ? $filters["ip"] : null;

	$fh = @fopen($log_path, 'r');
	if (!$fh) return $results;

	$count = 0;
	while (($line = fgets($fh)) !== false && $count < $max_lines) {
		$count++;
		$line = trim($line);
		if (strlen($line) < 22) continue;

		$ts_str = substr($line, 0, 19);
		$ts = @strtotime($ts_str);
		if ($ts === false || $ts < $from_ts || $ts > $to_ts) continue;

		$rest = substr($line, 20);
		$sev = "";
		if (preg_match('/^\[(\w+)\]\s*(.*)$/', $rest, $m)) {
			$sev = $m[1];
			$rest = $m[2];
		}

		$entry = array("ts" => $ts, "severity" => $sev, "raw" => $rest);

		if (strpos($rest, "flow_decide:") === 0) {
			$entry["type"] = "flow";
			preg_match_all('/(\w+)=(\S+)/', $rest, $kv);
			if (!empty($kv[1])) {
				$entry["fields"] = array_combine($kv[1], $kv[2]);
			}
		} elseif (strpos($rest, "bl_block:") === 0) {
			$entry["type"] = "blacklist";
			preg_match_all('/(\w+)=(\S+)/', $rest, $kv);
			if (!empty($kv[1])) {
				$entry["fields"] = array_combine($kv[1], $kv[2]);
			}
		} elseif (strpos($rest, "dns_block:") === 0) {
			$entry["type"] = "dns_block";
			preg_match_all('/(\w+)=(\S+)/', $rest, $kv);
			if (!empty($kv[1])) {
				$entry["fields"] = array_combine($kv[1], $kv[2]);
			}
		} elseif (strpos($rest, "enforce_block_dst:") === 0 || strpos($rest, "enforce_tag:") === 0) {
			$entry["type"] = "enforce";
			preg_match_all('/(\w+)=(\S+)/', $rest, $kv);
			if (!empty($kv[1])) {
				$entry["fields"] = array_combine($kv[1], $kv[2]);
			}
		} else {
			continue;
		}

		if ($type_filter !== null && $entry["type"] !== $type_filter) continue;
		if ($ip_filter !== null) {
			$found = false;
			if (isset($entry["fields"])) {
				foreach ($entry["fields"] as $fv) {
					if ($fv === $ip_filter) { $found = true; break; }
				}
			}
			if (!$found) continue;
		}

		$results[] = $entry;
	}
	fclose($fh);
	return $results;
}

function layer7_reports_setup_cron($enabled, $interval_minutes = 5)
{
	$cmd = "/bin/sh " . L7_REPORTS_COLLECT;
	if (function_exists("install_cron_job")) {
		install_cron_job($cmd, $enabled, "*/{$interval_minutes}", "*", "*", "*", "*");
	}
	$purge_cmd = "/bin/sh " . L7_REPORTS_PURGE;
	if (function_exists("install_cron_job")) {
		install_cron_job($purge_cmd, $enabled, "15", "3", "*", "*", "*");
	}
}

function layer7_reports_granularity_for_range($from_ts, $to_ts)
{
	$range = $to_ts - $from_ts;
	if ($range <= 172800) return 3600;
	if ($range <= 604800) return 21600;
	return 86400;
}
