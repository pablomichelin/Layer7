<?php
/**
 * Bootstrap V4-B2b — editor/criação de perfil (paridade FormData vs baseline V4-B2b).
 */

require_once dirname(__DIR__) . "/harness-policies-options/bootstrap.php";

if (!defined("L7HPE_BASELINE")) {
	define("L7HPE_BASELINE", __DIR__ . "/source-baseline-v4b2b.php");
}
if (!defined("L7HPE_BASELINE_SHA256")) {
	define("L7HPE_BASELINE_SHA256", "456e855cf4bcc916cd3fc541272726c2b2cbf5803a153a79fc7910d7b06cd6ca");
}

if (!function_exists("l7hpe_verify_baseline")) {
	function l7hpe_verify_baseline()
	{
		if (!is_file(L7HPE_BASELINE)) {
			fwrite(STDERR, "FAIL baseline V4-B2b editor em falta: " . L7HPE_BASELINE . "\n");
			exit(1);
		}
		$sha = hash_file("sha256", L7HPE_BASELINE);
		if (!hash_equals(L7HPE_BASELINE_SHA256, $sha)) {
			fwrite(STDERR, "FAIL baseline V4-B2b SHA256 diverge (esperado "
				. L7HPE_BASELINE_SHA256 . ", got {$sha})\n");
			exit(1);
		}
	}
}
l7hpe_verify_baseline();

if (!function_exists("layer7_profile_id_valid")) {
	function layer7_profile_id_valid($id)
	{
		return is_string($id) && $id !== "" && !layer7_profile_custom_id_valid($id);
	}
}

if (!function_exists("layer7_profile_get_by_id")) {
	function layer7_profile_get_by_id($profile_id)
	{
		foreach (layer7_load_profiles(true) as $p) {
			if (is_array($p) && (string)($p["id"] ?? "") === (string)$profile_id) {
				return $p;
			}
		}
		return null;
	}
}

if (!function_exists("layer7_profiles_factory_load")) {
	function layer7_profiles_factory_load()
	{
		return l7hp_repo_profiles();
	}
}

if (!function_exists("layer7_profile_sanitize_string_list")) {
	function layer7_profile_sanitize_string_list($list, $max)
	{
		if (!is_array($list)) {
			return array();
		}
		$out = array();
		foreach ($list as $v) {
			$v = trim((string)$v);
			if ($v !== "") {
				$out[] = $v;
			}
			if (count($out) >= $max) {
				break;
			}
		}
		return $out;
	}
}

if (!function_exists("layer7_profile_parse_hosts_textarea")) {
	function layer7_profile_parse_hosts_textarea($text, $max)
	{
		$out = array();
		foreach (preg_split('/[\r\n]+/', trim((string)$text)) as $line) {
			$line = trim($line);
			if ($line === "") {
				continue;
			}
			if (strpos($line, "<") !== false || strpos($line, ">") !== false) {
				return null;
			}
			if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $line)) {
				return null;
			}
			$out[] = $line;
			if (count($out) >= $max) {
				break;
			}
		}
		return $out;
	}
}

if (!function_exists("layer7_profile_icon_valid")) {
	function layer7_profile_icon_valid($icon)
	{
		return is_string($icon) && preg_match('/^fa-[a-z0-9-]{1,40}$/', $icon) === 1;
	}
}

if (!function_exists("layer7_profile_validate_limits")) {
	function layer7_profile_validate_limits($prof)
	{
		return null;
	}
}

if (!function_exists("layer7_profile_compute_override")) {
	function layer7_profile_compute_override($factory, $apps, $hosts, $hidden)
	{
		return array("ndpi_apps" => $apps, "hosts" => $hosts, "hidden" => $hidden);
	}
}

if (!function_exists("layer7_profile_apply_override")) {
	function layer7_profile_apply_override($factory, $ov, $catalog)
	{
		return is_array($factory) ? $factory : null;
	}
}

if (!function_exists("layer7_profiles_custom_save")) {
	function layer7_profiles_custom_save($custom)
	{
		return false;
	}
}

if (!function_exists("layer7_profile_reconnect_policy")) {
	function layer7_profile_reconnect_policy($data, $merged)
	{
		return false;
	}
}

if (!function_exists("l7hpe_custom_profile")) {
	function l7hpe_custom_profile()
	{
		return array(
			"id" => "c-harness-edit",
			"name" => 'Custom <script>x</script> & "quotes"',
			"group" => "Personalizados",
			"icon" => "fa-star",
			"description" => "Perfil personalizado de teste",
			"ndpi_apps" => array("AmazonVideo"),
			"ndpi_categories" => array("Advertisement"),
			"hosts" => array("custom.example"),
		);
	}
}

if (!function_exists("l7hpe_hidden_custom")) {
	function l7hpe_hidden_custom()
	{
		return array(
			"id" => "c-hidden-edit",
			"name" => "Oculto edit harness",
			"group" => "Personalizados",
			"icon" => "fa-eye-slash",
			"description" => "Hidden profile",
			"ndpi_apps" => array("AmazonVideo"),
			"ndpi_categories" => array(),
			"hosts" => array("hidden-edit.example"),
			"hidden" => true,
		);
	}
}

if (!function_exists("l7hpe_hostile_post")) {
	function l7hpe_hostile_post()
	{
		return array(
			"name" => 'Nome <img src=x onerror=alert(1)> & "q"',
			"description" => 'Desc <script>alert(1)</script>',
			"icon" => "fa-bug",
			"hosts" => "good.example\n\"></textarea><script>alert(1)</script>",
			"apps" => array("AmazonVideo"),
			"cats" => array("Advertisement"),
		);
	}
}

if (!function_exists("l7hpe_scenarios")) {
	function l7hpe_scenarios()
	{
		$all = l7hpl_repo_profiles_all();
		$factory = !empty($all) ? $all[0] : array("id" => "social", "name" => "Social");
		$fid = (string)($factory["id"] ?? "social");
		$custom = l7hpe_custom_profile();
		$hidden = l7hpe_hidden_custom();
		$hostile = l7hpe_hostile_post();
		$profiles_visible = array($factory, $custom);
		$custom_store = array(
			"custom_profiles" => array($custom, $hidden),
			"overrides" => array(
				$fid => array("hidden" => false, "hosts_add" => array("overlay.example")),
			),
		);

		return array(
			"edit-factory-get" => array(
				"get" => array("profile_edit" => $fid),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "GET",
			),
			"edit-custom-get" => array(
				"get" => array("profile_edit" => "c-harness-edit"),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "GET",
			),
			"edit-new-get" => array(
				"get" => array("profile_new" => "1"),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "GET",
			),
			"edit-connected" => array(
				"get" => array("profile_edit" => $fid),
				"data" => l7hp_data(array(
					l7hpl_profile_policy($fid, array("name" => "Factory activo")),
				), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "GET",
			),
			"edit-hidden" => array(
				"get" => array("profile_edit" => "c-hidden-edit"),
				"data" => l7hp_data(array(
					l7hpl_profile_policy("c-hidden-edit", array("name" => "Oculto activo")),
				)),
				"profiles" => array(
					"visible" => $profiles_visible,
					"hidden" => array($hidden),
					"custom" => $custom_store,
				),
				"method" => "GET",
			),
			"edit-invalid" => array(
				"get" => array("profile_edit" => "invalid-profile-id"),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "GET",
			),
			"edit-limit24" => array(
				"get" => array("profile_new" => "1"),
				"data" => l7hp_data(l7hpl_policies_at_limit()),
				"profiles" => array("visible" => $all),
				"method" => "GET",
			),
			"edit-empty-catalog" => array(
				"get" => array("profile_new" => "1"),
				"data" => l7hp_data(array()),
				"profiles" => array("visible" => array()),
				"method" => "GET",
			),
			"library-links" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array(
					"visible" => $profiles_visible,
					"hidden" => array($hidden),
					"custom" => $custom_store,
				),
				"method" => "GET",
			),
			"library-connected" => array(
				"get" => array("library" => "1"),
				"data" => l7hp_data(array(
					l7hpl_profile_policy($fid, array("name" => "Factory activo")),
					l7hpl_profile_policy("c-harness-edit", array("name" => "Custom activo")),
				), l7hp_groups()),
				"profiles" => array(
					"visible" => $profiles_visible,
					"hidden" => array($hidden),
					"custom" => $custom_store,
				),
				"method" => "GET",
			),
			"post-error-save" => array(
				"post" => array(
					"save_profile_edit" => "1",
					"edit_profile_id" => "c-harness-edit",
					"edit_profile_is_new" => "0",
					"edit_profile_name" => $hostile["name"],
					"edit_profile_description" => $hostile["description"],
					"edit_profile_icon" => $hostile["icon"],
					"edit_profile_apps" => $hostile["apps"],
					"edit_profile_cats" => $hostile["cats"],
					"edit_profile_hosts" => "not a valid host!!!",
				),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "POST",
				"input_errors" => array("Host invalido"),
			),
			"post-error-hostile" => array(
				"post" => array(
					"save_profile_edit" => "1",
					"edit_profile_id" => "c-harness-edit",
					"edit_profile_is_new" => "0",
					"edit_profile_name" => $hostile["name"],
					"edit_profile_description" => $hostile["description"],
					"edit_profile_icon" => $hostile["icon"],
					"edit_profile_apps" => $hostile["apps"],
					"edit_profile_cats" => $hostile["cats"],
					"edit_profile_hosts" => $hostile["hosts"],
				),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "POST",
				"input_errors" => array("Host invalido"),
			),
			"post-error-delete-connected" => array(
				"post" => array(
					"delete_custom_profile" => "1",
					"edit_profile_id" => "c-harness-edit",
					"edit_profile_is_new" => "0",
					"edit_profile_name" => (string)$custom["name"],
					"edit_profile_description" => (string)$custom["description"],
					"edit_profile_icon" => (string)$custom["icon"],
					"edit_profile_hosts" => "custom.example",
				),
				"data" => l7hp_data(array(
					l7hpl_profile_policy("c-harness-edit", array("name" => "Custom activo")),
				), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "POST",
				"input_errors" => array("Desligue o perfil"),
			),
			"post-error-empty-fields" => array(
				"post" => array(
					"save_profile_edit" => "1",
					"edit_profile_id" => "c-harness-edit",
					"edit_profile_is_new" => "0",
					"edit_profile_name" => "",
					"edit_profile_description" => "",
					"edit_profile_icon" => "",
					"edit_profile_hosts" => "not a valid host!!!",
				),
				"data" => l7hp_data(array(), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "POST",
				"input_errors" => array("Host invalido"),
			),
			"post-error-connected" => array(
				"post" => array(
					"save_profile_edit" => "1",
					"edit_profile_id" => "c-harness-edit",
					"edit_profile_is_new" => "0",
					"edit_profile_name" => "Custom editado",
					"edit_profile_description" => "",
					"edit_profile_icon" => "",
					"edit_profile_hosts" => "not a valid host!!!",
				),
				"data" => l7hp_data(array(
					l7hpl_profile_policy("c-harness-edit", array("name" => "Custom activo")),
				), l7hp_groups()),
				"profiles" => array("visible" => $profiles_visible, "custom" => $custom_store),
				"method" => "POST",
				"input_errors" => array("Host invalido"),
			),
		);
	}
}

if (!function_exists("l7hpe_extract_edit_form")) {
	function l7hpe_extract_edit_form($html)
	{
		$start = strpos($html, 'id="l7ProfileEditForm"');
		if ($start === false) {
			$start = strpos($html, 'id="l7-profile-edit"');
		}
		if ($start === false) {
			return "";
		}
		$form_pos = strpos($html, "<form", $start > 0 ? max(0, $start - 200) : 0);
		if ($form_pos === false) {
			$form_pos = strpos($html, "<form", 0);
		}
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

if (!function_exists("l7hpe_form_inventory")) {
	function l7hpe_form_inventory($form_html)
	{
		$fields = l7hp_extract_fields($form_html);
		$names = array_keys($fields["names"]);
		sort($names);
		$out = array(
			"names" => $names,
			"fields" => $fields,
			"apps_count" => substr_count($form_html, 'name="edit_profile_apps[]"'),
			"cats_count" => substr_count($form_html, 'name="edit_profile_cats[]"'),
			"action" => "",
			"method" => "post",
			"form_id" => "",
			"onsubmit" => "",
		);
		if (preg_match('/<form\b([^>]*)>/i', $form_html, $fm)) {
			$attrs = $fm[1];
			$act = l7hp_attr($attrs, "action");
			if (is_string($act)) {
				$out["action"] = $act;
			}
			$meth = l7hp_attr($attrs, "method");
			if (is_string($meth)) {
				$out["method"] = strtolower($meth);
			}
			$id = l7hp_attr($attrs, "id");
			if (is_string($id)) {
				$out["form_id"] = $id;
			}
			$ons = l7hp_attr($attrs, "onsubmit");
			if (is_string($ons)) {
				$out["onsubmit"] = $ons;
			}
		}
		return $out;
	}
}

if (!function_exists("l7hpe_checkbox_values")) {
	function l7hpe_checkbox_values($form_html, $name)
	{
		$vals = array();
		$q = preg_quote($name, "/");
		if (!preg_match_all('/name="' . $q . '"[^>]*value="([^"]*)"/', $form_html, $m)) {
			return $vals;
		}
		foreach ($m[1] as $v) {
			$vals[] = $v;
		}
		sort($vals);
		return $vals;
	}
}

if (!function_exists("l7hpe_checked_values")) {
	function l7hpe_checked_values($form_html, $name)
	{
		$vals = array();
		$q = preg_quote($name, "/");
		if (!preg_match_all('/name="' . $q . '"[^>]*value="([^"]*)"[^>]*checked/i', $form_html, $m)) {
			return $vals;
		}
		foreach ($m[1] as $v) {
			$vals[] = $v;
		}
		sort($vals);
		return $vals;
	}
}

if (!function_exists("l7hpe_render")) {
	function l7hpe_render($opts)
	{
		return l7hpl_render($opts);
	}
}
