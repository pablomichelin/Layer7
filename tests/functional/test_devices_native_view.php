<?php
/**
 * BG-174 / GUI4 — gate estático da view de Devices (list / edit / batch).
 *
 * Lê layer7_devices.php como texto. Não carrega guiconfig nem layer7.inc.
 * Não executa daemon, PF nem handlers. Não prova visual no appliance.
 *
 *   php tests/functional/test_devices_native_view.php
 *   php tests/functional/test_devices_native_view.php /caminho/layer7_devices.php
 */
$root = dirname(__DIR__, 2);
$path = (isset($argv[1]) && is_string($argv[1]) && $argv[1] !== "")
    ? $argv[1]
    : $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_devices.php";

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
	'new Form_Input(' => 'Form_Input alias',
	'setAttribute("id", "l7-alias-edit")' => 'id l7-alias-edit',
	'new Form_Button("save_aliases"' => 'Form_Button save_aliases',
	'"fa fa-save"' => 'icone fa fa-save',
	'l7_t("Gravar aliases")' => 'legenda Gravar aliases',
	'id="l7-edit-alias"' => 'ancora l7-edit-alias',
	'id="l7-form-aliases"' => 'form aliases separado',
	'id="l7-form-assign"' => 'form assign separado',
	'Limpar filtros' => 'Limpar filtros',
	'for="l7-dev-chk-' => 'label checkbox por dispositivo',
	'layer7_render_tabs("devices")' => 'layer7_render_tabs("devices")',
	'$l7_device_mode = "list"' => 'modo list',
	'$l7_device_mode = "edit"' => 'modo edit',
	'$l7_device_mode = "batch"' => 'modo batch',
	'if ($l7_device_mode === "list")' => 'render list exclusivo',
	'if ($l7_device_mode === "edit")' => 'render edit exclusivo',
	'if ($l7_device_mode === "batch")' => 'render batch exclusivo',
	'$_POST["save_aliases"]' => 'handler save_aliases',
	'$_POST["assign_to_group"]' => 'handler assign_to_group',
	'$_POST["alias"]' => 'campo POST alias',
	'$_POST["assign_macs"]' => 'campo POST assign_macs',
	'$_POST["assign_group"]' => 'campo POST assign_group',
	'name="save_aliases"' => 'botao save_aliases lote',
	'name="assign_to_group"' => 'botao assign_to_group',
	'name="alias[' => 'campo alias[MAC]',
	'name="assign_macs[]"' => 'checkbox assign_macs[]',
	'name="assign_group"' => 'select assign_group',
	'layer7_device_alias_save' => 'layer7_device_alias_save',
	'layer7_pf_config_resync()' => 'layer7_pf_config_resync()',
	'$_GET["edit"]' => 'deep link GET edit',
	'$_GET["q"]' => 'filtro GET q',
	'$_GET["page"]' => 'paginacao GET page',
	'$_GET["online"]' => 'filtro GET online',
	'$_GET["mode"]' => 'GET mode',
	'name="q"' => 'campo filtro q',
	'name="online"' => 'campo filtro online',
	'name="mode"' => 'campo mode batch',
	'id="l7-devices"' => 'ancora lista',
	'id="l7-assign"' => 'ancora atribuicao',
	'for="l7-alias-' => 'label for alias lote',
	'for="l7-dev-online"' => 'label for online',
	'for="l7-assign-group"' => 'label for grupo',
	'foreach ($l7_dev_filtered as $d)' => 'lote sem corte de 50',
	'accoes independentes sobre esse conjunto' => 'copy lote sem detalhes de implementacao',
	'layer7_devices_view_href' => 'URLs de contexto',
	'htmlspecialchars($l7_dev_batch_action)' => 'POST batch com contexto',
	'setAction($l7_dev_edit_action)' => 'POST edit com contexto',
	'IDENT=page-services-layer7-devices' => 'privilege IDENT',
	'MATCH=layer7_devices.php*' => 'privilege MATCH',
	'layer7_device_inventory()' => 'inventario',
	'layer7_load_groups()' => 'grupos',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$idx_edit = strpos($src, 'if ($l7_device_mode === "edit")');
$idx_batch = strpos($src, 'if ($l7_device_mode === "batch")');
$idx_list = strpos($src, 'if ($l7_device_mode === "list")');
$idx_save_batch = strpos($src, 'name="save_aliases"');
$idx_assign = strpos($src, 'name="assign_to_group"');
$idx_alias_batch = strpos($src, 'name="alias[');
$idx_slice_list = strpos($src, 'foreach ($l7_dev_slice as $d)');

check($idx_edit !== false && $idx_batch !== false && $idx_edit < $idx_batch,
    "edit renderiza antes de batch no fonte");
check($idx_batch !== false && $idx_list !== false && $idx_batch < $idx_list,
    "batch renderiza antes de list no fonte");
check($idx_batch !== false && $idx_save_batch !== false && $idx_batch < $idx_save_batch &&
    ($idx_list === false || $idx_save_batch < $idx_list),
    "botao save_aliases de tabela esta no modo batch");
check($idx_batch !== false && $idx_assign !== false && $idx_batch < $idx_assign &&
    ($idx_list === false || $idx_assign < $idx_list),
    "botao assign_to_group esta no modo batch");
check($idx_batch !== false && $idx_alias_batch !== false && $idx_batch < $idx_alias_batch,
    "input alias[MAC] de lote esta no modo batch");
check($idx_list !== false && $idx_slice_list !== false && $idx_list < $idx_slice_list,
    "consulta paginada (slice) esta no modo list");
check(substr_count($src, 'name="assign_to_group"') === 1,
    "um unico botao assign_to_group");

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'<style' => '<style',
	'style=' => 'atributo style=',
	'l7-kpi' => 'l7-kpi',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7-page' => 'layer7-page',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'A atribuicao aplica-se aos dispositivos seleccionados nesta pagina.' => 'ajuda de assign so da pagina',
	'new Form();' => 'Form() com Save padrao',
	'max_input_vars' => 'max_input_vars no produto',
	'nao somar alias[MAC]' => 'copy alias[MAC] no alerta',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

$fb = strpos($src, 'new Form_Button("save_aliases"');
$fb_end = strpos($src, 'addGlobal($l7_edit_save)', (int)$fb);
$fb_chunk = ($fb !== false && $fb_end !== false) ? substr($src, $fb, $fb_end - $fb) : "";
check($fb_chunk !== "" && strpos($fb_chunk, 'setAttribute("value", "1")') === false, "Form_Button edit sem value=1");

if ($fail) {
	fwrite(STDERR, "SOME DEVICES NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL DEVICES NATIVE VIEW TESTS PASSED\n";
exit(0);
