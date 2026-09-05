<?php
/**
 * BG-174 / GUI2a — gate funcional isolado de layer7_render_policies_subnav().
 *
 * Extrai a função real de layer7.inc do checkout (sem carregar o ficheiro inteiro).
 * Não executa daemon, PF, guiconfig nem appliance.
 *
 *   php tests/functional/test_policies_subnav_native.php
 *
 * Auditoria opcional (resto de layer7.inc byte-idéntico): defina
 * LAYER7_GUI2A_BASELINE com caminho para layer7.inc pinado (SHA256 abaixo).
 * Sem env imprime SKIP explícito; caminho informado inexistente/divergente = FAIL.
 */
$root = dirname(__DIR__, 2);
$inc = $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";
const L7GUI2A_BASELINE_SHA256 = "3ed7f3e2d65e5d5ce410b7517011a356fd322c0feb695878acccab5fbdee3fb4";

if (!is_file($inc)) {
	fwrite(STDERR, "FAIL ficheiro em falta: {$inc}\n");
	exit(1);
}

$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: {$name}\n";
	} else {
		echo "FAIL: {$name}\n";
		$fail = 1;
	}
}

function l7gui2a_extract_subnav_fn($src)
{
	$start = strpos($src, "function layer7_render_policies_subnav(\$active)");
	if ($start === false) {
		return null;
	}
	$end = strpos($src, "\nfunction layer7_render_messages()", $start);
	if ($end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
}

function l7gui2a_render_fn_source($fn_src, $active, $fn_name, $translator = null)
{
	if (!function_exists("l7_t")) {
		function l7_t($key)
		{
			if (isset($GLOBALS["l7gui2a_l7_t"]) && is_callable($GLOBALS["l7gui2a_l7_t"])) {
				return call_user_func($GLOBALS["l7gui2a_l7_t"], $key);
			}
			return $key;
		}
	}
	$GLOBALS["l7gui2a_l7_t"] = $translator;
	if (!isset($GLOBALS["l7gui2a_loaded"][$fn_name])) {
		$code = preg_replace(
			'/function layer7_render_policies_subnav/',
			"function {$fn_name}",
			$fn_src,
			1
		);
		eval($code);
		$GLOBALS["l7gui2a_loaded"][$fn_name] = true;
	}
	ob_start();
	call_user_func($fn_name, $active);
	return ob_get_clean();
}

function l7gui2a_parse_subnav_dom($html)
{
	$dom = new DOMDocument();
	if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html) === false) {
		return null;
	}
	$xpath = new DOMXPath($dom);
	$nav = $xpath->query("//nav")->item(0);
	if (!$nav instanceof DOMElement) {
		return null;
	}
	$lis = $xpath->query(".//ul[contains(@class,'nav-tabs')]/li", $nav);
	$anchors = $xpath->query(".//a", $nav);
	$scripts = $xpath->query("//script");
	return array(
		"dom" => $dom,
		"xpath" => $xpath,
		"nav" => $nav,
		"lis" => $lis,
		"anchors" => $anchors,
		"scripts" => $scripts,
	);
}

function l7gui2a_active_index_matches($parsed, $expected_idx)
{
	if ($parsed === null) {
		return false;
	}
	$lis = $parsed["lis"];
	if ($lis->length !== 5) {
		return false;
	}
	for ($i = 0; $i < $lis->length; $i++) {
		$li = $lis->item($i);
		$a = $parsed["xpath"]->query(".//a", $li)->item(0);
		if (!$a instanceof DOMElement) {
			return false;
		}
		$li_active = (strpos($li->getAttribute("class"), "active") !== false);
		$aria_current = ($a->getAttribute("aria-current") === "page");
		$should_active = ($expected_idx >= 0 && $i === $expected_idx);
		if ($li_active !== $should_active || $aria_current !== $should_active) {
			return false;
		}
	}
	return true;
}

$src = (string)file_get_contents($inc);
$fn_src = l7gui2a_extract_subnav_fn($src);
check($fn_src !== null, "funcao subnav extraida do produto");

$expected = array(
	array("href" => "layer7_policies.php", "label" => "Politicas"),
	array("href" => "layer7_groups.php", "label" => "Grupos"),
	array("href" => "layer7_exceptions.php", "label" => "Excecoes"),
	array("href" => "layer7_categories.php", "label" => "Categorias nDPI"),
	array("href" => "layer7_test.php", "label" => "Simular teste"),
);

$active_cases = array(
	"policies" => 0,
	"groups" => 1,
	"exceptions" => 2,
	"categories" => 3,
	"test" => 4,
	"" => -1,
	"unknown" => -1,
);

foreach ($active_cases as $active => $idx) {
	$html = l7gui2a_render_fn_source($fn_src, $active, "layer7_render_policies_subnav_prod");
	check(strpos($html, "l7-policies-subnav") === false, "active={$active}: sem classe CSS propria");
	check(strpos($html, 'role="tab"') === false && strpos($html, 'role="tablist"') === false,
		"active={$active}: sem role tab/tablist");
	check(strpos($html, '<ul class="nav nav-tabs">') !== false, "active={$active}: ul.nav.nav-tabs");

	$parsed = l7gui2a_parse_subnav_dom($html);
	check($parsed !== null, "active={$active}: HTML parseavel");
	if ($parsed === null) {
		continue;
	}
	check($parsed["scripts"]->length === 0, "active={$active}: DOM sem script");
	check($parsed["anchors"]->length === 5, "active={$active}: cinco anchors no nav");
	check(l7gui2a_active_index_matches($parsed, $idx),
		"active={$active}: indice activo exacto" . ($idx >= 0 ? " li[{$idx}]" : " (nenhum)"));

	$lis = $parsed["lis"];
	for ($i = 0; $i < $lis->length; $i++) {
		$a = $parsed["xpath"]->query(".//a", $lis->item($i))->item(0);
		check($a instanceof DOMElement, "active={$active}: li {$i} tem link");
		check($a->getAttribute("href") === $expected[$i]["href"],
			"active={$active}: href[{$i}]={$expected[$i]['href']}");
		check(trim($a->textContent) === $expected[$i]["label"],
			"active={$active}: label[{$i}]={$expected[$i]['label']}");
	}
}

$mutated_src = str_replace(
	'$is_active = ($key === $active);',
	'$is_active = ($key === "policies");',
	$fn_src
);
$html_mut = l7gui2a_render_fn_source($mutated_src, "groups", "layer7_render_policies_subnav_mut");
check(!l7gui2a_active_index_matches(l7gui2a_parse_subnav_dom($html_mut), 1),
	"mutacao always-policies: rejeitada quando active=groups (indice 1)");
check(l7gui2a_active_index_matches(l7gui2a_parse_subnav_dom($html_mut), 0),
	"mutacao always-policies: activa indice 0 como esperado pelo bug injectado");

$evil_label = '"><script>alert(1)</script><span aria-label="';
$evil = l7gui2a_render_fn_source($fn_src, "policies", "layer7_render_policies_subnav_evil", function ($key) use ($evil_label) {
	if ($key === "Politicas") {
		return $evil_label;
	}
	if ($key === "Grupos") {
		return "Grupos & Co";
	}
	return $key;
});
check(strpos($evil, "<script>") === false, "traducao adversarial: HTML sem tag script literal");
$evil_parsed = l7gui2a_parse_subnav_dom($evil);
check($evil_parsed !== null, "traducao adversarial: DOM parseavel");
if ($evil_parsed !== null) {
	check($evil_parsed["scripts"]->length === 0, "traducao adversarial: DOM sem script");
	check($evil_parsed["nav"]->getAttribute("aria-label") === $evil_label,
		"traducao adversarial: aria-label decodificado igual traducao inteira");
	check($evil_parsed["anchors"]->length === 5, "traducao adversarial: exactamente cinco anchors");
	check(trim($evil_parsed["xpath"]->query(".//a", $evil_parsed["lis"]->item(1))->item(0)->textContent) === "Grupos & Co",
		"traducao adversarial: label ampersand escapado no DOM");
}

$call_sites = array(
	"layer7_policies.php" => 'layer7_render_policies_subnav("policies")',
	"layer7_groups.php" => 'layer7_render_policies_subnav("groups")',
	"layer7_exceptions.php" => 'layer7_render_policies_subnav("exceptions")',
	"layer7_categories.php" => 'layer7_render_policies_subnav("categories")',
	"layer7_test.php" => 'layer7_render_policies_subnav("test")',
);
foreach ($call_sites as $page => $needle) {
	$page_src = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/" . $page;
	check(is_file($page_src), "pagina {$page} existe");
	check(strpos((string)file_get_contents($page_src), $needle) !== false,
		"chamada subnav preservada em {$page}");
}

check(strpos($src, "function layer7_nav_items()") !== false, "layer7_nav_items presente");
check(strpos($src, "function layer7_nav_secondary_items()") !== false, "layer7_nav_secondary_items presente");
check(strpos($src, "function layer7_render_tabs(") !== false, "layer7_render_tabs presente");

$ra_path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_remote_access.php";
check(is_file($ra_path), "layer7_remote_access.php existe");
if (is_file($ra_path)) {
	$ra_src = (string)file_get_contents($ra_path);
	check(strpos($ra_src, 'header("Location: /packages/layer7/layer7_policies.php#l7-ra");') !== false,
		"Remote Access redirect Location #l7-ra preservado");
	check(strpos($ra_src, "##|*IDENT=page-services-layer7-remote-access") !== false,
		"Remote Access privilege IDENT preservado");
	check(strpos($ra_src, "##|*MATCH=layer7_remote_access.php*") !== false,
		"Remote Access privilege MATCH preservado");
}

$audit_path = getenv("LAYER7_GUI2A_BASELINE");
if (!is_string($audit_path) || $audit_path === "") {
	echo "SKIP: auditoria byte-identica layer7.inc (defina LAYER7_GUI2A_BASELINE)\n";
} elseif (!is_file($audit_path)) {
	check(false, "LAYER7_GUI2A_BASELINE em falta: {$audit_path}");
} else {
	$base_src = (string)file_get_contents($audit_path);
	$base_sha = hash("sha256", $base_src);
	if (!hash_equals(L7GUI2A_BASELINE_SHA256, $base_sha)) {
		check(false, "LAYER7_GUI2A_BASELINE SHA256 diverge (esperado "
			. L7GUI2A_BASELINE_SHA256 . ", got {$base_sha})");
	} else {
		echo "PASS: auditoria baseline SHA256 pinado\n";
		$fn_start = strpos($src, "function layer7_render_policies_subnav(\$active)");
		$fn_end = strpos($src, "\nfunction layer7_render_messages()", $fn_start);
		$base_fn_start = strpos($base_src, "function layer7_render_policies_subnav(\$active)");
		$base_fn_end = strpos($base_src, "\nfunction layer7_render_messages()", $base_fn_start);
		check(
			hash("sha256", substr($src, 0, $fn_start)) === hash("sha256", substr($base_src, 0, $base_fn_start)),
			"auditoria: layer7.inc byte-identico antes da funcao subnav"
		);
		check(
			hash("sha256", substr($src, $fn_end)) === hash("sha256", substr($base_src, $base_fn_end)),
			"auditoria: layer7.inc byte-identico apos a funcao subnav"
		);
		$base_fn = l7gui2a_extract_subnav_fn($base_src);
		check($base_fn !== null && $fn_src !== $base_fn,
			"auditoria: funcao subnav candidato difere do baseline GUI2a");
	}
}

if ($fail) {
	fwrite(STDERR, "SOME POLICIES SUBNAV NATIVE TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES SUBNAV NATIVE TESTS PASSED\n";
exit(0);
