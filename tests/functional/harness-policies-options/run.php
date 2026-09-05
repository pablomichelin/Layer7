<?php
/**
 * Harness V4-B2a — Opções de perfil (FormData / controles completos).
 *
 *   php tests/functional/harness-policies-options/run.php
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

echo "HARNESS RENDER — layer7_policies.php opcoes V4-B2a\n";
echo "baseline: " . L7HPO_BASELINE . "\n";
echo "candidato: " . L7HP_CANDIDATE . "\n";
echo "Fixture grupos/VIP controlada; nao e pfSense nem visual\n";

$scenarios = l7hpo_scenarios();
$hostile = l7hpo_hostile_post_text();

$htmls = array("baseline" => array(), "candidate" => array());
foreach (array("baseline" => L7HPO_BASELINE, "candidate" => L7HP_CANDIDATE) as $label => $src) {
	foreach ($scenarios as $sid => $base_opts) {
		$opts = $base_opts;
		$opts["source"] = $src;
		$html = l7hpo_render($opts);
		$htmls[$label][$sid] = $html;
		file_put_contents($out_dir . "/{$label}-{$sid}.html", $html);
		check(has($html, "<!-- L7HP_HEAD -->"), "{$label} {$sid}: fonte real");
		check(!has($html, "Fatal error") && !has($html, "Parse error"), "{$label} {$sid}: sem fatal/parse");
	}
}

$c = $htmls["candidate"];
$b = $htmls["baseline"];

/* Bootstrap modal + link Opções */
check(has($c["groups2-vip"], 'class="modal fade" id="l7ProfileModal"'),
	"candidato: bootstrap modal opcoes");
check(!preg_match('/id="l7ProfileModal"[^>]*aria-hidden="true"/', $c["groups2-vip"]),
	"candidato: modal opcoes sem aria-hidden estatico");
check(has($c["groups2-vip"], 'role="dialog" aria-labelledby="l7ProfileModalTitle"'),
	"candidato: modal opcoes role/labelledby");
check(has($c["groups2-vip"], 'href="layer7_policies.php?profile_options='),
	"candidato: link GET profile_options");
check(!has($c["groups2-vip"], 'class="l7-modal-overlay" id="l7ProfileModal"'),
	"candidato: sem overlay proprio no modal opcoes");
check(has($c["groups2-vip"], 'id="l7ProfileModalAdvanced"'),
	"candidato: secao avancada Form_Section");
check(has($c["groups2-vip"], 'l7ProfileModalAdvanced_panel-body'),
	"candidato: avancado expansivel aberto (collapse in)");
$opts_form_c = l7hpo_extract_options_form($c["groups2-vip"]);
$opts_form_page = l7hpo_extract_options_form($c["options-get"]);
check($opts_form_c !== "", "candidato modal: form opcoes extraido");
check($opts_form_page !== "", "candidato pagina: form opcoes extraido");
check(!has($opts_form_c, "<details"),
	"candidato modal: form opcoes sem details manual");
check(strpos($opts_form_c, "style=") === false,
	"candidato modal: form opcoes sem style=");
check(strpos($opts_form_page, "style=") === false,
	"candidato pagina: form opcoes sem style=");
check(preg_match('/<textarea[^>]*name="profile_vip_hosts"/', $opts_form_c) === 1,
	"candidato: Form_Textarea profile_vip_hosts");
check(preg_match('/<textarea[^>]*name="profile_src_cidrs"/', $opts_form_c) === 1,
	"candidato: Form_Textarea profile_src_cidrs");

/* Inventário grupos0 — sem familias de grupo */
$inv_g0_c = l7hpo_form_inventory(l7hpo_extract_options_form($c["groups0"]));
$inv_g0_b = l7hpo_form_inventory(l7hpo_extract_options_form($b["groups0"]));
check($inv_g0_c["checkbox_counts"]["profile_groups[]"] === 0,
	"groups0: sem profile_groups[] (" . $inv_g0_c["checkbox_counts"]["profile_groups[]"] . ")");
check($inv_g0_c["checkbox_counts"]["profile_vip_groups[]"] === 0,
	"groups0: sem profile_vip_groups[]");
check($inv_g0_c["checkbox_counts"]["profile_src_exclude_groups[]"] === 0,
	"groups0: sem profile_src_exclude_groups[]");
check(has($c["groups0"], "Criar grupo"), "groups0: link criar grupo");

/* groups2 + VIP preenchido — tres familias de grupo */
$inv_g2_c = l7hpo_form_inventory(l7hpo_extract_options_form($c["groups2-vip"]));
$inv_g2_b = l7hpo_form_inventory(l7hpo_extract_options_form($b["groups2-vip"]));
check($inv_g2_c["checkbox_counts"]["profile_groups[]"] === 2,
	"groups2: profile_groups[] x2");
check($inv_g2_c["checkbox_counts"]["profile_vip_groups[]"] === 2,
	"groups2: profile_vip_groups[] x2");
check($inv_g2_c["checkbox_counts"]["profile_src_exclude_groups[]"] === 2,
	"groups2: profile_src_exclude_groups[] x2");
check(in_array("profile_vip_hosts", $inv_g2_c["names"], true), "groups2: profile_vip_hosts");
check(in_array("profile_vip_cidrs", $inv_g2_c["names"], true), "groups2: profile_vip_cidrs");
check(strpos($c["groups2-vip"], "192.168.1.50") !== false, "groups2: VIP hosts preenchido");
check(strpos($c["groups2-vip"], "10.0.0.0/24") !== false, "groups2: VIP cidrs preenchido");
check(
	preg_match('/name="profile_vip_groups\[\]"[^>]*value="vip"[^>]*checked/i', $c["groups2-vip"]) === 1,
	"groups2: VIP grupo vip checked"
);

/* groups2 VIP vazio — defaults nao repovoam textarea */
$inv_g2e = l7hpo_form_inventory(l7hpo_extract_options_form($c["groups2-vip-empty"]));
check(($inv_g2e["fields"]["values"]["profile_vip_hosts"] ?? "x") === "",
	"groups2-vip-empty: vip hosts vazio");
check(($inv_g2e["fields"]["values"]["profile_vip_cidrs"] ?? "x") === "",
	"groups2-vip-empty: vip cidrs vazio");
check(
	!preg_match('/name="profile_vip_groups\[\]"[^>]*checked/i', $c["groups2-vip-empty"]),
	"groups2-vip-empty: vip grupos desmarcados"
);

/* groups16 */
$inv_g16 = l7hpo_form_inventory(l7hpo_extract_options_form($c["groups16-vip"]));
check($inv_g16["checkbox_counts"]["profile_groups[]"] === 16, "groups16: profile_groups[] x16");
check($inv_g16["checkbox_counts"]["profile_vip_groups[]"] === 16, "groups16: profile_vip_groups[] x16");
check($inv_g16["checkbox_counts"]["profile_src_exclude_groups[]"] === 16,
	"groups16: profile_src_exclude_groups[] x16");

/* Paridade nomes essenciais baseline vs candidato (groups2) */
$core_names = array(
	"profile_id", "add_profile_policy", "profile_action",
	"profile_vip_hosts", "profile_vip_cidrs",
	"profile_src_cidrs", "profile_src_exclude_cidrs",
);
foreach ($core_names as $cn) {
	check(in_array($cn, $inv_g2_c["names"], true), "groups2 candidato: {$cn}");
	check(in_array($cn, $inv_g2_b["names"], true), "groups2 baseline: {$cn}");
}
check($inv_g2_c["profile_action_options"] === array("block", "monitor", "allow"),
	"groups2: opcoes accao sem tag");
check(!$inv_g2_c["has_submit_named"], "groups2: submit sem name");
check($inv_g2_c["action"] === "layer7_policies.php#l7-policies", "groups2: action POST");
check($inv_g2_c["method"] === "post", "groups2: method post");

/* Vista GET dedicada */
check(has($c["options-get"], 'id="l7-profile-options"'), "options-get: vista dedicada");
check(has($c["options-get"], 'name="add_profile_policy"'), "options-get: form opcoes");
check(has($c["options-get"], 'href="layer7_policies.php?library=1#l7-profiles"'),
	"options-get: retorno biblioteca");
check(!has($c["options-get"], 'id="l7ProfileSearch"'), "options-get: sem grelha biblioteca");

/* ID invalido */
check(has($c["options-invalid"], "Perfil nao encontrado"), "options-invalid: mensagem");
check(!has($c["options-invalid"], 'name="add_profile_policy"'), "options-invalid: sem form");

/* GET indisponivel — limite24 vs catalogo vazio */
check(has($c["options-limit24"], "Limite de 24 politicas atingido. A biblioteca"),
	"options-limit24: mensagem limite24");
check(!has($c["options-limit24"], "Catalogo de perfis indisponivel"),
	"options-limit24: sem mensagem catalogo vazio");
check(!has($c["options-limit24"], 'name="add_profile_policy"'),
	"options-limit24: sem form submissao");

check(has($c["options-empty-catalog"], "Catalogo de perfis indisponivel"),
	"options-empty-catalog: mensagem catalogo vazio");
check(!has($c["options-empty-catalog"], "Limite de 24 politicas atingido. A biblioteca"),
	"options-empty-catalog: sem mensagem limite24");
check(!has($c["options-empty-catalog"], 'name="add_profile_policy"'),
	"options-empty-catalog: sem form submissao");

/* Perfil oculto — GET dedicado preservado */
check(has($c["options-hidden"], 'id="l7-profile-options"'),
	"options-hidden: vista dedicada com perfil oculto");
check(has($c["options-hidden"], 'name="add_profile_policy"'),
	"options-hidden: form disponivel");
check(has($c["options-hidden"], 'value="c-hidden-active"'),
	"options-hidden: profile_id oculto");

/* POST erro — reapresenta valores */
check(has($c["post-error"], "Perfil nao encontrado"), "post-error: mensagem");
check(has($c["post-error"], 'id="l7-profile-options"'), "post-error: vista dedicada");
check(strpos($c["post-error"], "192.0.2.1") !== false, "post-error: vip hosts repost");
check(strpos($c["post-error"], "10.1.0.0/24") !== false, "post-error: src cidrs repost");
check(
	preg_match('/name="profile_action"[^>]*>[\s\S]*value="monitor"[^>]*selected/i', $c["post-error"]) === 1,
	"post-error: accao monitor seleccionada"
);

/* POST erro completo — todos os campos */
$inv_pef = l7hpo_form_inventory(l7hpo_extract_options_form($c["post-error-full"]));
check(has($c["post-error-full"], "Perfil nao encontrado"), "post-error-full: mensagem");
check(
	preg_match('/name="profile_action"[^>]*>[\s\S]*value="allow"[^>]*selected/i', $c["post-error-full"]) === 1,
	"post-error-full: accao allow"
);
check(
	preg_match('/name="profile_ifaces\[\]"[^>]*value="lan"[^>]*checked/i', $c["post-error-full"]) === 1,
	"post-error-full: iface lan checked"
);
check(
	preg_match('/name="profile_ifaces\[\]"[^>]*value="opt1"[^>]*checked/i', $c["post-error-full"]) === 1,
	"post-error-full: iface opt1 checked"
);
check(
	preg_match('/name="profile_groups\[\]"[^>]*value="lab"[^>]*checked/i', $c["post-error-full"]) === 1,
	"post-error-full: grupo lab checked"
);
check(
	preg_match('/name="profile_vip_groups\[\]"[^>]*value="vip"[^>]*checked/i', $c["post-error-full"]) === 1,
	"post-error-full: vip grupo checked"
);
check(
	preg_match('/name="profile_src_exclude_groups\[\]"[^>]*value="vip"[^>]*checked/i', $c["post-error-full"]) === 1,
	"post-error-full: exclude grupo checked"
);
check(strpos($inv_pef["fields"]["values"]["profile_vip_hosts"] ?? "", "192.0.2.1") !== false,
	"post-error-full: vip hosts repost hostil");
check(strpos($inv_pef["fields"]["values"]["profile_vip_cidrs"] ?? "", "10.0.0.0/24") !== false,
	"post-error-full: vip cidrs repost hostil");
check(strpos($inv_pef["fields"]["values"]["profile_src_cidrs"] ?? "", "10.1.0.0/24") !== false,
	"post-error-full: src cidrs repost hostil");
check(strpos($inv_pef["fields"]["values"]["profile_src_exclude_cidrs"] ?? "", "192.168.1.99") !== false,
	"post-error-full: exclude cidrs repost hostil");

/* POST com VIP limpo — nao repovoar defaults */
$inv_pec = l7hpo_form_inventory(l7hpo_extract_options_form($c["post-error-cleared-vip"]));
check(($inv_pec["fields"]["values"]["profile_vip_hosts"] ?? "x") === "",
	"post-error-cleared-vip: vip hosts vazio");
check(($inv_pec["fields"]["values"]["profile_vip_cidrs"] ?? "x") === "",
	"post-error-cleared-vip: vip cidrs vazio");
check(
	!preg_match('/name="profile_vip_groups\[\]"[^>]*checked/i', $c["post-error-cleared-vip"]),
	"post-error-cleared-vip: vip grupos desmarcados"
);

/* Escaping — nomes hostis e textarea POST */
$xss_form = l7hpo_extract_options_form($c["options-xss"]);
check($xss_form !== "", "options-xss: form extraido");
check(has($c["options-xss"], "Perfil &lt;img"),
	"options-xss: nome perfil hostil escapado na biblioteca");
check(has($c["options-xss-get"], 'id="l7-profile-options"'),
	"options-xss-get: vista dedicada");
check(has($c["options-xss-get"], "Perfil &lt;img"),
	"options-xss-get: nome perfil hostil no titulo");
check(!has($xss_form, "<script>alert(1)</script>"),
	"options-xss: script grupo nao executavel");
check(has($xss_form, "&lt;script&gt;") || has($xss_form, "&quot;"),
	"options-xss: entidades escapadas");
check(!preg_match('/<script\b/i', $xss_form),
	"options-xss: tag script real ausente no form");
$inv_xss = l7hpo_form_inventory($xss_form);
check(empty(l7hpo_form_duplicate_ids($opts_form_c)),
	"groups2: ids unicos no form modal");
$label_issues = l7hpo_form_label_issues($opts_form_c);
check(empty($label_issues),
	"groups2: labels visiveis (" . implode(", ", $label_issues) . ")");
$pef_form = l7hpo_extract_options_form($c["post-error-full"]);
check(!has($pef_form, "</textarea><script>"),
	"post-error-full: textarea hostil nao fecha tag");
check(strpos($inv_pef["fields"]["values"]["profile_vip_hosts"] ?? "", $hostile["vip_hosts"]) !== false,
	"post-error-full: valor textarea hostil preservado");

/* Draft JS byte-identico; editor migrou V4-B2b (harness-policies-edit) */
$base_src = file_get_contents(L7HPO_BASELINE);
$cand_src = file_get_contents(L7HP_CANDIDATE);
$draft_b = l7hpl_extract_modal($base_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
$draft_c = l7hpl_extract_modal($cand_src, 'function l7toggleProfileDraft', 'function l7showProfileModal');
check($draft_b !== "" && $draft_b === $draft_c, "draft JS byte-identico baseline/candidato");
check(has($c["groups2-vip"], 'class="modal fade" id="l7ProfileEditModal"'),
	"candidato: modal editor bootstrap presente");

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
	fwrite(STDERR, "SOME POLICIES OPTIONS HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL POLICIES OPTIONS HARNESS TESTS PASSED\n";
exit(0);
