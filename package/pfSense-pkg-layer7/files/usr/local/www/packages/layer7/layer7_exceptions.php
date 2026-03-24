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
				$real = convert_friendly_interface_to_real_interface_name($ifid);
				$new_exc_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
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
				layer7_signal_reload();
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
			layer7_signal_reload();
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
				layer7_signal_reload();
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
					$real = convert_friendly_interface_to_real_interface_name($ifid);
					$edit_exc_ifaces[] = ($real && $real !== $ifid) ? $real : $ifid;
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
					layer7_signal_reload();
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
		<?php layer7_render_tabs("exceptions"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= l7_t("Excecoes sao avaliadas antes das politicas e ajudam a preservar trafego de gestao, redes internas e casos especiais durante os testes."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Excecoes atuais"); ?></h3>
			<p class="help-block"><?= l7_t("Prioridade maior = regra avaliada primeiro."); ?></p>
			<?php if (count($exceptions) === 0) { ?>
			<div class="alert alert-info"><?= l7_t("Nenhuma excecao cadastrada no momento."); ?></div>
			<?php } else { ?>
			<form method="post">
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
								<td><span class="label label-default"><?= htmlspecialchars($action); ?></span></td>
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

			<form method="post" class="form-inline layer7-inline-form"
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
			<?php } ?>
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
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Editar excecao"); ?></h3>
			<p class="layer7-lead"><?= l7_t("Use excecoes para trafego de gestao, IPs criticos e redes que nao devem ser avaliadas pelas politicas gerais."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_exceptions.php" class="btn btn-default"><?= l7_t("Cancelar edicao"); ?></a>
			</div>

			<form method="post" class="form-horizontal">
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
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 8). Pode combinar com CIDRs."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-9">
						<textarea name="edit_cidrs" class="form-control" rows="2" style="max-width:400px"><?= htmlspecialchars($edit_cidrs_val); ?></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8). Ex.: 192.168.0.0/24"); ?></p>
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
							$chk = in_array($ifc["real"], $edit_ex_ifaces_arr, true) ? 'checked="checked"' : '';
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
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Adicionar excecao"); ?></h3>
			<p class="layer7-lead"><?= l7_t("Cadastre aqui os alvos que devem fugir do fluxo padrao de classificacao, sem precisar editar o JSON manualmente."); ?></p>
			<?php if ($exc_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 16 excecoes atingido."); ?></div>
			<?php } else { ?>
			<?php $pf_ifaces_exc = layer7_get_pfsense_interfaces(); ?>
			<form method="post" class="form-horizontal">

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
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 8). Pode combinar com CIDRs."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_cidrs" class="form-control" rows="2" style="max-width:400px" placeholder="192.168.77.0/24"></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8)."); ?></p>
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
