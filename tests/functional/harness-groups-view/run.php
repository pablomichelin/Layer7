<?php
/**
 * Harness de render — executa layer7_groups.php com stubs.
 * save_json é sempre false: não há persistência nem redirect/exit de sucesso.
 *
 *   php tests/functional/harness-groups-view/run.php
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

echo "HARNESS RENDER — view efetiva layer7_groups.php\n";
echo "Form_* pinados (Devices + Textarea/Select 9363ac5b)\n";
echo "Nao e pfSense, nao e appliance, save_json=false, sem CSRF\n";

$g0 = array();
$g1 = array(l7hg_group("lab", array(
	"name" => "Lab",
	"cidrs" => array("192.0.2.0/24"),
	"hosts" => array("192.0.2.10"),
	"device_macs" => array("aa:00:00:00:00:01"),
	"device_ips" => array("192.0.2.10"),
)));
$g_xss = array(l7hg_group("xss", array(
	"name" => 'grp<script>x</script>',
	"cidrs" => array("198.51.100.0/24"),
	"device_macs" => array("aa:00:00:00:00:02"),
	"device_ips" => array(),
)));
$g16 = array();
for ($i = 1; $i <= 16; $i++) {
	$g16[] = l7hg_group("g" . $i, array(
		"name" => "Grupo " . $i,
		"cidrs" => array("203.0.113." . $i . "/32"),
	));
}
$pol_ref = array(
	array("id" => "p1", "match" => array("groups" => array("lab"))),
);

/* 0 grupos */
$html0 = l7hg_render(array("data" => l7hg_data($g0)));
file_put_contents($out_dir . "/empty.html", $html0);
check(has($html0, "Nenhum grupo criado"), "0 grupos: vazio");
check(has($html0, "layer7_groups.php?new=1"), "0 grupos: link adicionar");
check(has($html0, 'id="l7-groups"'), "0 grupos: ancora lista");
check(has($html0, 'id="l7-add-group"'), "0 grupos: ancora add util");
check(!has($html0, 'name="new_group_id"'), "0 grupos: sem form criacao");
check(!has($html0, 'name="save"'), "0 grupos: sem Save padrao");
check(has($html0, "<!-- L7HG_HEAD -->"), "0 grupos: head stub");
check(has($html0, "resync_devices"), "0 grupos: resync na consulta");

/* 1 grupo */
$html1 = l7hg_render(array("data" => l7hg_data($g1, $pol_ref)));
file_put_contents($out_dir . "/one.html", $html1);
check(has($html1, "lab"), "1 grupo: id");
check(has($html1, "192.0.2.0/24"), "1 grupo: CIDR");
check(has($html1, "192.0.2.10"), "1 grupo: IP");
check(has($html1, "1 ") && has($html1, "dispositivos"), "1 grupo: MAC com IP resolvido");
check(has($html1, ">1<") || has($html1, "\t1"), "1 grupo: 1 politica");
check(has($html1, "layer7_groups.php?edit=0"), "1 grupo: Editar");
check(has($html1, 'name="delete_group_index"'), "1 grupo: delete");
check(has($html1, 'name="delete_group"'), "1 grupo: botao delete");
check(preg_match('/name="delete_group"[^>]*value="Remover"/', $html1)
	|| preg_match('/value="Remover"[^>]*name="delete_group"/', $html1), "1 grupo: delete_group legenda");
check(preg_match('/name="resync_devices"[^>]*value="Resync IPs dos dispositivos"/', $html1)
	|| preg_match('/value="Resync IPs dos dispositivos"[^>]*name="resync_devices"/', $html1), "1 grupo: resync_devices legenda");
check(has($html1, "fa fa-trash"), "1 grupo: icone fa fa-trash");
check(has($html1, "fa fa-refresh"), "1 grupo: icone fa fa-refresh");
check(!has($html1, 'name="new_group_id"'), "1 grupo list: sem criacao");
check(!has($html1, 'name="edit_group_name"'), "1 grupo list: sem editor");

/* 16 grupos */
$html16 = l7hg_render(array("data" => l7hg_data($g16)));
file_put_contents($out_dir . "/list-16.html", $html16);
check(has($html16, "g1") && has($html16, "g16"), "16 grupos: todos");
check(has($html16, "Limite de 16 grupos atingido"), "16 grupos: limite");
check(!has($html16, "layer7_groups.php?new=1"), "16 grupos: sem link criar");

/* edit=0 */
$html_e0 = l7hg_render(array(
	"get" => array("edit" => "0"),
	"data" => l7hg_data($g1),
));
file_put_contents($out_dir . "/edit-0.html", $html_e0);
check(has($html_e0, 'id="l7-edit-group"'), "edit=0: ancora");
check(has($html_e0, 'id="l7-edit-group-name"'), "edit=0: id nome");
check(has($html_e0, 'name="edit_group_name"'), "edit=0: name nome");
check(has($html_e0, 'name="edit_group_cidrs"'), "edit=0: cidrs");
check(has($html_e0, 'name="edit_group_hosts"'), "edit=0: hosts");
check(has($html_e0, 'name="edit_group_devices"'), "edit=0: macs");
check(has($html_e0, 'name="edit_group_index"'), "edit=0: index");
check(has($html_e0, 'name="save_group_edit"'), "edit=0: guardar");
check(preg_match('/name="save_group_edit"[^>]*value="Guardar alteracoes"/', $html_e0)
	|| preg_match('/value="Guardar alteracoes"[^>]*name="save_group_edit"/', $html_e0), "edit=0: save_group_edit legenda");
check(has($html_e0, "fa fa-save"), "edit=0: icone fa fa-save");
check(has($html_e0, 'value="Lab"') || has($html_e0, "Lab"), "edit=0: valor nome");
check(has($html_e0, "Cancelar edicao"), "edit=0: cancelar");
check(!has($html_e0, 'id="l7-groups"'), "edit=0: sem lista");
check(!has($html_e0, 'name="add_group"'), "edit=0: sem criacao");
check(!has($html_e0, 'name="delete_group"'), "edit=0: sem remocao");
check(!has($html_e0, 'name="save"'), "edit=0: sem Save padrao");
check(has($html_e0, "192.0.2.10"), "edit=0: IPs resolvidos");

/* edit invalido */
$html_bad = l7hg_render(array(
	"get" => array("edit" => "9"),
	"data" => l7hg_data($g1),
));
file_put_contents($out_dir . "/edit-invalid.html", $html_bad);
check(has($html_bad, 'id="l7-groups"'), "edit invalido: lista");
check(!has($html_bad, 'name="edit_group_name"'), "edit invalido: sem editor");
check(!has($html_bad, "Grupo adicionado"), "edit invalido: sem mutacao");

/* new=1 */
$html_new = l7hg_render(array(
	"get" => array("new" => "1"),
	"data" => l7hg_data($g1),
));
file_put_contents($out_dir . "/new.html", $html_new);
check(has($html_new, 'id="l7-add-group"'), "new=1: ancora");
check(has($html_new, 'name="new_group_id"'), "new=1: id");
check(has($html_new, 'name="new_group_name"'), "new=1: nome");
check(has($html_new, 'name="new_group_cidrs"'), "new=1: cidrs");
check(has($html_new, 'name="new_group_hosts"'), "new=1: hosts");
check(has($html_new, 'name="new_group_devices"'), "new=1: macs");
check(has($html_new, 'name="add_group"'), "new=1: add_group");
check(preg_match('/name="add_group"[^>]*value="Adicionar grupo"/', $html_new)
	|| preg_match('/value="Adicionar grupo"[^>]*name="add_group"/', $html_new), "new=1: add_group legenda");
check(has($html_new, "fa fa-plus"), "new=1: icone fa fa-plus");
check(has($html_new, 'maxlength="80"'), "new=1: maxlength 80");
check(has($html_new, 'maxlength="160"'), "new=1: maxlength 160");
check(has($html_new, 'pattern="[a-zA-Z0-9_-]+"'), "new=1: pattern");
check(has($html_new, "required"), "new=1: required");
check(has($html_new, "Voltar a lista"), "new=1: voltar");
check(!has($html_new, 'id="l7-groups"'), "new=1: sem lista");
check(!has($html_new, 'name="save"'), "new=1: sem Save padrao");

/* erro add + texto restaurado */
$html_err_add = l7hg_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_group" => "1",
		"new_group_id" => "restaurado",
		"new_group_name" => "Nome POST",
		"new_group_cidrs" => "",
		"new_group_hosts" => "",
		"new_group_devices" => "",
	),
	"data" => l7hg_data($g1),
));
file_put_contents($out_dir . "/error-add.html", $html_err_add);
check(has($html_err_add, "l7-input-errors"), "erro add: alerta");
check(has($html_err_add, "Indique pelo menos um CIDR"), "erro add: mensagem");
check(has($html_err_add, 'value="restaurado"'), "erro add: id restaurado");
check(has($html_err_add, 'value="Nome POST"'), "erro add: nome restaurado");
check(has($html_err_add, 'name="new_group_id"'), "erro add: reabre criacao");

/* erro edit + texto restaurado */
$html_err_edit = l7hg_render(array(
	"get" => array("edit" => "0"),
	"post" => array(
		"save_group_edit" => "1",
		"edit_group_index" => "0",
		"edit_group_name" => "edit-restaurado",
		"edit_group_cidrs" => "",
		"edit_group_hosts" => "",
		"edit_group_devices" => "",
	),
	"data" => l7hg_data($g1),
));
file_put_contents($out_dir . "/error-edit.html", $html_err_edit);
check(has($html_err_edit, "l7-input-errors"), "erro edit: alerta");
check(has($html_err_edit, "Indique pelo menos um CIDR"), "erro edit: mensagem");
check(has($html_err_edit, 'value="edit-restaurado"'), "erro edit: nome restaurado");
check(has($html_err_edit, 'id="l7-edit-group"'), "erro edit: reabre editor");

/* grupo referenciado */
$html_ref = l7hg_render(array(
	"data" => l7hg_data($g1, $pol_ref),
	"post" => array(
		"delete_group" => "1",
		"delete_group_index" => "0",
	),
));
file_put_contents($out_dir . "/error-delete-ref.html", $html_ref);
check(has($html_ref, "esta em uso por uma politica"), "ref: rejeitado no handler");
check(has($html_ref, "lab"), "ref: grupo ainda na lista");

/* GET: default indice 0; erro no indice 1 referenciado: alerta, grupo intacto, opcao 1 seleccionada */
$g2 = array(
	l7hg_group("alpha", array("name" => "Alpha", "cidrs" => array("192.0.2.0/24"))),
	l7hg_group("beta", array("name" => "Beta", "cidrs" => array("198.51.100.0/24"))),
);
$pol_beta = array(array("id" => "p1", "match" => array("groups" => array("beta"))));
$html_del_get = l7hg_render(array("data" => l7hg_data($g2)));
file_put_contents($out_dir . "/delete-get-2.html", $html_del_get);
check(preg_match('/<option value="0" selected>/', $html_del_get) === 1, "GET remocao: default indice 0");
check(preg_match('/<option value="1" selected>/', $html_del_get) !== 1, "GET remocao: indice 1 nao seleccionado");
$html_del1 = l7hg_render(array(
	"data" => l7hg_data($g2, $pol_beta),
	"post" => array(
		"delete_group" => "1",
		"delete_group_index" => "1",
	),
));
file_put_contents($out_dir . "/error-delete-index-1.html", $html_del1);
check(has($html_del1, "l7-input-errors"), "erro delete idx1: alerta");
check(has($html_del1, "esta em uso por uma politica"), "erro delete idx1: rejeitado");
check(has($html_del1, "alpha") && has($html_del1, "beta"), "erro delete idx1: grupos preservados");
check(preg_match('/<option value="1" selected>/', $html_del1) === 1, "erro delete idx1: opcao 1 seleccionada");
check(preg_match('/<option value="0" selected>/', $html_del1) !== 1, "erro delete idx1: indice 0 nao seleccionado");

/* MAC sem IP */
$html_off = l7hg_render(array(
	"get" => array("edit" => "0"),
	"data" => l7hg_data($g_xss),
));
file_put_contents($out_dir . "/edit-mac-offline.html", $html_off);
check(has($html_off, "Nenhum IP resolvido"), "mac sem IP: aviso");
check(has($html_off, "&lt;script&gt;"), "escaping: nome script");
check(!has($html_off, "<script>x</script>"), "escaping: script nao cru");

$g_badip = array(l7hg_group("badip", array(
	"name" => "badip",
	"device_macs" => array("aa:00:00:00:00:03"),
	"device_ips" => array('<img src=x onerror=alert(1)>', '192.0.2.99<script>'),
)));
$html_badip = l7hg_render(array(
	"get" => array("edit" => "0"),
	"data" => l7hg_data($g_badip),
));
file_put_contents($out_dir . "/edit-ips-escaped.html", $html_badip);
check(has($html_badip, "IPs resolvidos agora"), "ips maliciosos: help presente");
check(has($html_badip, "&lt;img src=x onerror=alert(1)&gt;")
	|| has($html_badip, "&lt;img src=x onerror&#61;alert(1)&gt;"), "ips maliciosos: img escapada");
check(has($html_badip, "192.0.2.99&lt;script&gt;"), "ips maliciosos: script escapado");
check(!preg_match('/<img\b/i', $html_badip), "ips maliciosos: tag img real ausente");
check(!preg_match('/<script\b/i', $html_badip), "ips maliciosos: tag script real ausente");
check(!preg_match('/<[^>]*\sonerror\s*=/i', $html_badip), "ips maliciosos: atributo onerror real ausente");

/* labels: for e id correspondentes (ambos) */
function has_for_id($html, $id)
{
	return (strpos($html, 'id="' . $id . '"') !== false)
		&& (strpos($html, 'for="' . $id . '"') !== false);
}
$edit_ids = array("l7-edit-group-name", "l7-edit-group-cidrs", "l7-edit-group-hosts", "l7-edit-group-devices");
foreach ($edit_ids as $fid) {
	check(has_for_id($html_e0, $fid), "edit: for+id {$fid}");
}
$new_ids = array("l7-new-group-id", "l7-new-group-name", "l7-new-group-cidrs", "l7-new-group-hosts", "l7-new-group-devices");
foreach ($new_ids as $fid) {
	check(has_for_id($html_new, $fid), "new: for+id {$fid}");
}
check(has_for_id($html1, "delete_group_index"), "remocao: for+id delete_group_index");

echo "HTML gerado em {$out_dir} (evidencia, nao produto)\n";
echo "Form vendor noise conhecido: " . (int)$GLOBALS["l7hg_form_noise"] . "\n";
if (!empty($GLOBALS["l7hg_form_noise_unexpected"])) {
	foreach ($GLOBALS["l7hg_form_noise_unexpected"] as $u) {
		echo "UNEXPECTED_VENDOR_WARNING: {$u}\n";
	}
}
check(empty($GLOBALS["l7hg_form_noise_unexpected"]), "vendor: so avisos conhecidos");

if ($fail) {
	fwrite(STDERR, "SOME GROUPS RENDER HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL GROUPS RENDER HARNESS TESTS PASSED\n";
exit(0);
