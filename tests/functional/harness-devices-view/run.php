<?php
/**
 * Harness de render — executa a view layer7_devices.php com stubs.
 * HTML gerado vai para /tmp (não é produto). Julgar por PASS/FAIL e
 * pelo marcador final, não só pelo exit do wrapper.
 *
 *   php tests/functional/harness-devices-view/run.php
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

$out_dir = __DIR__ . "/generated";
if (!is_dir($out_dir)) {
	@mkdir($out_dir, 0755, true);
}

echo "HARNESS RENDER — view efetiva layer7_devices.php\n";
echo "Form_* pinados: tests/functional/harness-devices-view/vendor/pfsense-form/\n";
echo "CITATION: vendor/pfsense-form/CITATION.txt (pfsense 9363ac5b)\n";
echo "Nao e pfSense, nao e appliance, csrf-magic ausente (conta +1 so no payload)\n";

$inv60 = l7h_inventory(60);
$inv0 = array();
$mac1 = l7h_mac(1);
$mac2 = l7h_mac(2);
$mac51 = l7h_mac(51);
$mac60 = l7h_mac(60);

$nomac = array(
	"ip" => "192.0.2.250",
	"mac" => "",
	"hostname" => "sem-mac",
	"descr" => "",
	"vendor" => "",
	"iface" => "lan",
	"online" => "online",
	"source" => "arp",
	"alias" => "",
);

/* ---- empty ---- */
$html_empty = l7h_render(array(
	"get" => array(),
	"inventory" => $inv0,
));
if ($out_dir && is_dir($out_dir)) {
	file_put_contents($out_dir . "/empty.html", $html_empty);
}
check(has($html_empty, "Nenhum dispositivo observado"), "empty: mensagem vazio");
check(!has($html_empty, 'name="save"'), "empty: sem botao Save padrao");
check(!has($html_empty, 'name="save_aliases"'), "empty list: sem save_aliases");
check(!has($html_empty, 'id="l7-form-aliases"'), "empty list: sem form batch");
check(has($html_empty, "<!-- L7HARNESS_HEAD -->"), "empty: head stub");
check(has($html_empty, "<!-- L7HARNESS_FOOT -->"), "empty: foot stub");

/* ---- one / list ---- */
$html_one = l7h_render(array(
	"get" => array(),
	"inventory" => l7h_inventory(1),
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/one.html", $html_one);
}
check(has($html_one, $mac1), "one: MAC sintetico");
check(has($html_one, "192.0.2.1"), "one: IP RFC 5737");
check(has($html_one, "Editar alias"), "one: accao edit");
check(!has($html_one, 'name="alias['), "one list: sem input alias");
check(!has($html_one, 'name="assign_macs[]"'), "one list: sem checkbox assign");

/* ---- list page2 >50 ---- */
$html_p2 = l7h_render(array(
	"get" => array("page" => "2"),
	"inventory" => $inv60,
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/list-page2.html", $html_p2);
}
check(has($html_p2, "Pagina 2 de 2"), "list page2: titulo pagina");
check(has($html_p2, $mac51), "list page2: contem dispositivo 51");
check(has($html_p2, $mac60), "list page2: contem dispositivo 60");
check(!has($html_p2, $mac1), "list page2: sem dispositivo 1 (corte 50)");
check(has($html_p2, "Anterior"), "list page2: Anterior");
check(!has($html_p2, 'name="save"'), "list page2: sem Save padrao");
check(!has($html_p2, 'name="save_aliases"'), "list page2: sem save_aliases");
check(has($html_p2, "Editar e seleccionar em lote"), "list page2: accao batch");
check(has($html_p2, "layer7_devices.php?edit="), "list page2: deep link edit");

/* ---- list page1 slice ---- */
$html_p1 = l7h_render(array(
	"get" => array(),
	"inventory" => $inv60,
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/list-page1.html", $html_p1);
}
check(has($html_p1, $mac1), "list page1: dispositivo 1");
check(!has($html_p1, $mac51), "list page1: sem dispositivo 51");
check(has($html_p1, "Pagina 1 de 2"), "list page1: paginacao");
check(has($html_p1, "Seguinte"), "list page1: Seguinte");

/* ---- batch 60: todos ---- */
$html_batch = l7h_render(array(
	"get" => array("mode" => "batch"),
	"inventory" => $inv60,
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/batch-60.html", $html_batch);
}
$alias_form = l7h_form_inner($html_batch, "l7-form-aliases");
$assign_form = l7h_form_inner($html_batch, "l7-form-assign");
$alias_names = l7h_named_attrs($alias_form);
$assign_names = l7h_named_attrs($assign_form);
check($alias_form !== "" && $assign_form !== "", "batch: dois forms");
check(l7h_count_names($alias_names, "alias[") === 60, "batch: 60 inputs alias[MAC]");
check(l7h_count_names($alias_names, "save_aliases") === 1, "batch: um save_aliases");
check(l7h_count_names($alias_names, "assign_macs[]") === 0, "batch aliases: sem assign_macs[]");
check(l7h_count_names($alias_names, "assign_to_group") === 0, "batch aliases: sem assign_to_group");
check(l7h_count_names($assign_names, "assign_macs[]") === 60, "batch: 60 assign_macs[]");
check(l7h_count_names($assign_names, "assign_group") === 1, "batch: um assign_group");
check(l7h_count_names($assign_names, "assign_to_group") === 1, "batch: um assign_to_group");
check(l7h_count_names($assign_names, "alias[") === 0, "batch assign: sem alias[MAC]");
check(l7h_count_names($assign_names, "save_aliases") === 0, "batch assign: sem save_aliases");
check(!has($html_batch, 'name="save"'), "batch: sem Save padrao do Form");
check(has($html_batch, $mac1) && has($html_batch, $mac60), "batch: conjunto completo 1 e 60");
check(has($html_batch, 'for="l7-alias-' . preg_replace('/[^0-9a-f]/', '', $mac1) . '"'), "batch: label alias");
check(has($html_batch, 'id="l7-alias-' . preg_replace('/[^0-9a-f]/', '', $mac1) . '"'), "batch: id alias");
check(has($html_batch, 'for="l7-dev-chk-' . preg_replace('/[^0-9a-f]/', '', $mac1) . '"'), "batch: label selecao");
check(has($html_batch, 'id="l7-dev-chk-' . preg_replace('/[^0-9a-f]/', '', $mac1) . '"'), "batch: id selecao");
check(has($html_batch, "accoes independentes"), "batch: copy sem detalhes de implementacao");
check(!has($html_batch, "max_input_vars"), "batch: copy sem max_input_vars");
check(!has($html_batch, "alias[MAC]"), "batch: copy sem alias[MAC]");
check(has($html_batch, 'action="layer7_devices.php?mode=batch'), "batch: POST action preserva mode");

/* ---- edit from list page2 ---- */
$html_edit_list = l7h_render(array(
	"get" => array("edit" => $mac51, "page" => "2"),
	"inventory" => $inv60,
	"aliases" => array($mac51 => "pagina-dois"),
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/edit-from-list-page2.html", $html_edit_list);
}
check(has($html_edit_list, 'id="l7-edit-alias"'), "edit list p2: ancora");
check(has($html_edit_list, 'id="l7-alias-edit"'), "edit list p2: id alias");
check(has($html_edit_list, 'for="l7-alias-edit"'), "edit list p2: label for alias");
check(has($html_edit_list, 'name="alias[' . $mac51 . ']"'), "edit list p2: name alias[MAC]");
check(substr_count($html_edit_list, 'name="save_aliases"') === 1, "edit list p2: um save_aliases");
check(preg_match('/name="save_aliases"[^>]*value="Gravar aliases"/', $html_edit_list)
	|| preg_match('/value="Gravar aliases"[^>]*name="save_aliases"/', $html_edit_list), "edit list p2: save_aliases legenda");
check(!preg_match('/name="save_aliases"[^>]*value="1"/', $html_edit_list)
	&& !preg_match('/value="1"[^>]*name="save_aliases"/', $html_edit_list), "edit list p2: save_aliases nao e 1");
check(has($html_edit_list, 'class="fa fa-save icon-embed-btn"')
	|| has($html_edit_list, "fa fa-save"), "edit list p2: icone fa fa-save");
check(!has($html_edit_list, 'name="save"'), "edit list p2: sem Save padrao");
check(!has($html_edit_list, 'id="l7-form-aliases"'), "edit list p2: sem tabela batch");
check(has($html_edit_list, 'action="layer7_devices.php?edit='), "edit list p2: POST action com edit");
check(has($html_edit_list, "page=2") || has($html_edit_list, "page=2&") || has($html_edit_list, "&amp;page=2"), "edit list p2: page=2 no HTML");
check(preg_match('/<form[^>]+action="[^"]*page=2[^"]*"/', $html_edit_list) === 1
	|| preg_match('/<form[^>]+action="[^"]*page%3D2[^"]*"/', $html_edit_list) === 1
	|| preg_match('/action="layer7_devices\.php\?[^"]*page=2/', $html_edit_list) === 1, "edit list p2: form action tem page");
check(has($html_edit_list, "Voltar a lista"), "edit list p2: Voltar");
$voltar_ok = preg_match('/href="layer7_devices\.php\?page=2"/', $html_edit_list)
	|| preg_match('/href="layer7_devices\.php\?[^"]*page=2[^"]*"/', $html_edit_list);
check($voltar_ok, "edit list p2: Voltar preserva page");
check(!has($html_edit_list, 'id="l7-form-assign"'), "edit list p2: exclusivo (sem assign)");

/* ---- edit from batch page2 ---- */
$html_edit_batch = l7h_render(array(
	"get" => array("edit" => $mac51, "mode" => "batch", "page" => "2"),
	"inventory" => $inv60,
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/edit-from-batch-page2.html", $html_edit_batch);
}
check(has($html_edit_batch, 'id="l7-alias-edit"'), "edit batch p2: id alias");
check(has($html_edit_batch, "mode=batch"), "edit batch p2: mode=batch no HTML");
check(preg_match('/<form[^>]+action="[^"]*mode=batch[^"]*"/', $html_edit_batch) === 1, "edit batch p2: POST action mode=batch");
check(preg_match('/<form[^>]+action="[^"]*page=2[^"]*"/', $html_edit_batch) === 1, "edit batch p2: POST action page=2");
check(preg_match('/href="layer7_devices\.php\?mode=batch[^"]*page=2/', $html_edit_batch)
	|| preg_match('/href="layer7_devices\.php\?[^"]*mode=batch[^"]*page=2/', $html_edit_batch), "edit batch p2: Voltar batch+page");
check(!has($html_edit_batch, 'id="l7-form-aliases"'), "edit batch p2: sem lote na view edit");

/* ---- filtro ---- */
$html_filter = l7h_render(array(
	"get" => array("q" => "lab-one", "online" => "1"),
	"inventory" => $inv60,
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/list-filter.html", $html_filter);
}
check(has($html_filter, $mac1), "filtro: lab-one visivel");
check(!has($html_filter, $mac2), "filtro: host-2 oculto");
check(has($html_filter, 'value="lab-one"'), "filtro: q preservado");
check(has($html_filter, 'checked="checked"'), "filtro: online checked");
check(has($html_filter, "Limpar filtros"), "filtro: Limpar filtros");

/* ---- vazio / sem MAC ---- */
$html_nomac = l7h_render(array(
	"get" => array("mode" => "batch"),
	"inventory" => array_merge(l7h_inventory(1), array($nomac)),
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/batch-nomac.html", $html_nomac);
}
check(has($html_nomac, "sem-mac"), "nomac: hostname visivel");
check(l7h_count_names(l7h_named_attrs(l7h_form_inner($html_nomac, "l7-form-aliases")), "alias[") === 1, "nomac: um alias (so com MAC)");
check(l7h_count_names(l7h_named_attrs(l7h_form_inner($html_nomac, "l7-form-assign")), "assign_macs[]") === 1, "nomac: um checkbox (so com MAC)");

$html_nomatch = l7h_render(array(
	"get" => array("q" => "nao-existe-xyz"),
	"inventory" => $inv60,
));
check(has($html_nomatch, "Nenhum dispositivo corresponde ao filtro"), "filtro vazio: mensagem");

/* ---- escaping ---- */
check(has($html_p1, "&lt;script&gt;") || has($html_batch, "&lt;script&gt;"), "escaping: hostname script");
check(!has($html_batch, "<script>x</script>") && !has($html_p1, "<script>x</script>"), "escaping: script nao cru");
check(has($html_batch, "foo&quot; onclick=&quot;x") || has($html_batch, 'value="foo&quot; onclick=&quot;x"'), "escaping: alias aspas");

/* ---- erro assign: selecao/grupo restaurados ---- */
$html_err_asg = l7h_render(array(
	"get" => array("mode" => "batch"),
	"post" => array(
		"assign_to_group" => "1",
		"assign_macs" => array($mac1, $mac2),
		"assign_group" => "",
	),
	"inventory" => l7h_inventory(3),
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/error-assign.html", $html_err_asg);
}
check(has($html_err_asg, "l7-input-errors"), "erro assign: alerta");
check(has($html_err_asg, "Selecione um grupo de destino"), "erro assign: mensagem");
check(preg_match('/id="l7-dev-chk-' . preg_replace('/[^0-9a-f]/', '', $mac1) . '"[^>]*checked="checked"/', $html_err_asg) === 1, "erro assign: MAC1 restaurado");
check(preg_match('/id="l7-dev-chk-' . preg_replace('/[^0-9a-f]/', '', $mac2) . '"[^>]*checked="checked"/', $html_err_asg) === 1, "erro assign: MAC2 restaurado");

/* ---- erro alias: valor restaurado ---- */
$html_err_alias = l7h_render(array(
	"get" => array("mode" => "batch"),
	"post" => array(
		"save_aliases" => "1",
		"alias" => array(
			$mac1 => "restaurado-ok",
			"zz:bad" => "x",
		),
	),
	"inventory" => l7h_inventory(2),
));
if (is_dir($out_dir)) {
	file_put_contents($out_dir . "/error-alias.html", $html_err_alias);
}
check(has($html_err_alias, "MAC invalido"), "erro alias: mensagem");
check(has($html_err_alias, 'value="restaurado-ok"'), "erro alias: valor restaurado");

/* ---- edit erro alias restaurado ---- */
$html_err_edit = l7h_render(array(
	"get" => array("edit" => $mac1),
	"post" => array(
		"save_aliases" => "1",
		"alias" => array(
			$mac1 => "edit-restaurado",
			"not-a-mac" => "x",
		),
	),
	"inventory" => l7h_inventory(1),
));
check(has($html_err_edit, 'id="l7-alias-edit"'), "erro edit: campo");
check(has($html_err_edit, 'value="edit-restaurado"'), "erro edit: alias restaurado");

if (is_dir($out_dir)) {
	echo "HTML gerado em {$out_dir} (evidencia, nao produto)\n";
}

if ($fail) {
	fwrite(STDERR, "SOME DEVICES RENDER HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL DEVICES RENDER HARNESS TESTS PASSED\n";
exit(0);
