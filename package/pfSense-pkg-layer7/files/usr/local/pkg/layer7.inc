<?php
/*
 * Funcoes partilhadas Layer 7 (JSON em /usr/local/etc/layer7.json).
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
		"# Bloqueio por origem do cliente (quarentena/tag).",
		"block drop quick inet from <layer7_block> to any label \"layer7:block:src\"",
		"block drop quick inet6 from <layer7_block> to any label \"layer7:block:src6\"",
		"",
		"# Bloqueio por destino (sites/apps bloqueados).",
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

	return layer7_pf_rules_text();
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

	if (!file_exists($cfgpath)) {
		$input_errors[] = gettext("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
	if (!is_writable($cfgpath)) {
		$input_errors[] = gettext("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
	if (file_put_contents($cfgpath, $json . "\n", LOCK_EX) === false) {
		$input_errors[] = gettext("Nao foi possivel gravar ") . $cfgpath;
		return false;
	}
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

function layer7_nav_items()
{
	return array(
		"status" => array("label" => gettext("Estado"), "href" => "layer7_status.php"),
		"settings" => array("label" => gettext("Definicoes"), "href" => "layer7_settings.php"),
		"policies" => array("label" => gettext("Politicas"), "href" => "layer7_policies.php"),
		"exceptions" => array("label" => gettext("Excecoes"), "href" => "layer7_exceptions.php"),
		"events" => array("label" => gettext("Events"), "href" => "layer7_events.php"),
		"diagnostics" => array("label" => gettext("Diagnostics"), "href" => "layer7_diagnostics.php")
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
