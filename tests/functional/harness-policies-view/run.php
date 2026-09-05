<?php
/**
 * Harness V4-A — render real das vistas list/new/edit/view.
 * layer7_load_profiles() = []. Biblioteca fica para V4-B.
 * Compara campos do baseline capturado com o candidato.
 *
 *   php tests/functional/harness-policies-view/run.php
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

if (!is_file(L7HP_BASELINE)) {
	fwrite(STDERR, "FAIL baseline em falta: " . L7HP_BASELINE . "\n");
	exit(1);
}
if (!is_file(L7HP_CANDIDATE)) {
	fwrite(STDERR, "FAIL candidato em falta: " . L7HP_CANDIDATE . "\n");
	exit(1);
}

echo "HARNESS RENDER — layer7_policies.php list/new/edit/view\n";
echo "baseline: " . L7HP_BASELINE . "\n";
echo "candidato: " . L7HP_CANDIDATE . "\n";
echo "Form_* pinados 9363ac5b; profiles=[]; save_json=false; sem CSRF\n";
echo "Nao e pfSense, nao e appliance, nao e visual\n";

$g_lab = l7hp_groups();
$p1 = array(l7hp_policy("p-one", array("name" => "Uma", "priority" => 5, "action" => "allow")));
$p_full = array(l7hp_full_policy());
$p_xss = array(l7hp_xss_policy());
$p24 = array();
for ($i = 0; $i < 24; $i++) {
	$p24[] = l7hp_policy("p-" . $i, array("name" => "Pol " . $i, "priority" => $i));
}
$data0 = l7hp_data(array(), $g_lab);
$data1 = l7hp_data($p1, $g_lab);
$data_full = l7hp_data($p_full, $g_lab);
$data_xss = l7hp_data($p_xss, $g_lab);
$data24 = l7hp_data($p24, $g_lab);

$scenarios = array(
	"list-0" => array("get" => array(), "data" => $data0),
	"list-1" => array("get" => array(), "data" => $data1),
	"list-24" => array("get" => array(), "data" => $data24),
	"edit-0" => array("get" => array("edit" => "0"), "data" => $data_full),
	"edit-invalid" => array("get" => array("edit" => "9"), "data" => $data1),
	"view-0" => array("get" => array("view" => "0"), "data" => $data_full),
	"view-invalid" => array("get" => array("view" => "9"), "data" => $data1),
	"new" => array("get" => array("new" => "1"), "data" => $data1),
	"new-limit" => array("get" => array("new" => "1"), "data" => $data24),
	"edit-xss" => array("get" => array("edit" => "0"), "data" => $data_xss),
	"view-xss" => array("get" => array("view" => "0"), "data" => $data_xss),
	"new-retry" => array(
		"post" => array(
			"add_policy" => "1",
			"new_id" => "bad id",
			"new_name" => "Nome digitado",
			"new_priority" => "77",
			"new_action" => "tag",
			"new_src_hosts" => "192.0.2.10",
			"new_src_cidrs" => "",
			"new_match_hosts" => "example.com",
			"new_tag_table" => "minha_tabela",
			"new_ndpi_apps" => array("HTTP"),
			"new_ifaces" => array("lan"),
			"new_groups" => array("lab"),
			"new_src_exclude_groups" => array(),
			"new_sched_mon" => "1",
			"new_sched_start" => "09:00",
			"new_sched_end" => "17:00",
		),
		"data" => $data1,
	),
	"edit-retry" => array(
		"get" => array("edit" => "0"),
		"post" => array(
			"save_policy_edit" => "1",
			"edit_policy_index" => "0",
			"edit_name" => str_repeat("x", 161),
			"edit_priority" => "3",
			"edit_action" => "allow",
			"edit_src_hosts" => "",
			"edit_src_cidrs" => "198.51.100.0/24",
			"edit_match_hosts" => "cleared.example",
			"edit_tag_table" => "",
			"edit_ndpi_apps" => array(),
			"edit_ifaces" => array(),
			"edit_groups" => array(),
			"edit_src_exclude_groups" => array(),
		),
		"data" => $data_full,
	),
	"edit-ad-noid" => array(
		"get" => array("edit" => "0"),
		"data" => $data_full,
		"identity" => false,
	),
	"edit-ad-noid-retry" => array(
		"get" => array("edit" => "0"),
		"post" => array(
			"save_policy_edit" => "1",
			"edit_policy_index" => "0",
			"edit_name" => str_repeat("x", 161),
			"edit_ad_users" => "",
			"edit_ad_groups" => "",
		),
		"data" => $data_full,
		"identity" => false,
	),
	"edit-csv" => array(
		"get" => array("edit" => "0"),
		"data" => $data_full,
		"ndpi" => array("protocols" => array(), "categories" => array()),
	),
	"new-csv" => array(
		"get" => array("new" => "1"),
		"data" => $data1,
		"ndpi" => array("protocols" => array(), "categories" => array()),
	),
);

$htmls = array("baseline" => array(), "candidate" => array());
foreach (array("baseline" => L7HP_BASELINE, "candidate" => L7HP_CANDIDATE) as $label => $src) {
	foreach ($scenarios as $sid => $opts) {
		$opts["source"] = $src;
		$html = l7hp_render($opts);
		$htmls[$label][$sid] = $html;
		file_put_contents($out_dir . "/{$label}-{$sid}.html", $html);
		check(has($html, "<!-- L7HP_HEAD -->"), "{$label} {$sid}: fonte real (head stub)");
		check(!has($html, "Fatal error") && !has($html, "Parse error"), "{$label} {$sid}: sem fatal/parse");
	}
}

$c = $htmls["candidate"];
$b = $htmls["baseline"];

check(has($c["list-0"], "Nenhuma politica cadastrada"), "list-0: vazio");
check(has($c["list-0"], 'id="l7-add"'), "list-0: ancora l7-add");
check(has($c["list-0"], "layer7_policies.php?new=1"), "list-0: link criar");
check(has($c["list-0"], 'id="l7-policies"'), "list-0: ancora lista");
check(!has($c["list-0"], 'name="edit_name"'), "list-0: sem form edicao");
check(!has($c["list-0"], 'name="new_id"'), "list-0: sem form criacao");
/* Biblioteca/modais = V4-B; nao emitir PASS tautologico sobre profiles=[] */

check(has($c["list-1"], "p-one"), "list-1: id");
check(has($c["list-1"], "Uma"), "list-1: nome");
check(has($c["list-1"], 'name="pon[0]"'), "list-1: pon[0]");
check(has($c["list-1"], 'id="pon_0"'), "list-1: id pon acessivel");
check(has($c["list-1"], 'aria-label="Politica activa: Uma"') || has($c["list-1"], 'for="pon_0"'),
	"list-1: nome acessivel pon");
check(has($c["list-1"], 'name="save_policies"'), "list-1: save_policies");
check(has($c["list-1"], 'name="delete_policy"'), "list-1: delete_policy");
check(has($c["list-1"], 'name="delete_policy_index"'), "list-1: Form_Select remover");
check(has($c["list-1"], 'id="delete_policy_index"'), "list-1: id select remover");
check(!has($c["list-1"], 'id="l7-delete-policy"'), "list-1: remocao visivel sem collapse");
check(has($c["list-1"], "layer7_policies.php?edit=0"), "list-1: ?edit=0");
check(has($c["list-1"], "layer7_policies.php?view=0"), "list-1: ?view=0");
check(has($c["list-1"], 'action="layer7_policies.php#l7-policies"'), "list-1: POST #l7-policies");

check(has($c["list-24"], "p-0") && has($c["list-24"], "p-23"), "list-24: todas");
check(substr_count($c["list-24"], 'name="pon[') === 24, "list-24: 24 pon[]");

check(has($c["edit-0"], 'id="l7-edit"'), "edit-0: ancora");
check(has($c["edit-0"], 'name="edit_name"'), "edit-0: nome");
check(has($c["edit-0"], 'name="edit_priority"'), "edit-0: prioridade");
check(has($c["edit-0"], 'name="edit_action"'), "edit-0: acao");
check(has($c["edit-0"], 'name="edit_ifaces[]"'), "edit-0: ifaces");
check(has($c["edit-0"], 'name="edit_src_hosts"'), "edit-0: src_hosts");
check(has($c["edit-0"], 'name="edit_src_cidrs"'), "edit-0: src_cidrs");
check(has($c["edit-0"], 'name="edit_ad_users"'), "edit-0: ad_users");
check(has($c["edit-0"], 'name="edit_ad_groups"'), "edit-0: ad_groups");
check(has($c["edit-0"], 'name="edit_groups[]"'), "edit-0: groups");
check(has($c["edit-0"], 'name="edit_src_exclude_groups[]"'), "edit-0: excl groups");
check(has($c["edit-0"], 'name="edit_src_exclude_cidrs"'), "edit-0: excl cidrs");
check(has($c["edit-0"], "CIDRs excluidos") || has($c["edit-0"], 'for="edit_src_exclude_cidrs"'),
	"edit-0: label excl cidrs");
check(preg_match('/name="edit_enabled"[^>]*value="1"/', $c["edit-0"]), "edit-0: Form_Checkbox enabled value=1");
check(has($c["edit-0"], 'name="edit_match_hosts"'), "edit-0: hosts");
check(has($c["edit-0"], 'name="edit_ndpi_apps[]"'), "edit-0: apps");
check(has($c["edit-0"], 'name="edit_ndpi_category[]"'), "edit-0: cats");
check(has($c["edit-0"], 'name="edit_tag_table"'), "edit-0: tag_table");
check(has($c["edit-0"], 'name="edit_sched_mon"'), "edit-0: horario");
check(has($c["edit-0"], 'name="edit_enabled"'), "edit-0: enabled");
check(has($c["edit-0"], 'name="edit_scope_global"'), "edit-0: scope");
check(has($c["edit-0"], 'name="edit_quarantine_origin"'), "edit-0: quarantine");
check(has($c["edit-0"], 'name="save_policy_edit"'), "edit-0: guardar");
check(has($c["edit-0"], 'name="edit_policy_index"'), "edit-0: index");
check(has($c["edit-0"], 'id="edit_ifaces_list"'), "edit-0: id JS ifaces");
check(has($c["edit-0"], 'id="edit_apps_list"'), "edit-0: id JS apps");
check(has($c["edit-0"], 'id="edit_apps_list_filter"'), "edit-0: filtro apps acessivel");
check(has($c["edit-0"], 'id="edit_cats_list_filter"'), "edit-0: filtro cats acessivel");
check(has($c["edit-0"], 'for="edit_apps_list_filter"'), "edit-0: label for filtro apps");
check(has($c["edit-0"], 'id="edit_sched_start"'), "edit-0: id horario inicio");
check(has($c["edit-0"], 'id="edit_sched_end"'), "edit-0: id horario fim");
check(has($c["edit-0"], 'for="edit_sched_start"'), "edit-0: label for horario inicio");
check(has($c["edit-0"], 'for="edit_sched_end"'), "edit-0: label for horario fim");
check(!preg_match('/id="edit_groups_list"\s+style="/', $c["edit-0"]), "edit-0: grupos sem style inline");
check(!preg_match('/id="edit_apps_list"\s+style="/', $c["edit-0"]), "edit-0: apps sem style inline");
check(has($c["edit-0"], 'id="edit_cats_list"'), "edit-0: id JS cats");
check(has($c["edit-0"], 'id="edit_groups_list"'), "edit-0: id JS groups");
check(has($c["edit-0"], 'action="layer7_policies.php#l7-edit"'), "edit-0: POST #l7-edit");
check(!has($c["edit-0"], 'id="l7-policies"') || !has($c["edit-0"], 'name="pon[0]"'), "edit-0: sem lote da lista");
check(!has($c["edit-0"], 'name="new_id"'), "edit-0: sem criacao");
check(has($c["edit-0"], "Politica lab") || has($c["edit-0"], 'value="Politica lab"'), "edit-0: valor nome");
check(has($c["edit-0"], "p-lab-001"), "edit-0: id imutavel");

check(has($c["edit-invalid"], 'id="l7-policies"'), "edit invalido: cai na lista");
check(!has($c["edit-invalid"], 'name="save_policy_edit"'), "edit invalido: sem editor");

check(has($c["view-0"], "Listas da politica") || has($c["view-0"], "p-lab-001"), "view-0: detalhe");
check(has($c["view-0"], "p-lab-001"), "view-0: id");
check(has($c["view-0"], "Politica lab"), "view-0: nome");
check(has($c["view-0"], "block"), "view-0: acao");
check(has($c["view-0"], "em1"), "view-0: iface");
check(has($c["view-0"], "YouTube"), "view-0: app");
check(has($c["view-0"], "Media"), "view-0: categoria");
check(has($c["view-0"], "youtube.com"), "view-0: host");
check(has($c["view-0"], "192.0.2.10"), "view-0: IP");
check(has($c["view-0"], "192.0.2.0/24"), "view-0: CIDR");
check(has($c["view-0"], "joao"), "view-0: AD user");
check(has($c["view-0"], "ti"), "view-0: AD group");
check(has($c["view-0"], "lab"), "view-0: grupo");
check(has($c["view-0"], "layer7_policies.php?edit=0"), "view-0: link editar");
check(!has($c["view-0"], 'name="save_policy_edit"'), "view-0: sem form edit");

check(has($c["view-invalid"], 'id="l7-policies"'), "view invalido: cai na lista");

check(has($c["new"], 'id="l7-add"'), "new: ancora");
check(has($c["new"], 'name="new_id"'), "new: id");
check(has($c["new"], 'name="new_name"'), "new: nome");
check(has($c["new"], 'name="new_priority"'), "new: prioridade");
check(has($c["new"], 'name="new_action"'), "new: acao");
check(has($c["new"], 'name="new_ifaces[]"'), "new: ifaces");
check(has($c["new"], 'name="new_src_hosts"'), "new: src_hosts");
check(has($c["new"], 'name="new_src_cidrs"'), "new: src_cidrs");
check(has($c["new"], 'name="new_ad_users"'), "new: ad_users");
check(has($c["new"], 'name="new_ad_groups"'), "new: ad_groups");
check(has($c["new"], 'name="new_groups[]"'), "new: groups");
check(has($c["new"], 'name="new_src_exclude_groups[]"'), "new: excl groups");
check(has($c["new"], 'name="new_src_exclude_cidrs"'), "new: excl cidrs");
check(has($c["new"], 'name="new_match_hosts"'), "new: hosts");
check(has($c["new"], 'name="new_ndpi_apps[]"'), "new: apps");
check(has($c["new"], 'name="new_ndpi_category[]"'), "new: cats");
check(has($c["new"], 'name="new_tag_table"'), "new: tag_table");
check(has($c["new"], 'name="new_sched_mon"'), "new: horario");
check(has($c["new"], 'name="new_enabled"'), "new: enabled");
check(has($c["new"], 'name="new_scope_global"'), "new: scope");
check(has($c["new"], 'name="new_quarantine_origin"'), "new: quarantine");
check(has($c["new"], 'name="add_policy"'), "new: add_policy");
check(has($c["new"], 'id="new_ifaces_list"'), "new: id JS ifaces");
check(has($c["new"], 'id="new_apps_list"'), "new: id JS apps");
check(has($c["new"], 'id="new_cats_list"'), "new: id JS cats");
check(has($c["new"], 'action="layer7_policies.php#l7-add"'), "new: POST #l7-add");
check(!has($c["new"], 'name="pon[0]"'), "new: sem lote");
check(has($c["new-limit"], "Limite de 24 politicas atingido"), "new-limit: aviso");
check(!has($c["new-limit"], 'name="new_id"'), "new-limit: sem form");

check(!has($c["edit-xss"], "<script>x</script>"), "edit-xss: nome escapado");
check(has($c["edit-xss"], "&lt;script&gt;") || has($c["edit-xss"], "pol"), "edit-xss: texto presente");
check(!has($c["view-xss"], "<script>x</script>"), "view-xss: nome escapado");
check(!has($c["view-xss"], "<img src=x onerror="), "view-xss: host escapado");

check(has($c["new-retry"], "ID invalido") || has($c["new-retry"], 'id="l7-input-errors"'), "new-retry: erro");
check(has($c["edit-retry"], "Nome demasiado longo") || has($c["edit-retry"], 'id="l7-input-errors"'), "edit-retry: erro");

check(has($c["edit-ad-noid"], "preservados sem edicao"), "edit-ad-noid: aviso AD");
check(!has($c["edit-ad-noid"], 'name="edit_ad_users"'), "edit-ad-noid: sem input ad_users");
check(!has($c["edit-ad-noid"], 'name="edit_ad_groups"'), "edit-ad-noid: sem input ad_groups");
check(has($c["edit-ad-noid-retry"], "preservados sem edicao"), "edit-ad-noid-retry: aviso AD apos erro");
check(!has($c["edit-ad-noid-retry"], 'name="edit_ad_users"'), "edit-ad-noid-retry: POST forjado nao cria input AD");
check(has($c["edit-ad-noid-retry"], "Nome demasiado longo") || has($c["edit-ad-noid-retry"], 'id="l7-input-errors"'),
	"edit-ad-noid-retry: erro noutro campo");

function l7hp_skip_name($name)
{
	return strpos($name, "profile_") === 0 || strpos($name, "edit_profile_") === 0;
}

function l7hp_compare_names_bidir($sid, $fb, $fc)
{
	foreach ($fb["names"] as $name => $type) {
		if (l7hp_skip_name($name)) {
			continue;
		}
		check(isset($fc["names"][$name]), "{$sid}: candidato tem name={$name}");
	}
	foreach ($fc["names"] as $name => $type) {
		if (l7hp_skip_name($name)) {
			continue;
		}
		check(isset($fb["names"][$name]), "{$sid}: baseline tem name={$name}");
	}
}

function l7hp_selected_equiv($fb, $fc, $name)
{
	$bs = isset($fb["selected"][$name]) ? $fb["selected"][$name] : array();
	$cs = isset($fc["selected"][$name]) ? $fc["selected"][$name] : array();
	if ($bs === $cs) {
		return true;
	}
	if (empty($bs) && !empty($cs) && isset($fb["values"][$name]) && is_array($fb["values"][$name])) {
		$opts = $fb["values"][$name];
		return count($opts) > 0 && $cs === array($opts[0]);
	}
	return false;
}

function l7hp_compare_static($sid, $fb, $fc)
{
	$submit_names = array("save_policy_edit" => true, "add_policy" => true);
	l7hp_compare_names_bidir($sid, $fb, $fc);
	foreach ($fb["values"] as $name => $val) {
		if (isset($submit_names[$name])) {
			check(isset($fc["values"][$name]) && $fc["values"][$name] !== "",
				"{$sid}: submit {$name} tem valor");
			continue;
		}
		check(isset($fc["values"][$name]), "{$sid}: candidato tem value {$name}");
		if (is_array($val) && is_array($fc["values"][$name])) {
			sort($val);
			$cv = $fc["values"][$name];
			sort($cv);
			check($val === $cv, "{$sid}: options {$name}");
		} else {
			check((string)$val === (string)$fc["values"][$name], "{$sid}: value {$name}");
		}
	}
	foreach ($fb["selected"] as $name => $sel) {
		check(isset($fc["selected"][$name]), "{$sid}: candidato tem selected {$name}");
		check(l7hp_selected_equiv($fb, $fc, $name), "{$sid}: selected {$name}");
	}
	foreach ($fb["checked"] as $name => $map) {
		check(isset($fc["checked"][$name]), "{$sid}: candidato tem checked {$name}");
		check($map === $fc["checked"][$name], "{$sid}: checked {$name}");
	}
	foreach ($fb["limits"] as $name => $lim) {
		check(isset($fc["limits"][$name]), "{$sid}: candidato tem limits {$name}");
		foreach ($lim as $k => $v) {
			$cv = isset($fc["limits"][$name][$k]) ? $fc["limits"][$name][$k] : null;
			if ($k === "required") {
				check($cv !== null, "{$sid}: required {$name}");
				continue;
			}
			check($cv === $v, "{$sid}: limit {$name}.{$k}");
		}
	}
}

function l7hp_post_scalar($post, $name)
{
	if (!array_key_exists($name, $post)) {
		return null;
	}
	return (string)$post[$name];
}

function l7hp_post_checked($post, $name)
{
	return isset($post[$name]) && (string)$post[$name] !== "" && (string)$post[$name] !== "0";
}

function l7hp_checked_on($fc, $name)
{
	if (!isset($fc["checked"][$name])) {
		return false;
	}
	foreach ($fc["checked"][$name] as $v => $on) {
		if ($on) {
			return true;
		}
	}
	return false;
}

function l7hp_checked_values($fc, $name)
{
	$got = array();
	if (!isset($fc["checked"][$name])) {
		return $got;
	}
	foreach ($fc["checked"][$name] as $v => $on) {
		if ($on) {
			$got[] = (string)$v;
		}
	}
	sort($got);
	return $got;
}

function l7hp_compare_retry($sid, $fb, $fc, $post, $expect_absent = array())
{
	l7hp_compare_names_bidir($sid, $fb, $fc);
	foreach ($fb["limits"] as $name => $lim) {
		check(isset($fc["limits"][$name]), "{$sid}: candidato tem limits {$name}");
		foreach ($lim as $k => $v) {
			if ($k === "required") {
				check(isset($fc["limits"][$name][$k]), "{$sid}: required {$name}");
				continue;
			}
			$cv = isset($fc["limits"][$name][$k]) ? $fc["limits"][$name][$k] : null;
			check($cv === $v, "{$sid}: limit {$name}.{$k}");
		}
	}
	foreach ($post as $name => $pval) {
		if (l7hp_skip_name($name) || $name === "save_policy_edit" || $name === "add_policy") {
			continue;
		}
		if (is_array($pval)) {
			$field = isset($fc["checked"][$name . "[]"]) ? $name . "[]" : $name;
			check(isset($fc["checked"][$field]) || isset($fc["names"][$name]),
				"{$sid}: candidato reconhece array POST {$name}");
			$want = array_map("strval", $pval);
			sort($want);
			check($want === l7hp_checked_values($fc, $field), "{$sid}: POST array {$name}");
			continue;
		}
		if (isset($fc["names"][$name]) && $fc["names"][$name] === "select") {
			$sel = isset($fc["selected"][$name]) ? $fc["selected"][$name] : array();
			check(in_array((string)$pval, $sel, true), "{$sid}: POST select {$name}");
			continue;
		}
		if (isset($fc["checked"][$name])) {
			check(l7hp_post_checked($post, $name) === l7hp_checked_on($fc, $name),
				"{$sid}: POST checkbox {$name}");
			continue;
		}
		check(isset($fc["values"][$name]), "{$sid}: candidato tem value {$name}");
		check((string)$fc["values"][$name] === (string)$pval, "{$sid}: POST value {$name}");
	}
	foreach ($expect_absent as $item) {
		if (strpos($item, "[]") !== false) {
			check(l7hp_checked_values($fc, $item) === array(), "{$sid}: ausencia {$item}");
			continue;
		}
		if (isset($fc["checked"][$item])) {
			check(!l7hp_checked_on($fc, $item), "{$sid}: checkbox off {$item}");
		}
	}
	check(isset($fc["values"]["save_policy_edit"]) || isset($fc["values"]["add_policy"]),
		"{$sid}: submit com valor legivel");
}

foreach (array("edit-0", "new") as $sid) {
	l7hp_compare_static($sid, l7hp_extract_fields($b[$sid]), l7hp_extract_fields($c[$sid]));
}
l7hp_compare_retry("new-retry", l7hp_extract_fields($b["new-retry"]),
	l7hp_extract_fields($c["new-retry"]), $scenarios["new-retry"]["post"], array(
		"new_enabled",
		"new_scope_global",
		"new_quarantine_origin",
		"new_src_exclude_groups[]",
	));
l7hp_compare_retry("edit-retry", l7hp_extract_fields($b["edit-retry"]),
	l7hp_extract_fields($c["edit-retry"]), $scenarios["edit-retry"]["post"], array(
		"edit_enabled",
		"edit_scope_global",
		"edit_quarantine_origin",
		"edit_sched_mon",
		"edit_sched_wed",
		"edit_ifaces[]",
		"edit_groups[]",
		"edit_src_exclude_groups[]",
		"edit_ndpi_apps[]",
		"edit_ndpi_category[]",
	));

$ec = l7hp_extract_fields($c["edit-csv"]);
$nc = l7hp_extract_fields($c["new-csv"]);
check(!isset($ec["names"]["edit_ndpi_apps[]"]), "edit-csv: sem checkbox apps");
check(isset($ec["names"]["edit_ndpi_apps_csv"]), "edit-csv: fallback csv apps");
check(!isset($ec["names"]["edit_ndpi_category[]"]), "edit-csv: sem checkbox cats");
check(isset($ec["names"]["edit_ndpi_category_csv"]), "edit-csv: fallback csv cats");
check(!isset($nc["names"]["new_ndpi_apps[]"]), "new-csv: sem checkbox apps");
check(isset($nc["names"]["new_ndpi_apps_csv"]), "new-csv: fallback csv apps");
check(!isset($nc["names"]["new_ndpi_category[]"]), "new-csv: sem checkbox cats");
check(isset($nc["names"]["new_ndpi_category_csv"]), "new-csv: fallback csv cats");

$ef = l7hp_extract_fields($c["edit-0"]);
check(isset($ef["values"]["edit_name"]) && $ef["values"]["edit_name"] === "Politica lab", "edit-0: value nome");
check(isset($ef["values"]["edit_priority"]) && (string)$ef["values"]["edit_priority"] === "10", "edit-0: value prioridade");
check(isset($ef["selected"]["edit_action"]) && in_array("block", $ef["selected"]["edit_action"], true), "edit-0: action=block");
check(!empty($ef["checked"]["edit_ifaces[]"]["lan"]), "edit-0: lan checked");
check(!empty($ef["checked"]["edit_ndpi_apps[]"]["YouTube"]), "edit-0: YouTube checked");
check(!empty($ef["checked"]["edit_ndpi_category[]"]["Media"]), "edit-0: Media checked");
check(!empty($ef["checked"]["edit_groups[]"]["lab"]), "edit-0: grupo lab");
check(!empty($ef["checked"]["edit_src_exclude_groups[]"]["vip"]), "edit-0: excl vip");
check(!empty($ef["checked"]["edit_sched_mon"]), "edit-0: sched mon");
check(empty($ef["checked"]["edit_sched_tue"]) || empty($ef["checked"]["edit_sched_tue"]["1"]), "edit-0: sched tue off");
check(!empty($ef["checked"]["edit_enabled"]), "edit-0: enabled");
check(!empty($ef["checked"]["edit_scope_global"]), "edit-0: scope");
check(!empty($ef["checked"]["edit_quarantine_origin"]), "edit-0: quarantine");
check(isset($ef["values"]["edit_src_hosts"]) && strpos($ef["values"]["edit_src_hosts"], "192.0.2.10") !== false, "edit-0: src host");
check(isset($ef["values"]["edit_match_hosts"]) && strpos($ef["values"]["edit_match_hosts"], "youtube.com") !== false, "edit-0: hosts");
check(isset($ef["limits"]["edit_priority"]["min"]) && $ef["limits"]["edit_priority"]["min"] === "0", "edit-0: min prioridade 0");
check(isset($ef["limits"]["edit_priority"]["max"]) && $ef["limits"]["edit_priority"]["max"] === "99999", "edit-0: max prioridade");
check(isset($ef["limits"]["edit_name"]["maxlength"]) && $ef["limits"]["edit_name"]["maxlength"] === "160", "edit-0: maxlength nome");
check(isset($ef["limits"]["edit_tag_table"]["pattern"]), "edit-0: pattern tag_table");

$nf = l7hp_extract_fields($c["new"]);
check(isset($nf["values"]["new_priority"]) && (string)$nf["values"]["new_priority"] === "50", "new: default prioridade 50");
check(isset($nf["selected"]["new_action"]) && (in_array("monitor", $nf["selected"]["new_action"], true) || $nf["selected"]["new_action"] === array()), "new: default monitor");
check(!empty($nf["checked"]["new_enabled"]), "new: enabled default");
check(empty($nf["checked"]["new_scope_global"]) || empty($nf["checked"]["new_scope_global"]["1"]), "new: scope off");
check(isset($nf["limits"]["new_id"]["pattern"]), "new: pattern id");
check(isset($nf["limits"]["new_id"]["required"]), "new: required new_id");
check(isset($nf["limits"]["new_id"]["maxlength"]) && $nf["limits"]["new_id"]["maxlength"] === "80", "new: maxlength id");
check(isset($nf["limits"]["new_priority"]["min"]) && $nf["limits"]["new_priority"]["min"] === "0", "new: min prioridade 0");

$nr = l7hp_extract_fields($c["new-retry"]);
$er = l7hp_extract_fields($c["edit-retry"]);
check(isset($nr["values"]["new_name"]) && $nr["values"]["new_name"] === "Nome digitado", "new-retry: nome POST (se overlay activo)");
check(isset($er["values"]["edit_priority"]) && (string)$er["values"]["edit_priority"] === "3", "edit-retry: prioridade POST (se overlay activo)");

if (!empty($GLOBALS["l7hp_form_noise_unexpected"])) {
	echo "FAIL: ruído Form inesperado\n";
	foreach ($GLOBALS["l7hp_form_noise_unexpected"] as $n) {
		echo "  {$n}\n";
	}
	$fail = 1;
} else {
	echo "PASS: sem ruído Form inesperado (noise conhecido={$GLOBALS["l7hp_form_noise"]})\n";
}

if ($fail) {
	fwrite(STDERR, "SOME POLICIES HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES HARNESS TESTS PASSED\n";
/* Gate WASM (revisao-v4a-testes.md): exit 0 sozinho nao basta — exigir marcador acima e zero FAIL: */
exit(0);
