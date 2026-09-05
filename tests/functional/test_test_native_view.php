<?php
/**
 * V9 Teste de políticas — gate estático view nativa + DOM labels.
 *
 *   php tests/functional/test_test_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_test.php";
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
	'IDENT=page-services-layer7-test' => 'privilege IDENT',
	'MATCH=layer7_test.php*' => 'privilege MATCH',
	'function l7_run_policy_test' => 'motor simulacao',
	'$_POST["run_test"]' => 'handler run_test',
	'gethostbynamel($domain)' => 'DNS simulacao (prefixo)',
	'layer7_ndpi_list()' => 'catalogo ndpi (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("policies")' => 'tabs policies',
	'layer7_render_policies_subnav("test")' => 'subnav test',
	'Simule o que aconteceria' => 'explicacao simulacao',
	'require_once("classes/Form.class.php")' => 'Form.class.php na view',
	'new Form(false)' => 'Form(false)',
	'setAction("layer7_test.php#l7-test")' => 'action form',
	'Form_Input("test_domain"' => 'campo test_domain',
	'maxlength", "255"' => 'maxlength dominio',
	'Form_Input("test_src_ip"' => 'campo test_src_ip',
	'maxlength", "48"' => 'maxlength origem',
	'Form_Select("test_ndpi_app"' => 'select app',
	'Form_Select("test_ndpi_cat"' => 'select categoria',
	'name="run_test" value="1"' => 'submitter run_test',
	'id="l7-test"' => 'ancora formulario',
	'id="l7-test-results"' => 'painel resultados',
	'id="l7-test-verdict"' => 'veredicto',
	'alert-danger' => 'classe block',
	'alert-success' => 'classe allow',
	'alert-info' => 'classe monitor/info',
	'table table-striped' => 'tabela nativa',
	'Esta simulacao usa as politicas' => 'nota simulacao',
	'Modo enforce activo' => 'nota enforce',
	'Modo monitor: nenhuma accao' => 'nota monitor',
	'text-center text-muted' => 'credito nativo',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'layer7-page' => 'layer7-page',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7-muted-note' => 'layer7-muted-note',
	'style=' => 'style inline',
	'Form_Button("run_test"' => 'Form_Button run_test',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

require_once __DIR__ . "/harness-test-view/bootstrap.php";
$html = l7ht_render(array());
$dom = new DOMDocument();
if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html) === false) {
	fwrite(STDERR, "FAIL DOM render\n");
	exit(1);
}
$xpath = new DOMXPath($dom);
foreach (array("test_domain", "test_src_ip", "test_ndpi_app", "test_ndpi_cat") as $fname) {
	$el = $xpath->query('//*[@name="' . $fname . '"]')->item(0);
	check($el instanceof DOMElement, "DOM: campo name={$fname}");
	if ($el instanceof DOMElement) {
		$fid = $el->getAttribute("id");
		if ($fid !== "") {
			$label = $xpath->query('//label[@for="' . $fid . '"]')->item(0);
			check($label instanceof DOMElement, "DOM: label for={$fid}");
		}
	}
}
$form = $xpath->query('//form[contains(@action,"layer7_test.php#l7-test")]')->item(0);
check($form instanceof DOMElement, "DOM: form action layer7_test.php#l7-test");
$run = $xpath->query('.//button[@name="run_test"]', $form)->item(0);
check($run instanceof DOMElement && $run->getAttribute("value") === "1", "DOM: run_test value=1");

if ($fail) {
	fwrite(STDERR, "SOME TEST NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL TEST NATIVE VIEW TESTS PASSED\n";
exit(0);
