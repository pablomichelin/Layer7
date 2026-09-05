<?php
/**
 * Harness V4-B1 — biblioteca de perfis (fonte REAL + catálogo JSON).
 * Compara baseline V4-B vs candidato; navegação bookmark; modais/JS draft byte-idênticos.
 *
 *   php tests/functional/harness-policies-library/run.php
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

if (!is_file(L7HPL_BASELINE)) {
	fwrite(STDERR, "FAIL baseline em falta: " . L7HPL_BASELINE . "\n");
	exit(1);
}
if (!is_file(L7HP_CANDIDATE)) {
	fwrite(STDERR, "FAIL candidato em falta: " . L7HP_CANDIDATE . "\n");
	exit(1);
}

echo "HARNESS RENDER — layer7_policies.php biblioteca V4-B1\n";
echo "baseline: " . L7HPL_BASELINE . "\n";
echo "candidato: " . L7HP_CANDIDATE . "\n";
echo "catalogo real profiles.json; save_json=false; sem CSRF\n";
echo "Nao e pfSense, nao e appliance, nao e visual\n";

$all_profiles = l7hpl_repo_profiles_all();
$one_profile = !empty($all_profiles) ? array($all_profiles[0]) : array();
$custom_profile = array(
	"id" => "c-harness-test",
	"name" => "Custom harness",
	"group" => "Personalizados",
	"icon" => "fa-cube",
	"description" => "Perfil custom de teste",
	"ndpi_apps" => array("HTTP"),
	"ndpi_categories" => array(),
	"hosts" => array("example.com"),
);
$visible_custom = array_merge($one_profile, array($custom_profile));
$hidden_only = array(
	array(
		"id" => "c-hidden-test",
		"name" => "Oculto harness",
		"group" => "Personalizados",
		"icon" => "fa-eye-slash",
		"description" => "Hidden",
		"ndpi_apps" => array(),
		"ndpi_categories" => array(),
		"hosts" => array(),
		"hidden" => true,
	),
);
$custom_store = array(
	"custom" => array($custom_profile, $hidden_only[0]),
	"overrides" => array(
		"facebook" => array(
			"hidden" => false,
			"hosts_add" => array("extra.example"),
		),
	),
);

$active_data = l7hp_data(array(
	l7hpl_profile_policy("social", array("name" => "Social activo")),
	l7hpl_profile_policy("youtube", array("name" => "YouTube activo")),
));
$limit_data = l7hp_data(l7hpl_policies_at_limit());

$scenarios = array(
	"list-nav-ok" => array(
		"get" => array(),
		"data" => $active_data,
		"profiles" => array("visible" => $all_profiles),
		"method" => "GET",
	),
	"list-post-error" => array(
		"get" => array(),
		"post" => array("save_policies" => "1"),
		"data" => $active_data,
		"profiles" => array("visible" => $all_profiles),
		"method" => "POST",
		"input_errors" => array("Erro simulado na lista"),
	),
	"list-limit24" => array(
		"get" => array(),
		"data" => $limit_data,
		"profiles" => array("visible" => $all_profiles),
		"method" => "GET",
	),
	"list-empty-catalog" => array(
		"get" => array(),
		"data" => l7hp_data(array()),
		"profiles" => array("visible" => array()),
		"method" => "GET",
	),
	"library-full" => array(
		"get" => array("library" => "1"),
		"data" => $active_data,
		"profiles" => array("visible" => $all_profiles),
		"method" => "GET",
	),
	"library-unavail" => array(
		"get" => array("library" => "1"),
		"data" => $limit_data,
		"profiles" => array("visible" => $all_profiles),
		"method" => "GET",
	),
	"library-empty" => array(
		"get" => array("library" => "1"),
		"data" => l7hp_data(array()),
		"profiles" => array("visible" => array()),
		"method" => "GET",
	),
	"library-custom-hidden" => array(
		"get" => array("library" => "1"),
		"data" => l7hp_data(array()),
		"profiles" => array(
			"visible" => $visible_custom,
			"hidden" => $hidden_only,
			"custom" => $custom_store,
		),
		"method" => "GET",
	),
	"library-hidden-active" => array(
		"get" => array("library" => "1"),
		"data" => l7hp_data(array(
			l7hpl_profile_policy("social", array("name" => "Social activo")),
			l7hpl_profile_policy("c-hidden-active", array("name" => "Oculto activo")),
		)),
		"profiles" => array(
			"visible" => (function () use ($all_profiles) {
				$vis = array_slice($all_profiles, 0, 3);
				foreach ($all_profiles as $p) {
					if (is_array($p) && ($p["id"] ?? "") === "facebook") {
						$vis[] = $p;
						break;
					}
				}
				return $vis;
			})(),
			"hidden" => array(array(
				"id" => "c-hidden-active",
				"name" => "Oculto activo harness",
				"group" => "Personalizados",
				"icon" => "fa-eye-slash",
				"description" => "Hidden com politica",
				"ndpi_apps" => array("HTTP"),
				"ndpi_categories" => array(),
				"hosts" => array("hidden-active.example"),
				"hidden" => true,
			)),
			"custom" => array(
				"custom" => array(),
				"overrides" => array(
					"facebook" => array("hidden" => false, "hosts_add" => array("extra.example")),
				),
			),
		),
		"method" => "GET",
	),
	"profile-post-retry" => array(
		"post" => array("toggle_profile_on" => "1", "profile_id" => "invalid"),
		"data" => l7hp_data(array()),
		"profiles" => array("visible" => $one_profile),
		"method" => "POST",
		"input_errors" => array("Perfil nao encontrado."),
	),
);

function l7hpl_do_render($opts)
{
	return l7hpl_render($opts);
}

$htmls = array("baseline" => array(), "candidate" => array());
foreach (array("baseline" => L7HPL_BASELINE, "candidate" => L7HP_CANDIDATE) as $label => $src) {
	foreach ($scenarios as $sid => $base_opts) {
		$opts = $base_opts;
		$opts["source"] = $src;
		$html = l7hpl_do_render($opts);
		$htmls[$label][$sid] = $html;
		file_put_contents($out_dir . "/{$label}-{$sid}.html", $html);
		check(has($html, "<!-- L7HP_HEAD -->"), "{$label} {$sid}: fonte real");
		check(!has($html, "Fatal error") && !has($html, "Parse error"), "{$label} {$sid}: sem fatal/parse");
	}
}

$c = $htmls["candidate"];
$b = $htmls["baseline"];

/* Navegação bookmark — só candidato (baseline não tem flag nem modo library separado) */
check(l7hpl_redirect_flag($c["list-nav-ok"]) === true, "nav: GET list limpo permite redirect");
check(l7hpl_redirect_flag($c["list-post-error"]) === false, "nav: POST com erro bloqueia redirect");
check(l7hpl_redirect_flag($c["list-limit24"]) === false, "nav: limite24 bloqueia redirect");
check(l7hpl_redirect_flag($c["list-empty-catalog"]) === false, "nav: catalogo vazio bloqueia redirect");
check(has($c["list-post-error"], "Erro simulado na lista"), "nav: POST erro preserva mensagem");
check(has($c["list-nav-ok"], 'href="layer7_policies.php?library=1#l7-profiles"'),
	"nav: link GET biblioteca na lista");
check(has($c["list-nav-ok"], 'href="layer7_policies.php?library=1#l7-ra"'),
	"nav: link GET #l7-ra na lista");
check(has($c["list-nav-ok"], 'id="l7-profiles"'), "nav: ancora l7-profiles na lista");
check(has($c["list-nav-ok"], 'id="l7-ra"'), "nav: ancora l7-ra na lista");
check(!has($c["list-nav-ok"], 'id="l7ProfileSearch"'), "nav: lista sem grelha biblioteca");
	check(has($c["list-limit24"], 'id="l7-policies"'), "nav: limite24 mantem lista");
check(!has($c["list-limit24"], 'href="layer7_policies.php?library=1'),
	"nav: limite24 sem link biblioteca");

/* Vista library candidato */
check(has($c["library-full"], 'id="l7ProfileSearch"'), "library-full: pesquisa");
check(has($c["library-full"], 'for="l7ProfileSearch"') || has($c["library-full"], 'aria-label="Pesquisar perfil'),
	"library-full: label acessivel pesquisa");
check(has($c["library-full"], 'id="l7ProfileActiveOnly"'), "library-full: filtro so ligados");
check(has($c["library-full"], "<details"), "library-full: details nativos");
check(!has($c["library-full"], 'role="button"'), "library-full: summary sem role button");
check(has($c["library-full"], 'id="l7-ra"'), "library-full: ancora ra no grupo");
check(has($c["library-full"], 'action="layer7_policies.php#l7-profiles"'),
	"library-full: POST toggle sem ?library=1");
check(!preg_match('/action="[^"]*library=1/', $c["library-full"]),
	"library-full: nenhum POST com ?library=1");
check(has($c["library-unavail"], "Limite de 24 politicas atingido"),
	"library-unavail: aviso limite24");
check(!has($c["library-unavail"], "Catalogo de perfis indisponivel"),
	"library-unavail: sem mensagem catalogo vazio");
check(!has($c["library-unavail"], "Remova uma politica"),
	"library-unavail: sem instrucao de exclusao");
check(has($c["library-unavail"], "Voltar a lista"), "library-unavail: retorno lista");
check(!has($c["library-unavail"], 'id="l7ProfileSearch"'),
	"library-unavail: sem grelha quando indisponivel");
check(has($c["library-empty"], "Catalogo de perfis indisponivel"),
	"library-empty: aviso catalogo vazio");
check(!has($c["library-empty"], "Limite de 24 politicas atingido. A biblioteca"),
	"library-empty: sem mensagem limite24");
check(!has($c["library-empty"], "Remova uma politica"),
	"library-empty: sem instrucao de exclusao");
check(has($c["profile-post-retry"], 'id="l7ProfileSearch"'),
	"profile-post-retry: erro perfil mantem biblioteca");

$full_ids_c = l7hpl_profile_ids($c["library-full"]);
check(count($full_ids_c) === 105, "library-full: 105 linhas perfil (" . count($full_ids_c) . ")");
$full_ids_b = l7hpl_profile_ids($b["list-nav-ok"]);
check($full_ids_c === $full_ids_b, "library-full: mesma ordem/IDs que baseline inline");

$forms_c = l7hpl_profile_post_forms($c["library-full"]);
$forms_b = l7hpl_profile_post_forms($b["list-nav-ok"]);
check(count($forms_c) === 105, "library-full: 105 forms toggle/unhide (" . count($forms_c) . ")");
check(count($forms_c) === count($forms_b), "library-full: contagem forms baseline/candidato");
foreach ($forms_b as $i => $fb) {
	if (!isset($forms_c[$i])) {
		check(false, "library-full: form #{$i} em falta no candidato");
		continue;
	}
	$fc = $forms_c[$i];
	check($fb === $fc, "library-full: form #{$i} payload integral (" . $fb["profile_id"] . "/" . $fb["kind"] . ")");
}

$edit_c = l7hpl_edit_data($c["library-full"]);
$edit_b = l7hpl_edit_data($b["list-nav-ok"]);
check(!empty($edit_c) && $edit_c === $edit_b, "library-full: l7ProfileEditData igual baseline");

check(in_array("c-harness-test", l7hpl_profile_ids($c["library-custom-hidden"]), true),
	"library-custom-hidden: custom visivel");
check(has($c["library-custom-hidden"], "Perfis ocultos"), "library-custom-hidden: secao ocultos");
check(in_array("c-hidden-test", l7hpl_profile_ids($c["library-custom-hidden"]), true),
	"library-custom-hidden: hidden na secao");

$ha = $c["library-hidden-active"];
check(has($ha, "Perfis ocultos"), "hidden-active: secao ocultos");
check(has($ha, 'data-profile-id="c-hidden-active"'), "hidden-active: linha oculta");
check(
	preg_match('/data-profile-id="c-hidden-active"[^>]*data-saved="1"/', $ha) === 1 ||
	preg_match('/data-saved="1"[^>]*data-profile-id="c-hidden-active"/', $ha) === 1,
	"hidden-active: saved=1 na linha oculta"
);
check(has($ha, 'name="unhide_profile"') && has($ha, 'value="c-hidden-active"'),
	"hidden-active: unhide payload profile_id");
check(has($ha, "editado") || has($ha, 'label-warning'), "hidden-active: factory override facebook");

/* Draft JS byte-identico; editor migrou V4-B2b (harness-policies-edit) */
$base_src = file_get_contents(L7HPL_BASELINE);
$cand_src = file_get_contents(L7HP_CANDIDATE);
$draft_b = l7hpl_extract_modal($base_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
$draft_c = l7hpl_extract_modal($cand_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
check($draft_b !== "" && $draft_b === $draft_c, "draft JS byte-identico baseline/candidato");
check(has($c["library-full"], 'class="modal fade" id="l7ProfileEditModal"'),
	"library-full: modal editor bootstrap presente");
check(has($c["library-full"], 'id="l7ProfileModal"'), "library-full: modal opcoes presente");
check(!preg_match('/id="l7ProfileModal"[^>]*aria-hidden="true"/', $c["library-full"]),
	"library-full: modal opcoes sem aria-hidden estatico");
check(has($c["library-full"], 'href="layer7_policies.php?profile_options='),
	"library-full: link GET opcoes");
check(!has($c["library-full"], 'class="l7-modal-overlay" id="l7ProfileModal"'),
	"library-full: modal opcoes sem overlay proprio");

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
	fwrite(STDERR, "SOME POLICIES LIBRARY HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES LIBRARY HARNESS TESTS PASSED\n";
exit(0);
