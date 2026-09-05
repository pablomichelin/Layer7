<?php
/**
 * Harness render — modos bulk/import/export V6b2b.
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/harness-exceptions-view/run-vip-bulk.php
 */
require_once __DIR__ . "/bootstrap.php";

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
function has($html, $needle)
{
	return strpos($html, $needle) !== false;
}

$vip_action = 'action="layer7_exceptions.php#l7-vip-list"';
$bridge = 'id="l7-vip-bookmark-bridge"';
$seed = l7he_vip_data(array(array("target" => "10.0.0.1", "description" => "A")));

echo "HARNESS RENDER — layer7_exceptions.php V6b2b bulk/import\n";

/* GET vip=1 consulta */
$html_list = l7he_render(array("get" => array("vip" => "1"), "data" => $seed));
check(has($html_list, 'vip_bulk=1#l7-vip-list'), "vip=1: link bulk");
check(has($html_list, 'vip_import=1#l7-vip-list'), "vip=1: link import");
check(has($html_list, 'name="export_vip_list"'), "vip=1: export POST");
check(!has($html_list, 'name="save_vip_bulk"'), "vip=1: sem lote embutido");
check(!has($html_list, 'name="import_vip_list"'), "vip=1: sem import embutido");
check(!has($html_list, "layer7-toolbar"), "vip=1: sem toolbar inline");
check(!has($html_list, "margin-top:16px"), "vip=1: sem margem inline export");

/* GET vip_bulk=1 */
$html_bulk = l7he_render(array("get" => array("vip_bulk" => "1"), "data" => $seed));
check(has($html_bulk, 'name="save_vip_bulk"'), "vip_bulk: formulario lote");
check(has($html_bulk, 'name="vip_bulk_text"'), "vip_bulk: textarea");
check(has($html_bulk, $vip_action), "vip_bulk: action literal");
check(has($html_bulk, 'href="layer7_exceptions.php?vip=1#l7-vip-list"'), "vip_bulk: voltar lista");
check(!has($html_bulk, 'name="export_vip_list"'), "vip_bulk: sem export");
check(preg_match('/onsubmit=.return confirm\(/', $html_bulk) === 1, "vip_bulk: confirm onsubmit");
check(strpos($html_bulk, "Guardar substitui todas as entradas directas") !== false, "vip_bulk: aviso estatico grupos");
check(strpos($html_bulk, "Deixar o campo vazio e guardar") !== false, "vip_bulk: aviso estatico lote vazio");
check(strpos($html_bulk, "font-family:Menlo") === false, "vip_bulk: textarea sem style inline");
check(!has($html_bulk, $bridge), "vip_bulk: sem ponte bookmark");

/* GET vip_import=1 */
$html_import = l7he_render(array("get" => array("vip_import" => "1"), "data" => $seed));
check(has($html_import, 'name="import_vip_list"'), "vip_import: formulario import");
check(has($html_import, 'name="vip_import_file"'), "vip_import: input file");
check(has($html_import, 'for="l7-vip-import-file"'), "vip_import: label acessivel");
check(has($html_import, 'enctype="multipart/form-data"'), "vip_import: multipart");
check(has($html_import, $vip_action), "vip_import: action literal");
check(preg_match('/onsubmit=.return confirm\(/', $html_import) === 1, "vip_import: confirm onsubmit");
check(!has($html_import, $bridge), "vip_import: sem ponte bookmark");

/* POST bulk erro abre modo bulk mesmo com vip=1 */
$html_bulk_err = l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "bad, x\n",
	),
	"data" => $seed,
));
check(has($html_bulk_err, 'name="save_vip_bulk"'), "POST bulk erro: modo bulk");
check(!has($html_bulk_err, 'name="export_vip_list"'), "POST bulk erro: nao consulta pura");

/* POST import erro */
$html_imp_err = l7he_render(array(
	"get" => array("vip" => "1"),
	"post" => array("import_vip_list" => "1"),
	"data" => $seed,
));
check(has($html_imp_err, 'name="import_vip_list"'), "POST import erro: modo import");
check(has($html_imp_err, "Seleccione novamente o ficheiro"), "POST import erro: pedir ficheiro");

/* POST bulk sucesso consulta */
$html_bulk_ok = l7he_render(array(
	"get" => array("vip_bulk" => "1"),
	"post" => array(
		"save_vip_bulk" => "1",
		"vip_bulk_text" => "10.1.0.1, OK\n",
	),
	"data" => $seed,
	"save_result" => true,
));
check(has($html_bulk_ok, 'name="export_vip_list"'), "POST bulk ok: consulta VIP");
check(!has($html_bulk_ok, 'name="save_vip_bulk"'), "POST bulk ok: sem editor lote");

/* vip_dhcp sem bulk/import */
$html_dhcp = l7he_render(array("get" => array("vip_dhcp" => "1"), "data" => $seed, "config" => l7he_vip_dhcp_config()));
check(!has($html_dhcp, 'name="save_vip_bulk"'), "vip_dhcp: sem lote");
check(!has($html_dhcp, 'name="import_vip_list"'), "vip_dhcp: sem import");

if ($fail) {
	fwrite(STDERR, "SOME VIP BULK HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL VIP BULK HARNESS TESTS PASSED\n";
exit(0);
