<?php
/**
 * Bootstrap V4-B2a — formulário Opções (paridade FormData vs baseline V4-B2).
 * Fixture controlada: grupos 0/2/16, VIP vazio/preenchido via exceptions ou override.
 */

require_once dirname(__DIR__) . "/harness-policies-library/bootstrap.php";

if (!defined("L7HPO_BASELINE")) {
	define("L7HPO_BASELINE", __DIR__ . "/source-baseline-v4b2.php");
}
if (!defined("L7HPO_BASELINE_SHA256")) {
	define("L7HPO_BASELINE_SHA256", "38988f0662ff1b9fbc893390bec5919ecf70b9b62e1a2f730899adc64501e50d");
}

if (!function_exists("l7hpo_verify_baseline")) {
	function l7hpo_verify_baseline()
	{
		if (!is_file(L7HPO_BASELINE)) {
			fwrite(STDERR, "FAIL baseline V4-B2 opcoes em falta: " . L7HPO_BASELINE . "\n");
			exit(1);
		}
		$sha = hash_file("sha256", L7HPO_BASELINE);
		if (!hash_equals(L7HPO_BASELINE_SHA256, $sha)) {
			fwrite(STDERR, "FAIL baseline V4-B2 opcoes SHA256 diverge (esperado "
				. L7HPO_BASELINE_SHA256 . ", got {$sha})\n");
			exit(1);
		}
	}
}
l7hpo_verify_baseline();

if (!function_exists("l7hpo_hidden_active_fixture")) {
	function l7hpo_hidden_active_fixture()
	{
		$all = l7hpl_repo_profiles_all();
		$vis = array_slice($all, 0, 3);
		foreach ($all as $p) {
			if (is_array($p) && ($p["id"] ?? "") === "facebook") {
				$vis[] = $p;
				break;
			}
		}
		return array(
			"visible" => $vis,
			"hidden" => array(array(
				"id" => "c-hidden-active",
				"name" => 'Oculto <script>x</script> & "quotes"',
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
		);
	}
}

if (!function_exists("l7hpo_hostile_profile")) {
	function l7hpo_hostile_profile()
	{
		return array(
			"id" => "xss-harness",
			"name" => 'Perfil <img src=x onerror=alert(1)> & "quotes"',
			"group" => "Testes",
			"icon" => "fa-bug",
			"description" => "Nome hostil de teste",
			"ndpi_apps" => array("HTTP"),
			"ndpi_categories" => array(),
			"hosts" => array("xss-harness.example"),
		);
	}
}

if (!function_exists("l7hpo_hostile_groups")) {
	function l7hpo_hostile_groups()
	{
		return array(
			array("id" => 'g"><script>alert(1)</script>', "name" => 'Grupo & "VIP" <test>'),
			array("id" => "lab", "name" => "Lab normal"),
		);
	}
}

if (!function_exists("l7hpo_hostile_ifaces")) {
	function l7hpo_hostile_ifaces()
	{
		return array(
			array("ifid" => "lan", "real" => "em1", "descr" => 'LAN & "quotes" <iface>'),
			array("ifid" => "opt1", "real" => "em2", "descr" => "OPT1</script>"),
		);
	}
}

if (!function_exists("l7hpo_hostile_post_text")) {
	function l7hpo_hostile_post_text()
	{
		return array(
			"vip_hosts" => "192.0.2.1\n\"></textarea><script>alert(1)</script>",
			"vip_cidrs" => '10.0.0.0/24 & "cidr"',
			"src_cidrs" => "10.1.0.0/24\n<script>x</script>",
			"exc_cidrs" => '192.168.1.99 & exclude',
		);
	}
}

if (!function_exists("l7hpo_scenarios")) {
	function l7hpo_scenarios()
	{
		$all = l7hpl_repo_profiles_all();
		$one = !empty($all) ? array($all[0]) : array();
		$pid = (string)($one[0]["id"] ?? "social");
		$groups2 = l7hp_groups();
		$groups16 = l7hp_groups_n(16);
		$vip = l7hp_vip_exception();
		$hostile = l7hpo_hostile_post_text();
		$hidden_profiles = l7hpo_hidden_active_fixture();
		$library_profiles = array();
		foreach ($all as $p) {
			if (!is_array($p)) {
				continue;
			}
			$id = (string)($p["id"] ?? "");
			if ($id === "facebook" || $id === "social" || $id === "youtube") {
				$library_profiles[] = $p;
			}
		}
		if (empty($library_profiles)) {
			$library_profiles = $one;
		}
		$hostile_profile = l7hpo_hostile_profile();

		return array(
			"groups0" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), array()),
				"profiles" => array("visible" => $one),
				"vip_exception" => false,
				"method" => "GET",
			),
			"groups2-vip" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "GET",
			),
			"groups2-vip-empty" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), $groups2, array()),
				"profiles" => array("visible" => $one),
				"vip_exception" => false,
				"method" => "GET",
			),
			"groups16-vip" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), $groups16, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "GET",
			),
			"options-get" => array(
				"get" => array("profile_options" => $pid),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "GET",
			),
			"options-invalid" => array(
				"get" => array("profile_options" => "invalid-profile-id"),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "GET",
			),
			"options-limit24" => array(
				"get" => array("profile_options" => $pid),
				"data" => l7hp_data(l7hpl_policies_at_limit()),
				"profiles" => array("visible" => $all),
				"method" => "GET",
			),
			"options-empty-catalog" => array(
				"get" => array("profile_options" => $pid),
				"data" => l7hp_data(array()),
				"profiles" => array("visible" => array()),
				"method" => "GET",
			),
			"options-hidden" => array(
				"get" => array("profile_options" => "c-hidden-active"),
				"data" => l7hp_data(array(
					l7hpl_profile_policy("social", array("name" => "Social activo")),
					l7hpl_profile_policy("c-hidden-active", array("name" => "Oculto activo")),
				)),
				"profiles" => $hidden_profiles,
				"method" => "GET",
			),
			"options-xss" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), l7hpo_hostile_groups(), array($vip)),
				"profiles" => array("visible" => array($hostile_profile)),
				"ifaces" => l7hpo_hostile_ifaces(),
				"method" => "GET",
			),
			"options-xss-get" => array(
				"get" => array("profile_options" => "xss-harness"),
				"data" => l7hp_data(array(), l7hpo_hostile_groups(), array($vip)),
				"profiles" => array("visible" => array($hostile_profile)),
				"ifaces" => l7hpo_hostile_ifaces(),
				"method" => "GET",
			),
			"library-draft" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(
					l7hpl_profile_policy("social", array("name" => "Social activo")),
				), $groups2, array($vip)),
				"profiles" => array("visible" => $library_profiles),
				"method" => "GET",
			),
			"post-error" => array(
				"post" => array(
					"add_profile_policy" => "1",
					"profile_id" => "invalid",
					"profile_action" => "monitor",
					"profile_vip_hosts" => "192.0.2.1",
					"profile_src_cidrs" => "10.1.0.0/24",
				),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "POST",
				"input_errors" => array("Perfil nao encontrado."),
			),
			"post-error-full" => array(
				"post" => array(
					"add_profile_policy" => "1",
					"profile_id" => "invalid",
					"profile_action" => "allow",
					"profile_ifaces" => array("lan", "opt1"),
					"profile_groups" => array("lab"),
					"profile_vip_groups" => array("vip"),
					"profile_vip_hosts" => $hostile["vip_hosts"],
					"profile_vip_cidrs" => $hostile["vip_cidrs"],
					"profile_src_cidrs" => $hostile["src_cidrs"],
					"profile_src_exclude_groups" => array("vip"),
					"profile_src_exclude_cidrs" => $hostile["exc_cidrs"],
				),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "POST",
				"input_errors" => array("Perfil nao encontrado."),
			),
			"post-error-cleared-vip" => array(
				"post" => array(
					"add_profile_policy" => "1",
					"profile_id" => "invalid",
					"profile_action" => "block",
					"profile_vip_hosts" => "",
					"profile_vip_cidrs" => "",
					"profile_src_cidrs" => "",
					"profile_src_exclude_cidrs" => "",
				),
				"data" => l7hp_data(array(), $groups2, array($vip)),
				"profiles" => array("visible" => $one),
				"method" => "POST",
				"input_errors" => array("Perfil nao encontrado."),
			),
		);
	}
}

if (!function_exists("l7hpo_extract_options_form")) {
	function l7hpo_extract_options_form($html)
	{
		$start = strpos($html, '<div class="modal fade" id="l7ProfileModal"');
		if ($start === false) {
			$start = strpos($html, '<div id="l7ProfileModal"');
		}
		if ($start === false) {
			$start = strpos($html, 'id="l7-profile-options"');
		}
		if ($start === false) {
			return "";
		}
		$form_pos = strpos($html, "<form", $start);
		if ($form_pos === false) {
			return "";
		}
		$form_end = strpos($html, "</form>", $form_pos);
		if ($form_end === false) {
			return "";
		}
		return substr($html, $form_pos, $form_end - $form_pos + 7);
	}
}

if (!function_exists("l7hpo_form_inventory")) {
	function l7hpo_form_inventory($form_html)
	{
		$fields = l7hp_extract_fields($form_html);
		$names = array_keys($fields["names"]);
		sort($names);
		$checkbox_families = array(
			"profile_ifaces[]",
			"profile_groups[]",
			"profile_vip_groups[]",
			"profile_src_exclude_groups[]",
		);
		$out = array(
			"names" => $names,
			"fields" => $fields,
			"checkbox_counts" => array(),
			"has_submit_named" => (bool)preg_match('/<button[^>]*type="submit"[^>]*name="/i', $form_html),
			"has_add_profile_policy" => in_array("add_profile_policy", $names, true),
			"action" => "",
			"method" => "post",
		);
		if (preg_match('/<form\b([^>]*)>/i', $form_html, $fm)) {
			$act = l7hp_attr($fm[1], "action");
			if (is_string($act)) {
				$out["action"] = $act;
			}
			$meth = l7hp_attr($fm[1], "method");
			if (is_string($meth)) {
				$out["method"] = strtolower($meth);
			}
		}
		foreach ($checkbox_families as $fam) {
			$out["checkbox_counts"][$fam] = substr_count($form_html, 'name="' . $fam . '"');
		}
		if (preg_match('/<select[^>]*name="profile_action"[^>]*>([\s\S]*?)<\/select>/i', $form_html, $sm)) {
			$opts = array();
			if (preg_match_all('/<option[^>]*value="([^"]*)"[^>]*>/i', $sm[1], $om)) {
				foreach ($om[1] as $ov) {
					$opts[] = $ov;
				}
			}
			$out["profile_action_options"] = $opts;
		}
		return $out;
	}
}

if (!function_exists("l7hpo_form_label_issues")) {
	function l7hpo_form_label_issues($form_html)
	{
		$issues = array();
		if ($form_html === "") {
			return array("form vazio");
		}
		if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $form_html, $els, PREG_SET_ORDER)) {
			return $issues;
		}
		foreach ($els as $el) {
			$tag = strtolower($el[1]);
			$attrs = $el[2];
			$name = l7hp_attr($attrs, "name");
			if (!is_string($name) || $name === "") {
				continue;
			}
			$type = l7hp_attr($attrs, "type");
			$type = is_string($type) ? strtolower($type) : "";
			if ($type === "hidden") {
				continue;
			}
			$id = l7hp_attr($attrs, "id");
			$has_label = false;
			if (is_string($id) && $id !== "") {
				if (preg_match('/<label[^>]*\bfor="' . preg_quote($id, "/") . '"/i', $form_html)) {
					$has_label = true;
				}
			}
			if (!$has_label) {
				$needle = preg_quote($el[0], "/");
				if (preg_match('/<label[^>]*>[\s\S]*?' . $needle . '/i', $form_html)) {
					$has_label = true;
				}
			}
			if (!$has_label) {
				$issues[] = $name;
			}
		}
		return $issues;
	}
}

if (!function_exists("l7hpo_form_duplicate_ids")) {
	function l7hpo_form_duplicate_ids($form_html)
	{
		$seen = array();
		$dups = array();
		if (!preg_match_all('/\bid="([^"]+)"/i', $form_html, $m)) {
			return $dups;
		}
		foreach ($m[1] as $id) {
			if ($id === "") {
				continue;
			}
			if (isset($seen[$id])) {
				$dups[$id] = true;
			}
			$seen[$id] = true;
		}
		return array_keys($dups);
	}
}

if (!function_exists("l7hpo_render")) {
	function l7hpo_render($opts)
	{
		return l7hpl_render($opts);
	}
}
