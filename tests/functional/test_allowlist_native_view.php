<?php
/**
 * BG-174 / V5 Allowlist — gate estático + prova DOM name/id (render isolado).
 *
 *   php tests/functional/test_allowlist_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_allowlist.php";
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
	'IDENT=page-services-layer7-allowlist' => 'privilege IDENT',
	'MATCH=layer7_allowlist.php*' => 'privilege MATCH',
	'$seed_entries = layer7_dst_allowlist_seed_entries();' => 'carga seed',
	'require_once("classes/Form.class.php")' => 'Form.class.php apos carga',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'new Form(false)' => 'Form(false)',
	'Form_Section' => 'Form_Section',
	'Form_Textarea' => 'Form_Textarea',
	'Form_Input("save_allowlist"' => 'hidden save_allowlist',
	'setAction("layer7_allowlist.php")' => 'action layer7_allowlist.php',
	'setAttribute("id", "l7-allow-entries")' => 'id l7-allow-entries',
	'id="l7-allow-submit"' => 'botao submit sem name',
	'type="submit"' => 'type submit',
	'layer7_render_tabs("allowlist")' => 'tabs allowlist',
	'$_POST["save_allowlist"]' => 'handler save_allowlist',
	'function l7_allow_normalize_input' => 'normalizador',
	'function l7_allow_classify' => 'classificador',
	'$l7_allow_entries_display' => 'retry POST view',
	'empty($savemsg)' => 'retry POST quando sem sucesso',
	'alert alert-info' => 'intro alert-info',
	'Politicas de bloqueio explicitas' => 'intro revisada',
	'Apenas leitura. Esta lista e fornecida pelo pacote' => 'ajuda seed revisada',
	'id="l7-allow-seed"' => 'painel seed',
	'<details>' => 'details nativo',
	'<summary>' => 'summary nativo',
	'pre-scrollable' => 'pre-scrollable',
	'text-center text-muted' => 'credito nativo',
	'https://www.systemup.inf.br' => 'URL credito Systemup',
	'layer7_dst_allowlist_apply_to_pf()' => 'handler apply_to_pf',
	'layer7_signal_reload()' => 'handler signal_reload',
	'layer7_filter_configure_safe()' => 'handler filter_configure',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

check(preg_match('/new\s+Form_Textarea\s*\(\s*"entries"/', $src) === 1,
	"preserva campo entries (Form_Textarea name=entries)");

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'<style' => '<style',
	'style=' => 'atributo style=',
	'layer7-page' => 'layer7-page',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'NUNCA devem ser bloqueados' => 'promessa NUNCA antiga',
	'blacklists (cobrem a allowlist)' => 'receita blacklist incorrecta',
	'Form_Button("save_allowlist"' => 'Form_Button save_allowlist',
	'name="save"' => 'botao save padrao',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

$seed_pos = strpos($src, '$seed_entries = layer7_dst_allowlist_seed_entries();');
$form_req = strpos($src, 'require_once("classes/Form.class.php")');
$print_form = strpos($src, 'print($form)');
$seed_panel = strpos($src, 'id="l7-allow-seed"');
check($seed_pos !== false && $form_req !== false && $form_req > $seed_pos,
	"Form.class.php apos carga seed_entries");
check($print_form !== false && $seed_panel !== false && $print_form < $seed_panel,
	"seed fora do formulario mutavel");

require_once __DIR__ . "/harness-allowlist-view/bootstrap.php";
$html_dom = l7ha_render(array(
	"data" => l7ha_data(array("bb.com.br", "8.8.8.8")),
	"seed" => array("seed.example.com"),
));
$dom = new DOMDocument();
if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html_dom) === false) {
	fwrite(STDERR, "FAIL DOM render\n");
	exit(1);
}
$xpath = new DOMXPath($dom);
$ta = $xpath->query('//textarea[@id="l7-allow-entries"]')->item(0);
check($ta instanceof DOMElement, "DOM: textarea id l7-allow-entries");
if ($ta instanceof DOMElement) {
	check($ta->getAttribute("name") === "entries", "DOM: textarea name=entries");
	check($ta->getAttribute("id") === "l7-allow-entries", "DOM: textarea id emparelhado");
}
$label = $xpath->query('//label[@for="l7-allow-entries"]')->item(0);
check($label instanceof DOMElement, "DOM: label for=l7-allow-entries");

if ($fail) {
	fwrite(STDERR, "SOME ALLOWLIST NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL ALLOWLIST NATIVE VIEW TESTS PASSED\n";
exit(0);
