<?php
##|+PRIV
##|*IDENT=page-services-layer7-exceptions
##|*NAME=Services: Layer 7 (exceptions)
##|*DESCR=Allow access to Layer 7 exceptions.
##|*MATCH=layer7_exceptions.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$layer7_exception_edit_retry = null;
$vip_bulk_retry = null;

if ($_POST["export_vip_list"] ?? false) {
	$data = layer7_load_or_default();
	$text = layer7_vip_export_text($data, true);
	header("Content-Type: text/plain; charset=UTF-8");
	header("Content-Disposition: attachment; filename=\"layer7-vip-list-" . date("Ymd-His") . ".txt\"");
	echo $text;
	exit;
}

if ($_POST["import_vip_list"] ?? false) {
	if (!isset($_FILES["vip_import_file"]) ||
	    $_FILES["vip_import_file"]["error"] !== UPLOAD_ERR_OK) {
		$input_errors[] = l7_t("Nenhum ficheiro enviado ou erro no upload.");
	} else {
		$raw = @file_get_contents($_FILES["vip_import_file"]["tmp_name"]);
		if (!is_string($raw) || $raw === "") {
			$input_errors[] = l7_t("Ficheiro vazio.");
		} else {
			$data = layer7_load_or_default();
			$res = layer7_vip_import_from_raw($data, $raw);
			if (!$res["ok"]) {
				$input_errors[] = $res["error"];
			} elseif (layer7_save_json($data)) {
				layer7_pf_config_resync(true);
				$savemsg = l7_t("Lista VIP importada com sucesso.");
			} else {
				$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
			}
		}
	}
}

if ($_POST["save_vip_bulk"] ?? false) {
	$vip_bulk_retry = (string)($_POST["vip_bulk_text"] ?? "");
	$data = layer7_load_or_default();
	$res = layer7_vip_import_from_raw($data, $vip_bulk_retry, true);
	if (!$res["ok"]) {
		$input_errors[] = $res["error"];
	} elseif (layer7_save_json($data)) {
		layer7_pf_config_resync(true);
		$savemsg = l7_t("Lista VIP actualizada a partir do texto.");
		$vip_bulk_retry = null;
	} else {
		$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
	}
}

if ($_POST["add_vip_entry"] ?? false) {
	$data = layer7_load_or_default();
	$res = layer7_vip_add_entry(
		$data,
		$_POST["vip_description"] ?? "",
		$_POST["vip_target"] ?? ""
	);
	if (!$res["ok"]) {
		$input_errors[] = $res["error"];
	} elseif (layer7_save_json($data)) {
		layer7_pf_config_resync(true);
		$savemsg = l7_t("Isento adicionado a Lista VIP.");
	} else {
		$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
	}
}

if ($_POST["add_vip_from_dhcp"] ?? false) {
	$selected = array();
	if (isset($_POST["vip_dhcp_ip"]) && is_array($_POST["vip_dhcp_ip"])) {
		$selected = $_POST["vip_dhcp_ip"];
	}
	if (empty($selected)) {
		$input_errors[] = l7_t("Selecione pelo menos uma reserva DHCP.");
	} else {
		$data = layer7_load_or_default();
		$res = layer7_vip_add_from_dhcp_ips($data, $selected);
		if (!$res["ok"]) {
			$input_errors[] = $res["error"];
		} elseif (layer7_save_json($data)) {
			layer7_pf_config_resync(true);
			$savemsg = sprintf(l7_t("%d isento(s) adicionados a partir das reservas DHCP."),
			    (int)$res["added"]);
		} else {
			$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
		}
	}
}

if ($_POST["remove_vip_entry"] ?? false) {
	$data = layer7_load_or_default();
	$target = trim((string)($_POST["vip_remove_target"] ?? ""));
	$res = layer7_vip_remove_entry($data, $target);
	if (!$res["ok"]) {
		$input_errors[] = $res["error"];
	} elseif (layer7_save_json($data)) {
		layer7_pf_config_resync(true);
		$savemsg = l7_t("Isento removido da Lista VIP.");
	} else {
		$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
	}
}

if ($_POST["add_exception"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$ok = true;

		if (count($exceptions) >= 16) {
			$input_errors[] = l7_t("Limite de 16 excecoes.");
			$ok = false;
		}

		$eid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($eid)) {
			$input_errors[] = l7_t("ID invalido (letras, numeros, _ e -; max. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($exceptions as $existing_exception) {
				if (isset($existing_exception["id"]) && (string)$existing_exception["id"] === $eid) {
					$input_errors[] = l7_t("Ja existe uma excecao com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$hosts = layer7_parse_ip_textarea($_POST["new_hosts"] ?? "");
		$cidrs = layer7_parse_cidr_textarea($_POST["new_cidrs"] ?? "");
		if ($ok && empty($hosts) && empty($cidrs)) {
			$input_errors[] = l7_t("Indique pelo menos um host IPv4 ou CIDR.");
			$ok = false;
		}

		$new_exc_ifaces = array();
		if (isset($_POST["new_exc_ifaces"]) && is_array($_POST["new_exc_ifaces"])) {
			foreach ($_POST["new_exc_ifaces"] as $ifid) {
				$real = layer7_real_interface_name($ifid);
				if ($real !== "") {
					$new_exc_ifaces[] = $real;
				}
			}
		}

		$pri = (int)($_POST["new_priority"] ?? 500);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = l7_t("Prioridade invalida (0-99999).");
			$ok = false;
		}

		$act = $_POST["new_action"] ?? "allow";
		if (!in_array($act, array("allow", "block", "monitor", "tag"), true)) {
			$act = "allow";
		}

		if ($ok) {
			$rule = array(
				"id" => $eid,
				"enabled" => isset($_POST["new_enabled"]),
				"priority" => $pri,
				"action" => $act
			);
			if (!empty($hosts)) {
				$rule["hosts"] = $hosts;
			}
			if (!empty($cidrs)) {
				$rule["cidrs"] = $cidrs;
			}
			if (!empty($new_exc_ifaces)) {
				$rule["interfaces"] = array_values(array_unique($new_exc_ifaces));
			}
			$exceptions[] = $rule;
			if (layer7_save_json($data)) {
				layer7_pf_config_resync(true);
				$savemsg = l7_t("Excecao adicionada.");
			}
		}
		unset($exceptions);
}

if ($_POST["save_exceptions"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$count = count($exceptions);
		for ($i = 0; $i < $count; $i++) {
			$exceptions[$i]["enabled"] = isset($_POST["eon"][$i]);
		}
		unset($exceptions);
		if (layer7_save_json($data)) {
			layer7_pf_config_resync(true);
			$savemsg = l7_t("Excecoes atualizadas.");
		}
}

if ($_POST["delete_exception"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["delete_exception_index"] ?? -1);
		$count = count($exceptions);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = l7_t("Indice de excecao invalido.");
		} else {
			array_splice($exceptions, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_pf_config_resync(true);
				$savemsg = l7_t("Excecao removida.");
			}
		}
		unset($exceptions);
}

if ($_POST["save_exception_edit"] ?? false) {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["edit_exception_index"] ?? -1);
		$count = count($exceptions);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = l7_t("Indice de excecao invalido.");
		} else {
			$layer7_exception_edit_retry = $idx;
			$orig = $exceptions[$idx];
			$eid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$hosts = layer7_parse_ip_textarea($_POST["edit_hosts"] ?? "");
			$cidrs = layer7_parse_cidr_textarea($_POST["edit_cidrs"] ?? "");
			if ($ok && empty($hosts) && empty($cidrs)) {
				$input_errors[] = l7_t("Indique pelo menos um host IPv4 ou CIDR.");
				$ok = false;
			}

			$edit_exc_ifaces = array();
			if (isset($_POST["edit_exc_ifaces"]) && is_array($_POST["edit_exc_ifaces"])) {
				foreach ($_POST["edit_exc_ifaces"] as $ifid) {
					$real = layer7_real_interface_name($ifid);
					if ($real !== "") {
						$edit_exc_ifaces[] = $real;
					}
				}
			}

			$pri = (int)($_POST["edit_priority"] ?? 500);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = l7_t("Prioridade invalida (0-99999).");
				$ok = false;
			}

			$act = $_POST["edit_action"] ?? "allow";
			if (!in_array($act, array("allow", "block", "monitor", "tag"), true)) {
				$act = "allow";
			}

			if ($ok) {
				$rule = array(
					"id" => $eid,
					"enabled" => isset($_POST["edit_enabled"]),
					"priority" => $pri,
					"action" => $act
				);
				if (!empty($hosts)) {
					$rule["hosts"] = $hosts;
				}
				if (!empty($cidrs)) {
					$rule["cidrs"] = $cidrs;
				}
				if (!empty($edit_exc_ifaces)) {
					$rule["interfaces"] = array_values(array_unique($edit_exc_ifaces));
				}
				$exceptions[$idx] = $rule;
				if (layer7_save_json($data)) {
					layer7_pf_config_resync(true);
					header("Location: layer7_exceptions.php");
					exit;
				}
				$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
			}
		}
		unset($exceptions);
}

$data = layer7_load_or_default();
$exceptions = isset($data["layer7"]["exceptions"]) && is_array($data["layer7"]["exceptions"])
	? $data["layer7"]["exceptions"] : array();
$exc_limit = count($exceptions) >= 16;
$vip_entries = layer7_vip_list_entries($data);
$vip_groups = layer7_vip_source_groups($data);
$vip_host_count = count(array_filter($vip_entries, function ($r) {
	return ($r["kind"] ?? "") === "host";
}));
$vip_cidr_count = count(array_filter($vip_entries, function ($r) {
	return ($r["kind"] ?? "") === "cidr";
}));
$vip_at_host_limit = $vip_host_count >= LAYER7_VIP_MAX_HOSTS;
$vip_at_cidr_limit = $vip_cidr_count >= LAYER7_VIP_MAX_CIDRS;
$vip_dns_mode = layer7_vip_dns_mode_get($data);
$vip_bulk_text = ($vip_bulk_retry !== null)
	? $vip_bulk_retry
	: rtrim(layer7_vip_export_text($data, false));
$vip_dhcp_maps = layer7_dhcp_static_maps();
$vip_present = array();
foreach ($vip_entries as $ventry) {
	$vip_present[(string)($ventry["target"] ?? "")] = true;
}
$vip_dhcp_available = array();
foreach ($vip_dhcp_maps as $dmap) {
	$dip = (string)($dmap["ip"] ?? "");
	if ($dip !== "" && empty($vip_present[$dip])) {
		$vip_dhcp_available[] = $dmap;
	}
}
$vip_iface_index = layer7_dhcp_ip_iface_index($vip_dhcp_maps);
$vip_dhcp_groups = layer7_dhcp_maps_group_by_iface($vip_dhcp_available);
$vip_filter_ifaces = array();
foreach ($vip_dhcp_maps as $dmap) {
	$ifid = (string)($dmap["ifid"] ?? "");
	if ($ifid === "") {
		continue;
	}
	$vip_filter_ifaces[$ifid] = (string)($dmap["iface"] ?? strtoupper($ifid));
}

$edit_ex_idx = null;
$edit_ex = null;
if ($layer7_exception_edit_retry !== null && $layer7_exception_edit_retry >= 0 &&
    $layer7_exception_edit_retry < count($exceptions)) {
	$edit_ex_idx = (int)$layer7_exception_edit_retry;
	$edit_ex = $exceptions[$edit_ex_idx];
} elseif (isset($_GET["edit"]) && ctype_digit((string)$_GET["edit"])) {
	$edit_candidate = (int)$_GET["edit"];
	if ($edit_candidate >= 0 && $edit_candidate < count($exceptions)) {
		$edit_ex_idx = $edit_candidate;
		$edit_ex = $exceptions[$edit_candidate];
	}
}

require_once("classes/Form.class.php");

$l7_exc_add_retry = (($_POST["add_exception"] ?? false) && empty($savemsg));
$l7_exc_edit_retry = (($_POST["save_exception_edit"] ?? false) && empty($savemsg));
$l7_exc_edit_post_invalid = (($_POST["save_exception_edit"] ?? false) && empty($savemsg) &&
    $layer7_exception_edit_retry === null);
if ($l7_exc_edit_post_invalid) {
	$edit_ex = null;
	$edit_ex_idx = null;
}
$l7_save_exc_retry = (($_POST["save_exceptions"] ?? false) && empty($savemsg));
$l7_exc_mode = "list";
if (isset($_GET["edit"]) && (string)$_GET["edit"] !== "" && $edit_ex === null) {
	if (!ctype_digit((string)$_GET["edit"]) || (int)$_GET["edit"] < 0 ||
	    (int)$_GET["edit"] >= count($exceptions)) {
		$input_errors[] = l7_t("Indice de excecao invalido.");
	}
}
if ($edit_ex !== null && $edit_ex_idx !== null) {
	$l7_exc_mode = "edit";
} elseif ($l7_exc_add_retry || (isset($_GET["new"]) && (string)$_GET["new"] === "1")) {
	$l7_exc_mode = "new";
}

$l7_vip_post = !empty($_POST["add_vip_entry"]) || !empty($_POST["remove_vip_entry"]) ||
    !empty($_POST["add_vip_from_dhcp"]) || !empty($_POST["save_vip_bulk"]) ||
    !empty($_POST["import_vip_list"]);
$l7_vip_add_retry = (($_POST["add_vip_entry"] ?? false) && empty($savemsg));
$l7_vip_desc = "";
$l7_vip_target = "";
if ($l7_vip_add_retry) {
	if (array_key_exists("vip_description", $_POST)) {
		$l7_vip_desc = (string)$_POST["vip_description"];
	}
	if (array_key_exists("vip_target", $_POST)) {
		$l7_vip_target = (string)$_POST["vip_target"];
	}
}
$l7_general_post_retry = ($l7_exc_mode !== "list") || $l7_exc_add_retry || $l7_exc_edit_retry ||
    $l7_save_exc_retry || $l7_exc_edit_post_invalid ||
    (!empty($_POST["delete_exception"]) && empty($savemsg));
$l7_vip_mode = "";
$l7_vip_add_ok = !empty($_POST["add_vip_entry"]) && !empty($savemsg);
if ($l7_exc_mode === "list" && !$l7_general_post_retry) {
	if ($l7_vip_add_retry || (
	    !$l7_vip_add_ok && isset($_GET["vip_add"]) && (string)$_GET["vip_add"] === "1"
	)) {
		$l7_vip_mode = "add";
	} elseif (
	    (isset($_GET["vip"]) && (string)$_GET["vip"] === "1") ||
	    ($l7_vip_post && !$l7_vip_add_retry)
	) {
		$l7_vip_mode = "list";
	}
}
$l7_vip_bookmark_bridge = false;
if ($l7_exc_mode === "list" && $l7_vip_mode === "") {
	$l7_req_method = isset($_SERVER["REQUEST_METHOD"]) ?
	    strtoupper((string)$_SERVER["REQUEST_METHOD"]) : "GET";
	if ($l7_req_method === "GET" && empty($_POST)) {
		$l7_bm_skip = false;
		if (isset($_GET["new"]) && (string)$_GET["new"] !== "") {
			$l7_bm_skip = true;
		}
		if (isset($_GET["edit"]) && (string)$_GET["edit"] !== "") {
			$l7_bm_skip = true;
		}
		if (isset($_GET["vip"]) && (string)$_GET["vip"] !== "") {
			$l7_bm_skip = true;
		}
		if (isset($_GET["vip_add"]) && (string)$_GET["vip_add"] !== "") {
			$l7_bm_skip = true;
		}
		if (!$l7_bm_skip) {
			$l7_vip_bookmark_bridge = true;
		}
	}
}

$l7_new_id = "";
$l7_new_hosts = "";
$l7_new_cidrs = "";
$l7_new_priority = "500";
$l7_new_action = "allow";
$l7_new_enabled = true;
$l7_new_exc_ifaces = array();
if ($l7_exc_add_retry) {
	$l7_new_id = (string)($_POST["new_id"] ?? "");
	$l7_new_hosts = (string)($_POST["new_hosts"] ?? "");
	$l7_new_cidrs = (string)($_POST["new_cidrs"] ?? "");
	if (array_key_exists("new_priority", $_POST)) {
		$l7_new_priority = (string)$_POST["new_priority"];
	}
	if (isset($_POST["new_action"])) {
		$l7_new_action = (string)$_POST["new_action"];
	}
	if (!in_array($l7_new_action, array("allow", "block", "monitor", "tag"), true)) {
		$l7_new_action = "allow";
	}
	$l7_new_enabled = isset($_POST["new_enabled"]);
	if (isset($_POST["new_exc_ifaces"]) && is_array($_POST["new_exc_ifaces"])) {
		$l7_new_exc_ifaces = $_POST["new_exc_ifaces"];
	}
}

$ee_id = "";
$ee_hosts = "";
$ee_cidrs = "";
$ee_priority = "0";
$ee_action = "allow";
$ee_enabled = false;
$ee_ifaces_arr = array();
if ($edit_ex !== null) {
	$ee_id = isset($edit_ex["id"]) ? (string)$edit_ex["id"] : "";
	if (!empty($edit_ex["hosts"]) && is_array($edit_ex["hosts"])) {
		$ee_hosts = implode("\n", $edit_ex["hosts"]);
	} elseif (!empty($edit_ex["host"])) {
		$ee_hosts = (string)$edit_ex["host"];
	}
	if (!empty($edit_ex["cidrs"]) && is_array($edit_ex["cidrs"])) {
		$ee_cidrs = implode("\n", $edit_ex["cidrs"]);
	} elseif (!empty($edit_ex["cidr"])) {
		$ee_cidrs = (string)$edit_ex["cidr"];
	}
	$ee_priority = isset($edit_ex["priority"]) ? (string)$edit_ex["priority"] : "0";
	$ee_action = isset($edit_ex["action"]) ? (string)$edit_ex["action"] : "allow";
	if (!in_array($ee_action, array("allow", "block", "monitor", "tag"), true)) {
		$ee_action = "allow";
	}
	$ee_enabled = !empty($edit_ex["enabled"]);
	if (isset($edit_ex["interfaces"]) && is_array($edit_ex["interfaces"])) {
		$ee_ifaces_arr = $edit_ex["interfaces"];
	}
}
if ($l7_exc_edit_retry && $edit_ex !== null) {
	$ee_hosts = (string)($_POST["edit_hosts"] ?? "");
	$ee_cidrs = (string)($_POST["edit_cidrs"] ?? "");
	if (array_key_exists("edit_priority", $_POST)) {
		$ee_priority = (string)$_POST["edit_priority"];
	}
	if (isset($_POST["edit_action"])) {
		$ee_action = (string)$_POST["edit_action"];
		if (!in_array($ee_action, array("allow", "block", "monitor", "tag"), true)) {
			$ee_action = "allow";
		}
	}
	$ee_enabled = isset($_POST["edit_enabled"]);
	$ee_ifaces_arr = array();
	if (isset($_POST["edit_exc_ifaces"]) && is_array($_POST["edit_exc_ifaces"])) {
		$ee_ifaces_arr = $_POST["edit_exc_ifaces"];
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Exceptions"));
include("head.inc");
layer7_render_styles();

function layer7_exc_target_summary($exception) {
	$parts = array();
	if (!empty($exception["hosts"]) && is_array($exception["hosts"])) {
		$parts[] = count($exception["hosts"]) . " host(s)";
	} elseif (!empty($exception["host"])) {
		$parts[] = "host " . $exception["host"];
	}
	if (!empty($exception["cidrs"]) && is_array($exception["cidrs"])) {
		$parts[] = count($exception["cidrs"]) . " CIDR(s)";
	} elseif (!empty($exception["cidr"])) {
		$parts[] = "cidr " . $exception["cidr"];
	}
	if (!empty($exception["interfaces"]) && is_array($exception["interfaces"])) {
		$parts[] = "ifaces: " . implode(",", $exception["interfaces"]);
	}
	return empty($parts) ? l7_t("Nao definido") : implode(" | ", $parts);
}
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - excecoes"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("policies"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>
			<?php layer7_render_policies_subnav("exceptions"); ?>

			<?php if ($l7_exc_mode === "list") { ?>
			<ul class="nav nav-pills">
				<li<?= ($l7_vip_mode === "") ? ' class="active"' : ''; ?>>
					<a href="layer7_exceptions.php#l7-exceptions"><?= htmlspecialchars(l7_t("Excecoes")); ?></a>
				</li>
				<li<?= ($l7_vip_mode !== "") ? ' class="active"' : ''; ?>>
					<a href="layer7_exceptions.php?vip=1#l7-vip-list"><?= htmlspecialchars(l7_t("Lista VIP")); ?></a>
				</li>
			</ul>
			<?php } ?>

			<?php if ($l7_exc_mode === "list" && $l7_vip_mode !== "") { ?>
			<div class="layer7-admin-block" id="l7-vip-list">
			<div class="layer7-admin-block__header"><?= l7_t("Lista VIP"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Origens registadas na Lista VIP isentam do controlo Layer7 (PF, daemon e sinkhole DNS quando enforce activo), sem garantir bypass das regras nativas do pfSense. Gere a excepcao canonica vip-isentos com descricao por entrada."); ?></p>
			<?php if ($vip_dns_mode === "rdr_fallback") { ?>
			<div class="alert alert-warning">
				<strong><?= l7_t("Aviso DNS (fallback)"); ?>:</strong>
				<?= l7_t("A view Unbound VIP falhou validacao; isencao DNS usa apenas exclusao do rdr :53. Clientes VIP que usem o resolver local Unbound podem continuar sujeitos ao sinkhole — ver ADR-0020."); ?>
			</div>
			<?php } elseif ($vip_dns_mode === "unbound_view") { ?>
			<div class="alert alert-info">
				<strong><?= l7_t("Isencao DNS"); ?>:</strong>
				<?= l7_t("View Unbound dedicada activa: origens VIP nao recebem sinkhole da pagina de bloqueio. Host overrides nativos podem diferir — validar no lab (Bloco E)."); ?>
			</div>
			<?php } ?>
			<p class="help-block"><?= l7_t("Para dispositivos identificados por MAC, use Grupos com DHCP static mapping — IPs resolvidos podem ficar desactualizados se o lease mudar."); ?></p>
			<?php if (!empty($vip_groups)) { ?>
			<p class="help-block"><strong><?= l7_t("Grupos isentos (via Perfis rapidos)"); ?>:</strong>
				<?= htmlspecialchars(implode(", ", $vip_groups)); ?>
			</p>
			<?php } ?>

			<?php if ($l7_vip_mode === "list") { ?>
			<?php if (count($vip_entries) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhum isento directo na Lista VIP."); ?></div>
			<?php } else { ?>
			<?php if (count($vip_filter_ifaces) > 1) { ?>
			<div class="l7-iface-filter" id="l7-vip-iface-filter">
				<span class="help-block" style="display:inline; margin-right:8px;"><?= l7_t("Filtrar por interface"); ?>:</span>
				<button type="button" class="btn btn-xs btn-default l7-vip-iface-btn active" data-iface=""><?= l7_t("Todas as interfaces"); ?></button>
				<?php foreach ($vip_filter_ifaces as $fid => $flabel) { ?>
				<button type="button" class="btn btn-xs btn-default l7-vip-iface-btn" data-iface="<?= htmlspecialchars($fid); ?>"><?= htmlspecialchars($flabel); ?></button>
				<?php } ?>
			</div>
			<?php } ?>
			<div class="layer7-form-card">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= l7_t("Descricao"); ?></th>
								<th><?= l7_t("IP/CIDR"); ?></th>
								<th><?= l7_t("Interface"); ?></th>
								<th><?= l7_t("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($vip_entries as $ventry) {
							$vtarget = (string)($ventry["target"] ?? "");
							$vdesc = (string)($ventry["description"] ?? "");
							$vif = isset($vip_iface_index[$vtarget]) ? $vip_iface_index[$vtarget] : array("ifid" => "", "iface" => "");
							$vifid = (string)($vif["ifid"] ?? "");
							$viflabel = (string)($vif["iface"] ?? "");
						?>
							<tr class="l7-iface-row" data-iface="<?= htmlspecialchars($vifid); ?>">
								<td><?= htmlspecialchars($vdesc !== "" ? $vdesc : "—"); ?></td>
								<td><code><?= htmlspecialchars($vtarget); ?></code></td>
								<td><?= htmlspecialchars($viflabel !== "" ? $viflabel : "—"); ?></td>
								<td class="layer7-table-actions">
									<form method="post" action="layer7_exceptions.php#l7-vip-list" style="display:inline;"
										onsubmit='return confirm(<?= json_encode(l7_t("Remover este isento da Lista VIP?"), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);'>
										<input type="hidden" name="vip_remove_target" value="<?= htmlspecialchars($vtarget); ?>" />
										<button type="submit" name="remove_vip_entry" value="1" class="btn btn-xs btn-danger"><?= l7_t("Remover"); ?></button>
									</form>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php } ?>
			<?php } ?>

			<p class="help-block small"><?= sprintf(l7_t("Limites actuais: %d IPs + %d CIDRs (daemon)."), LAYER7_VIP_MAX_HOSTS, LAYER7_VIP_MAX_CIDRS); ?></p>

			<?php if ($l7_vip_mode === "list") { ?>
			<p>
				<a class="btn btn-success" href="layer7_exceptions.php?vip_add=1#l7-vip-list"><?= htmlspecialchars(l7_t("Adicionar isento")); ?></a>
			</p>
			<?php } ?>

			<?php if ($l7_vip_mode === "list") { ?>
			<div class="layer7-form-card" style="margin-top:16px;">
				<h4 class="layer7-form-card__title"><?= l7_t("Reservas DHCP (IPs prefixados)"); ?></h4>
				<p class="help-block"><?= l7_t("Le as reservas estaticas de Services > DHCP Server em cada interface. Escolha quais IPs entram na Lista VIP — nada e isento automaticamente."); ?></p>
				<p class="help-block"><?= l7_t("Cada coluna e uma interface: so ve os IPs prefixados nessa rede."); ?></p>
				<?php if (count($vip_dhcp_maps) === 0) { ?>
				<div class="alert alert-info"><?= l7_t("Nenhuma reserva DHCP com IP nas interfaces. Crie mapeamentos estaticos no DHCP do pfSense."); ?></div>
				<?php } elseif (count($vip_dhcp_available) === 0) { ?>
				<div class="alert alert-info"><?= l7_t("Todas as reservas DHCP com IP ja estao na Lista VIP."); ?></div>
				<?php } elseif ($vip_at_host_limit) { ?>
				<div class="alert alert-warning"><?= l7_t("Limites da Lista VIP atingidos."); ?></div>
				<?php } else { ?>
				<form method="post" action="layer7_exceptions.php#l7-vip-list">
					<?php if (count($vip_filter_ifaces) > 1) { ?>
					<div class="l7-iface-filter" id="l7-dhcp-iface-filter">
						<span class="help-block" style="display:inline; margin-right:8px;"><?= l7_t("Filtrar por interface"); ?>:</span>
						<button type="button" class="btn btn-xs btn-default active" data-iface="" onclick="l7filterIface('');"><?= l7_t("Todas as interfaces"); ?></button>
						<?php foreach ($vip_filter_ifaces as $fid => $flabel) { ?>
						<button type="button" class="btn btn-xs btn-default" data-iface="<?= htmlspecialchars($fid); ?>" onclick="l7filterIface(<?= json_encode($fid); ?>);"><?= htmlspecialchars($flabel); ?></button>
						<?php } ?>
					</div>
					<?php } ?>
					<div class="l7-bulk-tools" style="margin-bottom:8px;">
						<button type="button" class="btn btn-xs btn-default" onclick="l7setVisibleChecks('vip_dhcp_list', true);"><?= l7_t("Selecionar tudo"); ?></button>
						<button type="button" class="btn btn-xs btn-default" onclick="l7setVisibleChecks('vip_dhcp_list', false);"><?= l7_t("Limpar"); ?></button>
					</div>
					<div class="l7-iface-cols" id="vip_dhcp_list">
					<?php foreach ($vip_dhcp_groups as $group) {
						$gid = (string)($group["ifid"] ?? "");
						$glabel = (string)($group["iface"] ?? $gid);
						$col_id = "vip_dhcp_col_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $gid);
					?>
						<div class="l7-iface-col" data-iface="<?= htmlspecialchars($gid); ?>" id="<?= htmlspecialchars($col_id); ?>">
							<div class="l7-iface-col__title">
								<?= htmlspecialchars($glabel); ?>
								<span class="badge"><?= count($group["entries"]); ?></span>
							</div>
							<div class="table-responsive">
								<table class="table table-condensed table-striped">
									<thead>
										<tr>
											<th></th>
											<th><?= l7_t("Descricao"); ?></th>
											<th><?= l7_t("IP"); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php foreach ($group["entries"] as $dmap) {
										$dip = (string)$dmap["ip"];
										$dlabel = (string)($dmap["label"] ?? $dip);
									?>
										<tr>
											<td><input type="checkbox" name="vip_dhcp_ip[]" value="<?= htmlspecialchars($dip); ?>" /></td>
											<td><?= htmlspecialchars($dlabel); ?></td>
											<td><code><?= htmlspecialchars($dip); ?></code></td>
										</tr>
									<?php } ?>
									</tbody>
								</table>
							</div>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(<?= json_encode($col_id); ?>, true);"><?= l7_t("Selecionar esta interface"); ?></button>
						</div>
					<?php } ?>
					</div>
					<button type="submit" name="add_vip_from_dhcp" value="1" class="btn btn-success">
						<?= l7_t("Adicionar seleccionados a Lista VIP"); ?>
					</button>
				</form>
				<?php } ?>
			</div>

			<div class="layer7-form-card" style="margin-top:16px;">
				<h4 class="layer7-form-card__title"><?= l7_t("Editar lista em lote"); ?></h4>
				<p class="help-block"><?= l7_t("Formato simples: uma linha por isento, IP ou rede, virgula e nome. Linhas com # sao ignoradas."); ?></p>
				<p class="help-block"><code>192.168.1.60, Silvana</code></p>
				<form method="post" action="layer7_exceptions.php#l7-vip-list">
					<div class="form-group">
						<textarea name="vip_bulk_text" class="form-control" rows="12" spellcheck="false"
							style="font-family:Menlo,Consolas,monospace; max-width:640px;"><?= htmlspecialchars($vip_bulk_text); ?></textarea>
					</div>
					<button type="submit" name="save_vip_bulk" value="1" class="btn btn-primary"
						onclick="return confirm(<?= json_encode(l7_t("Guardar a lista em lote substitui as entradas directas da Lista VIP. Continuar?")); ?>);">
						<?= l7_t("Guardar lista em lote"); ?>
					</button>
				</form>
			</div>

			<div class="layer7-toolbar" style="margin-top:16px;">
				<form method="post" action="layer7_exceptions.php#l7-vip-list" style="display:inline;">
					<button type="submit" name="export_vip_list" value="1" class="btn btn-sm btn-info">
						<i class="fa fa-download"></i> <?= l7_t("Exportar Lista VIP"); ?>
					</button>
				</form>
				<form method="post" action="layer7_exceptions.php#l7-vip-list" enctype="multipart/form-data" class="form-inline" style="display:inline; margin-left:8px;">
					<input type="file" name="vip_import_file" accept=".txt,.csv,.json,text/plain,text/csv,application/json" style="display:inline-block; width:auto;" />
					<button type="submit" name="import_vip_list" value="1" class="btn btn-sm btn-warning"
						onclick="return confirm(<?= json_encode(l7_t("Importar substitui entradas directas da Lista VIP (grupos isentos sao limpos). Continuar?")); ?>);">
						<i class="fa fa-upload"></i> <?= l7_t("Importar Lista VIP"); ?>
					</button>
				</form>
				<p class="help-block" style="margin-top:8px;"><?= l7_t("Tambem pode exportar/importar um ficheiro .txt (Excel e Bloco de notas). JSON antigo continua a ser aceite."); ?></p>
			</div>
			<?php } ?>

			<?php if ($l7_vip_mode === "add") { ?>
			<p>
				<a class="btn btn-default" href="layer7_exceptions.php?vip=1#l7-vip-list"><?= htmlspecialchars(l7_t("Voltar a Lista VIP")); ?></a>
			</p>
			<?php if ($vip_at_host_limit && $vip_at_cidr_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limites da Lista VIP atingidos."); ?></div>
			<?php } ?>
			<?php if (!$vip_at_host_limit || !$vip_at_cidr_limit || $l7_vip_add_retry) {
				$vip_add_form = new Form(false);
				$vip_add_form->setAction("layer7_exceptions.php#l7-vip-list");
				$sec_vip_add = new Form_Section(l7_t("Adicionar isento"));
				$vip_desc_in = new Form_Input("vip_description", l7_t("Descricao"), "text", $l7_vip_desc);
				$vip_desc_in->setAttribute("maxlength", "64");
				$vip_desc_in->setAttribute("required", "required");
				$vip_desc_in->setAttribute("id", "l7-vip-add-description");
				$vip_desc_in->setAttribute("placeholder", l7_t("ex.: Director"));
				$sec_vip_add->addInput($vip_desc_in);
				$vip_tgt_in = new Form_Input("vip_target", l7_t("IP ou CIDR"), "text", $l7_vip_target);
				$vip_tgt_in->setAttribute("required", "required");
				$vip_tgt_in->setAttribute("id", "l7-vip-add-target");
				$vip_tgt_in->setAttribute("placeholder", "192.168.1.50 / 192.168.10.0/24");
				$vip_tgt_in->setHelp(l7_t("Um IP (IPv4/IPv6) ou CIDR por entrada."));
				$sec_vip_add->addInput($vip_tgt_in);
				$vip_add_btn = '<button type="submit" name="add_vip_entry" value="1" class="btn btn-success">' .
					htmlspecialchars(l7_t("Adicionar isento")) . '</button> ' .
					'<a class="btn btn-default" href="layer7_exceptions.php?vip=1#l7-vip-list">' .
					htmlspecialchars(l7_t("Cancelar")) . '</a>';
				$sec_vip_add->addInput(new Form_StaticText("", $vip_add_btn));
				$vip_add_form->add($sec_vip_add);
				print($vip_add_form);
			} ?>
			<?php } ?>

			</div>
			</div>
			<?php } ?>

			<?php if ($l7_exc_mode === "list" && $l7_vip_mode === "") { ?>
			<p class="layer7-lead"><?= l7_t("Excecoes sao avaliadas antes das politicas e ajudam a preservar trafego de gestao, redes internas e casos especiais durante os testes."); ?></p>

		<div class="layer7-admin-block" id="l7-exceptions">
			<div class="layer7-admin-block__header"><?= l7_t("Excecoes atuais"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="help-block"><?= l7_t("Prioridade maior = regra avaliada primeiro."); ?></p>
			<div id="l7-add-exc">
			<?php if ($exc_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 16 excecoes atingido."); ?></div>
			<?php } else { ?>
			<p>
				<a class="btn btn-primary" href="layer7_exceptions.php?new=1"><?= htmlspecialchars(l7_t("Adicionar excecao")); ?></a>
			</p>
			<?php } ?>
			</div>
			<?php if (count($exceptions) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma excecao cadastrada no momento."); ?>
				<?php if (!$exc_limit) { ?>
				<a href="layer7_exceptions.php?new=1"><?= htmlspecialchars(l7_t("Adicionar a primeira excecao.")); ?></a>
				<?php } ?>
			</div>
			<?php } else {
				$list_rows = "";
				foreach ($exceptions as $i => $exception) {
					$eid = isset($exception["id"]) ? (string)$exception["id"] : "";
					$action = isset($exception["action"]) ? (string)$exception["action"] : "";
					$priority = isset($exception["priority"]) ? (int)$exception["priority"] : 0;
					$enabled = !empty($exception["enabled"]);
					if ($l7_save_exc_retry) {
						if (isset($_POST["eon"]) && is_array($_POST["eon"])) {
							$enabled = isset($_POST["eon"][$i]) || isset($_POST["eon"][(string)$i]);
						} else {
							$enabled = false;
						}
					}
					$target = layer7_exc_target_summary($exception);
					$chk = $enabled ? ' checked="checked"' : '';
					$cb_id = "eon-exc-" . (int)$i;
					$cb_label = htmlspecialchars($eid !== "" ? $eid : ("#" . $i), ENT_QUOTES, "UTF-8");
					$list_rows .= '<tr>';
					$list_rows .= '<td><label for="' . $cb_id . '" class="sr-only">' . $cb_label .
						'</label><input type="checkbox" id="' . $cb_id . '" name="eon[' . (int)$i .
						']" value="1"' . $chk . ' /></td>';
					$list_rows .= '<td>' . htmlspecialchars((string)$priority) . '</td>';
					$list_rows .= '<td><span class="label label-default">' . htmlspecialchars($action) . '</span>';
					if (layer7_is_managed_vip_exception($exception)) {
						$list_rows .= ' <span class="label label-info" title="' .
							htmlspecialchars(l7_t("Gerida pelos Perfis rapidos"), ENT_QUOTES, "UTF-8") . '">' .
							htmlspecialchars(l7_t("Perfis rapidos")) . '</span>';
					}
					$list_rows .= '</td>';
					$list_rows .= '<td><code>' . htmlspecialchars($eid) . '</code></td>';
					$list_rows .= '<td class="small">' . htmlspecialchars($target) . '</td>';
					$list_rows .= '<td class="layer7-table-actions">';
					$list_rows .= '<a href="layer7_exceptions.php?edit=' . (int)$i . '" class="btn btn-xs btn-info">' .
						htmlspecialchars(l7_t("Editar")) . '</a>';
					$list_rows .= '</td></tr>';
				}
				$list_table = '<div class="table-responsive"><table class="table table-striped table-hover">' .
					'<thead><tr>' .
					'<th>' . htmlspecialchars(l7_t("Ativa")) . '</th>' .
					'<th>' . htmlspecialchars(l7_t("Prioridade")) . '</th>' .
					'<th>' . htmlspecialchars(l7_t("Acao")) . '</th>' .
					'<th><code>id</code></th>' .
					'<th>' . htmlspecialchars(l7_t("Alvo")) . '</th>' .
					'<th>' . htmlspecialchars(l7_t("Acoes")) . '</th>' .
					'</tr></thead><tbody>' . $list_rows . '</tbody></table></div>';
				$save_btn = '<button type="submit" name="save_exceptions" value="1" class="btn btn-primary">' .
					htmlspecialchars(l7_t("Guardar estado das excecoes")) . '</button>';
				$list_form = new Form(false);
				$list_form->setAction("layer7_exceptions.php#l7-exceptions");
				$sec_list = new Form_Section(l7_t("Excecoes atuais"));
				$sec_list->addInput(new Form_StaticText("", $list_table));
				$sec_list->addInput(new Form_StaticText("", $save_btn));
				$list_form->add($sec_list);
				print($list_form);

				$del_opts = array();
				foreach ($exceptions as $i => $exception) {
					$eid = isset($exception["id"]) ? (string)$exception["id"] : ("#" . $i);
					$del_opts[(string)(int)$i] = $eid . " - " . layer7_exc_target_summary($exception);
				}
				$del_sel_value = "0";
				if (!empty($input_errors) && ($_POST["delete_exception"] ?? false) &&
				    isset($_POST["delete_exception_index"])) {
					$posted_del = (string)(int)$_POST["delete_exception_index"];
					if (array_key_exists($posted_del, $del_opts)) {
						$del_sel_value = $posted_del;
					}
				}
				$del_form = new Form(false);
				$del_form->setAction("layer7_exceptions.php#l7-exceptions");
				$del_form->setAttribute("onsubmit", "return confirm(" .
					json_encode(l7_t("Remover esta excecao do JSON?")) . ");");
				$sec_del = new Form_Section(l7_t("Remover excecao"));
				$del_sel = new Form_Select("delete_exception_index", l7_t("Remover excecao"), $del_sel_value, $del_opts);
				$del_sel->setAttribute("id", "delete_exception_index");
				$sec_del->addInput($del_sel);
				$del_btn_html = '<button type="submit" name="delete_exception" value="1" class="btn btn-danger">' .
					'<i class="fa fa-trash"></i> ' . htmlspecialchars(l7_t("Remover")) . '</button>';
				$sec_del->addInput(new Form_StaticText("", $del_btn_html));
				$del_form->add($sec_del);
				print($del_form);
			} ?>
		</div>
		</div>
			<?php } ?>
		<?php if ($l7_exc_mode === "edit" && $edit_ex !== null && $edit_ex_idx !== null) {
			$ee_ifaces = layer7_get_pfsense_interfaces();
			$edit_iface_html = '<div class="l7-bulk-tools">' .
				'<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'edit_exc_ifaces_list\', true);">' .
				htmlspecialchars(l7_t("Selecionar tudo")) . '</button> ' .
				'<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'edit_exc_ifaces_list\', false);">' .
				htmlspecialchars(l7_t("Limpar")) . '</button></div><div id="edit_exc_ifaces_list">';
			foreach ($ee_ifaces as $ifc) {
				$chk = (in_array($ifc["real"], $ee_ifaces_arr, true) ||
				    in_array($ifc["ifid"], $ee_ifaces_arr, true)) ? ' checked="checked"' : '';
				$edit_iface_html .= '<label class="checkbox-inline"><input type="checkbox" name="edit_exc_ifaces[]" value="' .
					htmlspecialchars($ifc["ifid"], ENT_QUOTES, "UTF-8") . '"' . $chk . ' /> ' .
					htmlspecialchars($ifc["descr"]) . ' <span class="text-muted">(' .
					htmlspecialchars($ifc["real"]) . ')</span></label>';
			}
			$edit_iface_html .= '</div>';
		?>
		<div class="layer7-admin-block" id="l7-edit-exc">
			<div class="layer7-admin-block__header"><?= l7_t("Editar excecao"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Use excecoes para trafego de gestao, IPs criticos e redes que nao devem ser avaliadas pelas politicas gerais."); ?></p>
			<p>
				<a class="btn btn-default" href="layer7_exceptions.php"><?= htmlspecialchars(l7_t("Cancelar edicao")); ?></a>
			</p>
<?php
			$edit_form = new Form(false);
			$edit_form->setAction("layer7_exceptions.php#l7-edit-exc");
			$sec_edit = new Form_Section(l7_t("Editar excecao"));
			$sec_edit->addInput(new Form_StaticText(
				"id",
				"<code>" . htmlspecialchars($ee_id !== "" ? $ee_id : "(vazio)") . "</code>"
			));
			$idx_hidden = new Form_Input("edit_exception_index", "", "hidden", (string)(int)$edit_ex_idx);
			$idx_hidden->setAttribute("id", "l7-edit-exception-index");
			$sec_edit->addInput($idx_hidden);
			$hosts_in = new Form_Textarea("edit_hosts", l7_t("Hosts (IP)"), $ee_hosts);
			$hosts_in->setRows(3);
			$hosts_in->setAttribute("id", "l7-edit-exc-hosts");
			$hosts_in->setHelp(l7_t("Um IP por linha. Pode combinar com CIDRs."));
			$sec_edit->addInput($hosts_in);
			$cidrs_in = new Form_Textarea("edit_cidrs", l7_t("CIDRs"), $ee_cidrs);
			$cidrs_in->setRows(2);
			$cidrs_in->setAttribute("id", "l7-edit-exc-cidrs");
			$cidrs_in->setHelp(l7_t("Um CIDR por linha. Ex.: 192.168.0.0/24"));
			$sec_edit->addInput($cidrs_in);
			$iface_st = new Form_StaticText(l7_t("Interfaces"), $edit_iface_html);
			$iface_st->setHelp(l7_t("Nenhuma = aplica a todas."));
			$sec_edit->addInput($iface_st);
			$pri_in = new Form_Input("edit_priority", l7_t("Prioridade"), "number", $ee_priority,
				array("min" => 0, "max" => 99999));
			$pri_in->setAttribute("id", "l7-edit-exc-priority");
			$sec_edit->addInput($pri_in);
			$act_opts = array(
				"allow" => l7_t("allow"),
				"block" => l7_t("block"),
				"monitor" => l7_t("monitor"),
				"tag" => l7_t("tag"),
			);
			$act_sel = new Form_Select("edit_action", l7_t("Acao"), $ee_action, $act_opts);
			$act_sel->setAttribute("id", "l7-edit-exc-action");
			$sec_edit->addInput($act_sel);
			$en_cb = new Form_Checkbox(
				"edit_enabled",
				l7_t("Ativa"),
				l7_t("Regra habilitada"),
				$ee_enabled,
				"1"
			);
			$en_cb->setAttribute("id", "l7-edit-exc-enabled");
			$sec_edit->addInput($en_cb);
			$save_edit_btn_html = '<button type="submit" name="save_exception_edit" value="1" class="btn btn-primary">' .
				'<i class="fa fa-save"></i> ' . htmlspecialchars(l7_t("Guardar alteracoes")) . '</button>';
			$sec_edit->addInput(new Form_StaticText("", $save_edit_btn_html));
			$edit_form->add($sec_edit);
			print($edit_form);
?>
			<p class="help-block"><?= htmlspecialchars(l7_t("O id nao pode ser alterado pela GUI.")); ?></p>
		</div>
		</div>
		<?php } ?>
		<?php if ($l7_exc_mode === "new") { ?>
		<div class="layer7-admin-block" id="l7-add-exc">
			<div class="layer7-admin-block__header"><?= l7_t("Adicionar excecao"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Cadastre aqui os alvos que devem fugir do fluxo padrao de classificacao, sem precisar editar o JSON manualmente."); ?></p>
			<p>
				<a class="btn btn-default" href="layer7_exceptions.php"><?= htmlspecialchars(l7_t("Voltar a lista")); ?></a>
			</p>
			<?php if ($exc_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 16 excecoes atingido."); ?></div>
			<?php } ?>
			<?php if (!$exc_limit || $l7_exc_add_retry) {
				$pf_ifaces_exc = layer7_get_pfsense_interfaces();
				$new_iface_html = '<div class="l7-bulk-tools">' .
					'<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'new_exc_ifaces_list\', true);">' .
					htmlspecialchars(l7_t("Selecionar tudo")) . '</button> ' .
					'<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks(\'new_exc_ifaces_list\', false);">' .
					htmlspecialchars(l7_t("Limpar")) . '</button></div><div id="new_exc_ifaces_list">';
				foreach ($pf_ifaces_exc as $ifc) {
					$chk = in_array($ifc["ifid"], $l7_new_exc_ifaces, true) ? ' checked="checked"' : '';
					$new_iface_html .= '<label class="checkbox-inline"><input type="checkbox" name="new_exc_ifaces[]" value="' .
						htmlspecialchars($ifc["ifid"], ENT_QUOTES, "UTF-8") . '"' . $chk . ' /> ' .
						htmlspecialchars($ifc["descr"]) . ' <span class="text-muted">(' .
						htmlspecialchars($ifc["real"]) . ')</span></label>';
				}
				$new_iface_html .= '</div>';
				$add_form = new Form(false);
				$add_form->setAction("layer7_exceptions.php#l7-add-exc");
				$sec_add = new Form_Section(l7_t("Adicionar excecao"));
				$id_in = new Form_Input("new_id", "id", "text", $l7_new_id);
				$id_in->setAttribute("maxlength", "80");
				$id_in->setAttribute("pattern", "[a-zA-Z0-9_-]+");
				$id_in->setAttribute("required", "required");
				$id_in->setAttribute("id", "l7-new-exc-id");
				$id_in->setAttribute("placeholder", "ex-mgmt-001");
				$sec_add->addInput($id_in);
				$new_hosts_in = new Form_Textarea("new_hosts", l7_t("Hosts (IP)"), $l7_new_hosts);
				$new_hosts_in->setRows(3);
				$new_hosts_in->setAttribute("id", "l7-new-exc-hosts");
				$new_hosts_in->setAttribute("placeholder", "10.0.0.99\n10.0.0.100");
				$new_hosts_in->setHelp(l7_t("Um IP por linha. Pode combinar com CIDRs."));
				$sec_add->addInput($new_hosts_in);
				$new_cidrs_in = new Form_Textarea("new_cidrs", l7_t("CIDRs"), $l7_new_cidrs);
				$new_cidrs_in->setRows(2);
				$new_cidrs_in->setAttribute("id", "l7-new-exc-cidrs");
				$new_cidrs_in->setAttribute("placeholder", "192.168.77.0/24");
				$new_cidrs_in->setHelp(l7_t("Um CIDR por linha."));
				$sec_add->addInput($new_cidrs_in);
				$new_iface_st = new Form_StaticText(l7_t("Interfaces"), $new_iface_html);
				$new_iface_st->setHelp(l7_t("Nenhuma = aplica a todas."));
				$sec_add->addInput($new_iface_st);
				$new_pri = new Form_Input("new_priority", l7_t("Prioridade"), "number", $l7_new_priority,
					array("min" => 0, "max" => 99999));
				$new_pri->setAttribute("id", "l7-new-exc-priority");
				$sec_add->addInput($new_pri);
				$new_act_opts = array(
					"allow" => l7_t("allow"),
					"block" => l7_t("block"),
					"monitor" => l7_t("monitor"),
					"tag" => l7_t("tag"),
				);
				$new_act = new Form_Select("new_action", l7_t("Acao"), $l7_new_action, $new_act_opts);
				$new_act->setAttribute("id", "l7-new-exc-action");
				$sec_add->addInput($new_act);
				$new_en = new Form_Checkbox(
					"new_enabled",
					l7_t("Ativa"),
					l7_t("Criar excecao ja habilitada"),
					$l7_new_enabled,
					"1"
				);
				$new_en->setAttribute("id", "l7-new-exc-enabled");
				$sec_add->addInput($new_en);
				$add_btn_html = '<button type="submit" name="add_exception" value="1" class="btn btn-success">' .
					'<i class="fa fa-plus"></i> ' . htmlspecialchars(l7_t("Adicionar excecao")) . '</button>';
				$sec_add->addInput(new Form_StaticText("", $add_btn_html));
				$add_form->add($sec_add);
				print($add_form);
			} ?>
			<p class="layer7-muted-note small"><?= l7_t("Para alterar o id de uma excecao existente, edite /usr/local/etc/layer7.json diretamente."); ?></p>
		</div>
		</div>
		<?php } ?>
		</div>
	</div>
</div>
<script>
function l7setChecks(listId, checked) {
	var wrap = document.getElementById(listId);
	var i, boxes;
	if (!wrap) return;
	boxes = wrap.querySelectorAll('input[type="checkbox"]');
	for (i = 0; i < boxes.length; i++) {
		boxes[i].checked = checked;
	}
}
function l7setVisibleChecks(listId, checked) {
	var wrap = document.getElementById(listId);
	var i, boxes, col;
	if (!wrap) return;
	boxes = wrap.querySelectorAll('input[type="checkbox"]');
	for (i = 0; i < boxes.length; i++) {
		col = boxes[i].parentNode;
		while (col && col !== wrap) {
			if (col.className && String(col.className).indexOf("l7-iface-col") !== -1) {
				if (String(col.className).indexOf("is-hidden") !== -1) {
					col = null;
					break;
				}
			}
			col = col.parentNode;
		}
		if (col === null) {
			continue;
		}
		boxes[i].checked = checked;
	}
}
function l7filterIface(ifid) {
	var i, el, match, btns, cols, rows;
	ifid = ifid || "";
	cols = document.querySelectorAll(".l7-iface-col");
	for (i = 0; i < cols.length; i++) {
		match = (ifid === "" || cols[i].getAttribute("data-iface") === ifid);
		cols[i].className = cols[i].className.replace(/\s*is-hidden\b/g, "");
		if (!match) {
			cols[i].className += " is-hidden";
		}
	}
	rows = document.querySelectorAll(".l7-iface-row");
	for (i = 0; i < rows.length; i++) {
		match = (ifid === "" || rows[i].getAttribute("data-iface") === ifid);
		rows[i].className = rows[i].className.replace(/\s*is-hidden\b/g, "");
		if (!match) {
			rows[i].className += " is-hidden";
		}
	}
	btns = document.querySelectorAll(".l7-iface-filter .btn");
	for (i = 0; i < btns.length; i++) {
		el = btns[i];
		el.className = el.className.replace(/\s*active\b/g, "");
		if ((el.getAttribute("data-iface") || "") === ifid) {
			el.className += " active";
		}
	}
}
(function () {
	var vipFilter = document.getElementById("l7-vip-iface-filter");
	if (vipFilter) {
		vipFilter.addEventListener("click", function (ev) {
			var btn = ev.target, cls;
			while (btn && btn !== vipFilter) {
				cls = btn.className ? String(btn.className) : "";
				if (cls.indexOf("l7-vip-iface-btn") !== -1) {
					l7filterIface(btn.getAttribute("data-iface") || "");
					return;
				}
				btn = btn.parentNode;
			}
		});
	}
})();
</script>
<?php if ($l7_vip_bookmark_bridge) { ?>
<script id="l7-vip-bookmark-bridge">
(function () {
	if (window.location.hash === "#l7-vip-list") {
		window.location.replace("layer7_exceptions.php?vip=1#l7-vip-list");
	}
})();
</script>
<?php } ?>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
