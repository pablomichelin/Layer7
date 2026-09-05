<?php
/**
 * BG-174 / V2 — gate estático da view de Groups (list / edit / new).
 * Lê layer7_groups.php como texto. Não carrega guiconfig nem layer7.inc.
 *
 *   php tests/functional/test_groups_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_groups.php";
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
	'require_once("classes/Form.class.php")' => 'Form.class.php',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'new Form(false)' => 'Form(false)',
	'Form_Section' => 'Form_Section',
	'Form_StaticText' => 'Form_StaticText',
	'new Form_Input(' => 'Form_Input',
	'new Form_Textarea(' => 'Form_Textarea',
	'new Form_Select(' => 'Form_Select',
	'new Form_Button("save_group_edit"' => 'Form_Button save_group_edit',
	'new Form_Button("add_group"' => 'Form_Button add_group',
	'new Form_Button("resync_devices"' => 'Form_Button resync_devices',
	'new Form_Button("delete_group"' => 'Form_Button delete_group',
	'"fa fa-save"' => 'icone fa fa-save',
	'"fa fa-plus"' => 'icone fa fa-plus',
	'"fa fa-refresh"' => 'icone fa fa-refresh',
	'"fa fa-trash"' => 'icone fa fa-trash',
	'l7_t("Guardar alteracoes")' => 'legenda Guardar alteracoes',
	'l7_t("Adicionar grupo")' => 'legenda Adicionar grupo',
	'l7_t("Resync IPs dos dispositivos")' => 'legenda Resync',
	'l7_t("Remover")' => 'legenda Remover',
	'id="l7-edit-group"' => 'ancora l7-edit-group',
	'id="l7-add-group"' => 'ancora l7-add-group',
	'id="l7-groups"' => 'ancora l7-groups',
	'$l7_group_mode = "list"' => 'modo list',
	'$l7_group_mode = "edit"' => 'modo edit',
	'$l7_group_mode = "new"' => 'modo new',
	'if ($l7_group_mode === "list")' => 'render list exclusivo',
	'if ($l7_group_mode === "edit")' => 'render edit exclusivo',
	'if ($l7_group_mode === "new")' => 'render new exclusivo',
	'$_POST["add_group"]' => 'handler add_group',
	'$_POST["delete_group"]' => 'handler delete_group',
	'$_POST["save_group_edit"]' => 'handler save_group_edit',
	'$_POST["resync_devices"]' => 'handler resync_devices',
	'new Form_Input("new_group_id"' => 'campo new_group_id',
	'new Form_Input("new_group_name"' => 'campo new_group_name',
	'new Form_Textarea("new_group_cidrs"' => 'campo new_group_cidrs',
	'new Form_Textarea("new_group_hosts"' => 'campo new_group_hosts',
	'new Form_Textarea("new_group_devices"' => 'campo new_group_devices',
	'new Form_Input("edit_group_index"' => 'campo edit_group_index',
	'new Form_Input("edit_group_name"' => 'campo edit_group_name',
	'new Form_Textarea("edit_group_cidrs"' => 'campo edit_group_cidrs',
	'new Form_Textarea("edit_group_hosts"' => 'campo edit_group_hosts',
	'new Form_Textarea("edit_group_devices"' => 'campo edit_group_devices',
	'new Form_Select("delete_group_index"' => 'campo delete_group_index',
	'array_key_exists($posted_del, $del_opts)' => 'restaura delete_group_index valido apos erro',
	'setAttribute("maxlength", "80")' => 'maxlength id 80',
	'setAttribute("maxlength", "160")' => 'maxlength nome 160',
	'setAttribute("pattern", "[a-zA-Z0-9_-]+")' => 'pattern id',
	'setAttribute("required", "required")' => 'required id',
	'$_GET["edit"]' => 'deep link edit',
	'$_GET["new"]' => 'deep link new',
	'layer7_groups.php?new=1' => 'link Adicionar grupo',
	'layer7_render_tabs("policies")' => 'tabs',
	'layer7_render_policies_subnav("groups")' => 'subnav',
	'IDENT=page-services-layer7-groups' => 'privilege IDENT',
	'MATCH=layer7_groups.php*' => 'privilege MATCH',
	'setAttribute("id", "l7-new-group-id")' => 'id new group',
	'setAttribute("id", "l7-edit-group-name")' => 'id edit name',
	'setAttribute("id", "delete_group_index")' => 'id delete select',
	'Remover este grupo?' => 'confirm remocao',
	'Cancelar edicao' => 'cancelar',
	'Voltar a lista' => 'voltar',
	'Um CIDR por linha (max. 8).' => 'limite CIDR',
	'Um IPv4 por linha (max. 16).' => 'limite IP',
	'Um MAC por linha (max. 64).' => 'limite MAC',
	'htmlspecialchars((string)$dip, ENT_QUOTES, "UTF-8")' => 'escape IPs resolvidos no setHelp',
	'function layer7_group_policy_count($gid, $policies)' => 'helper de contagem no produto',
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
	'new Form();' => 'Form() com Save padrao',
	'max_input_vars' => 'max_input_vars no produto',
	'function_exists("layer7_group_policy_count")' => 'guard function_exists no helper (so harness)',
	'setAttribute("value", "1")' => 'value=1 em Form_Button (destroi legenda)',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

check(substr_count($src, 'new Form_Button("add_group"') === 1, "um unico add_group");
check(substr_count($src, 'new Form_Button("save_group_edit"') === 1, "um unico save_group_edit");
check(substr_count($src, 'new Form_Button("delete_group"') === 1, "um unico delete_group");

if ($fail) {
	fwrite(STDERR, "SOME GROUPS NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL GROUPS NATIVE VIEW TESTS PASSED\n";
exit(0);
