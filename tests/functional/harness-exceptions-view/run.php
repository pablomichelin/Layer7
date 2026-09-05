<?php
/**
 * Harness render — layer7_exceptions.php (V6a excecoes gerais).
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/harness-exceptions-view/run.php
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

echo "HARNESS RENDER — layer7_exceptions.php V6a\n";
echo "save_json=false por omissao; VIP/JS/handlers congelados no produto\n";

$e0 = array();
$e1 = array(l7he_exc("mgmt", array(
	"hosts" => array("10.0.0.1"),
	"priority" => 500,
	"action" => "allow",
)));
$e_legacy = array(l7he_exc("legacy", array(
	"host" => "192.0.2.10",
	"cidr" => "192.0.2.0/24",
	"priority" => 0,
	"enabled" => false,
)));
$e_managed = array(l7he_exc("vip-isentos", array(
	"hosts" => array("10.0.0.99"),
	"priority" => 99999,
	"action" => "allow",
)));
$e16 = array();
for ($i = 1; $i <= 16; $i++) {
	$e16[] = l7he_exc("ex" . $i, array(
		"hosts" => array("10.0.0." . $i),
		"priority" => 500 - $i,
	));
}

/* consulta 0 */
$html0 = l7he_render(array("data" => l7he_data($e0)));
file_put_contents($out_dir . "/list-0.html", $html0);
check(has($html0, 'id="l7-exceptions"'), "0: ancora lista");
check(has($html0, "layer7_exceptions.php?new=1"), "0: link criar");
check(!has($html0, 'name="new_id"'), "0: sem form criacao inline");
check(!has($html0, 'id="l7-vip-list"'), "0: sem bloco VIP na consulta geral");
check(has($html0, 'id="l7-vip-bookmark-bridge"'), "0: ponte bookmark GET-only presente");
check(has($html0, "function l7setChecks"), "0: JS l7setChecks intacto");
check(!has($html0, 'id="l7-vip-iface-filter"'), "0: sem filtro VIP na consulta geral");

/* consulta 1 */
$html1 = l7he_render(array("data" => l7he_data($e1)));
check(has($html1, "mgmt"), "1: id na tabela");
check(has($html1, 'name="eon[0]"'), "1: checkbox eon");
check(has($html1, 'id="eon-exc-0"'), "1: id checkbox eon");
check(has($html1, 'for="eon-exc-0"'), "1: label for eon");
check(has($html1, 'name="save_exceptions" value="1"'), "1: save_exceptions value=1");
check(has($html1, "layer7_exceptions.php?edit=0"), "1: link editar");

/* new=1 */
$html_new = l7he_render(array(
	"get" => array("new" => "1"),
	"data" => l7he_data($e1),
));
file_put_contents($out_dir . "/new.html", $html_new);
check(has($html_new, 'id="l7-add-exc"'), "new: ancora");
check(has($html_new, 'name="new_id"'), "new: campo id");
check(has($html_new, 'name="add_exception" value="1"'), "new: add_exception value=1");
check(has($html_new, 'value="500"'), "new: prioridade default 500");
check(!has($html_new, 'id="l7-vip-list"'), "new: sem VIP");
check(!has($html_new, 'name="save"'), "new: sem Save padrao");

/* edit=0 */
$html_edit = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e1),
));
check(has($html_edit, 'id="l7-edit-exc"'), "edit: ancora");
check(has($html_edit, 'name="save_exception_edit" value="1"'), "edit: save value=1");
check(has($html_edit, 'name="edit_exception_index"'), "edit: indice hidden");
check(!has($html_edit, 'id="l7-vip-list"'), "edit: sem VIP");

/* GET edit invalido */
$html_bad_edit = l7he_render(array(
	"get" => array("edit" => "99"),
	"data" => l7he_data($e1),
));
check(has($html_bad_edit, "l7-input-errors"), "GET edit invalido: alerta");
check(!has($html_bad_edit, 'id="l7-edit-exc"'), "GET edit invalido: sem editor");
check(has($html_bad_edit, 'id="l7-exceptions"'), "GET edit invalido: consulta");

/* POST edit indice invalido + GET edit valido — nao abrir outro editor */
$html_post_bad_idx = l7he_render(array(
	"get" => array("edit" => "0"),
	"post" => array(
		"save_exception_edit" => "1",
		"edit_exception_index" => "99",
		"edit_hosts" => "10.0.0.1",
		"edit_cidrs" => "",
		"edit_priority" => "500",
		"edit_action" => "allow",
	),
	"data" => l7he_data($e1),
));
check(has($html_post_bad_idx, "l7-input-errors"), "POST idx invalido+GET edit: alerta");
check(!has($html_post_bad_idx, 'id="l7-edit-exc"'), "POST idx invalido+GET edit: sem editor");
check(has($html_post_bad_idx, 'id="l7-exceptions"'), "POST idx invalido+GET edit: consulta");

/* erro add: prioridade vazia preservada */
$html_err_add = l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "novo-id",
		"new_hosts" => "",
		"new_cidrs" => "",
		"new_priority" => "",
		"new_action" => "block",
	),
	"data" => l7he_data($e1),
));
check(has($html_err_add, "Indique pelo menos um host"), "erro add: mensagem host");
check(has($html_err_add, 'name="new_id"'), "erro add: reabre new");
check(preg_match('/name="new_priority"[^>]*value=""/', $html_err_add) === 1
	|| preg_match('/name="new_priority"[^>]*>\s*<\/input>/', $html_err_add) === 1, "erro add: prioridade vazia preservada");

/* erro edit: prioridade vazia */
$html_err_edit = l7he_render(array(
	"get" => array("edit" => "0"),
	"post" => array(
		"save_exception_edit" => "1",
		"edit_exception_index" => "0",
		"edit_hosts" => "",
		"edit_cidrs" => "",
		"edit_priority" => "",
		"edit_action" => "monitor",
	),
	"data" => l7he_data($e1),
));
check(has($html_err_edit, 'id="l7-edit-exc"'), "erro edit: reabre editor");
check(preg_match('/name="edit_priority"[^>]*value=""/', $html_err_edit) === 1
	|| preg_match('/name="edit_priority"[^>]*>\s*<\/input>/', $html_err_edit) === 1, "erro edit: prioridade vazia preservada");

/* limite 16 + erro add: texto preservado */
$html_limit = l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "tentativa-limite",
		"new_hosts" => "10.0.0.200",
		"new_cidrs" => "",
		"new_priority" => "777",
	),
	"data" => l7he_data($e16),
));
check(has($html_limit, "Limite de 16 excecoes"), "limite16: mensagem handler");
check(has($html_limit, 'value="tentativa-limite"'), "limite16: id preservado");
check(has($html_limit, 'name="new_hosts"'), "limite16: formulario visivel");

/* save_exceptions savefalse: eon raw (tudo desmarcado) */
$html_save_off = l7he_render(array(
	"post" => array("save_exceptions" => "1"),
	"data" => l7he_data($e1),
	"save_result" => false,
));
check(!has($html_save_off, "l7-savemsg"), "savefalse: sem savemsg");
check(!preg_match('/name="eon\[0\]"[^>]*checked/', $html_save_off), "savefalse: eon[0] desmarcado");

/* save_exceptions savefalse: eon parcial preservado */
$html_save_partial = l7he_render(array(
	"post" => array(
		"save_exceptions" => "1",
		"eon" => array("0" => "1"),
	),
	"data" => l7he_data(array_merge($e1, array(l7he_exc("b", array("hosts" => array("10.0.0.2")))))),
	"save_result" => false,
));
check(preg_match('/name="eon\[0\]"[^>]*checked/', $html_save_partial) === 1, "savefalse: eon[0] marcado");
check(!preg_match('/name="eon\[1\]"[^>]*checked/', $html_save_partial), "savefalse: eon[1] desmarcado");

/* managed VIP label */
$html_managed = l7he_render(array("data" => l7he_data($e_managed)));
check(has($html_managed, "Perfis rapidos"), "managed: badge");

/* 16 excecoes: todos eon na lista */
$html16 = l7he_render(array("data" => l7he_data($e16)));
for ($i = 0; $i < 16; $i++) {
	check(has($html16, 'name="eon[' . $i . ']"'), "16: eon[" . $i . "]");
}

/* delete value=1 */
check(has($html1, 'name="delete_exception" value="1"'), "delete: value=1");

/* legado host/cidr singular */
$html_leg = l7he_render(array("data" => l7he_data($e_legacy)));
check(has($html_leg, "legacy"), "legado: id");

/* legado: valores no editor */
$html_leg_edit = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e_legacy),
));
check(has($html_leg_edit, "192.0.2.10"), "legado edit: host no textarea");
check(has($html_leg_edit, "192.0.2.0/24"), "legado edit: cidr no textarea");

/* quatro acoes + prioridades extremas */
$e_actions = array(l7he_exc("prio0", array(
	"hosts" => array("10.0.0.10"),
	"priority" => 0,
	"action" => "block",
)));
$e_max = array(l7he_exc("prio99999", array(
	"hosts" => array("10.0.0.11"),
	"priority" => 99999,
	"action" => "tag",
)));
$html_prio0 = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e_actions),
));
check(has($html_prio0, 'name="edit_action"'), "acoes: select edit_action");
check(has($html_prio0, 'value="block"'), "acoes: block");
check(has($html_prio0, 'value="allow"'), "acoes: allow");
check(has($html_prio0, 'value="monitor"'), "acoes: monitor");
check(has($html_prio0, 'value="tag"'), "acoes: tag");
check(has($html_prio0, 'value="0"'), "prioridade: 0 no editor");
$html_prio99999 = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e_max),
));
check(has($html_prio99999, 'value="99999"'), "prioridade: 99999 no editor");
check(has($html_prio99999, 'value="tag"'), "acoes: tag seleccionada");

/* interfaces marcadas (ifid lan) */
$e_ifaces = array(l7he_exc("if-test", array(
	"hosts" => array("10.0.0.20"),
	"interfaces" => array("lan"),
)));
$html_if = l7he_render(array(
	"get" => array("edit" => "0"),
	"data" => l7he_data($e_ifaces),
));
check(has($html_if, 'name="edit_exc_ifaces[]" value="lan"'), "interfaces: checkbox lan");
check(preg_match('/name="edit_exc_ifaces\[\]" value="lan"[^>]*checked/', $html_if) === 1, "interfaces: lan marcada");
check(has($html_if, "(em0)"), "interfaces: real em0 visivel");

/* help neutralizado na area geral */
check(has($html_new, "Hosts (IP)"), "help: label Hosts (IP)");
check(has($html_new, "Um IP por linha. Pode combinar com CIDRs."), "help: texto hosts");
check(has($html_new, "Um CIDR por linha."), "help: texto cidr new");
check(has($html_edit, "Um CIDR por linha. Ex.: 192.168.0.0/24"), "help: texto cidr edit exemplo");

/* action URLs exactas */
check(has($html1, 'action="layer7_exceptions.php#l7-exceptions"'), "action: lista");
check(has($html_new, 'action="layer7_exceptions.php#l7-add-exc"'), "action: new");
check(has($html_edit, 'action="layer7_exceptions.php#l7-edit-exc"'), "action: edit");

/* XSS: id/interface escapados */
$xss_id = '<img onerror=alert(1)>';
$e_xss = array(l7he_exc($xss_id, array("hosts" => array("10.0.0.99"))));
$html_xss = l7he_render(array("data" => l7he_data($e_xss)));
check(!has($html_xss, "<img onerror"), "XSS: id escapado na lista");
check(has($html_xss, htmlspecialchars($xss_id, ENT_QUOTES, "UTF-8")), "XSS: entidade na lista");

/* erro ID invalido */
$html_bad_id = l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "id invalido!",
		"new_hosts" => "10.0.0.1",
		"new_cidrs" => "",
		"new_priority" => "500",
		"new_action" => "allow",
	),
	"data" => l7he_data($e1),
));
check(has($html_bad_id, "ID invalido"), "erro: id invalido");
check(has($html_bad_id, 'value="id invalido!"'), "erro id: valor preservado");

/* erro prioridade >99999 */
$html_bad_pri = l7he_render(array(
	"get" => array("edit" => "0"),
	"post" => array(
		"save_exception_edit" => "1",
		"edit_exception_index" => "0",
		"edit_hosts" => "10.0.0.1",
		"edit_cidrs" => "",
		"edit_priority" => "100000",
		"edit_action" => "allow",
	),
	"data" => l7he_data($e1),
));
check(has($html_bad_pri, "Prioridade invalida"), "erro: prioridade acima 99999");
check(has($html_bad_pri, 'value="100000"'), "erro prioridade: valor preservado");

/* add savefalse: campos raw, enabled off, interfaces vazio */
$html_add_savefalse = l7he_render(array(
	"get" => array("new" => "1"),
	"post" => array(
		"add_exception" => "1",
		"new_id" => "retry-raw",
		"new_hosts" => "2001:db8::1\n10.0.0.5",
		"new_cidrs" => "192.0.2.0/24",
		"new_priority" => "123",
		"new_action" => "monitor",
	),
	"data" => l7he_data($e1),
	"save_result" => false,
));
check(has($html_add_savefalse, 'id="l7-add-exc"'), "add savefalse: reabre new");
check(has($html_add_savefalse, "2001:db8::1"), "add savefalse: hosts preservados");
check(has($html_add_savefalse, "192.0.2.0/24"), "add savefalse: cidr preservado");
check(!preg_match('/name="new_enabled"[^>]*checked/', $html_add_savefalse), "add savefalse: enabled desmarcado");
check(!preg_match('/name="new_exc_ifaces\[\]"[^>]*checked/', $html_add_savefalse), "add savefalse: interfaces vazias");

/* edit savefalse */
$html_edit_savefalse = l7he_render(array(
	"get" => array("edit" => "0"),
	"post" => array(
		"save_exception_edit" => "1",
		"edit_exception_index" => "0",
		"edit_hosts" => "10.0.0.99",
		"edit_cidrs" => "",
		"edit_priority" => "42",
		"edit_action" => "block",
	),
	"data" => l7he_data($e1),
	"save_result" => false,
));
check(has($html_edit_savefalse, 'id="l7-edit-exc"'), "edit savefalse: reabre editor");
check(has($html_edit_savefalse, "10.0.0.99"), "edit savefalse: hosts preservados");
check(!preg_match('/name="edit_enabled"[^>]*checked/', $html_edit_savefalse), "edit savefalse: enabled desmarcado");

/* delete savefalse: indice reapresentado */
$html_del_fail = l7he_render(array(
	"post" => array(
		"delete_exception" => "1",
		"delete_exception_index" => "0",
	),
	"data" => l7he_data(array_merge($e1, array(l7he_exc("b", array("hosts" => array("10.0.0.2")))))),
	"save_result" => false,
));
check(has($html_del_fail, 'name="delete_exception_index"'), "delete savefalse: select presente");
check(preg_match('/name="delete_exception_index"[^>]*>\s*<option[^>]*value="0"[^>]*selected/', $html_del_fail) === 1
	|| preg_match('/<option[^>]*value="0"[^>]*selected[^>]*>/', $html_del_fail) === 1, "delete savefalse: indice 0 seleccionado");

/* lista16: envio bulk eon — todos presentes com id */
for ($i = 0; $i < 16; $i++) {
	check(has($html16, 'id="eon-exc-' . $i . '"'), "16: id eon-exc-" . $i);
}

/* ponte bookmark GET-only: ausente quando nao e consulta geral limpa */
$bridge_marker = 'id="l7-vip-bookmark-bridge"';
check(!has($html_new, $bridge_marker), "bookmark: ausente em GET new");
check(!has($html_edit, $bridge_marker), "bookmark: ausente em GET edit");
check(!has($html_err_add, $bridge_marker), "bookmark: ausente em POST erro add");
check(!has($html_err_edit, $bridge_marker), "bookmark: ausente em POST erro edit");
check(!has($html_add_savefalse, $bridge_marker), "bookmark: ausente em POST add savefalse");
check(!has($html_edit_savefalse, $bridge_marker), "bookmark: ausente em POST edit savefalse");
check(!has($html_save_off, $bridge_marker), "bookmark: ausente em POST save_exceptions savefalse");
check(!has($html_post_bad_idx, $bridge_marker), "bookmark: ausente em POST idx invalido");

/* ruído Form conhecido */
check(isset($GLOBALS["l7he_form_noise"]) && $GLOBALS["l7he_form_noise"] > 0, "form noise: conhecido registado");
check(
	empty($GLOBALS["l7he_form_noise_unexpected"]),
	"form noise: inesperado zero"
);

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS HARNESS TESTS PASSED\n";
exit(0);
