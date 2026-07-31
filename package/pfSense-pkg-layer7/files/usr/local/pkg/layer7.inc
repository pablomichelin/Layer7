<?php
/*
 * Funcoes partilhadas Layer 7 (JSON em /usr/local/etc/layer7.json).
 */
if (defined("LAYER7_INC_LOADED")) {
	return;
}
define("LAYER7_INC_LOADED", true);

require_once(__DIR__ . "/layer7-fa47-icons.inc");

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

/**
 * Nome de interface PF aceitavel em regras geradas (alinhado ao anti-QUIC).
 * Rejeita caracteres inesperados evitam sintaxe invalida no ruleset.
 * Usado em `layer7_generate_rules` (anti-QUIC por interface), em
 * `layer7_generate_rdr_rules_snippet` (fallback `force_dns`) e no filtro
 * `array_filter` das interfaces efectivas.
 */
function layer7_pf_ifname_for_rules($s)
{
	if (!is_string($s) || $s === "") {
		return false;
	}
	return preg_match('/^[a-z][a-z0-9]*(\.[0-9]+)?$/i', $s) === 1;
}

/**
 * Converte o identificador amigavel do pfSense (lan, opt1, ...) no nome real
 * que libpcap/PF recebem (vmx0, igb1, vmx0.10, ...). Nomes ja reais sao
 * preservados. Nunca devolve texto fora do formato aceite para interfaces.
 */
function layer7_real_interface_name($ifname)
{
	$ifname = trim((string)$ifname);
	if ($ifname === "" || strlen($ifname) > 32 ||
	    preg_match('/^[a-zA-Z0-9_.]+$/', $ifname) !== 1) {
		return "";
	}

	$real = null;
	if (function_exists("get_real_interface")) {
		$real = get_real_interface($ifname);
	}
	if ((!is_string($real) || $real === "") &&
	    function_exists("convert_friendly_interface_to_real_interface_name")) {
		$real = convert_friendly_interface_to_real_interface_name($ifname);
	}
	if (is_string($real)) {
		$real = trim($real);
		if ($real !== "" && strlen($real) <= 32 &&
		    preg_match('/^[a-zA-Z0-9_.]+$/', $real) === 1) {
			return $real;
		}
	}
	return $ifname;
}

/**
 * Normaliza e deduplica uma lista de interfaces para consumo runtime.
 */
function layer7_runtime_interfaces_normalize($ifaces, $max = 8)
{
	$out = array();
	if (!is_array($ifaces)) {
		return $out;
	}
	foreach ($ifaces as $ifname) {
		$real = layer7_real_interface_name($ifname);
		if ($real === "" || in_array($real, $out, true)) {
			continue;
		}
		if (count($out) >= $max) {
			break;
		}
		$out[] = $real;
	}
	return $out;
}

/**
 * Migra todos os campos de interface que o daemon/PF consomem. A funcao e
 * pura: devolve uma copia e permite teste sem gravar ficheiros.
 */
function layer7_config_interfaces_normalize($data)
{
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return $data;
	}
	$l7 = &$data["layer7"];
	if (isset($l7["interfaces"])) {
		$l7["interfaces"] =
		    layer7_runtime_interfaces_normalize($l7["interfaces"]);
	}
	if (isset($l7["block_quic_interfaces"])) {
		$l7["block_quic_interfaces"] =
		    layer7_runtime_interfaces_normalize($l7["block_quic_interfaces"]);
	}
	if (isset($l7["reports"]["event_interfaces"])) {
		$l7["reports"]["event_interfaces"] =
		    layer7_runtime_interfaces_normalize(
		        $l7["reports"]["event_interfaces"]);
	}
	foreach (array("policies", "exceptions") as $section) {
		if (!isset($l7[$section]) || !is_array($l7[$section])) {
			continue;
		}
		foreach ($l7[$section] as &$rule) {
			if (is_array($rule) && isset($rule["interfaces"])) {
				$rule["interfaces"] =
				    layer7_runtime_interfaces_normalize($rule["interfaces"]);
			}
		}
		unset($rule);
	}
	unset($l7);
	return $data;
}

/**
 * Registo minimo de aviso (pfSense `log_error` ou fallback `error_log`).
 */
function layer7_log_pkg_warn($msg)
{
	if (function_exists("log_error")) {
		log_error($msg);
	} else {
		error_log($msg);
	}
}

/**
 * Le a primeira linha do pidfile do layer7d e devolve o PID (só dígitos) ou null.
 * Alinhado a `layer7d_pid_from_file` / `send_sighup` nos scripts sh (F4.1).
 *
 * @param string|null $pidf caminho absoluto; por defeito /var/run/layer7d.pid
 * @return string|null PID como string de dígitos ou null se ausente/ilegível/inválido
 */
function layer7_daemon_pid_from_file($pidf = null)
{
	if ($pidf === null || $pidf === "") {
		$pidf = "/var/run/layer7d.pid";
	}
	if (!is_string($pidf) || $pidf === "" || !file_exists($pidf) || !is_readable($pidf)) {
		return null;
	}
	$raw = @file_get_contents($pidf);
	if (!is_string($raw)) {
		return null;
	}
	$parts = preg_split('/\r\n|\r|\n/', $raw, 2);
	$line = isset($parts[0]) ? $parts[0] : "";
	$pid = trim($line);
	if ($pid === "" || !ctype_digit($pid)) {
		return null;
	}
	return $pid;
}

/**
 * Gera regras rdr para DNS forcado para CIDRs das regras blacklist com
 * force_dns=true. Requer acesso a pfSense's get_real_interface().
 * Pares (interface, CIDR) repetidos em mais do que uma regra sao emitidos
 * uma unica vez (evita linhas redundantes no anchor NAT).
 * A lista de interfaces efectivas e ordenada alfabeticamente para saida
 * estavel entre reloads (mesma configuracao, mesma ordem de `rdr`).
 * Os CIDRs IPv4 validos de cada regra sao unicos, ordenados alfabeticamente
 * e validados uma vez por regra antes de cruzar com as interfaces.
 * Retorna string vazia se nao houver regras rdr activas.
 */
function layer7_generate_rdr_rules_snippet()
{
	$bl_config = layer7_bl_config_load();
	if (empty($bl_config["rules"]) || !is_array($bl_config["rules"])) {
		return "";
	}

	$l7config = layer7_load_or_default();
	$iface_ids = isset($l7config["layer7"]["interfaces"]) &&
	    is_array($l7config["layer7"]["interfaces"])
	    ? $l7config["layer7"]["interfaces"]
	    : array("lan");

	$real_ifaces = array();
	foreach ($iface_ids as $ifid) {
		if (function_exists("get_real_interface")) {
			$real = get_real_interface($ifid);
		} else {
			$real = null;
		}
		if ($real) {
			$real_ifaces[] = $real;
		} elseif (layer7_pf_ifname_for_rules($ifid)) {
			$real_ifaces[] = $ifid;
		}
	}
	$real_ifaces = array_values(array_filter(
	    array_unique($real_ifaces),
	    "layer7_pf_ifname_for_rules"
	));
	sort($real_ifaces, SORT_STRING);
	if (empty($real_ifaces)) {
		return "";
	}

	$lines = array();
	$lines[] = "";
	$lines[] = "# DNS forcado para CIDRs com blacklist (force_dns).";
	$has_rules = false;
	$seen_rdr = array();
	foreach ($bl_config["rules"] as $rule) {
		if (!isset($rule["enabled"]) || !$rule["enabled"]) {
			continue;
		}
		if (empty($rule["force_dns"])) {
			continue;
		}
		if (empty($rule["src_cidrs"]) || !is_array($rule["src_cidrs"])) {
			continue;
		}
		$cidrs_ok = array();
		foreach ($rule["src_cidrs"] as $craw) {
			$cidr = trim((string)$craw);
			if ($cidr === "") {
				continue;
			}
			if (!layer7_cidr_valid($cidr) && !layer7_ipv4_valid($cidr)) {
				continue;
			}
			$cidrs_ok[] = $cidr;
		}
		$cidrs_ok = array_values(array_unique($cidrs_ok));
		sort($cidrs_ok, SORT_STRING);
		if (empty($cidrs_ok)) {
			continue;
		}
		foreach ($real_ifaces as $iface) {
			foreach ($cidrs_ok as $cidr) {
				$key = $iface . "\0" . $cidr;
				if (isset($seen_rdr[$key])) {
					continue;
				}
				$seen_rdr[$key] = true;
				$has_rules = true;
			$lines[] = "rdr on {$iface} inet proto udp" .
			    " from {$cidr} to !127.0.0.1 port 53" .
			    " -> 127.0.0.1";
			$lines[] = "rdr on {$iface} inet proto tcp" .
			    " from {$cidr} to !127.0.0.1 port 53" .
			    " -> 127.0.0.1";
			}
		}
	}
	if (!$has_rules) {
		return "";
	}
	return implode("\n", $lines) . "\n";
}

/**
 * Limpa o sub-anchor legado natrules/layer7_nat. Ate a 1.8.11_44 as regras
 * rdr eram carregadas neste anchor via pfctl, mas o ruleset principal do
 * pfSense so declara `nat-anchor "natrules/*"` (sem `rdr-anchor`), pelo que
 * as regras rdr nunca eram avaliadas pelo PF — o rdr :80 da block page e o
 * DNS forcado :53 ficavam mortos. A partir da _45 as regras rdr entram no
 * ruleset principal via layer7_generate_rules("nat") (hook
 * filter_rule_function / discover_pkg_rules — mesmo mecanismo do proxy
 * transparente do Squid). Este flush remove residuos do modelo antigo.
 */
function layer7_flush_legacy_nat_anchor()
{
	@exec("/sbin/pfctl -a natrules/layer7_nat -F Nat 2>/dev/null");
}

/**
 * Mantido por retrocompatibilidade com layer7.xml (nat_rules_needed).
 * As regras rdr sao entregues por layer7_generate_rules("nat").
 */
function layer7_generate_nat_rules()
{
	return layer7_generate_nat_anchor_snippet();
}

/**
 * Snippet NAT completo (force_dns + pagina de bloqueio) para o ruleset
 * principal do pfSense, via discover_pkg_rules("nat").
 */
function layer7_generate_nat_anchor_snippet()
{
	$parts = array();
	$dns = layer7_generate_rdr_rules_snippet();
	if ($dns !== "") {
		$parts[] = trim($dns);
	}
	$bp = layer7_generate_blockpage_rdr_snippet();
	if ($bp !== "") {
		$parts[] = trim($bp);
	}
	if (empty($parts)) {
		return "";
	}
	return implode("\n", $parts) . "\n";
}

/**
 * Decide se o produto deve emitir regras `block drop` do Layer7.
 * Regra unica e auditavel: enabled=true E mode=="enforce".
 * Em qualquer outro estado (monitor / disabled / mode em falta) o snippet PF
 * fica passivo: apenas as tabelas `persist` sao mantidas para o daemon poder
 * operar / fazer self-heal, mas nenhuma regra de bloqueio do core e emitida.
 */
function layer7_pf_should_enforce($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return false;
	}
	$l7 = $data["layer7"];
	if (empty($l7["enabled"])) {
		return false;
	}
	$mode = isset($l7["mode"]) ? (string)$l7["mode"] : "monitor";
	return ($mode === "enforce");
}

/**
 * Modelo de enforcement: legacy_global (default) ou scoped_hybrid (Caminho B).
 */
function layer7_enforcement_model_get($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return "legacy_global";
	}
	$em = isset($data["layer7"]["enforcement_model"])
	    ? (string)$data["layer7"]["enforcement_model"] : "legacy_global";
	return ($em === "scoped_hybrid") ? "scoped_hybrid" : "legacy_global";
}

function layer7_enforcement_is_scoped_hybrid($data = null)
{
	return layer7_enforcement_model_get($data) === "scoped_hybrid";
}

/**
 * Ordena copia de politicas como layer7_policies_sort() (priority desc, id asc).
 */
function layer7_policies_sorted_copy($policies)
{
	if (!is_array($policies)) {
		return array();
	}
	$sorted = array_values($policies);
	usort($sorted, function ($a, $b) {
		$pa = isset($a["priority"]) ? (int)$a["priority"] : 0;
		$pb = isset($b["priority"]) ? (int)$b["priority"] : 0;
		if ($pa !== $pb) {
			return $pb - $pa;
		}
		$ida = isset($a["id"]) ? (string)$a["id"] : "";
		$idb = isset($b["id"]) ? (string)$b["id"] : "";
		return strcmp($ida, $idb);
	});
	return $sorted;
}

/**
 * Expande grupos da politica em src_hosts/src_cidrs (alinhado a policy.c).
 *
 * @return array{hosts: string[], cidrs: string[]}
 */
function layer7_policy_expand_src_origins($policy, $groups)
{
	$match = (isset($policy["match"]) && is_array($policy["match"]))
	    ? $policy["match"] : array();
	$hosts = (isset($match["src_hosts"]) && is_array($match["src_hosts"]))
	    ? $match["src_hosts"] : array();
	$cidrs = (isset($match["src_cidrs"]) && is_array($match["src_cidrs"]))
	    ? $match["src_cidrs"] : array();

	if (!empty($match["groups"]) && is_array($match["groups"]) &&
	    is_array($groups)) {
		foreach ($groups as $g) {
			if (!is_array($g) || empty($g["id"]) ||
			    !in_array((string)$g["id"], $match["groups"], true)) {
				continue;
			}
			if (isset($g["cidrs"]) && is_array($g["cidrs"])) {
				foreach ($g["cidrs"] as $c) {
					$c = trim((string)$c);
					if ($c !== "" &&
					    (layer7_cidr_valid($c) || layer7_ipv4_valid($c))) {
						$cidrs[] = $c;
					}
				}
			}
			foreach (array("hosts", "device_ips") as $gk) {
				if (!isset($g[$gk]) || !is_array($g[$gk])) {
					continue;
				}
				foreach ($g[$gk] as $h) {
					$h = trim((string)$h);
					if ($h !== "" && layer7_ipv4_valid($h)) {
						$hosts[] = $h;
					}
				}
			}
		}
	}

	$hosts = array_values(array_unique($hosts));
	$cidrs = array_values(array_unique($cidrs));
	return array("hosts" => $hosts, "cidrs" => $cidrs);
}

/**
 * Expande src_exclude_groups em hosts/cidrs (alinhado a policy.c / ADR-0019).
 *
 * @return array{hosts: string[], cidrs: string[]}
 */
function layer7_policy_expand_src_excludes($policy, $groups)
{
	$match = (isset($policy["match"]) && is_array($policy["match"]))
	    ? $policy["match"] : array();
	$hosts = array();
	$cidrs = (isset($match["src_exclude_cidrs"]) &&
	    is_array($match["src_exclude_cidrs"])) ? $match["src_exclude_cidrs"] :
	    array();

	if (!empty($match["src_exclude_groups"]) &&
	    is_array($match["src_exclude_groups"]) && is_array($groups)) {
		foreach ($groups as $g) {
			if (!is_array($g) || empty($g["id"]) ||
			    !in_array((string)$g["id"],
			    $match["src_exclude_groups"], true)) {
				continue;
			}
			if (isset($g["cidrs"]) && is_array($g["cidrs"])) {
				foreach ($g["cidrs"] as $c) {
					$c = trim((string)$c);
					if ($c !== "" &&
					    (layer7_cidr_valid($c) ||
					    layer7_ipv4_valid($c))) {
						$cidrs[] = $c;
					}
				}
			}
			foreach (array("hosts", "device_ips") as $gk) {
				if (!isset($g[$gk]) || !is_array($g[$gk])) {
					continue;
				}
				foreach ($g[$gk] as $h) {
					$h = trim((string)$h);
					if ($h !== "" && layer7_ipv4_valid($h)) {
						$hosts[] = $h;
					}
				}
			}
		}
	}

	$hosts = array_values(array_unique($hosts));
	$cidrs = array_values(array_unique($cidrs));
	return array("hosts" => $hosts, "cidrs" => $cidrs);
}

/**
 * Detecta conflito include/exclude na mesma origem (GUI BG-066).
 */
function layer7_policy_src_include_exclude_conflict($policy, $groups)
{
	$inc = layer7_policy_expand_src_origins($policy, $groups);
	$exc = layer7_policy_expand_src_excludes($policy, $groups);
	if (empty($inc["hosts"]) && empty($inc["cidrs"])) {
		return false;
	}
	foreach ($exc["hosts"] as $h) {
		if (in_array($h, $inc["hosts"], true)) {
			return true;
		}
	}
	foreach ($exc["cidrs"] as $ec) {
		foreach ($inc["cidrs"] as $ic) {
			if ($ec === $ic) {
				return true;
			}
		}
		foreach ($inc["hosts"] as $ih) {
			if (layer7_ipv4_in_cidr($ih, $ec)) {
				return true;
			}
		}
	}
	foreach ($exc["hosts"] as $eh) {
		foreach ($inc["cidrs"] as $ic) {
			if (layer7_ipv4_in_cidr($eh, $ic)) {
				return true;
			}
		}
	}
	$match = (isset($policy["match"]) && is_array($policy["match"]))
	    ? $policy["match"] : array();
	$ig = (isset($match["groups"]) && is_array($match["groups"]))
	    ? $match["groups"] : array();
	$eg = (isset($match["src_exclude_groups"]) &&
	    is_array($match["src_exclude_groups"]))
	    ? $match["src_exclude_groups"] : array();
	foreach ($ig as $gid) {
		if (in_array($gid, $eg, true)) {
			return true;
		}
	}
	return false;
}

/**
 * Metadados de politicas block para PF escopado (indice = posicao ordenada).
 *
 * @return array<int, array{idx: int, policy: array, needs_pdst: bool,
 *     needs_psrc: bool, src_hosts: string[], src_cidrs: string[],
 *     scope_global: bool, quarantine_origin: bool}>
 */
function layer7_policy_enforcement_meta($data)
{
	$out = array();
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return $out;
	}
	$l7 = $data["layer7"];
	$policies = (isset($l7["policies"]) && is_array($l7["policies"]))
	    ? $l7["policies"] : array();
	$groups = (isset($l7["groups"]) && is_array($l7["groups"]))
	    ? $l7["groups"] : array();
	$sorted = layer7_policies_sorted_copy($policies);

	foreach ($sorted as $idx => $policy) {
		if (!is_array($policy) || empty($policy["enabled"])) {
			continue;
		}
		if (!isset($policy["action"]) || (string)$policy["action"] !== "block") {
			continue;
		}
		$match = (isset($policy["match"]) && is_array($policy["match"]))
		    ? $policy["match"] : array();
		$hosts = (isset($match["hosts"]) && is_array($match["hosts"]))
		    ? $match["hosts"] : array();
		$apps = (isset($match["ndpi_app"]) && is_array($match["ndpi_app"]))
		    ? $match["ndpi_app"] : array();
		$cats = (isset($match["ndpi_category"]) &&
		    is_array($match["ndpi_category"])) ? $match["ndpi_category"] : array();

		$has_app_match = !empty($apps) || !empty($cats);
		$quarantine_origin =
		    !empty($policy["quarantine_origin"]) ||
		    !empty($policy["quarantine"]);
		/*
		 * App/categoria usa o destino observado por defeito. psrc corta
		 * toda a Internet da origem e fica reservado à quarentena opt-in.
		 * Uma politica mista com quarentena precisa das duas tabelas:
		 * host -> pdst; app/categoria -> psrc.
		 */
		$needs_pdst = !empty($hosts) ||
		    ($has_app_match && !$quarantine_origin);
		$needs_psrc = $has_app_match && $quarantine_origin;
		if (!$needs_pdst && !$needs_psrc) {
			if (!empty($policy["scope_global"]) ||
			    $quarantine_origin) {
				$needs_psrc = true;
			} else {
				continue;
			}
		}

		$src = layer7_policy_expand_src_origins($policy, $groups);
		$exc = layer7_policy_expand_src_excludes($policy, $groups);
		$out[] = array(
			"idx" => (int)$idx,
			"policy" => $policy,
			"needs_pdst" => $needs_pdst,
			"needs_psrc" => $needs_psrc,
			"src_hosts" => $src["hosts"],
			"src_cidrs" => $src["cidrs"],
			"exclude_hosts" => $exc["hosts"],
			"exclude_cidrs" => $exc["cidrs"],
			"scope_global" => !empty($policy["scope_global"]),
			"quarantine_origin" => $quarantine_origin
		);
	}
	return $out;
}

/**
 * Nomes de tabelas layer7_pdst_* / layer7_psrc_* activas na config actual.
 */
function layer7_policy_enforcement_table_names($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$tables = array();
	foreach (layer7_policy_enforcement_meta($data) as $m) {
		$idx = (int)$m["idx"];
		if (!empty($m["needs_pdst"])) {
			$tables[] = "layer7_pdst_" . $idx;
		}
		if (!empty($m["needs_psrc"])) {
			$tables[] = "layer7_psrc_" . $idx;
		}
		if (!empty($m["needs_pdst"]) &&
		    (!empty($m["exclude_hosts"]) || !empty($m["exclude_cidrs"]))) {
			$tables[] = "layer7_pexc_" . $idx;
		}
	}
	foreach (layer7_policy_allow_meta($data) as $m) {
		$tables[] = "layer7_pallow_" . (int)$m["idx"];
	}
	return array_values(array_unique($tables));
}

/**
 * Metadados das politicas allow. A tabela positiva e sempre por politica:
 * o daemon so inclui o destino depois de a regra ter vencido no policy
 * engine, preservando first-match sem abrir bypass para outros destinos.
 */
function layer7_policy_allow_meta($data)
{
	$out = array();
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return $out;
	}
	$l7 = $data["layer7"];
	$policies = (isset($l7["policies"]) && is_array($l7["policies"]))
	    ? $l7["policies"] : array();
	$groups = (isset($l7["groups"]) && is_array($l7["groups"]))
	    ? $l7["groups"] : array();

	foreach (layer7_policies_sorted_copy($policies) as $idx => $policy) {
		if (!is_array($policy) || empty($policy["enabled"]) ||
		    (string)($policy["action"] ?? "") !== "allow") {
			continue;
		}
		$src = layer7_policy_expand_src_origins($policy, $groups);
		$ifaces = array();
		$match = (isset($policy["match"]) && is_array($policy["match"]))
		    ? $policy["match"] : array();
		$raw_ifaces = (isset($match["interfaces"]) &&
		    is_array($match["interfaces"])) ? $match["interfaces"] :
		    ((isset($policy["interfaces"]) &&
		    is_array($policy["interfaces"])) ? $policy["interfaces"] :
		    array());
		foreach ($raw_ifaces as $iface) {
			$iface = trim((string)$iface);
			if ($iface !== "" && layer7_pf_ifname_for_rules($iface)) {
				$ifaces[] = $iface;
			}
		}
		$out[] = array(
			"idx" => (int)$idx,
			"policy" => $policy,
			"src_hosts" => $src["hosts"],
			"src_cidrs" => $src["cidrs"],
			"interfaces" => array_values(array_unique($ifaces))
		);
	}
	return $out;
}

/**
 * Excecoes allow sao bypass total do policy engine para uma origem. No PF,
 * o bypass continua limitado aos destinos que o proprio Layer7 bloqueia.
 */
function layer7_exception_allow_meta($data)
{
	$out = array();
	if (!is_array($data) || !isset($data["layer7"]) ||
	    !is_array($data["layer7"])) {
		return $out;
	}
	$exceptions = (isset($data["layer7"]["exceptions"]) &&
	    is_array($data["layer7"]["exceptions"]))
	    ? array_values($data["layer7"]["exceptions"]) : array();
	usort($exceptions, function ($a, $b) {
		$pa = isset($a["priority"]) ? (int)$a["priority"] : 0;
		$pb = isset($b["priority"]) ? (int)$b["priority"] : 0;
		if ($pa !== $pb) {
			return $pb - $pa;
		}
		return strcmp((string)($a["id"] ?? ""),
		    (string)($b["id"] ?? ""));
	});

	foreach ($exceptions as $idx => $exception) {
		if (!is_array($exception) ||
		    (array_key_exists("enabled", $exception) &&
		    empty($exception["enabled"])) ||
		    (string)($exception["action"] ?? "allow") !== "allow") {
			continue;
		}
		$entries = array();
		$hosts = (isset($exception["hosts"]) &&
		    is_array($exception["hosts"])) ? $exception["hosts"] :
		    (isset($exception["host"]) ? array($exception["host"]) :
		    array());
		$cidrs = (isset($exception["cidrs"]) &&
		    is_array($exception["cidrs"])) ? $exception["cidrs"] :
		    (isset($exception["cidr"]) ? array($exception["cidr"]) :
		    array());
		foreach ($hosts as $host) {
			$host = trim((string)$host);
			if (layer7_ipv4_valid($host)) {
				$entries[] = $host;
			}
		}
		foreach ($cidrs as $cidr) {
			$cidr = trim((string)$cidr);
			if (layer7_cidr_valid($cidr) || layer7_ipv4_valid($cidr)) {
				$entries[] = $cidr;
			}
		}
		if (empty($entries)) {
			continue;
		}
		$ifaces = array();
		if (!empty($exception["interfaces"]) &&
		    is_array($exception["interfaces"])) {
			foreach ($exception["interfaces"] as $iface) {
				$iface = trim((string)$iface);
				if ($iface !== "" &&
				    layer7_pf_ifname_for_rules($iface)) {
					$ifaces[] = $iface;
				}
			}
		}
		$out[] = array(
			"idx" => (int)$idx,
			"exception" => $exception,
			"entries" => array_values(array_unique($entries)),
			"interfaces" => array_values(array_unique($ifaces))
		);
	}
	return $out;
}

/**
 * Declaracoes dinamicas antes de qualquer regra que as referencie.
 */
function layer7_pf_managed_dynamic_tables_text($data, $bl_config = null)
{
	$lines = array();
	foreach (layer7_policy_enforcement_meta($data) as $m) {
		if (!empty($m["needs_pdst"])) {
			$lines[] = "table <layer7_pdst_" . (int)$m["idx"] .
			    "> persist";
		}
		if (!empty($m["needs_psrc"])) {
			$lines[] = "table <layer7_psrc_" . (int)$m["idx"] .
			    "> persist";
		}
	}
	foreach (layer7_policy_allow_meta($data) as $m) {
		$lines[] = "table <layer7_pallow_" . (int)$m["idx"] .
		    "> persist";
	}
	foreach (layer7_policy_enforcement_meta($data) as $m) {
		if (empty($m["needs_pdst"])) {
			continue;
		}
		$entries = array();
		foreach ($m["exclude_hosts"] as $h) {
			if (layer7_ipv4_valid($h)) {
				$entries[] = $h;
			}
		}
		foreach ($m["exclude_cidrs"] as $c) {
			if (layer7_cidr_valid($c) || layer7_ipv4_valid($c)) {
				$entries[] = $c;
			}
		}
		if (empty($entries)) {
			continue;
		}
		$lines[] = "table <layer7_pexc_" . (int)$m["idx"] .
		    "> persist { " . implode(", ", array_unique($entries)) . " }";
	}
	foreach (layer7_exception_allow_meta($data) as $m) {
		$lines[] = "table <layer7_exc_allow_" . (int)$m["idx"] .
		    "> persist { " . implode(", ", $m["entries"]) . " }";
	}
	if ($bl_config === null) {
		$bl_config = layer7_bl_config_load();
	}
	if (!empty($bl_config["rules"]) && is_array($bl_config["rules"])) {
		foreach ($bl_config["rules"] as $idx => $rule) {
			$lines[] = "table <layer7_bld_" . (int)$idx .
			    "> persist";
		}
	}
	foreach (layer7_blacklist_source_scope_meta($bl_config) as $m) {
		$lines[] = "table <" . $m["table"] . "> persist { " .
		    implode(", ", $m["entries"]) . " }";
	}
	$lines = array_values(array_unique($lines));
	if (empty($lines)) {
		return "";
	}
	return "\n# Layer7 managed policy/blacklist tables\n" .
	    implode("\n", $lines) . "\n";
}

function layer7_policy_allow_rules_text($data)
{
	if (!layer7_pf_should_enforce($data)) {
		return "";
	}
	$rules = "";
	foreach (layer7_policy_allow_meta($data) as $m) {
		$idx = (int)$m["idx"];
		$policy = $m["policy"];
		$pid = preg_replace('/[^a-zA-Z0-9_-]/', '',
		    (string)($policy["id"] ?? "p{$idx}"));
		$pid = ($pid === "") ? "p{$idx}" : $pid;
		$sources = array_merge($m["src_hosts"], $m["src_cidrs"]);
		if (empty($sources)) {
			$sources = array(null);
		}
		$ifaces = empty($m["interfaces"]) ? array(null) :
		    $m["interfaces"];
		foreach ($ifaces as $iface) {
			$on = ($iface === null) ? "" : "on {$iface} ";
			foreach ($sources as $source) {
				$from = ($source === null) ? "" : "from {$source} ";
				$rules .= "match {$on}inet {$from}" .
				    "to <layer7_pallow_{$idx}> " .
				    "tag L7ALLOW label \"layer7:pallow:{$pid}\"\n";
			}
		}
	}
	return $rules;
}

function layer7_exception_allow_rules_text($data, $bl_config = null)
{
	if (!layer7_pf_should_enforce($data)) {
		return "";
	}
	$rules = "";
	foreach (layer7_exception_allow_meta($data) as $m) {
		$idx = (int)$m["idx"];
		$exception = $m["exception"];
		$eid = preg_replace('/[^a-zA-Z0-9_-]/', '',
		    (string)($exception["id"] ?? "e{$idx}"));
		$eid = ($eid === "") ? "e{$idx}" : $eid;
		$ifaces = empty($m["interfaces"]) ? array(null) :
		    $m["interfaces"];
		foreach ($ifaces as $iface) {
			$on = ($iface === null) ? "" : "on {$iface} ";
			$rules .= "match {$on}inet " .
			    "from <layer7_exc_allow_{$idx}> to !<localsubnets> " .
			    "tag L7ALLOW label \"layer7:exc-allow:{$eid}\"\n";
		}
	}
	return $rules;
}

/**
 * Regras e tabelas PF escopadas por politica (Caminho B / E2).
 * So em scoped_hybrid + enforce; legacy_global permanece inalterado.
 */
function layer7_policy_enforcement_rules_text($data, $include_tables = true)
{
	if (!layer7_enforcement_is_scoped_hybrid($data) ||
	    !layer7_pf_should_enforce($data)) {
		return "";
	}

	$tables = "";
	$rules = "";
	foreach (layer7_policy_enforcement_meta($data) as $m) {
		$idx = (int)$m["idx"];
		$policy = $m["policy"];
		$pid = isset($policy["id"])
		    ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$policy["id"])
		    : "";
		if ($pid === "") {
			$pid = "p" . $idx;
		}

		$has_src = !empty($m["src_hosts"]) || !empty($m["src_cidrs"]);
		$scope_global = !empty($m["scope_global"]);
		$quarantine_origin = !empty($m["quarantine_origin"]);

		if (!empty($m["needs_pdst"])) {
			$tbl = "layer7_pdst_" . $idx;
			$tables .= "table <{$tbl}> persist\n";
			/* O match pexc tem de preceder os blocks `quick` da mesma
			 * politica: a tag L7ALLOW so protege se ja estiver aplicada
			 * quando o pacote chega ao block (ADR-0019). */
			if (!empty($m["exclude_hosts"]) ||
			    !empty($m["exclude_cidrs"])) {
				$rules .= "match inet from <layer7_pexc_{$idx}> " .
				    "to <layer7_pdst_{$idx}> tag L7ALLOW " .
				    "label \"layer7:pexc:{$pid}\"\n";
			}
			if ($has_src) {
				foreach ($m["src_hosts"] as $sh) {
					if (layer7_ipv4_valid($sh)) {
						$rules .= "block drop quick inet from {$sh} " .
						    "to <{$tbl}> ! tagged L7ALLOW " .
						    "label \"layer7:pdst:{$pid}\"\n";
					}
				}
				foreach ($m["src_cidrs"] as $sc) {
					if (layer7_cidr_valid($sc) || layer7_ipv4_valid($sc)) {
						$rules .= "block drop quick inet from {$sc} " .
						    "to <{$tbl}> ! tagged L7ALLOW " .
						    "label \"layer7:pdst:{$pid}\"\n";
					}
				}
			} elseif ($scope_global) {
				$rules .= "block drop quick inet to <{$tbl}> " .
				    "! tagged L7ALLOW " .
				    "label \"layer7:pdst:{$pid}:global\"\n";
			}
		}

		if (!empty($m["needs_psrc"])) {
			$tbl = "layer7_psrc_" . $idx;
			$tables .= "table <{$tbl}> persist\n";
			if ($has_src || $scope_global || $quarantine_origin) {
				$rules .= "block drop quick inet from <{$tbl}> " .
				    "to !<localsubnets> ! tagged L7ALLOW " .
				    "label \"layer7:psrc:{$pid}\"\n";
			}
		}
	}

	if ($tables === "" && $rules === "") {
		return "";
	}

	return "\n# Policy enforcement scoped (Caminho B / E2)\n" .
	    ($include_tables ? $tables . "\n" : "") .
	    "# Policy scoped rules\n" . $rules;
}

/**
 * Tabelas PF persist do Layer7. Sao sempre emitidas (mesmo em monitor) para
 * que `pfctl -t ... -T show` continue a funcionar e `layer7_pf_selfheal()` no
 * daemon nao falhe so por mudanca de modo.
 *
 * `layer7_allow_dst` (Bloco 3) e populada pelo daemon (via DNS hint) e pelo
 * pacote (com IPs/CIDRs estaticos da allowlist).
 */
function layer7_pf_tables_text()
{
	return implode("\n", array(
		"# Layer7 PF snippet (package-managed): tables",
		"table <layer7_block> persist",
		"table <layer7_tagged> persist",
		"table <layer7_block_dst> persist",
		"table <layer7_allow_dst> persist",
		""
	)) . "\n";
}

function layer7_pf_default_rules_text($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$out = layer7_pf_tables_text();

	if (!layer7_pf_should_enforce($data)) {
		$out .= "# Layer7 passivo (enabled=false ou mode!=enforce):" .
		    " sem regras de bloqueio do core.\n";
		return $out;
	}

	$l7 = $data["layer7"];
	$block_dot_doq = !empty($l7["block_dot_doq"]);
	$bl_config = layer7_bl_config_load();
	$out .= layer7_pf_managed_dynamic_tables_text($data, $bl_config);

	/* A allowlist apenas marca o pacote. As regras Layer7 ignoram a marca,
	 * mas as regras nativas do pfSense continuam a avaliar o trafego. */
	$out .= implode("\n", array(
		"",
		"# Allowlist (Bloco 3) — destinos protegidos contra bloqueio.",
		"match inet to <layer7_allow_dst> tag L7ALLOW label \"layer7:allow:dst\"",
	)) . "\n";
	$out .= "\n# Explicit allow por politica (destino aprendido em runtime).\n";
	$out .= layer7_policy_allow_rules_text($data);
	$out .= "\n# Excecoes allow: somente destinos bloqueados pelo Layer7.\n";
	$out .= layer7_exception_allow_rules_text($data, $bl_config);

	if ($block_dot_doq) {
		$out .= implode("\n", array(
			"",
			"# Anti-bypass DoT/DoQ (porta 853) — toggle explicito 'block_dot_doq'.",
			"block drop quick inet proto tcp to !<localsubnets> port 853 ! tagged L7ALLOW label \"layer7:anti-dot\"",
			"block drop quick inet6 proto tcp to !<localsubnets> port 853 ! tagged L7ALLOW label \"layer7:anti-dot6\"",
			"block drop quick inet proto udp to !<localsubnets> port 853 ! tagged L7ALLOW label \"layer7:anti-doq\"",
			"block drop quick inet6 proto udp to !<localsubnets> port 853 ! tagged L7ALLOW label \"layer7:anti-doq6\""
		)) . "\n";
	}

	$out .= implode("\n", array(
		"",
		"# Bloqueio por origem do cliente (quarentena/tag) — apenas destinos externos.",
		"block drop quick inet from <layer7_block> to !<localsubnets> ! tagged L7ALLOW label \"layer7:block:src\"",
		"block drop quick inet6 from <layer7_block> to !<localsubnets> ! tagged L7ALLOW label \"layer7:block:src6\"",
	)) . "\n";

	/* Caminho B / E2: em scoped_hybrid o destino global layer7_block_dst nao
	 * e usado — cada politica block tem layer7_pdst_N / layer7_psrc_N. */
	if (!layer7_enforcement_is_scoped_hybrid($data)) {
		$out .= implode("\n", array(
			"",
			"# Bloqueio por destino (sites/apps bloqueados via policies).",
			"block drop quick inet to <layer7_block_dst> ! tagged L7ALLOW label \"layer7:block:dst\"",
			"block drop quick inet6 to <layer7_block_dst> ! tagged L7ALLOW label \"layer7:block:dst6\"",
		)) . "\n";
	}

	$out .= "\n# layer7_tagged permanece disponivel para regras manuais/encadeadas.\n";

	return $out;
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

function layer7_ensure_pf_table($table)
{
	$out = array();
	$rc = 0;
	exec("/sbin/pfctl -t " . escapeshellarg($table) . " -T show 2>/dev/null", $out, $rc);
	if ($rc === 0) {
		return true;
	}
	exec("/sbin/pfctl -t " . escapeshellarg($table) .
		" -T add 127.0.0.254 2>/dev/null", $out, $rc);
	exec("/sbin/pfctl -t " . escapeshellarg($table) .
		" -T delete 127.0.0.254 2>/dev/null", $out, $rc);
	return true;
}

function layer7_resync()
{
	$data = layer7_load_or_default();

	layer7_ensure_pf_table("layer7_block");
	layer7_ensure_pf_table("layer7_block_dst");
	layer7_ensure_pf_table("layer7_tagged");
	layer7_ensure_pf_table("layer7_allow_dst");

	$bl_config = layer7_bl_config_load();
	if (!empty($bl_config["rules"]) && is_array($bl_config["rules"])) {
		foreach ($bl_config["rules"] as $idx => $rule) {
			layer7_ensure_pf_table("layer7_bld_" . (int)$idx);
		}
	}

	foreach (layer7_policy_enforcement_table_names($data) as $ptbl) {
		layer7_ensure_pf_table($ptbl);
	}

	/* As regras rdr (block page + DNS forcado) sao entregues ao pfSense
	 * por layer7_generate_rules("nat") no filter reload; aqui apenas se
	 * limpa o anchor legado. Gate Bloco 1: em modo passivo flusha as
	 * tabelas dinamicas (Bloco 5: evitar IPs `stale`). */
	layer7_flush_legacy_nat_anchor();
	if (!layer7_pf_should_enforce()) {
		layer7_flush_dynamic_tables();
	}

	/* Bloco 3: popular `layer7_allow_dst` com IPs/CIDRs estaticos da
	 * allowlist (JSON + seed). Dominios sao adicionados em runtime pelo
	 * daemon, via DNS hint. Executado apos flush dinamico para repor
	 * entradas estaticas quando `layer7_allow_dst` foi esvaziada. */
	layer7_dst_allowlist_apply_to_pf();
	layer7_blockpage_sync($data);
	layer7_ensure_daemon_running();
}

/**
 * Le o ficheiro semente da allowlist (`/usr/local/etc/layer7/allowlist-seed.txt`).
 * Devolve array de strings (uma por entrada). Linhas vazias e `#` sao
 * ignoradas. Limita a 512 entradas como defesa em profundidade.
 */
function layer7_dst_allowlist_seed_entries()
{
	$path = "/usr/local/etc/layer7/allowlist-seed.txt";
	$out = array();
	if (!is_readable($path)) {
		return $out;
	}
	$lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (!is_array($lines)) {
		return $out;
	}
	$count = 0;
	foreach ($lines as $ln) {
		$s = trim((string)$ln);
		if ($s === "" || $s[0] === "#") {
			continue;
		}
		$out[] = $s;
		if (++$count >= 512) {
			break;
		}
	}
	return $out;
}

/**
 * Devolve a allowlist combinada (JSON do operador + seed embutida) como
 * array de strings unicas.
 */
function layer7_dst_allowlist_merged()
{
	$data = layer7_load_or_default();
	$user = isset($data["layer7"]["dst_allowlist"]) &&
	    is_array($data["layer7"]["dst_allowlist"])
	    ? $data["layer7"]["dst_allowlist"]
	    : array();
	$seed = layer7_dst_allowlist_seed_entries();
	$all = array();
	foreach (array_merge($seed, $user) as $e) {
		$s = trim((string)$e);
		if ($s !== "" && $s[0] !== "#") {
			$all[] = $s;
		}
	}
	return array_values(array_unique($all));
}

/**
 * Flush de uma tabela PF Layer7. Tabela ausente (pre-filter reload) e ignorada
 * silenciosamente; falhas reais geram no maximo um WARN por tabela por pedido.
 *
 * @return bool true se flush OK ou tabela inexistente
 */
function layer7_pf_table_flush($table)
{
	static $warned = array();
	$out = array();
	$rc = 0;

	if (!is_string($table) || $table === "" ||
	    !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
		return false;
	}

	exec("/sbin/pfctl -t " . escapeshellarg($table) .
	    " -T flush 2>/dev/null", $out, $rc);
	if ($rc === 0) {
		return true;
	}

	exec("/sbin/pfctl -t " . escapeshellarg($table) .
	    " -T show 2>/dev/null", $out, $rc);
	if ($rc !== 0) {
		return false;
	}

	if (empty($warned[$table])) {
		layer7_log_pkg_warn("Layer7: pfctl flush falhou tabela=" . $table);
		$warned[$table] = true;
	}
	return false;
}

/**
 * Popula `layer7_allow_dst` com IPv4 hosts/CIDRs da allowlist combinada.
 * Faz `flush` antes para garantir coerencia com a config actual. Dominios
 * sao saltados (adicionados pelo daemon via DNS hint).
 */
function layer7_dst_allowlist_apply_to_pf()
{
	$entries = layer7_dst_allowlist_merged();
	$ips = array();
	foreach ($entries as $e) {
		if (preg_match('/^[0-9]{1,3}(\.[0-9]{1,3}){3}$/', $e)) {
			if (layer7_ipv4_valid($e)) {
				$ips[] = $e;
			}
		} elseif (strpos($e, "/") !== false) {
			if (layer7_cidr_valid($e)) {
				$ips[] = $e;
			}
		}
	}
	$ips = array_values(array_unique($ips));

	layer7_pf_table_flush("layer7_allow_dst");
	static $warned_add = false;
	foreach ($ips as $ip) {
		$out = array();
		$rc = 0;
		exec("/sbin/pfctl -t layer7_allow_dst -T add " .
		    escapeshellarg($ip) . " 2>/dev/null", $out, $rc);
		if ($rc !== 0 && !$warned_add) {
			layer7_log_pkg_warn(
			    "Layer7: pfctl add falhou em layer7_allow_dst");
			$warned_add = true;
		}
	}
	return count($ips);
}

/**
 * Esvazia as tabelas PF dinamicas (`layer7_block`, `layer7_block_dst`,
 * `layer7_allow_dst`, `layer7_bld_N`, `layer7_blsrc_N`, `layer7_exc_allow_N`,
 * `layer7_pdst_N`, `layer7_psrc_N`, `layer7_pallow_N`, `layer7_pexc_N`).
 * Usado quando o modo passa para monitor/disabled ou ao desinstalar — evita
 * que IPs adicionados num enforce anterior continuem a bloquear apos a mudanca
 * (Bloco 5). Entradas estaticas da allowlist sao repostas por
 * `layer7_dst_allowlist_apply_to_pf()` apos flush quando enforce activo.
 */
function layer7_flush_dynamic_tables()
{
	$tables = array(
	    "layer7_block", "layer7_block_dst", "layer7_tagged",
	    "layer7_allow_dst");
	$bl_config = layer7_bl_config_load();
	if (!empty($bl_config["rules"]) && is_array($bl_config["rules"])) {
		foreach (array_keys($bl_config["rules"]) as $idx) {
			$tables[] = "layer7_bld_" . (int)$idx;
		}
	}
	/* Indices orfaos apos reorder/delete — flush ate limites do projecto. */
	for ($i = 0; $i < 32; $i++) {
		$tables[] = "layer7_bld_" . $i;
		$tables[] = "layer7_blsrc_" . $i;
	}
	for ($i = 0; $i < 16; $i++) {
		$tables[] = "layer7_exc_allow_" . $i;
	}
	for ($i = 0; $i < 24; $i++) {
		$tables[] = "layer7_pdst_" . $i;
		$tables[] = "layer7_psrc_" . $i;
		$tables[] = "layer7_pallow_" . $i;
		$tables[] = "layer7_pexc_" . $i;
	}
	foreach (layer7_policy_enforcement_table_names($data = layer7_load_or_default()) as $ptbl) {
		$tables[] = $ptbl;
	}
	$tables = array_values(array_unique($tables));
	foreach ($tables as $t) {
		layer7_pf_table_flush($t);
	}
}

/**
 * Apos mudanca PF-relevante: flush opcional, filter_configure, SIGHUP.
 */
function layer7_pf_config_resync($flush_dynamic = false)
{
	if ($flush_dynamic) {
		layer7_flush_dynamic_tables();
	}
	if (layer7_pf_should_enforce()) {
		layer7_dst_allowlist_apply_to_pf();
	}
	layer7_blockpage_sync();
	if (function_exists("filter_configure")) {
		filter_configure();
	}
	layer7_signal_reload();
}

/**
 * If the service is enabled but the daemon is not running, start it.
 * Called by layer7_resync() (pfSense boot + filter reload) to guarantee
 * the daemon survives reboots even when the rc.d ordering fails.
 */
function layer7_ensure_daemon_running()
{
	$data = layer7_load_or_default();
	if (empty($data["layer7"]["enabled"])) {
		return;
	}

	$pidf = "/var/run/layer7d.pid";
	if (file_exists($pidf)) {
		$pid = layer7_daemon_pid_from_file($pidf);
		if ($pid !== null) {
			exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $chk, $code);
			if ($code === 0) {
				return;
			}
		}
		@unlink($pidf);
	}

	$rc = "/usr/local/etc/rc.d/layer7d";
	if (!file_exists($rc)) {
		return;
	}
	$cmd = "/bin/sh " . escapeshellarg($rc) . " onestart 2>/dev/null";
	if (function_exists("mwexec")) {
		mwexec($cmd);
	} else {
		@shell_exec($cmd);
	}
}

/**
 * Escopo efectivo de origem por regra de blacklist. Quando ha except_ips,
 * uma tabela PF combina as origens positivas com entradas negadas. Assim a
 * excepcao nao precisa de pass/tag e nao interfere noutra politica Layer7.
 */
function layer7_blacklist_source_scope_meta($bl_config)
{
	$out = array();
	if (empty($bl_config["enabled"]) ||
	    empty($bl_config["rules"]) ||
	    !is_array($bl_config["rules"])) {
		return $out;
	}
	foreach ($bl_config["rules"] as $idx => $brule) {
		if (empty($brule["enabled"]) ||
		    empty($brule["except_ips"]) ||
		    !is_array($brule["except_ips"])) {
			continue;
		}
		$entries = array();
		if (!empty($brule["src_cidrs"]) &&
		    is_array($brule["src_cidrs"])) {
			foreach ($brule["src_cidrs"] as $cidr) {
				$cidr = trim((string)$cidr);
				if ($cidr !== "" &&
				    (layer7_cidr_valid($cidr) ||
				    layer7_ipv4_valid($cidr))) {
					$entries[] = $cidr;
				}
			}
		}
		if (empty($entries)) {
			$entries[] = "0.0.0.0/0";
		}
		$n_except = 0;
		foreach ($brule["except_ips"] as $eip) {
			$eip = trim((string)$eip);
			if ($eip !== "" && layer7_ipv4_valid($eip)) {
				$entries[] = "!" . $eip;
				$n_except++;
			}
		}
		if ($n_except > 0) {
			$out[(int)$idx] = array(
				"table" => "layer7_blsrc_" . (int)$idx,
				"entries" => array_values(array_unique($entries))
			);
		}
	}
	return $out;
}

/**
 * Regras de blacklist sem declaracoes de tabela (emitidas no cabecalho).
 */
function layer7_blacklist_filter_rules_text($bl_config)
{
	if (empty($bl_config["enabled"]) ||
	    empty($bl_config["rules"]) ||
	    !is_array($bl_config["rules"])) {
		return "";
	}
	$scope_meta = layer7_blacklist_source_scope_meta($bl_config);
	$rules = "\n# Blacklist rules (per-source/CIDR)\n";
	foreach ($bl_config["rules"] as $idx => $brule) {
		if (empty($brule["enabled"])) {
			continue;
		}
		$table = "layer7_bld_" . (int)$idx;
		$rname = preg_replace('/[^a-zA-Z0-9_-]/', '',
		    $brule["name"] ?? "rule{$idx}");
		if (isset($scope_meta[(int)$idx])) {
			$src_table = $scope_meta[(int)$idx]["table"];
			$rules .= "block drop quick inet from <{$src_table}> " .
			    "to <{$table}> ! tagged L7ALLOW " .
			    "label \"layer7:bl:{$rname}\"\n";
		} elseif (!empty($brule["src_cidrs"])) {
			foreach ($brule["src_cidrs"] as $cidr) {
				$cidr = trim((string)$cidr);
				if ($cidr !== "" &&
				    (layer7_cidr_valid($cidr) ||
				    layer7_ipv4_valid($cidr))) {
					$rules .= "block drop quick inet from {$cidr} " .
					    "to <{$table}> ! tagged L7ALLOW " .
					    "label \"layer7:bl:{$rname}\"\n";
				}
			}
		} else {
			$rules .= "block drop quick inet to <{$table}> " .
			    "! tagged L7ALLOW " .
			    "label \"layer7:bl:{$rname}\"\n";
		}
	}
	return $rules;
}

/**
 * Anti-QUIC por interface. Em PF, `on <if>` deve anteceder `inet`/`inet6`;
 * inverter essa ordem faz o reload inteiro falhar quando o toggle esta activo.
 */
function layer7_anti_quic_filter_rules_text($data)
{
	$rules = "";
	$quic_ifaces = isset($data["layer7"]["block_quic_interfaces"]) &&
	    is_array($data["layer7"]["block_quic_interfaces"])
	    ? $data["layer7"]["block_quic_interfaces"]
	    : array();
	if (!empty($quic_ifaces)) {
		$rules .= "\n# Anti-QUIC por interface — apenas destinos externos.\n";
		foreach ($quic_ifaces as $qi) {
			$qi = trim((string)$qi);
			if ($qi === "" || !layer7_pf_ifname_for_rules($qi)) {
				continue;
			}
			$rules .= "block drop quick on {$qi} inet proto udp" .
			    " to !<localsubnets> port 443 ! tagged L7ALLOW" .
			    " label \"layer7:anti-quic:{$qi}\"\n";
			$rules .= "block drop quick on {$qi} inet6 proto udp" .
			    " to !<localsubnets> port 443 ! tagged L7ALLOW" .
			    " label \"layer7:anti-quic6:{$qi}\"\n";
		}
	} elseif (!empty($data["layer7"]["block_quic"])) {
		$rules .= "\n# Anti-QUIC global (legado) — apenas destinos externos.\n";
		$rules .= "block drop quick inet proto udp to !<localsubnets> " .
		    "port 443 ! tagged L7ALLOW label \"layer7:anti-quic\"\n";
		$rules .= "block drop quick inet6 proto udp to !<localsubnets> " .
		    "port 443 ! tagged L7ALLOW label \"layer7:anti-quic6\"\n";
	}
	return $rules;
}

function layer7_generate_rules($type)
{
	$type = (string)$type;

	/* Tipo "nat": o pfSense pede as regras de traducao do package em
	 * filter_nat_rules_generate() (discover_pkg_rules). E aqui que o rdr
	 * :80 da block page e o DNS forcado :53 entram no ruleset principal —
	 * o anchor natrules/* NAO serve para rdr (so ha nat-anchor). */
	if ($type === "nat") {
		$data = layer7_load_or_default();
		if (!layer7_pf_should_enforce($data)) {
			return "";
		}
		return layer7_generate_nat_anchor_snippet();
	}

	if ($type !== "filter" && $type !== "rule") {
		return "";
	}

	$data = layer7_load_or_default();

	/* Gate F4/Bloco 1: em modo passivo (monitor / desactivado) o produto nao
	 * emite NENHUM block drop, anti-QUIC, blacklists nem injecta NAT.
	 *
	 * IMPORTANTE: nao usar `layer7_pf_rules_text()` aqui — essa funcao
	 * prefere o `pf.conf` em disco (escrito por `layer7-pfctl` com os blocks
	 * sempre presentes), o que contornava este gate. Retornamos apenas as
	 * tabelas `persist` (mode-aware via `layer7_pf_tables_text()`), limpamos
	 * o anchor NAT e nao deixamos regras de bloqueio. */
	if (!layer7_pf_should_enforce($data)) {
		layer7_flush_legacy_nat_anchor();
		return layer7_pf_tables_text();
	}

	/* Enforce: construir a partir da fonte mode-aware (inclui allowlist
	 * por tag, toggle `block_dot_doq` e os blocks por src/dst), em vez
	 * do `pf.conf` em disco, para garantir coerencia com a config actual. */
	$rules = layer7_pf_default_rules_text($data);

	$rules .= layer7_anti_quic_filter_rules_text($data);

	$bl_config = layer7_bl_config_load();
	$rules .= layer7_blacklist_filter_rules_text($bl_config);

	$rules .= layer7_policy_enforcement_rules_text($data, false);

	return $rules;
}

function layer7_bare_config()
{
	return array(
		"layer7" => array(
			"enabled" => false,
			"mode" => "monitor",
			"log_level" => "info",
			"log_file_max_mb" => 5,
			"log_file_keep" => 3,
			"syslog_remote" => false,
			"syslog_remote_host" => "",
			"syslog_remote_port" => 514,
			"debug_minutes" => 0,
			"block_quic" => false,
			"block_quic_interfaces" => array(),
			"block_dot_doq" => false,
			"sni_inspection" => false,
			"enforcement_model" => "legacy_global",
			"block_page" => array(
				"enabled" => false,
				"portal_ip" => "",
				"title" => "Acesso bloqueado",
				"message" => "Este site ou servico foi bloqueado pela politica de rede do seu administrador.",
				"contact" => "",
				"show_host" => true,
				"show_policy" => false,
				"sinkhole_blacklists" => true,
				"blacklist_domain_limit" => 256,
				"force_dns" => false
			),
			"interfaces" => array("lan"),
			"groups" => array(),
			"policies" => array(),
			"exceptions" => array(),
			"dst_allowlist" => array(),
			"device_aliases" => array()
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
	$data = layer7_config_interfaces_normalize($data);
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
	$pid = layer7_daemon_pid_from_file("/var/run/layer7d.pid");
	if ($pid !== null) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $chk, $code);
		return ($code === 0);
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

define('L7_BLOCKPAGE_PORT', 8099);
define('L7_BLOCKPAGE_MARKER_START', '# --- Layer7 block-page START ---');
define('L7_BLOCKPAGE_MARKER_END', '# --- Layer7 block-page END ---');

/**
 * Configuracao normalizada da pagina de bloqueio (defaults seguros).
 */
function layer7_blockpage_config_get($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$raw = array();
	if (is_array($data) && isset($data["layer7"]["block_page"]) &&
	    is_array($data["layer7"]["block_page"])) {
		$raw = $data["layer7"]["block_page"];
	}
	$def_title = "Acesso bloqueado";
	$def_msg = "Este site ou servico foi bloqueado pela politica de rede do seu administrador.";
	return array(
		"enabled" => !empty($raw["enabled"]),
		"portal_ip" => isset($raw["portal_ip"]) ? trim((string)$raw["portal_ip"]) : "",
		"title" => isset($raw["title"]) && trim((string)$raw["title"]) !== ""
		    ? trim((string)$raw["title"]) : $def_title,
		"message" => isset($raw["message"]) && trim((string)$raw["message"]) !== ""
		    ? trim((string)$raw["message"]) : $def_msg,
		"contact" => isset($raw["contact"]) ? trim((string)$raw["contact"]) : "",
		"show_host" => !isset($raw["show_host"]) || !empty($raw["show_host"]),
		"show_policy" => !empty($raw["show_policy"]),
		"sinkhole_blacklists" => !isset($raw["sinkhole_blacklists"]) ||
		    !empty($raw["sinkhole_blacklists"]),
		"blacklist_domain_limit" => isset($raw["blacklist_domain_limit"])
		    ? (int)$raw["blacklist_domain_limit"] : 256,
		"force_dns" => !empty($raw["force_dns"])
	);
}

/**
 * Pagina de bloqueio so corre com enforce activo e toggle ON.
 */
function layer7_blockpage_should_run($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	if (!layer7_pf_should_enforce($data)) {
		return false;
	}
	$bp = layer7_blockpage_config_get($data);
	return !empty($bp["enabled"]);
}

/**
 * IP portal: custom IPv4 ou auto (primeira interface Layer7 com IPv4).
 */
function layer7_blockpage_portal_ip($data = null)
{
	global $config;
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$bp = layer7_blockpage_config_get($data);
	if ($bp["portal_ip"] !== "" && layer7_ipv4_valid($bp["portal_ip"])) {
		return $bp["portal_ip"];
	}
	$iface_ids = isset($data["layer7"]["interfaces"]) &&
	    is_array($data["layer7"]["interfaces"])
	    ? $data["layer7"]["interfaces"] : array("lan");
	foreach ($iface_ids as $ifid) {
		$ifid = trim((string)$ifid);
		if ($ifid === "") {
			continue;
		}
		$keys = array($ifid);
		$real = layer7_real_interface_name($ifid);
		if ($real !== "" && $real !== $ifid) {
			$keys[] = $real;
		}
		foreach (array_unique($keys) as $key) {
			if (!isset($config["interfaces"][$key]) ||
			    !is_array($config["interfaces"][$key])) {
				continue;
			}
			$ip = isset($config["interfaces"][$key]["ipaddr"])
			    ? trim((string)$config["interfaces"][$key]["ipaddr"]) : "";
			if ($ip !== "" && layer7_ipv4_valid($ip)) {
				return $ip;
			}
		}
		/* layer7.json pode guardar nomes reais (vmx0, vmx0.95) em vez de
		 * lan/optN: mapear pelo campo `if` do config.xml. */
		if (isset($config["interfaces"]) &&
		    is_array($config["interfaces"])) {
			foreach ($config["interfaces"] as $cfg_if) {
				if (!is_array($cfg_if) ||
				    !isset($cfg_if["if"]) ||
				    (string)$cfg_if["if"] !== $ifid) {
					continue;
				}
				$ip = isset($cfg_if["ipaddr"])
				    ? trim((string)$cfg_if["ipaddr"]) : "";
				if ($ip !== "" && layer7_ipv4_valid($ip)) {
					return $ip;
				}
			}
		}
	}
	return null;
}

/**
 * Domínios a sinkhole: politicas block activas + blacklists (limitadas).
 * @return array{domains: string[], truncated: bool, policy_map: array<string,string>}
 */
function layer7_blockpage_collect_domains($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$bp = layer7_blockpage_config_get($data);
	$domains = array();
	$policy_map = array();
	$policies = isset($data["layer7"]["policies"]) &&
	    is_array($data["layer7"]["policies"])
	    ? $data["layer7"]["policies"] : array();
	foreach ($policies as $pol) {
		if (!is_array($pol) || empty($pol["enabled"])) {
			continue;
		}
		if (!isset($pol["action"]) || (string)$pol["action"] !== "block") {
			continue;
		}
		$pname = isset($pol["name"]) ? (string)$pol["name"] : "";
		$match = isset($pol["match"]) && is_array($pol["match"])
		    ? $pol["match"] : array();
		$hosts = isset($match["hosts"]) && is_array($match["hosts"])
		    ? $match["hosts"] : array();
		foreach ($hosts as $h) {
			$h = strtolower(trim((string)$h));
			if ($h === "" || !layer7_policy_host_valid($h)) {
				continue;
			}
			$domains[] = $h;
			if ($pname !== "" && !isset($policy_map[$h])) {
				$policy_map[$h] = $pname;
			}
		}
	}
	$domains = array_values(array_unique($domains));
	$bl_limit = (int)$bp["blacklist_domain_limit"];
	if ($bl_limit < 1) {
		$bl_limit = 256;
	}
	if ($bl_limit > 4096) {
		$bl_limit = 4096;
	}
	$truncated = false;
	if (!empty($bp["sinkhole_blacklists"])) {
		$bl_cfg = layer7_bl_config_load();
		if (!empty($bl_cfg["enabled"]) && !empty($bl_cfg["rules"]) &&
		    is_array($bl_cfg["rules"])) {
			$bl_domains = array();
			foreach ($bl_cfg["rules"] as $rule) {
				if (!is_array($rule) || empty($rule["enabled"])) {
					continue;
				}
				$cats = isset($rule["categories"]) && is_array($rule["categories"])
				    ? $rule["categories"] : array();
				foreach ($cats as $cat) {
					$cat = strtolower(trim((string)$cat));
					if (!layer7_bl_category_id_valid($cat)) {
						continue;
					}
					foreach (array(
					    L7_BL_DIR . '/' . $cat . '/domains',
					    L7_BL_DIR . '/_custom/' . $cat . '.domains'
					) as $path) {
						if (!is_readable($path)) {
							continue;
						}
						$raw = @file_get_contents($path);
						if (!is_string($raw) || $raw === "") {
							continue;
						}
						foreach (layer7_bl_domains_normalize($raw) as $d) {
							$bl_domains[] = $d;
							if (count($bl_domains) >= $bl_limit) {
								break 3;
							}
						}
					}
				}
			}
			$before = count($domains);
			$domains = array_values(array_unique(array_merge($domains, $bl_domains)));
			if (count($bl_domains) >= $bl_limit) {
				$truncated = true;
			}
			if (count($domains) > $before + $bl_limit) {
				$truncated = true;
			}
		}
	}
	sort($domains, SORT_STRING);
	return array(
		"domains" => $domains,
		"truncated" => $truncated,
		"policy_map" => $policy_map
	);
}

/**
 * Nome de politica associado a um host (para template HTML).
 */
function layer7_blockpage_policy_for_host($host, $data = null)
{
	$host = strtolower(trim((string)$host));
	if ($host === "") {
		return "";
	}
	$collected = layer7_blockpage_collect_domains($data);
	$map = isset($collected["policy_map"]) ? $collected["policy_map"] : array();
	if (isset($map[$host])) {
		return (string)$map[$host];
	}
	foreach ($map as $rule_host => $pname) {
		$rl = strlen($rule_host);
		if ($rl > 0 && strlen($host) > $rl &&
		    substr($host, -($rl + 1)) === "." . $rule_host) {
			return (string)$pname;
		}
	}
	return "";
}

/**
 * Bloco Unbound local-zone/local-data para sinkhole DNS.
 */
function layer7_blockpage_unbound_block($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$portal = layer7_blockpage_portal_ip($data);
	if ($portal === null) {
		return "";
	}
	$collected = layer7_blockpage_collect_domains($data);
	$domains = isset($collected["domains"]) ? $collected["domains"] : array();
	if (empty($domains)) {
		return "";
	}
	$lines = array();
	$lines[] = L7_BLOCKPAGE_MARKER_START;
	$lines[] = "# Layer7 block-page: sinkhole DNS -> {$portal}";
	foreach ($domains as $d) {
		$lines[] = "server:";
		$lines[] = "    local-zone: \"{$d}.\" redirect";
		$lines[] = "    local-data: \"{$d}. 60 IN A {$portal}\"";
	}
	if (!empty($collected["truncated"])) {
		$lines[] = "# AVISO: lista de blacklist truncada (limite configurado)";
	}
	$lines[] = L7_BLOCKPAGE_MARKER_END;
	return implode("\n", $lines) . "\n";
}

/**
 * Remove apenas o bloco block-page do custom_options Unbound.
 */
function layer7_unbound_blockpage_strip($text)
{
	$pos_start = strpos($text, L7_BLOCKPAGE_MARKER_START);
	if ($pos_start === false) {
		return $text;
	}
	$pos_end = strpos($text, L7_BLOCKPAGE_MARKER_END, $pos_start);
	if ($pos_end === false) {
		return substr($text, 0, $pos_start);
	}
	$pos_end += strlen(L7_BLOCKPAGE_MARKER_END);
	while ($pos_end < strlen($text) &&
	    ($text[$pos_end] === "\n" || $text[$pos_end] === "\r")) {
		$pos_end++;
	}
	return rtrim(substr($text, 0, $pos_start)) .
	    (strlen($text) > $pos_end ? substr($text, $pos_end) : "");
}

/**
 * Aplica ou remove overrides Unbound da pagina de bloqueio.
 */
function layer7_configure_unbound_blockpage($data = null)
{
	global $config;
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	if (!function_exists("write_config")) {
		return array("ok" => false, "msg" => l7_t("Funcao write_config nao disponivel."));
	}
	if (!isset($config["unbound"])) {
		return array("ok" => false, "msg" => l7_t("Seccao Unbound nao encontrada no config.xml."));
	}
	$existing = layer7_unbound_custom_options_read();
	$existing = layer7_unbound_blockpage_strip($existing);
	$block = layer7_blockpage_unbound_block($data);
	if ($block === "") {
		layer7_unbound_custom_options_write(trim($existing));
		write_config("Layer7: block-page Unbound removed");
		if (function_exists("services_unbound_configure")) {
			services_unbound_configure();
		}
		return array("ok" => true, "msg" => l7_t("Pagina de bloqueio: overrides DNS removidos."));
	}
	$new_content = "";
	if ($existing !== "") {
		$new_content = rtrim($existing) . "\n\n";
	}
	$new_content .= $block;
	layer7_unbound_custom_options_write($new_content);
	write_config("Layer7: block-page DNS sinkhole");
	if (function_exists("services_unbound_configure")) {
		services_unbound_configure();
	}
	$nd = count(layer7_blockpage_collect_domains($data)["domains"]);
	return array(
		"ok" => true,
		"msg" => sprintf(l7_t("Pagina de bloqueio: %d dominio(s) sinkhole configurados."), $nd)
	);
}

function layer7_remove_unbound_blockpage()
{
	global $config;
	if (!function_exists("write_config") || !isset($config["unbound"])) {
		return array("ok" => false, "msg" => l7_t("Config nao acessivel."));
	}
	$existing = layer7_unbound_custom_options_read();
	if (strpos($existing, L7_BLOCKPAGE_MARKER_START) === false) {
		return array("ok" => true, "msg" => l7_t("Overrides block-page nao encontrados."));
	}
	$existing = layer7_unbound_blockpage_strip($existing);
	layer7_unbound_custom_options_write(trim($existing));
	write_config("Layer7: block-page overrides removed");
	if (function_exists("services_unbound_configure")) {
		services_unbound_configure();
	}
	return array("ok" => true, "msg" => l7_t("Overrides block-page removidos do Unbound."));
}

/**
 * Par (IP portal, interfaces PF reais) usado pelas regras da block page.
 * Devolve null quando a block page nao deve correr ou falta portal/iface.
 */
function layer7_blockpage_portal_and_ifaces($data)
{
	if (!layer7_blockpage_should_run($data)) {
		return null;
	}
	$portal = layer7_blockpage_portal_ip($data);
	if ($portal === null) {
		return null;
	}
	$iface_ids = isset($data["layer7"]["interfaces"]) &&
	    is_array($data["layer7"]["interfaces"])
	    ? $data["layer7"]["interfaces"] : array("lan");
	$real_ifaces = array();
	foreach ($iface_ids as $ifid) {
		if (function_exists("get_real_interface")) {
			$real = get_real_interface($ifid);
		} else {
			$real = null;
		}
		if ($real) {
			$real_ifaces[] = $real;
		} elseif (layer7_pf_ifname_for_rules($ifid)) {
			$real_ifaces[] = $ifid;
		}
	}
	$real_ifaces = array_values(array_filter(
	    array_unique($real_ifaces),
	    "layer7_pf_ifname_for_rules"
	));
	sort($real_ifaces, SORT_STRING);
	if (empty($real_ifaces)) {
		return null;
	}
	return array("portal" => $portal, "ifaces" => $real_ifaces);
}

/**
 * Regras rdr TCP/80 e TCP/443 -> servico local da pagina de bloqueio.
 *
 * O 443 tambem e redireccionado: sem rdr, o SYN ao portal:443 era aceite
 * por regras `pass` anteriores (anti-lockout / allow LAN) e o
 * `net.inet.tcp.blackhole=2` do pfSense dropava em silencio a porta
 * fechada — o browser ficava "a carregar" ate ao timeout, sem erro
 * visivel. Com o rdr, o cliente TLS recebe resposta HTTP invalida do
 * servico da pagina e o browser mostra de imediato o erro de ligacao
 * (sem MITM: a pagina so e visivel em HTTP, ADR-0017).
 *
 * Salvaguarda: a porta efectiva do webConfigurator nunca e redireccionada
 * (GUI em 80/443 e comum; sequestra-la trancava o admin fora da GUI).
 */

/**
 * Porta efectiva do webConfigurator (protocol http=80 / https=443 quando
 * `port` nao esta definido em system.webgui).
 */
function layer7_webgui_port()
{
	global $config;
	$proto = "https";
	$port = 0;
	if (isset($config["system"]["webgui"]) &&
	    is_array($config["system"]["webgui"])) {
		$wg = $config["system"]["webgui"];
		if (!empty($wg["protocol"])) {
			$proto = (string)$wg["protocol"];
		}
		if (!empty($wg["port"])) {
			$port = (int)$wg["port"];
		}
	}
	if ($port > 0) {
		return $port;
	}
	return ($proto === "http") ? 80 : 443;
}
function layer7_generate_blockpage_rdr_snippet($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	$ctx = layer7_blockpage_portal_and_ifaces($data);
	if ($ctx === null) {
		return "";
	}
	$portal = $ctx["portal"];
	$real_ifaces = $ctx["ifaces"];
	$port = (int)L7_BLOCKPAGE_PORT;
	$webgui_port = layer7_webgui_port();
	$rdr_ports = array();
	foreach (array(80, 443) as $p) {
		if ($p !== $webgui_port) {
			$rdr_ports[] = $p;
		}
	}
	$lines = array();
	$lines[] = "# Pagina de bloqueio Layer7 (HTTP/HTTPS -> localhost:{$port};" .
	    " porta webgui {$webgui_port} preservada)";
	/* Nota: pf rejeita `label` em regras rdr (syntax error) — nao usar. */
	foreach ($real_ifaces as $iface) {
		foreach ($rdr_ports as $p) {
			$lines[] = "rdr on {$iface} inet proto tcp" .
			    " from any to {$portal} port {$p}" .
			    " -> 127.0.0.1 port {$port}";
		}
	}
	$bp = layer7_blockpage_config_get($data);
	if (!empty($bp["force_dns"])) {
		$lines[] = "# DNS forcado global (anti-bypass sinkhole) — block_page.force_dns";
		foreach ($real_ifaces as $iface) {
			$lines[] = "rdr on {$iface} inet proto udp" .
			    " from any to !127.0.0.1 port 53 -> 127.0.0.1";
			$lines[] = "rdr on {$iface} inet proto tcp" .
			    " from any to !127.0.0.1 port 53 -> 127.0.0.1";
		}
	}
	return implode("\n", $lines) . "\n";
}

/**
 * Arranca ou para o servico layer7-blockpage.
 */
function layer7_ensure_blockpage_service($run)
{
	if ($run) {
		@exec("/usr/sbin/sysrc layer7_blockpage_enable=YES 2>/dev/null");
		@exec("/usr/sbin/service layer7-blockpage onestart 2>/dev/null");
		return;
	}
	@exec("/usr/sbin/service layer7-blockpage onestop 2>/dev/null");
	@exec("/usr/sbin/sysrc layer7_blockpage_enable=NO 2>/dev/null");
}

/**
 * Sincroniza Unbound, servico HTTP e depende de filter reload para NAT rdr.
 */
function layer7_blockpage_sync($data = null)
{
	if ($data === null) {
		$data = layer7_load_or_default();
	}
	if (layer7_blockpage_should_run($data)) {
		layer7_configure_unbound_blockpage($data);
		layer7_ensure_blockpage_service(true);
		/* force_dns implica anti-DoH (NXDOMAIN para resolvers DoH +
		 * canario use-application-dns.net que desliga DoH no Firefox).
		 * Desactivar force_dns nao remove anti-DoH: gerido tambem em
		 * Diagnostics, remover so por accao explicita do admin. */
		$bp = layer7_blockpage_config_get($data);
		if (!empty($bp["force_dns"]) &&
		    !layer7_unbound_anti_doh_configured()) {
			layer7_configure_unbound_anti_doh();
		}
		return;
	}
	layer7_remove_unbound_blockpage();
	layer7_ensure_blockpage_service(false);
}

/**
 * Valida politica block: exige criterios de match ou flags explicitas.
 */
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

/**
 * Um block em scoped_hybrid precisa de um escopo PF executavel: origem
 * estatica/grupo, scope_global explicito ou quarentena dinamica da origem.
 * Interfaces limitam matching no daemon, mas nao substituem o escopo de
 * origem nas regras PF.
 */
function layer7_policy_scoped_block_valid($policy, $data)
{
	if (!is_array($policy) ||
	    (isset($policy["action"]) && (string)$policy["action"] !== "block")) {
		return true;
	}
	if (!empty($policy["scope_global"]) ||
	    !empty($policy["quarantine_origin"]) ||
	    !empty($policy["quarantine"])) {
		return true;
	}
	$groups = array();
	if (is_array($data) && isset($data["layer7"]["groups"]) &&
	    is_array($data["layer7"]["groups"])) {
		$groups = $data["layer7"]["groups"];
	}
	$src = layer7_policy_expand_src_origins($policy, $groups);
	return !empty($src["hosts"]) || !empty($src["cidrs"]);
}

function layer7_signal_reload()
{
	$pidf = "/var/run/layer7d.pid";
	/* Alinhar ao `reload` do rc.d: HUP se vivo; senão tentar arranque via
	 * layer7_ensure_daemon_running() (só sobe se layer7.enabled no JSON). */
	$pid = layer7_daemon_pid_from_file($pidf);
	if ($pid !== null) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $lr_chk, $lr_code);
		if ($lr_code === 0) {
			$cmd = "/bin/kill -HUP " . escapeshellarg($pid) . " 2>/dev/null";
			if (function_exists("mwexec")) {
				mwexec($cmd);
			} else {
				@shell_exec($cmd);
			}
			return;
		}
	}
	layer7_ensure_daemon_running();
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

/*
 * Versao real do pacote pfSense actualmente instalado, no formato
 * <PORTVERSION>[_<PORTREVISION>] (ex.: "1.8.11_14"). Devolve "" se o pkg
 * nao estiver instalado. Esta e a fonte canonica para comparacoes do
 * updater do GUI; o banner do daemon (`layer7d -V`) e cosmetico e pode
 * estar atrasado em releases que so mexem em PHP/scripts.
 */
function layer7_pkg_version()
{
	$out = array();
	$rc = 0;
	exec("/usr/sbin/pkg query %v pfSense-pkg-layer7 2>/dev/null", $out, $rc);
	return ($rc === 0 && !empty($out[0])) ? trim($out[0]) : "";
}

/**
 * Consulta GitHub Releases/latest e compara com a versao instalada do pacote.
 * Usado pelo updater da GUI (POST legado e AJAX).
 *
 * @return array{ok:bool,error:string,current:string,latest:string,tag:string,pkg_url:string,name:string,has_update:bool,up_to_date:bool,no_pkg_asset:bool}
 */
function layer7_check_for_update($owner = "pablomichelin", $repo = "Layer7")
{
	$result = array(
		"ok" => false,
		"error" => "",
		"current" => "",
		"latest" => "",
		"tag" => "",
		"pkg_url" => "",
		"name" => "",
		"has_update" => false,
		"up_to_date" => false,
		"no_pkg_asset" => false,
	);

	$current_ver = layer7_pkg_version();
	if ($current_ver === "") {
		$current_ver = layer7_daemon_version();
	}
	if ($current_ver === "") {
		$current_ver = l7_t("desconhecida");
	}
	$result["current"] = $current_ver;

	$gh_api = "https://api.github.com/repos/" . $owner . "/" . $repo . "/releases/latest";
	$tmp_json = "/tmp/layer7-gh-latest.json";
	@unlink($tmp_json);
	$fetch_cmd = "/usr/bin/fetch -qo " . escapeshellarg($tmp_json)
	    . " --user-agent=Layer7-pfSense-pkg/" . escapeshellarg($current_ver)
	    . " " . escapeshellarg($gh_api);
	exec($fetch_cmd . " 2>&1", $fetch_out, $fetch_rc);
	if ($fetch_rc !== 0 || !file_exists($tmp_json)) {
		$result["error"] = l7_t("Nao foi possivel contactar o GitHub. Verifique a ligacao a Internet.");
		return $result;
	}

	$gh_raw = @file_get_contents($tmp_json);
	$gh = is_string($gh_raw) ? @json_decode($gh_raw, true) : null;
	@unlink($tmp_json);
	if (!is_array($gh) || !isset($gh["tag_name"])) {
		$result["error"] = l7_t("Resposta do GitHub invalida ou repositorio sem releases.");
		return $result;
	}

	$latest_tag = $gh["tag_name"];
	if (preg_match('/^v?\d+\.\d+/', $latest_tag) !== 1) {
		$result["error"] = l7_t("Release mais recente nao e uma versao do pacote (tag ignorada): ") . $latest_tag;
		return $result;
	}

	$latest_ver = ltrim($latest_tag, "vV");
	$pkg_url = "";
	if (isset($gh["assets"]) && is_array($gh["assets"])) {
		foreach ($gh["assets"] as $asset) {
			if (!isset($asset["browser_download_url"])) {
				continue;
			}
			$url = (string)$asset["browser_download_url"];
			if (strpos($url, ".pkg") !== false && substr($url, -7) !== ".sha256") {
				$pkg_url = $url;
				break;
			}
		}
	}

	$result["ok"] = true;
	$result["latest"] = $latest_ver;
	$result["tag"] = $latest_tag;
	$result["pkg_url"] = $pkg_url;
	$result["name"] = isset($gh["name"]) ? (string)$gh["name"] : $latest_tag;

	if ($pkg_url === "") {
		$result["no_pkg_asset"] = true;
	} elseif (version_compare($latest_ver, $current_ver, ">")) {
		$result["has_update"] = true;
	} else {
		$result["up_to_date"] = true;
	}

	return $result;
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

/* CIDR a.b.c.d/n (n 0-32, octetos 0-255) */
function layer7_cidr_valid($s)
{
	if (!is_string($s) || strlen($s) > 48) {
		return false;
	}
	if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d+)$/', $s, $m) !== 1) {
		return false;
	}
	for ($i = 1; $i <= 4; $i++) {
		if ((int)$m[$i] > 255) {
			return false;
		}
	}
	$n = (int)$m[5];
	/* Rejeita 0.0.0.0/0 — allowlist aberta demais para PF. */
	return $n >= 1 && $n <= 32;
}

/** IPv4 pertence a CIDR ou coincide com host unico. */
function layer7_ipv4_in_cidr($ip, $cidr)
{
	if (!layer7_ipv4_valid($ip)) {
		return false;
	}
	$cidr = trim((string)$cidr);
	if ($cidr === "") {
		return false;
	}
	if (strpos($cidr, '/') === false) {
		return layer7_ipv4_valid($cidr) && $ip === $cidr;
	}
	if (!layer7_cidr_valid($cidr)) {
		return false;
	}
	list($net, $prefix) = explode('/', $cidr, 2);
	$prefix = (int)$prefix;
	$ip_long = ip2long($ip);
	$net_long = ip2long($net);
	if ($ip_long === false || $net_long === false) {
		return false;
	}
	if ($prefix === 0) {
		return true;
	}
	$mask = -1 << (32 - $prefix);
	return ($ip_long & $mask) === ($net_long & $mask);
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
	if (!function_exists("get_configured_interface_list") || !function_exists("get_real_interface")) {
		return $result;
	}
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

function layer7_profile_parse_hosts_textarea($text, $max = 64)
{
	$out = array();
	$lines = preg_split("/[\r\n,]+/", trim((string)$text));
	foreach ($lines as $line) {
		$line = strtolower(trim($line));
		if ($line === "" || $line[0] === "#") {
			continue;
		}
		if ($line[0] === ".") {
			$line = substr($line, 1);
		}
		if (!layer7_profile_host_valid($line)) {
			return null;
		}
		if (count($out) < $max) {
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
	$pid = layer7_daemon_pid_from_file("/var/run/layer7d.pid");
	if ($pid !== null) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $st_chk, $st_code);
		if ($st_code === 0) {
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
	$test = getenv("LAYER7_PROFILES_TEST_DIR");
	if (is_string($test) && $test !== "") {
		return rtrim($test, "/") . "/profiles.json";
	}
	return "/usr/local/etc/layer7/profiles.json";
}

function layer7_profiles_custom_path()
{
	$test = getenv("LAYER7_PROFILES_TEST_DIR");
	if (is_string($test) && $test !== "") {
		return rtrim($test, "/") . "/profiles-custom.json";
	}
	return "/usr/local/etc/layer7/profiles-custom.json";
}

/** @return array skeleton profiles-custom.json */
function layer7_profiles_custom_defaults()
{
	return array(
		"version" => 1,
		"overrides" => array(),
		"custom_profiles" => array(),
	);
}

function layer7_profile_id_valid($id)
{
	return is_string($id) && preg_match("/^[a-z0-9-]{1,64}$/", $id) === 1;
}

function layer7_profile_custom_id_valid($id)
{
	return is_string($id) && preg_match("/^c-[a-z0-9-]{1,62}$/", $id) === 1;
}

function layer7_profile_host_valid($host)
{
	return is_string($host) && preg_match(
	    "/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i",
	    $host
	) === 1;
}

function layer7_fa47_icon_set()
{
	static $set = null;
	if ($set !== null) {
		return $set;
	}
	$set = array("fa-cube" => true);
	foreach (explode("\n", layer7_fa47_icon_names_raw()) as $name) {
		$name = trim($name);
		if ($name !== "") {
			$set[$name] = true;
		}
	}
	return $set;
}

function layer7_profile_icon_valid($icon)
{
	if (!is_string($icon) || $icon === "") {
		return $icon === "";
	}
	if (preg_match("/^fa-[a-z0-9-]{1,40}$/", $icon) !== 1) {
		return false;
	}
	return isset(layer7_fa47_icon_set()[$icon]);
}

/**
 * Load factory catalog only (profiles.json shipped with package).
 * @return array
 */
function layer7_profiles_factory_load()
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
 * Apps/categories known from factory profiles (selection catalog for custom/override).
 * @return array{apps: string[], categories: string[], apps_set: array, cats_set: array}
 */
function layer7_profiles_catalog()
{
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$apps = array();
	$cats = array();
	foreach (layer7_profiles_factory_load() as $p) {
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
	$cache = array(
		"apps" => $app_list,
		"categories" => $cat_list,
		"apps_set" => $apps,
		"cats_set" => $cats,
	);
	return $cache;
}

/**
 * Load and validate profiles-custom.json (overlay; never in pkg-plist).
 * @return array
 */
function layer7_profiles_custom_load()
{
	$path = layer7_profiles_custom_path();
	if (!file_exists($path)) {
		return layer7_profiles_custom_defaults();
	}
	$raw = @file_get_contents($path);
	if (!is_string($raw) || $raw === "") {
		return layer7_profiles_custom_defaults();
	}
	$j = json_decode($raw, true);
	if (!is_array($j)) {
		return layer7_profiles_custom_defaults();
	}
	$defaults = layer7_profiles_custom_defaults();
	$out = $defaults;
	if (isset($j["version"]) && is_int($j["version"])) {
		$out["version"] = $j["version"];
	}
	if (isset($j["overrides"]) && is_array($j["overrides"])) {
		$out["overrides"] = $j["overrides"];
	}
	if (isset($j["custom_profiles"]) && is_array($j["custom_profiles"])) {
		$out["custom_profiles"] = $j["custom_profiles"];
	}
	return $out;
}

/**
 * Persist profiles-custom.json (atomic write, www:wheel).
 * @param array $data
 * @return bool
 */
function layer7_profiles_custom_save($data)
{
	global $input_errors;

	if (!is_array($data)) {
		return false;
	}
	$dir = dirname(layer7_profiles_custom_path());
	if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
		if (isset($input_errors)) {
			$input_errors[] = l7_t("Nao foi possivel criar ") . $dir;
		}
		return false;
	}
	@chown($dir, "www");
	@chgrp($dir, "wheel");
	@chmod($dir, 0775);

	$payload = layer7_profiles_custom_defaults();
	$payload["version"] = 1;
	if (isset($data["overrides"]) && is_array($data["overrides"])) {
		$payload["overrides"] = $data["overrides"];
	}
	if (isset($data["custom_profiles"]) && is_array($data["custom_profiles"])) {
		$payload["custom_profiles"] = $data["custom_profiles"];
	}

	$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		if (isset($input_errors)) {
			$input_errors[] = l7_t("Erro ao serializar perfis personalizados.");
		}
		return false;
	}
	$path = layer7_profiles_custom_path();
	if (@file_put_contents($path, $json . "\n", LOCK_EX) === false) {
		if (isset($input_errors)) {
			$input_errors[] = l7_t("Nao foi possivel gravar ") . $path;
		}
		return false;
	}
	@chown($path, "www");
	@chgrp($path, "wheel");
	@chmod($path, 0664);
	return true;
}

/**
 * Sanitize string list from POST (trim, dedupe, max items).
 * @param mixed $raw
 * @param int $max
 * @return string[]
 */
function layer7_profile_sanitize_string_list($raw, $max)
{
	if (!is_array($raw)) {
		return array();
	}
	$out = array();
	foreach ($raw as $v) {
		$v = trim((string)$v);
		if ($v === "") {
			continue;
		}
		$out[$v] = true;
		if (count($out) >= $max) {
			break;
		}
	}
	return array_keys($out);
}

/**
 * Validate merged profile limits (64 apps / 8 cats / 64 hosts).
 * @param array $profile
 * @return string|null error message or null if OK
 */
function layer7_profile_validate_limits($profile)
{
	if (!is_array($profile)) {
		return l7_t("Perfil invalido.");
	}
	$apps = (isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"]))
	    ? $profile["ndpi_apps"] : array();
	$cats = (isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"]))
	    ? $profile["ndpi_categories"] : array();
	$hosts = (isset($profile["hosts"]) && is_array($profile["hosts"])) ? $profile["hosts"] : array();
	if (count($apps) > 64) {
		return l7_t("Limite de 64 apps por perfil.");
	}
	if (count($cats) > 8) {
		return l7_t("Limite de 8 categorias por perfil.");
	}
	if (count($hosts) > 64) {
		return l7_t("Limite de 64 hosts por perfil.");
	}
	return null;
}

/**
 * Apply factory override onto a catalog profile.
 * @param array $profile factory profile
 * @param array $override hosts/apps add/remove, hidden
 * @param array|null $catalog from layer7_profiles_catalog()
 * @return array|null merged profile or null on failure
 */
function layer7_profile_apply_override($profile, $override, $catalog = null)
{
	if (!is_array($profile) || !is_array($override)) {
		return $profile;
	}
	if ($catalog === null) {
		$catalog = layer7_profiles_catalog();
	}
	$apps_set = isset($catalog["apps_set"]) ? $catalog["apps_set"] : array();
	$cats_set = isset($catalog["cats_set"]) ? $catalog["cats_set"] : array();

	$base_apps = (isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"]))
	    ? $profile["ndpi_apps"] : array();
	$remove_apps = layer7_profile_sanitize_string_list($override["apps_remove"] ?? array(), 64);
	$add_apps = layer7_profile_sanitize_string_list($override["apps_add"] ?? array(), 64);
	$apps = array();
	foreach ($base_apps as $a) {
		$a = trim((string)$a);
		if ($a === "" || in_array($a, $remove_apps, true)) {
			continue;
		}
		if (!empty($apps_set) && !isset($apps_set[$a])) {
			continue;
		}
		$apps[$a] = true;
	}
	foreach ($add_apps as $a) {
		if (!empty($apps_set) && !isset($apps_set[$a])) {
			continue;
		}
		$apps[$a] = true;
	}

	$base_hosts = (isset($profile["hosts"]) && is_array($profile["hosts"]))
	    ? $profile["hosts"] : array();
	$remove_hosts = layer7_profile_sanitize_string_list($override["hosts_remove"] ?? array(), 64);
	$add_hosts = layer7_profile_sanitize_string_list($override["hosts_add"] ?? array(), 64);
	$hosts = array();
	foreach ($base_hosts as $h) {
		$h = strtolower(trim((string)$h));
		if ($h === "" || in_array($h, $remove_hosts, true)) {
			continue;
		}
		if (!layer7_profile_host_valid($h)) {
			continue;
		}
		$hosts[$h] = true;
	}
	foreach ($add_hosts as $h) {
		$h = strtolower(trim((string)$h));
		if (!layer7_profile_host_valid($h)) {
			continue;
		}
		$hosts[$h] = true;
	}

	$merged = $profile;
	$merged["ndpi_apps"] = array_values(array_keys($apps));
	$merged["hosts"] = array_values(array_keys($hosts));
	$cats = (isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"]))
	    ? $profile["ndpi_categories"] : array();
	$clean_cats = array();
	foreach ($cats as $c) {
		$c = trim((string)$c);
		if ($c === "") {
			continue;
		}
		if (!empty($cats_set) && !isset($cats_set[$c])) {
			continue;
		}
		$clean_cats[$c] = true;
	}
	$merged["ndpi_categories"] = array_values(array_keys($clean_cats));

	if (layer7_profile_validate_limits($merged) !== null) {
		return null;
	}
	return $merged;
}

/**
 * Compute override diff from factory baseline vs desired merged state.
 * @return array|null override array or null if no changes
 */
function layer7_profile_compute_override($factory, $desired_apps, $desired_hosts, $hidden)
{
	if (!is_array($factory)) {
		return null;
	}
	$f_apps = (isset($factory["ndpi_apps"]) && is_array($factory["ndpi_apps"]))
	    ? $factory["ndpi_apps"] : array();
	$f_hosts = (isset($factory["hosts"]) && is_array($factory["hosts"]))
	    ? array_map("strtolower", $factory["hosts"]) : array();
	$desired_apps = layer7_profile_sanitize_string_list($desired_apps, 64);
	$desired_hosts_clean = array();
	foreach (layer7_profile_sanitize_string_list($desired_hosts, 64) as $h) {
		$h = strtolower(trim((string)$h));
		if (!layer7_profile_host_valid($h)) {
			return false;
		}
		$desired_hosts_clean[$h] = true;
	}
	$desired_hosts = array_keys($desired_hosts_clean);

	$apps_add = array_values(array_diff($desired_apps, $f_apps));
	$apps_remove = array_values(array_diff($f_apps, $desired_apps));
	$hosts_add = array_values(array_diff($desired_hosts, $f_hosts));
	$hosts_remove = array_values(array_diff($f_hosts, $desired_hosts));

	if (empty($apps_add) && empty($apps_remove) && empty($hosts_add) &&
	    empty($hosts_remove) && !$hidden) {
		return null;
	}
	return array(
		"apps_add" => $apps_add,
		"apps_remove" => $apps_remove,
		"hosts_add" => $hosts_add,
		"hosts_remove" => $hosts_remove,
		"hidden" => (bool)$hidden,
	);
}

/**
 * Whether a factory profile has a non-empty override entry.
 */
function layer7_profile_has_override($profile_id, $custom = null)
{
	if (!layer7_profile_id_valid($profile_id)) {
		return false;
	}
	if ($custom === null) {
		$custom = layer7_profiles_custom_load();
	}
	if (!isset($custom["overrides"][$profile_id]) ||
	    !is_array($custom["overrides"][$profile_id])) {
		return false;
	}
	$ov = $custom["overrides"][$profile_id];
	foreach (array("apps_add", "apps_remove", "hosts_add", "hosts_remove") as $k) {
		if (!empty($ov[$k]) && is_array($ov[$k])) {
			return true;
		}
	}
	return !empty($ov["hidden"]);
}

/**
 * Find merged profile by id (factory + overlay).
 */
function layer7_profile_get_by_id($profile_id)
{
	foreach (layer7_load_profiles(true) as $p) {
		if (($p["id"] ?? "") === $profile_id) {
			return $p;
		}
	}
	return null;
}

/**
 * Regenerate policy snapshot match from merged profile (keeps scope/VIP fields).
 * @return bool true if a connected policy was updated
 */
function layer7_profile_reconnect_policy(&$data, $profile)
{
	if (!is_array($profile) || !is_array($data)) {
		return false;
	}
	$profile_id = (string)($profile["id"] ?? "");
	if ($profile_id === "") {
		return false;
	}
	$pid = "profile-" . $profile_id;
	if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
		return false;
	}
	$updated = false;
	foreach ($data["layer7"]["policies"] as &$pol) {
		if (($pol["id"] ?? "") !== $pid) {
			continue;
		}
		if (!isset($pol["match"]) || !is_array($pol["match"])) {
			$pol["match"] = array();
		}
		$keep = array();
		foreach (array("src_cidrs", "groups", "src_exclude_cidrs", "src_exclude_groups",
		    "src_hosts") as $mk) {
			if (isset($pol["match"][$mk])) {
				$keep[$mk] = $pol["match"][$mk];
			}
		}
		$apps = (isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"]))
		    ? $profile["ndpi_apps"] : array();
		$hosts = (isset($profile["hosts"]) && is_array($profile["hosts"]))
		    ? $profile["hosts"] : array();
		$cats = (isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"]))
		    ? $profile["ndpi_categories"] : array();
		$pol["match"] = $keep;
		if (!empty($apps)) {
			$pol["match"]["ndpi_app"] = array_slice($apps, 0, 64);
		}
		if (!empty($cats)) {
			$pol["match"]["ndpi_category"] = array_slice($cats, 0, 8);
		}
		if (!empty($hosts)) {
			$pol["match"]["hosts"] = array_slice($hosts, 0, 64);
		}
		if (isset($profile["name"]) && is_string($profile["name"]) &&
		    trim($profile["name"]) !== "") {
			$pol["name"] = trim($profile["name"]);
		}
		$updated = true;
		break;
	}
	unset($pol);
	return $updated;
}

/**
 * Load service profiles: factory → overrides → custom_profiles.
 * @param bool $include_hidden include profiles marked hidden (GUI uses false)
 * @return array of profile arrays
 */
function layer7_load_profiles($include_hidden = false)
{
	$factory = layer7_profiles_factory_load();
	$custom = layer7_profiles_custom_load();
	$overrides = (isset($custom["overrides"]) && is_array($custom["overrides"]))
	    ? $custom["overrides"] : array();
	$catalog = layer7_profiles_catalog();
	$result = array();
	$seen = array();

	foreach ($factory as $p) {
		if (!is_array($p)) {
			continue;
		}
		$id = (string)($p["id"] ?? "");
		if ($id === "" || !layer7_profile_id_valid($id)) {
			continue;
		}
		$ov = (isset($overrides[$id]) && is_array($overrides[$id])) ? $overrides[$id] : array();
		if (!empty($ov["hidden"]) && !$include_hidden) {
			continue;
		}
		$merged = layer7_profile_apply_override($p, $ov, $catalog);
		if ($merged === null) {
			$merged = $p;
		}
		$result[] = $merged;
		$seen[$id] = true;
	}

	$custom_list = (isset($custom["custom_profiles"]) && is_array($custom["custom_profiles"]))
	    ? $custom["custom_profiles"] : array();
	foreach ($custom_list as $cp) {
		if (!is_array($cp)) {
			continue;
		}
		$id = (string)($cp["id"] ?? "");
		if ($id === "" || !layer7_profile_custom_id_valid($id) || isset($seen[$id])) {
			continue;
		}
		if (!empty($cp["hidden"]) && !$include_hidden) {
			continue;
		}
		if (layer7_profile_validate_limits($cp) !== null) {
			continue;
		}
		$cp["group"] = "Personalizados";
		$result[] = $cp;
		$seen[$id] = true;
	}

	return $result;
}

/**
 * Caminho A / A4 — hits por perfil a partir das stats do daemon.
 *
 * Soma os `count` das apps nDPI bloqueadas (`top_apps_blocked`) que pertencem
 * a cada perfil (match por nome, case-insensitive). Devolve map
 * id_perfil => total de bloqueios observados. Sem stats -> tudo 0.
 */
function layer7_profile_hit_counts($profiles, $stats)
{
	$out = array();
	if (!is_array($profiles)) {
		return $out;
	}
	$apphits = array();
	if (is_array($stats) && isset($stats["top_apps_blocked"]) &&
	    is_array($stats["top_apps_blocked"])) {
		foreach ($stats["top_apps_blocked"] as $e) {
			$a = strtolower(trim((string)($e["app"] ?? "")));
			if ($a !== "") {
				$apphits[$a] = (int)($e["count"] ?? 0);
			}
		}
	}
	foreach ($profiles as $p) {
		$id = (string)($p["id"] ?? "");
		if ($id === "") {
			continue;
		}
		$sum = 0;
		$apps = (isset($p["ndpi_apps"]) && is_array($p["ndpi_apps"]))
		    ? $p["ndpi_apps"] : array();
		foreach ($apps as $app) {
			$k = strtolower(trim((string)$app));
			if ($k !== "" && isset($apphits[$k])) {
				$sum += $apphits[$k];
			}
		}
		$out[$id] = $sum;
	}
	return $out;
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

/** Limites actuais do daemon (policy.h L7_EXC_MAX_*); Bloco C alarga depois. */
define("LAYER7_VIP_MAX_HOSTS", 8);
define("LAYER7_VIP_MAX_CIDRS", 8);

/** ID canonico da excepcao VIP partilhada (gerida pelo modal Perfis rapidos). */
function layer7_vip_exception_id()
{
	return "vip-isentos";
}

function layer7_is_managed_vip_exception($exception)
{
	return is_array($exception) &&
	    isset($exception["id"]) &&
	    (string)$exception["id"] === layer7_vip_exception_id();
}

/**
 * Devolve a excepcao VIP gerida ou null.
 *
 * @param array $data Configuracao layer7 (layer7_load_or_default).
 * @return array|null
 */
function layer7_find_vip_exception($data)
{
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

/**
 * Indice da excepcao VIP em layer7.exceptions ou -1.
 */
function layer7_find_vip_exception_index($data)
{
	if (!is_array($data) || !isset($data["layer7"]["exceptions"]) ||
	    !is_array($data["layer7"]["exceptions"])) {
		return -1;
	}
	foreach ($data["layer7"]["exceptions"] as $i => $exc) {
		if (layer7_is_managed_vip_exception($exc)) {
			return (int)$i;
		}
	}
	return -1;
}

/**
 * Expande IDs de grupo em hosts/cidrs (schema de excepcao).
 *
 * @param string[] $group_ids
 * @param array[] $groups
 * @return array{hosts: string[], cidrs: string[]}
 */
function layer7_expand_groups_for_exception($group_ids, $groups)
{
	$fake = array("match" => array("groups" => array_values($group_ids)));
	return layer7_policy_expand_src_origins($fake, $groups);
}

/**
 * Cria ou actualiza a excepcao canonica vip-isentos (allow global).
 *
 * @param array $data Referencia a configuracao completa (mutavel).
 * @param string[] $group_ids Grupos seleccionados (metadata source_groups).
 * @param string $hosts_text Textarea IPv4 (um por linha).
 * @param string $cidrs_text Textarea CIDR (um por linha).
 * @return array{ok: bool, error: string, updated: bool}
 */
function layer7_upsert_vip_exception(&$data, $group_ids, $hosts_text, $cidrs_text)
{
	$out = array("ok" => true, "error" => "", "updated" => false);

	$valid_groups = array();
	if (is_array($group_ids)) {
		foreach ($group_ids as $gid) {
			$gid = trim((string)$gid);
			if ($gid !== "" && layer7_group_id_valid($gid)) {
				$valid_groups[] = $gid;
			}
		}
		$valid_groups = array_values(array_unique($valid_groups));
	}

	$hosts = layer7_parse_ip_textarea($hosts_text);
	$cidrs = layer7_parse_cidr_textarea($cidrs_text);

	$all_groups = (isset($data["layer7"]["groups"]) &&
	    is_array($data["layer7"]["groups"])) ? $data["layer7"]["groups"] : array();
	/* Resolver device_macs -> device_ips nos grupos seleccionados. */
	foreach ($all_groups as $gi => $g) {
		if (!is_array($g) || empty($g["id"]) ||
		    !in_array((string)$g["id"], $valid_groups, true)) {
			continue;
		}
		$macs = (isset($g["device_macs"]) && is_array($g["device_macs"]))
		    ? $g["device_macs"] : array();
		if (!empty($macs)) {
			$data["layer7"]["groups"][$gi]["device_ips"] =
			    layer7_resolve_macs_to_ips($macs);
		}
	}

	$expanded = layer7_expand_groups_for_exception($valid_groups, $all_groups);
	$hosts = array_values(array_unique(array_merge($hosts, $expanded["hosts"])));
	$cidrs = array_values(array_unique(array_merge($cidrs, $expanded["cidrs"])));

	if (empty($hosts) && empty($cidrs)) {
		return $out;
	}

	$limit_err = layer7_vip_validate_limits($hosts, $cidrs);
	if ($limit_err !== "") {
		$out["ok"] = false;
		$out["error"] = $limit_err;
		return $out;
	}

	if (!isset($data["layer7"]["exceptions"]) ||
	    !is_array($data["layer7"]["exceptions"])) {
		$data["layer7"]["exceptions"] = array();
	}
	$exceptions = &$data["layer7"]["exceptions"];
	$idx = layer7_find_vip_exception_index($data);

	if ($idx < 0 && count($exceptions) >= 16) {
		$out["ok"] = false;
		$out["error"] = l7_t("Limite de 16 excecoes.");
		unset($exceptions);
		return $out;
	}

	$rule = array(
		"id" => layer7_vip_exception_id(),
		"name" => l7_t("Isentos (Perfis rapidos)"),
		"enabled" => true,
		"priority" => 9000,
		"action" => "allow",
		"managed_by" => "profiles"
	);
	if (!empty($hosts)) {
		$rule["hosts"] = array_values($hosts);
	}
	if (!empty($cidrs)) {
		$rule["cidrs"] = array_values($cidrs);
	}
	if (!empty($valid_groups)) {
		$rule["source_groups"] = $valid_groups;
	}

	if ($idx >= 0) {
		$exceptions[$idx] = $rule;
	} else {
		$exceptions[] = $rule;
	}
	layer7_vip_labels_cleanup($data, $hosts, $cidrs);
	$out["updated"] = true;
	unset($exceptions);
	return $out;
}

/**
 * Sanitiza descricao de entrada VIP (max 64; sem aspas ou barra invertida).
 */
function layer7_vip_sanitize_label($text)
{
	$text = trim((string)$text);
	$text = str_replace(array('"', "\\"), "", $text);
	if (strlen($text) > 64) {
		$text = substr($text, 0, 64);
	}
	return $text;
}

/**
 * @return array<string, string> mapa IP/CIDR -> descricao
 */
function layer7_vip_get_labels(&$data)
{
	if (!isset($data["layer7"]["vip_meta"]["labels"]) ||
	    !is_array($data["layer7"]["vip_meta"]["labels"])) {
		$data["layer7"]["vip_meta"]["labels"] = array();
	}
	return $data["layer7"]["vip_meta"]["labels"];
}

/**
 * Remove labels orfas (alvos ja nao presentes na excepcao VIP).
 *
 * @param string[] $hosts
 * @param string[] $cidrs
 */
function layer7_vip_labels_cleanup(&$data, $hosts, $cidrs)
{
	$labels = layer7_vip_get_labels($data);
	$valid = array();
	foreach ($hosts as $h) {
		$valid[(string)$h] = true;
	}
	foreach ($cidrs as $c) {
		$valid[(string)$c] = true;
	}
	foreach (array_keys($labels) as $key) {
		if (!isset($valid[$key])) {
			unset($data["layer7"]["vip_meta"]["labels"][$key]);
		}
	}
}

/**
 * Valida limites VIP face ao daemon actual (8 hosts + 8 CIDRs).
 *
 * @param string[] $hosts
 * @param string[] $cidrs
 * @return string mensagem de erro ou vazio se OK
 */
function layer7_vip_validate_limits($hosts, $cidrs)
{
	if (count($hosts) > LAYER7_VIP_MAX_HOSTS) {
		return sprintf(l7_t("Limite de %d IPs isentos (daemon actual)."),
		    LAYER7_VIP_MAX_HOSTS);
	}
	if (count($cidrs) > LAYER7_VIP_MAX_CIDRS) {
		return sprintf(l7_t("Limite de %d CIDRs isentos (daemon actual)."),
		    LAYER7_VIP_MAX_CIDRS);
	}
	return "";
}

/**
 * Extrai hosts/cidrs directos da excepcao VIP (sem grupos).
 *
 * @return array{hosts: string[], cidrs: string[]}
 */
function layer7_vip_direct_targets($data)
{
	$vip = layer7_find_vip_exception($data);
	$hosts = array();
	$cidrs = array();
	if ($vip !== null) {
		if (!empty($vip["hosts"]) && is_array($vip["hosts"])) {
			$hosts = array_values($vip["hosts"]);
		}
		if (!empty($vip["cidrs"]) && is_array($vip["cidrs"])) {
			$cidrs = array_values($vip["cidrs"]);
		}
	}
	return array("hosts" => $hosts, "cidrs" => $cidrs);
}

/**
 * Linhas da Lista VIP para a GUI (descricao + alvo).
 *
 * @return array<int, array{target: string, description: string, kind: string}>
 */
function layer7_vip_list_entries($data)
{
	$targets = layer7_vip_direct_targets($data);
	$labels = layer7_vip_get_labels($data);
	$rows = array();
	foreach ($targets["hosts"] as $h) {
		$desc = isset($labels[$h]) ? (string)$labels[$h] : "";
		$rows[] = array(
			"target" => $h,
			"description" => $desc,
			"kind" => "host"
		);
	}
	foreach ($targets["cidrs"] as $c) {
		$desc = isset($labels[$c]) ? (string)$labels[$c] : "";
		$rows[] = array(
			"target" => $c,
			"description" => $desc,
			"kind" => "cidr"
		);
	}
	return $rows;
}

/**
 * Grupos associados a excepcao VIP (metadata source_groups).
 *
 * @return string[]
 */
function layer7_vip_source_groups($data)
{
	$vip = layer7_find_vip_exception($data);
	if ($vip === null || empty($vip["source_groups"]) ||
	    !is_array($vip["source_groups"])) {
		return array();
	}
	return array_values($vip["source_groups"]);
}

/**
 * Sincroniza excepcao vip-isentos a partir de listas directas (preserva source_groups).
 *
 * @param string[] $hosts
 * @param string[] $cidrs
 * @param string[]|null $source_groups null = manter existentes
 * @return array{ok: bool, error: string, updated: bool}
 */
function layer7_vip_sync_direct_targets(&$data, $hosts, $cidrs, $source_groups = null)
{
	$hosts = array_values(array_unique($hosts));
	$cidrs = array_values(array_unique($cidrs));
	$groups = ($source_groups === null)
	    ? layer7_vip_source_groups($data)
	    : array_values(array_unique($source_groups));

	if (empty($hosts) && empty($cidrs) && empty($groups)) {
		$idx = layer7_find_vip_exception_index($data);
		if ($idx >= 0 && isset($data["layer7"]["exceptions"][$idx])) {
			array_splice($data["layer7"]["exceptions"], $idx, 1);
			layer7_vip_labels_cleanup($data, array(), array());
			return array("ok" => true, "error" => "", "updated" => true);
		}
		layer7_vip_labels_cleanup($data, array(), array());
		return array("ok" => true, "error" => "", "updated" => false);
	}

	$hosts_text = implode("\n", $hosts);
	$cidrs_text = implode("\n", $cidrs);
	return layer7_upsert_vip_exception($data, $groups, $hosts_text, $cidrs_text);
}

/**
 * Adiciona entrada directa a Lista VIP (descricao + IP ou CIDR).
 *
 * @return array{ok: bool, error: string, updated: bool}
 */
function layer7_vip_add_entry(&$data, $description, $target)
{
	$description = layer7_vip_sanitize_label($description);
	$target = trim((string)$target);
	if ($target === "") {
		return array("ok" => false, "error" => l7_t("Indique um IPv4 ou CIDR."), "updated" => false);
	}
	if ($description === "") {
		return array("ok" => false, "error" => l7_t("Indique uma descricao."), "updated" => false);
	}

	$is_host = layer7_ipv4_valid($target);
	$is_cidr = layer7_cidr_valid($target);
	if (!$is_host && !$is_cidr) {
		return array("ok" => false, "error" => l7_t("IPv4 ou CIDR invalido."), "updated" => false);
	}

	$current = layer7_vip_direct_targets($data);
	$hosts = $current["hosts"];
	$cidrs = $current["cidrs"];

	if ($is_host) {
		if (in_array($target, $hosts, true)) {
			return array("ok" => false, "error" => l7_t("Este IP ja esta na Lista VIP."), "updated" => false);
		}
		$hosts[] = $target;
	} else {
		if (in_array($target, $cidrs, true)) {
			return array("ok" => false, "error" => l7_t("Este CIDR ja esta na Lista VIP."), "updated" => false);
		}
		$cidrs[] = $target;
	}

	$limit_err = layer7_vip_validate_limits($hosts, $cidrs);
	if ($limit_err !== "") {
		return array("ok" => false, "error" => $limit_err, "updated" => false);
	}

	$res = layer7_vip_sync_direct_targets($data, $hosts, $cidrs);
	if (!$res["ok"]) {
		return $res;
	}
	$labels = layer7_vip_get_labels($data);
	$labels[$target] = $description;
	return array("ok" => true, "error" => "", "updated" => true);
}

/**
 * Remove entrada directa da Lista VIP.
 *
 * @return array{ok: bool, error: string, updated: bool}
 */
function layer7_vip_remove_entry(&$data, $target)
{
	$target = trim((string)$target);
	if ($target === "") {
		return array("ok" => false, "error" => l7_t("Alvo invalido."), "updated" => false);
	}

	$current = layer7_vip_direct_targets($data);
	$hosts = array_values(array_filter($current["hosts"],
	    function ($h) use ($target) { return $h !== $target; }));
	$cidrs = array_values(array_filter($current["cidrs"],
	    function ($c) use ($target) { return $c !== $target; }));

	if (count($hosts) === count($current["hosts"]) &&
	    count($cidrs) === count($current["cidrs"])) {
		return array("ok" => false, "error" => l7_t("Entrada nao encontrada."), "updated" => false);
	}

	if (isset($data["layer7"]["vip_meta"]["labels"][$target])) {
		unset($data["layer7"]["vip_meta"]["labels"][$target]);
	}

	return layer7_vip_sync_direct_targets($data, $hosts, $cidrs);
}

/**
 * Payload JSON para export da Lista VIP (padrao BG-070).
 *
 * @return array<string, mixed>
 */
function layer7_vip_export_payload($data)
{
	$entries = array();
	foreach (layer7_vip_list_entries($data) as $row) {
		$entries[] = array(
			"description" => $row["description"],
			"target" => $row["target"]
		);
	}
	return array(
		"layer7_vip_list" => true,
		"version" => layer7_daemon_version(),
		"timestamp" => date("c"),
		"entries" => $entries
	);
}

/**
 * Importa Lista VIP a partir de JSON exportado.
 *
 * @return array{ok: bool, error: string, updated: bool}
 */
function layer7_vip_import_apply(&$data, $imported)
{
	if (!is_array($imported)) {
		return array("ok" => false, "error" => l7_t("JSON invalido."), "updated" => false);
	}
	$entries = null;
	if (!empty($imported["layer7_vip_list"]) &&
	    isset($imported["entries"]) && is_array($imported["entries"])) {
		$entries = $imported["entries"];
	} elseif (isset($imported["entries"]) && is_array($imported["entries"])) {
		$entries = $imported["entries"];
	}
	if ($entries === null) {
		return array("ok" => false,
		    "error" => l7_t("Ficheiro nao contem Lista VIP valida."), "updated" => false);
	}

	$hosts = array();
	$cidrs = array();
	$labels = array();
	foreach ($entries as $entry) {
		if (!is_array($entry)) {
			continue;
		}
		$target = trim((string)($entry["target"] ?? ""));
		$desc = layer7_vip_sanitize_label($entry["description"] ?? "");
		if ($target === "" || $desc === "") {
			continue;
		}
		if (layer7_ipv4_valid($target)) {
			if (!in_array($target, $hosts, true)) {
				$hosts[] = $target;
				$labels[$target] = $desc;
			}
		} elseif (layer7_cidr_valid($target)) {
			if (!in_array($target, $cidrs, true)) {
				$cidrs[] = $target;
				$labels[$target] = $desc;
			}
		}
	}

	$limit_err = layer7_vip_validate_limits($hosts, $cidrs);
	if ($limit_err !== "") {
		return array("ok" => false, "error" => $limit_err, "updated" => false);
	}

	$res = layer7_vip_sync_direct_targets($data, $hosts, $cidrs, array());
	if (!$res["ok"]) {
		return $res;
	}
	$data["layer7"]["vip_meta"]["labels"] = $labels;
	layer7_vip_labels_cleanup($data, $hosts, $cidrs);
	return array("ok" => true, "error" => "", "updated" => true);
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

/* ============================================================
 * Caminho A / A1 — inventario de dispositivos (read-only).
 *
 * Fontes de identidade (ver ADR-0011):
 *   - DHCP leases via API canonica do pfSense `system_get_dhcpleases()`
 *     (unifica ISC dhcpd e Kea; traz ip/mac/hostname/descr/if/online).
 *   - Tabela ARP (`arp -an`) para vizinhos L2 activos, incluindo clientes
 *     de IP estatico sem lease.
 * Limitacoes honestas: so ve dispositivos adjacentes em L2 (mesmo segmento);
 * nao ha 802.1x/LDAP; vendor depende de base OUI presente no sistema.
 * Este modulo NAO altera enforcement; e estritamente observacional.
 * ============================================================ */

function layer7_device_oui_files()
{
	/* Bases OUI possiveis no sistema (best-effort, sem dependencia rigida). */
	return array(
		"/usr/local/share/ntopng/httpdocs/other/EtherOUI.txt",
		"/usr/local/share/wireshark/manuf",
		"/usr/share/misc/oui.txt",
	);
}

/* Resolve vendor para um conjunto de prefixos OUI (aa:bb:cc) numa unica
 * passagem pelo ficheiro OUI (evita carregar a base inteira por dispositivo). */
function layer7_device_vendor_map($prefixes)
{
	$want = array();
	foreach ($prefixes as $p) {
		$p = strtolower($p);
		if (strlen($p) >= 8) {
			$want[substr($p, 0, 8)] = "";
		}
	}
	if (empty($want)) {
		return array();
	}
	foreach (layer7_device_oui_files() as $f) {
		if (!is_file($f) || !is_readable($f)) {
			continue;
		}
		$fh = @fopen($f, "r");
		if (!$fh) {
			continue;
		}
		$remaining = count($want);
		while (($line = fgets($fh)) !== false) {
			$line = trim($line);
			if ($line === "" || $line[0] === "#") {
				continue;
			}
			/* Formato tshark manuf: PREFIX<TAB>SHORT<TAB>LONG */
			$parts = preg_split('/\t+/', $line);
			if (count($parts) < 2) {
				continue;
			}
			$pfx = strtolower(str_replace("-", ":", trim($parts[0])));
			if (strpos($pfx, "/") !== false || strlen($pfx) < 8) {
				continue;
			}
			$key = substr($pfx, 0, 8);
			if (array_key_exists($key, $want) && $want[$key] === "") {
				$want[$key] = trim($parts[count($parts) - 1]);
				$remaining--;
				if ($remaining <= 0) {
					break;
				}
			}
		}
		fclose($fh);
		$found = false;
		foreach ($want as $v) {
			if ($v !== "") { $found = true; break; }
		}
		if ($found) {
			break;
		}
	}
	$out = array();
	foreach ($want as $k => $v) {
		if ($v !== "") {
			$out[$k] = $v;
		}
	}
	return $out;
}

/* Aliases persistentes por MAC, guardados em layer7.json (`device_aliases`).
 * O daemon ignora esta chave (so observacional). */
function layer7_device_aliases_load()
{
	$data = layer7_load_or_default();
	$a = isset($data["layer7"]["device_aliases"]) ? $data["layer7"]["device_aliases"] : array();
	return is_array($a) ? $a : array();
}

function layer7_device_mac_valid($mac)
{
	return (bool)preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', strtolower((string)$mac));
}

function layer7_device_alias_save($mac, $alias)
{
	$mac = strtolower(trim((string)$mac));
	if (!layer7_device_mac_valid($mac)) {
		return false;
	}
	$alias = trim((string)$alias);
	$alias = preg_replace('/[^\p{L}\p{N} ._\-]/u', '', $alias);
	if (function_exists("mb_substr")) {
		$alias = mb_substr($alias, 0, 64);
	} else {
		$alias = substr($alias, 0, 64);
	}
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["device_aliases"]) || !is_array($data["layer7"]["device_aliases"])) {
		$data["layer7"]["device_aliases"] = array();
	}
	if ($alias === "") {
		unset($data["layer7"]["device_aliases"][$mac]);
	} else {
		$data["layer7"]["device_aliases"][$mac] = $alias;
	}
	return layer7_save_json($data);
}

/* Constroi o inventario combinado (DHCP leases + ARP), enriquecido com vendor
 * (OUI) e alias do operador. Devolve array ordenado por IP. */
function layer7_device_inventory()
{
	$devices = array();

	if (function_exists("system_get_dhcpleases")) {
		$lr = system_get_dhcpleases();
		$leases = (isset($lr["lease"]) && is_array($lr["lease"])) ? $lr["lease"] : array();
		foreach ($leases as $l) {
			$ip = trim($l["ip"] ?? "");
			$mac = strtolower(trim($l["mac"] ?? ""));
			if ($ip === "" && $mac === "") {
				continue;
			}
			$key = ($mac !== "") ? $mac : ("ip:" . $ip);
			$devices[$key] = array(
				"ip" => $ip,
				"mac" => $mac,
				"hostname" => trim($l["hostname"] ?? ""),
				"descr" => trim($l["descr"] ?? ""),
				"iface" => trim($l["if"] ?? ""),
				"online" => trim($l["online"] ?? ""),
				"type" => trim($l["type"] ?? ""),
				"source" => "dhcp",
			);
		}
	}

	$arp = array();
	@exec("/usr/sbin/arp -an 2>/dev/null", $arp);
	foreach ($arp as $line) {
		if (!preg_match('/\(([0-9.]+)\) at ([0-9a-fA-F:]+) on ([A-Za-z0-9_.]+)/', $line, $m)) {
			continue;
		}
		$ip = $m[1];
		$mac = strtolower($m[2]);
		$iface = $m[3];
		if (!layer7_device_mac_valid($mac)) {
			continue;
		}
		if (isset($devices[$mac])) {
			if (($devices[$mac]["ip"] ?? "") === "") {
				$devices[$mac]["ip"] = $ip;
			}
			if (($devices[$mac]["iface"] ?? "") === "") {
				$devices[$mac]["iface"] = $iface;
			}
			$devices[$mac]["source"] = (strpos((string)$devices[$mac]["source"], "dhcp") !== false) ? "dhcp+arp" : "arp";
			if (($devices[$mac]["online"] ?? "") === "") {
				$devices[$mac]["online"] = "active/online";
			}
		} else {
			$devices[$mac] = array(
				"ip" => $ip,
				"mac" => $mac,
				"hostname" => "",
				"descr" => "",
				"iface" => $iface,
				"online" => "active/online",
				"type" => "dynamic",
				"source" => "arp",
			);
		}
	}

	$prefixes = array();
	foreach ($devices as $d) {
		$mac = $d["mac"] ?? "";
		if ($mac !== "" && strlen($mac) >= 8) {
			$prefixes[substr($mac, 0, 8)] = true;
		}
	}
	$vmap = layer7_device_vendor_map(array_keys($prefixes));
	$aliases = layer7_device_aliases_load();

	$out = array();
	foreach ($devices as $d) {
		$mac = $d["mac"] ?? "";
		$d["vendor"] = ($mac !== "" && isset($vmap[substr($mac, 0, 8)])) ? $vmap[substr($mac, 0, 8)] : "";
		$d["alias"] = ($mac !== "" && isset($aliases[$mac])) ? $aliases[$mac] : "";
		$out[] = $d;
	}

	usort($out, function ($a, $b) {
		$la = @ip2long($a["ip"] ?? "");
		$lb = @ip2long($b["ip"] ?? "");
		if ($la === false) { $la = -1; }
		if ($lb === false) { $lb = -1; }
		return $la <=> $lb;
	});
	return $out;
}

/* ============================================================
 * Caminho A / A2 — politicas por dispositivo (MAC -> IP).
 *
 * Em pfSense o PF impoe por IP (nao ha match por MAC). Tal como o proprio
 * pfSense faz "regras por dispositivo" via static mapping -> IP, aqui o
 * operador escolhe dispositivos por MAC num grupo; o pacote resolve MAC->IP
 * actual (DHCP leases + ARP) e grava em `device_ips` no grupo. O daemon le
 * `device_ips` como hosts de origem (ver policy.c parse_group).
 * ============================================================ */

/* Resolve um conjunto de MACs para os IPv4 actuais (DHCP leases + ARP).
 * Prefere lease online/activo; ARP preenche clientes de IP estatico. */
function layer7_resolve_macs_to_ips($macs)
{
	$want = array();
	foreach ((array)$macs as $m) {
		$m = strtolower(trim((string)$m));
		if (layer7_device_mac_valid($m)) {
			$want[$m] = true;
		}
	}
	if (empty($want)) {
		return array();
	}
	$map = array();
	if (function_exists("system_get_dhcpleases")) {
		$lr = system_get_dhcpleases();
		$leases = (isset($lr["lease"]) && is_array($lr["lease"])) ? $lr["lease"] : array();
		foreach ($leases as $l) {
			$mac = strtolower(trim($l["mac"] ?? ""));
			$ip = trim($l["ip"] ?? "");
			if (!isset($want[$mac]) || $ip === "" || !layer7_ipv4_valid($ip)) {
				continue;
			}
			$online = strtolower((string)($l["online"] ?? ""));
			$is_on = (strpos($online, "online") !== false || strpos($online, "active") !== false);
			if (!isset($map[$mac]) || $is_on) {
				$map[$mac] = $ip;
			}
		}
	}
	$arp = array();
	@exec("/usr/sbin/arp -an 2>/dev/null", $arp);
	foreach ($arp as $line) {
		if (!preg_match('/\(([0-9.]+)\) at ([0-9a-fA-F:]+)/', $line, $mm)) {
			continue;
		}
		$ip = $mm[1];
		$mac = strtolower($mm[2]);
		if (!isset($want[$mac]) || !layer7_ipv4_valid($ip)) {
			continue;
		}
		if (!isset($map[$mac])) {
			$map[$mac] = $ip;
		}
	}
	return array_values(array_unique(array_values($map)));
}

/* Normaliza uma lista de MACs (textarea ou array) para MACs validos. */
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

/* Re-resolve `device_ips` de todos os grupos a partir de `device_macs`.
 * Grava e sinaliza reload apenas se algo mudou. Devolve total de IPs. */
function layer7_devices_resync()
{
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		return 0;
	}
	$changed = false;
	$total = 0;
	foreach ($data["layer7"]["groups"] as $i => $g) {
		$macs = (isset($g["device_macs"]) && is_array($g["device_macs"])) ? $g["device_macs"] : array();
		if (empty($macs)) {
			if (isset($data["layer7"]["groups"][$i]["device_ips"])) {
				unset($data["layer7"]["groups"][$i]["device_ips"]);
				$changed = true;
			}
			continue;
		}
		$ips = layer7_resolve_macs_to_ips($macs);
		$old = (isset($g["device_ips"]) && is_array($g["device_ips"])) ? $g["device_ips"] : array();
		sort($ips);
		$old_sorted = $old;
		sort($old_sorted);
		if ($ips !== $old_sorted) {
			$changed = true;
		}
		$data["layer7"]["groups"][$i]["device_ips"] = $ips;
		$total += count($ips);
	}
	if ($changed) {
		layer7_save_json($data);
		layer7_signal_reload();
	}
	return $total;
}

function layer7_nav_items()
{
	return array(
		"status" => array("label" => l7_t("Estado"), "href" => "layer7_status.php"),
		"devices" => array("label" => l7_t("Dispositivos"), "href" => "layer7_devices.php"),
		"policies" => array("label" => l7_t("Politicas"), "href" => "layer7_policies.php"),
		"blacklists" => array("label" => l7_t("Blacklists"), "href" => "layer7_blacklists.php"),
		"allowlist" => array("label" => l7_t("Allowlist"), "href" => "layer7_allowlist.php"),
		"eventos" => array("label" => l7_t("Eventos"), "href" => "layer7_events.php"),
		"reports" => array("label" => l7_t("Relatorios"), "href" => "layer7_reports.php"),
		"settings" => array("label" => l7_t("Definicoes"), "href" => "layer7_settings.php"),
		"diagnosticos" => array("label" => l7_t("Diagnosticos"), "href" => "layer7_diagnostics.php"),
		"removal" => array("label" => l7_t("Remocao do pacote"), "href" => "layer7_removal.php")
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
	.layer7-page .layer7-admin-block { margin-top: 26px; }
	.layer7-page .layer7-admin-block:first-child { margin-top: 0; }
	.layer7-page .layer7-admin-block__header { background: #4b4b4b; color: #fff; border: 1px solid #3f3f3f; border-bottom: 0; padding: 9px 12px; font-size: 15px; font-weight: 700; line-height: 1.35; }
	.layer7-page .layer7-admin-block__header .badge { margin-left: 6px; background: rgba(255,255,255,0.18); color: #fff; vertical-align: middle; }
	.layer7-page .layer7-admin-block__body { background: #fff; border: 1px solid #d7d7d7; padding: 16px 18px 18px; }
	.layer7-page .layer7-admin-block__body .alert:last-child { margin-bottom: 0; }
	.layer7-page .layer7-admin-block__body .dl-horizontal:last-child { margin-bottom: 0; }
	.layer7-page .layer7-admin-block__body > .layer7-lead:first-child { margin-top: 0; }
	.layer7-page .layer7-admin-block__body .layer7-form-card { margin-top: 12px; background: #fafafa; border-color: #dcdcdc; }
	.layer7-page .layer7-admin-block__body .layer7-form-card:first-child { margin-top: 0; }
	.layer7-page .layer7-admin-block__body .layer7-form-card__title { margin: 0 0 10px; font-size: 15px; font-weight: 700; }
	.layer7-page .layer7-admin-block__body .layer7-toolbar:first-child { margin-top: 0; }
	.layer7-page .layer7-admin-block__body .form-horizontal .form-group { margin: 0; padding: 12px 0; border-top: 1px solid #ececec; }
	.layer7-page .layer7-admin-block__body .form-horizontal .form-group:first-child { padding-top: 0; border-top: 0; }
	.layer7-page .layer7-admin-block__body .form-horizontal .form-group:last-child { padding-bottom: 0; }
	.layer7-page .layer7-admin-block__body .form-horizontal .help-block { margin-top: 4px; }
	.layer7-page .layer7-admin-block__actions { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
	.layer7-page .layer7-admin-block__note { margin-top: 12px; color: #666; }
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
	.layer7-page .layer7-form-card { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 16px 18px; margin-top: 14px; }
	.layer7-page .layer7-form-card:first-child { margin-top: 0; }
	.layer7-page .layer7-form-card__title { margin: 0 0 12px; font-size: 16px; font-weight: 600; }
	.layer7-page .layer7-form-card__actions { margin-top: 14px; }
	.layer7-page .layer7-form-card__actions .btn { margin-right: 8px; margin-bottom: 6px; }
	.layer7-page .layer7-danger-zone { background: #fff8f8; border-color: #ebcccc; }
	.layer7-page .layer7-readonly-block { background: #f7f7f7; border: 1px solid #e5e5e5; border-radius: 3px; padding: 12px 14px; }
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
define('L7_BL_LOCK', '/tmp/layer7-bl-update.lock');
define('L7_BL_SCRIPT', '/usr/local/etc/layer7/update-blacklists.sh');
define('L7_BL_STATE_DIR', L7_BL_DIR . '/.state');
define('L7_BL_ACTIVE_STATE', L7_BL_STATE_DIR . '/active-snapshot.state');
define('L7_BL_FALLBACK_STATE', L7_BL_STATE_DIR . '/fallback.state');
define('L7_BL_LKG_DIR', L7_BL_DIR . '/.last-known-good');
define('L7_BL_LKG_STATE', L7_BL_LKG_DIR . '/snapshot.state');

function layer7_bl_official_manifest_url()
{
	return "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt";
}

function layer7_bl_official_mirror_urls()
{
	return array(
		"https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt"
	);
}

function layer7_bl_legacy_source_urls()
{
	return array(
		"http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz",
		"https://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz"
	);
}

function layer7_bl_normalize_source_url($url)
{
	$url = trim((string)$url);
	if ($url === "" || in_array($url, layer7_bl_legacy_source_urls(), true)) {
		return layer7_bl_official_manifest_url();
	}
	if ($url === layer7_bl_official_manifest_url()) {
		return $url;
	}
	foreach (layer7_bl_official_mirror_urls() as $mirror_url) {
		if ($url === $mirror_url) {
			return $url;
		}
	}
	return layer7_bl_official_manifest_url();
}

function layer7_bl_state_file_load($path)
{
	$state = array();
	if (!file_exists($path)) {
		return $state;
	}
	$lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (!is_array($lines)) {
		return $state;
	}
	foreach ($lines as $line) {
		$line = trim((string)$line);
		if ($line === "" || strpos($line, '=') === false) {
			continue;
		}
		list($key, $value) = explode('=', $line, 2);
		$key = trim($key);
		if ($key === "") {
			continue;
		}
		$state[$key] = trim($value);
	}
	return $state;
}

function layer7_bl_runtime_state_load()
{
	return layer7_bl_state_file_load(L7_BL_ACTIVE_STATE);
}

function layer7_bl_lkg_state_load()
{
	return layer7_bl_state_file_load(L7_BL_LKG_STATE);
}

function layer7_bl_fallback_state_load()
{
	return layer7_bl_state_file_load(L7_BL_FALLBACK_STATE);
}

function layer7_bl_config_defaults()
{
	return array(
		"enabled" => false,
		"source_url" => layer7_bl_official_manifest_url(),
		"mirror_urls" => layer7_bl_official_mirror_urls(),
		"auto_update" => false,
		"update_interval_hours" => 24,
		"whitelist" => array(),
		"rules" => array(),
		"category_custom" => array()
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
	$data["source_url"] = layer7_bl_normalize_source_url($data["source_url"] ?? "");
	$data["mirror_urls"] = layer7_bl_official_mirror_urls();
	return $data;
}

function layer7_bl_config_save($config)
{
	if (!is_dir(L7_BL_DIR) && !@mkdir(L7_BL_DIR, 0775, true)) {
		return false;
	}
	@chown(L7_BL_DIR, "www");
	@chgrp(L7_BL_DIR, "wheel");
	@chmod(L7_BL_DIR, 0775);
	$config["source_url"] = layer7_bl_normalize_source_url($config["source_url"] ?? "");
	$config["mirror_urls"] = layer7_bl_official_mirror_urls();
	$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	if (!is_string($json)) {
		return false;
	}
	if (@file_put_contents(L7_BL_CONFIG, $json . "\n", LOCK_EX) === false) {
		return false;
	}
	@chown(L7_BL_CONFIG, "www");
	@chgrp(L7_BL_CONFIG, "wheel");
	@chmod(L7_BL_CONFIG, 0664);
	return true;
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
	if (!is_dir(L7_BL_DIR)) {
		@mkdir(L7_BL_DIR, 0775, true);
	}
	@chown(L7_BL_DIR, "www");
	@chgrp(L7_BL_DIR, "wheel");
	@chmod(L7_BL_DIR, 0775);

	if (is_dir(L7_BL_LOCK)) {
		$stamp = date('Y-m-d H:i:s');
		@file_put_contents(
			L7_BL_PROGRESS,
			$stamp . " ERROR: download ja em curso (lock activo em " . L7_BL_LOCK . ")\n",
			LOCK_EX
		);
		return;
	}

	$stamp = date('Y-m-d H:i:s');
	@file_put_contents(
		L7_BL_PROGRESS,
		$stamp . " INFO: a iniciar download em background...\n",
		LOCK_EX
	);

	$cmd = "/bin/sh " . escapeshellarg(L7_BL_SCRIPT) . " --download";
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

function layer7_bl_download_in_progress()
{
	if (is_dir(L7_BL_LOCK)) {
		return true;
	}
	if (!file_exists(L7_BL_PROGRESS)) {
		return false;
	}
	$mtime = @filemtime(L7_BL_PROGRESS);
	if ($mtime === false || (time() - $mtime) > 600) {
		return false;
	}
	$content = (string)@file_get_contents(L7_BL_PROGRESS);
	if (strpos($content, 'INFO: update complete') !== false) {
		return false;
	}
	if (preg_match('/\bERROR:/', $content) && (time() - $mtime) > 30) {
		return false;
	}
	return true;
}

function layer7_bl_apply()
{
	if (!layer7_bl_sync_custom_category_files()) {
		return false;
	}

	/* Evita IPs stale em layer7_bld_* apos reorder/delete de regras. */
	layer7_flush_dynamic_tables();

	$helper = layer7_pf_helper_path();
	$ensure_ok = true;
	if (is_executable($helper)) {
		exec(escapeshellarg($helper) . " ensure 2>/dev/null", $ensure_out, $ensure_rc);
		$ensure_ok = ($ensure_rc === 0);
	}
	if (function_exists("filter_configure")) {
		filter_configure();
	}
	usleep(500000);
	$tables_raw = array();
	exec("/sbin/pfctl -s Tables 2>/dev/null", $tables_raw, $tables_rc);
	$tables = array();
	if ($tables_rc === 0) {
		foreach ($tables_raw as $line) {
			$line = trim((string)$line);
			if ($line !== "") {
				$tables[$line] = true;
			}
		}
	}
	$tables_ok = isset($tables["layer7_block"]) && isset($tables["layer7_block_dst"]);
	if ((!$ensure_ok || !$tables_ok) && file_exists("/tmp/rules.debug")) {
		if (function_exists("mwexec")) {
			mwexec("/sbin/pfctl -f /tmp/rules.debug");
		} else {
			@shell_exec("/sbin/pfctl -f /tmp/rules.debug 2>/dev/null");
		}
	}
	layer7_signal_reload();
	return true;
}

function layer7_bl_category_id_valid($id)
{
	$id = strtolower(trim((string)$id));
	if ($id === "" || strlen($id) > 47) {
		return false;
	}
	return preg_match('/^[a-z0-9_-]+$/', $id) === 1;
}

function layer7_bl_domains_normalize($raw)
{
	$out = array();
	if (is_array($raw)) {
		$lines = $raw;
	} else {
		$lines = preg_split('/[\r\n]+/', (string)$raw);
	}
	foreach ($lines as $line) {
		$line = strtolower(trim((string)$line));
		if ($line === "" || $line[0] === '#') {
			continue;
		}
		if ($line[0] === '.') {
			$line = substr($line, 1);
		}
		if (!layer7_policy_host_valid($line)) {
			continue;
		}
		$out[] = $line;
	}
	$out = array_values(array_unique($out));
	sort($out);
	return $out;
}

function layer7_bl_category_custom_get($cfg = null)
{
	if (!is_array($cfg)) {
		$cfg = layer7_bl_config_load();
	}
	$raw = isset($cfg["category_custom"]) && is_array($cfg["category_custom"])
		? $cfg["category_custom"] : array();
	$out = array();
	foreach ($raw as $cat => $domains) {
		$cat = strtolower(trim((string)$cat));
		if (!layer7_bl_category_id_valid($cat)) {
			continue;
		}
		$norm = layer7_bl_domains_normalize($domains);
		if (!empty($norm)) {
			$out[$cat] = $norm;
		}
	}
	ksort($out);
	return $out;
}

function layer7_bl_sync_custom_category_files($cfg = null)
{
	if (!is_array($cfg)) {
		$cfg = layer7_bl_config_load();
	}
	$custom = layer7_bl_category_custom_get($cfg);
	$dir = L7_BL_DIR . '/_custom';
	if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
		return false;
	}
	@chown($dir, "www");
	@chgrp($dir, "wheel");
	@chmod($dir, 0775);

	$keep = array();
	foreach ($custom as $cat => $domains) {
		$path = $dir . '/' . $cat . '.domains';
		$keep[$path] = true;
		$content = implode("\n", $domains) . "\n";
		if (@file_put_contents($path, $content, LOCK_EX) === false) {
			return false;
		}
		@chown($path, "www");
		@chgrp($path, "wheel");
		@chmod($path, 0664);
	}

	$existing = glob($dir . '/*.domains');
	if (is_array($existing)) {
		foreach ($existing as $path) {
				if (!isset($keep[$path])) {
					@unlink($path);
				}
			}
		}
	return true;
}

function layer7_bl_cron_fields_for_interval($hours)
{
	$hours = (int)$hours;
	if ($hours < 1) {
		$hours = 1;
	}
	if ($hours > 168) {
		$hours = 168;
	}
	if ($hours < 24) {
		return array("minute" => "0", "hour" => "*/" . $hours,
		    "day" => "*", "month" => "*", "dow" => "*");
	}
	if ($hours === 168) {
		return array("minute" => "0", "hour" => "3",
		    "day" => "*", "month" => "*", "dow" => "0");
	}
	if ($hours === 24) {
		return array("minute" => "0", "hour" => "3",
		    "day" => "*", "month" => "*", "dow" => "*");
	}
	$days = (int)ceil($hours / 24);
	if ($days < 2) {
		$days = 2;
	}
	if ($days > 6) {
		$days = 6;
	}
	return array("minute" => "0", "hour" => "3",
	    "day" => "*/" . $days, "month" => "*", "dow" => "*");
}

function layer7_bl_setup_cron($enabled, $interval_hours)
{
	$cmd = L7_BL_SCRIPT;
	if (function_exists("install_cron_job")) {
		$f = layer7_bl_cron_fields_for_interval($interval_hours);
		install_cron_job($cmd, $enabled, $f["minute"], $f["hour"],
		    $f["day"], $f["month"], $f["dow"]);
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
		"collect_interval" => 5,
		"event_log_enabled" => false,
		"event_retention_days" => 7,
			"event_max_mb" => 100,
			"event_interfaces" => array()
	);
}

function layer7_reports_normalize_interfaces($ifaces)
{
	$out = array();

	if (!is_array($ifaces)) {
		return $out;
	}
	foreach ($ifaces as $iface) {
		$iface = trim((string)$iface);
		if ($iface === "" || strlen($iface) > 32) {
			continue;
		}
		if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $iface)) {
			continue;
		}
		$out[] = $iface;
	}
	return array_values(array_unique($out));
}

function layer7_reports_config()
{
	$data = layer7_load_or_default();
	$cfg = layer7_reports_defaults();

	if (isset($data["layer7"]["reports"]) && is_array($data["layer7"]["reports"])) {
		$stored = $data["layer7"]["reports"];

		$cfg = array_merge($cfg, $stored);
		if (!array_key_exists("event_log_enabled", $stored)) {
			$cfg["event_log_enabled"] = false;
		}
		if (!array_key_exists("event_retention_days", $stored)) {
			$cfg["event_retention_days"] = min(7,
			    max(1, (int)($cfg["retention_days"] ?? 30)));
		}
	}
	$cfg["enabled"] = !empty($cfg["enabled"]);
	$cfg["event_log_enabled"] = !empty($cfg["event_log_enabled"]);

	$cfg["retention_days"] = (int)$cfg["retention_days"];
	if ($cfg["retention_days"] < 1) {
		$cfg["retention_days"] = 1;
	}
	if ($cfg["retention_days"] > 365) {
		$cfg["retention_days"] = 365;
	}

	$cfg["collect_interval"] = (int)$cfg["collect_interval"];
	if (!in_array($cfg["collect_interval"], array(5, 10, 15, 30, 60), true)) {
		$cfg["collect_interval"] = 5;
	}

	$cfg["event_retention_days"] = (int)$cfg["event_retention_days"];
	if ($cfg["event_retention_days"] < 1) {
		$cfg["event_retention_days"] = 1;
	}
	if ($cfg["event_retention_days"] > 365) {
		$cfg["event_retention_days"] = 365;
	}

	$cfg["event_max_mb"] = (int)($cfg["event_max_mb"] ?? 100);
	if ($cfg["event_max_mb"] < 25) {
		$cfg["event_max_mb"] = 25;
	}
	if ($cfg["event_max_mb"] > 1000) {
		$cfg["event_max_mb"] = 1000;
	}

	$cfg["event_interfaces"] = layer7_reports_normalize_interfaces(
	    $cfg["event_interfaces"] ?? array());
	return $cfg;
}

function layer7_log_family_size($path, $keep)
{
	$total = 0;
	$files = 0;
	for ($i = 0; $i <= (int)$keep; $i++) {
		$p = $i === 0 ? $path : ($path . "." . $i);
		if (!is_file($p)) {
			continue;
		}
		$size = @filesize($p);
		if ($size !== false) {
			$total += (int)$size;
			$files++;
		}
	}
	return array("bytes" => $total, "files" => $files);
}

function layer7_log_storage_status()
{
	$data = layer7_load_or_default();
	$l7 = isset($data["layer7"]) && is_array($data["layer7"])
	    ? $data["layer7"] : array();
	$max_mb = max(1, min(100, (int)($l7["log_file_max_mb"] ?? 5)));
	$keep = max(1, min(10, (int)($l7["log_file_keep"] ?? 3)));
	$reports = layer7_reports_config();
	$operational = layer7_log_family_size("/var/log/layer7d.log", $keep);
	$events = layer7_log_family_size("/var/log/layer7-events.log", $keep);
	$db_bytes = is_file(L7_REPORTS_DB) ? (int)@filesize(L7_REPORTS_DB) : 0;

	return array(
		"max_mb_per_file" => $max_mb,
		"keep_files" => $keep,
		"operational" => $operational,
		"events" => $events,
		"db_bytes" => $db_bytes,
		"db_max_mb" => (int)($reports["event_max_mb"] ?? 100),
		"event_log_enabled" => !empty($reports["event_log_enabled"])
	);
}

function layer7_reports_filters_empty($filters)
{
	if (!is_array($filters)) {
		return true;
	}
	foreach ($filters as $value) {
		if (trim((string)$value) !== "") {
			return false;
		}
	}
	return true;
}

function layer7_reports_event_iface_allowed($cfg, $event)
{
	$selected = layer7_reports_normalize_interfaces(
	    $cfg["event_interfaces"] ?? array());
	$iface = trim((string)($event["iface"] ?? ""));

	if (empty($selected)) {
		return true;
	}
	if ($iface === "") {
		return false;
	}
	return in_array($iface, $selected, true);
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
	$log_path = file_exists("/var/log/layer7-events.log")
	    ? "/var/log/layer7-events.log" : "/var/log/layer7d.log";
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
		$event = layer7_reports_event_from_log_line($line);
		if (!is_array($event)) {
			continue;
		}
		$entry = array(
			"ts" => (int)$event["ts"],
			"severity" => "",
			"raw" => (string)$event["raw"],
			"type" => (string)$event["type"],
			"fields" => array(
				"src" => (string)$event["src_ip"],
				"src_host" => (string)$event["src_hostname"],
				"dst" => (string)$event["dst_ip"],
				"host" => (string)$event["host"],
				"app" => (string)$event["app"],
				"cat" => (string)$event["category"],
				"action" => (string)$event["action"],
				"policy" => (string)$event["policy"],
				"iface" => (string)$event["iface"]
			)
		);
		if ($entry["ts"] < $from_ts || $entry["ts"] > $to_ts) {
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

define('L7_REPORTS_DB', L7_REPORTS_DIR . '/reports.db');
define('L7_REPORTS_CURSOR', L7_REPORTS_DIR . '/ingest.cursor');
define('L7_REPORTS_EVENTS_CURSOR', L7_REPORTS_DIR . '/ingest-events.cursor');
define('L7_OPERATIONAL_LOG', '/var/log/layer7d.log');
define('L7_EVENTS_LOG', '/var/log/layer7-events.log');
define('L7_REPORTS_COLLECT_PHP', '/usr/local/etc/layer7/layer7-reports-collect.php');

function layer7_reports_db_available()
{
	return class_exists("SQLite3");
}

function layer7_reports_db_connect($readonly = false)
{
	if (!layer7_reports_db_available()) {
		return null;
	}
	@mkdir(L7_REPORTS_DIR, 0755, true);
	$flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
	if ($readonly) {
		$flags = SQLITE3_OPEN_READONLY;
	}
	try {
		$db = new SQLite3(L7_REPORTS_DB, $flags);
		$db->busyTimeout(3000);
		return $db;
	} catch (Exception $e) {
		return null;
	}
}

function layer7_reports_init_schema($db)
{
	if (!$db) {
		return false;
	}
	$sql = array(
		"CREATE TABLE IF NOT EXISTS events (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			ts INTEGER NOT NULL,
			ts_text TEXT NOT NULL,
			type TEXT NOT NULL,
			src_ip TEXT NOT NULL DEFAULT '',
			src_hostname TEXT NOT NULL DEFAULT '',
			dst_ip TEXT NOT NULL DEFAULT '',
			host TEXT NOT NULL DEFAULT '',
			app TEXT NOT NULL DEFAULT '',
			category TEXT NOT NULL DEFAULT '',
			action TEXT NOT NULL DEFAULT '',
			policy TEXT NOT NULL DEFAULT '',
			iface TEXT NOT NULL DEFAULT '',
			raw TEXT NOT NULL DEFAULT ''
		)",
		"CREATE INDEX IF NOT EXISTS idx_events_ts ON events(ts)",
		"CREATE INDEX IF NOT EXISTS idx_events_src_ip ON events(src_ip)",
		"CREATE INDEX IF NOT EXISTS idx_events_host ON events(host)",
		"CREATE INDEX IF NOT EXISTS idx_events_action ON events(action)",
		"CREATE INDEX IF NOT EXISTS idx_events_policy ON events(policy)",
		"CREATE TABLE IF NOT EXISTS identity_map (
			src_ip TEXT PRIMARY KEY,
			hostname TEXT NOT NULL DEFAULT '',
			source TEXT NOT NULL DEFAULT 'ip_hostname',
			first_seen INTEGER NOT NULL,
			last_seen INTEGER NOT NULL
		)",
		"CREATE TABLE IF NOT EXISTS daily_kpi (
			day_key TEXT PRIMARY KEY,
			total_events INTEGER NOT NULL DEFAULT 0,
			blocked_events INTEGER NOT NULL DEFAULT 0,
			allowed_events INTEGER NOT NULL DEFAULT 0,
			monitor_events INTEGER NOT NULL DEFAULT 0,
			unique_devices INTEGER NOT NULL DEFAULT 0,
			updated_at INTEGER NOT NULL DEFAULT 0
		)",
		"CREATE TABLE IF NOT EXISTS meta (
			k TEXT PRIMARY KEY,
			v TEXT NOT NULL
		)"
	);
	foreach ($sql as $q) {
		if (@$db->exec($q) === false) {
			return false;
		}
	}
	return true;
}

function layer7_reports_parse_kv_line($text)
{
	$out = array();
	if (!is_string($text) || $text === "") {
		return $out;
	}
	if (preg_match_all('/(\w+)=(".*?"|\S+)/', $text, $m, PREG_SET_ORDER)) {
		foreach ($m as $kv) {
			$key = $kv[1];
			$val = trim($kv[2], "\"");
			$out[$key] = $val;
		}
	}
	return $out;
}

function layer7_reports_resolver_domains()
{
	return array(
		"dns.google",
		"dns.google.com",
		"dns.quad9.net",
		"dns9.quad9.net",
		"dns10.quad9.net",
		"dns11.quad9.net",
		"one.one.one.one",
		"cloudflare-dns.com",
		"security.cloudflare-dns.com",
		"family.cloudflare-dns.com",
		"dns.adguard.com",
		"dns-family.adguard.com",
		"dns-unfiltered.adguard.com",
		"doh.opendns.com",
		"doh.cleanbrowsing.org",
		"dns.nextdns.io"
	);
}

function layer7_reports_host_is_resolver($host)
{
	$host = strtolower(trim((string)$host));
	if ($host === "" || $host === "-") {
		return false;
	}
	foreach (layer7_reports_resolver_domains() as $resolver) {
		if ($host === $resolver || substr($host, -strlen("." . $resolver)) === "." . $resolver) {
			return true;
		}
	}
	return false;
}

function layer7_reports_guess_attempted_host($db, $src_ip, $ts, $window_seconds = 120)
{
	if (!$db || trim((string)$src_ip) === "" || (int)$ts <= 0) {
		return "";
	}
	$stmt = @$db->prepare("SELECT host
		FROM events
		WHERE type='dns_query'
		  AND src_ip=:src_ip
		  AND host <> ''
		  AND ts BETWEEN :from_ts AND :to_ts
		ORDER BY ts DESC
		LIMIT 10");
	if (!$stmt) {
		return "";
	}
	$stmt->bindValue(":src_ip", (string)$src_ip, SQLITE3_TEXT);
	$stmt->bindValue(":from_ts", (int)$ts - (int)$window_seconds, SQLITE3_INTEGER);
	$stmt->bindValue(":to_ts", (int)$ts, SQLITE3_INTEGER);
	$res = @$stmt->execute();
	while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
		$host = trim((string)($row["host"] ?? ""));
		if ($host === "" || layer7_reports_host_is_resolver($host)) {
			continue;
		}
		return $host;
	}
	return "";
}

function layer7_reports_parse_line_timestamp($line, &$ts, &$ts_text, &$rest)
{
	$line = trim((string)$line);
	$ts = 0;
	$ts_text = "";
	$rest = "";
	if ($line === "") {
		return false;
	}

	if (preg_match('/^(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})\s+(.*)$/', $line, $m)) {
		$parsed = @strtotime(str_replace("T", " ", $m[1]));
		if ($parsed === false) {
			return false;
		}
		$ts = (int)$parsed;
		$ts_text = date("Y-m-d H:i:s", $ts);
		$rest = (string)$m[2];
		return true;
	}

	if (preg_match('/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})\s+(.*)$/', $line, $m)) {
		$stamp = preg_replace('/\s+/', ' ', trim($m[1]));
		$year = date("Y");
		$parsed = @strtotime($stamp . " " . $year);
		if ($parsed !== false && $parsed > (time() + 86400)) {
			$parsed = @strtotime($stamp . " " . ((int)$year - 1));
		}
		if ($parsed === false) {
			return false;
		}
		$msg = (string)$m[2];
		if (preg_match('/^[^ ]+\s+layer7d(?:\[\d+\])?:\s*(.*)$/', $msg, $m2)) {
			$msg = $m2[1];
		}
		$ts = (int)$parsed;
		$ts_text = date("Y-m-d H:i:s", $ts);
		$rest = $msg;
		return true;
	}

	return false;
}

function layer7_reports_event_from_log_line($line)
{
	$ts = 0;
	$ts_str = "";
	$rest = "";
	if (!layer7_reports_parse_line_timestamp($line, $ts, $ts_str, $rest)) {
		return null;
	}
	if (preg_match('/^\[(\w+)\]\s*(.*)$/', $rest, $m)) {
		$rest = $m[2];
	}

	$type = "";
	$action = "";
	if (strpos($rest, "flow_decide:") === 0) {
		$type = "flow";
	} elseif (strpos($rest, "bl_block:") === 0 ||
	    strpos($rest, "sni_bl_block:") === 0) {
		$type = "blacklist";
		$action = "block";
	} elseif (strpos($rest, "dns_query:") === 0) {
		$type = "dns_query";
		$action = "query";
	} elseif (strpos($rest, "dns_block:") === 0) {
		$type = "dns_block";
		$action = "block";
	} elseif (strpos($rest, "enforce_block:") === 0 ||
	    strpos($rest, "enforce_block_dst:") === 0) {
		$type = "enforce";
		/*
		 * O evento flow/blacklist ja contabiliza a decisao de bloqueio.
		 * Este registo confirma a aplicacao no PF e nao deve duplicar o
		 * KPI de bloqueios.
		 */
		$action = "enforced";
	} elseif (strpos($rest, "enforce_tag:") === 0) {
		$type = "enforce";
		$action = "tag";
	} elseif (strpos($rest, "allowlist_dns:") === 0 ||
	    strpos($rest, "allowlist_flow:") === 0) {
		$type = "allowlist";
		$action = "allow";
	} else {
		return null;
	}

	$f = layer7_reports_parse_kv_line($rest);
	if ($action === "" && isset($f["action"])) {
		$action = (string)$f["action"];
	}
	if ($action === "") {
		$action = "monitor";
	}

	return array(
		"ts" => (int)$ts,
		"ts_text" => $ts_str,
		"type" => $type,
		"src_ip" => (string)($f["src"] ?? ""),
		"src_hostname" => (string)($f["src_host"] ?? ""),
		"dst_ip" => (string)($f["dst"] ?? ($f["resolver"] ?? "")),
		"host" => (string)($f["host"] ?? ($f["qname"] ?? ($f["domain"] ?? ""))),
		"app" => (string)($f["app"] ?? ($type === "dns_query" ? "DNS" : "")),
		"category" => (string)($f["cat"] ?? ($type === "dns_query" ? "DNS" : "")),
		"action" => (string)$action,
		"policy" => (string)($f["policy"] ?? ""),
		"iface" => (string)($f["iface"] ?? ""),
		"raw" => $rest
	);
}

function layer7_reports_cursor_load($path, $default_inode, $default_offset)
{
	if (!file_exists($path)) {
		return array("inode" => (string)$default_inode, "offset" => (int)$default_offset);
	}
	$raw = @file_get_contents($path);
	$obj = @json_decode($raw, true);
	if (!is_array($obj)) {
		return array("inode" => (string)$default_inode, "offset" => (int)$default_offset);
	}
	return array(
		"inode" => isset($obj["inode"]) ? (string)$obj["inode"] : (string)$default_inode,
		"offset" => isset($obj["offset"]) ? (int)$obj["offset"] : (int)$default_offset
	);
}

function layer7_reports_cursor_save($path, $inode, $offset)
{
	$payload = json_encode(array("inode" => (string)$inode, "offset" => (int)$offset), JSON_UNESCAPED_SLASHES);
	if (!is_string($payload)) {
		return;
	}
	@file_put_contents($path, $payload . "\n", LOCK_EX);
}

function layer7_reports_update_identity_map($db, $src_ip, $hostname, $ts)
{
	if (!$db || !is_string($src_ip) || $src_ip === "") {
		return;
	}
	$hostname = trim((string)$hostname);
	$stmt = @$db->prepare("INSERT INTO identity_map(src_ip, hostname, source, first_seen, last_seen)
		VALUES(:ip, :hn, 'ip_hostname', :ts, :ts)
		ON CONFLICT(src_ip) DO UPDATE SET
			hostname = CASE WHEN excluded.hostname <> '' THEN excluded.hostname ELSE identity_map.hostname END,
			last_seen = excluded.last_seen");
	if (!$stmt) {
		return;
	}
	$stmt->bindValue(":ip", $src_ip, SQLITE3_TEXT);
	$stmt->bindValue(":hn", $hostname, SQLITE3_TEXT);
	$stmt->bindValue(":ts", (int)$ts, SQLITE3_INTEGER);
	@$stmt->execute();
}

function layer7_reports_refresh_daily_kpi($db, $day_keys)
{
	if (!$db || empty($day_keys)) {
		return;
	}
	$days = array_values(array_unique($day_keys));
	foreach ($days as $day) {
		$start = @strtotime($day . " 00:00:00");
		$end = @strtotime($day . " 23:59:59");
		if ($start === false || $end === false) {
			continue;
		}
		$q = @$db->prepare("SELECT
			COUNT(*) AS total_events,
			SUM(CASE WHEN action='block' THEN 1 ELSE 0 END) AS blocked_events,
			SUM(CASE WHEN action='allow' THEN 1 ELSE 0 END) AS allowed_events,
			SUM(CASE WHEN action NOT IN ('block','allow') THEN 1 ELSE 0 END) AS monitor_events,
			COUNT(DISTINCT CASE WHEN src_ip <> '' THEN src_ip END) AS unique_devices
			FROM events WHERE ts BETWEEN :start AND :end");
		if (!$q) {
			continue;
		}
		$q->bindValue(":start", (int)$start, SQLITE3_INTEGER);
		$q->bindValue(":end", (int)$end, SQLITE3_INTEGER);
		$r = @$q->execute();
		$row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
		if (!is_array($row)) {
			continue;
		}
		$up = @$db->prepare("INSERT INTO daily_kpi(day_key, total_events, blocked_events, allowed_events, monitor_events, unique_devices, updated_at)
			VALUES(:day, :t, :b, :a, :m, :u, :now)
			ON CONFLICT(day_key) DO UPDATE SET
			total_events=excluded.total_events,
			blocked_events=excluded.blocked_events,
			allowed_events=excluded.allowed_events,
			monitor_events=excluded.monitor_events,
			unique_devices=excluded.unique_devices,
			updated_at=excluded.updated_at");
		if (!$up) {
			continue;
		}
		$up->bindValue(":day", (string)$day, SQLITE3_TEXT);
		$up->bindValue(":t", (int)$row["total_events"], SQLITE3_INTEGER);
		$up->bindValue(":b", (int)$row["blocked_events"], SQLITE3_INTEGER);
		$up->bindValue(":a", (int)$row["allowed_events"], SQLITE3_INTEGER);
		$up->bindValue(":m", (int)$row["monitor_events"], SQLITE3_INTEGER);
		$up->bindValue(":u", (int)$row["unique_devices"], SQLITE3_INTEGER);
		$up->bindValue(":now", time(), SQLITE3_INTEGER);
		@$up->execute();
	}
}

function layer7_reports_insert_event($db, $insert, $cfg, $event, &$day_keys)
{
	if (!is_array($event) || !layer7_reports_event_iface_allowed($cfg, $event)) {
		return 0;
	}
	$insert->reset();
	$insert->bindValue(":ts", (int)$event["ts"], SQLITE3_INTEGER);
	$insert->bindValue(":ts_text", (string)$event["ts_text"], SQLITE3_TEXT);
	$insert->bindValue(":type", (string)$event["type"], SQLITE3_TEXT);
	$insert->bindValue(":src_ip", (string)$event["src_ip"], SQLITE3_TEXT);
	$insert->bindValue(":src_hostname", (string)$event["src_hostname"], SQLITE3_TEXT);
	$insert->bindValue(":dst_ip", (string)$event["dst_ip"], SQLITE3_TEXT);
	$insert->bindValue(":host", (string)$event["host"], SQLITE3_TEXT);
	$insert->bindValue(":app", (string)$event["app"], SQLITE3_TEXT);
	$insert->bindValue(":category", (string)$event["category"], SQLITE3_TEXT);
	$insert->bindValue(":action", (string)$event["action"], SQLITE3_TEXT);
	$insert->bindValue(":policy", (string)$event["policy"], SQLITE3_TEXT);
	$insert->bindValue(":iface", (string)$event["iface"], SQLITE3_TEXT);
	$insert->bindValue(":raw", (string)$event["raw"], SQLITE3_TEXT);
	$res = @$insert->execute();
	if ($res === false) {
		return 0;
	}
	layer7_reports_update_identity_map($db, $event["src_ip"],
	    $event["src_hostname"], $event["ts"]);
	$day_keys[] = date("Y-m-d", (int)$event["ts"]);
	return 1;
}

function layer7_reports_ingest_path($db, $insert, $cfg, $log_path,
    $cursor_path, &$day_keys)
{
	if (!file_exists($log_path)) {
		return array("ok" => true, "ingested" => 0, "reason" => "log_missing");
	}
	clearstatcache(true, $log_path);
	$st = @stat($log_path);
	if (!is_array($st)) {
		return array("ok" => false, "ingested" => 0, "reason" => "stat_failed");
	}
	$inode = isset($st["ino"]) ? (string)$st["ino"] : "";
	$size = isset($st["size"]) ? (int)$st["size"] : 0;
	$cursor = layer7_reports_cursor_load($cursor_path, $inode, 0);
	$cursor_inode = (string)$cursor["inode"];
	$cursor_offset = max(0, (int)$cursor["offset"]);
	$sources = array();

	if ($cursor_inode === $inode && $cursor_offset <= $size) {
		$sources[] = array("path" => $log_path, "offset" => $cursor_offset,
		    "current" => true);
	} else {
		$matched_rotation = 0;
		for ($i = 1; $i <= 10; $i++) {
			$rotated = $log_path . "." . $i;
			$rst = @stat($rotated);
			if (is_array($rst) && (string)($rst["ino"] ?? "") === $cursor_inode) {
				$matched_rotation = $i;
				break;
			}
		}
		if ($matched_rotation > 0) {
			for ($i = $matched_rotation; $i >= 1; $i--) {
				$sources[] = array(
					"path" => $log_path . "." . $i,
					"offset" => ($i === $matched_rotation ? $cursor_offset : 0),
					"current" => false
				);
			}
		}
		$sources[] = array("path" => $log_path, "offset" => 0,
		    "current" => true);
	}

	$ingested = 0;
	$current_offset = 0;
	$current_inode = $inode;
	@$db->exec("BEGIN IMMEDIATE TRANSACTION");
	foreach ($sources as $source) {
		$fh = @fopen($source["path"], "r");
		if (!$fh) {
			if (!empty($source["current"])) {
				@$db->exec("ROLLBACK");
				return array("ok" => false, "ingested" => $ingested,
				    "reason" => "open_failed");
			}
			continue;
		}
		if (!empty($source["current"])) {
			$fst = @fstat($fh);
			if (is_array($fst) && isset($fst["ino"])) {
				$current_inode = (string)$fst["ino"];
			}
		}
		if ((int)$source["offset"] > 0) {
			@fseek($fh, (int)$source["offset"]);
		}
		while (($line = fgets($fh)) !== false) {
			$event = layer7_reports_event_from_log_line($line);
			$ingested += layer7_reports_insert_event(
			    $db, $insert, $cfg, $event, $day_keys);
		}
		if (!empty($source["current"])) {
			$pos = @ftell($fh);
			$current_offset = ($pos === false) ? 0 : (int)$pos;
		}
		@fclose($fh);
	}
	if (@$db->exec("COMMIT") === false) {
		@$db->exec("ROLLBACK");
		return array("ok" => false, "ingested" => $ingested,
		    "reason" => "commit_failed");
	}
	layer7_reports_cursor_save($cursor_path, $current_inode, $current_offset);
	return array("ok" => true, "ingested" => $ingested, "reason" => "ok");
}

function layer7_reports_enforce_db_cap($db, $max_mb)
{
	$max_mb = max(25, min(1000, (int)$max_mb));
	$max_bytes = $max_mb * 1024 * 1024;
	clearstatcache(true, L7_REPORTS_DB);
	$size = file_exists(L7_REPORTS_DB) ? (int)@filesize(L7_REPORTS_DB) : 0;
	if ($size <= $max_bytes) {
		return array("ok" => true, "deleted" => 0, "size" => $size);
	}
	$count = (int)@$db->querySingle("SELECT COUNT(*) FROM events");
	if ($count <= 0) {
		return array("ok" => true, "deleted" => 0, "size" => $size);
	}
	$target_bytes = (int)floor($max_bytes * 0.85);
	$keep_ratio = max(0.05, min(0.95, $target_bytes / max(1, $size)));
	$delete_count = max(1, (int)ceil($count * (1.0 - $keep_ratio)));
	$sql = "DELETE FROM events WHERE id IN (
		SELECT id FROM events ORDER BY ts ASC, id ASC LIMIT " .
		(int)$delete_count . ")";
	$before = (int)@$db->querySingle("SELECT COUNT(*) FROM events");
	@$db->exec("BEGIN IMMEDIATE TRANSACTION");
	@$db->exec($sql);
	@$db->exec("COMMIT");
	$after = (int)@$db->querySingle("SELECT COUNT(*) FROM events");
	$deleted = max(0, $before - $after);
	if ($deleted > 0) {
		@$db->exec("VACUUM");
	}
	clearstatcache(true, L7_REPORTS_DB);
	$size = file_exists(L7_REPORTS_DB) ? (int)@filesize(L7_REPORTS_DB) : 0;
	return array("ok" => true, "deleted" => $deleted, "size" => $size);
}

function layer7_reports_ingest_log_incremental()
{
	$cfg = layer7_reports_config();
	if (empty($cfg["event_log_enabled"])) {
		return array("ok" => true, "ingested" => 0,
		    "reason" => "event_log_disabled");
	}
	$db = layer7_reports_db_connect(false);
	if (!$db) {
		return array("ok" => false, "ingested" => 0,
		    "reason" => "sqlite_unavailable");
	}
	if (!layer7_reports_init_schema($db)) {
		return array("ok" => false, "ingested" => 0,
		    "reason" => "schema_failed");
	}
	$insert = @$db->prepare("INSERT INTO events(ts, ts_text, type, src_ip, src_hostname, dst_ip, host, app, category, action, policy, iface, raw)
		VALUES(:ts,:ts_text,:type,:src_ip,:src_hostname,:dst_ip,:host,:app,:category,:action,:policy,:iface,:raw)");
	if (!$insert) {
		return array("ok" => false, "ingested" => 0,
		    "reason" => "insert_prepare_failed");
	}

	$day_keys = array();
	$legacy = layer7_reports_ingest_path($db, $insert, $cfg,
	    L7_OPERATIONAL_LOG, L7_REPORTS_CURSOR, $day_keys);
	$events = layer7_reports_ingest_path($db, $insert, $cfg,
	    L7_EVENTS_LOG, L7_REPORTS_EVENTS_CURSOR, $day_keys);
	if (empty($legacy["ok"]) || empty($events["ok"])) {
		return array("ok" => false,
		    "ingested" => (int)($legacy["ingested"] ?? 0) +
		        (int)($events["ingested"] ?? 0),
		    "reason" => empty($legacy["ok"]) ?
		        ($legacy["reason"] ?? "legacy_failed") :
		        ($events["reason"] ?? "events_failed"));
	}
	layer7_reports_refresh_daily_kpi($db, $day_keys);
	$cap = layer7_reports_enforce_db_cap($db,
	    (int)($cfg["event_max_mb"] ?? 100));
	return array(
		"ok" => true,
		"ingested" => (int)$legacy["ingested"] + (int)$events["ingested"],
		"purged_by_size" => (int)($cap["deleted"] ?? 0),
		"reason" => "ok"
	);
}

function layer7_reports_purge_db($retention_days, $summary_retention_days = null)
{
	$retention_days = (int)$retention_days;
	if ($retention_days < 1) {
		$retention_days = 1;
	}
	if ($retention_days > 365) {
		$retention_days = 365;
	}
	$summary_retention_days = $summary_retention_days === null ?
	    $retention_days : (int)$summary_retention_days;
	if ($summary_retention_days < 1) {
		$summary_retention_days = 1;
	}
	if ($summary_retention_days > 365) {
		$summary_retention_days = 365;
	}
	$db = layer7_reports_db_connect(false);
	if (!$db) {
		return array("ok" => false, "deleted" => 0, "reason" => "sqlite_unavailable");
	}
	if (!layer7_reports_init_schema($db)) {
		return array("ok" => false, "deleted" => 0, "reason" => "schema_failed");
	}
	$cutoff = time() - ($retention_days * 86400);
	$summary_cutoff = time() - ($summary_retention_days * 86400);
	$before = @$db->querySingle("SELECT COUNT(*) FROM events");
	$del = @$db->prepare("DELETE FROM events WHERE ts < :cutoff");
	if ($del) {
		$del->bindValue(":cutoff", (int)$cutoff, SQLITE3_INTEGER);
		@$del->execute();
	}
	@$db->exec("DELETE FROM identity_map WHERE last_seen < " . (int)$cutoff);
	@$db->exec("DELETE FROM daily_kpi WHERE day_key < '" . date("Y-m-d", $summary_cutoff) . "'");
	$after = @$db->querySingle("SELECT COUNT(*) FROM events");
	$deleted = max(0, (int)$before - (int)$after);
	if ($deleted > 0) {
		@$db->exec("VACUUM");
	}
	$cfg = layer7_reports_config();
	$cap = layer7_reports_enforce_db_cap($db,
	    (int)($cfg["event_max_mb"] ?? 100));
	return array("ok" => true,
	    "deleted" => $deleted + (int)($cap["deleted"] ?? 0),
	    "reason" => "ok");
}

function layer7_reports_clear_all()
{
	$deleted = 0;
	$db = layer7_reports_db_connect(false);
	if ($db) {
		$before = @$db->querySingle("SELECT COUNT(*) FROM events");
		@$db->exec("DELETE FROM events");
		@$db->exec("DELETE FROM identity_map");
		@$db->exec("DELETE FROM daily_kpi");
		@$db->exec("VACUUM");
		$after = @$db->querySingle("SELECT COUNT(*) FROM events");
		$deleted = max(0, (int)$before - (int)$after);
	}
	@unlink(L7_REPORTS_HISTORY);

	foreach (array(
	    array(L7_OPERATIONAL_LOG, L7_REPORTS_CURSOR),
	    array(L7_EVENTS_LOG, L7_REPORTS_EVENTS_CURSOR)
	) as $source) {
		$log_path = $source[0];
		$cursor_path = $source[1];
		if (file_exists($log_path)) {
			$st = @stat($log_path);
			$inode = isset($st["ino"]) ? (string)$st["ino"] : "";
			$size = isset($st["size"]) ? (int)$st["size"] : 0;
			layer7_reports_cursor_save($cursor_path, $inode, $size);
		} else {
			@unlink($cursor_path);
		}
	}

	return array("ok" => true, "deleted" => $deleted);
}

function layer7_reports_filter_build($from_ts, $to_ts, $filters, &$params)
{
	$where = array("ts BETWEEN :from_ts AND :to_ts");
	$params[":from_ts"] = (int)$from_ts;
	$params[":to_ts"] = (int)$to_ts;

	$map = array(
		"src_ip" => "src_ip",
		"host" => "host",
		"app" => "app",
		"category" => "category",
		"policy" => "policy",
		"action" => "action"
	);
	foreach ($map as $fk => $col) {
		if (isset($filters[$fk]) && trim((string)$filters[$fk]) !== "") {
			$p = ":f_" . $fk;
			$where[] = "{$col} = {$p}";
			$params[$p] = trim((string)$filters[$fk]);
		}
	}
	if (isset($filters["q"]) && trim((string)$filters["q"]) !== "") {
		$q = trim((string)$filters["q"]);
		$where[] = "(host LIKE :f_q OR app LIKE :f_q OR category LIKE :f_q OR policy LIKE :f_q OR src_ip LIKE :f_q OR src_hostname LIKE :f_q)";
		$params[":f_q"] = "%" . $q . "%";
	}
	return implode(" AND ", $where);
}

function layer7_reports_fetch_summary($from_ts, $to_ts, $filters = array())
{
	$db = layer7_reports_db_connect(true);
	if (!$db || !layer7_reports_init_schema($db)) {
		return null;
	}
	$params = array();
	$where = layer7_reports_filter_build($from_ts, $to_ts, $filters, $params);
	$sql = "SELECT
		COUNT(*) AS total_events,
		SUM(CASE WHEN action='block' THEN 1 ELSE 0 END) AS blocked_events,
		SUM(CASE WHEN action='allow' THEN 1 ELSE 0 END) AS allowed_events,
		SUM(CASE WHEN action NOT IN ('block','allow') THEN 1 ELSE 0 END) AS monitor_events,
		COUNT(DISTINCT CASE WHEN src_ip <> '' THEN src_ip END) AS unique_devices,
		COUNT(DISTINCT CASE WHEN host <> '' THEN host END) AS unique_sites
		FROM events WHERE {$where}";
	$stmt = @$db->prepare($sql);
	if (!$stmt) {
		return null;
	}
	foreach ($params as $k => $v) {
		$type = is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT;
		$stmt->bindValue($k, $v, $type);
	}
	$res = @$stmt->execute();
	$row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
	if (is_array($row)) {
		$row["unique_sites"] = 0;
		$sstmt = @$db->prepare("SELECT host, COUNT(*) AS c FROM events WHERE {$where} AND host <> '' GROUP BY host");
		if ($sstmt) {
			foreach ($params as $k => $v) {
				$sstmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
			}
			$sr = @$sstmt->execute();
			while ($sr && ($srow = $sr->fetchArray(SQLITE3_ASSOC))) {
				if (!layer7_reports_host_is_resolver($srow["host"] ?? "")) {
					$row["unique_sites"]++;
				}
			}
		}
	}
	return is_array($row) ? $row : null;
}

function layer7_reports_fetch_timeline($from_ts, $to_ts, $granularity, $filters = array())
{
	$db = layer7_reports_db_connect(true);
	if (!$db || !layer7_reports_init_schema($db)) {
		return array();
	}
	$params = array();
	$where = layer7_reports_filter_build($from_ts, $to_ts, $filters, $params);
	$bucket_expr = "strftime('%Y-%m-%d %H:00:00', ts, 'unixepoch')";
	if ((int)$granularity >= 86400) {
		$bucket_expr = "strftime('%Y-%m-%d 00:00:00', ts, 'unixepoch')";
	} elseif ((int)$granularity >= 21600) {
		$bucket_expr = "strftime('%Y-%m-%d %H:00:00', ts - (ts % 21600), 'unixepoch')";
	}
	$sql = "SELECT {$bucket_expr} AS bucket,
		COUNT(*) AS total_events,
		SUM(CASE WHEN action='block' THEN 1 ELSE 0 END) AS blocked_events,
		SUM(CASE WHEN action='allow' THEN 1 ELSE 0 END) AS allowed_events
		FROM events WHERE {$where}
		GROUP BY bucket ORDER BY bucket";
	$stmt = @$db->prepare($sql);
	if (!$stmt) {
		return array();
	}
	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
	}
	$r = @$stmt->execute();
	$out = array();
	while ($r && ($row = $r->fetchArray(SQLITE3_ASSOC))) {
		$ts = @strtotime($row["bucket"]);
		if ($ts === false) {
			continue;
		}
		$out[] = array(
			"ts" => (int)$ts,
			"total_events" => (int)$row["total_events"],
			"blocked_events" => (int)$row["blocked_events"],
			"allowed_events" => (int)$row["allowed_events"]
		);
	}
	return $out;
}

function layer7_reports_fetch_top_devices($from_ts, $to_ts, $filters = array(), $limit = 20)
{
	$db = layer7_reports_db_connect(true);
	if (!$db || !layer7_reports_init_schema($db)) {
		return array();
	}
	$params = array();
	$where = layer7_reports_filter_build($from_ts, $to_ts, $filters, $params);
	$sql = "SELECT src_ip,
		MAX(src_hostname) AS src_hostname,
		COUNT(*) AS total_events,
		SUM(CASE WHEN action='block' THEN 1 ELSE 0 END) AS blocked_events
		FROM events
		WHERE {$where} AND src_ip <> ''
		GROUP BY src_ip
		ORDER BY blocked_events DESC, total_events DESC
		LIMIT " . (int)$limit;
	$stmt = @$db->prepare($sql);
	if (!$stmt) {
		return array();
	}
	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
	}
	$r = @$stmt->execute();
	$out = array();
	while ($r && ($row = $r->fetchArray(SQLITE3_ASSOC))) {
		$out[] = $row;
	}
	return $out;
}

function layer7_reports_fetch_top_sites($from_ts, $to_ts, $filters = array(), $limit = 20)
{
	$db = layer7_reports_db_connect(true);
	if (!$db || !layer7_reports_init_schema($db)) {
		return array();
	}
	$params = array();
	$where = layer7_reports_filter_build($from_ts, $to_ts, $filters, $params);
	$sql = "SELECT host,
		COUNT(*) AS total_events,
		SUM(CASE WHEN action='block' THEN 1 ELSE 0 END) AS blocked_events
		FROM events
		WHERE {$where} AND host <> ''
		GROUP BY host
		ORDER BY total_events DESC
		LIMIT " . max(50, ((int)$limit * 8));
	$stmt = @$db->prepare($sql);
	if (!$stmt) {
		return array();
	}
	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
	}
	$r = @$stmt->execute();
	$out = array();
	while ($r && ($row = $r->fetchArray(SQLITE3_ASSOC))) {
		if (layer7_reports_host_is_resolver($row["host"] ?? "")) {
			continue;
		}
		$out[] = $row;
		if (count($out) >= (int)$limit) {
			break;
		}
	}
	return $out;
}

function layer7_reports_fetch_events($from_ts, $to_ts, $filters = array(), $page = 1, $page_size = 50)
{
	$db = layer7_reports_db_connect(true);
	if (!$db || !layer7_reports_init_schema($db)) {
		return array("rows" => array(), "total" => 0, "page" => 1, "page_size" => $page_size);
	}
	$page = max(1, (int)$page);
	$page_size = max(10, min(200, (int)$page_size));
	$offset = ($page - 1) * $page_size;
	$params = array();
	$where = layer7_reports_filter_build($from_ts, $to_ts, $filters, $params);

	$count_sql = "SELECT COUNT(*) AS c FROM events WHERE {$where}";
	$cstmt = @$db->prepare($count_sql);
	if ($cstmt) {
		foreach ($params as $k => $v) {
			$cstmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
		}
		$cr = @$cstmt->execute();
		$crow = $cr ? $cr->fetchArray(SQLITE3_ASSOC) : array("c" => 0);
		$total = (int)($crow["c"] ?? 0);
	} else {
		$total = 0;
	}

	$sql = "SELECT ts, ts_text, type, src_ip, src_hostname, dst_ip, host, app, category, action, policy, iface
		FROM events WHERE {$where}
		ORDER BY ts DESC
		LIMIT :limit OFFSET :offset";
	$stmt = @$db->prepare($sql);
	if (!$stmt) {
		return array("rows" => array(), "total" => $total, "page" => $page, "page_size" => $page_size);
	}
	foreach ($params as $k => $v) {
		$stmt->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
	}
	$stmt->bindValue(":limit", $page_size, SQLITE3_INTEGER);
	$stmt->bindValue(":offset", $offset, SQLITE3_INTEGER);
	$r = @$stmt->execute();
	$rows = array();
	while ($r && ($row = $r->fetchArray(SQLITE3_ASSOC))) {
		$host = trim((string)($row["host"] ?? ""));
		$action = (string)($row["action"] ?? "");
		$row["host_inferred"] = 0;
		if ($action === "block" && ($host === "" || layer7_reports_host_is_resolver($host))) {
			$guess = layer7_reports_guess_attempted_host($db, (string)($row["src_ip"] ?? ""), (int)($row["ts"] ?? 0), 120);
			if ($guess !== "") {
				$row["host"] = $guess;
				$row["host_inferred"] = 1;
			}
		}
		$rows[] = $row;
	}
	return array("rows" => $rows, "total" => $total, "page" => $page, "page_size" => $page_size);
}

function layer7_reports_identity_provider_ip_hostname($ip)
{
	$ip = trim((string)$ip);
	if ($ip === "" || !filter_var($ip, FILTER_VALIDATE_IP)) {
		return null;
	}
	$db = layer7_reports_db_connect(true);
	if ($db && layer7_reports_init_schema($db)) {
		$stmt = @$db->prepare("SELECT hostname FROM identity_map WHERE src_ip=:ip");
		if ($stmt) {
			$stmt->bindValue(":ip", $ip, SQLITE3_TEXT);
			$res = @$stmt->execute();
			$row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
			if (is_array($row) && trim((string)$row["hostname"]) !== "") {
				return array(
					"display_name" => $row["hostname"],
					"source" => "ip_hostname",
					"confidence" => 90
				);
			}
		}
	}
	$rdns = @gethostbyaddr($ip);
	if (is_string($rdns) && $rdns !== "" && $rdns !== $ip) {
		return array(
			"display_name" => $rdns,
			"source" => "ip_hostname",
			"confidence" => 60
		);
	}
	return array(
		"display_name" => $ip,
		"source" => "ip_hostname",
		"confidence" => 30
	);
}

function layer7_reports_identity_providers()
{
	return array(
		"ip_hostname" => "layer7_reports_identity_provider_ip_hostname",
		"captive_portal" => null,
		"ad_radius" => null
	);
}

function resolveIdentityByIp($ip)
{
	foreach (layer7_reports_identity_providers() as $provider => $resolver) {
		if (!is_string($resolver) || !function_exists($resolver)) {
			continue;
		}
		$identity = $resolver($ip);
		if (is_array($identity) && !empty($identity["display_name"])) {
			return $identity;
		}
	}
	return array(
		"display_name" => (string)$ip,
		"source" => "ip_hostname",
		"confidence" => 10
	);
}
