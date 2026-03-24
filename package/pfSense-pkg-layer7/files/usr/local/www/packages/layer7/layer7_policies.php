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
					$savemsg = sprintf(l7_t("Politica '%s' criada a partir do perfil '%s'."), $pid, $profile["name"] ?? $profile_id);

					if (isset($profile["extra_action"]) && $profile["extra_action"] === "configure_unbound_anti_doh") {
						$ub_conf = "/var/unbound/unbound.conf";
						$marker_start = "# --- Layer7 anti-DoH/Relay START ---";
						if (file_exists($ub_conf)) {
							$ub_content = @file_get_contents($ub_conf);
							if (strpos($ub_content, $marker_start) === false) {
								$marker_end = "# --- Layer7 anti-DoH/Relay END ---";
								$doh_domains = array(
									"mask.icloud.com", "mask-h2.icloud.com", "use-application-dns.net",
									"dns.google", "dns.google.com", "8888.google", "dns64.dns.google",
									"cloudflare-dns.com", "one.one.one.one", "1dot1dot1dot1.cloudflare-dns.com",
									"security.cloudflare-dns.com", "family.cloudflare-dns.com",
									"dns.quad9.net", "dns9.quad9.net", "dns10.quad9.net", "dns11.quad9.net",
									"dns.adguard.com", "dns-family.adguard.com", "dns-unfiltered.adguard.com",
									"doh.opendns.com", "doh.cleanbrowsing.org", "dns.nextdns.io",
									"doh.xfinity.com", "ordns.he.net"
								);
								$block = "\n{$marker_start}\n";
								$block .= "# Dominios de resolvers DoH/DoT e Apple Private Relay.\n";
								$block .= "# Devolver NXDOMAIN forca fallback para DNS convencional.\n";
								$block .= "# Gerado pela GUI Layer7.\n";
								foreach ($doh_domains as $d) {
									$block .= "server:\n    local-zone: \"{$d}.\" always_nxdomain\n";
								}
								$block .= "{$marker_end}\n";
								@copy($ub_conf, $ub_conf . ".layer7-bak." . date("YmdHis"));
								if (@file_put_contents($ub_conf, $ub_content . $block) !== false) {
									exec("/usr/local/sbin/pfSsh.php playback svc restart unbound 2>&1", $restart_out, $restart_code);
									$savemsg .= " " . l7_t("Unbound anti-DoH tambem configurado.");
								}
							}
						}
					}
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
		$new_match_hosts_pre = layer7_parse_host_textarea($_POST["new_match_hosts"] ?? "");
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
				$savemsg = l7_t("Politica adicionada.");
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
				layer7_signal_reload();
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
			$edit_match_hosts_pre = layer7_parse_host_textarea($_POST["edit_match_hosts"] ?? "");
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
				$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
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

			<p class="layer7-lead"><?= l7_t("Organize a ordem de avaliacao, ajuste o estado de cada regra e mantenha a base de politicas pronta para o modo de enforcement."); ?></p>

		<?php
		$l7_profiles = layer7_load_profiles();
		if (!empty($l7_profiles) && !$at_limit) {
		$prof_ifaces = layer7_get_pfsense_interfaces();
		?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Perfis rapidos"); ?></h3>
			<p class="layer7-lead"><?= l7_t("Clique num perfil para criar automaticamente uma politica com todas as apps e dominios associados. Escolha a accao, interfaces e sub-redes antes de aplicar."); ?></p>

		<?php
		$l7_app_icons = array(
			"youtube" => array("#FF0000", '<path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z" fill="#fff"/>'),
			"facebook" => array("#1877F2", '<path d="M24 12c0-6.627-5.373-12-12-12S0 5.373 0 12c0 5.99 4.388 10.954 10.125 11.854V15.47H7.078V12h3.047V9.356c0-3.007 1.792-4.668 4.533-4.668 1.312 0 2.686.234 2.686.234v2.953H15.83c-1.491 0-1.956.925-1.956 1.875V12h3.328l-.532 3.47h-2.796v8.385C19.612 22.954 24 17.99 24 12z" fill="#fff"/>'),
			"instagram" => array("#E4405F", '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12s.014 3.668.072 4.948c.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24s3.668-.014 4.948-.072c4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948s-.014-3.667-.072-4.947c-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" fill="#fff"/>'),
			"tiktok" => array("#010101", '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z" fill="#fff"/>'),
			"whatsapp" => array("#25D366", '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="#fff"/>'),
			"twitter" => array("#000000", '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" fill="#fff"/>'),
			"linkedin" => array("#0A66C2", '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" fill="#fff"/>'),
			"netflix" => array("#E50914", '<path d="M5.398 0v.006c3.028 8.556 5.37 15.175 8.348 23.596 2.344.058 4.85.398 4.854.398-2.8-7.924-5.923-16.747-8.487-24zm8.489 0v9.63L18.6 22.951c.043.043.105.065.18.096l.003-.003c0-7.681-.001-15.362 0-23.044H13.887zM5.398 1.05V24c1.873-.225 2.81-.312 4.715-.398v-9.22l-4.715-13.33z" fill="#fff"/>'),
			"spotify" => array("#1DB954", '<path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z" fill="#fff"/>'),
			"twitch" => array("#9146FF", '<path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" fill="#fff"/>'),
			"social" => array("#4267B2", '<path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="#fff"/>'),
			"streaming" => array("#FF6D00", '<circle cx="12" cy="12" r="10" fill="none" stroke="#fff" stroke-width="1.5"/><polygon points="10,8 16,12 10,16" fill="#fff"/>'),
			"gaming" => array("#7B2FBE", '<path d="M21.58 16.09l-1.09-7.66C20.21 6.46 18.52 5 16.53 5H7.47C5.48 5 3.79 6.46 3.51 8.43l-1.09 7.66C2.2 17.63 3.39 19 4.94 19c.68 0 1.32-.27 1.8-.75L9 16h6l2.25 2.25c.48.48 1.13.75 1.8.75 1.56 0 2.75-1.37 2.53-2.91zM11 11H9v2H8v-2H6v-1h2V8h1v2h2v1zm4 2c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm3-2c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z" fill="#fff"/>'),
			"vpn-proxy" => array("#2C3E50", '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z" fill="#fff"/>'),
			"ai-tools" => array("#10A37F", '<path d="M22.282 9.821a5.985 5.985 0 00-.516-4.91 6.046 6.046 0 00-6.51-2.9A6.065 6.065 0 0011.694.14 6.016 6.016 0 005.21 2.253 6.09 6.09 0 001.634 7.96a6.027 6.027 0 00.72 5.027 5.985 5.985 0 00.516 4.911 6.046 6.046 0 006.51 2.9A6.06 6.06 0 0012.95 23.856a6.016 6.016 0 006.484-2.112 6.09 6.09 0 003.577-5.707 6.027 6.027 0 00-.73-6.216zm-9.332 12.66a4.508 4.508 0 01-2.888-1.04l.144-.082 4.795-2.77a.78.78 0 00.393-.68v-6.76l2.027 1.17a.071.071 0 01.039.052v5.6a4.536 4.536 0 01-4.51 4.51zm-9.707-4.14a4.483 4.483 0 01-.538-3.024l.144.086 4.796 2.77a.78.78 0 00.786 0l5.857-3.382v2.34a.073.073 0 01-.029.062L9.354 19.7a4.536 4.536 0 01-6.111-1.359zM2.14 7.847a4.49 4.49 0 012.35-1.979V11.6a.78.78 0 00.394.68l5.856 3.383-2.027 1.17a.072.072 0 01-.067.005L3.739 14.07A4.536 4.536 0 012.14 7.847zm16.653 3.872l-5.857-3.382 2.027-1.17a.072.072 0 01.067-.005l4.907 2.833a4.534 4.534 0 01-.7 8.177V12.4a.78.78 0 00-.394-.68zm2.016-3.036l-.144-.086-4.796-2.77a.78.78 0 00-.786 0l-5.857 3.382V6.87a.073.073 0 01.029-.062l4.907-2.832a4.536 4.536 0 016.647 4.707zM8.61 12.89l-2.027-1.17a.071.071 0 01-.039-.052V6.07a4.535 4.535 0 017.399-3.517l-.144.082-4.795 2.77a.78.78 0 00-.393.68v6.76zm1.1-2.378l2.608-1.506 2.608 1.506v3.012l-2.608 1.506-2.608-1.506v-3.012z" fill="#fff"/>'),
		"remote-access" => array("#E74C3C", '<path d="M21 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h7v2H8v2h8v-2h-2v-2h7c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H3V4h18v12z" fill="#fff"/>'),
		"anti-bypass-dns" => array("#E67E22", '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v2h-2V7zm0 4h2v6h-2v-6z" fill="#fff"/>')
		);
		?>
		<div class="l7-profiles-grid">
		<?php foreach ($l7_profiles as $prof) {
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
			$icon_color = "#667";
			$icon_svg = '<text x="12" y="17" text-anchor="middle" fill="#fff" font-size="14" font-weight="bold">' . strtoupper(substr($prof["id"] ?? "?", 0, 1)) . '</text>';
			if (isset($l7_app_icons[$prof["id"]])) {
				$icon_color = $l7_app_icons[$prof["id"]][0];
				$icon_svg = $l7_app_icons[$prof["id"]][1];
			}
		?>
			<div class="l7-profile-card<?= $prof_exists ? ' l7-profile-used' : ''; ?>">
				<div class="l7-profile-icon-ios" style="background:<?= $icon_color; ?>;">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28"><?= $icon_svg; ?></svg>
				</div>
				<div class="l7-profile-name"><?= $prof_name; ?></div>
				<div class="l7-profile-desc"><?= $prof_desc; ?></div>
				<div class="l7-profile-meta"><?= $prof_apps_count; ?> apps &middot; <?= $prof_hosts_count; ?> hosts</div>
				<?php if ($prof_exists) { ?>
				<span class="label label-info"><?= l7_t("Ja aplicado"); ?></span>
				<?php } else { ?>
				<button type="button" class="btn btn-sm btn-success" onclick="l7showProfileModal('<?= $prof_id; ?>', '<?= $prof_name; ?>');"><?= l7_t("Aplicar"); ?></button>
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
						<label class="col-sm-4 control-label"><?= l7_t("Interfaces"); ?></label>
						<div class="col-sm-8">
						<?php foreach ($prof_ifaces as $ifc) { ?>
							<label class="checkbox-inline">
								<input type="checkbox" name="profile_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
								<?= htmlspecialchars($ifc["descr"]); ?>
							</label>
						<?php } ?>
							<p class="help-block"><?= l7_t("Nenhuma = todas."); ?></p>
						</div>
					</div>

					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("CIDRs de origem"); ?></label>
						<div class="col-sm-8">
							<textarea name="profile_src_cidrs" class="form-control" rows="2" placeholder="192.168.10.0/24"></textarea>
							<p class="help-block"><?= l7_t("Vazio = qualquer sub-rede."); ?></p>
						</div>
					</div>

					<?php if (!empty($l7_groups)) { ?>
					<div class="form-group">
						<label class="col-sm-4 control-label"><?= l7_t("Grupos"); ?></label>
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
							<p class="help-block"><?= l7_t("Alternativa a CIDRs manuais."); ?></p>
						</div>
					</div>
					<?php } ?>

					<div class="form-group">
						<div class="col-sm-offset-4 col-sm-8">
							<button type="submit" class="btn btn-success"><?= l7_t("Criar politica"); ?></button>
							<button type="button" class="btn btn-default" onclick="l7hideProfileModal();"><?= l7_t("Cancelar"); ?></button>
						</div>
					</div>
				</form>
			</div>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Politicas atuais"); ?></h3>
			<?php if (count($policies) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma politica cadastrada. Adicione a primeira regra abaixo ou importe um layer7.json existente."); ?></div>
			<?php } else { ?>
			<form method="post">
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

			<form method="post" class="form-inline layer7-inline-form"
				onsubmit='return confirm(<?= json_encode(l7_t("Remover esta politica do JSON?")); ?>);'>
				<div class="form-group">
					<label class="control-label" for="delete_policy_index"><?= l7_t("Remover politica"); ?></label>
					<select id="delete_policy_index" name="delete_policy_index" class="form-control">
						<?php foreach ($policies as $i => $policy) {
							$pid = isset($policy["id"]) ? (string)$policy["id"] : ("#" . $i);
							$pname = isset($policy["name"]) ? (string)$policy["name"] : "";
							$label = $pid . ($pname !== "" ? " - " . $pname : "");
						?>
						<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
						<?php } ?>
					</select>
					<button type="submit" name="delete_policy" value="1" class="btn btn-danger"><?= l7_t("Remover"); ?></button>
				</div>
			</form>
			<?php } ?>
		</div>

		<?php if ($view_policy !== null && $view_idx !== null) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Listas da politica"); ?></h3>
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
			<h3 class="layer7-section-title"><?= l7_t("Editar politica"); ?></h3>
			<p class="layer7-lead"><?= l7_t("Atualize os detalhes da regra selecionada. O identificador permanece fixo para manter a referencia no JSON."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_policies.php" class="btn btn-default"><?= l7_t("Cancelar edicao"); ?></a>
			</div>
			<form method="post" class="form-horizontal">
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
							$chk = in_array($ifc["real"], $edit_policy_ifaces, true) ? 'checked="checked"' : '';
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

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_match_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_hosts_match_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um host por linha, ex.: youtube.com ou api.whatsapp.com. O match aceita o host exacto e subdominios."); ?></p>
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
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="save_policy_edit" value="1" class="btn btn-primary"><?= l7_t("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Adicionar politica"); ?></h3>
			<p class="layer7-lead"><?= l7_t("Use nomes claros e prioridades previsiveis para manter a leitura do conjunto simples durante o troubleshooting."); ?></p>
			<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 24 politicas atingido."); ?></div>
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
					<label class="col-sm-3 control-label"><?= l7_t("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_match_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="youtube.com&#10;api.whatsapp.com"></textarea>
						<p class="help-block"><?= l7_t("Um host por linha, ex.: youtube.com. Para block, basta indicar sites aqui (sem necessidade de app nDPI). O bloqueio DNS atua automaticamente."); ?></p>
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
<style>
.l7-profiles-grid { display: flex; flex-wrap: wrap; gap: 14px; }
.l7-profile-card { border: 1px solid #ddd; border-radius: 6px; padding: 16px; width: 180px; text-align: center; background: #fdfdfd; transition: box-shadow 0.15s; }
.l7-profile-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
.l7-profile-card.l7-profile-used { opacity: 0.6; }
.l7-profile-icon-ios { width: 56px; height: 56px; border-radius: 13px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 6px rgba(0,0,0,0.18); }
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
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
