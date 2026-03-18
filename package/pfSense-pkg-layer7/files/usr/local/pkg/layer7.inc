<?php
/*
 * Funções partilhadas Layer 7 (JSON em /usr/local/etc/layer7.json).
 */
if (defined("LAYER7_INC_LOADED")) {
	return;
}
define("LAYER7_INC_LOADED", true);

function layer7_cfg_path()
{
	return "/usr/local/etc/layer7.json";
}

function layer7_sample_path()
{
	return "/usr/local/etc/layer7.json.sample";
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
			"interfaces" => array("lan"),
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
		$input_errors[] = gettext("Erro ao serializar JSON.");
		return false;
	}
	$tmp = $cfgpath . ".tmp." . getmypid();
	if (file_put_contents($tmp, $json . "\n") === false) {
		$input_errors[] = gettext("Não foi possível escrever ficheiro temporário.");
		return false;
	}
	if (!@rename($tmp, $cfgpath)) {
		@unlink($tmp);
		$input_errors[] = gettext("Não foi possível gravar ") . $cfgpath;
		return false;
	}
	@chmod($cfgpath, 0644);
	return true;
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

function layer7_csrf_token()
{
	if (empty($_SESSION["layer7_csrf"])) {
		$_SESSION["layer7_csrf"] = bin2hex(random_bytes(16));
	}
	return $_SESSION["layer7_csrf"];
}

function layer7_csrf_rotate()
{
	$_SESSION["layer7_csrf"] = bin2hex(random_bytes(16));
}

function layer7_csrf_verify_post()
{
	$tok = $_POST["form_token"] ?? "";
	return hash_equals(layer7_csrf_token(), $tok);
}

/**
 * CSV de interfaces (lan, opt1, …) → array único; vazio → [].
 * @return array|null null se inválido ou > $max itens
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

/** Saída de `layer7d -V` ou string vazia. */
function layer7_daemon_version()
{
	$bin = "/usr/local/sbin/layer7d";
	if (!is_executable($bin)) {
		return "";
	}
	exec(escapeshellarg($bin) . " -V 2>&1", $out, $code);
	return ($code === 0 && !empty($out[0])) ? trim($out[0]) : "";
}

/** id de política: [A-Za-z0-9_-], 1–80 */
function layer7_policy_id_valid($id)
{
	return is_string($id) && strlen($id) >= 1 && strlen($id) <= 80
		&& preg_match('/^[a-zA-Z0-9_-]+$/', $id) === 1;
}

/** Nome de tabela PF (action=tag) */
function layer7_pf_table_name_valid($name)
{
	return is_string($name) && strlen($name) >= 1 && strlen($name) <= 63
		&& preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
}

/**
 * CSV → array; cada token ≤ $maxlen; máx. $max tokens.
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

/** IPv4 dotted quad (ex.: 10.0.0.1); cada octeto 0–255 */
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

/** CIDR a.b.c.d/n (n 0–32) */
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
 * Host para syslog remoto (UDP): IPv4 ou hostname conservador (labels alfanum + -).
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
