<?php
/**
 * BG-174 / GUI1 — gate estático da view nativa de Status.
 *
 * Lê layer7_status.php como texto. Não carrega guiconfig nem layer7.inc.
 * Não executa daemon, PF nem handlers.
 *
 *   php tests/functional/test_status_native_view.php
 *   php tests/functional/test_status_native_view.php /caminho/layer7_status.php
 */
$root = dirname(__DIR__, 2);
$path = (isset($argv[1]) && is_string($argv[1]) && $argv[1] !== "")
    ? $argv[1]
    : $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_status.php";

if (!is_file($path)) {
	fwrite(STDERR, "FAIL ficheiro em falta: {$path}\n");
	exit(1);
}

$src = file_get_contents($path);
if (!is_string($src) || $src === "") {
	fwrite(STDERR, "FAIL nao foi possivel ler: {$path}\n");
	exit(1);
}

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
	'require_once("classes/Form.class.php")' => 'Form.class.php',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'new Form(false)' => 'Form(false)',
	'Form_Section' => 'Form_Section',
	'Form_StaticText' => 'Form_StaticText',
	'layer7_render_tabs("status")' => 'layer7_render_tabs("status")',
	'print_info_box' => 'print_info_box',
	'$_POST["restart_service"]' => 'handler POST restart_service',
	'name="restart_service"' => 'botao POST restart_service',
	'layer7_restart_service()' => 'layer7_restart_service()',
	'layer7_settings.php' => 'link settings',
	'layer7_diagnostics.php' => 'link diagnostics',
	'layer7_policies.php' => 'link policies',
	'layer7_events.php' => 'link events',
	'layer7_reports.php' => 'link reports',
	'Estado do daemon' => 'secao Estado do daemon',
	'Resumo' => 'secao Resumo',
	'Top 10 apps bloqueadas' => 'secao Top 10 apps bloqueadas',
	'Top 10 clientes bloqueados' => 'secao Top 10 clientes bloqueados',
	'Acoes' => 'secao Acoes',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'<style' => '<style',
	'style=' => 'atributo style=',
	'l7-kpi' => 'l7-kpi',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7-page' => 'layer7-page',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

if ($fail) {
	fwrite(STDERR, "SOME STATUS NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL STATUS NATIVE VIEW TESTS PASSED\n";
exit(0);
