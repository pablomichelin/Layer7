<?php
/**
 * BG-174 / V6a–V6c — gate estatico da view de Exceptions (list / edit / new / VIP modos).
 *
 *   php tests/functional/test_exceptions_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php";
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
	'new Form(false)' => 'Form(false)',
	'Form_Section' => 'Form_Section',
	'Form_StaticText' => 'Form_StaticText',
	'Form_Input' => 'Form_Input',
	'Form_Textarea' => 'Form_Textarea',
	'Form_Select' => 'Form_Select',
	'Form_Checkbox' => 'Form_Checkbox',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("policies")' => 'tabs policies',
	'layer7_render_policies_subnav("exceptions")' => 'subnav exceptions',
	'$l7_exc_mode = "list"' => 'modo list',
	'$l7_vip_mode' => 'modo VIP exclusivo',
	'$l7_vip_bookmark_bridge' => 'ponte bookmark GET-only',
	'$l7_vip_add_retry' => 'retry add VIP',
	'$l7_exc_add_retry' => 'retry add excecao',
	'$l7_exc_edit_retry' => 'retry edit',
	'$l7_exc_edit_post_invalid' => 'POST edit indice invalido',
	'$l7_save_exc_retry' => 'retry save_exceptions',
	'if ($l7_exc_mode === "list")' => 'render list exclusivo',
	'if ($l7_exc_mode === "list" && $l7_vip_mode !== "")' => 'VIP so em modo vip',
	'if ($l7_exc_mode === "list" && $l7_vip_mode === "")' => 'geral sem VIP',
	'if ($l7_vip_bookmark_bridge)' => 'ponte bookmark condicional',
	'id="l7-vip-bookmark-bridge"' => 'marcador ponte bookmark',
	'layer7_exceptions.php#l7-vip-list' => 'action literal VIP',
	'layer7_exceptions.php?vip=1#l7-vip-list' => 'link nav VIP',
	'if ($l7_exc_mode === "edit"' => 'render edit exclusivo',
	'if ($l7_exc_mode === "new")' => 'render new exclusivo',
	'layer7_exceptions.php?new=1' => 'link criar',
	'layer7_exceptions.php?edit=' => 'deep link edit',
	'id="l7-vip-list"' => 'ancora l7-vip-list',
	'id="l7-exceptions"' => 'ancora l7-exceptions',
	'id="l7-edit-exc"' => 'ancora l7-edit-exc',
	'id="l7-add-exc"' => 'ancora l7-add-exc',
	'panel panel-default' => 'painel nativo Bootstrap',
	'text-center text-muted' => 'credito nativo',
	'https://www.systemup.inf.br' => 'URL credito Systemup',
	'name="save_exceptions" value="1"' => 'save_exceptions value=1 manual',
	'name="add_exception" value="1"' => 'add_exception value=1 manual',
	'name="save_exception_edit" value="1"' => 'save_exception_edit value=1 manual',
	'name="delete_exception" value="1"' => 'delete_exception value=1 manual',
	'class="sr-only"' => 'label sr-only eon',
	'for="' => 'label for eon',
	'"eon-exc-"' => 'id eon checkbox',
	'array("min" => 0, "max" => 99999)' => 'prioridade min0 max99999',
	'$l7_new_priority = "500"' => 'prioridade add string default',
	'$ee_priority = "0"' => 'prioridade edit string default',
	'array_key_exists("new_priority", $_POST)' => 'prioridade add raw POST',
	'array_key_exists("edit_priority", $_POST)' => 'prioridade edit raw POST',
	'Cancelar edicao' => 'cancelar edit',
	'Voltar a lista' => 'voltar new',
	'$_POST["add_exception"]' => 'handler add_exception',
	'$_POST["save_exception_edit"]' => 'handler save_exception_edit',
	'$_POST["save_exceptions"]' => 'handler save_exceptions',
	'$_POST["delete_exception"]' => 'handler delete_exception',
	'function l7setChecks' => 'JS l7setChecks intacto',
	'function l7filterIface' => 'JS l7filterIface intacto',
	'function l7filterDhcpIface' => 'JS l7filterDhcpIface intacto',
	'classList.add("hidden")' => 'visibilidade Bootstrap hidden',
	'l7_t("Confirmar selecao")' => 'secao DHCP submit titulada',
	'l7_t("Hosts (IP)")' => 'help neutralizado Hosts (IP)',
	'l7_t("Um IP por linha. Pode combinar com CIDRs.")' => 'help hosts por linha',
	'l7_t("Um CIDR por linha.")' => 'help cidr new',
	'l7_t("Um CIDR por linha. Ex.: 192.168.0.0/24")' => 'help cidr edit exemplo',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$form_pos = strpos($src, 'require_once("classes/Form.class.php")');
$add_retry_pos = strpos($src, '$l7_exc_add_retry =');
$head_pos = strpos($src, 'include("head.inc")');
check($form_pos !== false && $add_retry_pos !== false && $form_pos < $add_retry_pos,
	"Form.class.php antes de \$l7_exc_add_retry (posicao original)");
check($head_pos === false || $form_pos === false || $form_pos < $head_pos,
	"Form.class.php nao duplicado apos head.inc");
check(substr_count($src, 'require_once("classes/Form.class.php")') === 1,
	"Form.class.php require unico");

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'layer7-page' => 'layer7-page',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7-form-card' => 'layer7-form-card',
	'layer7-muted-note' => 'layer7-muted-note',
	'layer7-toolbar' => 'layer7-toolbar',
	'is-hidden' => 'classe is-hidden custom',
	'style=' => 'atributo style= layout',
	'new Form_Button("add_exception"' => 'Form_Button add_exception',
	'new Form_Button("save_exception_edit"' => 'Form_Button save_exception_edit',
	'new Form_Button("delete_exception"' => 'Form_Button delete_exception',
	'new Form();' => 'Form() com Save padrao',
	'setAttribute("value", "1")' => 'value=1 em Form_Button',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS NATIVE VIEW TESTS PASSED\n";
exit(0);
