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
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token invalido - atualize a pagina.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$ok = true;

		if (count($exceptions) >= 16) {
			$input_errors[] = gettext("Limite de 16 excecoes.");
			$ok = false;
		}

		$eid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($eid)) {
			$input_errors[] = gettext("ID invalido (letras, numeros, _ e -; max. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($exceptions as $existing_exception) {
				if (isset($existing_exception["id"]) && (string)$existing_exception["id"] === $eid) {
					$input_errors[] = gettext("Ja existe uma excecao com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$host = trim($_POST["new_host"] ?? "");
		$cidr = trim($_POST["new_cidr"] ?? "");
		if ($ok && $host !== "" && $cidr !== "") {
			$input_errors[] = gettext("Indique apenas host ou CIDR, nao ambos.");
			$ok = false;
		}
		if ($ok && $host === "" && $cidr === "") {
			$input_errors[] = gettext("Indique host (IPv4) ou CIDR (ex.: 192.168.0.0/24).");
			$ok = false;
		}
		if ($ok && $host !== "" && !layer7_ipv4_valid($host)) {
			$input_errors[] = gettext("Host: IPv4 invalido (ex.: 10.0.0.1).");
			$ok = false;
		}
		if ($ok && $cidr !== "" && !layer7_cidr_valid($cidr)) {
			$input_errors[] = gettext("CIDR invalido (ex.: 192.168.0.0/24).");
			$ok = false;
		}

		$pri = (int)($_POST["new_priority"] ?? 500);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = gettext("Prioridade invalida (0-99999).");
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
			if ($host !== "") {
				$rule["host"] = $host;
			} else {
				$rule["cidr"] = $cidr;
			}
			$exceptions[] = $rule;
			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Excecao adicionada.");
			}
		}
		unset($exceptions);
	}
}

if ($_POST["save_exceptions"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token invalido - atualize a pagina.");
	} else {
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
			layer7_csrf_rotate();
			layer7_signal_reload();
			$savemsg = gettext("Excecoes atualizadas.");
		}
	}
}

if ($_POST["delete_exception"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token invalido - atualize a pagina.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["delete_exception_index"] ?? -1);
		$count = count($exceptions);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = gettext("Indice de excecao invalido.");
		} else {
			array_splice($exceptions, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Excecao removida.");
			}
		}
		unset($exceptions);
	}
}

if ($_POST["save_exception_edit"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token invalido - atualize a pagina.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["edit_exception_index"] ?? -1);
		$count = count($exceptions);
		if ($idx < 0 || $idx >= $count) {
			$input_errors[] = gettext("Indice de excecao invalido.");
		} else {
			$layer7_exception_edit_retry = $idx;
			$orig = $exceptions[$idx];
			$eid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$host = trim($_POST["edit_host"] ?? "");
			$cidr = trim($_POST["edit_cidr"] ?? "");
			if ($ok && $host !== "" && $cidr !== "") {
				$input_errors[] = gettext("Indique apenas host ou CIDR, nao ambos.");
				$ok = false;
			}
			if ($ok && $host === "" && $cidr === "") {
				$input_errors[] = gettext("Indique host (IPv4) ou CIDR (ex.: 192.168.0.0/24).");
				$ok = false;
			}
			if ($ok && $host !== "" && !layer7_ipv4_valid($host)) {
				$input_errors[] = gettext("Host: IPv4 invalido (ex.: 10.0.0.1).");
				$ok = false;
			}
			if ($ok && $cidr !== "" && !layer7_cidr_valid($cidr)) {
				$input_errors[] = gettext("CIDR invalido (ex.: 192.168.0.0/24).");
				$ok = false;
			}

			$pri = (int)($_POST["edit_priority"] ?? 500);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = gettext("Prioridade invalida (0-99999).");
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
				if ($host !== "") {
					$rule["host"] = $host;
				} else {
					$rule["cidr"] = $cidr;
				}
				$exceptions[$idx] = $rule;
				if (layer7_save_json($data)) {
					layer7_csrf_rotate();
					layer7_signal_reload();
					header("Location: layer7_exceptions.php");
					exit;
				}
				$input_errors[] = gettext("Nao foi possivel gravar a configuracao.");
			}
		}
		unset($exceptions);
	}
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

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Exceptions"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - excecoes"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("exceptions"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= gettext("Excecoes sao avaliadas antes das politicas e ajudam a preservar trafego de gestao, redes internas e casos especiais durante os testes."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Excecoes atuais"); ?></h3>
			<p class="help-block"><?= gettext("Prioridade maior = regra avaliada primeiro."); ?></p>
			<?php if (count($exceptions) === 0) { ?>
			<div class="alert alert-info"><?= gettext("Nenhuma excecao cadastrada no momento."); ?></div>
			<?php } else { ?>
			<form method="post">
				<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><?= gettext("Ativa"); ?></th>
								<th><?= gettext("Prioridade"); ?></th>
								<th><?= gettext("Acao"); ?></th>
								<th><code>id</code></th>
								<th><?= gettext("Alvo"); ?></th>
								<th><?= gettext("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($exceptions as $i => $exception) {
							$eid = isset($exception["id"]) ? (string)$exception["id"] : "";
							$action = isset($exception["action"]) ? (string)$exception["action"] : "";
							$priority = isset($exception["priority"]) ? (int)$exception["priority"] : 0;
							$enabled = !empty($exception["enabled"]);
							if (!empty($exception["host"])) {
								$target = "host " . $exception["host"];
							} elseif (!empty($exception["cidr"])) {
								$target = "cidr " . $exception["cidr"];
							} else {
								$target = gettext("Nao definido");
							}
						?>
							<tr>
								<td><input type="checkbox" name="eon[<?= (int)$i; ?>]" value="1" <?= $enabled ? 'checked="checked"' : ''; ?> /></td>
								<td><?= htmlspecialchars((string)$priority); ?></td>
								<td><span class="label label-default"><?= htmlspecialchars($action); ?></span></td>
								<td><code><?= htmlspecialchars($eid); ?></code></td>
								<td><code><?= htmlspecialchars($target); ?></code></td>
								<td class="layer7-table-actions">
									<a href="layer7_exceptions.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= gettext("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="layer7-toolbar">
					<button type="submit" name="save_exceptions" value="1" class="btn btn-primary"><?= gettext("Guardar estado das excecoes"); ?></button>
				</div>
			</form>

			<form method="post" class="form-inline layer7-inline-form"
				onsubmit='return confirm(<?= json_encode(gettext("Remover esta excecao do JSON?")); ?>);'>
				<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
				<div class="form-group">
					<label class="control-label" for="delete_exception_index"><?= gettext("Remover excecao"); ?></label>
					<select id="delete_exception_index" name="delete_exception_index" class="form-control">
						<?php foreach ($exceptions as $i => $exception) {
							$eid = isset($exception["id"]) ? (string)$exception["id"] : ("#" . $i);
							if (!empty($exception["host"])) {
								$target = "host " . $exception["host"];
							} elseif (!empty($exception["cidr"])) {
								$target = "cidr " . $exception["cidr"];
							} else {
								$target = "?";
							}
							$label = $eid . " - " . $target;
						?>
						<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
						<?php } ?>
					</select>
					<button type="submit" name="delete_exception" value="1" class="btn btn-danger"><?= gettext("Remover"); ?></button>
				</div>
			</form>
			<?php } ?>
		</div>

		<?php if ($edit_ex !== null && $edit_ex_idx !== null) {
			$edit_id = isset($edit_ex["id"]) ? (string)$edit_ex["id"] : "";
			$edit_host = !empty($edit_ex["host"]) ? (string)$edit_ex["host"] : "";
			$edit_cidr = !empty($edit_ex["cidr"]) ? (string)$edit_ex["cidr"] : "";
			$edit_priority = isset($edit_ex["priority"]) ? (int)$edit_ex["priority"] : 0;
			$edit_action = isset($edit_ex["action"]) ? (string)$edit_ex["action"] : "allow";
			if (!in_array($edit_action, array("allow", "block", "monitor", "tag"), true)) {
				$edit_action = "allow";
			}
			$edit_enabled = !empty($edit_ex["enabled"]);
		?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Editar excecao"); ?></h3>
			<p class="layer7-lead"><?= gettext("Use excecoes para trafego de gestao, IPs criticos e redes que nao devem ser avaliadas pelas politicas gerais."); ?></p>
			<div class="layer7-toolbar">
				<a href="layer7_exceptions.php" class="btn btn-default"><?= gettext("Cancelar edicao"); ?></a>
			</div>

			<form method="post" class="form-horizontal">
				<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
				<input type="hidden" name="edit_exception_index" value="<?= (int)$edit_ex_idx; ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-9">
						<p class="form-control-static"><code><?= htmlspecialchars($edit_id !== "" ? $edit_id : "(vazio)"); ?></code></p>
						<p class="help-block"><?= gettext("O id nao pode ser alterado pela GUI."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Host (IPv4)"); ?></label>
					<div class="col-sm-4">
						<input type="text" name="edit_host" class="form-control" value="<?= htmlspecialchars($edit_host); ?>" />
						<p class="help-block"><?= gettext("Preencha host ou CIDR, nunca ambos."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("CIDR"); ?></label>
					<div class="col-sm-4">
						<input type="text" name="edit_cidr" class="form-control" value="<?= htmlspecialchars($edit_cidr); ?>" />
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
							<option value="allow" <?= $edit_action === "allow" ? 'selected="selected"' : ''; ?>><?= gettext("allow"); ?></option>
							<option value="block" <?= $edit_action === "block" ? 'selected="selected"' : ''; ?>><?= gettext("block"); ?></option>
							<option value="monitor" <?= $edit_action === "monitor" ? 'selected="selected"' : ''; ?>><?= gettext("monitor"); ?></option>
							<option value="tag" <?= $edit_action === "tag" ? 'selected="selected"' : ''; ?>><?= gettext("tag"); ?></option>
						</select>
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
						<button type="submit" name="save_exception_edit" value="1" class="btn btn-primary"><?= gettext("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Adicionar excecao"); ?></h3>
			<p class="layer7-lead"><?= gettext("Cadastre aqui os alvos que devem fugir do fluxo padrao de classificacao, sem precisar editar o JSON manualmente."); ?></p>
			<?php if ($exc_limit) { ?>
			<div class="alert alert-warning"><?= gettext("Limite de 16 excecoes atingido."); ?></div>
			<?php } else { ?>
			<form method="post" class="form-horizontal">
				<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_id" class="form-control" maxlength="80"
							pattern="[a-zA-Z0-9_-]+" required="required" placeholder="ex-mgmt-001" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Host (IPv4)"); ?></label>
					<div class="col-sm-4">
						<input type="text" name="new_host" class="form-control" placeholder="10.0.0.99" />
						<p class="help-block"><?= gettext("Ou preencha o CIDR abaixo."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("CIDR"); ?></label>
					<div class="col-sm-4">
						<input type="text" name="new_cidr" class="form-control" placeholder="192.168.77.0/24" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Prioridade"); ?></label>
					<div class="col-sm-3">
						<input type="number" name="new_priority" class="form-control" value="500" min="0" max="99999" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Acao"); ?></label>
					<div class="col-sm-4">
						<select name="new_action" class="form-control">
							<option value="allow" selected="selected"><?= gettext("allow"); ?></option>
							<option value="block"><?= gettext("block"); ?></option>
							<option value="monitor"><?= gettext("monitor"); ?></option>
							<option value="tag"><?= gettext("tag"); ?></option>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Ativa"); ?></label>
					<div class="col-sm-9">
						<label class="checkbox-inline">
							<input type="checkbox" name="new_enabled" value="1" checked="checked" />
							<?= gettext("Criar excecao ja habilitada"); ?>
						</label>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="add_exception" value="1" class="btn btn-success"><?= gettext("Adicionar excecao"); ?></button>
					</div>
				</div>
			</form>
			<?php } ?>

			<p class="layer7-muted-note small"><?= gettext("Para alterar o id de uma excecao existente, edite /usr/local/etc/layer7.json diretamente."); ?></p>
		</div>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
