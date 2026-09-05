<?php
##|+PRIV
##|*IDENT=page-services-layer7-policies
##|*NAME=Services: Layer 7 (policies)
##|*DESCR=Allow access to Layer 7 policies.
##|*MATCH=layer7_policies.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$layer7_policy_edit_retry = null;
$l7_ent = layer7_entitlements();
$l7_has_identity = !empty($l7_ent["has_identity"]);

if ($_POST["add_profile_policy"] ?? false) {
		$profile_id = trim($_POST["profile_id"] ?? "");
		$profiles = layer7_load_profiles();
		$profile = null;
		foreach ($profiles as $p) {
			if (isset($p["id"]) && $p["id"] === $profile_id) {
				$profile = $p;
				break;
			}
		}
		if ($profile === null) {
			$input_errors[] = l7_t("Perfil nao encontrado.");
		} else {
			$data = layer7_load_or_default();
			if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
				$data["layer7"]["policies"] = array();
			}
			$policies = &$data["layer7"]["policies"];

			if (count($policies) >= 24) {
				$input_errors[] = l7_t("Limite de 24 politicas.");
			} else {
				$pid = "profile-" . $profile_id;
				$dup = false;
				foreach ($policies as $existing) {
					if (isset($existing["id"]) && (string)$existing["id"] === $pid) {
						$dup = true;
						break;
					}
				}
				if ($dup) {
					$input_errors[] = sprintf(l7_t("Ja existe uma politica com id '%s'. Remova-a primeiro para recriar."), $pid);
				} else {
					$prof_act = trim($_POST["profile_action"] ?? "block");
					if (!in_array($prof_act, array("monitor", "allow", "block", "tag"), true)) {
						$prof_act = "block";
					}
					$rule = array(
						"id" => $pid,
						"name" => $profile["name"] ?? $pid,
						"enabled" => true,
						"action" => $prof_act,
						"priority" => 50,
						"match" => array()
					);
					$prof_ifaces = array();
					if (isset($_POST["profile_ifaces"]) && is_array($_POST["profile_ifaces"])) {
						foreach ($_POST["profile_ifaces"] as $ifid) {
							$real = layer7_real_interface_name($ifid);
							if ($real !== "") {
								$prof_ifaces[] = $real;
							}
						}
					}
					if (!empty($prof_ifaces)) {
						$rule["interfaces"] = array_values(array_unique($prof_ifaces));
					}
					$prof_src_cidrs = layer7_parse_cidr_textarea($_POST["profile_src_cidrs"] ?? "");
					if (!empty($prof_src_cidrs)) {
						$rule["match"]["src_cidrs"] = $prof_src_cidrs;
					}
					$prof_groups_sel = array();
					if (isset($_POST["profile_groups"]) && is_array($_POST["profile_groups"])) {
						foreach ($_POST["profile_groups"] as $gv) {
							$gv = trim($gv);
							if ($gv !== "" && layer7_group_id_valid($gv)) {
								$prof_groups_sel[] = $gv;
							}
						}
						$prof_groups_sel = array_values(array_unique($prof_groups_sel));
					}
					if (!empty($prof_groups_sel)) {
						$rule["match"]["groups"] = $prof_groups_sel;
					}
					$prof_exc_cidrs = layer7_parse_cidr_textarea(
					    $_POST["profile_src_exclude_cidrs"] ?? "");
					if (!empty($prof_exc_cidrs)) {
						$rule["match"]["src_exclude_cidrs"] = $prof_exc_cidrs;
					}
					$prof_exc_groups = array();
					if (isset($_POST["profile_src_exclude_groups"]) &&
					    is_array($_POST["profile_src_exclude_groups"])) {
						foreach ($_POST["profile_src_exclude_groups"] as $gv) {
							$gv = trim($gv);
							if ($gv !== "" && layer7_group_id_valid($gv)) {
								$prof_exc_groups[] = $gv;
							}
						}
						$prof_exc_groups = array_values(array_unique($prof_exc_groups));
					}
					if (!empty($prof_exc_groups)) {
						$rule["match"]["src_exclude_groups"] = $prof_exc_groups;
					}

					layer7_policy_apply_profile_selectors($rule, $profile);

					if ($prof_act === "block" &&
					    layer7_enforcement_is_scoped_hybrid($data) &&
					    !layer7_policy_scoped_block_valid($rule, $data)) {
						$input_errors[] = l7_t("No modo scoped_hybrid, selecione ao menos um CIDR/grupo de origem para o perfil.");
					} elseif (layer7_policy_src_include_exclude_conflict($rule,
					    isset($data["layer7"]["groups"]) ? $data["layer7"]["groups"] : array())) {
						$input_errors[] = l7_t("Origem nao pode estar simultaneamente incluida e excluida nesta politica.");
					} else {
						$policies[] = $rule;

						$vip_groups_post = array();
						if (isset($_POST["profile_vip_groups"]) &&
						    is_array($_POST["profile_vip_groups"])) {
							foreach ($_POST["profile_vip_groups"] as $vg) {
								$vg = trim((string)$vg);
								if ($vg !== "" && layer7_group_id_valid($vg)) {
									$vip_groups_post[] = $vg;
								}
							}
							$vip_groups_post = array_values(array_unique($vip_groups_post));
						}
						$vip_hosts_post = (string)($_POST["profile_vip_hosts"] ?? "");
						$vip_cidrs_post = (string)($_POST["profile_vip_cidrs"] ?? "");
						$has_vip = !empty($vip_groups_post) ||
						    trim($vip_hosts_post) !== "" ||
						    trim($vip_cidrs_post) !== "";
						if ($has_vip) {
							$vip_res = layer7_upsert_vip_exception(
								$data, $vip_groups_post,
								$vip_hosts_post, $vip_cidrs_post);
							if (!$vip_res["ok"]) {
								array_pop($policies);
								$input_errors[] = $vip_res["error"];
							}
						}

						if (empty($input_errors) && layer7_save_json($data)) {
							layer7_pf_config_resync(true);
							$savemsg = sprintf(l7_t("Politica '%s' criada a partir do perfil '%s'."), $pid, $profile["name"] ?? $profile_id);
							if ($has_vip && !empty($vip_res["updated"])) {
								$savemsg .= " " . l7_t("Excepcao VIP isentos actualizada.");
							}

							if (isset($profile["extra_action"]) && $profile["extra_action"] === "configure_unbound_anti_doh") {
								$doh_result = layer7_configure_unbound_anti_doh();
								if ($doh_result["ok"]) {
									$savemsg .= " " . l7_t("Unbound anti-DoH tambem configurado.");
								}
							}
						}
					}
				}
			}
			unset($policies);
		}
}

/* BG-110 — aplicar vários perfis rápidos de uma vez (1 save + 1 resync). */
if ($_POST["apply_profiles_batch"] ?? false) {
	$parse_ids = function ($raw) {
		if (is_array($raw)) {
			return array_values(array_filter(array_map("strval", $raw)));
		}
		$raw = trim((string)$raw);
		if ($raw === "") {
			return array();
		}
		return preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY);
	};
	$enable_ids = $parse_ids($_POST["enable_ids"] ?? "");
	$disable_ids = $parse_ids($_POST["disable_ids"] ?? "");
	$result = layer7_apply_profile_toggles($enable_ids, $disable_ids);
	if (!empty($_POST["ajax"])) {
		header("Content-Type: application/json; charset=utf-8");
		header("Cache-Control: no-cache, no-store");
		echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}
	if (!empty($result["ok"])) {
		$savemsg = (string)($result["msg"] ?? "");
	} elseif (!empty($result["errors"]) && is_array($result["errors"])) {
		$input_errors = array_merge(
		    is_array($input_errors ?? null) ? $input_errors : array(),
		    $result["errors"]
		);
	} elseif (!empty($result["msg"])) {
		$input_errors[] = (string)$result["msg"];
	}
}

/* Caminho A / A4 — toggle directo de perfil ON (fallback sem JS). */
if ($_POST["toggle_profile_on"] ?? false) {
		$profile_id = trim($_POST["profile_id"] ?? "");
		$result = layer7_apply_profile_toggles(array($profile_id), array());
		if (!empty($result["ok"])) {
			$savemsg = (string)($result["msg"] ?? "");
		} elseif (!empty($result["errors"])) {
			$input_errors = array_merge(
			    is_array($input_errors ?? null) ? $input_errors : array(),
			    $result["errors"]
			);
		}
}

/* Caminho A / A4 — toggle directo de perfil OFF (fallback sem JS). */
if ($_POST["toggle_profile_off"] ?? false) {
		$profile_id = trim($_POST["profile_id"] ?? "");
		$result = layer7_apply_profile_toggles(array(), array($profile_id));
		if (!empty($result["ok"])) {
			$savemsg = (string)($result["msg"] ?? "");
		} elseif (!empty($result["errors"])) {
			$input_errors = array_merge(
			    is_array($input_errors ?? null) ? $input_errors : array(),
			    $result["errors"]
			);
		}
}

/* BG-070 — tornar perfil oculto visivel novamente (sem alterar politica ligada). */
if ($_POST["unhide_profile"] ?? false) {
	$profile_id = trim((string)($_POST["profile_id"] ?? ""));
	if (!layer7_profile_id_valid($profile_id) &&
	    !layer7_profile_custom_id_valid($profile_id)) {
		$input_errors[] = l7_t("Perfil invalido.");
	} else {
		$custom = layer7_profiles_custom_load();
		if (layer7_profile_custom_id_valid($profile_id)) {
			$list = (isset($custom["custom_profiles"]) &&
			    is_array($custom["custom_profiles"]))
			    ? $custom["custom_profiles"] : array();
			foreach ($list as $i => $cp) {
				if (is_array($cp) && ($cp["id"] ?? "") === $profile_id) {
					unset($list[$i]["hidden"]);
					break;
				}
			}
			$custom["custom_profiles"] = array_values($list);
		} elseif (isset($custom["overrides"][$profile_id]) &&
		    is_array($custom["overrides"][$profile_id])) {
			unset($custom["overrides"][$profile_id]["hidden"]);
			$ov = $custom["overrides"][$profile_id];
			$ov_empty = true;
			foreach (array("apps_add", "apps_remove", "hosts_add", "hosts_remove") as $k) {
				if (!empty($ov[$k]) && is_array($ov[$k])) {
					$ov_empty = false;
					break;
				}
			}
			if ($ov_empty) {
				unset($custom["overrides"][$profile_id]);
			}
		}
		if (empty($input_errors) && layer7_profiles_custom_save($custom)) {
			$savemsg = l7_t("Perfil visivel novamente.");
		}
	}
}

/* BG-070 — guardar edicao de perfil (override fabrica ou perfil personalizado). */
if ($_POST["save_profile_edit"] ?? false) {
	$profile_id = trim((string)($_POST["edit_profile_id"] ?? ""));
	$is_new = !empty($_POST["edit_profile_is_new"]);
	$catalog = layer7_profiles_catalog();
	$custom = layer7_profiles_custom_load();
	$data = layer7_load_or_default();
	$reconnected = false;

	if ($is_new) {
		$name = trim((string)($_POST["edit_profile_name"] ?? ""));
		$desc = trim((string)($_POST["edit_profile_description"] ?? ""));
		if ($name === "" || strlen($name) > 120) {
			$input_errors[] = l7_t("Nome do perfil obrigatorio (max. 120 caracteres).");
		}
		if (strlen($desc) > 400) {
			$input_errors[] = l7_t("Descricao demasiado longa (max. 400 caracteres).");
		}
		if ($profile_id === "" || !layer7_profile_custom_id_valid($profile_id)) {
			$slug = preg_replace("/[^a-z0-9-]+/", "-", strtolower($name));
			$slug = trim($slug, "-");
			if ($slug === "") {
				$slug = "perfil";
			}
			$profile_id = "c-" . substr($slug, 0, 40);
			$n = 2;
			while (layer7_profile_get_by_id($profile_id) !== null) {
				$profile_id = "c-" . substr($slug, 0, 36) . "-" . $n;
				$n++;
			}
		} elseif (layer7_profile_get_by_id($profile_id) !== null) {
			$input_errors[] = l7_t("Ja existe um perfil com este id.");
		}
	} elseif (layer7_profile_custom_id_valid($profile_id)) {
		/* update custom — id fixo */
	} elseif (layer7_profile_id_valid($profile_id)) {
		/* factory override */
	} else {
		$input_errors[] = l7_t("Perfil invalido.");
	}

	if (empty($input_errors)) {
		$desired_apps = layer7_profile_sanitize_string_list(
		    $_POST["edit_profile_apps"] ?? array(), 64);
		$filtered_apps = array();
		foreach ($desired_apps as $a) {
			if (isset($catalog["apps_set"][$a])) {
				$filtered_apps[] = $a;
			}
		}
		$desired_cats = layer7_profile_sanitize_string_list(
		    $_POST["edit_profile_cats"] ?? array(), 8);
		$filtered_cats = array();
		foreach ($desired_cats as $c) {
			if (isset($catalog["cats_set"][$c])) {
				$filtered_cats[] = $c;
			}
		}
		$desired_hosts = layer7_profile_parse_hosts_textarea(
		    (string)($_POST["edit_profile_hosts"] ?? ""), 64);
		if ($desired_hosts === null) {
			$input_errors[] = l7_t("Host invalido (use dominios validos, um por linha).");
		}
		$hidden = !empty($_POST["edit_profile_hidden"]);

		if (empty($input_errors)) {
			if ($is_new || layer7_profile_custom_id_valid($profile_id)) {
				$name = trim((string)($_POST["edit_profile_name"] ?? ""));
				$desc = trim((string)($_POST["edit_profile_description"] ?? ""));
				$icon = trim((string)($_POST["edit_profile_icon"] ?? "fa-cube"));
				if (!layer7_profile_icon_valid($icon)) {
					$icon = "fa-cube";
				}
				$new_prof = array(
					"id" => $profile_id,
					"name" => $name,
					"description" => $desc,
					"group" => "Personalizados",
					"icon" => $icon,
					"ndpi_apps" => $filtered_apps,
					"ndpi_categories" => $filtered_cats,
					"hosts" => $desired_hosts,
				);
				if ($hidden) {
					$new_prof["hidden"] = true;
				}
				$lim_err = layer7_profile_validate_limits($new_prof);
				if ($lim_err !== null) {
					$input_errors[] = $lim_err;
				} else {
					$list = (isset($custom["custom_profiles"]) &&
					    is_array($custom["custom_profiles"]))
					    ? $custom["custom_profiles"] : array();
					$replaced = false;
					foreach ($list as $i => $cp) {
						if (is_array($cp) && ($cp["id"] ?? "") === $profile_id) {
							$list[$i] = $new_prof;
							$replaced = true;
							break;
						}
					}
					if (!$replaced) {
						$list[] = $new_prof;
					}
					$custom["custom_profiles"] = $list;
				}
				} else {
					$factory = null;
					foreach (layer7_profiles_factory_load() as $fp) {
						if (is_array($fp) && ($fp["id"] ?? "") === $profile_id) {
							$factory = $fp;
							break;
						}
					}
					if ($factory === null) {
						$input_errors[] = l7_t("Perfil de fabrica nao encontrado.");
					} else {
						$ov = layer7_profile_compute_override(
						    $factory, $filtered_apps, $desired_hosts, $hidden);
						if ($ov === false) {
							$input_errors[] = l7_t("Host invalido na lista.");
						} else {
							$test_merged = layer7_profile_apply_override(
							    $factory, is_array($ov) ? $ov : array(), $catalog);
							if ($test_merged === null ||
							    layer7_profile_validate_limits($test_merged) !== null) {
								$input_errors[] = l7_t("Limites do perfil excedidos (64 apps / 8 cats / 64 hosts).");
							} elseif ($ov === null) {
								unset($custom["overrides"][$profile_id]);
							} else {
								if (!isset($custom["overrides"]) || !is_array($custom["overrides"])) {
									$custom["overrides"] = array();
								}
								$custom["overrides"][$profile_id] = $ov;
							}
						}
					}
				}
		}
	}

	if (empty($input_errors) && layer7_profiles_custom_save($custom)) {
		$merged = layer7_profile_get_by_id($profile_id);
		if ($merged !== null) {
			$reconnected = layer7_profile_reconnect_policy($data, $merged);
			if ($reconnected && layer7_save_json($data)) {
				layer7_pf_config_resync(true);
			}
		}
		$savemsg = l7_t("Perfil guardado.");
		if ($reconnected) {
			$savemsg .= " " . l7_t("A politica ligada foi actualizada com o novo snapshot.");
		}
		if ($hidden && $reconnected) {
			$savemsg .= " " . l7_t("O perfil esta oculto mas a politica permanece activa.");
		}
	}
}

/* BG-070 — remover perfil personalizado (nao desliga politica ligada automaticamente). */
if ($_POST["delete_custom_profile"] ?? false) {
	$profile_id = trim((string)($_POST["edit_profile_id"] ?? ""));
	if (!layer7_profile_custom_id_valid($profile_id)) {
		$input_errors[] = l7_t("Apenas perfis personalizados podem ser apagados.");
	} else {
		$data = layer7_load_or_default();
		$pid = "profile-" . $profile_id;
		$connected = false;
		if (isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])) {
			foreach ($data["layer7"]["policies"] as $pol) {
				if (($pol["id"] ?? "") === $pid) {
					$connected = true;
					break;
				}
			}
		}
		if ($connected) {
			$input_errors[] = l7_t("Desligue o perfil antes de o apagar.");
		} else {
			$custom = layer7_profiles_custom_load();
			$list = (isset($custom["custom_profiles"]) &&
			    is_array($custom["custom_profiles"])) ? $custom["custom_profiles"] : array();
			$custom["custom_profiles"] = array_values(array_filter(
				$list,
				function ($cp) use ($profile_id) {
					return !is_array($cp) || ($cp["id"] ?? "") !== $profile_id;
				}
			));
			if (layer7_profiles_custom_save($custom)) {
				$savemsg = l7_t("Perfil personalizado removido.");
			}
		}
	}
}

if ($_POST["add_policy"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$ok = true;

		if (count($policies) >= 24) {
			$input_errors[] = l7_t("Limite de 24 politicas.");
			$ok = false;
		}

		$pid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($pid)) {
			$input_errors[] = l7_t("ID invalido (letras, numeros, _ e -; max. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($policies as $existing_policy) {
				if (isset($existing_policy["id"]) && (string)$existing_policy["id"] === $pid) {
					$input_errors[] = l7_t("Ja existe uma politica com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$name = trim($_POST["new_name"] ?? "");
		if ($ok && strlen($name) > 160) {
			$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
			$ok = false;
		}

		$pri = (int)($_POST["new_priority"] ?? 50);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = l7_t("Prioridade invalida (0-99999).");
			$ok = false;
		}

		$act = $_POST["new_action"] ?? "monitor";
		if (!in_array($act, array("monitor", "allow", "block", "tag"), true)) {
			$act = "monitor";
		}

		if (isset($_POST["new_ndpi_apps"]) && is_array($_POST["new_ndpi_apps"])) {
			$apps = array_slice(array_filter(array_map('trim', $_POST["new_ndpi_apps"]), 'strlen'), 0, 12);
		} else {
			$apps = layer7_split_csv_tokens($_POST["new_ndpi_apps_csv"] ?? "", 12, 64);
		}
		if (isset($_POST["new_ndpi_category"]) && is_array($_POST["new_ndpi_category"])) {
			$cats = array_slice(array_filter(array_map('trim', $_POST["new_ndpi_category"]), 'strlen'), 0, 8);
		} else {
			$cats = layer7_split_csv_tokens($_POST["new_ndpi_category_csv"] ?? "", 8, 64);
		}
		if ($ok && ($apps === null || $cats === null)) {
			$input_errors[] = l7_t("App ou categoria: cada valor max. 64 caracteres.");
			$ok = false;
		}
		$new_match_hosts_pre = layer7_parse_host_textarea($_POST["new_match_hosts"] ?? "", 64);
		if ($ok && $apps !== null && $cats !== null &&
		    ($act === "block" || $act === "tag") &&
		    count($apps) + count($cats) === 0 &&
		    empty($new_match_hosts_pre)) {
			$input_errors[] = l7_t("Para block ou tag, indique app nDPI, categoria e/ou sites/hosts.");
			$ok = false;
		}

		$tag_table = trim($_POST["new_tag_table"] ?? "");
		if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
			$input_errors[] = l7_t("Tabela PF (tag): apenas A-Z, a-z, 0-9, _ (1-63 caracteres).");
			$ok = false;
		}

		$new_rule_ifaces = array();
		if (isset($_POST["new_ifaces"]) && is_array($_POST["new_ifaces"])) {
			foreach ($_POST["new_ifaces"] as $ifid) {
				$real = layer7_real_interface_name($ifid);
				if ($real !== "") {
					$new_rule_ifaces[] = $real;
				}
			}
		}
		$new_src_hosts = layer7_parse_ip_textarea($_POST["new_src_hosts"] ?? "");
		$new_src_cidrs = layer7_parse_cidr_textarea($_POST["new_src_cidrs"] ?? "");
		$new_match_hosts = layer7_parse_host_textarea($_POST["new_match_hosts"] ?? "", 64);
		$new_ad_users = $l7_has_identity
		    ? layer7_parse_ad_users_textarea($_POST["new_ad_users"] ?? "")
		    : array();
		$new_ad_groups = $l7_has_identity
		    ? layer7_parse_ad_groups_textarea($_POST["new_ad_groups"] ?? "")
		    : array();

		$new_groups_sel = array();
		if (isset($_POST["new_groups"]) && is_array($_POST["new_groups"])) {
			foreach ($_POST["new_groups"] as $gv) {
				$gv = trim($gv);
				if ($gv !== "" && layer7_group_id_valid($gv)) {
					$new_groups_sel[] = $gv;
				}
			}
			$new_groups_sel = array_values(array_unique($new_groups_sel));
		}

		if ($ok && $apps !== null && $cats !== null) {
			$rule = array(
				"id" => $pid,
				"name" => $name !== "" ? $name : $pid,
				"enabled" => isset($_POST["new_enabled"]),
				"action" => $act,
				"priority" => $pri,
				"match" => array()
			);
			if (!empty($new_rule_ifaces)) {
				$rule["interfaces"] = array_values(array_unique($new_rule_ifaces));
			}
			if (count($apps) > 0) {
				$rule["match"]["ndpi_app"] = $apps;
			}
			if (count($cats) > 0) {
				$rule["match"]["ndpi_category"] = $cats;
			}
			if (!empty($new_match_hosts)) {
				$rule["match"]["hosts"] = $new_match_hosts;
			}
			if (!empty($new_src_hosts)) {
				$rule["match"]["src_hosts"] = $new_src_hosts;
			}
			if (!empty($new_src_cidrs)) {
				$rule["match"]["src_cidrs"] = $new_src_cidrs;
			}
			if (!empty($new_ad_users)) {
				$rule["match"]["ad_users"] = $new_ad_users;
			}
			if (!empty($new_ad_groups)) {
				$rule["match"]["ad_groups"] = $new_ad_groups;
			}
			if (!empty($new_groups_sel)) {
				$rule["match"]["groups"] = $new_groups_sel;
			}
			$new_exc_cidrs = layer7_parse_cidr_textarea(
			    $_POST["new_src_exclude_cidrs"] ?? "");
			if (!empty($new_exc_cidrs)) {
				$rule["match"]["src_exclude_cidrs"] = $new_exc_cidrs;
			}
			$new_exc_groups = array();
			if (isset($_POST["new_src_exclude_groups"]) &&
			    is_array($_POST["new_src_exclude_groups"])) {
				foreach ($_POST["new_src_exclude_groups"] as $gv) {
					$gv = trim($gv);
					if ($gv !== "" && layer7_group_id_valid($gv)) {
						$new_exc_groups[] = $gv;
					}
				}
				$new_exc_groups = array_values(array_unique($new_exc_groups));
			}
			if (!empty($new_exc_groups)) {
				$rule["match"]["src_exclude_groups"] = $new_exc_groups;
			}
			if ($act === "tag") {
				$rule["tag_table"] = $tag_table;
			}
			$sched = layer7_parse_schedule_post("new");
			if ($sched !== null) {
				$rule["schedule"] = $sched;
			}
			if (!empty($_POST["new_scope_global"])) {
				$rule["scope_global"] = true;
			}
			if (!empty($_POST["new_quarantine_origin"])) {
				$rule["quarantine_origin"] = true;
			}
			if ($act === "block" && !layer7_policy_block_valid($rule)) {
				$input_errors[] = l7_t("Politica block sem criterios: indique hosts/app/categoria ou active quarentena/scope global.");
				$ok = false;
			}
			if ($ok && $act === "block" &&
			    layer7_enforcement_is_scoped_hybrid($data) &&
			    !layer7_policy_scoped_block_valid($rule, $data)) {
				$input_errors[] = l7_t("No modo scoped_hybrid, indique origem (IP/CIDR/grupo), active quarentena da origem ou confirme scope global.");
				$ok = false;
			}
			if ($ok && layer7_policy_src_include_exclude_conflict($rule,
			    isset($data["layer7"]["groups"]) ? $data["layer7"]["groups"] : array())) {
				$input_errors[] = l7_t("Origem nao pode estar simultaneamente incluida e excluida nesta politica.");
				$ok = false;
			}
			if ($ok) {
			$policies[] = $rule;
			if (layer7_save_json($data)) {
				layer7_pf_config_resync(true);
				$savemsg = l7_t("Politica adicionada.");
			}
			}
		}
		unset($policies);
}

if ($_POST["save_policies"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$count = count($policies);
		for ($i = 0; $i < $count; $i++) {
			$policies[$i]["enabled"] = isset($_POST["pon"][$i]);
		}
		unset($policies);
		if (layer7_save_json($data)) {
			layer7_pf_config_resync(true);
			$savemsg = l7_t("Politicas atualizadas.");
		}
}

if ($_POST["delete_policy"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$idx = (int)($_POST["delete_policy_index"] ?? -1);
		$count = count($policies);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = l7_t("Indice de politica invalido.");
		} else {
			array_splice($policies, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_pf_config_resync(true);
				$savemsg = l7_t("Politica removida.");
			}
		}
		unset($policies);
}

if ($_POST["save_policy_edit"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$idx = (int)($_POST["edit_policy_index"] ?? -1);
		$count = count($policies);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = l7_t("Indice de politica invalido.");
		} else {
			$layer7_policy_edit_retry = $idx;
			$orig = $policies[$idx];
			$pid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$name = trim($_POST["edit_name"] ?? "");
			if ($ok && strlen($name) > 160) {
				$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
				$ok = false;
			}

			$pri = (int)($_POST["edit_priority"] ?? 50);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = l7_t("Prioridade invalida (0-99999).");
				$ok = false;
			}

			$act = $_POST["edit_action"] ?? "monitor";
			if (!in_array($act, array("monitor", "allow", "block", "tag"), true)) {
				$act = "monitor";
			}

			if (isset($_POST["edit_ndpi_apps"]) && is_array($_POST["edit_ndpi_apps"])) {
				$apps = array_slice(array_filter(array_map('trim', $_POST["edit_ndpi_apps"]), 'strlen'), 0, 12);
			} else {
				$apps = layer7_split_csv_tokens($_POST["edit_ndpi_apps_csv"] ?? "", 12, 64);
			}
			if (isset($_POST["edit_ndpi_category"]) && is_array($_POST["edit_ndpi_category"])) {
				$cats = array_slice(array_filter(array_map('trim', $_POST["edit_ndpi_category"]), 'strlen'), 0, 8);
			} else {
				$cats = layer7_split_csv_tokens($_POST["edit_ndpi_category_csv"] ?? "", 8, 64);
			}
			if ($ok && ($apps === null || $cats === null)) {
				$input_errors[] = l7_t("App ou categoria: cada valor max. 64 caracteres.");
				$ok = false;
			}
			$edit_match_hosts_pre = layer7_parse_host_textarea($_POST["edit_match_hosts"] ?? "", 64);
			if ($ok && $apps !== null && $cats !== null &&
			    ($act === "block" || $act === "tag") &&
			    count($apps) + count($cats) === 0 &&
			    empty($edit_match_hosts_pre)) {
				$input_errors[] = l7_t("Para block ou tag, indique app nDPI, categoria e/ou sites/hosts.");
				$ok = false;
			}

			$tag_table = trim($_POST["edit_tag_table"] ?? "");
			if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
				$input_errors[] = l7_t("Tabela PF (tag): apenas A-Z, a-z, 0-9, _ (1-63 caracteres).");
				$ok = false;
			}

			$edit_rule_ifaces = array();
			if (isset($_POST["edit_ifaces"]) && is_array($_POST["edit_ifaces"])) {
				foreach ($_POST["edit_ifaces"] as $ifid) {
					$real = layer7_real_interface_name($ifid);
					if ($real !== "") {
						$edit_rule_ifaces[] = $real;
					}
				}
			}
			$edit_src_hosts = layer7_parse_ip_textarea($_POST["edit_src_hosts"] ?? "");
			$edit_src_cidrs = layer7_parse_cidr_textarea($_POST["edit_src_cidrs"] ?? "");
			$edit_match_hosts = layer7_parse_host_textarea($_POST["edit_match_hosts"] ?? "", 64);
			$edit_ad_users = $l7_has_identity
			    ? layer7_parse_ad_users_textarea($_POST["edit_ad_users"] ?? "")
			    : ((isset($orig["match"]["ad_users"]) && is_array($orig["match"]["ad_users"]))
				? $orig["match"]["ad_users"] : array());
			$edit_ad_groups = $l7_has_identity
			    ? layer7_parse_ad_groups_textarea($_POST["edit_ad_groups"] ?? "")
			    : ((isset($orig["match"]["ad_groups"]) && is_array($orig["match"]["ad_groups"]))
				? $orig["match"]["ad_groups"] : array());

			$edit_groups_sel = array();
			if (isset($_POST["edit_groups"]) && is_array($_POST["edit_groups"])) {
				foreach ($_POST["edit_groups"] as $gv) {
					$gv = trim($gv);
					if ($gv !== "" && layer7_group_id_valid($gv)) {
						$edit_groups_sel[] = $gv;
					}
				}
				$edit_groups_sel = array_values(array_unique($edit_groups_sel));
			}

			if ($ok && $apps !== null && $cats !== null) {
				$rule = array(
					"id" => $pid,
					"name" => $name !== "" ? $name : ($pid !== "" ? $pid : ("policy-" . $idx)),
					"enabled" => isset($_POST["edit_enabled"]),
					"action" => $act,
					"priority" => $pri,
					"match" => array()
				);
				if (!empty($edit_rule_ifaces)) {
					$rule["interfaces"] = array_values(array_unique($edit_rule_ifaces));
				}
				if (count($apps) > 0) {
					$rule["match"]["ndpi_app"] = $apps;
				}
				if (count($cats) > 0) {
					$rule["match"]["ndpi_category"] = $cats;
				}
				if (!empty($edit_match_hosts)) {
					$rule["match"]["hosts"] = $edit_match_hosts;
				}
				if (!empty($edit_src_hosts)) {
					$rule["match"]["src_hosts"] = $edit_src_hosts;
				}
				if (!empty($edit_src_cidrs)) {
					$rule["match"]["src_cidrs"] = $edit_src_cidrs;
				}
				if (!empty($edit_ad_users)) {
					$rule["match"]["ad_users"] = $edit_ad_users;
				}
				if (!empty($edit_ad_groups)) {
					$rule["match"]["ad_groups"] = $edit_ad_groups;
				}
				if (!empty($edit_groups_sel)) {
					$rule["match"]["groups"] = $edit_groups_sel;
				}
				$edit_exc_cidrs = layer7_parse_cidr_textarea(
				    $_POST["edit_src_exclude_cidrs"] ?? "");
				if (!empty($edit_exc_cidrs)) {
					$rule["match"]["src_exclude_cidrs"] = $edit_exc_cidrs;
				}
				$edit_exc_groups = array();
				if (isset($_POST["edit_src_exclude_groups"]) &&
				    is_array($_POST["edit_src_exclude_groups"])) {
					foreach ($_POST["edit_src_exclude_groups"] as $gv) {
						$gv = trim($gv);
						if ($gv !== "" && layer7_group_id_valid($gv)) {
							$edit_exc_groups[] = $gv;
						}
					}
					$edit_exc_groups = array_values(array_unique($edit_exc_groups));
				}
				if (!empty($edit_exc_groups)) {
					$rule["match"]["src_exclude_groups"] = $edit_exc_groups;
				}
				layer7_policy_stamp_adulto_match_mode($rule);
				if ($act === "tag") {
					$rule["tag_table"] = $tag_table;
				}
				$edit_sched = layer7_parse_schedule_post("edit");
				if ($edit_sched !== null) {
					$rule["schedule"] = $edit_sched;
				}
				if (!empty($_POST["edit_scope_global"])) {
					$rule["scope_global"] = true;
				}
				if (!empty($_POST["edit_quarantine_origin"])) {
					$rule["quarantine_origin"] = true;
				}
				if ($act === "block" && !layer7_policy_block_valid($rule)) {
					$input_errors[] = l7_t("Politica block sem criterios: indique hosts/app/categoria ou active quarentena/scope global.");
					$ok = false;
				}
				if ($ok && $act === "block" &&
				    layer7_enforcement_is_scoped_hybrid($data) &&
				    !layer7_policy_scoped_block_valid($rule, $data)) {
					$input_errors[] = l7_t("No modo scoped_hybrid, indique origem (IP/CIDR/grupo), active quarentena da origem ou confirme scope global.");
					$ok = false;
				}
				if ($ok && layer7_policy_src_include_exclude_conflict($rule,
				    isset($data["layer7"]["groups"]) ? $data["layer7"]["groups"] : array())) {
					$input_errors[] = l7_t("Origem nao pode estar simultaneamente incluida e excluida nesta politica.");
					$ok = false;
				}
				if ($ok) {
				$policies[$idx] = $rule;
				if (layer7_save_json($data)) {
					layer7_pf_config_resync(true);
					header("Location: layer7_policies.php");
					exit;
				}
				$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
				}
			}
		}
		unset($policies);
}

$data = layer7_load_or_default();
$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
	? $data["layer7"]["policies"] : array();
$at_limit = count($policies) >= 24;
$l7_profiles_catalog = layer7_load_profiles();
$l7_library_unavail_reason = null;
if ($at_limit) {
	$l7_library_unavail_reason = "limit24";
} elseif (empty($l7_profiles_catalog)) {
	$l7_library_unavail_reason = "empty_catalog";
}
$l7_library_available = ($l7_library_unavail_reason === null);

$edit_idx = null;
$edit_policy = null;
$view_idx = null;
$view_policy = null;
if ($layer7_policy_edit_retry !== null && $layer7_policy_edit_retry >= 0 &&
    $layer7_policy_edit_retry < count($policies)) {
	$edit_idx = (int)$layer7_policy_edit_retry;
	$edit_policy = $policies[$edit_idx];
} elseif (isset($_GET["edit"]) && ctype_digit((string)$_GET["edit"])) {
	$edit_candidate = (int)$_GET["edit"];
	if ($edit_candidate >= 0 && $edit_candidate < count($policies)) {
		$edit_idx = $edit_candidate;
		$edit_policy = $policies[$edit_candidate];
	}
}
if (isset($_GET["view"]) && ctype_digit((string)$_GET["view"])) {
	$view_candidate = (int)$_GET["view"];
	if ($view_candidate >= 0 && $view_candidate < count($policies)) {
		$view_idx = $view_candidate;
		$view_policy = $policies[$view_candidate];
	}
}

$layer7_policy_add_retry = !empty($_POST["add_policy"]) && !empty($input_errors);
$l7_profile_post = !empty($_POST["add_profile_policy"]) ||
	!empty($_POST["toggle_profile_on"]) ||
	!empty($_POST["toggle_profile_off"]) ||
	!empty($_POST["unhide_profile"]) ||
	!empty($_POST["save_profile_edit"]) ||
	!empty($_POST["delete_custom_profile"]) ||
	!empty($_POST["apply_profiles_batch"]);
$l7_profile_post_retry = $l7_profile_post && !empty($input_errors);
$l7_policy_mode = "list";
if ($edit_policy !== null && $edit_idx !== null) {
	$l7_policy_mode = "edit";
} elseif ($view_policy !== null && $view_idx !== null) {
	$l7_policy_mode = "view";
} elseif ($layer7_policy_add_retry ||
    (isset($_GET["new"]) && (string)$_GET["new"] !== "" && (string)$_GET["new"] !== "0")) {
	$l7_policy_mode = "new";
} elseif ($l7_profile_post_retry && !empty($_POST["add_profile_policy"])) {
	$l7_policy_mode = "profile_options";
} elseif (isset($_GET["profile_options"]) && trim((string)$_GET["profile_options"]) !== "") {
	$l7_policy_mode = "profile_options";
} elseif ($l7_profile_post_retry &&
    (!empty($_POST["save_profile_edit"]) || !empty($_POST["delete_custom_profile"]))) {
	$l7_policy_mode = "profile_edit";
} elseif (isset($_GET["profile_edit"]) && trim((string)$_GET["profile_edit"]) !== "") {
	$l7_policy_mode = "profile_edit";
} elseif (isset($_GET["profile_new"]) && (string)$_GET["profile_new"] !== "" &&
    (string)$_GET["profile_new"] !== "0") {
	$l7_policy_mode = "profile_edit";
} elseif (isset($_GET["library"]) && (string)$_GET["library"] !== "" && (string)$_GET["library"] !== "0") {
	$l7_policy_mode = "library";
} elseif ($l7_profile_post_retry) {
	$l7_policy_mode = "library";
}

$l7_legacy_library_redirect_ok = (
	$l7_policy_mode === "list" &&
	$l7_library_available &&
	(($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") &&
	empty($_POST) &&
	empty($input_errors)
);

$l7_groups = layer7_load_groups();

$l7_vip_exc = layer7_find_vip_exception($data);
$l7_vip_hosts_val = "";
$l7_vip_cidrs_val = "";
$l7_vip_groups_sel = array();
if (is_array($l7_vip_exc)) {
	if (!empty($l7_vip_exc["hosts"]) && is_array($l7_vip_exc["hosts"])) {
		$l7_vip_hosts_val = implode("\n", $l7_vip_exc["hosts"]);
	}
	if (!empty($l7_vip_exc["cidrs"]) && is_array($l7_vip_exc["cidrs"])) {
		$l7_vip_cidrs_val = implode("\n", $l7_vip_exc["cidrs"]);
	}
	if (!empty($l7_vip_exc["source_groups"]) && is_array($l7_vip_exc["source_groups"])) {
		$l7_vip_groups_sel = $l7_vip_exc["source_groups"];
	}
}

$l7_profile_options_id = null;
$l7_profile_options_profile = null;
$l7_po_req_id = "";
if (!empty($_POST["add_profile_policy"]) && $l7_profile_post_retry) {
	$l7_po_req_id = trim((string)($_POST["profile_id"] ?? ""));
} elseif (isset($_GET["profile_options"])) {
	$l7_po_req_id = trim((string)$_GET["profile_options"]);
}
if ($l7_po_req_id !== "") {
	$l7_profile_options_id = $l7_po_req_id;
	$l7_profile_options_profile = layer7_policies_find_profile($l7_po_req_id);
}
$l7_po_from_post = !empty($_POST["add_profile_policy"]) && $l7_profile_post_retry;
$l7_profile_options_state = layer7_profile_options_state($l7_po_from_post, array(
	"profile_id" => $l7_profile_options_id ?? "",
	"vip_groups" => $l7_vip_groups_sel,
	"vip_hosts" => $l7_vip_hosts_val,
	"vip_cidrs" => $l7_vip_cidrs_val,
));

$l7_profile_edit_id = null;
$l7_profile_edit_profile = null;
$l7_pe_from_post = false;
$l7_pe_is_new = false;
$l7_pe_req_id = "";
if (!empty($_POST["save_profile_edit"]) && $l7_profile_post_retry) {
	$l7_pe_from_post = true;
	$l7_pe_is_new = !empty($_POST["edit_profile_is_new"]);
	$l7_pe_req_id = trim((string)($_POST["edit_profile_id"] ?? ""));
} elseif (!empty($_POST["delete_custom_profile"]) && $l7_profile_post_retry) {
	$l7_pe_from_post = true;
	$l7_pe_is_new = false;
	$l7_pe_req_id = trim((string)($_POST["edit_profile_id"] ?? ""));
} elseif (isset($_GET["profile_new"]) && (string)$_GET["profile_new"] !== "" &&
    (string)$_GET["profile_new"] !== "0") {
	$l7_pe_is_new = true;
} elseif (isset($_GET["profile_edit"])) {
	$l7_pe_req_id = trim((string)$_GET["profile_edit"]);
}
if ($l7_pe_req_id !== "" && !$l7_pe_is_new) {
	$l7_profile_edit_id = $l7_pe_req_id;
	$l7_profile_edit_profile = layer7_policies_find_profile($l7_pe_req_id);
}
$l7_profiles_custom_for_edit = layer7_profiles_custom_load();
$l7_profile_edit_state = layer7_profile_edit_state($l7_pe_from_post, array(
	"profile" => $l7_profile_edit_profile,
	"is_new" => $l7_pe_is_new,
	"policies" => $policies,
	"custom_raw" => $l7_profiles_custom_for_edit,
));

$ndpi_list = layer7_ndpi_list();
$ndpi_protos = isset($ndpi_list["protocols"]) ? $ndpi_list["protocols"] : array();
$ndpi_cats = isset($ndpi_list["categories"]) ? $ndpi_list["categories"] : array();
sort($ndpi_protos);
sort($ndpi_cats);

$l7_edit_retry = (!empty($input_errors) && ($_POST["save_policy_edit"] ?? false));
$l7_add_retry = $layer7_policy_add_retry;
$l7_pf_ifaces = layer7_get_pfsense_interfaces();
$l7_action_opts = array(
	"monitor" => l7_t("monitor"),
	"allow" => l7_t("allow"),
	"block" => l7_t("block"),
	"tag" => l7_t("tag"),
);
$l7_sched_days = array(
	"mon" => "Seg",
	"tue" => "Ter",
	"wed" => "Qua",
	"thu" => "Qui",
	"fri" => "Sex",
	"sat" => "Sab",
	"sun" => "Dom",
);

function layer7_policies_posted_text($key)
{
	return array_key_exists($key, $_POST) ? (string)$_POST[$key] : "";
}
function layer7_policies_posted_list($key)
{
	if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
		return array();
	}
	$out = array();
	foreach ($_POST[$key] as $v) {
		$out[] = (string)$v;
	}
	return $out;
}
function layer7_policies_posted_checked($key)
{
	return isset($_POST[$key]) && (string)$_POST[$key] !== "" && (string)$_POST[$key] !== "0";
}
function layer7_policies_lines($arr)
{
	return (is_array($arr) && count($arr) > 0) ? implode("\n", $arr) : "";
}
function layer7_policies_iface_selected($ifc, $selected, $from_post)
{
	if (!is_array($selected)) {
		return false;
	}
	if ($from_post) {
		return in_array($ifc["ifid"], $selected, true);
	}
	return in_array($ifc["real"], $selected, true) ||
		in_array($ifc["ifid"], $selected, true);
}
function layer7_policies_ifaces_html($list_id, $input_name, $ifaces, $selected, $from_post, $help)
{
	$html = '<div class="l7-bulk-tools">';
	$html .= '<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'' .
		htmlspecialchars($list_id, ENT_QUOTES, "UTF-8") . '\', true);">' .
		htmlspecialchars(l7_t("Selecionar tudo")) . '</button> ';
	$html .= '<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'' .
		htmlspecialchars($list_id, ENT_QUOTES, "UTF-8") . '\', false);">' .
		htmlspecialchars(l7_t("Limpar")) . '</button>';
	$html .= '</div><div id="' . htmlspecialchars($list_id) . '">';
	foreach ($ifaces as $ifc) {
		$chk = layer7_policies_iface_selected($ifc, $selected, $from_post)
			? ' checked="checked"' : '';
		$html .= '<label class="checkbox-inline">';
		$html .= '<input type="checkbox" name="' . htmlspecialchars($input_name) .
			'" value="' . htmlspecialchars($ifc["ifid"]) . '"' . $chk . ' /> ';
		$html .= htmlspecialchars($ifc["descr"]) .
			' <span class="text-muted">(' . htmlspecialchars($ifc["real"]) . ')</span>';
		$html .= '</label>';
	}
	$html .= '</div><p class="help-block">' . htmlspecialchars($help) . '</p>';
	return $html;
}
function layer7_policies_group_boxes_html($list_id, $input_name, $groups, $selected, $show_id)
{
	$html = '<div class="l7-multiselect-wrap" id="' . htmlspecialchars($list_id) . '">';
	foreach ($groups as $grp) {
		$gid_raw = isset($grp["id"]) ? (string)$grp["id"] : "";
		$gid = htmlspecialchars($gid_raw);
		$gname = htmlspecialchars(isset($grp["name"]) ? (string)$grp["name"] : $gid_raw);
		$gchk = in_array($gid_raw, (array)$selected, true) ? ' checked="checked"' : '';
		$html .= '<label><input type="checkbox" name="' . htmlspecialchars($input_name) .
			'" value="' . $gid . '"' . $gchk . ' /> ' . $gname;
		if ($show_id) {
			$html .= ' <span class="text-muted">(' . $gid . ')</span>';
		}
		$html .= '</label>';
	}
	$html .= '</div>';
	return $html;
}
function layer7_policies_ndpi_list_html($list_id, $input_name, $items, $selected, $filter_ph)
{
	$filter_id = $list_id . "_filter";
	$html = '<label class="sr-only" for="' . htmlspecialchars($filter_id) . '">' .
		htmlspecialchars($filter_ph) . '</label>';
	$html .= '<input type="text" id="' . htmlspecialchars($filter_id) .
		'" class="form-control l7-filter" placeholder="' .
		htmlspecialchars($filter_ph) . '" aria-label="' . htmlspecialchars($filter_ph) .
		'" onkeyup="l7filter(this,\'' .
		htmlspecialchars($list_id, ENT_QUOTES, "UTF-8") .
		'\')" />';
	$html .= '<div class="l7-bulk-tools">';
	$html .= '<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'' .
		htmlspecialchars($list_id, ENT_QUOTES, "UTF-8") . '\', true, true);">' .
		htmlspecialchars(l7_t("Selecionar visiveis")) . '</button> ';
	$html .= '<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'' .
		htmlspecialchars($list_id, ENT_QUOTES, "UTF-8") . '\', false, false);">' .
		htmlspecialchars(l7_t("Limpar tudo")) . '</button>';
	$html .= '</div><div class="l7-multiselect-wrap" id="' . htmlspecialchars($list_id) . '">';
	foreach ($items as $item) {
		$chk = in_array($item, (array)$selected, true) ? ' checked="checked"' : '';
		$html .= '<label><input type="checkbox" name="' . htmlspecialchars($input_name) .
			'" value="' . htmlspecialchars($item) . '"' . $chk . ' /> ' .
			htmlspecialchars($item) . '</label>';
	}
	$html .= '</div>';
	return $html;
}
function layer7_policies_schedule_html($prefix, $days_map, $selected, $start, $end)
{
	$html = "";
	foreach ($days_map as $dk => $dl) {
		$chk = in_array($dk, (array)$selected, true) ? ' checked="checked"' : '';
		$html .= '<label class="checkbox-inline">';
		$html .= '<input type="checkbox" name="' . htmlspecialchars($prefix . "_sched_" . $dk) .
			'" value="1"' . $chk . ' /> ' . htmlspecialchars($dl);
		$html .= '</label>';
	}
	$start_name = $prefix . "_sched_start";
	$end_name = $prefix . "_sched_end";
	$html .= '<div class="l7-schedule-times form-inline">';
	$html .= '<label class="control-label" for="' . htmlspecialchars($start_name) . '">' .
		htmlspecialchars(l7_t("De")) . '</label> ';
	$html .= '<input type="time" class="form-control input-sm" name="' .
		htmlspecialchars($start_name) . '" id="' . htmlspecialchars($start_name) .
		'" value="' . htmlspecialchars($start) . '" /> ';
	$html .= '<label class="control-label" for="' . htmlspecialchars($end_name) . '">' .
		htmlspecialchars(l7_t("ate")) . '</label> ';
	$html .= '<input type="time" class="form-control input-sm" name="' .
		htmlspecialchars($end_name) . '" id="' . htmlspecialchars($end_name) .
		'" value="' . htmlspecialchars($end) . '" />';
	$html .= '</div>';
	$html .= '<p class="help-block">' .
		htmlspecialchars(l7_t("Vazio = sempre activa. Preencha dias + horas para restringir.")) .
		'</p>';
	return $html;
}
function layer7_policies_find_profile($profile_id)
{
	$profile_id = trim((string)$profile_id);
	if ($profile_id === "") {
		return null;
	}
	foreach (layer7_load_profiles(true) as $p) {
		if (isset($p["id"]) && (string)$p["id"] === $profile_id) {
			return $p;
		}
	}
	return null;
}
function layer7_profile_options_state($from_post, $defaults)
{
	if ($from_post) {
		$act = trim((string)($_POST["profile_action"] ?? "block"));
		if (!in_array($act, array("monitor", "allow", "block"), true)) {
			$act = "block";
		}
		return array(
			"profile_id" => trim((string)($_POST["profile_id"] ?? "")),
			"action" => $act,
			"ifaces" => layer7_policies_posted_list("profile_ifaces"),
			"groups" => layer7_policies_posted_list("profile_groups"),
			"vip_groups" => layer7_policies_posted_list("profile_vip_groups"),
			"vip_hosts" => layer7_policies_posted_text("profile_vip_hosts"),
			"vip_cidrs" => layer7_policies_posted_text("profile_vip_cidrs"),
			"src_cidrs" => layer7_policies_posted_text("profile_src_cidrs"),
			"exc_groups" => layer7_policies_posted_list("profile_src_exclude_groups"),
			"exc_cidrs" => layer7_policies_posted_text("profile_src_exclude_cidrs"),
		);
	}
	return array(
		"profile_id" => (string)($defaults["profile_id"] ?? ""),
		"action" => "block",
		"ifaces" => array(),
		"groups" => array(),
		"vip_groups" => (array)($defaults["vip_groups"] ?? array()),
		"vip_hosts" => (string)($defaults["vip_hosts"] ?? ""),
		"vip_cidrs" => (string)($defaults["vip_cidrs"] ?? ""),
		"src_cidrs" => "",
		"exc_groups" => array(),
		"exc_cidrs" => "",
	);
}
function layer7_profile_options_ifaces_html($ifaces, $selected)
{
	$html = '<p class="text-muted small"><strong>' . htmlspecialchars(l7_t("Interfaces")) . '</strong></p>';
	foreach ($ifaces as $ifc) {
		$chk = in_array($ifc["ifid"], (array)$selected, true) ? ' checked="checked"' : '';
		$html .= '<label class="checkbox-inline">';
		$html .= '<input type="checkbox" name="profile_ifaces[]" value="' .
			htmlspecialchars($ifc["ifid"]) . '"' . $chk . ' /> ';
		$html .= htmlspecialchars($ifc["descr"]);
		$html .= '</label>';
	}
	$html .= '<p class="help-block">' . htmlspecialchars(l7_t("Nenhuma = todas.")) . '</p>';
	return $html;
}
function layer7_profile_options_groups_html($groups, $input_name, $selected, $heading)
{
	if (empty($groups)) {
		return "";
	}
	$html = '<p class="text-muted small"><strong>' .
		htmlspecialchars($heading) . '</strong></p>';
	foreach ($groups as $grp) {
		$gid_raw = isset($grp["id"]) ? (string)$grp["id"] : "";
		$gid = htmlspecialchars($gid_raw);
		$gname = htmlspecialchars(isset($grp["name"]) ? (string)$grp["name"] : $gid_raw);
		$gchk = in_array($gid_raw, (array)$selected, true) ? ' checked="checked"' : '';
		$html .= '<label class="checkbox-inline">';
		$html .= '<input type="checkbox" name="' . htmlspecialchars($input_name) .
			'" value="' . $gid . '"' . $gchk . ' /> ' . $gname;
		$html .= '</label>';
	}
	return $html;
}
function layer7_render_profile_options_form($state, $ifaces, $groups, $ctx)
{
	$form = new Form(false);
	$form->setAction("layer7_policies.php#l7-policies");

	$sec_main = new Form_Section("NOTITLE", "", 0);
	$pid_in = new Form_Input("profile_id", "", "hidden", $state["profile_id"]);
	if ($ctx === "modal") {
		$pid_in->setAttribute("id", "l7ProfileId");
	}
	$sec_main->addInput($pid_in);
	$sec_main->addInput(new Form_Input("add_profile_policy", "", "hidden", "1"));
	$action_opts = array(
		"block" => l7_t("block"),
		"monitor" => l7_t("monitor"),
		"allow" => l7_t("allow"),
	);
	$sec_main->addInput(new Form_Select(
		"profile_action",
		l7_t("Accao"),
		$state["action"],
		$action_opts
	));
	$apply_html = layer7_profile_options_ifaces_html($ifaces, $state["ifaces"]);
	if (empty($groups)) {
		$apply_html .= '<p class="text-muted small"><strong>' .
			htmlspecialchars(l7_t("Grupos")) . '</strong></p>';
		$apply_html .= '<p class="help-block"><a href="layer7_groups.php" class="btn btn-xs btn-default">' .
			htmlspecialchars(l7_t("Criar grupo (ex.: Gestores)")) . '</a></p>';
	} else {
		$apply_html .= layer7_profile_options_groups_html(
			$groups,
			"profile_groups[]",
			$state["groups"],
			l7_t("Grupos")
		);
		$apply_html .= '<p class="help-block">' .
			htmlspecialchars(l7_t("Preferivel a CIDRs manuais.")) . '</p>';
	}
	$sec_main->addInput(new Form_StaticText(l7_t("Aplicar a"), $apply_html));
	$form->add($sec_main);

	$sec_vip = new Form_Section(l7_t("Isentos (nunca bloqueados)"), "l7ProfileOptionsVip", 0);
	$sec_vip->addClass("l7-modal-section-vip");
	$vip_intro = '<p class="help-block">' .
		htmlspecialchars(l7_t("Isencao global: estes IPs/dispositivos nunca sao bloqueados por nenhum perfil Layer7. Gere a excepcao partilhada vip-isentos.")) .
		'</p>';
	$vip_intro .= '<p class="help-block"><a href="layer7_exceptions.php#l7-vip-list" class="btn btn-xs btn-default">' .
		'<i class="fa fa-list"></i> ' . htmlspecialchars(l7_t("Gerir Lista VIP")) . '</a></p>';
	$vip_intro .= '<p class="help-block text-warning small">' .
		htmlspecialchars(l7_t("Isencao total de bloqueios Layer7; sinkhole DNS so coberto apos Bloco D.")) .
		'</p>';
	if (!empty($groups)) {
		$vip_intro .= layer7_profile_options_groups_html(
			$groups,
			"profile_vip_groups[]",
			$state["vip_groups"],
			l7_t("Grupos isentos")
		);
	}
	$sec_vip->addInput(new Form_StaticText("", $vip_intro));
	$vip_hosts = new Form_Textarea("profile_vip_hosts", l7_t("IPs isentos"), $state["vip_hosts"]);
	$vip_hosts->setRows(2);
	$vip_hosts->setAttribute("placeholder", "192.168.1.50");
	$sec_vip->addInput($vip_hosts);
	$vip_cidrs = new Form_Textarea("profile_vip_cidrs", l7_t("CIDRs isentos"), $state["vip_cidrs"]);
	$vip_cidrs->setRows(2);
	$vip_cidrs->setAttribute("placeholder", "192.168.1.0/24");
	$vip_cidrs->setHelp(l7_t("Desligar um perfil nao remove a excepcao VIP — continua editavel em Excepcoes."));
	$sec_vip->addInput($vip_cidrs);
	$form->add($sec_vip);

	$sec_adv = new Form_Section(l7_t("Avancado"), "l7ProfileModalAdvanced", COLLAPSIBLE);
	$src_cidrs = new Form_Textarea("profile_src_cidrs", l7_t("CIDRs de origem"), $state["src_cidrs"]);
	$src_cidrs->setRows(2);
	$src_cidrs->setAttribute("placeholder", "192.168.10.0/24");
	$src_cidrs->setHelp(l7_t("Vazio = qualquer sub-rede. Use apenas se grupos nao forem suficientes."));
	$sec_adv->addInput($src_cidrs);
	$exc_html = '<p class="help-block">' .
		htmlspecialchars(l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis.")) .
		'</p>';
	if (!empty($groups)) {
		$exc_html .= layer7_profile_options_groups_html(
			$groups,
			"profile_src_exclude_groups[]",
			$state["exc_groups"],
			l7_t("Grupos excluidos")
		);
	}
	$sec_adv->addInput(new Form_StaticText(l7_t("Excluir origens (so este perfil)"), $exc_html));
	$exc_cidrs = new Form_Textarea("profile_src_exclude_cidrs", l7_t("CIDRs excluidos"), $state["exc_cidrs"]);
	$exc_cidrs->setRows(2);
	$exc_cidrs->setAttribute("placeholder", "192.168.1.50");
	$sec_adv->addInput($exc_cidrs);
	$form->add($sec_adv);

	$sec_actions = new Form_Section("NOTITLE", "", 0);
	$ver_html = '<a href="layer7_test.php" class="btn btn-link btn-sm">' .
		'<i class="fa fa-search"></i> ' . htmlspecialchars(l7_t("Verificador de politica efectiva")) . '</a>';
	$sec_actions->addInput(new Form_StaticText("", $ver_html));
	$btns = '<button type="submit" class="btn btn-success">' .
		htmlspecialchars(l7_t("Criar politica")) . '</button> ';
	if ($ctx === "modal") {
		$btns .= '<button type="button" class="btn btn-default" onclick="l7hideProfileModal();">' .
			htmlspecialchars(l7_t("Cancelar")) . '</button>';
	} else {
		$btns .= '<a href="layer7_policies.php?library=1#l7-profiles" class="btn btn-default">' .
			htmlspecialchars(l7_t("Cancelar")) . '</a>';
	}
	$sec_actions->addInput(new Form_StaticText("", $btns));
	$form->add($sec_actions);

	ob_start();
	print($form);
	return ob_get_clean();
}
function layer7_profile_edit_connected($profile_id, $policies)
{
	if ($profile_id === "") {
		return false;
	}
	$pid = "profile-" . $profile_id;
	foreach ($policies as $existing) {
		if (isset($existing["id"]) && (string)$existing["id"] === $pid) {
			return true;
		}
	}
	return false;
}
function layer7_profile_edit_hidden_flag($profile_id, $prof, $custom_raw)
{
	if (layer7_profile_custom_id_valid($profile_id)) {
		return !empty($prof["hidden"]);
	}
	if (isset($custom_raw["overrides"][$profile_id]["hidden"])) {
		return !empty($custom_raw["overrides"][$profile_id]["hidden"]);
	}
	return false;
}
function layer7_profile_edit_state($from_post, $defaults)
{
	$policies = (array)($defaults["policies"] ?? array());
	$custom_raw = (array)($defaults["custom_raw"] ?? array());
	if ($from_post) {
		$profile_id = trim((string)($_POST["edit_profile_id"] ?? ""));
		$is_new = !empty($_POST["edit_profile_is_new"]);
		$is_custom = $is_new || layer7_profile_custom_id_valid($profile_id);
		return array(
			"profile_id" => $profile_id,
			"is_new" => $is_new,
			"name" => layer7_policies_posted_text("edit_profile_name"),
			"description" => layer7_policies_posted_text("edit_profile_description"),
			"icon" => layer7_policies_posted_text("edit_profile_icon"),
			"apps" => layer7_policies_posted_list("edit_profile_apps"),
			"cats" => layer7_policies_posted_list("edit_profile_cats"),
			"hosts" => layer7_policies_posted_text("edit_profile_hosts"),
			"hidden" => layer7_policies_posted_checked("edit_profile_hidden"),
			"is_custom" => $is_custom,
			"connected" => layer7_profile_edit_connected($profile_id, $policies),
			"show_delete" => !$is_new && $is_custom,
		);
	}
	if (!empty($defaults["is_new"])) {
		return array(
			"profile_id" => "",
			"is_new" => true,
			"name" => "",
			"description" => "",
			"icon" => "fa-cube",
			"apps" => array(),
			"cats" => array(),
			"hosts" => "",
			"hidden" => false,
			"is_custom" => true,
			"connected" => false,
			"show_delete" => false,
		);
	}
	$prof = $defaults["profile"] ?? null;
	if (!is_array($prof)) {
		return null;
	}
	$profile_id = (string)($prof["id"] ?? "");
	$is_custom = layer7_profile_custom_id_valid($profile_id);
	return array(
		"profile_id" => $profile_id,
		"is_new" => false,
		"name" => (string)($prof["name"] ?? ""),
		"description" => (string)($prof["description"] ?? ""),
		"icon" => (string)($prof["icon"] ?? "fa-cube"),
		"apps" => (isset($prof["ndpi_apps"]) && is_array($prof["ndpi_apps"])) ? $prof["ndpi_apps"] : array(),
		"cats" => (isset($prof["ndpi_categories"]) && is_array($prof["ndpi_categories"])) ? $prof["ndpi_categories"] : array(),
		"hosts" => (isset($prof["hosts"]) && is_array($prof["hosts"])) ? implode("\n", $prof["hosts"]) : "",
		"hidden" => layer7_profile_edit_hidden_flag($profile_id, $prof, $custom_raw),
		"is_custom" => $is_custom,
		"connected" => layer7_profile_edit_connected($profile_id, $policies),
		"show_delete" => $is_custom,
	);
}
function layer7_profile_edit_confirm_map($profile_id, $policies, $prof, $custom_raw)
{
	$profile_id = trim((string)$profile_id);
	if ($profile_id === "" || !is_array($prof)) {
		return array();
	}
	$is_custom = layer7_profile_custom_id_valid($profile_id);
	return array(
		$profile_id => array(
			"id" => $profile_id,
			"name" => (string)($prof["name"] ?? ""),
			"description" => (string)($prof["description"] ?? ""),
			"icon" => (string)($prof["icon"] ?? "fa-cube"),
			"apps" => (isset($prof["ndpi_apps"]) && is_array($prof["ndpi_apps"])) ? $prof["ndpi_apps"] : array(),
			"cats" => (isset($prof["ndpi_categories"]) && is_array($prof["ndpi_categories"])) ? $prof["ndpi_categories"] : array(),
			"hosts" => (isset($prof["hosts"]) && is_array($prof["hosts"])) ? implode("\n", $prof["hosts"]) : "",
			"is_custom" => $is_custom,
			"has_override" => layer7_profile_has_override($profile_id, $custom_raw),
			"hidden" => layer7_profile_edit_hidden_flag($profile_id, $prof, $custom_raw),
			"connected" => layer7_profile_edit_connected($profile_id, $policies),
		),
	);
}
function layer7_profile_edit_filter_html($input_id, $list_id, $label, $placeholder)
{
	$html = '<label for="' . htmlspecialchars($input_id) . '">' .
		htmlspecialchars($label) . '</label>';
	$html .= '<div class="input-group l7-edit-filter-group">';
	$html .= '<input type="text" id="' . htmlspecialchars($input_id) .
		'" class="form-control l7-filter" placeholder="' . htmlspecialchars($placeholder) .
		'" onkeyup="l7filter(this,' . htmlspecialchars(json_encode($list_id), ENT_QUOTES) . ')" />';
	$html .= '<span class="input-group-btn"><button type="button" class="btn btn-default" onclick="l7clearEditFilter(' .
		htmlspecialchars(json_encode($input_id), ENT_QUOTES) . ',' .
		htmlspecialchars(json_encode($list_id), ENT_QUOTES) . ');">' .
		htmlspecialchars(l7_t("Limpar")) . '</button></span>';
	$html .= '</div>';
	return $html;
}
function layer7_profile_edit_set_dom_hidden($element, $hidden)
{
	if (!$element) {
		return;
	}
	if ($hidden) {
		$element->setAttribute("hidden", "hidden");
		$element->addClass("hidden");
	} else {
		$element->setAttribute("hidden", null);
		$element->removeClass("hidden");
	}
}

function layer7_profile_edit_checkbox_html($input_name, $catalog_items, $selected, $list_id)
{
	$sel = array();
	foreach ((array)$selected as $v) {
		$sel[(string)$v] = true;
	}
	$html = '<div class="l7-multiselect-wrap" id="' . htmlspecialchars($list_id) . '">';
	foreach ($catalog_items as $item) {
		$val = (string)$item;
		$chk = isset($sel[$val]) ? ' checked="checked"' : '';
		$html .= '<label><input type="checkbox" name="' . htmlspecialchars($input_name) .
			'" value="' . htmlspecialchars($val) . '"' . $chk . ' /> ' .
			htmlspecialchars($val) . '</label>';
	}
	$html .= '</div>';
	return $html;
}
function layer7_render_profile_edit_form($state, $catalog, $ctx)
{
	$show_custom = !empty($state["is_new"]) || !empty($state["is_custom"]);
	$show_factory = empty($state["is_new"]) && empty($state["is_custom"]);
	$show_delete = ($ctx === "page" && !empty($state["show_delete"]));
	$icon_val = array_key_exists("icon", $state) ? (string)$state["icon"] : "fa-cube";

	$form = new Form(false);
	$form->setAction("layer7_policies.php#l7-policies");
	$form->setAttribute("id", "l7ProfileEditForm");
	$form->setAttribute("onsubmit", "return l7confirmProfileEditSave(event);");

	$sec_hidden = new Form_Section("NOTITLE", "", 0);
	$pid_in = new Form_Input(
		"edit_profile_id",
		"",
		"hidden",
		(string)($state["profile_id"] ?? "")
	);
	$pid_in->setAttribute("id", "l7EditProfileId");
	$sec_hidden->addInput($pid_in);
	$is_new_in = new Form_Input(
		"edit_profile_is_new",
		"",
		"hidden",
		!empty($state["is_new"]) ? "1" : "0"
	);
	$is_new_in->setAttribute("id", "l7EditProfileIsNew");
	$sec_hidden->addInput($is_new_in);
	$form->add($sec_hidden);

	$sec_custom = new Form_Section("NOTITLE", "", 0);
	$sec_custom->addClass("l7-edit-custom-only");
	if (!$show_custom) {
		layer7_profile_edit_set_dom_hidden($sec_custom, true);
	}
	$name_in = new Form_Input(
		"edit_profile_name",
		l7_t("Nome"),
		"text",
		(string)($state["name"] ?? "")
	);
	$name_in->setAttribute("id", "l7EditProfileName");
	$name_in->setAttribute("maxlength", "120");
	$sec_custom->addInput($name_in);
	$desc_in = new Form_Input(
		"edit_profile_description",
		l7_t("Descricao"),
		"text",
		(string)($state["description"] ?? "")
	);
	$desc_in->setAttribute("id", "l7EditProfileDesc");
	$desc_in->setAttribute("maxlength", "400");
	$sec_custom->addInput($desc_in);
	$icon_in = new Form_Input(
		"edit_profile_icon",
		l7_t("Icone FA 4.7"),
		"text",
		$icon_val
	);
	$icon_in->setAttribute("id", "l7EditProfileIcon");
	$icon_in->setAttribute("placeholder", "fa-cube");
	$icon_in->setAttribute("maxlength", "45");
	$icon_in->setHelp(l7_t("Ex.: fa-youtube, fa-facebook (FontAwesome 4.7)."));
	$sec_custom->addInput($icon_in);
	$form->add($sec_custom);

	$sec_factory = new Form_Section("NOTITLE", "", 0);
	$sec_factory->addClass("l7-edit-factory-note");
	if (!$show_factory) {
		layer7_profile_edit_set_dom_hidden($sec_factory, true);
	}
	$sec_factory->addInput(new Form_StaticText(
		"",
		'<p class="help-block">' .
		htmlspecialchars(l7_t("Perfil de fabrica: edite apps e hosts (overlay). O catalogo de fabrica permanece intacto nos upgrades.")) .
		'</p>'
	));
	$form->add($sec_factory);

	$sec_apps = new Form_Section("NOTITLE", "", 0);
	$apps_html = layer7_profile_edit_filter_html(
		"l7EditAppsFilter",
		"l7EditAppsList",
		l7_t("Pesquisar apps"),
		l7_t("Pesquisar apps...")
	);
	$apps_html .= layer7_profile_edit_checkbox_html(
		"edit_profile_apps[]",
		$catalog["apps"] ?? array(),
		$state["apps"] ?? array(),
		"l7EditAppsList"
	);
	$sec_apps->addInput(new Form_StaticText(
		l7_t("Apps nDPI"),
		$apps_html .
		'<p class="help-block">' .
		htmlspecialchars(l7_t("Selecao do catalogo de fabrica (max. 64).")) .
		'</p>'
	));
	$form->add($sec_apps);

	$sec_cats = new Form_Section("NOTITLE", "", 0);
	$sec_cats->addClass("l7-edit-custom-only");
	if (!$show_custom) {
		layer7_profile_edit_set_dom_hidden($sec_cats, true);
	}
	$cats_html = layer7_profile_edit_filter_html(
		"l7EditCatsFilter",
		"l7EditCatsList",
		l7_t("Pesquisar categorias"),
		l7_t("Pesquisar categorias...")
	);
	$cats_html .= layer7_profile_edit_checkbox_html(
		"edit_profile_cats[]",
		$catalog["categories"] ?? array(),
		$state["cats"] ?? array(),
		"l7EditCatsList"
	);
	$sec_cats->addInput(new Form_StaticText(
		l7_t("Categorias nDPI"),
		$cats_html .
		'<p class="help-block">' . htmlspecialchars(l7_t("Max. 8 categorias.")) . '</p>'
	));
	$form->add($sec_cats);

	$sec_hosts = new Form_Section("NOTITLE", "", 0);
	$hosts_in = new Form_Textarea(
		"edit_profile_hosts",
		l7_t("Hosts / dominios"),
		(string)($state["hosts"] ?? "")
	);
	$hosts_in->setAttribute("id", "l7EditProfileHosts");
	$hosts_in->setRows(4);
	$hosts_in->setAttribute("placeholder", "example.com");
	$hosts_in->setHelp(l7_t("Um dominio por linha (max. 64). Texto livre validado."));
	$sec_hosts->addInput($hosts_in);
	$form->add($sec_hosts);

	$sec_hide = new Form_Section("NOTITLE", "", 0);
	$hidden_cb = new Form_Checkbox(
		"edit_profile_hidden",
		l7_t("Ocultar perfil"),
		l7_t("Ocultar da biblioteca (politica ligada permanece activa)"),
		!empty($state["hidden"]),
		"1"
	);
	$hidden_cb->setAttribute("id", "l7EditProfileHidden");
	$sec_hide->addInput($hidden_cb);
	$form->add($sec_hide);

	$sec_actions = new Form_Section("NOTITLE", "", 0);
	$btns = '<button type="submit" name="save_profile_edit" value="1" class="btn btn-success">' .
		htmlspecialchars(l7_t("Guardar")) . '</button> ';
	if ($ctx === "modal") {
		$btns .= '<button type="button" class="btn btn-default" onclick="l7hideProfileEditModal();">' .
			htmlspecialchars(l7_t("Cancelar")) . '</button>';
	} else {
		$btns .= '<a href="layer7_policies.php?library=1#l7-profiles" class="btn btn-default">' .
			htmlspecialchars(l7_t("Cancelar")) . '</a>';
	}
	$delete_btn_class = "btn btn-danger" . ($show_delete ? "" : " hidden");
	$delete_btn_hidden = $show_delete ? "" : ' hidden';
	$btns .= ' <button type="submit" name="delete_custom_profile" value="1" id="l7EditProfileDeleteBtn" class="' .
		$delete_btn_class . '"' . $delete_btn_hidden .
		' onclick="return confirm(' .
		htmlspecialchars(json_encode(l7_t("Apagar este perfil personalizado?")), ENT_QUOTES) . ');">' .
		htmlspecialchars(l7_t("Apagar")) . '</button>';
	$sec_actions->addInput(new Form_StaticText("", $btns));
	$form->add($sec_actions);

	ob_start();
	print($form);
	return ob_get_clean();
}

$l7_ef = array(
	"id" => "",
	"name" => "",
	"priority" => "0",
	"action" => "monitor",
	"enabled" => false,
	"ifaces" => array(),
	"ifaces_from_post" => false,
	"src_hosts" => "",
	"src_cidrs" => "",
	"ad_users" => "",
	"ad_groups" => "",
	"groups" => array(),
	"exc_groups" => array(),
	"exc_cidrs" => "",
	"hosts" => "",
	"apps" => array(),
	"apps_csv" => "",
	"cats" => array(),
	"cats_csv" => "",
	"tag_table" => "layer7_tagged",
	"sched_days" => array(),
	"sched_start" => "",
	"sched_end" => "",
	"scope_global" => false,
	"quarantine" => false,
);
if ($edit_policy !== null) {
	$l7_ef["id"] = isset($edit_policy["id"]) ? (string)$edit_policy["id"] : "";
	$l7_ef["name"] = isset($edit_policy["name"]) ? (string)$edit_policy["name"] : "";
	$l7_ef["priority"] = isset($edit_policy["priority"]) ? (string)(int)$edit_policy["priority"] : "0";
	$l7_ef["action"] = isset($edit_policy["action"]) ? (string)$edit_policy["action"] : "monitor";
	if (!isset($l7_action_opts[$l7_ef["action"]])) {
		$l7_ef["action"] = "monitor";
	}
	$l7_ef["enabled"] = !empty($edit_policy["enabled"]);
	$l7_ef["ifaces"] = (isset($edit_policy["interfaces"]) && is_array($edit_policy["interfaces"]))
		? $edit_policy["interfaces"] : array();
	$l7_ef["src_hosts"] = layer7_policies_lines($edit_policy["match"]["src_hosts"] ?? null);
	$l7_ef["src_cidrs"] = layer7_policies_lines($edit_policy["match"]["src_cidrs"] ?? null);
	$l7_ef["ad_users"] = layer7_policies_lines($edit_policy["match"]["ad_users"] ?? null);
	$l7_ef["ad_groups"] = layer7_policies_lines($edit_policy["match"]["ad_groups"] ?? null);
	$l7_ef["groups"] = (isset($edit_policy["match"]["groups"]) && is_array($edit_policy["match"]["groups"]))
		? $edit_policy["match"]["groups"] : array();
	$l7_ef["exc_groups"] = (isset($edit_policy["match"]["src_exclude_groups"]) &&
	    is_array($edit_policy["match"]["src_exclude_groups"]))
		? $edit_policy["match"]["src_exclude_groups"] : array();
	$l7_ef["exc_cidrs"] = layer7_policies_lines($edit_policy["match"]["src_exclude_cidrs"] ?? null);
	$l7_ef["hosts"] = layer7_policies_lines($edit_policy["match"]["hosts"] ?? null);
	$l7_ef["apps"] = (isset($edit_policy["match"]["ndpi_app"]) && is_array($edit_policy["match"]["ndpi_app"]))
		? $edit_policy["match"]["ndpi_app"] : array();
	$l7_ef["apps_csv"] = implode(", ", $l7_ef["apps"]);
	$l7_ef["cats"] = (isset($edit_policy["match"]["ndpi_category"]) && is_array($edit_policy["match"]["ndpi_category"]))
		? $edit_policy["match"]["ndpi_category"] : array();
	$l7_ef["cats_csv"] = implode(", ", $l7_ef["cats"]);
	$l7_ef["tag_table"] = isset($edit_policy["tag_table"]) ? (string)$edit_policy["tag_table"] : "";
	if ($l7_ef["tag_table"] === "") {
		$l7_ef["tag_table"] = "layer7_tagged";
	}
	if (isset($edit_policy["schedule"]) && is_array($edit_policy["schedule"])) {
		$l7_ef["sched_days"] = isset($edit_policy["schedule"]["days"]) && is_array($edit_policy["schedule"]["days"])
			? $edit_policy["schedule"]["days"] : array();
		$l7_ef["sched_start"] = isset($edit_policy["schedule"]["start"]) ? (string)$edit_policy["schedule"]["start"] : "";
		$l7_ef["sched_end"] = isset($edit_policy["schedule"]["end"]) ? (string)$edit_policy["schedule"]["end"] : "";
	}
	$l7_ef["scope_global"] = !empty($edit_policy["scope_global"]);
	$l7_ef["quarantine"] = !empty($edit_policy["quarantine_origin"]);
}
if ($l7_edit_retry) {
	$l7_ef["name"] = layer7_policies_posted_text("edit_name");
	$l7_ef["priority"] = layer7_policies_posted_text("edit_priority");
	$l7_ef["action"] = layer7_policies_posted_text("edit_action");
	$l7_ef["enabled"] = layer7_policies_posted_checked("edit_enabled");
	$l7_ef["ifaces"] = layer7_policies_posted_list("edit_ifaces");
	$l7_ef["ifaces_from_post"] = true;
	$l7_ef["src_hosts"] = layer7_policies_posted_text("edit_src_hosts");
	$l7_ef["src_cidrs"] = layer7_policies_posted_text("edit_src_cidrs");
	if ($l7_has_identity) {
		$l7_ef["ad_users"] = layer7_policies_posted_text("edit_ad_users");
		$l7_ef["ad_groups"] = layer7_policies_posted_text("edit_ad_groups");
	}
	$l7_ef["groups"] = layer7_policies_posted_list("edit_groups");
	$l7_ef["exc_groups"] = layer7_policies_posted_list("edit_src_exclude_groups");
	$l7_ef["exc_cidrs"] = layer7_policies_posted_text("edit_src_exclude_cidrs");
	$l7_ef["hosts"] = layer7_policies_posted_text("edit_match_hosts");
	$l7_ef["apps"] = layer7_policies_posted_list("edit_ndpi_apps");
	$l7_ef["apps_csv"] = layer7_policies_posted_text("edit_ndpi_apps_csv");
	$l7_ef["cats"] = layer7_policies_posted_list("edit_ndpi_category");
	$l7_ef["cats_csv"] = layer7_policies_posted_text("edit_ndpi_category_csv");
	$l7_ef["tag_table"] = layer7_policies_posted_text("edit_tag_table");
	$l7_ef["sched_days"] = array();
	foreach (array_keys($l7_sched_days) as $dk) {
		if (layer7_policies_posted_checked("edit_sched_" . $dk)) {
			$l7_ef["sched_days"][] = $dk;
		}
	}
	$l7_ef["sched_start"] = layer7_policies_posted_text("edit_sched_start");
	$l7_ef["sched_end"] = layer7_policies_posted_text("edit_sched_end");
	$l7_ef["scope_global"] = layer7_policies_posted_checked("edit_scope_global");
	$l7_ef["quarantine"] = layer7_policies_posted_checked("edit_quarantine_origin");
}

$l7_nf = array(
	"id" => "",
	"name" => "",
	"priority" => "50",
	"action" => "monitor",
	"enabled" => true,
	"ifaces" => array(),
	"src_hosts" => "",
	"src_cidrs" => "",
	"ad_users" => "",
	"ad_groups" => "",
	"groups" => array(),
	"exc_groups" => array(),
	"exc_cidrs" => "",
	"hosts" => "",
	"apps" => array(),
	"apps_csv" => "",
	"cats" => array(),
	"cats_csv" => "",
	"tag_table" => "",
	"sched_days" => array(),
	"sched_start" => "",
	"sched_end" => "",
	"scope_global" => false,
	"quarantine" => false,
);
if ($l7_add_retry) {
	$l7_nf["id"] = layer7_policies_posted_text("new_id");
	$l7_nf["name"] = layer7_policies_posted_text("new_name");
	$l7_nf["priority"] = layer7_policies_posted_text("new_priority");
	$l7_nf["action"] = layer7_policies_posted_text("new_action");
	$l7_nf["enabled"] = layer7_policies_posted_checked("new_enabled");
	$l7_nf["ifaces"] = layer7_policies_posted_list("new_ifaces");
	$l7_nf["src_hosts"] = layer7_policies_posted_text("new_src_hosts");
	$l7_nf["src_cidrs"] = layer7_policies_posted_text("new_src_cidrs");
	if ($l7_has_identity) {
		$l7_nf["ad_users"] = layer7_policies_posted_text("new_ad_users");
		$l7_nf["ad_groups"] = layer7_policies_posted_text("new_ad_groups");
	}
	$l7_nf["groups"] = layer7_policies_posted_list("new_groups");
	$l7_nf["exc_groups"] = layer7_policies_posted_list("new_src_exclude_groups");
	$l7_nf["exc_cidrs"] = layer7_policies_posted_text("new_src_exclude_cidrs");
	$l7_nf["hosts"] = layer7_policies_posted_text("new_match_hosts");
	$l7_nf["apps"] = layer7_policies_posted_list("new_ndpi_apps");
	$l7_nf["apps_csv"] = layer7_policies_posted_text("new_ndpi_apps_csv");
	$l7_nf["cats"] = layer7_policies_posted_list("new_ndpi_category");
	$l7_nf["cats_csv"] = layer7_policies_posted_text("new_ndpi_category_csv");
	$l7_nf["tag_table"] = layer7_policies_posted_text("new_tag_table");
	$l7_nf["sched_days"] = array();
	foreach (array_keys($l7_sched_days) as $dk) {
		if (layer7_policies_posted_checked("new_sched_" . $dk)) {
			$l7_nf["sched_days"][] = $dk;
		}
	}
	$l7_nf["sched_start"] = layer7_policies_posted_text("new_sched_start");
	$l7_nf["sched_end"] = layer7_policies_posted_text("new_sched_end");
	$l7_nf["scope_global"] = layer7_policies_posted_checked("new_scope_global");
	$l7_nf["quarantine"] = layer7_policies_posted_checked("new_quarantine_origin");
}

$l7_del_sel = "0";
if (!empty($input_errors) && ($_POST["delete_policy"] ?? false) && isset($_POST["delete_policy_index"])) {
	$posted_del = (string)(int)$_POST["delete_policy_index"];
	if (isset($policies[(int)$posted_del])) {
		$l7_del_sel = $posted_del;
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Policies"));
if ($l7_policy_mode === "edit") {
	$pgtitle[] = l7_t("Editar");
} elseif ($l7_policy_mode === "new") {
	$pgtitle[] = l7_t("Adicionar politica");
} elseif ($l7_policy_mode === "view") {
	$pgtitle[] = l7_t("Detalhe");
}
include("head.inc");

function layer7_policy_match_summary($policy) {
	$matches = array();
	if (!empty($policy["interfaces"]) && is_array($policy["interfaces"])) {
		$matches[] = l7_t("Ifaces") . ": " . implode(", ", $policy["interfaces"]);
	}
	if (!empty($policy["match"]["ndpi_app"]) && is_array($policy["match"]["ndpi_app"])) {
		$matches[] = l7_t("Apps") . ": " . implode(", ", $policy["match"]["ndpi_app"]);
	}
	if (!empty($policy["match"]["ndpi_category"]) && is_array($policy["match"]["ndpi_category"])) {
		$matches[] = l7_t("Categorias") . ": " . implode(", ", $policy["match"]["ndpi_category"]);
	}
	if (!empty($policy["match"]["hosts"]) && is_array($policy["match"]["hosts"])) {
		$matches[] = l7_t("Sites") . ": " . implode(", ", $policy["match"]["hosts"]);
	}
	if (!empty($policy["match"]["src_hosts"]) && is_array($policy["match"]["src_hosts"])) {
		$matches[] = l7_t("IPs") . ": " . implode(", ", $policy["match"]["src_hosts"]);
	}
	if (!empty($policy["match"]["ad_users"]) && is_array($policy["match"]["ad_users"])) {
		$matches[] = l7_t("Utilizadores AD") . ": " . implode(", ", $policy["match"]["ad_users"]);
	}
	if (!empty($policy["match"]["ad_groups"]) && is_array($policy["match"]["ad_groups"])) {
		$matches[] = l7_t("Grupos AD") . ": " . implode(", ", $policy["match"]["ad_groups"]);
	}
	if (!empty($policy["match"]["src_cidrs"]) && is_array($policy["match"]["src_cidrs"])) {
		$matches[] = l7_t("CIDRs") . ": " . implode(", ", $policy["match"]["src_cidrs"]);
	}
	if (!empty($policy["match"]["groups"]) && is_array($policy["match"]["groups"])) {
		$matches[] = l7_t("Grupos") . ": " . implode(", ", $policy["match"]["groups"]);
	}
	if (!empty($policy["match"]["src_exclude_cidrs"]) && is_array($policy["match"]["src_exclude_cidrs"])) {
		$matches[] = l7_t("Excl. CIDRs") . ": " . implode(", ", $policy["match"]["src_exclude_cidrs"]);
	}
	if (!empty($policy["match"]["src_exclude_groups"]) && is_array($policy["match"]["src_exclude_groups"])) {
		$matches[] = l7_t("Excl. grupos") . ": " . implode(", ", $policy["match"]["src_exclude_groups"]);
	}
	if (!empty($policy["tag_table"]) && (($policy["action"] ?? "") === "tag")) {
		$matches[] = l7_t("Tabela PF") . ": " . $policy["tag_table"];
	}
	$sched_label = layer7_schedule_summary($policy);
	if ($sched_label !== l7_t("Sempre activa")) {
		$matches[] = l7_t("Horario") . ": " . $sched_label;
	}
	return count($matches) > 0 ? $matches : array(l7_t("Sem filtros especificos."));
}
?>
<?php layer7_render_tabs("policies"); ?>
<?php layer7_render_messages(); ?>
<?php layer7_render_policies_subnav("policies"); ?>
<div class="alert alert-info">
	<?= htmlspecialchars(l7_t("Gerir politicas de classificacao e bloqueio.")); ?>
</div>
<?php
$_nav_data = layer7_load_or_default();
$_nav_groups = isset($_nav_data["layer7"]["groups"]) ? count($_nav_data["layer7"]["groups"]) : 0;
$_nav_exceptions = isset($_nav_data["layer7"]["exceptions"]) ? count($_nav_data["layer7"]["exceptions"]) : 0;
?>
<p>
	<a href="layer7_groups.php" class="btn btn-default btn-sm"><i class="fa fa-users"></i> <?= l7_t("Grupos"); ?> (<?= $_nav_groups; ?>)</a>
	<a href="layer7_exceptions.php" class="btn btn-default btn-sm"><i class="fa fa-shield"></i> <?= l7_t("Excecoes"); ?> (<?= $_nav_exceptions; ?>)</a>
	<a href="layer7_categories.php" class="btn btn-default btn-sm"><i class="fa fa-th-list"></i> <?= l7_t("Categorias nDPI"); ?></a>
	<a href="layer7_test.php" class="btn btn-default btn-sm"><i class="fa fa-play-circle"></i> <?= l7_t("Simular teste"); ?></a>
</p>

		<?php if ($l7_policy_mode === "list") { ?>
		<div class="panel panel-default" id="l7-policies">
			<div class="panel-heading">
				<h2 class="panel-title"><?= htmlspecialchars(l7_t("Politicas aplicadas")); ?></h2>
			</div>
			<div class="panel-body">
			<p id="l7-add">
				<a href="layer7_policies.php?new=1" class="btn btn-success"><?= htmlspecialchars(l7_t("Adicionar politica")); ?></a>
				<?php if ($l7_library_available) { ?>
				<a href="layer7_policies.php?library=1#l7-profiles" class="btn btn-default"><?= htmlspecialchars(l7_t("Abrir biblioteca de perfis")); ?></a>
				<?php } ?>
			</p>
			<?php if ($l7_library_available) { ?>
			<p class="help-block">
				<span id="l7-profiles" tabindex="-1"></span>
				<a href="layer7_policies.php?library=1#l7-profiles"><?= htmlspecialchars(l7_t("Biblioteca de perfis")); ?></a>
				&middot;
				<span id="l7-ra" tabindex="-1"></span>
				<a href="layer7_policies.php?library=1#l7-ra"><?= htmlspecialchars(l7_t("Acesso remoto (biblioteca)")); ?></a>
			</p>
			<?php } ?>
			<?php if (count($policies) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma politica cadastrada. Adicione a primeira regra abaixo ou importe um layer7.json existente."); ?></div>
			<?php } else { ?>
			<form method="post" action="layer7_policies.php#l7-policies">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= l7_t("Ativa"); ?></th>
								<th><?= l7_t("Prioridade"); ?></th>
								<th><?= l7_t("Nome"); ?></th>
								<th><?= l7_t("Acao"); ?></th>
								<th><?= l7_t("Correspondencia"); ?></th>
								<th><code>id</code></th>
								<th><?= l7_t("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($policies as $i => $policy) {
							$pid = isset($policy["id"]) ? (string)$policy["id"] : "";
							$name = isset($policy["name"]) ? (string)$policy["name"] : "";
							$action = isset($policy["action"]) ? (string)$policy["action"] : "";
							$priority = isset($policy["priority"]) ? (int)$policy["priority"] : 0;
							$enabled = !empty($policy["enabled"]);
							$matches = layer7_policy_match_summary($policy);
							$pon_id = "pon_" . (int)$i;
							$pon_label = l7_t("Politica activa") . ": " . ($name !== "" ? $name : $pid);
						?>
							<tr>
								<td>
									<label class="sr-only" for="<?= htmlspecialchars($pon_id); ?>"><?= htmlspecialchars($pon_label); ?></label>
									<input type="checkbox" id="<?= htmlspecialchars($pon_id); ?>" name="pon[<?= (int)$i; ?>]" value="1" aria-label="<?= htmlspecialchars($pon_label); ?>" <?= $enabled ? 'checked="checked"' : ''; ?> />
								</td>
								<td><?= htmlspecialchars((string)$priority); ?></td>
								<td><?= htmlspecialchars($name); ?></td>
								<td><span class="label label-default"><?= htmlspecialchars($action); ?></span></td>
								<td class="small"><?= htmlspecialchars(implode(" | ", $matches)); ?></td>
								<td><code><?= htmlspecialchars($pid); ?></code></td>
								<td>
									<a href="layer7_policies.php?view=<?= (int)$i; ?>" class="btn btn-xs btn-default"><?= l7_t("Ver listas"); ?></a>
									<a href="layer7_policies.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= l7_t("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<p>
					<button type="submit" name="save_policies" value="1" class="btn btn-primary"><?= l7_t("Guardar estado das politicas"); ?></button>
				</p>
			</form>

			<?php
			$del_opts = array();
			foreach ($policies as $i => $policy) {
				$pid = isset($policy["id"]) ? (string)$policy["id"] : ("#" . $i);
				$pname = isset($policy["name"]) ? (string)$policy["name"] : "";
				$del_opts[(string)(int)$i] = $pid . ($pname !== "" ? " - " . $pname : "");
			}
			$del_form = new Form(false);
			$del_form->setAction("layer7_policies.php#l7-policies");
			$del_form->setAttribute("onsubmit", "return confirm(" . json_encode(l7_t("Remover esta politica do JSON?")) . ");");
			$sec_del = new Form_Section(l7_t("Remover politica"));
			$del_sel = new Form_Select("delete_policy_index", l7_t("Politica a remover"), $l7_del_sel, $del_opts);
			$del_sel->setAttribute("id", "delete_policy_index");
			$sec_del->addInput($del_sel);
			$del_form->add($sec_del);
			$del_btn = new Form_Button("delete_policy", l7_t("Remover"), null, "fa fa-trash");
			$del_btn->addClass("btn-danger");
			$del_form->addGlobal($del_btn);
			print($del_form);
			?>
			<?php } ?>
			</div>
		</div>
		<?php } ?>

		<?php if ($l7_policy_mode === "library") { ?>
		<p>
			<a href="layer7_policies.php#l7-policies" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
		</p>
		<?php if (!$l7_library_available) { ?>
		<div class="alert alert-warning"><?php
		if ($l7_library_unavail_reason === "limit24") {
			echo l7_t("Limite de 24 politicas atingido. A biblioteca de perfis so fica disponivel com menos de 24 politicas aplicadas.");
		} else {
			echo l7_t("Catalogo de perfis indisponivel. Nenhum perfil foi carregado.");
		}
		?></div>
		<?php } else {
		$l7_profiles = $l7_profiles_catalog;
		$prof_ifaces = layer7_get_pfsense_interfaces();
		/* A4: contadores de hits por perfil a partir das stats do daemon. */
		$l7_prof_hits = layer7_profile_hit_counts($l7_profiles, layer7_read_stats());
		?>
		<div class="panel panel-default" id="l7-profiles">
			<div class="panel-heading">
				<h2 class="panel-title"><?= htmlspecialchars(l7_t("Biblioteca de perfis")); ?></h2>
			</div>
			<div class="panel-body">
			<p><?= l7_t("Categorias comecam fechadas — clique no titulo para abrir. Ligue ou desligue perfis na grelha; as alteracoes ficam em rascunho. Clique em Aplicar para gravar e activar tudo de uma vez (um unico reload). Use 'Opcoes' para accao, interfaces e sub-redes. Edite ou crie perfis personalizados em profiles-custom.json (preservado nos upgrades)."); ?></p>
			<p>
				<label class="sr-only" for="l7ProfileSearch"><?= htmlspecialchars(l7_t("Pesquisar perfil...")); ?></label>
				<input type="text" id="l7ProfileSearch" class="form-control" placeholder="<?= l7_t("Pesquisar perfil..."); ?>" autocomplete="off" aria-label="<?= htmlspecialchars(l7_t("Pesquisar perfil...")); ?>" oninput="l7filterProfileGrid();" />
			</p>
			<p>
				<label>
					<input type="checkbox" id="l7ProfileActiveOnly" onchange="l7filterProfileGrid();" />
					<?= l7_t("So ligados"); ?>
				</label>
			</p>
			<p>
				<button type="button" class="btn btn-default btn-sm" onclick="l7setAllProfileGroups(true);"><?= l7_t("Expandir tudo"); ?></button>
				<button type="button" class="btn btn-default btn-sm" onclick="l7setAllProfileGroups(false);"><?= l7_t("Recolher tudo"); ?></button>
				<a href="layer7_policies.php?profile_new=1" class="btn btn-primary btn-sm" onclick="return l7showProfileEditModal('', true, event);">
					<i class="fa fa-plus"></i> <?= l7_t("Criar perfil"); ?>
				</a>
			</p>
			<div id="l7ProfileDraftBar" class="alert alert-warning" hidden>
				<p id="l7ProfileDraftMsg"></p>
				<p>
					<button type="button" class="btn btn-default btn-sm" id="l7ProfileDraftDiscard" onclick="l7discardProfileDraft();"><?= l7_t("Descartar"); ?></button>
					<button type="button" class="btn btn-success btn-sm" id="l7ProfileDraftApply" onclick="l7applyProfileDraft();"><?= l7_t("Aplicar alteracoes"); ?></button>
				</p>
			</div>

		<?php
		$l7_profiles_custom_raw = layer7_profiles_custom_load();
		$l7_profile_catalog = layer7_profiles_catalog();
		/*
		 * Icones dos Perfis rapidos: o glifo vem do campo "icon" do
		 * profiles.json (FontAwesome 4.7, incluido no pfSense). A cor de
		 * fundo vem da marca quando conhecida ($l7_brand_colors) ou do
		 * grupo do perfil ($l7_group_colors). Entrada opcionalmente em
		 * array(bg, fg) quando o fundo claro exige glifo escuro.
		 */
		$l7_brand_colors = array(
			"facebook" => "#1877F2",
			"instagram" => "#E4405F",
			"tiktok" => "#010101",
			"twitter" => "#14171A",
			"linkedin" => "#0A66C2",
			"reddit" => "#FF4500",
			"pinterest" => "#E60023",
			"snapchat" => array("#FFFC00", "#484848"),
			"kwai" => "#FF7705",
			"threads" => "#101010",
			"bluesky" => "#0285FF",
			"kick" => array("#53FC18", "#101010"),
			"rumble" => "#85C742",
			"whatsapp" => "#25D366",
			"telegram" => "#26A5E4",
			"discord" => "#5865F2",
			"zoom" => "#2D8CFF",
			"ms-teams" => "#6264A7",
			"google-meet" => "#00897B",
			"webex" => "#005073",
			"teamspeak" => "#2580C3",
			"youtube" => "#FF0000",
			"netflix" => "#E50914",
			"spotify" => "#1DB954",
			"twitch" => "#9146FF",
			"prime-video" => "#00A8E1",
			"disneyplus" => "#113CCF",
			"max-hbo" => "#002BE7",
			"globoplay" => "#FB0234",
			"crunchyroll" => "#F47521",
			"deezer" => "#A238FF",
			"soundcloud" => "#FF5500",
			"dazn" => "#1A1A1A",
			"paramount" => "#0064FF",
			"hulu" => array("#1CE783", "#0B0C0F"),
			"vimeo-dailymotion" => "#1AB7EA",
			"roblox" => "#E2231A",
			"free-fire" => "#F05A28",
			"github" => "#24292E",
			"ai-tools" => "#10A37F",
			"webmail-pessoal" => "#D93025",
			"marketplaces" => "#FF9900",
			"cripto" => "#F7931A",
			"namoro" => "#FD297B",
			"adulto" => "#C0392B",
			"malware" => "#B71C1C",
			"social" => "#4267B2",
			"streaming" => "#FF6D00",
			"gaming" => "#7B2FBE",
			"vpn-proxy" => "#2C3E50",
			"remote-access" => "#3D5A80",
			"anti-bypass-dns" => "#E67E22",
			/* Acesso remoto — cores por produto (evita muro vermelho do grupo). */
			"ndpi-remote-category" => "#607D8B",
			"teamviewer" => "#0E8EE9",
			"anydesk" => "#EF7B18",
			"rustdesk" => "#1B9E77",
			"chrome-remote" => "#4285F4",
			"logmein" => "#F15A29",
			"splashtop" => "#00A4E4",
			"zoho-assist" => "#C62828",
			"ultraviewer" => "#5C6BC0",
			"supremo" => "#FFB300",
			"remotepc" => "#00897B",
			"isl-online" => "#1565C0",
			"dwservice" => "#039BE5",
			"getscreen" => "#7B1FA2",
			"helpwire" => "#00838F",
			"aeroadmin" => "#5D4037",
			"iperius" => "#455A64",
			"rdp" => "#0078D4",
			"vnc" => "#37474F",
			"realvnc" => "#00ADEF",
			"nomachine" => "#E65100",
			"ssh" => "#212121",
			"parsec" => "#F04E32",
			"moonlight" => "#3949AB",
			"hoptodesk" => "#2E7D32",
			"screenconnect" => "#D32F2F",
			"atera" => "#FF6B00",
			"ninjaone" => "#1A237E",
			"meshcentral" => "#546E7A",
			"datto-rmm" => "#00AEEF",
			"todesk" => "#1E88E5",
			"sunlogin" => "#FF8F00",
			"awesun" => "#FB8C00",
			/* Outros perfis sem cor de marca (alinhamento visual). */
			"mensageria" => "#00A884",
			"videoconferencia" => "#2D8CFF",
			"redes-alternativas" => "#5C6BC0",
			"musica" => "#1DB954",
			"futebol-pirata" => "#C62828",
			"cloud-gaming" => "#7B2FBE",
			"cloud-storage" => "#4285F4",
			"empregos" => "#455A64",
			"noticias" => "#546E7A",
			"desporto" => "#2E7D32",
			"viagens" => "#0277BD",
			"speedtest" => "#FF6D00",
			"torrent-p2p" => "#6A1B9A",
			"apostas" => "#2E7D32",
			"anonymizers" => "#37474F",
			"publicidade" => "#F9A825",
			"mining" => "#795548",
			"preset-distracoes" => "#00897B",
			"preset-protecao-infantil" => "#5C6BC0",
			"preset-higiene" => "#16A085",
		);
		$l7_group_colors = array(
			"Redes sociais" => "#4267B2",
			"Mensagens" => "#00A884",
			"Mensageria" => "#00A884", /* legado overlay/custom */
			"Comunicação e reuniões" => "#2D8CFF",
			"Acesso remoto" => "#3D5A80",
			"Streaming" => "#FF6D00",
			"Jogos" => "#7B2FBE",
			"Produtividade" => "#546E7A",
			"Segurança e bypass" => "#2C3E50",
			"Presets" => "#16A085",
			"Personalizados" => "#8E44AD",
		);
		?>
		<div class="l7-profiles-groups">
		<?php
		$l7_group_order = array(
			l7_t("Redes sociais"),
			l7_t("Mensagens"),
			l7_t("Comunicação e reuniões"),
			l7_t("Acesso remoto"),
			l7_t("Streaming"),
			l7_t("Jogos"),
			l7_t("Produtividade"),
			l7_t("Segurança e bypass"),
			l7_t("Presets"),
			l7_t("Personalizados"),
		);
		$l7_profiles_by_group = array();
		$l7_profile_edit_data = array();
		foreach ($l7_profiles as $prof) {
			$gk_raw = isset($prof["group"]) && is_string($prof["group"]) && trim($prof["group"]) !== ""
			    ? trim($prof["group"]) : "Outros";
			/* Label PT renomeado; ID interno mensageria e overlays legados preservados. */
			if ($gk_raw === "Mensageria") {
				$gk_raw = "Mensagens";
			}
			$gk = l7_t($gk_raw);
			if (!isset($l7_profiles_by_group[$gk])) {
				$l7_profiles_by_group[$gk] = array();
			}
			$l7_profiles_by_group[$gk][] = $prof;
			$eid = (string)($prof["id"] ?? "");
			if ($eid === "") {
				continue;
			}
			$e_pid = "profile-" . $eid;
			$e_connected = false;
			foreach ($policies as $existing) {
				if (isset($existing["id"]) && (string)$existing["id"] === $e_pid) {
					$e_connected = true;
					break;
				}
			}
			$e_hidden = false;
			if (layer7_profile_custom_id_valid($eid)) {
				$e_hidden = !empty($prof["hidden"]);
			} elseif (isset($l7_profiles_custom_raw["overrides"][$eid]["hidden"])) {
				$e_hidden = !empty($l7_profiles_custom_raw["overrides"][$eid]["hidden"]);
			}
			$l7_profile_edit_data[$eid] = array(
				"id" => $eid,
				"name" => (string)($prof["name"] ?? ""),
				"description" => (string)($prof["description"] ?? ""),
				"icon" => (string)($prof["icon"] ?? "fa-cube"),
				"apps" => (isset($prof["ndpi_apps"]) && is_array($prof["ndpi_apps"])) ? $prof["ndpi_apps"] : array(),
				"cats" => (isset($prof["ndpi_categories"]) && is_array($prof["ndpi_categories"])) ? $prof["ndpi_categories"] : array(),
				"hosts" => (isset($prof["hosts"]) && is_array($prof["hosts"])) ? implode("\n", $prof["hosts"]) : "",
				"is_custom" => layer7_profile_custom_id_valid($eid),
				"has_override" => layer7_profile_has_override($eid, $l7_profiles_custom_raw),
				"hidden" => $e_hidden,
				"connected" => $e_connected,
			);
		}
		/* Pacotes agregados primeiro em todos os grupos.
		 * Acesso remoto: restantes A–Z por nome; outros grupos mantêm
		 * a ordem relativa do catalogo (popularidade / JSON). */
		$l7_aggregate_prio = array(
			"remote-access" => 0,
			"ndpi-remote-category" => 1,
			"social" => 0,
			"mensageria" => 0,
			"videoconferencia" => 0,
			"streaming" => 0,
			"musica" => 1,
			"gaming" => 0,
		);
		$l7_ra_group_label = l7_t("Acesso remoto");
		foreach ($l7_profiles_by_group as $l7_gk => &$l7_gprofs_sort) {
			$is_ra = ($l7_gk === $l7_ra_group_label);
			$first = array();
			$rest = array();
			foreach ($l7_gprofs_sort as $gp) {
				$gid = (string)($gp["id"] ?? "");
				if ($gid !== "" && isset($l7_aggregate_prio[$gid])) {
					$first[] = $gp;
				} else {
					$rest[] = $gp;
				}
			}
			usort($first, function ($a, $b) use ($l7_aggregate_prio) {
				$pa = $l7_aggregate_prio[(string)($a["id"] ?? "")] ?? 0;
				$pb = $l7_aggregate_prio[(string)($b["id"] ?? "")] ?? 0;
				if ($pa !== $pb) {
					return $pa - $pb;
				}
				return strcasecmp((string)($a["name"] ?? ""), (string)($b["name"] ?? ""));
			});
			if ($is_ra) {
				usort($rest, function ($a, $b) {
					return strcasecmp((string)($a["name"] ?? ""), (string)($b["name"] ?? ""));
				});
			}
			$l7_gprofs_sort = array_merge($first, $rest);
		}
		unset($l7_gprofs_sort);
		$l7_quick_scoped = layer7_enforcement_is_scoped_hybrid(layer7_load_or_default());
		$l7_groups_rendered = array();
		$l7_render_profile_card = function ($prof, $is_hidden = false) use ($policies, $l7_brand_colors, $l7_group_colors, $l7_prof_hits, $l7_profiles_custom_raw, $l7_quick_scoped) {
			$prof_id = isset($prof["id"]) ? htmlspecialchars($prof["id"]) : "";
			$prof_id_raw = (string)($prof["id"] ?? "");
			$prof_name = isset($prof["name"]) ? htmlspecialchars(l7_t($prof["name"])) : $prof_id;
			$prof_desc = isset($prof["description"]) ? htmlspecialchars(l7_t($prof["description"])) : "";
			$prof_apps_count = isset($prof["ndpi_apps"]) && is_array($prof["ndpi_apps"]) ? count($prof["ndpi_apps"]) : 0;
			$prof_hosts_count = isset($prof["hosts"]) && is_array($prof["hosts"]) ? count($prof["hosts"]) : 0;
			$prof_is_custom = layer7_profile_custom_id_valid($prof_id_raw);
			$prof_is_edited = !$prof_is_custom && layer7_profile_has_override($prof_id_raw, $l7_profiles_custom_raw);
			$prof_exists = false;
			$prof_pid = "profile-" . ($prof["id"] ?? "");
			foreach ($policies as $existing) {
				if (isset($existing["id"]) && (string)$existing["id"] === $prof_pid) {
					$prof_exists = true;
					break;
				}
			}
			$icon_raw = "fa-cube";
			if (isset($prof["icon"]) && is_string($prof["icon"]) && preg_match('/^fa-[a-z0-9-]{1,40}$/', $prof["icon"])) {
				$icon_raw = $prof["icon"];
			}
			$icon_bg = "#66748A";
			$icon_fg = "#fff";
			$prof_group_raw = isset($prof["group"]) && is_string($prof["group"]) ? trim($prof["group"]) : "";
			if (isset($l7_brand_colors[$prof["id"] ?? ""])) {
				$brand = $l7_brand_colors[$prof["id"]];
				if (is_array($brand)) {
					$icon_bg = $brand[0];
					$icon_fg = $brand[1];
				} else {
					$icon_bg = $brand;
				}
			} elseif ($prof_group_raw !== "" && isset($l7_group_colors[$prof_group_raw])) {
				$icon_bg = $l7_group_colors[$prof_group_raw];
			}
			$prof_hit = isset($l7_prof_hits[$prof["id"] ?? ""]) ? (int)$l7_prof_hits[$prof["id"] ?? ""] : 0;
			$search_hosts = "";
			if (!empty($prof["hosts"]) && is_array($prof["hosts"])) {
				$search_hosts = implode(" ", $prof["hosts"]);
			}
			$card_title = $prof_desc !== "" ? $prof_desc : $prof_name;
			$prof_cats_count = isset($prof["ndpi_categories"]) && is_array($prof["ndpi_categories"]) ? count($prof["ndpi_categories"]) : 0;
			$meta_parts = array();
			if ($prof_cats_count > 0 && $prof_apps_count === 0 && $prof_hosts_count === 0) {
				$meta_parts[] = $prof_cats_count . " " . l7_t("cats");
			} else {
				$meta_parts[] = $prof_apps_count . " apps";
				$meta_parts[] = $prof_hosts_count . " " . l7_t("hosts");
				if ($prof_cats_count > 0) {
					$meta_parts[] = $prof_cats_count . " " . l7_t("cats");
				}
			}
			if ($prof_hit > 0) {
				$meta_parts[] = $prof_hit . " " . l7_t("hits");
			}
			$card_classes = "l7-profile-card";
			if ($prof_exists) {
				$card_classes .= " l7-profile-on";
			}
			if ($is_hidden) {
				$card_classes .= " l7-profile-card-hidden";
			}
			$toggle_name = $prof_exists ? "toggle_profile_off" : "toggle_profile_on";
			$toggle_label = $prof_exists ? l7_t("Desligar") : l7_t("Ligar");
			$toggle_btn = $prof_exists ? "btn-success" : "btn-default";
		?>
			<tr class="<?= $card_classes; ?>" title="<?= $card_title; ?>"
				data-profile-name="<?= strtolower($prof_name); ?>"
				data-profile-active="<?= $prof_exists ? "1" : "0"; ?>"
				data-profile-search="<?= htmlspecialchars(strtolower($prof_name . " " . $search_hosts), ENT_QUOTES); ?>">
				<td>
					<?= layer7_profile_icon_html($icon_raw); ?>
					<?= $prof_name; ?>
					<?php if ($prof_is_custom) { ?>
					<span class="label label-info"><?= l7_t("personalizado"); ?></span>
					<?php } elseif ($prof_is_edited) { ?>
					<span class="label label-warning"><?= l7_t("editado"); ?></span>
					<?php } ?>
				</td>
				<td class="small"><?= htmlspecialchars(implode(" | ", $meta_parts)); ?></td>
				<td>
				<form method="post" action="layer7_policies.php#l7-profiles">
					<input type="hidden" name="profile_id" value="<?= $prof_id; ?>" />
					<button type="submit" name="<?= $toggle_name; ?>" value="1"
						class="btn btn-xs <?= $toggle_btn; ?>"
						data-profile-id="<?= htmlspecialchars($prof_id_raw, ENT_QUOTES); ?>"
						data-saved="<?= $prof_exists ? "1" : "0"; ?>"
						data-desired="<?= $prof_exists ? "1" : "0"; ?>"
						data-scoped="<?= $l7_quick_scoped ? "1" : "0"; ?>"
						title="<?= $toggle_label; ?>"
						aria-label="<?= $toggle_label; ?>"
						onclick="l7toggleProfileDraft(this); return false;"><?= htmlspecialchars($toggle_label); ?></button>
				</form>
				</td>
				<td>
				<?php if (!$prof_exists) { ?>
				<a href="layer7_policies.php?profile_options=<?= rawurlencode($prof_id_raw); ?>" class="btn btn-xs btn-default" title="<?= l7_t("Opcoes"); ?>" onclick="return l7showProfileModal(<?= htmlspecialchars(json_encode($prof_id_raw), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($prof["name"] ?? $prof_id_raw), ENT_QUOTES) ?>, event);"><?= l7_t("Opcoes"); ?></a>
				<?php } ?>
				<a href="layer7_policies.php?profile_edit=<?= rawurlencode($prof_id_raw); ?>" class="btn btn-xs btn-default" title="<?= l7_t("Editar perfil"); ?>" onclick="return l7showProfileEditModal(<?= htmlspecialchars(json_encode($prof_id_raw), ENT_QUOTES) ?>, false, event);"><?= l7_t("Editar"); ?></a>
				<?php if ($is_hidden) { ?>
				<form method="post" action="layer7_policies.php#l7-profiles">
					<input type="hidden" name="profile_id" value="<?= $prof_id; ?>" />
					<button type="submit" name="unhide_profile" value="1" class="btn btn-xs btn-default" title="<?= l7_t("Mostrar"); ?>"><?= l7_t("Mostrar"); ?></button>
				</form>
				<?php } ?>
				</td>
			</tr>
		<?php
		};
		$l7_group_slug = function ($gname) {
			return "g" . substr(preg_replace('/[^a-z0-9]+/i', '-', strtolower($gname)), 0, 48);
		};
		$l7_count_group_active = function ($gprofs) use ($policies) {
			$n = 0;
			foreach ($gprofs as $gp) {
				$gpid = "profile-" . ($gp["id"] ?? "");
				foreach ($policies as $existing) {
					if (isset($existing["id"]) && (string)$existing["id"] === $gpid) {
						$n++;
						break;
					}
				}
			}
			return $n;
		};
		$l7_render_profile_group = function ($gname, $gprofs, $opts = array()) use ($l7_render_profile_card, $l7_group_slug, $l7_count_group_active) {
			$gid = $l7_group_slug($gname);
			$active_n = $l7_count_group_active($gprofs);
			$is_presets = !empty($opts["presets"]);
			$is_hidden_section = !empty($opts["hidden_section"]);
			$is_ra_group = !empty($opts["remote_access"]);
			$group_classes = "panel panel-default l7-profile-group";
			if ($is_presets) {
				$group_classes .= " l7-profile-group-presets";
			}
			if ($is_hidden_section) {
				$group_classes .= " l7-profile-group-hidden-section";
			}
			$anchor_attr = $is_ra_group ? ' id="l7-ra"' : "";
		?>
			<details class="<?= $group_classes; ?>"<?= $anchor_attr; ?> data-group-id="<?= htmlspecialchars($gid); ?>" data-group-default-open="0">
				<summary class="panel-heading l7-profile-group-header">
					<h2 class="panel-title">
						<?= htmlspecialchars($gname); ?>
						<span class="l7-profile-group-count"><?= count($gprofs); ?> <?= l7_t("perfis"); ?></span>
						<?php if ($active_n > 0) { ?>
						<span class="label label-success l7-profile-group-active-badge"><?= $active_n; ?> <?= l7_t("ligados"); ?></span>
						<?php } ?>
						<span class="label label-warning l7-profile-group-pending-badge" hidden></span>
					</h2>
				</summary>
				<div class="panel-body l7-profile-group-body">
				<?php if ($is_hidden_section) { ?>
				<p class="help-block"><?= l7_t("Estes perfis nao aparecem na grelha principal. A politica ligada permanece activa."); ?></p>
				<?php } ?>
				<div class="table-responsive">
				<table class="table table-striped table-condensed table-hover">
					<thead>
						<tr>
							<th><?= l7_t("Nome"); ?></th>
							<th><?= l7_t("Correspondencia"); ?></th>
							<th><?= l7_t("Ativa"); ?></th>
							<th><?= l7_t("Acoes"); ?></th>
						</tr>
					</thead>
					<tbody>
				<?php
				foreach ($gprofs as $prof) {
					$l7_render_profile_card($prof, $is_hidden_section);
				}
				?>
					</tbody>
				</table>
				</div>
				</div>
			</details>
		<?php
		};
		foreach ($l7_group_order as $l7_gname) {
			if (empty($l7_profiles_by_group[$l7_gname])) {
				continue;
			}
			$l7_groups_rendered[$l7_gname] = true;
			$l7_render_profile_group($l7_gname, $l7_profiles_by_group[$l7_gname], array(
				"presets" => ($l7_gname === l7_t("Presets")),
				"remote_access" => ($l7_gname === l7_t("Acesso remoto")),
			));
		}
		foreach ($l7_profiles_by_group as $l7_gname => $l7_gprofs) {
			if (!empty($l7_groups_rendered[$l7_gname])) {
				continue;
			}
			$l7_render_profile_group($l7_gname, $l7_gprofs, array(
				"presets" => ($l7_gname === l7_t("Presets")),
			));
		}
		$l7_visible_ids = array();
		foreach ($l7_profiles as $vp) {
			$vid = (string)($vp["id"] ?? "");
			if ($vid !== "") {
				$l7_visible_ids[$vid] = true;
			}
		}
		$l7_hidden_profiles = array();
		foreach (layer7_load_profiles(true) as $hp) {
			$hid = (string)($hp["id"] ?? "");
			if ($hid !== "" && !isset($l7_visible_ids[$hid])) {
				$l7_hidden_profiles[] = $hp;
			}
		}
		if (!empty($l7_hidden_profiles)) {
			$l7_render_profile_group(l7_t("Perfis ocultos"), $l7_hidden_profiles, array(
				"hidden_section" => true,
			));
		}
		?>
		</div>
		</div>

		<div class="modal fade" id="l7ProfileModal" tabindex="-1" role="dialog" aria-labelledby="l7ProfileModalTitle">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="<?= htmlspecialchars(l7_t("Fechar")); ?>"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title" id="l7ProfileModalTitle"></h4>
					</div>
					<div class="modal-body">
						<?= layer7_render_profile_options_form($l7_profile_options_state, $prof_ifaces, $l7_groups, "modal"); ?>
					</div>
				</div>
			</div>
		</div>

		<div class="modal fade" id="l7ProfileEditModal" tabindex="-1" role="dialog" aria-labelledby="l7ProfileEditModalTitle">
			<div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="<?= htmlspecialchars(l7_t("Fechar")); ?>"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title" id="l7ProfileEditModalTitle"><?= l7_t("Editar perfil"); ?></h4>
					</div>
					<div class="modal-body">
						<div id="l7ProfileEditReconnectWarn" class="alert alert-warning hidden" hidden></div>
						<?php
						$l7_pe_modal_state = array(
							"profile_id" => "",
							"is_new" => false,
							"name" => "",
							"description" => "",
							"icon" => "fa-cube",
							"apps" => array(),
							"cats" => array(),
							"hosts" => "",
							"hidden" => false,
							"is_custom" => true,
							"connected" => false,
							"show_delete" => false,
						);
						echo layer7_render_profile_edit_form($l7_pe_modal_state, $l7_profile_catalog, "modal");
						?>
					</div>
				</div>
			</div>
		</div>
		<script>var l7ProfileEditData = <?= json_encode($l7_profile_edit_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>

		</div>
		<?php } ?>
		<?php } ?>

		<?php if ($l7_policy_mode === "profile_options") {
		$l7_po_ifaces = layer7_get_pfsense_interfaces();
		$l7_po_name = $l7_profile_options_profile
			? (string)($l7_profile_options_profile["name"] ?? $l7_profile_options_id)
			: (string)$l7_profile_options_id;
		if (!$l7_po_from_post && $l7_profile_options_profile !== null) {
			$l7_profile_options_state["profile_id"] = (string)$l7_profile_options_id;
		}
		?>
		<p>
			<a href="layer7_policies.php?library=1#l7-profiles" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a biblioteca")); ?></a>
		</p>
		<?php if (!$l7_library_available) { ?>
		<div class="alert alert-warning"><?php
		if ($l7_library_unavail_reason === "limit24") {
			echo l7_t("Limite de 24 politicas atingido. A biblioteca de perfis so fica disponivel com menos de 24 politicas aplicadas.");
		} else {
			echo l7_t("Catalogo de perfis indisponivel. Nenhum perfil foi carregado.");
		}
		?></div>
		<?php } elseif ($l7_profile_options_profile === null && !$l7_po_from_post) { ?>
		<div class="alert alert-danger"><?= htmlspecialchars(l7_t("Perfil nao encontrado.")); ?></div>
		<?php } else { ?>
		<div class="panel panel-default" id="l7-profile-options">
			<div class="panel-heading">
				<h2 class="panel-title"><?= htmlspecialchars(sprintf(l7_t("Opcoes: %s"), $l7_po_name)); ?></h2>
			</div>
			<div class="panel-body">
				<?= layer7_render_profile_options_form($l7_profile_options_state, $l7_po_ifaces, $l7_groups, "page"); ?>
			</div>
		</div>
		<?php } ?>
		<?php } ?>

		<?php if ($l7_policy_mode === "profile_edit") {
		$l7_pe_catalog = layer7_profiles_catalog();
		$l7_pe_title = l7_t("Editar perfil");
		if ($l7_pe_is_new) {
			$l7_pe_title = l7_t("Criar perfil personalizado");
		} elseif (is_array($l7_profile_edit_state)) {
			$l7_pe_title = l7_t("Editar perfil") . ": " . (string)($l7_profile_edit_state["name"] ?? $l7_profile_edit_id);
		} elseif ($l7_profile_edit_id !== null) {
			$l7_pe_title = l7_t("Editar perfil") . ": " . (string)$l7_profile_edit_id;
		}
		?>
		<p>
			<a href="layer7_policies.php?library=1#l7-profiles" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a biblioteca")); ?></a>
		</p>
		<?php if (!$l7_library_available) { ?>
		<div class="alert alert-warning"><?php
		if ($l7_library_unavail_reason === "limit24") {
			echo l7_t("Limite de 24 politicas atingido. A biblioteca de perfis so fica disponivel com menos de 24 politicas aplicadas.");
		} else {
			echo l7_t("Catalogo de perfis indisponivel. Nenhum perfil foi carregado.");
		}
		?></div>
		<?php } elseif (!$l7_pe_is_new && !$l7_pe_from_post && $l7_profile_edit_profile === null) { ?>
		<div class="alert alert-danger"><?= htmlspecialchars(l7_t("Perfil nao encontrado.")); ?></div>
		<?php } elseif (is_array($l7_profile_edit_state)) {
		$l7_pe_confirm_prof = $l7_profile_edit_profile;
		if ($l7_pe_confirm_prof === null && !$l7_pe_is_new) {
			$l7_pe_confirm_prof = layer7_policies_find_profile(
				(string)($l7_profile_edit_state["profile_id"] ?? ""));
		}
		$l7_pe_confirm_data = (!$l7_pe_is_new && is_array($l7_pe_confirm_prof))
			? layer7_profile_edit_confirm_map(
				(string)($l7_profile_edit_state["profile_id"] ?? ""),
				$policies,
				$l7_pe_confirm_prof,
				$l7_profiles_custom_for_edit
			)
			: array();
		?>
		<script>var l7ProfileEditData = <?= json_encode($l7_pe_confirm_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
		<div class="panel panel-default" id="l7-profile-edit">
			<div class="panel-heading">
				<h2 class="panel-title"><?= htmlspecialchars($l7_pe_title); ?></h2>
			</div>
			<div class="panel-body">
				<?php if (!empty($l7_profile_edit_state["connected"])) { ?>
				<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Este perfil esta ligado — ao guardar, a politica sera actualizada automaticamente com o novo snapshot.")); ?></div>
				<?php } ?>
				<?= layer7_render_profile_edit_form($l7_profile_edit_state, $l7_pe_catalog, "page"); ?>
			</div>
		</div>
		<?php } ?>
		<?php } ?>

		<?php if ($l7_policy_mode === "view") { ?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h2 class="panel-title"><?= htmlspecialchars(l7_t("Listas da politica")); ?></h2>
			</div>
			<div class="panel-body">
			<p><?= l7_t("Visualizacao rapida da regra, com todos os itens incluidos no match."); ?></p>
			<p>
				<a href="layer7_policies.php" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
				<a href="layer7_policies.php?edit=<?= (int)$view_idx; ?>" class="btn btn-info"><?= l7_t("Editar esta politica"); ?></a>
			</p>
			<dl class="dl-horizontal layer7-detail-grid">
				<dt><code>id</code></dt>
				<dd><code><?= htmlspecialchars((string)($view_policy["id"] ?? "")); ?></code></dd>
				<dt><?= l7_t("Nome"); ?></dt>
				<dd><?= htmlspecialchars((string)($view_policy["name"] ?? "")); ?></dd>
				<dt><?= l7_t("Acao"); ?></dt>
				<dd><span class="label label-default"><?= htmlspecialchars((string)($view_policy["action"] ?? "monitor")); ?></span></dd>
				<dt><?= l7_t("Interfaces"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["interfaces"]) ? implode("\n", $view_policy["interfaces"]) : l7_t("Todas")); ?></pre></dd>
				<dt><?= l7_t("Apps nDPI"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ndpi_app"]) ? implode("\n", $view_policy["match"]["ndpi_app"]) : l7_t("Qualquer app")); ?></pre></dd>
				<dt><?= l7_t("Categorias nDPI"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ndpi_category"]) ? implode("\n", $view_policy["match"]["ndpi_category"]) : l7_t("Qualquer categoria")); ?></pre></dd>
				<dt><?= l7_t("Sites/hosts"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["hosts"]) ? implode("\n", $view_policy["match"]["hosts"]) : l7_t("Qualquer host")); ?></pre></dd>
				<dt><?= l7_t("IPs de origem"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["src_hosts"]) ? implode("\n", $view_policy["match"]["src_hosts"]) : l7_t("Qualquer IP")); ?></pre></dd>
				<dt><?= l7_t("CIDRs de origem"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["src_cidrs"]) ? implode("\n", $view_policy["match"]["src_cidrs"]) : l7_t("Qualquer sub-rede")); ?></pre></dd>
				<dt><?= l7_t("Utilizadores AD"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ad_users"]) ? implode("\n", $view_policy["match"]["ad_users"]) : l7_t("Nenhum")); ?></pre></dd>
				<dt><?= l7_t("Grupos AD"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ad_groups"]) ? implode("\n", $view_policy["match"]["ad_groups"]) : l7_t("Nenhum")); ?></pre></dd>
				<dt><?= l7_t("Grupos"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["groups"]) ? implode("\n", $view_policy["match"]["groups"]) : l7_t("Nenhum grupo")); ?></pre></dd>
				<dt><?= l7_t("Horario"); ?></dt>
				<dd><?= htmlspecialchars(layer7_schedule_summary($view_policy)); ?></dd>
			</dl>
			</div>
		</div>
		<?php } ?>

		<?php if ($l7_policy_mode === "edit") { ?>
		<div id="l7-edit">
			<p><?= htmlspecialchars(l7_t("Atualize os detalhes da regra selecionada. O identificador permanece fixo para manter a referencia no JSON.")); ?></p>
			<p>
				<a href="layer7_policies.php" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
			</p>
<?php
	$edit_form = new Form(false);
	$edit_form->setAction("layer7_policies.php#l7-edit");
	$sec_edit = new Form_Section(l7_t("Editar politica"));
	$id_html = "<code>" . htmlspecialchars($l7_ef["id"] !== "" ? $l7_ef["id"] : "(vazio)") . "</code>";
	$id_st = new Form_StaticText("id", $id_html);
	$id_st->setHelp(l7_t("O id nao pode ser alterado pela GUI."));
	$sec_edit->addInput($id_st);
	$idx_hidden = new Form_Input("edit_policy_index", "", "hidden", (string)(int)$edit_idx);
	$sec_edit->addInput($idx_hidden);
	$edit_name = new Form_Input("edit_name", l7_t("Nome"), "text", $l7_ef["name"]);
	$edit_name->setAttribute("maxlength", "160");
	$sec_edit->addInput($edit_name);
	$edit_prio = new Form_Input(
		"edit_priority",
		l7_t("Prioridade"),
		"number",
		$l7_ef["priority"],
		array("min" => 0, "max" => 99999)
	);
	$sec_edit->addInput($edit_prio);
	$sec_edit->addInput(new Form_Select(
		"edit_action",
		l7_t("Acao"),
		$l7_ef["action"],
		$l7_action_opts
	));
	$sec_edit->addInput(new Form_StaticText(
		l7_t("Interfaces"),
		layer7_policies_ifaces_html(
			"edit_ifaces_list",
			"edit_ifaces[]",
			$l7_pf_ifaces,
			$l7_ef["ifaces"],
			!empty($l7_ef["ifaces_from_post"]),
			l7_t("Nenhuma = aplica a todas.")
		)
	));
	$edit_src_h = new Form_Textarea("edit_src_hosts", l7_t("IPs de origem"), $l7_ef["src_hosts"]);
	$edit_src_h->setRows(3);
	$edit_src_h->setHelp(l7_t("Um IPv4 por linha (max. 16). Vazio = qualquer IP."));
	$sec_edit->addInput($edit_src_h);
	$edit_src_c = new Form_Textarea("edit_src_cidrs", l7_t("CIDRs de origem"), $l7_ef["src_cidrs"]);
	$edit_src_c->setRows(2);
	$edit_src_c->setHelp(l7_t("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."));
	$sec_edit->addInput($edit_src_c);
	if ($l7_has_identity) {
		$edit_adu = new Form_Textarea("edit_ad_users", l7_t("Utilizadores AD"), $l7_ef["ad_users"]);
		$edit_adu->setRows(3);
		$edit_adu->setHelp(l7_t("Um utilizador por linha (max. 16). Aceita DOMAIN\\user ou UPN; o daemon normaliza. Match por IP do mapa Identity (activo)."));
		$sec_edit->addInput($edit_adu);
		$edit_adg = new Form_Textarea("edit_ad_groups", l7_t("Grupos AD"), $l7_ef["ad_groups"]);
		$edit_adg->setRows(2);
		$edit_adg->setHelp(l7_t("Um grupo AD por linha (max. 16). Distinto dos grupos IP/MAC Layer7."));
		$sec_edit->addInput($edit_adg);
	} elseif ($l7_ef["ad_users"] !== "" || $l7_ef["ad_groups"] !== "") {
		$sec_edit->addInput(new Form_StaticText(
			l7_t("Alvos Identity"),
			'<p class="help-block text-warning">' . htmlspecialchars(l7_t("Esta politica tem ad_users/ad_groups gravados; o entitlement Identity esta bloqueado — os alvos AD sao preservados sem edicao.")) . '</p>'
		));
	}
	if (!empty($l7_groups)) {
		$edit_grps = new Form_StaticText(
			l7_t("Grupos"),
			layer7_policies_group_boxes_html(
				"edit_groups_list",
				"edit_groups[]",
				$l7_groups,
				$l7_ef["groups"],
				true
			)
		);
		$edit_grps->setHelp(l7_t("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem."));
		$sec_edit->addInput($edit_grps);
	}
	$exc_html = '<p class="help-block">' . htmlspecialchars(l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis.")) . '</p>';
	if (!empty($l7_groups)) {
		$exc_html .= layer7_policies_group_boxes_html(
			"edit_exc_groups_list",
			"edit_src_exclude_groups[]",
			$l7_groups,
			$l7_ef["exc_groups"],
			false
		);
	}
	$sec_edit->addInput(new Form_StaticText(l7_t("Excluir origens (so este perfil)"), $exc_html));
	$edit_exc = new Form_Textarea("edit_src_exclude_cidrs", l7_t("CIDRs excluidos"), $l7_ef["exc_cidrs"]);
	$edit_exc->setRows(2);
	$edit_exc->setAttribute("placeholder", "192.168.1.50");
	$edit_exc->setHelp(l7_t("CIDRs excluidos (um por linha)."));
	$sec_edit->addInput($edit_exc);
	$edit_hosts = new Form_Textarea("edit_match_hosts", l7_t("Sites/hosts"), $l7_ef["hosts"]);
	$edit_hosts->setRows(3);
	$edit_hosts->setHelp(l7_t("Um host por linha, ex.: youtube.com ou api.whatsapp.com. O match aceita o host exacto e subdominios. Maximo 64 hosts por politica."));
	$sec_edit->addInput($edit_hosts);
	if (!empty($ndpi_protos)) {
		$edit_apps_st = new Form_StaticText(
			l7_t("Apps nDPI"),
			layer7_policies_ndpi_list_html(
				"edit_apps_list",
				"edit_ndpi_apps[]",
				$ndpi_protos,
				$l7_ef["apps"],
				l7_t("Pesquisar apps...")
			)
		);
		$edit_apps_st->setHelp(l7_t("Selecione ate 12 aplicacoes."));
		$sec_edit->addInput($edit_apps_st);
	} else {
		$edit_apps_in = new Form_Input("edit_ndpi_apps_csv", l7_t("Apps nDPI"), "text", $l7_ef["apps_csv"]);
		$edit_apps_in->setHelp(l7_t("Selecione ate 12 aplicacoes."));
		$sec_edit->addInput($edit_apps_in);
	}
	if (!empty($ndpi_cats)) {
		$edit_cats_st = new Form_StaticText(
			l7_t("Categorias nDPI"),
			layer7_policies_ndpi_list_html(
				"edit_cats_list",
				"edit_ndpi_category[]",
				$ndpi_cats,
				$l7_ef["cats"],
				l7_t("Pesquisar categorias...")
			)
		);
		$edit_cats_st->setHelp(l7_t("Selecione ate 8 categorias."));
		$sec_edit->addInput($edit_cats_st);
	} else {
		$edit_cats_in = new Form_Input("edit_ndpi_category_csv", l7_t("Categorias nDPI"), "text", $l7_ef["cats_csv"]);
		$edit_cats_in->setHelp(l7_t("Selecione ate 8 categorias."));
		$sec_edit->addInput($edit_cats_in);
	}
	$edit_tag = new Form_Input("edit_tag_table", "tag_table", "text", $l7_ef["tag_table"]);
	$edit_tag->setAttribute("maxlength", "63");
	$edit_tag->setPattern("[A-Za-z0-9_]+");
	$edit_tag->setHelp(l7_t("Obrigatorio quando a acao for tag."));
	$sec_edit->addInput($edit_tag);
	$sec_edit->addInput(new Form_StaticText(
		l7_t("Horario"),
		layer7_policies_schedule_html(
			"edit",
			$l7_sched_days,
			$l7_ef["sched_days"],
			$l7_ef["sched_start"],
			$l7_ef["sched_end"]
		)
	));
	$edit_en = new Form_Checkbox(
		"edit_enabled",
		l7_t("Ativa"),
		l7_t("Regra habilitada"),
		!empty($l7_ef["enabled"]),
		"1"
	);
	$sec_edit->addInput($edit_en);
	$edit_scope = new Form_Checkbox(
		"edit_scope_global",
		l7_t("Escopo global"),
		l7_t("Aplicar a toda a rede (sem origem definida)"),
		!empty($l7_ef["scope_global"]),
		"1"
	);
	$edit_scope->setHelp(l7_t("So relevante com enforcement escopado (scoped_hybrid). Sem IPs/CIDRs/grupos de origem, a politica block so gera regra PF global se esta opcao estiver activa."));
	$sec_edit->addInput($edit_scope);
	$sec_edit->addInput(new Form_StaticText(
		"",
		'<p class="help-block text-warning"><strong>' . htmlspecialchars(l7_t("Atencao:")) . '</strong> ' .
		htmlspecialchars(l7_t("Com match vazio (sem hosts/apps/categorias) e esta opcao activa, qualquer IP adicionado a tabela PF escopada bloqueia saida externa de forma global — efeito amplo em toda a rede. Use apenas com criterios explicitos ou origens definidas.")) .
		'</p>'
	));
	$edit_quar = new Form_Checkbox(
		"edit_quarantine_origin",
		l7_t("Quarentena origem"),
		l7_t("Bloquear toda a saida externa da origem (app-only sem destino)"),
		!empty($l7_ef["quarantine"]),
		"1"
	);
	$edit_quar->setHelp(l7_t("So relevante com enforcement escopado. Politicas block por app/categoria sem host exigem esta opcao para quarentenar a origem; caso contrario o bloqueio e ignorado com aviso no log."));
	$sec_edit->addInput($edit_quar);
	$edit_form->add($sec_edit);
	$save_btn = new Form_Button("save_policy_edit", l7_t("Guardar alteracoes"), null, "fa fa-save");
	$save_btn->addClass("btn-primary");
	$edit_form->addGlobal($save_btn);
	print($edit_form);
?>
		</div>
		<?php } ?>

		<?php if ($l7_policy_mode === "new") { ?>
		<div id="l7-add">
			<p>
				<a href="layer7_policies.php" class="btn btn-default"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
			</p>
			<p><?= htmlspecialchars(l7_t("Use nomes claros e prioridades previsiveis para manter a leitura do conjunto simples durante o troubleshooting.")); ?></p>
			<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= htmlspecialchars(l7_t("Limite de 24 politicas atingido.")); ?></div>
			<?php } else {
	$add_form = new Form(false);
	$add_form->setAction("layer7_policies.php#l7-add");
	$sec_add = new Form_Section(l7_t("Adicionar politica"));
	$new_id = new Form_Input("new_id", "id", "text", $l7_nf["id"]);
	$new_id->setAttribute("maxlength", "80");
	$new_id->setPattern("[a-zA-Z0-9_-]+");
	$new_id->setIsRequired();
	$new_id->setPlaceholder("p-exemplo-001");
	$sec_add->addInput($new_id);
	$new_name = new Form_Input("new_name", l7_t("Nome"), "text", $l7_nf["name"]);
	$new_name->setAttribute("maxlength", "160");
	$new_name->setPlaceholder(l7_t("Ex.: Monitor geral"));
	$sec_add->addInput($new_name);
	$new_prio = new Form_Input(
		"new_priority",
		l7_t("Prioridade"),
		"number",
		$l7_nf["priority"],
		array("min" => 0, "max" => 99999)
	);
	$sec_add->addInput($new_prio);
	$sec_add->addInput(new Form_Select(
		"new_action",
		l7_t("Acao"),
		$l7_nf["action"],
		$l7_action_opts
	));
	$sec_add->addInput(new Form_StaticText(
		l7_t("Interfaces"),
		layer7_policies_ifaces_html(
			"new_ifaces_list",
			"new_ifaces[]",
			$l7_pf_ifaces,
			$l7_nf["ifaces"],
			!empty($l7_add_retry),
			l7_t("Nenhuma selecionada = aplica a todas as interfaces.")
		)
	));
	$new_src_h = new Form_Textarea("new_src_hosts", l7_t("IPs de origem"), $l7_nf["src_hosts"]);
	$new_src_h->setRows(3);
	$new_src_h->setAttribute("placeholder", "192.168.1.50\n192.168.1.51");
	$new_src_h->setHelp(l7_t("Um IPv4 por linha (max. 16). Vazio = qualquer IP."));
	$sec_add->addInput($new_src_h);
	$new_src_c = new Form_Textarea("new_src_cidrs", l7_t("CIDRs de origem"), $l7_nf["src_cidrs"]);
	$new_src_c->setRows(2);
	$new_src_c->setAttribute("placeholder", "192.168.10.0/24");
	$new_src_c->setHelp(l7_t("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."));
	$sec_add->addInput($new_src_c);
	if ($l7_has_identity) {
		$new_adu = new Form_Textarea("new_ad_users", l7_t("Utilizadores AD"), $l7_nf["ad_users"]);
		$new_adu->setRows(3);
		$new_adu->setAttribute("placeholder", "joao.silva\nDOMAIN\\maria");
		$new_adu->setHelp(l7_t("Um utilizador por linha (max. 16). Aceita DOMAIN\\user ou UPN; o daemon normaliza. Match por IP do mapa Identity (activo)."));
		$sec_add->addInput($new_adu);
		$new_adg = new Form_Textarea("new_ad_groups", l7_t("Grupos AD"), $l7_nf["ad_groups"]);
		$new_adg->setRows(2);
		$new_adg->setAttribute("placeholder", "TI\nVPN");
		$new_adg->setHelp(l7_t("Um grupo AD por linha (max. 16). Distinto dos grupos IP/MAC Layer7."));
		$sec_add->addInput($new_adg);
	}
	if (!empty($l7_groups)) {
		$new_grps = new Form_StaticText(
			l7_t("Grupos"),
			layer7_policies_group_boxes_html(
				"new_groups_list",
				"new_groups[]",
				$l7_groups,
				$l7_nf["groups"],
				true
			)
		);
		$new_grps->setHelp(l7_t("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem. Alternativa a digitar CIDRs manualmente."));
		$sec_add->addInput($new_grps);
	}
	$new_exc_html = '<p class="help-block">' . htmlspecialchars(l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis.")) . '</p>';
	if (!empty($l7_groups)) {
		$new_exc_html .= layer7_policies_group_boxes_html(
			"new_exc_groups_list",
			"new_src_exclude_groups[]",
			$l7_groups,
			$l7_nf["exc_groups"],
			false
		);
	}
	$sec_add->addInput(new Form_StaticText(l7_t("Excluir origens (so este perfil)"), $new_exc_html));
	$new_exc = new Form_Textarea("new_src_exclude_cidrs", l7_t("CIDRs excluidos"), $l7_nf["exc_cidrs"]);
	$new_exc->setRows(2);
	$new_exc->setAttribute("placeholder", "192.168.1.50");
	$new_exc->setHelp(l7_t("CIDRs excluidos (um por linha)."));
	$sec_add->addInput($new_exc);
	$new_hosts = new Form_Textarea("new_match_hosts", l7_t("Sites/hosts"), $l7_nf["hosts"]);
	$new_hosts->setRows(3);
	$new_hosts->setAttribute("placeholder", "youtube.com\napi.whatsapp.com");
	$new_hosts->setHelp(l7_t("Um host por linha, ex.: youtube.com. Para block, basta indicar sites aqui (sem necessidade de app nDPI). O bloqueio DNS atua automaticamente. Maximo 64 hosts por politica."));
	$sec_add->addInput($new_hosts);
	if (!empty($ndpi_protos)) {
		$new_apps_st = new Form_StaticText(
			l7_t("Apps nDPI"),
			layer7_policies_ndpi_list_html(
				"new_apps_list",
				"new_ndpi_apps[]",
				$ndpi_protos,
				$l7_nf["apps"],
				l7_t("Pesquisar apps...")
			)
		);
		$new_apps_st->setHelp(l7_t("Selecione ate 12 aplicacoes. Em branco = qualquer app."));
		$sec_add->addInput($new_apps_st);
	} else {
		$new_apps_in = new Form_Input("new_ndpi_apps_csv", l7_t("Apps nDPI"), "text", $l7_nf["apps_csv"]);
		$new_apps_in->setPlaceholder("HTTP, BitTorrent");
		$new_apps_in->setHelp(l7_t("Selecione ate 12 aplicacoes. Em branco = qualquer app."));
		$sec_add->addInput($new_apps_in);
	}
	if (!empty($ndpi_cats)) {
		$new_cats_st = new Form_StaticText(
			l7_t("Categorias nDPI"),
			layer7_policies_ndpi_list_html(
				"new_cats_list",
				"new_ndpi_category[]",
				$ndpi_cats,
				$l7_nf["cats"],
				l7_t("Pesquisar categorias...")
			)
		);
		$new_cats_st->setHelp(l7_t("Selecione ate 8 categorias."));
		$sec_add->addInput($new_cats_st);
	} else {
		$new_cats_in = new Form_Input("new_ndpi_category_csv", l7_t("Categorias nDPI"), "text", $l7_nf["cats_csv"]);
		$new_cats_in->setPlaceholder("Web");
		$new_cats_in->setHelp(l7_t("Selecione ate 8 categorias."));
		$sec_add->addInput($new_cats_in);
	}
	$new_tag = new Form_Input("new_tag_table", "tag_table", "text", $l7_nf["tag_table"]);
	$new_tag->setAttribute("maxlength", "63");
	$new_tag->setPattern("[A-Za-z0-9_]+");
	$new_tag->setPlaceholder("layer7_tagged");
	$new_tag->setHelp(l7_t("Obrigatorio quando a acao for tag."));
	$sec_add->addInput($new_tag);
	$sec_add->addInput(new Form_StaticText(
		l7_t("Horario"),
		layer7_policies_schedule_html(
			"new",
			$l7_sched_days,
			$l7_nf["sched_days"],
			$l7_nf["sched_start"],
			$l7_nf["sched_end"]
		)
	));
	$new_en = new Form_Checkbox(
		"new_enabled",
		l7_t("Ativa"),
		l7_t("Criar politica ja habilitada"),
		!empty($l7_nf["enabled"]),
		"1"
	);
	$sec_add->addInput($new_en);
	$new_scope = new Form_Checkbox(
		"new_scope_global",
		l7_t("Escopo global"),
		l7_t("Aplicar a toda a rede (sem origem definida)"),
		!empty($l7_nf["scope_global"]),
		"1"
	);
	$new_scope->setHelp(l7_t("So relevante com enforcement escopado (scoped_hybrid). Sem IPs/CIDRs/grupos de origem, a politica block so gera regra PF global se esta opcao estiver activa."));
	$sec_add->addInput($new_scope);
	$sec_add->addInput(new Form_StaticText(
		"",
		'<p class="help-block text-warning"><strong>' . htmlspecialchars(l7_t("Atencao:")) . '</strong> ' .
		htmlspecialchars(l7_t("Com match vazio (sem hosts/apps/categorias) e esta opcao activa, qualquer IP adicionado a tabela PF escopada bloqueia saida externa de forma global — efeito amplo em toda a rede. Use apenas com criterios explicitos ou origens definidas.")) .
		'</p>'
	));
	$new_quar = new Form_Checkbox(
		"new_quarantine_origin",
		l7_t("Quarentena origem"),
		l7_t("Bloquear toda a saida externa da origem (app-only sem destino)"),
		!empty($l7_nf["quarantine"]),
		"1"
	);
	$new_quar->setHelp(l7_t("So relevante com enforcement escopado. Politicas block por app/categoria sem host exigem esta opcao para quarentenar a origem; caso contrario o bloqueio e ignorado com aviso no log."));
	$sec_add->addInput($new_quar);
	$add_form->add($sec_add);
	$add_btn = new Form_Button("add_policy", l7_t("Adicionar politica"), null, "fa fa-plus");
	$add_btn->addClass("btn-success");
	$add_form->addGlobal($add_btn);
	print($add_form);
			} ?>
			<p class="help-block"><?= htmlspecialchars(l7_t("Para alterar o id de uma politica existente, edite /usr/local/etc/layer7.json diretamente.")); ?></p>
		</div>
		<?php } ?>
<script>
var l7LegacyLibraryRedirectOk = <?= $l7_legacy_library_redirect_ok ? "true" : "false"; ?>;
var l7ProfileDraftBusy = false;
var l7ProfileDraftStrings = {
	pendingOne: <?= json_encode(l7_t("%d alteracao por aplicar")) ?>,
	pendingMany: <?= json_encode(l7_t("%d alteracoes por aplicar")) ?>,
	pendingGroupOne: <?= json_encode(l7_t("%d pendente")) ?>,
	pendingGroupMany: <?= json_encode(l7_t("%d pendentes")) ?>,
	scoped: <?= json_encode(l7_t("No modo scoped_hybrid, use Opcoes e selecione um CIDR/grupo de origem.")) ?>,
	confirmOff: <?= json_encode(l7_t("Vai desligar perfil(is) e remover a(s) politica(s). Continuar?")) ?>,
	applying: <?= json_encode(l7_t("A aplicar... aguarde o reload.")) ?>,
	fail: <?= json_encode(l7_t("Falha ao aplicar alteracoes de perfis.")) ?>
};

function l7toggleProfileDraft(btn) {
	if (l7ProfileDraftBusy || !btn) return;
	var saved = btn.getAttribute('data-saved') === '1';
	var desired = btn.getAttribute('data-desired') === '1';
	var next = !desired;
	if (next && btn.getAttribute('data-scoped') === '1') {
		alert(l7ProfileDraftStrings.scoped);
		return;
	}
	btn.setAttribute('data-desired', next ? '1' : '0');
	btn.classList.toggle('btn-success', next);
	btn.classList.toggle('btn-default', !next);
	btn.textContent = next ? <?= json_encode(l7_t("Desligar")) ?> : <?= json_encode(l7_t("Ligar")) ?>;
	btn.title = btn.textContent;
	btn.setAttribute('aria-label', btn.title);
	var card = btn.closest('.l7-profile-card');
	if (card) {
		card.classList.toggle('l7-profile-pending', next !== saved);
		card.classList.toggle('warning', next !== saved);
		card.classList.toggle('l7-profile-on', next);
	}
	l7updateProfileDraftBar();
}

function l7collectProfileDraft() {
	var enableIds = [];
	var disableIds = [];
	document.querySelectorAll('button[data-profile-id]').forEach(function(btn) {
		var id = btn.getAttribute('data-profile-id') || '';
		if (!id) return;
		var saved = btn.getAttribute('data-saved') === '1';
		var desired = btn.getAttribute('data-desired') === '1';
		if (desired === saved) return;
		if (desired) enableIds.push(id);
		else disableIds.push(id);
	});
	return { enableIds: enableIds, disableIds: disableIds };
}

function l7updateProfileDraftBar() {
	var d = l7collectProfileDraft();
	var n = d.enableIds.length + d.disableIds.length;
	var bar = document.getElementById('l7ProfileDraftBar');
	var msg = document.getElementById('l7ProfileDraftMsg');
	if (!bar || !msg) return;
	if (n === 0) {
		bar.hidden = true;
		msg.textContent = '';
	} else {
		bar.hidden = false;
		var tpl = n === 1 ? l7ProfileDraftStrings.pendingOne : l7ProfileDraftStrings.pendingMany;
		msg.textContent = tpl.replace('%d', String(n));
	}
	l7updateProfileGroupPendingBadges();
}

function l7updateProfileGroupPendingBadges() {
	var groups = document.querySelectorAll('.l7-profiles-groups .l7-profile-group');
	for (var i = 0; i < groups.length; i++) {
		var group = groups[i];
		var pending = group.querySelectorAll('.l7-profile-card.l7-profile-pending').length;
		var badge = group.querySelector('.l7-profile-group-pending-badge');
		if (!badge) continue;
		if (pending > 0) {
			var tpl = pending === 1 ? l7ProfileDraftStrings.pendingGroupOne : l7ProfileDraftStrings.pendingGroupMany;
			badge.textContent = tpl.replace('%d', String(pending));
			badge.hidden = false;
		} else {
			badge.textContent = '';
			badge.hidden = true;
		}
	}
}

function l7discardProfileDraft() {
	if (l7ProfileDraftBusy) return;
	document.querySelectorAll('button[data-profile-id]').forEach(function(btn) {
		var saved = btn.getAttribute('data-saved') === '1';
		btn.setAttribute('data-desired', saved ? '1' : '0');
		btn.classList.toggle('btn-success', saved);
		btn.classList.toggle('btn-default', !saved);
		btn.textContent = saved ? <?= json_encode(l7_t("Desligar")) ?> : <?= json_encode(l7_t("Ligar")) ?>;
		btn.title = btn.textContent;
		btn.setAttribute('aria-label', btn.title);
		var card = btn.closest('.l7-profile-card');
		if (card) {
			card.classList.remove('l7-profile-pending');
			card.classList.remove('warning');
			card.classList.toggle('l7-profile-on', saved);
		}
	});
	l7updateProfileDraftBar();
}

function l7csrfToken() {
	var el = document.querySelector('input[name="__csrf_magic"]');
	return el ? el.value : '';
}

function l7applyProfileDraft() {
	if (l7ProfileDraftBusy) return;
	var d = l7collectProfileDraft();
	if (!d.enableIds.length && !d.disableIds.length) {
		l7updateProfileDraftBar();
		return;
	}
	if (d.disableIds.length && !confirm(l7ProfileDraftStrings.confirmOff)) {
		return;
	}
	l7ProfileDraftBusy = true;
	var bar = document.getElementById('l7ProfileDraftBar');
	var msg = document.getElementById('l7ProfileDraftMsg');
	var applyBtn = document.getElementById('l7ProfileDraftApply');
	if (bar) bar.classList.add('l7-profile-draft-busy');
	if (msg) msg.textContent = l7ProfileDraftStrings.applying;
	if (applyBtn) applyBtn.disabled = true;

	var body = new FormData();
	body.append('apply_profiles_batch', '1');
	body.append('ajax', '1');
	body.append('enable_ids', d.enableIds.join(','));
	body.append('disable_ids', d.disableIds.join(','));
	var csrf = l7csrfToken();
	if (csrf) body.append('__csrf_magic', csrf);

	fetch('layer7_policies.php', {
		method: 'POST',
		body: body,
		credentials: 'same-origin',
		headers: { 'X-Requested-With': 'XMLHttpRequest' }
	}).then(function(r) {
		return r.json().then(function(j) { return { okHttp: r.ok, j: j }; }).catch(function() {
			return { okHttp: false, j: null };
		});
	}).then(function(res) {
		if (res.j && res.j.ok) {
			window.location.href = 'layer7_policies.php#l7-policies';
			return;
		}
		l7ProfileDraftBusy = false;
		if (bar) bar.classList.remove('l7-profile-draft-busy');
		if (applyBtn) applyBtn.disabled = false;
		var err = (res.j && (res.j.msg || (res.j.errors && res.j.errors.join(' ')))) || l7ProfileDraftStrings.fail;
		alert(err);
		l7updateProfileDraftBar();
	}).catch(function() {
		/* Fallback: form classico se fetch/CSRF falhar */
		var f = document.createElement('form');
		f.method = 'post';
		f.action = 'layer7_policies.php#l7-policies';
		function add(name, val) {
			var i = document.createElement('input');
			i.type = 'hidden';
			i.name = name;
			i.value = val;
			f.appendChild(i);
		}
		add('apply_profiles_batch', '1');
		add('enable_ids', d.enableIds.join(','));
		add('disable_ids', d.disableIds.join(','));
		if (csrf) add('__csrf_magic', csrf);
		document.body.appendChild(f);
		f.submit();
	});
}

window.addEventListener('beforeunload', function(ev) {
	if (l7ProfileDraftBusy) return;
	var d = l7collectProfileDraft();
	if (d.enableIds.length + d.disableIds.length > 0) {
		ev.preventDefault();
		ev.returnValue = '';
	}
});

function l7showProfileModal(profileId, profileName, ev) {
	var modal = document.getElementById('l7ProfileModal');
	var pid = document.getElementById('l7ProfileId');
	var title = document.getElementById('l7ProfileModalTitle');
	var hasModalApi = (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.modal);
	if (!modal || !pid || !title || !hasModalApi) {
		return true;
	}
	pid.value = profileId;
	title.textContent = profileName;
	jQuery(modal).modal('show');
	if (ev && typeof ev.preventDefault === 'function') {
		ev.preventDefault();
	}
	return false;
}
function l7hideProfileModal() {
	if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.modal) {
		jQuery('#l7ProfileModal').modal('hide');
	}
}

function l7profileEditSetVisible(el, show) {
	if (!el) {
		return;
	}
	if (show) {
		el.removeAttribute('hidden');
		el.classList.remove('hidden');
	} else {
		el.setAttribute('hidden', 'hidden');
		el.classList.add('hidden');
	}
}

function l7showProfileEditModal(profileId, isNew, ev) {
	var modal = document.getElementById('l7ProfileEditModal');
	var data = (typeof l7ProfileEditData !== 'undefined') ? l7ProfileEditData : {};
	var p = isNew ? null : data[profileId];
	var hasModalApi = (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.modal);
	if (!modal || !hasModalApi) {
		return true;
	}
	if (!isNew && (profileId === '' || !p)) {
		return true;
	}
	document.getElementById('l7EditProfileIsNew').value = isNew ? '1' : '0';
	document.getElementById('l7EditProfileId').value = isNew ? '' : profileId;
	document.getElementById('l7EditProfileName').value = p ? (p.name || '') : '';
	document.getElementById('l7EditProfileDesc').value = p ? (p.description || '') : '';
	document.getElementById('l7EditProfileIcon').value = p ? (p.icon || 'fa-cube') : 'fa-cube';
	document.getElementById('l7EditProfileHosts').value = p ? (p.hosts || '') : '';
	document.getElementById('l7EditProfileHidden').checked = p ? !!p.hidden : false;
	var apps = p ? (p.apps || []) : [];
	var cats = p ? (p.cats || []) : [];
	var appBoxes = document.querySelectorAll('#l7EditAppsList input[type=checkbox]');
	for (var i = 0; i < appBoxes.length; i++) {
		appBoxes[i].checked = apps.indexOf(appBoxes[i].value) >= 0;
	}
	var catBoxes = document.querySelectorAll('#l7EditCatsList input[type=checkbox]');
	for (var j = 0; j < catBoxes.length; j++) {
		catBoxes[j].checked = cats.indexOf(catBoxes[j].value) >= 0;
	}
	var appsFilter = document.getElementById('l7EditAppsFilter');
	var catsFilter = document.getElementById('l7EditCatsFilter');
	if (appsFilter) {
		appsFilter.value = '';
		l7filter(appsFilter, 'l7EditAppsList');
	}
	if (catsFilter) {
		catsFilter.value = '';
		l7filter(catsFilter, 'l7EditCatsList');
	}
	var customOnly = document.querySelectorAll('.l7-edit-custom-only');
	var factoryNote = document.querySelector('.l7-edit-factory-note');
	var isCustom = isNew || (p && p.is_custom);
	for (var k = 0; k < customOnly.length; k++) {
		l7profileEditSetVisible(customOnly[k], isCustom);
	}
	l7profileEditSetVisible(factoryNote, (!isNew && p && !p.is_custom));
	l7profileEditSetVisible(document.getElementById('l7EditProfileDeleteBtn'), (!isNew && p && p.is_custom));
	document.getElementById('l7ProfileEditModalTitle').textContent = isNew ?
		<?= json_encode(l7_t("Criar perfil personalizado")); ?> :
		(<?= json_encode(l7_t("Editar perfil")); ?> + ': ' + (p ? p.name : profileId));
	var warn = document.getElementById('l7ProfileEditReconnectWarn');
	if (p && p.connected) {
		l7profileEditSetVisible(warn, true);
		warn.textContent = <?= json_encode(l7_t("Este perfil esta ligado — ao guardar, a politica sera actualizada automaticamente com o novo snapshot.")); ?>;
	} else {
		l7profileEditSetVisible(warn, false);
		warn.textContent = '';
	}
	jQuery(modal).modal('show');
	if (ev && typeof ev.preventDefault === 'function') {
		ev.preventDefault();
	}
	return false;
}

function l7hideProfileEditModal() {
	if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.modal) {
		jQuery('#l7ProfileEditModal').modal('hide');
	}
}

function l7clearEditFilter(inputId, listId) {
	var input = document.getElementById(inputId);
	if (!input) {
		return;
	}
	input.value = '';
	l7filter(input, listId);
}

function l7confirmProfileEditSave(ev) {
	var sub = ev && ev.submitter ? ev.submitter : null;
	if (sub && sub.name === 'delete_custom_profile') {
		return true;
	}
	var data = (typeof l7ProfileEditData !== 'undefined') ? l7ProfileEditData : {};
	var id = document.getElementById('l7EditProfileId').value;
	if (id && data[id] && data[id].connected) {
		return confirm(<?= json_encode(l7_t("Este perfil esta ligado. A politica sera actualizada com o novo snapshot. Continuar?")); ?>);
	}
	return true;
}

function l7filter(input, listId) {
	var filter = input.value.toLowerCase();
	var wrap = document.getElementById(listId);
	if (!wrap) return;
	var labels = wrap.getElementsByTagName('label');
	for (var i = 0; i < labels.length; i++) {
		var txt = labels[i].textContent.toLowerCase();
		labels[i].style.display = txt.indexOf(filter) >= 0 ? '' : 'none';
	}
}

function l7toggleProfileGroup(headerEl, forceOpen) {
	var group = headerEl ? headerEl.closest('.l7-profile-group') : null;
	if (!group) return;
	if (group.tagName === 'DETAILS') {
		group.open = (typeof forceOpen === 'boolean') ? !!forceOpen : !group.open;
		return;
	}
	var collapsed = group.classList.contains('l7-profile-group-collapsed');
	var open = (typeof forceOpen === 'boolean') ? forceOpen : collapsed;
	if (open) {
		group.classList.remove('l7-profile-group-collapsed');
	} else {
		group.classList.add('l7-profile-group-collapsed');
	}
	var body = group.querySelector('.l7-profile-group-body');
	if (body) {
		body.hidden = !open;
	}
	if (headerEl && headerEl.setAttribute) {
		headerEl.setAttribute('aria-expanded', open ? 'true' : 'false');
	}
}

function l7setAllProfileGroups(open) {
	var groups = document.querySelectorAll('.l7-profiles-groups .l7-profile-group');
	for (var i = 0; i < groups.length; i++) {
		if (groups[i].tagName === 'DETAILS') {
			groups[i].open = !!open;
			continue;
		}
		var hdr = groups[i].querySelector('.l7-profile-group-header');
		if (hdr) {
			l7toggleProfileGroup(hdr, !!open);
		}
	}
}

function l7initProfileGroups() {
	if (l7LegacyLibraryRedirectOk && !document.getElementById('l7ProfileSearch') && document.getElementById('l7-policies')) {
		var legacyHash = window.location.hash;
		if (legacyHash === '#l7-profiles' || legacyHash === '#l7-ra') {
			window.location.replace('layer7_policies.php?library=1' + legacyHash);
			return;
		}
	}
	var groups = document.querySelectorAll('.l7-profiles-groups .l7-profile-group');
	for (var i = 0; i < groups.length; i++) {
		var g = groups[i];
		if (g.tagName === 'DETAILS') {
			g.open = g.getAttribute('data-group-default-open') === '1';
			continue;
		}
		g.classList.add('l7-profile-group-collapsed');
		var hdr = g.querySelector('.l7-profile-group-header');
		var body = g.querySelector('.l7-profile-group-body');
		if (hdr) {
			hdr.setAttribute('aria-expanded', 'false');
		}
		if (body) {
			body.hidden = true;
		}
	}
	/* Bookmark legado layer7_remote_access.php → #l7-ra */
	if (window.location.hash === '#l7-ra') {
		var ra = document.getElementById('l7-ra');
		if (ra) {
			if (ra.tagName === 'DETAILS') {
				ra.open = true;
			} else {
				var hdrRa = ra.querySelector('.l7-profile-group-header');
				if (hdrRa) {
					l7toggleProfileGroup(hdrRa, true);
				}
			}
			try {
				ra.scrollIntoView({ behavior: 'smooth', block: 'start' });
			} catch (e2) {
				ra.scrollIntoView(true);
			}
		}
	}
}

function l7filterProfileGrid() {
	var searchEl = document.getElementById('l7ProfileSearch');
	var activeOnlyEl = document.getElementById('l7ProfileActiveOnly');
	var q = searchEl ? searchEl.value.toLowerCase().trim() : '';
	var activeOnly = activeOnlyEl ? activeOnlyEl.checked : false;
	var groups = document.querySelectorAll('.l7-profiles-groups .l7-profile-group');
	for (var g = 0; g < groups.length; g++) {
		var group = groups[g];
		var cards = group.querySelectorAll('.l7-profile-card');
		var visible = 0;
		for (var c = 0; c < cards.length; c++) {
			var card = cards[c];
			var hay = (card.getAttribute('data-profile-search') || '') + ' ' + (card.getAttribute('data-profile-name') || '');
			var matchQ = !q || hay.indexOf(q) >= 0;
			var matchActive = !activeOnly || card.getAttribute('data-profile-active') === '1';
			var show = matchQ && matchActive;
			if (show) {
				card.classList.remove('l7-profile-filter-hidden');
				card.hidden = false;
				visible++;
			} else {
				card.classList.add('l7-profile-filter-hidden');
				card.hidden = true;
			}
		}
		if (visible === 0 && (q || activeOnly)) {
			group.classList.add('l7-profile-group-filter-hidden');
			group.hidden = true;
		} else {
			group.classList.remove('l7-profile-group-filter-hidden');
			group.hidden = false;
			if ((q || activeOnly) && visible > 0) {
				if (group.tagName === 'DETAILS') {
					group.open = true;
				} else {
					var hdr = group.querySelector('.l7-profile-group-header');
					if (hdr) {
						l7toggleProfileGroup(hdr, true);
					}
				}
			}
		}
	}
}

function l7setChecks(listId, checked, onlyVisible) {
	var wrap = document.getElementById(listId);
	var i, boxes, label;
	if (!wrap) return;
	boxes = wrap.querySelectorAll('input[type="checkbox"]');
	for (i = 0; i < boxes.length; i++) {
		label = boxes[i].closest('label');
		if (onlyVisible && label && label.style.display === 'none') {
			continue;
		}
		boxes[i].checked = checked;
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', l7initProfileGroups);
} else {
	l7initProfileGroups();
}
</script>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc"); ?>
