<?php
/**
 * Bootstrap V4-B1 — biblioteca de perfis (fonte REAL + catálogo JSON do repo).
 * Reutiliza stubs de harness-policies-view. Não altera source-baseline V4-A.
 */

require_once dirname(__DIR__) . "/harness-policies-view/bootstrap.php";

if (!function_exists("layer7_apply_profile_toggles")) {
	function layer7_apply_profile_toggles($enable_ids, $disable_ids)
	{
		$ids = array_merge((array)$enable_ids, (array)$disable_ids);
		foreach ($ids as $id) {
			if ($id === "invalid" || $id === "") {
				return array("ok" => false, "errors" => array("Perfil nao encontrado."));
			}
		}
		return array("ok" => true, "msg" => "stub ok");
	}
}

if (!defined("L7HPL_BASELINE")) {
	define("L7HPL_BASELINE", __DIR__ . "/source-baseline-v4b.php");
}
if (!defined("L7HPL_BASELINE_SHA256")) {
	define("L7HPL_BASELINE_SHA256", "5c0ff398155a4e64d53a8fb5b5b47fd17a35f767da2effd8f6a70e6dddb8951a");
}

if (!function_exists("l7hpl_verify_baseline")) {
	function l7hpl_verify_baseline()
	{
		if (!is_file(L7HPL_BASELINE)) {
			fwrite(STDERR, "FAIL baseline V4-B em falta: " . L7HPL_BASELINE . "\n");
			exit(1);
		}
		$sha = hash_file("sha256", L7HPL_BASELINE);
		if (!hash_equals(L7HPL_BASELINE_SHA256, $sha)) {
			fwrite(STDERR, "FAIL baseline V4-B SHA256 diverge (esperado " . L7HPL_BASELINE_SHA256 . ", got {$sha})\n");
			exit(1);
		}
	}
}
l7hpl_verify_baseline();

if (!function_exists("l7hpl_repo_profiles_all")) {
	function l7hpl_repo_profiles_all()
	{
		return l7hp_repo_profiles();
	}
}

if (!function_exists("l7hpl_profile_policy")) {
	function l7hpl_profile_policy($profile_id, $opts = array())
	{
		return l7hp_policy("profile-" . $profile_id, array_merge(array(
			"name" => "Perfil " . $profile_id,
			"action" => "block",
			"enabled" => true,
			"priority" => 50,
		), $opts));
	}
}

if (!function_exists("l7hpl_policies_at_limit")) {
	function l7hpl_policies_at_limit()
	{
		$out = array();
		for ($i = 0; $i < 24; $i++) {
			$out[] = l7hp_policy("p-limit-" . $i, array(
				"name" => "Pol " . $i,
				"priority" => $i,
			));
		}
		return $out;
	}
}

if (!function_exists("l7hpl_set_profiles")) {
	function l7hpl_set_profiles($visible, $hidden = null, $custom = null)
	{
		$GLOBALS["l7hp_profiles_catalog"] = $visible;
		if ($hidden === null) {
			unset($GLOBALS["l7hp_profiles_hidden"]);
		} else {
			$GLOBALS["l7hp_profiles_hidden"] = $hidden;
		}
		if ($custom === null) {
			unset($GLOBALS["l7hp_profiles_custom"]);
		} else {
			$GLOBALS["l7hp_profiles_custom"] = $custom;
		}
	}
}

if (!function_exists("l7hpl_render")) {
	function l7hpl_render($opts)
	{
		global $input_errors;
		$_SERVER["REQUEST_METHOD"] = isset($opts["method"]) ? (string)$opts["method"] : "GET";
		if (isset($opts["profiles"])) {
			l7hpl_set_profiles(
				$opts["profiles"]["visible"] ?? array(),
				$opts["profiles"]["hidden"] ?? null,
				$opts["profiles"]["custom"] ?? null
			);
		} else {
			unset($GLOBALS["l7hp_profiles_catalog"], $GLOBALS["l7hp_profiles_hidden"], $GLOBALS["l7hp_profiles_custom"]);
		}
		if (isset($opts["input_errors"]) && is_array($opts["input_errors"])) {
			$input_errors = $opts["input_errors"];
		}
		return l7hp_render($opts);
	}
}

if (!function_exists("l7hpl_redirect_flag")) {
	function l7hpl_redirect_flag($html)
	{
		if (preg_match('/var l7LegacyLibraryRedirectOk = (true|false);/', $html, $m)) {
			return $m[1] === "true";
		}
		return null;
	}
}

if (!function_exists("l7hpl_profile_ids")) {
	function l7hpl_profile_ids($html)
	{
		$ids = array();
		if (preg_match_all('/data-profile-id="([^"]+)"/', $html, $m)) {
			foreach ($m[1] as $id) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}

if (!function_exists("l7hpl_edit_data")) {
	function l7hpl_edit_data($html)
	{
		if (preg_match('/var l7ProfileEditData = (\{[\s\S]*?\});/', $html, $m)) {
			$j = json_decode($m[1], true);
			return is_array($j) ? $j : array();
		}
		return array();
	}
}

if (!function_exists("l7hpl_extract_modal")) {
	function l7hpl_extract_modal($src, $start, $end)
	{
		$i = strpos($src, $start);
		if ($i === false) {
			return "";
		}
		$j = strpos($src, $end, $i);
		if ($j === false) {
			return "";
		}
		return substr($src, $i, $j - $i);
	}
}

if (!function_exists("l7hpl_profile_post_forms")) {
	function l7hpl_profile_post_forms($html)
	{
		$out = array();
		if (!preg_match_all(
			'/<form\b([^>]*)>([\s\S]*?)<\/form>/i',
			$html,
			$forms,
			PREG_SET_ORDER
		)) {
			return $out;
		}
		foreach ($forms as $fm) {
			$attrs = $fm[1];
			$body = $fm[2];
			if (strpos($body, "profile_id") === false) {
				continue;
			}
			if (!preg_match('/name="profile_id"\s+value="([^"]*)"/', $body, $pid)) {
				continue;
			}
			$method = "post";
			if (preg_match('/\bmethod\s*=\s*"([^"]*)"/i', $attrs, $mm)) {
				$method = strtolower($mm[1]);
			}
			$action = "";
			if (preg_match('/\baction\s*=\s*"([^"]*)"/i', $attrs, $am)) {
				$action = $am[1];
			}
			$kind = "";
			$fields = array();
			if (preg_match_all('/<input\b([^>]*)>/i', $body, $ins, PREG_SET_ORDER)) {
				foreach ($ins as $in) {
					$a = $in[1];
					$name = "";
					$value = "";
					$type = "text";
					if (preg_match('/\bname\s*=\s*"([^"]*)"/i', $a, $nm)) {
						$name = $nm[1];
					}
					if (preg_match('/\bvalue\s*=\s*"([^"]*)"/i', $a, $vm)) {
						$value = $vm[1];
					}
					if (preg_match('/\btype\s*=\s*"([^"]*)"/i', $a, $tm)) {
						$type = strtolower($tm[1]);
					}
					if ($name === "") {
						continue;
					}
					$fields[] = array("name" => $name, "value" => $value, "type" => $type);
				}
			}
			if (preg_match_all('/<button\b([^>]*)>/i', $body, $bts, PREG_SET_ORDER)) {
				foreach ($bts as $bt) {
					$a = $bt[1];
					$name = "";
					$value = "1";
					if (preg_match('/\bname\s*=\s*"([^"]*)"/i', $a, $nm)) {
						$name = $nm[1];
					}
					if (preg_match('/\bvalue\s*=\s*"([^"]*)"/i', $a, $vm)) {
						$value = $vm[1];
					}
					if ($name === "") {
						continue;
					}
					if ($kind === "") {
						$kind = $name;
					}
					$fields[] = array("name" => $name, "value" => $value, "type" => "submit");
				}
			}
			usort($fields, function ($a, $b) {
				$ka = $a["name"] . "\0" . $a["type"];
				$kb = $b["name"] . "\0" . $b["type"];
				return strcmp($ka, $kb);
			});
			$out[] = array(
				"profile_id" => $pid[1],
				"method" => $method,
				"action" => $action,
				"kind" => $kind,
				"fields" => $fields,
			);
		}
		return $out;
	}
}

/* Compat: alias antigo */
if (!function_exists("l7hpl_profile_toggle_forms")) {
	function l7hpl_profile_toggle_forms($html)
	{
		$out = array();
		foreach (l7hpl_profile_post_forms($html) as $f) {
			$out[] = array(
				"profile_id" => $f["profile_id"],
				"action" => $f["action"],
				"toggle" => $f["kind"],
			);
		}
		return $out;
	}
}
