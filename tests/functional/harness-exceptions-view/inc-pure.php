<?php
/**
 * Carrega constantes e funcoes puras necessarias de layer7.inc (extraccao por
 * brace-match com strings ignoradas). Nao carrega o ficheiro inteiro. Usado
 * pelo harness V6a/V6b — nao e produto.
 *
 * Fronteiras externas (exec/ARP/leases) ficam em stubs deterministicos abaixo;
 * nunca dependem de binarios ou OS no ambiente de teste.
 */
if (!defined("L7HE_ROOT")) {
	return;
}

/** Valor errado que o bootstrap V6a usava antes da correcao V6b (prova divergencia). */
if (!defined("L7HE_VIP_MAX_HOSTS_WRONG_FIXTURE")) {
	define("L7HE_VIP_MAX_HOSTS_WRONG_FIXTURE", 64);
}

/** Versao fixa para export VIP em harness (sem executar layer7d). */
if (!defined("L7HE_DAEMON_VERSION_FIXTURE")) {
	define("L7HE_DAEMON_VERSION_FIXTURE", "layer7d-harness-fixture");
}

if (!function_exists("l7he_layer7_inc_path")) {
	function l7he_layer7_inc_path()
	{
		return L7HE_ROOT . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
	}
}

if (!function_exists("l7he_layer7_inc_source")) {
	function l7he_layer7_inc_source()
	{
		static $src = null;
		if ($src !== null) {
			return $src;
		}
		$inc = l7he_layer7_inc_path();
		if (!is_file($inc)) {
			fwrite(STDERR, "FAIL layer7.inc em falta: {$inc}\n");
			exit(1);
		}
		$loaded = file_get_contents($inc);
		if (!is_string($loaded) || $loaded === "") {
			fwrite(STDERR, "FAIL layer7.inc vazio: {$inc}\n");
			exit(1);
		}
		$src = $loaded;
		return $src;
	}
}

if (!function_exists("l7he_extract_function_src")) {
	/**
	 * Extrai corpo de funcao ignorando chavetas dentro de strings/comentarios.
	 */
	function l7he_extract_function_src($src, $func_name)
	{
		$pat = '/function\s+' . preg_quote($func_name, '/') . '\s*\(/';
		if (!preg_match($pat, $src, $m, PREG_OFFSET_CAPTURE)) {
			return null;
		}
		$start = (int)$m[0][1];
		$brace = strpos($src, "{", $start);
		if ($brace === false) {
			return null;
		}
		$depth = 0;
		$len = strlen($src);
		$in_squote = false;
		$in_dquote = false;
		$in_line_comment = false;
		$in_block_comment = false;
		for ($i = $brace; $i < $len; $i++) {
			$c = $src[$i];
			$next = ($i + 1 < $len) ? $src[$i + 1] : '';

			if ($in_line_comment) {
				if ($c === "\n") {
					$in_line_comment = false;
				}
				continue;
			}
			if ($in_block_comment) {
				if ($c === '*' && $next === '/') {
					$in_block_comment = false;
					$i++;
				}
				continue;
			}
			if ($in_squote) {
				if ($c === '\\' && $next !== '') {
					$i++;
					continue;
				}
				if ($c === "'") {
					$in_squote = false;
				}
				continue;
			}
			if ($in_dquote) {
				if ($c === '\\' && $next !== '') {
					$i++;
					continue;
				}
				if ($c === '"') {
					$in_dquote = false;
				}
				continue;
			}

			if ($c === '/' && $next === '/') {
				$in_line_comment = true;
				$i++;
				continue;
			}
			if ($c === '/' && $next === '*') {
				$in_block_comment = true;
				$i++;
				continue;
			}
			if ($c === "'") {
				$in_squote = true;
				continue;
			}
			if ($c === '"') {
				$in_dquote = true;
				continue;
			}

			if ($c === "{") {
				$depth++;
			} elseif ($c === "}") {
				$depth--;
				if ($depth === 0) {
					return substr($src, $start, $i - $start + 1);
				}
			}
		}
		return null;
	}
}

/* --- Stubs de fronteira externa (antes da extraccao; nunca exec/ARP reais) --- */

if (!function_exists("layer7_daemon_version")) {
	function layer7_daemon_version()
	{
		return L7HE_DAEMON_VERSION_FIXTURE;
	}
}

if (!function_exists("layer7_resolve_macs_to_ips")) {
	function layer7_resolve_macs_to_ips($macs)
	{
		$fixture = (isset($GLOBALS["l7he_mac_resolve_fixture"]) &&
		    is_array($GLOBALS["l7he_mac_resolve_fixture"]))
		    ? $GLOBALS["l7he_mac_resolve_fixture"] : array();
		$out = array();
		foreach ((array)$macs as $m) {
			$m = strtolower(trim((string)$m));
			if ($m === "" || !layer7_device_mac_valid($m) || !isset($fixture[$m])) {
				continue;
			}
			$ip = trim((string)$fixture[$m]);
			if ($ip !== "" && layer7_ipv4_valid($ip)) {
				$out[] = $ip;
			}
		}
		return array_values(array_unique($out));
	}
}

if (!function_exists("l7he_load_layer7_vip_constants")) {
	function l7he_load_layer7_vip_constants()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		$src = l7he_layer7_inc_source();
		$defs = array(
			"LAYER7_VIP_MAX_HOSTS" => null,
			"LAYER7_VIP_MAX_CIDRS" => null,
		);
		foreach (array_keys($defs) as $name) {
			if (defined($name)) {
				continue;
			}
			$pat = '/define\s*\(\s*["\']' . preg_quote($name, '/') .
			    '["\']\s*,\s*(\d+)\s*\)/';
			if (!preg_match($pat, $src, $m)) {
				fwrite(STDERR, "FAIL extrair {$name} de layer7.inc\n");
				exit(1);
			}
			define($name, (int)$m[1]);
		}
		$loaded = true;
	}
}

if (!function_exists("l7he_load_layer7_pure")) {
	function l7he_load_layer7_pure()
	{
		static $loaded = false;
		if ($loaded) {
			return;
		}
		l7he_load_layer7_vip_constants();
		$src = l7he_layer7_inc_source();
		$funcs = array(
			"layer7_ipv4_valid",
			"layer7_ipv6_valid",
			"layer7_ip_valid",
			"layer7_cidr_valid",
			"layer7_cidr6_valid",
			"layer7_cidr_any_valid",
			"layer7_ip_or_cidr_valid",
			"layer7_parse_ip_textarea",
			"layer7_parse_cidr_textarea",
			"layer7_policy_id_valid",
			"layer7_group_id_valid",
			"layer7_device_mac_valid",
			"layer7_policy_expand_src_origins",
			"layer7_vip_exception_id",
			"layer7_is_managed_vip_exception",
			"layer7_find_vip_exception",
			"layer7_find_vip_exception_index",
			"layer7_vip_sanitize_label",
			"layer7_vip_get_labels",
			"layer7_vip_labels_cleanup",
			"layer7_vip_validate_limits",
			"layer7_vip_direct_targets",
			"layer7_vip_list_entries",
			"layer7_vip_source_groups",
			"layer7_expand_groups_for_exception",
			"layer7_upsert_vip_exception",
			"layer7_vip_sync_direct_targets",
			"layer7_vip_add_entry",
			"layer7_vip_remove_entry",
			"layer7_vip_strip_bom",
			"layer7_vip_looks_like_json",
			"layer7_vip_json_decode_tolerant",
			"layer7_vip_is_header_line",
			"layer7_vip_csv_quote",
			"layer7_vip_parse_list_line",
			"layer7_vip_parse_list_text",
			"layer7_vip_import_apply",
			"layer7_vip_import_from_raw",
			"layer7_vip_export_payload",
			"layer7_vip_export_text",
			"layer7_pfsense_config_branch",
			"layer7_dhcp_static_map_label",
			"layer7_dhcp_static_maps_iface_label",
			"layer7_dhcp_static_maps",
			"layer7_dhcp_maps_group_by_iface",
			"layer7_dhcp_ip_iface_index",
			"layer7_vip_add_from_dhcp_ips",
			"layer7_real_interface_name",
		);
		foreach ($funcs as $fn) {
			if (function_exists($fn)) {
				continue;
			}
			$body = l7he_extract_function_src($src, $fn);
			if ($body === null) {
				fwrite(STDERR, "FAIL extrair {$fn} de layer7.inc\n");
				exit(1);
			}
			eval($body);
		}
		$loaded = true;
	}
}

l7he_load_layer7_pure();
