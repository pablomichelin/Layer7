<?php
/**
 * BG-174 / GUI3 — gate estático da view de Policies (modos exclusivos).
 *
 * Lê layer7_policies.php como texto. Não carrega guiconfig nem layer7.inc.
 * Não executa daemon, PF nem handlers.
 *
 *   php tests/functional/test_policies_native_view.php
 *   php tests/functional/test_policies_native_view.php /caminho/layer7_policies.php
 */
$root = dirname(__DIR__, 2);
$path = (isset($argv[1]) && is_string($argv[1]) && $argv[1] !== "")
    ? $argv[1]
    : $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php";

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
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("policies")' => 'layer7_render_tabs("policies")',
	'$l7_policy_mode = "list"' => 'modo list',
	'$l7_policy_mode = "edit"' => 'modo edit',
	'$l7_policy_mode = "view"' => 'modo view',
	'$l7_policy_mode = "new"' => 'modo new',
	'$l7_policy_mode = "library"' => 'modo library',
	'$l7_policy_mode = "profile_options"' => 'modo profile_options',
	'$_GET["profile_options"]' => 'deep link ?profile_options=',
	'$l7_profile_post_retry' => 'retry POST perfil forca library',
	'$l7_legacy_library_redirect_ok' => 'flag redirect bookmark GET list',
	'l7LegacyLibraryRedirectOk' => 'JS guard redirect bookmark',
	'if ($l7_policy_mode === "list")' => 'render list exclusivo',
	'if ($l7_policy_mode === "library")' => 'render library exclusivo',
	'if ($l7_policy_mode === "view")' => 'render view exclusivo',
	'if ($l7_policy_mode === "new")' => 'render new exclusivo',
	'setAction("layer7_policies.php#l7-edit")' => 'POST edit fragmento #l7-edit (Form)',
	'setAction("layer7_policies.php#l7-add")' => 'POST add fragmento #l7-add (Form)',
	'action="layer7_policies.php#l7-policies"' => 'POST lista/modal fragmento #l7-policies',
	'action="layer7_policies.php#l7-profiles"' => 'POST perfil/toggle fragmento #l7-profiles',
	'$_POST["add_profile_policy"]' => 'handler add_profile_policy',
	'$_POST["apply_profiles_batch"]' => 'handler apply_profiles_batch',
	'$_POST["toggle_profile_on"]' => 'handler toggle_profile_on',
	'$_POST["toggle_profile_off"]' => 'handler toggle_profile_off',
	'$_POST["unhide_profile"]' => 'handler unhide_profile',
	'$_POST["save_profile_edit"]' => 'handler save_profile_edit',
	'$_POST["delete_custom_profile"]' => 'handler delete_custom_profile',
	'$_POST["add_policy"]' => 'handler add_policy',
	'$_POST["save_policies"]' => 'handler save_policies',
	'$_POST["delete_policy"]' => 'handler delete_policy',
	'$_POST["save_policy_edit"]' => 'handler save_policy_edit',
	'name="save_policies"' => 'botao save_policies',
	'Form_Button("delete_policy"' => 'botao delete_policy (Form)',
	'Form_Button("save_policy_edit"' => 'botao save_policy_edit (Form)',
	'Form_Button("add_policy"' => 'botao add_policy (Form)',
	'Form_Input("new_id"' => 'campo new_id (Form)',
	'Form_Input("edit_policy_index"' => 'campo edit_policy_index (Form)',
	'Form_Select("delete_policy_index"' => 'campo delete_policy_index (Form)',
	'name="pon[' => 'lote pon[]',
	'layer7_policies.php?edit=' => 'deep link ?edit=',
	'layer7_policies.php?view=' => 'deep link ?view=',
	'layer7_policies.php?new=1' => 'deep link ?new=1',
	'layer7_policies.php?library=1' => 'deep link ?library=1',
	'<details class="' => 'grupos biblioteca details nativos',
	'id="l7-policies"' => 'ancora politicas aplicadas',
	'id="l7-profiles"' => 'ancora biblioteca',
	'table table-striped table-condensed table-hover' => 'tabela nativa da biblioteca',
	'toggle_profile_on' => 'fallback POST toggle_profile_on',
	'toggle_profile_off' => 'fallback POST toggle_profile_off',
	'l7toggleProfileDraft(this); return false;' => 'toggle JS com return false',
	'id="l7-edit"' => 'ancora edicao',
	'id="l7-add"' => 'ancora criacao',
	'IDENT=page-services-layer7-policies' => 'privilege IDENT',
	'MATCH=layer7_policies.php*' => 'privilege MATCH',
	'name="profile_id"' => 'campo profile_id',
	'"profile_action",' => 'campo profile_action (Form_Select)',
	'layer7_render_profile_options_form' => 'helper form opcoes perfil',
	'id="l7-profile-options"' => 'ancora vista opcoes dedicada',
	'function l7showProfileModal' => 'JS modal opcoes progressivo',
	'!hasModalApi' => 'JS fallback href sem plugin modal',
	'$_POST["enable_ids"]' => 'campo enable_ids batch',
	'layer7_render_profile_edit_form' => 'helper form editor perfil',
	'alert alert-info' => 'lead nativo alert-info',
	'text-center text-muted' => 'credito nativo no rodape',
	'https://www.systemup.inf.br' => 'URL credito Systemup',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'<style' => '<style',
	'style=' => 'atributo style=',
	'layer7-page' => 'layer7-page',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'layer7_render_footer()' => 'layer7_render_footer()',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

check(preg_match('/Form_Input\s*\(\s*"edit_profile_id"/', $src) === 1,
    "preserva campo edit_profile_id (Form_Input nativo)");
check(preg_match('/setAttribute\s*\(\s*"id"\s*,\s*"l7EditProfileId"\s*\)/', $src) === 1,
    "preserva id DOM l7EditProfileId no editor");

$idx_list = strpos($src, '<?php if ($l7_policy_mode === "list") { ?>');
$idx_library = strpos($src, '<?php if ($l7_policy_mode === "library") { ?>');
$idx_view = strpos($src, '<?php if ($l7_policy_mode === "view") { ?>');
$idx_new = strpos($src, '<?php if ($l7_policy_mode === "new") { ?>');
$idx_edit = strpos($src, '<?php if ($l7_policy_mode === "edit") { ?>');
$idx_add_form = strpos($src, 'Form_Button("add_policy"');
$idx_edit_form = strpos($src, 'Form_Button("save_policy_edit"');

check($idx_list !== false && $idx_library !== false && $idx_view !== false
    && $idx_list < $idx_library && $idx_library < $idx_view,
    "lista, biblioteca e view renderizam em ordem exclusiva");
check($idx_new !== false && $idx_add_form !== false && $idx_new < $idx_add_form,
    "form add_policy esta dentro do modo new");
check($idx_edit !== false && $idx_edit_form !== false && $idx_edit < $idx_edit_form,
    "form save_policy_edit esta dentro do modo edit");
check(!preg_match('/action="[^"]*library=1/', $src),
    "nenhum POST com ?library=1 no action");
check(!preg_match('/id="l7ProfileModal"[^>]*aria-hidden="true"/', $src),
    "modal opcoes sem aria-hidden=true estatico");
check(has_str($src, 'id="l7ProfileModal" tabindex="-1" role="dialog" aria-labelledby="l7ProfileModalTitle"'),
    "modal opcoes role dialog e aria-labelledby");

$forbidden = array(
	'l7-kpi' => 'l7-kpi',
	'l7-profile-icon-ios' => 'l7-profile-icon-ios',
	'l7-profiles-grid' => 'l7-profiles-grid',
	'l7-switch-track' => 'l7-switch-track',
	'l7-switch-on' => 'l7-switch-on',
	'l7-switch-off' => 'l7-switch-off',
	'l7-switch' => 'l7-switch',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

if ($fail) {
	fwrite(STDERR, "SOME POLICIES NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES NATIVE VIEW TESTS PASSED\n";
exit(0);
