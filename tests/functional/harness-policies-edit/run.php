<?php
/**
 * Harness V4-B2b — editor/criação de perfil (FormData / modal Bootstrap).
 *
 *   php tests/functional/harness-policies-edit/run.php
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

echo "HARNESS RENDER — layer7_policies.php editor V4-B2b\n";
echo "baseline: " . L7HPE_BASELINE . "\n";
echo "candidato: " . L7HP_CANDIDATE . "\n";

$scenarios = l7hpe_scenarios();
$htmls = array("baseline" => array(), "candidate" => array());
foreach (array("baseline" => L7HPE_BASELINE, "candidate" => L7HP_CANDIDATE) as $label => $src) {
	foreach ($scenarios as $sid => $base_opts) {
		$opts = $base_opts;
		$opts["source"] = $src;
		$html = l7hpe_render($opts);
		$htmls[$label][$sid] = $html;
		file_put_contents($out_dir . "/{$label}-{$sid}.html", $html);
		check(has($html, "<!-- L7HP_HEAD -->"), "{$label} {$sid}: fonte real");
		check(!has($html, "Fatal error") && !has($html, "Parse error"), "{$label} {$sid}: sem fatal/parse");
	}
}

$c = $htmls["candidate"];
$b = $htmls["baseline"];

$core_names = array(
	"edit_profile_id", "edit_profile_is_new", "edit_profile_name",
	"edit_profile_description", "edit_profile_icon", "edit_profile_hosts",
	"edit_profile_hidden", "save_profile_edit", "delete_custom_profile",
);

/* Modal Bootstrap + links progressivos */
check(has($c["library-links"], 'class="modal fade" id="l7ProfileEditModal"'),
	"candidato: bootstrap modal editor");
check(!preg_match('/id="l7ProfileEditModal"[^>]*aria-hidden="true"/', $c["library-links"]),
	"candidato: modal editor sem aria-hidden estatico");
check(has($c["library-links"], 'role="dialog" aria-labelledby="l7ProfileEditModalTitle"'),
	"candidato: modal editor role/labelledby");
check(has($c["library-links"], 'href="layer7_policies.php?profile_edit='),
	"candidato: link GET profile_edit");
check(has($c["library-links"], 'href="layer7_policies.php?profile_new=1"'),
	"candidato: link GET profile_new");
check(!has($c["library-links"], 'class="l7-modal-overlay" id="l7ProfileEditModal"'),
	"candidato: sem overlay proprio no editor");

$lib_form_c = l7hpe_extract_edit_form($c["library-links"]);
$lib_form_b = l7hpe_extract_edit_form($b["library-links"]);
check($lib_form_c !== "" && $lib_form_b !== "", "library: form editor modal baseline/candidato");
check(has($lib_form_c, 'id="l7ProfileEditForm"'), "library: form id contrato");
check(has($lib_form_c, "l7confirmProfileEditSave"), "library: onsubmit contrato");

/* Catalogo completo no modal biblioteca (baseline vs candidato) */
$apps_b = l7hpe_checkbox_values($lib_form_b, "edit_profile_apps[]");
$apps_c = l7hpe_checkbox_values($lib_form_c, "edit_profile_apps[]");
$cats_b = l7hpe_checkbox_values($lib_form_b, "edit_profile_cats[]");
$cats_c = l7hpe_checkbox_values($lib_form_c, "edit_profile_cats[]");
check(count($apps_c) > 0 && $apps_b === $apps_c, "library: catalogo apps completo paridade (" . count($apps_c) . ")");
check(count($cats_c) > 0 && $cats_b === $cats_c, "library: catalogo cats completo paridade (" . count($cats_c) . ")");

/* Vista GET dedicada (so candidato — baseline sem profile_edit) */
check(has($c["edit-custom-get"], 'id="l7-profile-edit"'), "edit-custom-get: vista dedicada");
check(has($c["edit-custom-get"], 'href="layer7_policies.php?library=1#l7-profiles"'),
	"edit-custom-get: voltar biblioteca");
check(has($c["edit-invalid"], "Perfil nao encontrado"), "edit-invalid: mensagem sem formulario");
check(!has(l7hpe_extract_edit_form($c["edit-invalid"]), 'name="save_profile_edit"'),
	"edit-invalid: sem formulario");

/* Guards biblioteca */
check(has($c["edit-limit24"], "Limite de 24 politicas"), "edit-limit24: guarda limite");
check(has($c["edit-empty-catalog"], "Catalogo de perfis indisponivel"), "edit-empty-catalog: guarda catalogo");

/* Factory GET pagina candidata */
$inv_factory_c = l7hpe_form_inventory(l7hpe_extract_edit_form($c["edit-factory-get"]));
foreach ($core_names as $cn) {
	check(in_array($cn, $inv_factory_c["names"], true), "factory candidato: {$cn}");
}
check($inv_factory_c["action"] === "layer7_policies.php#l7-policies", "factory: action POST");
check($inv_factory_c["method"] === "post", "factory: method post");
check(
	!preg_match('/name="edit_profile_name"[^>]*\bdisabled\b/i', l7hpe_extract_edit_form($c["edit-factory-get"])),
	"factory-get: campos fabrica nao disabled"
);

/* Custom GET */
$custom_form = l7hpe_extract_edit_form($c["edit-custom-get"]);
$inv_custom = l7hpe_form_inventory($custom_form);
check(in_array("edit_profile_name", $inv_custom["names"], true), "custom: edit_profile_name");
check(strpos($inv_custom["fields"]["values"]["edit_profile_name"] ?? "", "<script>") !== false,
	"custom: nome hostil preservado no value");
check(
	l7hpe_checked_values($custom_form, "edit_profile_apps[]") === array("AmazonVideo"),
	"custom: app AmazonVideo checked"
);

/* Novo GET */
$inv_new = l7hpe_form_inventory(l7hpe_extract_edit_form($c["edit-new-get"]));
check(($inv_new["fields"]["values"]["edit_profile_is_new"] ?? "") === "1", "new: is_new=1");
check(($inv_new["fields"]["values"]["edit_profile_id"] ?? "x") === "", "new: id vazio");
check(($inv_new["fields"]["values"]["edit_profile_icon"] ?? "") === "fa-cube", "new: icon fa-cube");
check(
	preg_match('/id="l7EditProfileDeleteBtn"[^>]*\bhidden\b/i', l7hpe_extract_edit_form($c["edit-new-get"])) === 1 &&
	preg_match('/id="l7EditProfileDeleteBtn"[^>]*class="[^"]*\bhidden\b/i', l7hpe_extract_edit_form($c["edit-new-get"])) === 1,
	"new: botao apagar hidden atributo e classe nativa"
);

/* Conectado */
check(has($c["edit-connected"], "ligado"), "edit-connected: aviso conectado");
check(has($c["edit-connected"], "var l7ProfileEditData"), "edit-connected-get: l7ProfileEditData na pagina");
check(
	preg_match('/"connected"\s*:\s*true/', $c["edit-connected"]) === 1,
	"edit-connected-get: connected true no mapa salvo"
);
check(has($c["library-connected"], "var l7ProfileEditData"), "library-connected: mapa JS");
check(
	preg_match('/"connected"\s*:\s*true/', $c["library-connected"]) === 1,
	"library-connected: perfis ligados no mapa"
);
check(has($c["post-error-connected"], "var l7ProfileEditData"), "post-error-connected: l7ProfileEditData na pagina");
check(
	preg_match('/"connected"\s*:\s*true/', $c["post-error-connected"]) === 1,
	"post-error-connected: connected do config salvo"
);

/* POST erro — icon vazio e listas limpas */
$empty_form = l7hpe_extract_edit_form($c["post-error-empty-fields"]);
$inv_empty = l7hpe_form_inventory($empty_form);
check(($inv_empty["fields"]["values"]["edit_profile_icon"] ?? "x") === "",
	"post-error-empty-fields: icon vazio preservado");
check(($inv_empty["fields"]["values"]["edit_profile_name"] ?? "x") === "",
	"post-error-empty-fields: nome vazio preservado");
check(l7hpe_checked_values($empty_form, "edit_profile_apps[]") === array(),
	"post-error-empty-fields: apps desmarcados");
check(l7hpe_checked_values($empty_form, "edit_profile_cats[]") === array(),
	"post-error-empty-fields: cats desmarcados");

/* POST hostile textarea */
$hostile_form = l7hpe_extract_edit_form($c["post-error-hostile"]);
$inv_hostile = l7hpe_form_inventory($hostile_form);
check(
	strpos($inv_hostile["fields"]["values"]["edit_profile_hosts"] ?? "", "</textarea><script>") !== false,
	"post-error-hostile: hosts com fecho textarea/script preservado"
);
check(!has($hostile_form, "</textarea><script>"),
	"post-error-hostile: HTML nao fecha textarea com script");

/* Estado visual sem style= inline no form migrado */
$factory_form = l7hpe_extract_edit_form($c["edit-factory-get"]);
check(strpos($factory_form, "style=") === false, "factory-get: form sem style= inline");
check(
	preg_match('/class="[^"]*l7-edit-custom-only[^"]*\bhidden\b[^"]*"[^>]*\bhidden\b/i', $factory_form) === 1,
	"factory-get: custom-only com hidden atributo e classe nativa"
);
check(
	preg_match('/id="l7EditProfileDeleteBtn"[^>]*\bhidden\b/i', $factory_form) === 1 &&
	preg_match('/id="l7EditProfileDeleteBtn"[^>]*class="[^"]*\bhidden\b/i', $factory_form) === 1,
	"factory-get: apagar oculto atributo e classe nativa"
);
check(
	preg_match('/id="l7EditProfileDeleteBtn"[^>]*\bhidden\b/i', $custom_form) !== 1,
	"custom-get: apagar visivel na pagina"
);

/* POST erro — valores preservados */
$err_form = l7hpe_extract_edit_form($c["post-error-save"]);
$inv_err = l7hpe_form_inventory($err_form);
check(has($c["post-error-save"], "Host invalido"), "post-error-save: mensagem erro");
check(strpos($inv_err["fields"]["values"]["edit_profile_hosts"] ?? "", "not a valid host") !== false,
	"post-error-save: hosts invalidos preservados");
check(l7hpe_checked_values($err_form, "edit_profile_cats[]") === array("Advertisement"),
	"post-error-save: cats conforme POST");

$del_form = l7hpe_extract_edit_form($c["post-error-delete-connected"]);
check(has($c["post-error-delete-connected"], "Desligue o perfil"), "post-error-delete: mensagem");
check(strpos(l7hpe_form_inventory($del_form)["fields"]["values"]["edit_profile_name"] ?? "", "<script>") !== false,
	"post-error-delete: nome preservado");

/* Perfil oculto — GET dedicada */
check(has($c["edit-hidden"], 'id="l7-profile-edit"'), "edit-hidden: vista dedicada perfil oculto");
check(has($c["edit-hidden"], "var l7ProfileEditData"), "edit-hidden: confirm data na pagina");

/* Filtros com label e limpar */
check(has($lib_form_c, 'id="l7EditAppsFilter"'), "modal: filtro apps com id");
check(has($lib_form_c, 'id="l7EditCatsFilter"'), "modal: filtro cats com id");
check(has($lib_form_c, "l7clearEditFilter"), "modal: acao limpar filtro");
check(has($lib_form_c, 'for="l7EditAppsFilter"'), "modal: label filtro apps");
check(has($lib_form_c, 'for="l7EditCatsFilter"'), "modal: label filtro cats");
check(has($lib_form_c, 'maxlength="120"'), "modal: maxlength nome");
check(has($lib_form_c, 'maxlength="400"'), "modal: maxlength descricao");
check(has($lib_form_c, 'maxlength="45"'), "modal: maxlength icon");

/* Handlers + draft JS byte-identicos ao baseline B2b */
$base_src = file_get_contents(L7HPE_BASELINE);
$cand_src = file_get_contents(L7HP_CANDIDATE);
$handler_start = strpos($base_src, 'if ($_POST["add_profile_policy"] ?? false) {');
$handler_marker = '$data = layer7_load_or_default();' . "\n" . '$policies = isset($data["layer7"]["policies"])';
$handler_end = strpos($base_src, $handler_marker, $handler_start);
check($handler_start !== false && $handler_end !== false, "handlers: marcadores encontrados");
if ($handler_start !== false && $handler_end !== false) {
	$base_handlers = substr($base_src, $handler_start, $handler_end - $handler_start);
	$cand_handlers = substr($cand_src, $handler_start, $handler_end - $handler_start);
	check($base_handlers === $cand_handlers, "handlers byte-identicos baseline/candidato");
}
$draft_b = l7hpl_extract_modal($base_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
$draft_c = l7hpl_extract_modal($cand_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
check($draft_b !== "" && $draft_b === $draft_c, "draft JS byte-identico baseline/candidato");

/* JSON_HEX no l7ProfileEditData (perfil hostil na biblioteca) */
check(preg_match('/var l7ProfileEditData = (\{[\s\S]*?\});/', $c["library-links"], $jm) === 1,
	"library: l7ProfileEditData presente");
if (!empty($jm[1])) {
	check(stripos($jm[1], "\\u003c") !== false, "library: JSON_HEX tag escapada no mapa");
	check(strpos($jm[1], "\\u0026") !== false, "library: JSON_HEX ampersand escapado no mapa");
}

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
	fwrite(STDERR, "SOME POLICIES EDIT HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES EDIT HARNESS TESTS PASSED\n";
exit(0);
