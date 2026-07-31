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

					$apps = isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"]) ? $profile["ndpi_apps"] : array();
					$hosts = isset($profile["hosts"]) && is_array($profile["hosts"]) ? $profile["hosts"] : array();
					$cats = isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"]) ? $profile["ndpi_categories"] : array();
					if (!empty($apps)) {
						$rule["match"]["ndpi_app"] = array_slice($apps, 0, 64);
					}
					if (!empty($cats)) {
						$rule["match"]["ndpi_category"] = array_slice($cats, 0, 8);
					}
					if (!empty($hosts)) {
						$rule["match"]["hosts"] = array_slice($hosts, 0, 64);
					}

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

/* Caminho A / A4 — toggle directo de perfil ON (um clique). Cria a politica
 * `profile-<id>` com accao block (em modo monitor fica apenas observada). Para
 * controlo fino (interfaces, CIDRs, grupos) continua a existir o modal. */
if ($_POST["toggle_profile_on"] ?? false) {
		$profile_id = trim($_POST["profile_id"] ?? "");
		if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $profile_id)) {
			$input_errors[] = l7_t("Perfil invalido.");
		} else {
			$profiles = layer7_load_profiles();
			$profile = null;
			foreach ($profiles as $p) {
				if (($p["id"] ?? "") === $profile_id) { $profile = $p; break; }
			}
			if ($profile === null) {
				$input_errors[] = l7_t("Perfil nao encontrado.");
			} else {
				$data = layer7_load_or_default();
				if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
					$data["layer7"]["policies"] = array();
				}
				$policies = &$data["layer7"]["policies"];
				$pid = "profile-" . $profile_id;
				$dup = false;
				foreach ($policies as $ex) {
					if (($ex["id"] ?? "") === $pid) { $dup = true; break; }
				}
				if ($dup) {
					$savemsg = sprintf(l7_t("Perfil '%s' ja esta ligado."), $profile["name"] ?? $profile_id);
				} elseif (count($policies) >= 24) {
					$input_errors[] = l7_t("Limite de 24 politicas.");
				} elseif (layer7_enforcement_is_scoped_hybrid($data)) {
					$input_errors[] = l7_t("No modo scoped_hybrid, use Configurar e selecione um CIDR/grupo de origem. O botao de um clique nao cria escopo global implicito.");
				} else {
					$rule = array(
						"id" => $pid,
						"name" => $profile["name"] ?? $pid,
						"enabled" => true,
						"action" => "block",
						"priority" => 20,
						"match" => array(),
					);
					$apps = (isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"])) ? $profile["ndpi_apps"] : array();
					$hosts = (isset($profile["hosts"]) && is_array($profile["hosts"])) ? $profile["hosts"] : array();
					$cats = (isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"])) ? $profile["ndpi_categories"] : array();
					if (!empty($apps)) { $rule["match"]["ndpi_app"] = array_slice($apps, 0, 64); }
					if (!empty($cats)) { $rule["match"]["ndpi_category"] = array_slice($cats, 0, 8); }
					if (!empty($hosts)) { $rule["match"]["hosts"] = array_slice($hosts, 0, 64); }
					$policies[] = $rule;
					if (layer7_save_json($data)) {
						layer7_pf_config_resync(true);
						$savemsg = sprintf(l7_t("Perfil '%s' ligado (accao block; em modo monitor fica apenas observado)."), $profile["name"] ?? $profile_id);
						if (($profile["extra_action"] ?? "") === "configure_unbound_anti_doh") {
							$doh_result = layer7_configure_unbound_anti_doh();
							if ($doh_result["ok"]) { $savemsg .= " " . l7_t("Unbound anti-DoH tambem configurado."); }
						}
					}
				}
				unset($policies);
			}
		}
}

/* Caminho A / A4 — toggle directo de perfil OFF. Remove a politica
 * `profile-<id>`. Seguro: so remove politicas cujo id casa exactamente. */
if ($_POST["toggle_profile_off"] ?? false) {
		$profile_id = trim($_POST["profile_id"] ?? "");
		if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $profile_id)) {
			$input_errors[] = l7_t("Perfil invalido.");
		} else {
			$pid = "profile-" . $profile_id;
			$data = layer7_load_or_default();
			if (isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])) {
				$before = count($data["layer7"]["policies"]);
				$data["layer7"]["policies"] = array_values(array_filter(
					$data["layer7"]["policies"],
					function ($p) use ($pid) { return ($p["id"] ?? "") !== $pid; }
				));
				if (count($data["layer7"]["policies"]) !== $before) {
					if (layer7_save_json($data)) {
						layer7_pf_config_resync(true);
						$savemsg = sprintf(l7_t("Perfil '%s' desligado."), $profile_id);
					}
				} else {
					$savemsg = sprintf(l7_t("Perfil '%s' ja estava desligado."), $profile_id);
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

$ndpi_list = layer7_ndpi_list();
$ndpi_protos = isset($ndpi_list["protocols"]) ? $ndpi_list["protocols"] : array();
$ndpi_cats = isset($ndpi_list["categories"]) ? $ndpi_list["categories"] : array();
sort($ndpi_protos);
sort($ndpi_cats);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Policies"));
include("head.inc");
layer7_render_styles();

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
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - politicas"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("policies"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= l7_t("Gerir politicas de classificacao e bloqueio."); ?></p>

		<?php
		$_nav_data = layer7_load_or_default();
		$_nav_groups = isset($_nav_data["layer7"]["groups"]) ? count($_nav_data["layer7"]["groups"]) : 0;
		$_nav_exceptions = isset($_nav_data["layer7"]["exceptions"]) ? count($_nav_data["layer7"]["exceptions"]) : 0;
		?>
		<div class="layer7-toolbar" style="margin-bottom:16px;">
			<a href="layer7_groups.php" class="btn btn-default btn-sm"><i class="fa fa-users"></i> <?= l7_t("Grupos"); ?> (<?= $_nav_groups; ?>)</a>
			<a href="layer7_exceptions.php" class="btn btn-default btn-sm"><i class="fa fa-shield"></i> <?= l7_t("Excecoes"); ?> (<?= $_nav_exceptions; ?>)</a>
			<a href="layer7_categories.php" class="btn btn-default btn-sm"><i class="fa fa-th-list"></i> <?= l7_t("Categorias nDPI"); ?></a>
			<a href="layer7_test.php" class="btn btn-default btn-sm"><i class="fa fa-play-circle"></i> <?= l7_t("Simular teste"); ?></a>
		</div>

		<?php
		$l7_profiles = layer7_load_profiles();
		if (!empty($l7_profiles) && !$at_limit) {
		$prof_ifaces = layer7_get_pfsense_interfaces();
		?>
		<?php
		/* A4: contadores de hits por perfil a partir das stats do daemon. */
		$l7_prof_hits = layer7_profile_hit_counts($l7_profiles, layer7_read_stats());
		?>
		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Perfis rapidos"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Ligue ou desligue um perfil com um clique — cria/remove automaticamente a politica com todas as apps e dominios associados (accao block; em modo monitor fica apenas observado). Use 'Opcoes' para escolher accao, interfaces e sub-redes."); ?></p>

		<?php
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
			"remote-access" => "#E74C3C",
			"anti-bypass-dns" => "#E67E22",
		);
		$l7_group_colors = array(
			"Redes sociais" => "#4267B2",
			"Mensageria" => "#00A884",
			"Comunicação e reuniões" => "#2D8CFF",
			"Streaming" => "#FF6D00",
			"Jogos" => "#7B2FBE",
			"Produtividade" => "#546E7A",
			"Segurança e bypass" => "#2C3E50",
			"Presets" => "#16A085",
		);
		?>
		<div class="l7-profiles-groups">
		<?php
		$l7_group_order = array(
			l7_t("Redes sociais"),
			l7_t("Mensageria"),
			l7_t("Comunicação e reuniões"),
			l7_t("Streaming"),
			l7_t("Jogos"),
			l7_t("Produtividade"),
			l7_t("Segurança e bypass"),
			l7_t("Presets"),
		);
		$l7_profiles_by_group = array();
		foreach ($l7_profiles as $prof) {
			$gk = isset($prof["group"]) && is_string($prof["group"]) && trim($prof["group"]) !== ""
			    ? l7_t(trim($prof["group"])) : l7_t("Outros");
			if (!isset($l7_profiles_by_group[$gk])) {
				$l7_profiles_by_group[$gk] = array();
			}
			$l7_profiles_by_group[$gk][] = $prof;
		}
		$l7_groups_rendered = array();
		$l7_render_profile_card = function ($prof) use ($policies, $l7_brand_colors, $l7_group_colors, $l7_prof_hits) {
			$prof_id = isset($prof["id"]) ? htmlspecialchars($prof["id"]) : "";
			$prof_name = isset($prof["name"]) ? htmlspecialchars(l7_t($prof["name"])) : $prof_id;
			$prof_desc = isset($prof["description"]) ? htmlspecialchars(l7_t($prof["description"])) : "";
			$prof_apps_count = isset($prof["ndpi_apps"]) && is_array($prof["ndpi_apps"]) ? count($prof["ndpi_apps"]) : 0;
			$prof_hosts_count = isset($prof["hosts"]) && is_array($prof["hosts"]) ? count($prof["hosts"]) : 0;
			$prof_exists = false;
			$prof_pid = "profile-" . ($prof["id"] ?? "");
			foreach ($policies as $existing) {
				if (isset($existing["id"]) && (string)$existing["id"] === $prof_pid) {
					$prof_exists = true;
					break;
				}
			}
			/* Glifo FA 4.7 do profiles.json (sanitizado); cor de marca ou do grupo. */
			$icon_fa = "fa-cube";
			if (isset($prof["icon"]) && is_string($prof["icon"]) && preg_match('/^fa-[a-z0-9-]{1,40}$/', $prof["icon"])) {
				$icon_fa = $prof["icon"];
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
		?>
			<div class="l7-profile-card<?= $prof_exists ? ' l7-profile-on' : ''; ?>">
				<div class="l7-profile-state"><?php if ($prof_exists) { ?><span class="l7-dot l7-dot-on" title="<?= l7_t("Ligado"); ?>"></span><?php } else { ?><span class="l7-dot l7-dot-off" title="<?= l7_t("Desligado"); ?>"></span><?php } ?></div>
				<div class="l7-profile-icon-ios" style="background:<?= $icon_bg; ?>;color:<?= $icon_fg; ?>;">
					<i class="fa <?= htmlspecialchars($icon_fa); ?>" aria-hidden="true"></i>
				</div>
				<div class="l7-profile-name"><?= $prof_name; ?></div>
				<div class="l7-profile-desc" title="<?= $prof_desc; ?>"><?= $prof_desc; ?></div>
				<div class="l7-profile-meta"><?= $prof_apps_count; ?> apps &middot; <?= $prof_hosts_count; ?> hosts<?php if ($prof_exists && $prof_hit > 0) { ?> &middot; <span class="l7-profile-hits" title="<?= l7_t("Bloqueios observados pelo daemon"); ?>"><?= $prof_hit; ?> <?= l7_t("hits"); ?></span><?php } ?></div>
				<div class="l7-profile-cta">
				<?php if ($prof_exists) { ?>
				<form method="post" action="layer7_policies.php#l7-policies" style="margin:0;" onsubmit='return confirm(<?= htmlspecialchars(json_encode(l7_t("Desligar este perfil (remove a politica)? A excepcao VIP isentos, se existir, permanece activa.")), ENT_QUOTES); ?>);'>
					<input type="hidden" name="profile_id" value="<?= $prof_id; ?>" />
					<button type="submit" name="toggle_profile_off" value="1" class="btn btn-sm btn-danger"><i class="fa fa-power-off"></i> <?= l7_t("Desligar"); ?></button>
				</form>
				<?php } else { ?>
				<div class="l7-profile-actions">
					<form method="post" action="layer7_policies.php#l7-policies" style="margin:0;display:inline-block;">
						<input type="hidden" name="profile_id" value="<?= $prof_id; ?>" />
						<button type="submit" name="toggle_profile_on" value="1" class="btn btn-sm btn-success"><i class="fa fa-power-off"></i> <?= l7_t("Ligar"); ?></button>
					</form>
					<button type="button" class="btn btn-sm btn-default" onclick="l7showProfileModal(<?= htmlspecialchars(json_encode($prof_id), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($prof_name), ENT_QUOTES) ?>);"><?= l7_t("Opcoes"); ?></button>
				</div>
				<?php } ?>
				</div>
			</div>
		<?php
		};
		/* Cada grupo e uma seccao propria: cabecalho full-width + grelha de cartoes. */
		$l7_render_profile_group = function ($gname, $gprofs) use ($l7_render_profile_card) {
		?>
			<div class="l7-profile-group">
				<div class="l7-profile-group-header">
					<span class="l7-profile-group-title"><?= htmlspecialchars($gname); ?></span>
					<span class="l7-profile-group-count"><?= count($gprofs); ?> <?= l7_t("perfis"); ?></span>
				</div>
				<div class="l7-profiles-grid">
				<?php
				foreach ($gprofs as $prof) {
					$l7_render_profile_card($prof);
				}
				?>
				</div>
			</div>
		<?php
		};
		foreach ($l7_group_order as $l7_gname) {
			if (empty($l7_profiles_by_group[$l7_gname])) {
				continue;
			}
			$l7_groups_rendered[$l7_gname] = true;
			$l7_render_profile_group($l7_gname, $l7_profiles_by_group[$l7_gname]);
		}
		foreach ($l7_profiles_by_group as $l7_gname => $l7_gprofs) {
			if (!empty($l7_groups_rendered[$l7_gname])) {
				continue;
			}
			$l7_render_profile_group($l7_gname, $l7_gprofs);
		}
		?>
		</div>
		</div>

		<div id="l7ProfileModal" class="l7-modal-overlay" style="display:none;">
			<div class="l7-modal-box">
				<h4 id="l7ProfileModalTitle"></h4>
				<form method="post" action="layer7_policies.php#l7-policies" class="form-horizontal">
					<input type="hidden" name="profile_id" id="l7ProfileId" value="" />
					<input type="hidden" name="add_profile_policy" value="1" />

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("Accao"); ?></label>
						<div class="col-sm-8">
							<select name="profile_action" class="form-control">
								<option value="block" selected="selected"><?= l7_t("block"); ?></option>
								<option value="monitor"><?= l7_t("monitor"); ?></option>
								<option value="allow"><?= l7_t("allow"); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("Aplicar a"); ?></label>
						<div class="col-sm-8">
							<p class="text-muted small"><strong><?= l7_t("Interfaces"); ?></strong></p>
						<?php foreach ($prof_ifaces as $ifc) { ?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
								<?= htmlspecialchars($ifc["descr"]); ?>
							</label>
						<?php } ?>
							<p class="help-block"><?= l7_t("Nenhuma = todas."); ?></p>

							<p class="text-muted small" style="margin-top:10px;"><strong><?= l7_t("Grupos"); ?></strong></p>
						<?php if (empty($l7_groups)) { ?>
							<p class="help-block">
								<a href="layer7_groups.php" class="btn btn-xs btn-default"><?= l7_t("Criar grupo (ex.: Gestores)"); ?></a>
							</p>
						<?php } else { ?>
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_groups[]" value="<?= $gid; ?>" />
								<?= $gname; ?>
							</label>
						<?php } ?>
							<p class="help-block"><?= l7_t("Preferivel a CIDRs manuais."); ?></p>
						<?php } ?>
						</div>
					</div>

					<div class="form-group l7-modal-section-vip">
						<label class="col-sm-4 control-label"><?= l7_t("Isentos (nunca bloqueados)"); ?></label>
						<div class="col-sm-8">
							<p class="help-block"><?= l7_t("Isencao global: estes IPs/dispositivos nunca sao bloqueados por nenhum perfil Layer7. Gere a excepcao partilhada vip-isentos."); ?></p>
							<?php if (!empty($l7_groups)) { ?>
							<p class="text-muted small"><strong><?= l7_t("Grupos isentos"); ?></strong></p>
							<?php foreach ($l7_groups as $grp) {
								$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
								$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
								$gchk = in_array($grp["id"] ?? "", $l7_vip_groups_sel, true) ? ' checked="checked"' : "";
							?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_vip_groups[]" value="<?= $gid; ?>"<?= $gchk; ?> />
								<?= $gname; ?>
							</label>
							<?php } ?>
							<?php } ?>
							<label class="control-label small" style="margin-top:8px;"><?= l7_t("IPs isentos"); ?></label>
							<textarea name="profile_vip_hosts" class="form-control" rows="2" placeholder="192.168.1.50"><?= htmlspecialchars($l7_vip_hosts_val); ?></textarea>
							<label class="control-label small" style="margin-top:8px;"><?= l7_t("CIDRs isentos"); ?></label>
							<textarea name="profile_vip_cidrs" class="form-control" rows="2" placeholder="192.168.1.0/24"><?= htmlspecialchars($l7_vip_cidrs_val); ?></textarea>
							<p class="help-block"><?= l7_t("Desligar um perfil nao remove a excepcao VIP — continua editavel em Excepcoes."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-4 col-sm-8">
							<a data-toggle="collapse" href="#l7ProfileModalAdvanced" style="cursor:pointer;">
								<i class="fa fa-cog"></i> <?= l7_t("Avancado"); ?>
							</a>
						</div>
					</div>
					<div id="l7ProfileModalAdvanced" class="collapse">
					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("CIDRs de origem"); ?></label>
						<div class="col-sm-8">
							<textarea name="profile_src_cidrs" class="form-control" rows="2" placeholder="192.168.10.0/24"></textarea>
							<p class="help-block"><?= l7_t("Vazio = qualquer sub-rede. Use apenas se grupos nao forem suficientes."); ?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("Excluir origens (so este perfil)"); ?></label>
						<div class="col-sm-8">
							<p class="help-block"><?= l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis."); ?></p>
						<?php if (!empty($l7_groups)) { ?>
							<p class="text-muted small"><strong><?= l7_t("Grupos excluidos"); ?></strong></p>
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_src_exclude_groups[]" value="<?= $gid; ?>" />
								<?= $gname; ?>
							</label>
						<?php } ?>
						<?php } ?>
							<label class="control-label small" style="margin-top:8px;"><?= l7_t("CIDRs excluidos"); ?></label>
							<textarea name="profile_src_exclude_cidrs" class="form-control" rows="2" placeholder="192.168.1.50"></textarea>
						</div>
					</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-4 col-sm-8">
							<a href="layer7_test.php" class="btn btn-link btn-sm" style="padding-left:0;">
								<i class="fa fa-search"></i> <?= l7_t("Verificador de politica efectiva"); ?>
							</a>
						</div>
					</div>

					<div class="form-group">
						<div class="col-sm-offset-4 col-sm-8">
							<button type="submit" class="btn btn-success"><?= l7_t("Criar politica"); ?></button>
							<button type="button" class="btn btn-default" onclick="l7hideProfileModal();"><?= l7_t("Cancelar"); ?></button>
						</div>
					</div>
				</form>
		</div>
		</div>
		</div>
		<?php } ?>

		<div class="layer7-admin-block" id="l7-policies">
			<div class="layer7-admin-block__header"><?= l7_t("Politicas atuais"); ?></div>
			<div class="layer7-admin-block__body">
			<?php if (count($policies) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma politica cadastrada. Adicione a primeira regra abaixo ou importe um layer7.json existente."); ?></div>
			<?php } else { ?>
			<div class="layer7-form-card">
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
						?>
							<tr>
								<td><input type="checkbox" name="pon[<?= (int)$i; ?>]" value="1" <?= $enabled ? 'checked="checked"' : ''; ?> /></td>
								<td><?= htmlspecialchars((string)$priority); ?></td>
								<td><?= htmlspecialchars($name); ?></td>
								<td><span class="label label-default"><?= htmlspecialchars($action); ?></span></td>
								<td class="small"><?= htmlspecialchars(implode(" | ", $matches)); ?></td>
								<td><code><?= htmlspecialchars($pid); ?></code></td>
								<td class="layer7-table-actions">
									<a href="layer7_policies.php?view=<?= (int)$i; ?>" class="btn btn-xs btn-default"><?= l7_t("Ver listas"); ?></a>
									<a href="layer7_policies.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= l7_t("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="layer7-toolbar">
					<button type="submit" name="save_policies" value="1" class="btn btn-primary"><?= l7_t("Guardar estado das politicas"); ?></button>
				</div>
			</form>
			</div>

			<div style="margin-top:12px;">
				<a data-toggle="collapse" href="#l7-delete-policy" style="cursor:pointer; color:#d9534f;">
					<i class="fa fa-trash"></i> <?= l7_t("Remover politica"); ?>
				</a>
			</div>
			<div id="l7-delete-policy" class="collapse" style="margin-top:8px;">
				<form method="post" action="layer7_policies.php#l7-policies" class="form-inline"
					onsubmit='return confirm(<?= json_encode(l7_t("Remover esta politica do JSON?")); ?>);'>
					<select name="delete_policy_index" class="form-control input-sm">
						<?php foreach ($policies as $i => $policy) {
							$pid = isset($policy["id"]) ? (string)$policy["id"] : ("#" . $i);
							$pname = isset($policy["name"]) ? (string)$policy["name"] : "";
							$label = $pid . ($pname !== "" ? " - " . $pname : "");
						?>
						<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
						<?php } ?>
					</select>
					<button type="submit" name="delete_policy" value="1" class="btn btn-sm btn-danger"><?= l7_t("Remover"); ?></button>
				</form>
			</div>
			<?php } ?>
			</div>
		</div>

		<?php if ($view_policy !== null && $view_idx !== null) { ?>
		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Listas da politica"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Visualizacao rapida da regra, com todos os itens incluidos no match."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_policies.php" class="btn btn-default"><?= l7_t("Fechar"); ?></a>
				<a href="layer7_policies.php?edit=<?= (int)$view_idx; ?>" class="btn btn-info"><?= l7_t("Editar esta politica"); ?></a>
			</div>
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
				<dt><?= l7_t("Grupos"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["groups"]) ? implode("\n", $view_policy["match"]["groups"]) : l7_t("Nenhum grupo")); ?></pre></dd>
				<dt><?= l7_t("Horario"); ?></dt>
				<dd><?= htmlspecialchars(layer7_schedule_summary($view_policy)); ?></dd>
			</dl>
			</div>
		</div>
		<?php } ?>

		<?php if ($edit_policy !== null && $edit_idx !== null) {
			$edit_id = isset($edit_policy["id"]) ? (string)$edit_policy["id"] : "";
			$edit_name = isset($edit_policy["name"]) ? (string)$edit_policy["name"] : "";
			$edit_priority = isset($edit_policy["priority"]) ? (int)$edit_policy["priority"] : 0;
			$edit_action = isset($edit_policy["action"]) ? (string)$edit_policy["action"] : "monitor";
			if (!in_array($edit_action, array("monitor", "allow", "block", "tag"), true)) {
				$edit_action = "monitor";
			}
			$edit_enabled = !empty($edit_policy["enabled"]);
			$edit_apps = "";
			if (isset($edit_policy["match"]["ndpi_app"]) && is_array($edit_policy["match"]["ndpi_app"])) {
				$edit_apps = implode(", ", $edit_policy["match"]["ndpi_app"]);
			}
			$edit_categories = "";
			if (isset($edit_policy["match"]["ndpi_category"]) && is_array($edit_policy["match"]["ndpi_category"])) {
				$edit_categories = implode(", ", $edit_policy["match"]["ndpi_category"]);
			}
			$edit_hosts_match_val = "";
			if (isset($edit_policy["match"]["hosts"]) && is_array($edit_policy["match"]["hosts"])) {
				$edit_hosts_match_val = implode("\n", $edit_policy["match"]["hosts"]);
			}
			$edit_tag_table = isset($edit_policy["tag_table"]) ? (string)$edit_policy["tag_table"] : "";
			$edit_sched_days = array();
			$edit_sched_start = "";
			$edit_sched_end = "";
			if (isset($edit_policy["schedule"]) && is_array($edit_policy["schedule"])) {
				$edit_sched_days = isset($edit_policy["schedule"]["days"]) && is_array($edit_policy["schedule"]["days"]) ? $edit_policy["schedule"]["days"] : array();
				$edit_sched_start = isset($edit_policy["schedule"]["start"]) ? (string)$edit_policy["schedule"]["start"] : "";
				$edit_sched_end = isset($edit_policy["schedule"]["end"]) ? (string)$edit_policy["schedule"]["end"] : "";
			}
		?>
		<div class="layer7-admin-block" id="l7-edit">
			<div class="layer7-admin-block__header"><?= l7_t("Editar politica"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Atualize os detalhes da regra selecionada. O identificador permanece fixo para manter a referencia no JSON."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_policies.php" class="btn btn-default"><?= l7_t("Cancelar edicao"); ?></a>
			</div>
			<form method="post" action="layer7_policies.php#l7-edit" class="form-horizontal">
				<input type="hidden" name="edit_policy_index" value="<?= (int)$edit_idx; ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-9">
						<p class="form-control-static"><code><?= htmlspecialchars($edit_id !== "" ? $edit_id : "(vazio)"); ?></code></p>
						<p class="help-block"><?= l7_t("O id nao pode ser alterado pela GUI."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Nome"); ?></label>
					<div class="col-sm-9">
						<input type="text" name="edit_name" class="form-control" maxlength="160" value="<?= htmlspecialchars($edit_name); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="edit_priority" class="form-control" value="<?= (int)$edit_priority; ?>" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="edit_action" class="form-control">
							<option value="monitor" <?= $edit_action === "monitor" ? 'selected="selected"' : ''; ?>><?= l7_t("monitor"); ?></option>
							<option value="allow" <?= $edit_action === "allow" ? 'selected="selected"' : ''; ?>><?= l7_t("allow"); ?></option>
							<option value="block" <?= $edit_action === "block" ? 'selected="selected"' : ''; ?>><?= l7_t("block"); ?></option>
							<option value="tag" <?= $edit_action === "tag" ? 'selected="selected"' : ''; ?>><?= l7_t("tag"); ?></option>
						</select>
					</div>
				</div>

				<?php
				$edit_policy_ifaces = array();
				if (isset($edit_policy["interfaces"]) && is_array($edit_policy["interfaces"])) {
					$edit_policy_ifaces = $edit_policy["interfaces"];
				}
				$edit_src_hosts_val = "";
				if (isset($edit_policy["match"]["src_hosts"]) && is_array($edit_policy["match"]["src_hosts"])) {
					$edit_src_hosts_val = implode("\n", $edit_policy["match"]["src_hosts"]);
				}
				$edit_src_cidrs_val = "";
				if (isset($edit_policy["match"]["src_cidrs"]) && is_array($edit_policy["match"]["src_cidrs"])) {
					$edit_src_cidrs_val = implode("\n", $edit_policy["match"]["src_cidrs"]);
				}
				$ep_ifaces = layer7_get_pfsense_interfaces();
				?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_ifaces_list', true);"><?= l7_t("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_ifaces_list', false);"><?= l7_t("Limpar"); ?></button>
						</div>
						<div id="edit_ifaces_list">
						<?php foreach ($ep_ifaces as $ifc) {
							$chk = (in_array($ifc["real"], $edit_policy_ifaces, true) ||
							    in_array($ifc["ifid"], $edit_policy_ifaces, true))
							    ? 'checked="checked"' : '';
						?>
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" <?= $chk; ?> />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Nenhuma = aplica a todas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("IPs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_src_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_src_hosts_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16). Vazio = qualquer IP."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_src_cidrs" class="form-control" rows="2" style="max-width:400px"><?= htmlspecialchars($edit_src_cidrs_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."); ?></p>
					</div>
				</div>

				<?php if (!empty($l7_groups)) {
					$edit_grps_arr = array();
					if (isset($edit_policy["match"]["groups"]) && is_array($edit_policy["match"]["groups"])) {
						$edit_grps_arr = $edit_policy["match"]["groups"];
					}
				?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Grupos"); ?></label>
					<div class="col-sm-9">
						<div class="l7-multiselect-wrap" id="edit_groups_list" style="max-width:400px;max-height:160px;">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
							$gchk = in_array($grp["id"] ?? "", $edit_grps_arr, true) ? 'checked="checked"' : '';
						?>
							<label><input type="checkbox" name="edit_groups[]" value="<?= $gid; ?>" <?= $gchk; ?> /> <?= $gname; ?> <span class="text-muted">(<?= $gid; ?>)</span></label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem."); ?></p>
					</div>
				</div>
				<?php } ?>

				<?php
				$edit_exc_cidrs_val = "";
				if (isset($edit_policy["match"]["src_exclude_cidrs"]) &&
				    is_array($edit_policy["match"]["src_exclude_cidrs"])) {
					$edit_exc_cidrs_val = implode("\n",
					    $edit_policy["match"]["src_exclude_cidrs"]);
				}
				$edit_exc_grps_arr = array();
				if (isset($edit_policy["match"]["src_exclude_groups"]) &&
				    is_array($edit_policy["match"]["src_exclude_groups"])) {
					$edit_exc_grps_arr = $edit_policy["match"]["src_exclude_groups"];
				}
				?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Excluir origens (so este perfil)"); ?></label>
					<div class="col-sm-9">
						<p class="help-block"><?= l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis."); ?></p>
					<?php if (!empty($l7_groups)) { ?>
						<div class="l7-multiselect-wrap" id="edit_exc_groups_list" style="max-width:400px;max-height:120px;">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
							$gchk = in_array($grp["id"] ?? "", $edit_exc_grps_arr, true) ? 'checked="checked"' : '';
						?>
							<label><input type="checkbox" name="edit_src_exclude_groups[]" value="<?= $gid; ?>" <?= $gchk; ?> /> <?= $gname; ?></label>
						<?php } ?>
						</div>
					<?php } ?>
						<textarea name="edit_src_exclude_cidrs" class="form-control" rows="2" style="max-width:400px;margin-top:8px;" placeholder="192.168.1.50"><?= htmlspecialchars($edit_exc_cidrs_val); ?></textarea>
						<p class="help-block"><?= l7_t("CIDRs excluidos (um por linha)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_match_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_hosts_match_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um host por linha, ex.: youtube.com ou api.whatsapp.com. O match aceita o host exacto e subdominios. Maximo 64 hosts por politica."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Apps nDPI"); ?></label>
					<div class="col-sm-9">
						<?php
						$edit_apps_arr = array();
						if (isset($edit_policy["match"]["ndpi_app"]) && is_array($edit_policy["match"]["ndpi_app"])) {
							$edit_apps_arr = $edit_policy["match"]["ndpi_app"];
						}
						if (!empty($ndpi_protos)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= l7_t("Pesquisar apps..."); ?>" onkeyup="l7filter(this,'edit_apps_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_apps_list', true, true);"><?= l7_t("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_apps_list', false, false);"><?= l7_t("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="edit_apps_list" style="max-width:400px">
						<?php foreach ($ndpi_protos as $proto) {
							$chk = in_array($proto, $edit_apps_arr, true) ? 'checked="checked"' : '';
						?>
							<label><input type="checkbox" name="edit_ndpi_apps[]" value="<?= htmlspecialchars($proto); ?>" <?= $chk; ?> /> <?= htmlspecialchars($proto); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="edit_ndpi_apps_csv" class="form-control" value="<?= htmlspecialchars($edit_apps); ?>" />
						<?php } ?>
						<p class="help-block"><?= l7_t("Selecione ate 12 aplicacoes."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Categorias nDPI"); ?></label>
					<div class="col-sm-9">
						<?php
						$edit_cats_arr = array();
						if (isset($edit_policy["match"]["ndpi_category"]) && is_array($edit_policy["match"]["ndpi_category"])) {
							$edit_cats_arr = $edit_policy["match"]["ndpi_category"];
						}
						if (!empty($ndpi_cats)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= l7_t("Pesquisar categorias..."); ?>" onkeyup="l7filter(this,'edit_cats_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_cats_list', true, true);"><?= l7_t("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_cats_list', false, false);"><?= l7_t("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="edit_cats_list" style="max-width:400px">
						<?php foreach ($ndpi_cats as $cat) {
							$chk = in_array($cat, $edit_cats_arr, true) ? 'checked="checked"' : '';
						?>
							<label><input type="checkbox" name="edit_ndpi_category[]" value="<?= htmlspecialchars($cat); ?>" <?= $chk; ?> /> <?= htmlspecialchars($cat); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="edit_ndpi_category_csv" class="form-control" value="<?= htmlspecialchars($edit_categories); ?>" />
						<?php } ?>
						<p class="help-block"><?= l7_t("Selecione ate 8 categorias."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>tag_table</code></label>
					<div class="col-sm-6">
						<input type="text" name="edit_tag_table" class="form-control" maxlength="63"
							pattern="[A-Za-z0-9_]+" value="<?= htmlspecialchars($edit_tag_table !== "" ? $edit_tag_table : "layer7_tagged"); ?>" />
						<p class="help-block"><?= l7_t("Obrigatorio quando a acao for tag."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Horario"); ?></label>
					<div class="col-sm-9">
						<?php $ed_days = array("mon" => "Seg", "tue" => "Ter", "wed" => "Qua", "thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom"); ?>
						<?php foreach ($ed_days as $dk => $dl) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_sched_<?= $dk; ?>" value="1" <?= in_array($dk, $edit_sched_days, true) ? 'checked="checked"' : ''; ?> />
							<?= $dl; ?>
						</label>
						<?php } ?>
						<div style="margin-top:8px;">
							<label class="control-label" style="display:inline;"><?= l7_t("De"); ?></label>
							<input type="time" name="edit_sched_start" value="<?= htmlspecialchars($edit_sched_start); ?>" class="form-control" style="width:120px;display:inline-block;" />
							<label class="control-label" style="display:inline;margin-left:10px;"><?= l7_t("ate"); ?></label>
							<input type="time" name="edit_sched_end" value="<?= htmlspecialchars($edit_sched_end); ?>" class="form-control" style="width:120px;display:inline-block;" />
						</div>
						<p class="help-block"><?= l7_t("Vazio = sempre activa. Preencha dias + horas para restringir."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_enabled" value="1" <?= $edit_enabled ? 'checked="checked"' : ''; ?> />
							<?= l7_t("Regra habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Escopo global"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_scope_global" value="1" <?= !empty($edit_policy["scope_global"]) ? 'checked="checked"' : ''; ?> />
							<?= l7_t("Aplicar a toda a rede (sem origem definida)"); ?>
						</label>
						<p class="help-block"><?= l7_t("So relevante com enforcement escopado (scoped_hybrid). Sem IPs/CIDRs/grupos de origem, a politica block so gera regra PF global se esta opcao estiver activa."); ?></p>
						<p class="help-block text-warning"><strong><?= l7_t("Atencao:"); ?></strong> <?= l7_t("Com match vazio (sem hosts/apps/categorias) e esta opcao activa, qualquer IP adicionado a tabela PF escopada bloqueia saida externa de forma global — efeito amplo em toda a rede. Use apenas com criterios explicitos ou origens definidas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Quarentena origem"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_quarantine_origin" value="1" <?= !empty($edit_policy["quarantine_origin"]) ? 'checked="checked"' : ''; ?> />
							<?= l7_t("Bloquear toda a saida externa da origem (app-only sem destino)"); ?>
						</label>
						<p class="help-block"><?= l7_t("So relevante com enforcement escopado. Politicas block por app/categoria sem host exigem esta opcao para quarentenar a origem; caso contrario o bloqueio e ignorado com aviso no log."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="save_policy_edit" value="1" class="btn btn-primary"><?= l7_t("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
			</div>
		</div>
		<?php } ?>

		<div class="layer7-admin-block" id="l7-add">
			<div class="layer7-admin-block__header"><?= l7_t("Adicionar politica"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Use nomes claros e prioridades previsiveis para manter a leitura do conjunto simples durante o troubleshooting."); ?></p>
			<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 24 politicas atingido."); ?></div>
			<?php } else { ?>
			<form method="post" action="layer7_policies.php#l7-add" class="form-horizontal">

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_id" class="form-control" maxlength="80"
							pattern="[a-zA-Z0-9_-]+" required="required" placeholder="p-exemplo-001" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Nome"); ?></label>
					<div class="col-sm-9">
						<input type="text" name="new_name" class="form-control" maxlength="160" placeholder="<?= l7_t("Ex.: Monitor geral"); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="new_priority" class="form-control" value="50" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="new_action" class="form-control">
							<option value="monitor"><?= l7_t("monitor"); ?></option>
							<option value="allow"><?= l7_t("allow"); ?></option>
							<option value="block"><?= l7_t("block"); ?></option>
							<option value="tag"><?= l7_t("tag"); ?></option>
						</select>
					</div>
				</div>

				<?php $pf_ifaces = layer7_get_pfsense_interfaces(); ?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_ifaces_list', true);"><?= l7_t("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_ifaces_list', false);"><?= l7_t("Limpar"); ?></button>
						</div>
						<div id="new_ifaces_list">
						<?php foreach ($pf_ifaces as $ifc) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="new_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Nenhuma selecionada = aplica a todas as interfaces."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("IPs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_src_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="192.168.1.50&#10;192.168.1.51"></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16). Vazio = qualquer IP."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_src_cidrs" class="form-control" rows="2" style="max-width:400px" placeholder="192.168.10.0/24"></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."); ?></p>
					</div>
				</div>

				<?php if (!empty($l7_groups)) { ?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Grupos"); ?></label>
					<div class="col-sm-9">
						<div class="l7-multiselect-wrap" id="new_groups_list" style="max-width:400px;max-height:160px;">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label><input type="checkbox" name="new_groups[]" value="<?= $gid; ?>" /> <?= $gname; ?> <span class="text-muted">(<?= $gid; ?>)</span></label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem. Alternativa a digitar CIDRs manualmente."); ?></p>
					</div>
				</div>
				<?php } ?>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Excluir origens (so este perfil)"); ?></label>
					<div class="col-sm-9">
						<p class="help-block"><?= l7_t("IPs/CIDRs/grupos isentos desta politica; continuam sujeitos aos restantes perfis."); ?></p>
					<?php if (!empty($l7_groups)) { ?>
						<div class="l7-multiselect-wrap" id="new_exc_groups_list" style="max-width:400px;max-height:120px;">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label><input type="checkbox" name="new_src_exclude_groups[]" value="<?= $gid; ?>" /> <?= $gname; ?></label>
						<?php } ?>
						</div>
					<?php } ?>
						<textarea name="new_src_exclude_cidrs" class="form-control" rows="2" style="max-width:400px;margin-top:8px;" placeholder="192.168.1.50"></textarea>
						<p class="help-block"><?= l7_t("CIDRs excluidos (um por linha)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_match_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="youtube.com&#10;api.whatsapp.com"></textarea>
						<p class="help-block"><?= l7_t("Um host por linha, ex.: youtube.com. Para block, basta indicar sites aqui (sem necessidade de app nDPI). O bloqueio DNS atua automaticamente. Maximo 64 hosts por politica."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Apps nDPI"); ?></label>
					<div class="col-sm-9">
						<?php if (!empty($ndpi_protos)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= l7_t("Pesquisar apps..."); ?>" onkeyup="l7filter(this,'new_apps_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_apps_list', true, true);"><?= l7_t("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_apps_list', false, false);"><?= l7_t("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="new_apps_list" style="max-width:400px">
						<?php foreach ($ndpi_protos as $proto) { ?>
							<label><input type="checkbox" name="new_ndpi_apps[]" value="<?= htmlspecialchars($proto); ?>" /> <?= htmlspecialchars($proto); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="new_ndpi_apps_csv" class="form-control" placeholder="HTTP, BitTorrent" />
						<?php } ?>
						<p class="help-block"><?= l7_t("Selecione ate 12 aplicacoes. Em branco = qualquer app."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Categorias nDPI"); ?></label>
					<div class="col-sm-9">
						<?php if (!empty($ndpi_cats)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= l7_t("Pesquisar categorias..."); ?>" onkeyup="l7filter(this,'new_cats_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_cats_list', true, true);"><?= l7_t("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_cats_list', false, false);"><?= l7_t("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="new_cats_list" style="max-width:400px">
						<?php foreach ($ndpi_cats as $cat) { ?>
							<label><input type="checkbox" name="new_ndpi_category[]" value="<?= htmlspecialchars($cat); ?>" /> <?= htmlspecialchars($cat); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="new_ndpi_category_csv" class="form-control" placeholder="Web" />
						<?php } ?>
						<p class="help-block"><?= l7_t("Selecione ate 8 categorias."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>tag_table</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_tag_table" class="form-control" maxlength="63"
							pattern="[A-Za-z0-9_]+" placeholder="layer7_tagged" />
						<p class="help-block"><?= l7_t("Obrigatorio quando a acao for tag."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Horario"); ?></label>
					<div class="col-sm-9">
						<?php $new_days = array("mon" => "Seg", "tue" => "Ter", "wed" => "Qua", "thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom"); ?>
						<?php foreach ($new_days as $dk => $dl) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="new_sched_<?= $dk; ?>" value="1" />
							<?= $dl; ?>
						</label>
						<?php } ?>
						<div style="margin-top:8px;">
							<label class="control-label" style="display:inline;"><?= l7_t("De"); ?></label>
							<input type="time" name="new_sched_start" value="" class="form-control" style="width:120px;display:inline-block;" />
							<label class="control-label" style="display:inline;margin-left:10px;"><?= l7_t("ate"); ?></label>
							<input type="time" name="new_sched_end" value="" class="form-control" style="width:120px;display:inline-block;" />
						</div>
						<p class="help-block"><?= l7_t("Vazio = sempre activa. Preencha dias + horas para restringir."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_enabled" value="1" checked="checked" />
							<?= l7_t("Criar politica ja habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Escopo global"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_scope_global" value="1" />
							<?= l7_t("Aplicar a toda a rede (sem origem definida)"); ?>
						</label>
						<p class="help-block"><?= l7_t("So relevante com enforcement escopado (scoped_hybrid). Sem IPs/CIDRs/grupos de origem, a politica block so gera regra PF global se esta opcao estiver activa."); ?></p>
						<p class="help-block text-warning"><strong><?= l7_t("Atencao:"); ?></strong> <?= l7_t("Com match vazio (sem hosts/apps/categorias) e esta opcao activa, qualquer IP adicionado a tabela PF escopada bloqueia saida externa de forma global — efeito amplo em toda a rede. Use apenas com criterios explicitos ou origens definidas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Quarentena origem"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_quarantine_origin" value="1" />
							<?= l7_t("Bloquear toda a saida externa da origem (app-only sem destino)"); ?>
						</label>
						<p class="help-block"><?= l7_t("So relevante com enforcement escopado. Politicas block por app/categoria sem host exigem esta opcao para quarentenar a origem; caso contrario o bloqueio e ignorado com aviso no log."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="add_policy" value="1" class="btn btn-success"><?= l7_t("Adicionar politica"); ?></button>
					</div>
				</div>
			</form>
			<?php } ?>

			<p class="layer7-muted-note small"><?= l7_t("Para alterar o id de uma politica existente, edite /usr/local/etc/layer7.json diretamente."); ?></p>
			</div>
		</div>
		</div>
	</div>
</div>
<style>
.l7-profile-group { margin-bottom: 26px; }
.l7-profile-group:last-child { margin-bottom: 8px; }
.l7-profile-group-header { display: flex; align-items: baseline; justify-content: space-between; border-bottom: 1px solid #ddd; padding-bottom: 6px; margin-bottom: 14px; }
.l7-profile-group-title { font-weight: 600; font-size: 16px; }
.l7-profile-group-count { font-size: 11px; color: #999; white-space: nowrap; margin-left: 12px; }
.l7-profiles-grid { display: flex; flex-wrap: wrap; gap: 14px; align-items: stretch; }
.l7-profile-card { position: relative; display: flex; flex-direction: column; border: 1px solid #ddd; border-radius: 6px; padding: 16px; width: 180px; min-height: 232px; text-align: center; background: #fdfdfd; transition: box-shadow 0.15s; }
.l7-profile-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
.l7-profile-card.l7-profile-on { border-color: #4cae4c; box-shadow: 0 0 0 1px #4cae4c inset; }
.l7-profile-state { position: absolute; top: 8px; right: 8px; }
.l7-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.l7-dot-on { background: #5cb85c; box-shadow: 0 0 4px #5cb85c; }
.l7-dot-off { background: #ccc; }
.l7-profile-hits { color: #5cb85c; font-weight: 600; }
.l7-profile-cta { margin-top: auto; padding-top: 8px; }
.l7-profile-actions { display: flex; gap: 6px; justify-content: center; }
.l7-profile-icon-ios { width: 56px; height: 56px; border-radius: 13px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.18); flex-shrink: 0; }
.l7-profile-icon-ios .fa { font-size: 26px; line-height: 1; }
.l7-profile-name { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.l7-profile-desc { font-size: 12px; color: #666; margin-bottom: 6px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; max-height: 51px; }
.l7-profile-meta { font-size: 11px; color: #999; margin-bottom: 0; }
.l7-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.45); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.l7-modal-box { background: #fff; border-radius: 6px; padding: 24px 28px; min-width: 420px; max-width: 560px; box-shadow: 0 4px 24px rgba(0,0,0,0.2); }
.l7-modal-box h4 { margin: 0 0 18px; font-size: 18px; font-weight: 600; }
</style>
<script>
function l7showProfileModal(profileId, profileName) {
	document.getElementById('l7ProfileId').value = profileId;
	document.getElementById('l7ProfileModalTitle').textContent = profileName;
	document.getElementById('l7ProfileModal').style.display = '';
}
function l7hideProfileModal() {
	document.getElementById('l7ProfileModal').style.display = 'none';
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
</script>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
