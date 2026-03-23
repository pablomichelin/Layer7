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
			$input_errors[] = gettext("Perfil nao encontrado.");
		} else {
			$data = layer7_load_or_default();
			if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
				$data["layer7"]["policies"] = array();
			}
			$policies = &$data["layer7"]["policies"];

			if (count($policies) >= 24) {
				$input_errors[] = gettext("Limite de 24 politicas.");
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
					$input_errors[] = sprintf(gettext("Ja existe uma politica com id '%s'. Remova-a primeiro para recriar."), $pid);
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
							$real = convert_friendly_interface_to_real_interface_name($ifid);
							$prof_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
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

					$apps = isset($profile["ndpi_apps"]) && is_array($profile["ndpi_apps"]) ? $profile["ndpi_apps"] : array();
					$hosts = isset($profile["hosts"]) && is_array($profile["hosts"]) ? $profile["hosts"] : array();
					$cats = isset($profile["ndpi_categories"]) && is_array($profile["ndpi_categories"]) ? $profile["ndpi_categories"] : array();
					if (!empty($apps)) {
						$rule["match"]["ndpi_app"] = array_slice($apps, 0, 12);
					}
					if (!empty($cats)) {
						$rule["match"]["ndpi_category"] = array_slice($cats, 0, 8);
					}
					if (!empty($hosts)) {
						$rule["match"]["hosts"] = array_slice($hosts, 0, 64);
					}

					$policies[] = $rule;
					if (layer7_save_json($data)) {
						layer7_signal_reload();
						$savemsg = sprintf(gettext("Politica '%s' criada a partir do perfil '%s'."), $pid, $profile["name"] ?? $profile_id);
					}
				}
			}
			unset($policies);
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
			$input_errors[] = gettext("Limite de 24 politicas.");
			$ok = false;
		}

		$pid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($pid)) {
			$input_errors[] = gettext("ID invalido (letras, numeros, _ e -; max. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($policies as $existing_policy) {
				if (isset($existing_policy["id"]) && (string)$existing_policy["id"] === $pid) {
					$input_errors[] = gettext("Ja existe uma politica com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$name = trim($_POST["new_name"] ?? "");
		if ($ok && strlen($name) > 160) {
			$input_errors[] = gettext("Nome demasiado longo (max. 160).");
			$ok = false;
		}

		$pri = (int)($_POST["new_priority"] ?? 50);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = gettext("Prioridade invalida (0-99999).");
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
			$input_errors[] = gettext("App ou categoria: cada valor max. 64 caracteres.");
			$ok = false;
		}
		$new_match_hosts_pre = layer7_parse_host_textarea($_POST["new_match_hosts"] ?? "");
		if ($ok && $apps !== null && $cats !== null &&
		    ($act === "block" || $act === "tag") &&
		    count($apps) + count($cats) === 0 &&
		    empty($new_match_hosts_pre)) {
			$input_errors[] = gettext("Para block ou tag, indique app nDPI, categoria e/ou sites/hosts.");
			$ok = false;
		}

		$tag_table = trim($_POST["new_tag_table"] ?? "");
		if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
			$input_errors[] = gettext("Tabela PF (tag): apenas A-Z, a-z, 0-9, _ (1-63 caracteres).");
			$ok = false;
		}

		$new_rule_ifaces = array();
		if (isset($_POST["new_ifaces"]) && is_array($_POST["new_ifaces"])) {
			foreach ($_POST["new_ifaces"] as $ifid) {
				$real = convert_friendly_interface_to_real_interface_name($ifid);
				$new_rule_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
			}
		}
		$new_src_hosts = layer7_parse_ip_textarea($_POST["new_src_hosts"] ?? "");
		$new_src_cidrs = layer7_parse_cidr_textarea($_POST["new_src_cidrs"] ?? "");
		$new_match_hosts = layer7_parse_host_textarea($_POST["new_match_hosts"] ?? "");

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
			if ($act === "tag") {
				$rule["tag_table"] = $tag_table;
			}
			$sched = layer7_parse_schedule_post("new");
			if ($sched !== null) {
				$rule["schedule"] = $sched;
			}
			$policies[] = $rule;
			if (layer7_save_json($data)) {
				layer7_signal_reload();
				$savemsg = gettext("Politica adicionada.");
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
			layer7_signal_reload();
			$savemsg = gettext("Politicas atualizadas.");
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
			$input_errors[] = gettext("Indice de politica invalido.");
		} else {
			array_splice($policies, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_signal_reload();
				$savemsg = gettext("Politica removida.");
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
			$input_errors[] = gettext("Indice de politica invalido.");
		} else {
			$layer7_policy_edit_retry = $idx;
			$orig = $policies[$idx];
			$pid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$name = trim($_POST["edit_name"] ?? "");
			if ($ok && strlen($name) > 160) {
				$input_errors[] = gettext("Nome demasiado longo (max. 160).");
				$ok = false;
			}

			$pri = (int)($_POST["edit_priority"] ?? 50);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = gettext("Prioridade invalida (0-99999).");
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
				$input_errors[] = gettext("App ou categoria: cada valor max. 64 caracteres.");
				$ok = false;
			}
			$edit_match_hosts_pre = layer7_parse_host_textarea($_POST["edit_match_hosts"] ?? "");
			if ($ok && $apps !== null && $cats !== null &&
			    ($act === "block" || $act === "tag") &&
			    count($apps) + count($cats) === 0 &&
			    empty($edit_match_hosts_pre)) {
				$input_errors[] = gettext("Para block ou tag, indique app nDPI, categoria e/ou sites/hosts.");
				$ok = false;
			}

			$tag_table = trim($_POST["edit_tag_table"] ?? "");
			if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
				$input_errors[] = gettext("Tabela PF (tag): apenas A-Z, a-z, 0-9, _ (1-63 caracteres).");
				$ok = false;
			}

			$edit_rule_ifaces = array();
			if (isset($_POST["edit_ifaces"]) && is_array($_POST["edit_ifaces"])) {
				foreach ($_POST["edit_ifaces"] as $ifid) {
					$real = convert_friendly_interface_to_real_interface_name($ifid);
					$edit_rule_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
				}
			}
			$edit_src_hosts = layer7_parse_ip_textarea($_POST["edit_src_hosts"] ?? "");
			$edit_src_cidrs = layer7_parse_cidr_textarea($_POST["edit_src_cidrs"] ?? "");
			$edit_match_hosts = layer7_parse_host_textarea($_POST["edit_match_hosts"] ?? "");

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
				if ($act === "tag") {
					$rule["tag_table"] = $tag_table;
				}
				$edit_sched = layer7_parse_schedule_post("edit");
				if ($edit_sched !== null) {
					$rule["schedule"] = $edit_sched;
				}
				$policies[$idx] = $rule;
				if (layer7_save_json($data)) {
					layer7_signal_reload();
					header("Location: layer7_policies.php");
					exit;
				}
				$input_errors[] = gettext("Nao foi possivel gravar a configuracao.");
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

$ndpi_list = layer7_ndpi_list();
$ndpi_protos = isset($ndpi_list["protocols"]) ? $ndpi_list["protocols"] : array();
$ndpi_cats = isset($ndpi_list["categories"]) ? $ndpi_list["categories"] : array();
sort($ndpi_protos);
sort($ndpi_cats);

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Policies"));
include("head.inc");
layer7_render_styles();

function layer7_policy_match_summary($policy) {
	$matches = array();
	if (!empty($policy["interfaces"]) && is_array($policy["interfaces"])) {
		$matches[] = gettext("Ifaces") . ": " . implode(", ", $policy["interfaces"]);
	}
	if (!empty($policy["match"]["ndpi_app"]) && is_array($policy["match"]["ndpi_app"])) {
		$matches[] = gettext("Apps") . ": " . implode(", ", $policy["match"]["ndpi_app"]);
	}
	if (!empty($policy["match"]["ndpi_category"]) && is_array($policy["match"]["ndpi_category"])) {
		$matches[] = gettext("Categorias") . ": " . implode(", ", $policy["match"]["ndpi_category"]);
	}
	if (!empty($policy["match"]["hosts"]) && is_array($policy["match"]["hosts"])) {
		$matches[] = gettext("Sites") . ": " . implode(", ", $policy["match"]["hosts"]);
	}
	if (!empty($policy["match"]["src_hosts"]) && is_array($policy["match"]["src_hosts"])) {
		$matches[] = gettext("IPs") . ": " . implode(", ", $policy["match"]["src_hosts"]);
	}
	if (!empty($policy["match"]["src_cidrs"]) && is_array($policy["match"]["src_cidrs"])) {
		$matches[] = gettext("CIDRs") . ": " . implode(", ", $policy["match"]["src_cidrs"]);
	}
	if (!empty($policy["match"]["groups"]) && is_array($policy["match"]["groups"])) {
		$matches[] = gettext("Grupos") . ": " . implode(", ", $policy["match"]["groups"]);
	}
	if (!empty($policy["tag_table"]) && (($policy["action"] ?? "") === "tag")) {
		$matches[] = gettext("Tabela PF") . ": " . $policy["tag_table"];
	}
	$sched_label = layer7_schedule_summary($policy);
	if ($sched_label !== gettext("Sempre activa")) {
		$matches[] = gettext("Horario") . ": " . $sched_label;
	}
	return count($matches) > 0 ? $matches : array(gettext("Sem filtros especificos."));
}
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - politicas"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("policies"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= gettext("Organize a ordem de avaliacao, ajuste o estado de cada regra e mantenha a base de politicas pronta para o modo de enforcement."); ?></p>

		<?php
		$l7_profiles = layer7_load_profiles();
		if (!empty($l7_profiles) && !$at_limit) {
		$prof_ifaces = layer7_get_pfsense_interfaces();
		?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Perfis rapidos"); ?></h3>
			<p class="layer7-lead"><?= gettext("Clique num perfil para criar automaticamente uma politica com todas as apps e dominios associados. Escolha a accao, interfaces e sub-redes antes de aplicar."); ?></p>

			<div class="l7-profiles-grid">
			<?php foreach ($l7_profiles as $prof) {
				$prof_id = isset($prof["id"]) ? htmlspecialchars($prof["id"]) : "";
				$prof_name = isset($prof["name"]) ? htmlspecialchars($prof["name"]) : $prof_id;
				$prof_icon = isset($prof["icon"]) ? htmlspecialchars($prof["icon"]) : "fa-puzzle-piece";
				$prof_desc = isset($prof["description"]) ? htmlspecialchars($prof["description"]) : "";
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
			?>
				<div class="l7-profile-card<?= $prof_exists ? ' l7-profile-used' : ''; ?>">
					<div class="l7-profile-icon"><i class="fa <?= $prof_icon; ?>"></i></div>
					<div class="l7-profile-name"><?= $prof_name; ?></div>
					<div class="l7-profile-desc"><?= $prof_desc; ?></div>
					<div class="l7-profile-meta"><?= $prof_apps_count; ?> apps &middot; <?= $prof_hosts_count; ?> hosts</div>
					<?php if ($prof_exists) { ?>
					<span class="label label-info"><?= gettext("Ja aplicado"); ?></span>
					<?php } else { ?>
					<button type="button" class="btn btn-sm btn-success" onclick="l7showProfileModal('<?= $prof_id; ?>', '<?= $prof_name; ?>');"><?= gettext("Aplicar"); ?></button>
					<?php } ?>
				</div>
			<?php } ?>
			</div>
		</div>

		<div id="l7ProfileModal" class="l7-modal-overlay" style="display:none;">
			<div class="l7-modal-box">
				<h4 id="l7ProfileModalTitle"></h4>
				<form method="post" class="form-horizontal">
					<input type="hidden" name="profile_id" id="l7ProfileId" value="" />
					<input type="hidden" name="add_profile_policy" value="1" />

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= gettext("Accao"); ?></label>
						<div class="col-sm-8">
							<select name="profile_action" class="form-control">
								<option value="block" selected="selected"><?= gettext("block"); ?></option>
								<option value="monitor"><?= gettext("monitor"); ?></option>
								<option value="allow"><?= gettext("allow"); ?></option>
							</select>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= gettext("Interfaces"); ?></label>
						<div class="col-sm-8">
						<?php foreach ($prof_ifaces as $ifc) { ?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
								<?= htmlspecialchars($ifc["descr"]); ?>
							</label>
						<?php } ?>
							<p class="help-block"><?= gettext("Nenhuma = todas."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= gettext("CIDRs de origem"); ?></label>
						<div class="col-sm-8">
							<textarea name="profile_src_cidrs" class="form-control" rows="2" placeholder="192.168.10.0/24"></textarea>
							<p class="help-block"><?= gettext("Vazio = qualquer sub-rede."); ?></p>
						</div>
					</div>

					<?php if (!empty($l7_groups)) { ?>
					<div class="form-group">
						<label class="col-sm-4 control-label"><?= gettext("Grupos"); ?></label>
						<div class="col-sm-8">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_groups[]" value="<?= $gid; ?>" />
								<?= $gname; ?>
							</label>
						<?php } ?>
							<p class="help-block"><?= gettext("Alternativa a CIDRs manuais."); ?></p>
						</div>
					</div>
					<?php } ?>

					<div class="form-group">
						<div class="col-sm-offset-4 col-sm-8">
							<button type="submit" class="btn btn-success"><?= gettext("Criar politica"); ?></button>
							<button type="button" class="btn btn-default" onclick="l7hideProfileModal();"><?= gettext("Cancelar"); ?></button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Politicas atuais"); ?></h3>
			<?php if (count($policies) === 0) { ?>
			<div class="alert alert-info"><?= gettext("Nenhuma politica cadastrada. Adicione a primeira regra abaixo ou importe um layer7.json existente."); ?></div>
			<?php } else { ?>
			<form method="post">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= gettext("Ativa"); ?></th>
								<th><?= gettext("Prioridade"); ?></th>
								<th><?= gettext("Nome"); ?></th>
								<th><?= gettext("Acao"); ?></th>
								<th><?= gettext("Correspondencia"); ?></th>
								<th><code>id</code></th>
								<th><?= gettext("Acoes"); ?></th>
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
									<a href="layer7_policies.php?view=<?= (int)$i; ?>" class="btn btn-xs btn-default"><?= gettext("Ver listas"); ?></a>
									<a href="layer7_policies.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= gettext("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="layer7-toolbar">
					<button type="submit" name="save_policies" value="1" class="btn btn-primary"><?= gettext("Guardar estado das politicas"); ?></button>
				</div>
			</form>

			<form method="post" class="form-inline layer7-inline-form"
				onsubmit='return confirm(<?= json_encode(gettext("Remover esta politica do JSON?")); ?>);'>
				<div class="form-group">
					<label class="control-label" for="delete_policy_index"><?= gettext("Remover politica"); ?></label>
					<select id="delete_policy_index" name="delete_policy_index" class="form-control">
						<?php foreach ($policies as $i => $policy) {
							$pid = isset($policy["id"]) ? (string)$policy["id"] : ("#" . $i);
							$pname = isset($policy["name"]) ? (string)$policy["name"] : "";
							$label = $pid . ($pname !== "" ? " - " . $pname : "");
						?>
						<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
						<?php } ?>
					</select>
					<button type="submit" name="delete_policy" value="1" class="btn btn-danger"><?= gettext("Remover"); ?></button>
				</div>
			</form>
			<?php } ?>
		</div>

		<?php if ($view_policy !== null && $view_idx !== null) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Listas da politica"); ?></h3>
			<p class="layer7-lead"><?= gettext("Visualizacao rapida da regra, com todos os itens incluidos no match."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_policies.php" class="btn btn-default"><?= gettext("Fechar"); ?></a>
				<a href="layer7_policies.php?edit=<?= (int)$view_idx; ?>" class="btn btn-info"><?= gettext("Editar esta politica"); ?></a>
			</div>
			<dl class="dl-horizontal layer7-detail-grid">
				<dt><code>id</code></dt>
				<dd><code><?= htmlspecialchars((string)($view_policy["id"] ?? "")); ?></code></dd>
				<dt><?= gettext("Nome"); ?></dt>
				<dd><?= htmlspecialchars((string)($view_policy["name"] ?? "")); ?></dd>
				<dt><?= gettext("Acao"); ?></dt>
				<dd><span class="label label-default"><?= htmlspecialchars((string)($view_policy["action"] ?? "monitor")); ?></span></dd>
				<dt><?= gettext("Interfaces"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["interfaces"]) ? implode("\n", $view_policy["interfaces"]) : gettext("Todas")); ?></pre></dd>
				<dt><?= gettext("Apps nDPI"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ndpi_app"]) ? implode("\n", $view_policy["match"]["ndpi_app"]) : gettext("Qualquer app")); ?></pre></dd>
				<dt><?= gettext("Categorias nDPI"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["ndpi_category"]) ? implode("\n", $view_policy["match"]["ndpi_category"]) : gettext("Qualquer categoria")); ?></pre></dd>
				<dt><?= gettext("Sites/hosts"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["hosts"]) ? implode("\n", $view_policy["match"]["hosts"]) : gettext("Qualquer host")); ?></pre></dd>
				<dt><?= gettext("IPs de origem"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["src_hosts"]) ? implode("\n", $view_policy["match"]["src_hosts"]) : gettext("Qualquer IP")); ?></pre></dd>
				<dt><?= gettext("CIDRs de origem"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["src_cidrs"]) ? implode("\n", $view_policy["match"]["src_cidrs"]) : gettext("Qualquer sub-rede")); ?></pre></dd>
				<dt><?= gettext("Grupos"); ?></dt>
				<dd><pre class="pre-scrollable"><?= htmlspecialchars(!empty($view_policy["match"]["groups"]) ? implode("\n", $view_policy["match"]["groups"]) : gettext("Nenhum grupo")); ?></pre></dd>
				<dt><?= gettext("Horario"); ?></dt>
				<dd><?= htmlspecialchars(layer7_schedule_summary($view_policy)); ?></dd>
			</dl>
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
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Editar politica"); ?></h3>
			<p class="layer7-lead"><?= gettext("Atualize os detalhes da regra selecionada. O identificador permanece fixo para manter a referencia no JSON."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_policies.php" class="btn btn-default"><?= gettext("Cancelar edicao"); ?></a>
			</div>
			<form method="post" class="form-horizontal">
				<input type="hidden" name="edit_policy_index" value="<?= (int)$edit_idx; ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-9">
						<p class="form-control-static"><code><?= htmlspecialchars($edit_id !== "" ? $edit_id : "(vazio)"); ?></code></p>
						<p class="help-block"><?= gettext("O id nao pode ser alterado pela GUI."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Nome"); ?></label>
					<div class="col-sm-9">
						<input type="text" name="edit_name" class="form-control" maxlength="160" value="<?= htmlspecialchars($edit_name); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="edit_priority" class="form-control" value="<?= (int)$edit_priority; ?>" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="edit_action" class="form-control">
							<option value="monitor" <?= $edit_action === "monitor" ? 'selected="selected"' : ''; ?>><?= gettext("monitor"); ?></option>
							<option value="allow" <?= $edit_action === "allow" ? 'selected="selected"' : ''; ?>><?= gettext("allow"); ?></option>
							<option value="block" <?= $edit_action === "block" ? 'selected="selected"' : ''; ?>><?= gettext("block"); ?></option>
							<option value="tag" <?= $edit_action === "tag" ? 'selected="selected"' : ''; ?>><?= gettext("tag"); ?></option>
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
					<label class="col-sm-3 control-label"><?= gettext("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_ifaces_list', true);"><?= gettext("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_ifaces_list', false);"><?= gettext("Limpar"); ?></button>
						</div>
						<div id="edit_ifaces_list">
						<?php foreach ($ep_ifaces as $ifc) {
							$chk = in_array($ifc["real"], $edit_policy_ifaces, true) ? 'checked="checked"' : '';
						?>
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" <?= $chk; ?> />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= gettext("Nenhuma = aplica a todas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("IPs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_src_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_src_hosts_val); ?></textarea>
						<p class="help-block"><?= gettext("Um IPv4 por linha (max. 16). Vazio = qualquer IP."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("CIDRs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_src_cidrs" class="form-control" rows="2" style="max-width:400px"><?= htmlspecialchars($edit_src_cidrs_val); ?></textarea>
						<p class="help-block"><?= gettext("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."); ?></p>
					</div>
				</div>

				<?php if (!empty($l7_groups)) {
					$edit_grps_arr = array();
					if (isset($edit_policy["match"]["groups"]) && is_array($edit_policy["match"]["groups"])) {
						$edit_grps_arr = $edit_policy["match"]["groups"];
					}
				?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Grupos"); ?></label>
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
						<p class="help-block"><?= gettext("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem."); ?></p>
					</div>
				</div>
				<?php } ?>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_match_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_hosts_match_val); ?></textarea>
						<p class="help-block"><?= gettext("Um host por linha, ex.: youtube.com ou api.whatsapp.com. O match aceita o host exacto e subdominios."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Apps nDPI"); ?></label>
					<div class="col-sm-9">
						<?php
						$edit_apps_arr = array();
						if (isset($edit_policy["match"]["ndpi_app"]) && is_array($edit_policy["match"]["ndpi_app"])) {
							$edit_apps_arr = $edit_policy["match"]["ndpi_app"];
						}
						if (!empty($ndpi_protos)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= gettext("Pesquisar apps..."); ?>" onkeyup="l7filter(this,'edit_apps_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_apps_list', true, true);"><?= gettext("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_apps_list', false, false);"><?= gettext("Limpar tudo"); ?></button>
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
						<p class="help-block"><?= gettext("Selecione ate 12 aplicacoes."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Categorias nDPI"); ?></label>
					<div class="col-sm-9">
						<?php
						$edit_cats_arr = array();
						if (isset($edit_policy["match"]["ndpi_category"]) && is_array($edit_policy["match"]["ndpi_category"])) {
							$edit_cats_arr = $edit_policy["match"]["ndpi_category"];
						}
						if (!empty($ndpi_cats)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= gettext("Pesquisar categorias..."); ?>" onkeyup="l7filter(this,'edit_cats_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_cats_list', true, true);"><?= gettext("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_cats_list', false, false);"><?= gettext("Limpar tudo"); ?></button>
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
						<p class="help-block"><?= gettext("Selecione ate 8 categorias."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>tag_table</code></label>
					<div class="col-sm-6">
						<input type="text" name="edit_tag_table" class="form-control" maxlength="63"
							pattern="[A-Za-z0-9_]+" value="<?= htmlspecialchars($edit_tag_table !== "" ? $edit_tag_table : "layer7_tagged"); ?>" />
						<p class="help-block"><?= gettext("Obrigatorio quando a acao for tag."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Horario"); ?></label>
					<div class="col-sm-9">
						<?php $ed_days = array("mon" => "Seg", "tue" => "Ter", "wed" => "Qua", "thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom"); ?>
						<?php foreach ($ed_days as $dk => $dl) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_sched_<?= $dk; ?>" value="1" <?= in_array($dk, $edit_sched_days, true) ? 'checked="checked"' : ''; ?> />
							<?= $dl; ?>
						</label>
						<?php } ?>
						<div style="margin-top:8px;">
							<label class="control-label" style="display:inline;"><?= gettext("De"); ?></label>
							<input type="time" name="edit_sched_start" value="<?= htmlspecialchars($edit_sched_start); ?>" class="form-control" style="width:120px;display:inline-block;" />
							<label class="control-label" style="display:inline;margin-left:10px;"><?= gettext("ate"); ?></label>
							<input type="time" name="edit_sched_end" value="<?= htmlspecialchars($edit_sched_end); ?>" class="form-control" style="width:120px;display:inline-block;" />
						</div>
						<p class="help-block"><?= gettext("Vazio = sempre activa. Preencha dias + horas para restringir."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_enabled" value="1" <?= $edit_enabled ? 'checked="checked"' : ''; ?> />
							<?= gettext("Regra habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="save_policy_edit" value="1" class="btn btn-primary"><?= gettext("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Adicionar politica"); ?></h3>
			<p class="layer7-lead"><?= gettext("Use nomes claros e prioridades previsiveis para manter a leitura do conjunto simples durante o troubleshooting."); ?></p>
			<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= gettext("Limite de 24 politicas atingido."); ?></div>
			<?php } else { ?>
			<form method="post" class="form-horizontal">

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_id" class="form-control" maxlength="80"
							pattern="[a-zA-Z0-9_-]+" required="required" placeholder="p-exemplo-001" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Nome"); ?></label>
					<div class="col-sm-9">
						<input type="text" name="new_name" class="form-control" maxlength="160" placeholder="<?= gettext("Ex.: Monitor geral"); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="new_priority" class="form-control" value="50" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="new_action" class="form-control">
							<option value="monitor"><?= gettext("monitor"); ?></option>
							<option value="allow"><?= gettext("allow"); ?></option>
							<option value="block"><?= gettext("block"); ?></option>
							<option value="tag"><?= gettext("tag"); ?></option>
						</select>
					</div>
				</div>

				<?php $pf_ifaces = layer7_get_pfsense_interfaces(); ?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_ifaces_list', true);"><?= gettext("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_ifaces_list', false);"><?= gettext("Limpar"); ?></button>
						</div>
						<div id="new_ifaces_list">
						<?php foreach ($pf_ifaces as $ifc) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="new_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= gettext("Nenhuma selecionada = aplica a todas as interfaces."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("IPs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_src_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="192.168.1.50&#10;192.168.1.51"></textarea>
						<p class="help-block"><?= gettext("Um IPv4 por linha (max. 16). Vazio = qualquer IP."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("CIDRs de origem"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_src_cidrs" class="form-control" rows="2" style="max-width:400px" placeholder="192.168.10.0/24"></textarea>
						<p class="help-block"><?= gettext("Um CIDR por linha (max. 8). Vazio = qualquer sub-rede."); ?></p>
					</div>
				</div>

				<?php if (!empty($l7_groups)) { ?>
				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Grupos"); ?></label>
					<div class="col-sm-9">
						<div class="l7-multiselect-wrap" id="new_groups_list" style="max-width:400px;max-height:160px;">
						<?php foreach ($l7_groups as $grp) {
							$gid = isset($grp["id"]) ? htmlspecialchars($grp["id"]) : "";
							$gname = isset($grp["name"]) ? htmlspecialchars($grp["name"]) : $gid;
						?>
							<label><input type="checkbox" name="new_groups[]" value="<?= $gid; ?>" /> <?= $gname; ?> <span class="text-muted">(<?= $gid; ?>)</span></label>
						<?php } ?>
						</div>
						<p class="help-block"><?= gettext("Selecione grupos de dispositivos. Os CIDRs/IPs do grupo sao aplicados como origem. Alternativa a digitar CIDRs manualmente."); ?></p>
					</div>
				</div>
				<?php } ?>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_match_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="youtube.com&#10;api.whatsapp.com"></textarea>
						<p class="help-block"><?= gettext("Um host por linha, ex.: youtube.com. Para block, basta indicar sites aqui (sem necessidade de app nDPI). O bloqueio DNS atua automaticamente."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Apps nDPI"); ?></label>
					<div class="col-sm-9">
						<?php if (!empty($ndpi_protos)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= gettext("Pesquisar apps..."); ?>" onkeyup="l7filter(this,'new_apps_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_apps_list', true, true);"><?= gettext("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_apps_list', false, false);"><?= gettext("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="new_apps_list" style="max-width:400px">
						<?php foreach ($ndpi_protos as $proto) { ?>
							<label><input type="checkbox" name="new_ndpi_apps[]" value="<?= htmlspecialchars($proto); ?>" /> <?= htmlspecialchars($proto); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="new_ndpi_apps_csv" class="form-control" placeholder="HTTP, BitTorrent" />
						<?php } ?>
						<p class="help-block"><?= gettext("Selecione ate 12 aplicacoes. Em branco = qualquer app."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Categorias nDPI"); ?></label>
					<div class="col-sm-9">
						<?php if (!empty($ndpi_cats)) { ?>
						<input type="text" class="form-control l7-filter" placeholder="<?= gettext("Pesquisar categorias..."); ?>" onkeyup="l7filter(this,'new_cats_list')" style="max-width:400px" />
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_cats_list', true, true);"><?= gettext("Selecionar visiveis"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_cats_list', false, false);"><?= gettext("Limpar tudo"); ?></button>
						</div>
						<div class="l7-multiselect-wrap" id="new_cats_list" style="max-width:400px">
						<?php foreach ($ndpi_cats as $cat) { ?>
							<label><input type="checkbox" name="new_ndpi_category[]" value="<?= htmlspecialchars($cat); ?>" /> <?= htmlspecialchars($cat); ?></label>
						<?php } ?>
						</div>
						<?php } else { ?>
						<input type="text" name="new_ndpi_category_csv" class="form-control" placeholder="Web" />
						<?php } ?>
						<p class="help-block"><?= gettext("Selecione ate 8 categorias."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>tag_table</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_tag_table" class="form-control" maxlength="63"
							pattern="[A-Za-z0-9_]+" placeholder="layer7_tagged" />
						<p class="help-block"><?= gettext("Obrigatorio quando a acao for tag."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Horario"); ?></label>
					<div class="col-sm-9">
						<?php $new_days = array("mon" => "Seg", "tue" => "Ter", "wed" => "Qua", "thu" => "Qui", "fri" => "Sex", "sat" => "Sab", "sun" => "Dom"); ?>
						<?php foreach ($new_days as $dk => $dl) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="new_sched_<?= $dk; ?>" value="1" />
							<?= $dl; ?>
						</label>
						<?php } ?>
						<div style="margin-top:8px;">
							<label class="control-label" style="display:inline;"><?= gettext("De"); ?></label>
							<input type="time" name="new_sched_start" value="" class="form-control" style="width:120px;display:inline-block;" />
							<label class="control-label" style="display:inline;margin-left:10px;"><?= gettext("ate"); ?></label>
							<input type="time" name="new_sched_end" value="" class="form-control" style="width:120px;display:inline-block;" />
						</div>
						<p class="help-block"><?= gettext("Vazio = sempre activa. Preencha dias + horas para restringir."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_enabled" value="1" checked="checked" />
							<?= gettext("Criar politica ja habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="add_policy" value="1" class="btn btn-success"><?= gettext("Adicionar politica"); ?></button>
					</div>
				</div>
			</form>
			<?php } ?>

			<p class="layer7-muted-note small"><?= gettext("Para alterar o id de uma politica existente, edite /usr/local/etc/layer7.json diretamente."); ?></p>
		</div>
		</div>
	</div>
</div>
<style>
.l7-profiles-grid { display: flex; flex-wrap: wrap; gap: 14px; }
.l7-profile-card { border: 1px solid #ddd; border-radius: 6px; padding: 16px; width: 180px; text-align: center; background: #fdfdfd; transition: box-shadow 0.15s; }
.l7-profile-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
.l7-profile-card.l7-profile-used { opacity: 0.6; }
.l7-profile-icon { font-size: 28px; margin-bottom: 8px; color: #337ab7; }
.l7-profile-name { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
.l7-profile-desc { font-size: 12px; color: #666; margin-bottom: 6px; line-height: 1.4; min-height: 34px; }
.l7-profile-meta { font-size: 11px; color: #999; margin-bottom: 8px; }
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
<?php require_once("foot.inc"); ?>
