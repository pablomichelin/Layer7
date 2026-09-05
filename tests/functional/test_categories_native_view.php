<?php
/**
 * BG-174 / V3 — gate estático da view de Categorias nDPI.
 * Lê layer7_categories.php como texto. Não carrega guiconfig nem layer7.inc.
 *
 *   php tests/functional/test_categories_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_categories.php";
if (!is_file($path)) {
	fwrite(STDERR, "FAIL ficheiro em falta: {$path}\n");
	exit(1);
}
$src = file_get_contents($path);
$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$fail = 1;
	}
}
function has_str($src, $needle)
{
	return strpos($src, $needle) !== false;
}

$required = array(
	'IDENT=page-services-layer7-categories' => 'privilege IDENT',
	'MATCH=layer7_categories.php*' => 'privilege MATCH',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_ndpi_list()' => 'leitura layer7_ndpi_list',
	'$ndpi_list["protocols_by_category"]' => 'protocols_by_category',
	'$ndpi_list["protocols"]' => 'protocols',
	'ksort($by_cat)' => 'ksort categorias',
	'sort($protos)' => 'sort protocolos',
	'layer7_render_tabs("policies")' => 'tabs',
	'layer7_render_policies_subnav("categories")' => 'subnav',
	'id="l7-categories"' => 'ancora catalogo',
	'for="l7-cat-search"' => 'label for pesquisa',
	'id="l7-cat-search"' => 'id pesquisa',
	'id="l7-cat-clear"' => 'limpar pesquisa',
	'id="l7-cat-empty"' => 'sem resultados',
	'<details' => 'details nativo',
	'<summary' => 'summary nativo',
	'data-category=' => 'data-category',
	'data-proto=' => 'data-proto',
	'table table-striped' => 'tabela nativa',
	'events.push(l7CatInit)' => 'events[] do host',
	'DOMContentLoaded' => 'fallback DOMContentLoaded',
	'htmlspecialchars(strtolower((string)$cat_name), ENT_QUOTES, "UTF-8")' => 'escape categoria',
	'htmlspecialchars(strtolower((string)$proto), ENT_QUOTES, "UTF-8")' => 'escape protocolo attr',
	'htmlspecialchars((string)$proto)' => 'escape protocolo texto',
	'Consulta de referencia' => 'aviso consulta',
	'A pesquisa so filtra esta consulta' => 'aviso pesquisa sem efeito',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'<style' => '<style',
	'style=' => 'atributo style=',
	'layer7-page' => 'layer7-page',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'l7-proto-tag' => 'chips proto',
	'label-default' => 'chips label',
	'$(' => 'jQuery $',
	'jQuery' => 'jQuery',
	'.collapse(' => 'Bootstrap collapse JS',
	'data-toggle="collapse"' => 'data-toggle collapse',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

$script_pos = strpos($src, "<script>");
$foot_pos = strpos($src, 'include("foot.inc")');
check($script_pos !== false && $foot_pos !== false && $script_pos < $foot_pos, "script antes de foot.inc");
check(substr_count($src, "<script>") === 1, "um unico script");
check(has_str($src, 'for="l7-cat-search"') && has_str($src, 'id="l7-cat-search"'), "for+id pesquisa emparelhados");

if ($fail) {
	fwrite(STDERR, "SOME CATEGORIES NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL CATEGORIES NATIVE VIEW TESTS PASSED\n";
exit(0);
