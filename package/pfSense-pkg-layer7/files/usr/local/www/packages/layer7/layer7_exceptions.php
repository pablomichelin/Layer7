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
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$ok = true;

		if (count($exceptions) >= 16) {
			$input_errors[] = gettext("Limite de 16 exceções.");
			$ok = false;
		}

		$eid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($eid)) {
			$input_errors[] = gettext("ID inválido (letras, números, _ e -; máx. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($exceptions as $ex) {
				if (isset($ex["id"]) && (string)$ex["id"] === $eid) {
					$input_errors[] = gettext("Já existe uma exceção com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$host = trim($_POST["new_host"] ?? "");
		$cidr = trim($_POST["new_cidr"] ?? "");
		if ($ok && $host !== "" && $cidr !== "") {
			$input_errors[] = gettext("Indique apenas host ou CIDR, não ambos.");
			$ok = false;
		}
		if ($ok && $host === "" && $cidr === "") {
			$input_errors[] = gettext("Indique host (IPv4) ou CIDR (ex.: 192.168.0.0/24).");
			$ok = false;
		}
		if ($ok && $host !== "" && !layer7_ipv4_valid($host)) {
			$input_errors[] = gettext("Host: IPv4 inválido (ex.: 10.0.0.1).");
			$ok = false;
		}
		if ($ok && $cidr !== "" && !layer7_cidr_valid($cidr)) {
			$input_errors[] = gettext("CIDR inválido (ex.: 192.168.0.0/24).");
			$ok = false;
		}

		$pri = (int)($_POST["new_priority"] ?? 500);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = gettext("Prioridade inválida (0–99999).");
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
				$savemsg = gettext("Exceção adicionada.");
			}
		}
		unset($exceptions);
	}
}

if ($_POST["save_exceptions"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$n = count($exceptions);
		for ($i = 0; $i < $n; $i++) {
			$exceptions[$i]["enabled"] = isset($_POST["eon"][$i]);
		}
		unset($exceptions);
		if (layer7_save_json($data)) {
			layer7_csrf_rotate();
			layer7_signal_reload();
			$savemsg = gettext("Exceções atualizadas.");
		}
	}
}

if ($_POST["delete_exception"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["delete_exception_index"] ?? -1);
		$n = count($exceptions);
		if ($idx < 0 || $idx >= $n) {
			$input_errors[] = gettext("Índice de exceção inválido.");
		} else {
			array_splice($exceptions, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Exceção removida.");
			}
		}
		unset($exceptions);
	}
}

if ($_POST["save_exception_edit"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["exceptions"]) || !is_array($data["layer7"]["exceptions"])) {
			$data["layer7"]["exceptions"] = array();
		}
		$exceptions = &$data["layer7"]["exceptions"];
		$idx = (int)($_POST["edit_exception_index"] ?? -1);
		$n = count($exceptions);
		if ($idx < 0 || $idx >= $n) {
			$input_errors[] = gettext("Índice de exceção inválido.");
		} else {
			$layer7_exception_edit_retry = $idx;
			$orig = $exceptions[$idx];
			$eid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$host = trim($_POST["edit_host"] ?? "");
			$cidr = trim($_POST["edit_cidr"] ?? "");
			if ($ok && $host !== "" && $cidr !== "") {
				$input_errors[] = gettext("Indique apenas host ou CIDR, não ambos.");
				$ok = false;
			}
			if ($ok && $host === "" && $cidr === "") {
				$input_errors[] = gettext("Indique host (IPv4) ou CIDR (ex.: 192.168.0.0/24).");
				$ok = false;
			}
			if ($ok && $host !== "" && !layer7_ipv4_valid($host)) {
				$input_errors[] = gettext("Host: IPv4 inválido (ex.: 10.0.0.1).");
				$ok = false;
			}
			if ($ok && $cidr !== "" && !layer7_cidr_valid($cidr)) {
				$input_errors[] = gettext("CIDR inválido (ex.: 192.168.0.0/24).");
				$ok = false;
			}

			$pri = (int)($_POST["edit_priority"] ?? 500);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = gettext("Prioridade inválida (0–99999).");
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
				$input_errors[] = gettext("Não foi possível gravar a configuração.");
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
	$ei = (int)$_GET["edit"];
	if ($ei >= 0 && $ei < count($exceptions)) {
		$edit_ex_idx = $ei;
		$edit_ex = $exceptions[$ei];
	}
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Exceptions"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — exceções"); ?></h2>
	</div>
	<div class="panel-body">
		<p><a href="layer7_status.php"><?= gettext("← Estado"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_settings.php"><?= gettext("Definições"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_policies.php"><?= gettext("Políticas"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_events.php"><?= gettext("Events"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_diagnostics.php"><?= gettext("Diagnostics"); ?></a></p>
		<p class="text-muted small"><?= gettext("Exceções aplicam-se antes das políticas (prioridade maior = primeiro)."); ?></p>
		<?php if (count($exceptions) === 0) { ?>
		<p class="text-muted"><?= gettext("Nenhuma exceção. Adicione abaixo."); ?></p>
		<?php } else { ?>
		<form method="post">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?= gettext("Ativa"); ?></th>
						<th><?= gettext("Prioridade"); ?></th>
						<th><?= gettext("Ação"); ?></th>
						<th><code>id</code></th>
						<th><?= gettext("Alvo"); ?></th>
						<th><?= gettext("Ações"); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($exceptions as $i => $ex) {
					$eid = isset($ex["id"]) ? $ex["id"] : "";
					$act = isset($ex["action"]) ? $ex["action"] : "";
					$pr = isset($ex["priority"]) ? (int)$ex["priority"] : 0;
					$on = !empty($ex["enabled"]);
					$target = "";
					if (!empty($ex["host"])) {
						$target = "host " . $ex["host"];
					} elseif (!empty($ex["cidr"])) {
						$target = "cidr " . $ex["cidr"];
					} else {
						$target = "—";
					}
				?>
					<tr>
						<td><input type="checkbox" name="eon[<?= (int)$i ?>]" value="1" <?= $on ? "checked=\"checked\"" : ""; ?> /></td>
						<td><?= htmlspecialchars((string)$pr); ?></td>
						<td><code><?= htmlspecialchars($act); ?></code></td>
						<td class="small"><?= htmlspecialchars($eid); ?></td>
						<td class="small"><code><?= htmlspecialchars($target); ?></code></td>
						<td><a href="layer7_exceptions.php?edit=<?= (int)$i ?>" class="btn btn-xs btn-info"><?= gettext("Editar"); ?></a></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			</div>
			<button type="submit" name="save_exceptions" value="1" class="btn btn-primary"><?= gettext("Guardar exceções"); ?></button>
		</form>
		<form method="post" class="form-inline" style="margin-top:12px;"
			onsubmit='return confirm(<?= json_encode(gettext("Remover esta exceção do JSON?")); ?>);'>
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<label class="control-label" style="margin-right:8px;"><?= gettext("Remover exceção"); ?></label>
			<select name="delete_exception_index" class="form-control" style="display:inline-block; max-width:420px;">
				<?php foreach ($exceptions as $i => $ex) {
					$eid = isset($ex["id"]) ? $ex["id"] : ("#" . $i);
					if (!empty($ex["host"])) {
						$tgt = "host " . $ex["host"];
					} elseif (!empty($ex["cidr"])) {
						$tgt = "cidr " . $ex["cidr"];
					} else {
						$tgt = "?";
					}
					$lab = $eid . " — " . $tgt;
				?>
				<option value="<?= (int)$i ?>"><?= htmlspecialchars($lab); ?></option>
				<?php } ?>
			</select>
			<button type="submit" name="delete_exception" value="1" class="btn btn-danger" style="margin-left:8px;"><?= gettext("Remover"); ?></button>
		</form>
		<hr />
		<?php } ?>

		<?php if ($edit_ex !== null && $edit_ex_idx !== null) {
			$xx = $edit_ex;
			$xid = isset($xx["id"]) ? (string)$xx["id"] : "";
			$xhost = !empty($xx["host"]) ? (string)$xx["host"] : "";
			$xcidr = !empty($xx["cidr"]) ? (string)$xx["cidr"] : "";
			$xpr = isset($xx["priority"]) ? (int)$xx["priority"] : 0;
			$xact = isset($xx["action"]) ? (string)$xx["action"] : "allow";
			if (!in_array($xact, array("allow", "block", "monitor", "tag"), true)) {
				$xact = "allow";
			}
			$xon = !empty($xx["enabled"]);
		?>
		<h3><?= gettext("Editar exceção"); ?></h3>
		<p><a href="layer7_exceptions.php" class="btn btn-default btn-sm"><?= gettext("Cancelar edição"); ?></a></p>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<input type="hidden" name="edit_exception_index" value="<?= (int)$edit_ex_idx; ?>" />
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>id</code></label>
				<div class="col-sm-10">
					<p class="form-control-static"><code><?= htmlspecialchars($xid !== "" ? $xid : "(" . gettext("vazio") . ")"); ?></code>
					<span class="help-block"><?= gettext("O id não pode ser alterado pela GUI."); ?></span></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Host (IPv4)"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="edit_host" class="form-control" style="max-width:200px;" value="<?= htmlspecialchars($xhost); ?>" />
					<span class="help-block"><?= gettext("Ou CIDR abaixo (não ambos)."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("CIDR"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="edit_cidr" class="form-control" style="max-width:200px;" value="<?= htmlspecialchars($xcidr); ?>" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Prioridade"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="edit_priority" class="form-control" style="max-width:120px;" value="<?= (int)$xpr; ?>" min="0" max="99999" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ação"); ?></label>
				<div class="col-sm-10">
					<select name="edit_action" class="form-control" style="max-width:200px;">
						<option value="allow" <?= $xact === "allow" ? "selected=\"selected\"" : ""; ?>><?= gettext("allow"); ?></option>
						<option value="block" <?= $xact === "block" ? "selected=\"selected\"" : ""; ?>><?= gettext("block"); ?></option>
						<option value="monitor" <?= $xact === "monitor" ? "selected=\"selected\"" : ""; ?>><?= gettext("monitor"); ?></option>
						<option value="tag" <?= $xact === "tag" ? "selected=\"selected\"" : ""; ?>><?= gettext("tag"); ?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ativa"); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="edit_enabled" value="1" <?= $xon ? "checked=\"checked\"" : ""; ?> />
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="save_exception_edit" value="1" class="btn btn-primary"><?= gettext("Guardar alterações"); ?></button>
				</div>
			</div>
		</form>
		<hr />
		<?php } ?>

		<h3><?= gettext("Adicionar exceção"); ?></h3>
		<?php if ($exc_limit) { ?>
		<p class="text-warning"><?= gettext("Limite de 16 exceções atingido."); ?></p>
		<?php } else { ?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>id</code></label>
				<div class="col-sm-10">
					<input type="text" name="new_id" class="form-control" style="max-width:320px;" maxlength="80"
						pattern="[a-zA-Z0-9_-]+" required="required"
						placeholder="ex-mgmt-001" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Host (IPv4)"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="new_host" class="form-control" style="max-width:200px;"
						placeholder="10.0.0.99" />
					<span class="help-block"><?= gettext("Ou preencha CIDR abaixo (não ambos)."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("CIDR"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="new_cidr" class="form-control" style="max-width:200px;"
						placeholder="192.168.77.0/24" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Prioridade"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="new_priority" class="form-control" style="max-width:120px;" value="500" min="0" max="99999" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ação"); ?></label>
				<div class="col-sm-10">
					<select name="new_action" class="form-control" style="max-width:200px;">
						<option value="allow" selected="selected"><?= gettext("allow"); ?></option>
						<option value="block"><?= gettext("block"); ?></option>
						<option value="monitor"><?= gettext("monitor"); ?></option>
						<option value="tag"><?= gettext("tag"); ?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ativa"); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="new_enabled" value="1" checked="checked" />
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="add_exception" value="1" class="btn btn-success"><?= gettext("Adicionar exceção"); ?></button>
				</div>
			</div>
		</form>
		<?php } ?>
		<p class="text-muted small"><?= gettext("Alterar id da exceção: edite /usr/local/etc/layer7.json."); ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>
