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

if ($_POST["export_vip_list"] ?? false) {
	$data = layer7_load_or_default();
	$payload = layer7_vip_export_payload($data);
	$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	header("Content-Type: application/json");
	header("Content-Disposition: attachment; filename=\"layer7-vip-list-" . date("Ymd-His") . ".json\"");
	echo $json;
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
			$imported = @json_decode($raw, true);
			$data = layer7_load_or_default();
			$res = layer7_vip_import_apply($data, $imported);
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

			<div class="layer7-admin-block" id="l7-vip-list">
			<div class="layer7-admin-block__header"><?= l7_t("Lista VIP (isencao total)"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Origens isentas de todos os bloqueios Layer7 (PF, daemon e sinkhole DNS quando enforce activo). Gere a excepcao canonica vip-isentos com descricao por entrada."); ?></p>
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

			<?php if (count($vip_entries) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhum isento directo na Lista VIP."); ?></div>
			<?php } else { ?>
			<div class="layer7-form-card">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= l7_t("Descricao"); ?></th>
								<th><?= l7_t("IP/CIDR"); ?></th>
								<th><?= l7_t("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($vip_entries as $ventry) {
							$vtarget = (string)($ventry["target"] ?? "");
							$vdesc = (string)($ventry["description"] ?? "");
						?>
							<tr>
								<td><?= htmlspecialchars($vdesc !== "" ? $vdesc : "—"); ?></td>
								<td><code><?= htmlspecialchars($vtarget); ?></code></td>
								<td class="layer7-table-actions">
									<form method="post" action="layer7_exceptions.php#l7-vip-list" style="display:inline;"
										onsubmit='return confirm(<?= json_encode(l7_t("Remover este isento da Lista VIP?")); ?>);'>
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

			<p class="help-block small"><?= sprintf(l7_t("Limites actuais: %d IPs + %d CIDRs (daemon)."), LAYER7_VIP_MAX_HOSTS, LAYER7_VIP_MAX_CIDRS); ?></p>

			<div class="layer7-form-card" style="margin-top:16px;">
				<h4 class="layer7-form-card__title"><?= l7_t("Adicionar isento"); ?></h4>
				<?php if ($vip_at_host_limit && $vip_at_cidr_limit) { ?>
				<div class="alert alert-warning"><?= l7_t("Limites da Lista VIP atingidos."); ?></div>
				<?php } else { ?>
				<form method="post" action="layer7_exceptions.php#l7-vip-list" class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("Descricao"); ?></label>
						<div class="col-sm-6">
							<input type="text" name="vip_description" class="form-control" maxlength="64" required="required"
								placeholder="<?= l7_t("ex.: Director"); ?>" />
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label"><?= l7_t("IP ou CIDR"); ?></label>
						<div class="col-sm-6">
							<input type="text" name="vip_target" class="form-control" required="required"
								placeholder="192.168.1.50 / 192.168.10.0/24" />
							<p class="help-block"><?= l7_t("Um IPv4 ou CIDR por entrada."); ?></p>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-3 col-sm-9">
							<button type="submit" name="add_vip_entry" value="1" class="btn btn-success"><?= l7_t("Adicionar isento"); ?></button>
						</div>
					</div>
				</form>
				<?php } ?>
			</div>

			<div class="layer7-toolbar" style="margin-top:16px;">
				<form method="post" action="layer7_exceptions.php#l7-vip-list" style="display:inline;">
					<button type="submit" name="export_vip_list" value="1" class="btn btn-sm btn-info">
						<i class="fa fa-download"></i> <?= l7_t("Exportar Lista VIP"); ?>
					</button>
				</form>
				<form method="post" action="layer7_exceptions.php#l7-vip-list" enctype="multipart/form-data" class="form-inline" style="display:inline; margin-left:8px;">
					<input type="file" name="vip_import_file" accept=".json" style="display:inline-block; width:auto;" />
					<button type="submit" name="import_vip_list" value="1" class="btn btn-sm btn-warning"
						onclick="return confirm(<?= json_encode(l7_t("Importar substitui entradas directas da Lista VIP (grupos isentos sao limpos). Continuar?")); ?>);">
						<i class="fa fa-upload"></i> <?= l7_t("Importar Lista VIP"); ?>
					</button>
				</form>
			</div>
			</div>
			</div>

			<p class="layer7-lead"><?= l7_t("Excecoes sao avaliadas antes das politicas e ajudam a preservar trafego de gestao, redes internas e casos especiais durante os testes."); ?></p>

		<div class="layer7-admin-block" id="l7-exceptions">
			<div class="layer7-admin-block__header"><?= l7_t("Excecoes atuais"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="help-block"><?= l7_t("Prioridade maior = regra avaliada primeiro."); ?></p>
			<?php if (count($exceptions) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma excecao cadastrada no momento."); ?></div>
			<?php } else { ?>
			<div class="layer7-form-card">
			<form method="post" action="layer7_exceptions.php#l7-exceptions">
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= l7_t("Ativa"); ?></th>
								<th><?= l7_t("Prioridade"); ?></th>
								<th><?= l7_t("Acao"); ?></th>
								<th><code>id</code></th>
								<th><?= l7_t("Alvo"); ?></th>
								<th><?= l7_t("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($exceptions as $i => $exception) {
							$eid = isset($exception["id"]) ? (string)$exception["id"] : "";
							$action = isset($exception["action"]) ? (string)$exception["action"] : "";
							$priority = isset($exception["priority"]) ? (int)$exception["priority"] : 0;
							$enabled = !empty($exception["enabled"]);
							$target = layer7_exc_target_summary($exception);
						?>
							<tr>
								<td><input type="checkbox" name="eon[<?= (int)$i; ?>]" value="1" <?= $enabled ? 'checked="checked"' : ''; ?> /></td>
								<td><?= htmlspecialchars((string)$priority); ?></td>
								<td><span class="label label-default"><?= htmlspecialchars($action); ?></span>
								<?php if (layer7_is_managed_vip_exception($exception)) { ?>
									<span class="label label-info" title="<?= l7_t("Gerida pelos Perfis rapidos"); ?>"><?= l7_t("Perfis rapidos"); ?></span>
								<?php } ?>
								</td>
								<td><code><?= htmlspecialchars($eid); ?></code></td>
								<td class="small"><?= htmlspecialchars($target); ?></td>
								<td class="layer7-table-actions">
									<a href="layer7_exceptions.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= l7_t("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="layer7-toolbar">
					<button type="submit" name="save_exceptions" value="1" class="btn btn-primary"><?= l7_t("Guardar estado das excecoes"); ?></button>
				</div>
			</form>
			</div>

			<div class="layer7-callout layer7-danger-zone">
				<form method="post" action="layer7_exceptions.php#l7-exceptions" class="form-inline layer7-inline-form"
					onsubmit='return confirm(<?= json_encode(l7_t("Remover esta excecao do JSON?")); ?>);'>
					<div class="form-group">
						<label class="control-label" for="delete_exception_index"><?= l7_t("Remover excecao"); ?></label>
						<select id="delete_exception_index" name="delete_exception_index" class="form-control">
							<?php foreach ($exceptions as $i => $exception) {
								$eid = isset($exception["id"]) ? (string)$exception["id"] : ("#" . $i);
								$label = $eid . " - " . layer7_exc_target_summary($exception);
							?>
							<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
							<?php } ?>
						</select>
						<button type="submit" name="delete_exception" value="1" class="btn btn-danger"><?= l7_t("Remover"); ?></button>
					</div>
				</form>
			</div>
			<?php } ?>
		</div>
		</div>

		<?php if ($edit_ex !== null && $edit_ex_idx !== null) {
			$edit_id = isset($edit_ex["id"]) ? (string)$edit_ex["id"] : "";
			$edit_hosts_val = "";
			if (!empty($edit_ex["hosts"]) && is_array($edit_ex["hosts"])) {
				$edit_hosts_val = implode("\n", $edit_ex["hosts"]);
			} elseif (!empty($edit_ex["host"])) {
				$edit_hosts_val = (string)$edit_ex["host"];
			}
			$edit_cidrs_val = "";
			if (!empty($edit_ex["cidrs"]) && is_array($edit_ex["cidrs"])) {
				$edit_cidrs_val = implode("\n", $edit_ex["cidrs"]);
			} elseif (!empty($edit_ex["cidr"])) {
				$edit_cidrs_val = (string)$edit_ex["cidr"];
			}
			$edit_priority = isset($edit_ex["priority"]) ? (int)$edit_ex["priority"] : 0;
			$edit_action = isset($edit_ex["action"]) ? (string)$edit_ex["action"] : "allow";
			if (!in_array($edit_action, array("allow", "block", "monitor", "tag"), true)) {
				$edit_action = "allow";
			}
			$edit_enabled = !empty($edit_ex["enabled"]);
			$edit_ex_ifaces_arr = array();
			if (isset($edit_ex["interfaces"]) && is_array($edit_ex["interfaces"])) {
				$edit_ex_ifaces_arr = $edit_ex["interfaces"];
			}
			$ee_ifaces = layer7_get_pfsense_interfaces();
		?>
		<div class="layer7-admin-block" id="l7-edit-exc">
			<div class="layer7-admin-block__header"><?= l7_t("Editar excecao"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Use excecoes para trafego de gestao, IPs criticos e redes que nao devem ser avaliadas pelas politicas gerais."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_exceptions.php" class="btn btn-default"><?= l7_t("Cancelar edicao"); ?></a>
			</div>

			<form method="post" action="layer7_exceptions.php#l7-edit-exc" class="form-horizontal">
				<input type="hidden" name="edit_exception_index" value="<?= (int)$edit_ex_idx; ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-9">
						<p class="form-control-static"><code><?= htmlspecialchars($edit_id !== "" ? $edit_id : "(vazio)"); ?></code></p>
						<p class="help-block"><?= l7_t("O id nao pode ser alterado pela GUI."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Hosts (IPv4)"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_hosts" class="form-control" rows="3" style="max-width:400px"><?= htmlspecialchars($edit_hosts_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16). Pode combinar com CIDRs."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_cidrs" class="form-control" rows="2" style="max-width:400px"><?= htmlspecialchars($edit_cidrs_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 16). Ex.: 192.168.0.0/24"); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_exc_ifaces_list', true);"><?= l7_t("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('edit_exc_ifaces_list', false);"><?= l7_t("Limpar"); ?></button>
						</div>
						<div id="edit_exc_ifaces_list">
						<?php foreach ($ee_ifaces as $ifc) {
							$chk = (in_array($ifc["real"], $edit_ex_ifaces_arr, true) ||
							    in_array($ifc["ifid"], $edit_ex_ifaces_arr, true))
							    ? 'checked="checked"' : '';
						?>
						<label class="checkbox-inline">
							<input type="checkbox" name="edit_exc_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" <?= $chk; ?> />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Nenhuma = aplica a todas."); ?></p>
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
							<option value="allow" <?= $edit_action === "allow" ? 'selected="selected"' : ''; ?>><?= l7_t("allow"); ?></option>
							<option value="block" <?= $edit_action === "block" ? 'selected="selected"' : ''; ?>><?= l7_t("block"); ?></option>
							<option value="monitor" <?= $edit_action === "monitor" ? 'selected="selected"' : ''; ?>><?= l7_t("monitor"); ?></option>
							<option value="tag" <?= $edit_action === "tag" ? 'selected="selected"' : ''; ?>><?= l7_t("tag"); ?></option>
						</select>
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
						<button type="submit" name="save_exception_edit" value="1" class="btn btn-primary"><?= l7_t("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
		</div>
		</div>
		<?php } ?>

		<div class="layer7-admin-block" id="l7-add-exc">
			<div class="layer7-admin-block__header"><?= l7_t("Adicionar excecao"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Cadastre aqui os alvos que devem fugir do fluxo padrao de classificacao, sem precisar editar o JSON manualmente."); ?></p>
			<?php if ($exc_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 16 excecoes atingido."); ?></div>
			<?php } else { ?>
			<?php $pf_ifaces_exc = layer7_get_pfsense_interfaces(); ?>
			<form method="post" action="layer7_exceptions.php#l7-add-exc" class="form-horizontal">

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_id" class="form-control" maxlength="80"
							pattern="[a-zA-Z0-9_-]+" required="required" placeholder="ex-mgmt-001" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Hosts (IPv4)"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="10.0.0.99&#10;10.0.0.100"></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16). Pode combinar com CIDRs."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_cidrs" class="form-control" rows="2" style="max-width:400px" placeholder="192.168.77.0/24"></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 16)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Interfaces"); ?></label>
					<div class="col-sm-9">
						<div class="l7-bulk-tools">
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_exc_ifaces_list', true);"><?= l7_t("Selecionar tudo"); ?></button>
							<button type="button" class="btn btn-xs btn-default" onclick="l7setChecks('new_exc_ifaces_list', false);"><?= l7_t("Limpar"); ?></button>
						</div>
						<div id="new_exc_ifaces_list">
						<?php foreach ($pf_ifaces_exc as $ifc) { ?>
						<label class="checkbox-inline">
							<input type="checkbox" name="new_exc_ifaces[]" value="<?= htmlspecialchars($ifc["ifid"]); ?>" />
							<?= htmlspecialchars($ifc["descr"]); ?> <span class="text-muted">(<?= htmlspecialchars($ifc["real"]); ?>)</span>
						</label>
						<?php } ?>
						</div>
						<p class="help-block"><?= l7_t("Nenhuma = aplica a todas."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="new_priority" class="form-control" value="500" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="new_action" class="form-control">
							<option value="allow" selected="selected"><?= l7_t("allow"); ?></option>
							<option value="block"><?= l7_t("block"); ?></option>
							<option value="monitor"><?= l7_t("monitor"); ?></option>
							<option value="tag"><?= l7_t("tag"); ?></option>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_enabled" value="1" checked="checked" />
							<?= l7_t("Criar excecao ja habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="add_exception" value="1" class="btn btn-success"><?= l7_t("Adicionar excecao"); ?></button>
					</div>
				</div>
			</form>
			<?php } ?>

			<p class="layer7-muted-note small"><?= l7_t("Para alterar o id de uma excecao existente, edite /usr/local/etc/layer7.json diretamente."); ?></p>
		</div>
		</div>
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
</script>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
