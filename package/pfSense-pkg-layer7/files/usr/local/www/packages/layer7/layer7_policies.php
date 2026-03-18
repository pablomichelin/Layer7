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

if ($_POST["add_policy"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$ok = true;

		if (count($policies) >= 24) {
			$input_errors[] = gettext("Limite de 24 políticas.");
			$ok = false;
		}

		$pid = trim($_POST["new_id"] ?? "");
		if ($ok && !layer7_policy_id_valid($pid)) {
			$input_errors[] = gettext("ID inválido (letras, números, _ e -; máx. 80).");
			$ok = false;
		}
		if ($ok) {
			foreach ($policies as $ex) {
				if (isset($ex["id"]) && (string)$ex["id"] === $pid) {
					$input_errors[] = gettext("Já existe uma política com esse ID.");
					$ok = false;
					break;
				}
			}
		}

		$name = trim($_POST["new_name"] ?? "");
		if ($ok && strlen($name) > 160) {
			$input_errors[] = gettext("Nome demasiado longo (máx. 160).");
			$ok = false;
		}

		$pri = (int)($_POST["new_priority"] ?? 50);
		if ($ok && ($pri < 0 || $pri > 99999)) {
			$input_errors[] = gettext("Prioridade inválida (0–99999).");
			$ok = false;
		}

		$act = $_POST["new_action"] ?? "monitor";
		if (!in_array($act, array("monitor", "allow", "block", "tag"), true)) {
			$act = "monitor";
		}

		$apps = layer7_split_csv_tokens($_POST["new_ndpi_apps"] ?? "", 12, 64);
		$cats = layer7_split_csv_tokens($_POST["new_ndpi_category"] ?? "", 8, 64);
		if ($ok && ($apps === null || $cats === null)) {
			$input_errors[] = gettext("App ou categoria: cada valor máx. 64 caracteres.");
			$ok = false;
		}
		if ($ok && $apps !== null && $cats !== null &&
		    ($act === "block" || $act === "tag") &&
		    count($apps) + count($cats) === 0) {
			$input_errors[] = gettext("Para block ou tag, indique app nDPI e/ou categoria.");
			$ok = false;
		}

		$tag_table = trim($_POST["new_tag_table"] ?? "");
		if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
			$input_errors[] = gettext("Tabela PF (tag): apenas A–Z, a–z, 0–9, _ (1–63 caracteres).");
			$ok = false;
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
			if (count($apps) > 0) {
				$rule["match"]["ndpi_app"] = $apps;
			}
			if (count($cats) > 0) {
				$rule["match"]["ndpi_category"] = $cats;
			}
			if ($act === "tag") {
				$rule["tag_table"] = $tag_table;
			}
			$policies[] = $rule;
			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Política adicionada.");
			}
		}
		unset($policies);
	}
}

if ($_POST["save_policies"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$n = count($policies);
		for ($i = 0; $i < $n; $i++) {
			$policies[$i]["enabled"] = isset($_POST["pon"][$i]);
		}
		unset($policies);
		if (layer7_save_json($data)) {
			layer7_csrf_rotate();
			layer7_signal_reload();
			$savemsg = gettext("Políticas atualizadas.");
		}
	}
}

if ($_POST["delete_policy"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$idx = (int)($_POST["delete_policy_index"] ?? -1);
		$n = count($policies);
		if ($idx < 0 || $idx >= $n) {
			$input_errors[] = gettext("Índice de política inválido.");
		} else {
			array_splice($policies, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_csrf_rotate();
				layer7_signal_reload();
				$savemsg = gettext("Política removida.");
			}
		}
		unset($policies);
	}
}

if ($_POST["save_policy_edit"] ?? false) {
	if (!layer7_csrf_verify_post()) {
		$input_errors[] = gettext("Token inválido — atualize a página.");
	} else {
		$data = layer7_load_or_default();
		if (!isset($data["layer7"]["policies"]) || !is_array($data["layer7"]["policies"])) {
			$data["layer7"]["policies"] = array();
		}
		$policies = &$data["layer7"]["policies"];
		$idx = (int)($_POST["edit_policy_index"] ?? -1);
		$n = count($policies);
		if ($idx < 0 || $idx >= $n) {
			$input_errors[] = gettext("Índice de política inválido.");
		} else {
			$layer7_policy_edit_retry = $idx;
			$orig = $policies[$idx];
			$pid = isset($orig["id"]) ? (string)$orig["id"] : "";

			$ok = true;
			$name = trim($_POST["edit_name"] ?? "");
			if ($ok && strlen($name) > 160) {
				$input_errors[] = gettext("Nome demasiado longo (máx. 160).");
				$ok = false;
			}

			$pri = (int)($_POST["edit_priority"] ?? 50);
			if ($ok && ($pri < 0 || $pri > 99999)) {
				$input_errors[] = gettext("Prioridade inválida (0–99999).");
				$ok = false;
			}

			$act = $_POST["edit_action"] ?? "monitor";
			if (!in_array($act, array("monitor", "allow", "block", "tag"), true)) {
				$act = "monitor";
			}

			$apps = layer7_split_csv_tokens($_POST["edit_ndpi_apps"] ?? "", 12, 64);
			$cats = layer7_split_csv_tokens($_POST["edit_ndpi_category"] ?? "", 8, 64);
			if ($ok && ($apps === null || $cats === null)) {
				$input_errors[] = gettext("App ou categoria: cada valor máx. 64 caracteres.");
				$ok = false;
			}
			if ($ok && $apps !== null && $cats !== null &&
			    ($act === "block" || $act === "tag") &&
			    count($apps) + count($cats) === 0) {
				$input_errors[] = gettext("Para block ou tag, indique app nDPI e/ou categoria.");
				$ok = false;
			}

			$tag_table = trim($_POST["edit_tag_table"] ?? "");
			if ($ok && $act === "tag" && !layer7_pf_table_name_valid($tag_table)) {
				$input_errors[] = gettext("Tabela PF (tag): apenas A–Z, a–z, 0–9, _ (1–63 caracteres).");
				$ok = false;
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
				if (count($apps) > 0) {
					$rule["match"]["ndpi_app"] = $apps;
				}
				if (count($cats) > 0) {
					$rule["match"]["ndpi_category"] = $cats;
				}
				if ($act === "tag") {
					$rule["tag_table"] = $tag_table;
				}
				$policies[$idx] = $rule;
				if (layer7_save_json($data)) {
					layer7_csrf_rotate();
					layer7_signal_reload();
					header("Location: layer7_policies.php");
					exit;
				}
				$input_errors[] = gettext("Não foi possível gravar a configuração.");
			}
		}
		unset($policies);
	}
}

$data = layer7_load_or_default();
$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
	? $data["layer7"]["policies"] : array();
$at_limit = count($policies) >= 24;

$edit_idx = null;
$edit_policy = null;
if ($layer7_policy_edit_retry !== null && $layer7_policy_edit_retry >= 0 &&
    $layer7_policy_edit_retry < count($policies)) {
	$edit_idx = (int)$layer7_policy_edit_retry;
	$edit_policy = $policies[$edit_idx];
} elseif (isset($_GET["edit"]) && ctype_digit((string)$_GET["edit"])) {
	$ei = (int)$_GET["edit"];
	if ($ei >= 0 && $ei < count($policies)) {
		$edit_idx = $ei;
		$edit_policy = $policies[$ei];
	}
}

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Policies"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — políticas"); ?></h2>
	</div>
	<div class="panel-body">
		<p><a href="layer7_status.php"><?= gettext("← Estado"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_settings.php"><?= gettext("Definições"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_exceptions.php"><?= gettext("Exceções"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_events.php"><?= gettext("Events"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_diagnostics.php"><?= gettext("Diagnostics"); ?></a></p>
		<?php if (count($policies) === 0) { ?>
		<p class="text-muted"><?= gettext("Nenhuma política. Adicione abaixo ou copie layer7.json.sample."); ?></p>
		<?php } else { ?>
		<form method="post">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<div class="table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th><?= gettext("Ativa"); ?></th>
						<th><?= gettext("Prioridade"); ?></th>
						<th><?= gettext("Nome"); ?></th>
						<th><?= gettext("Ação"); ?></th>
						<th><code>id</code></th>
						<th><?= gettext("Ações"); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($policies as $i => $p) {
					$pid = isset($p["id"]) ? $p["id"] : "";
					$name = isset($p["name"]) ? $p["name"] : "";
					$act = isset($p["action"]) ? $p["action"] : "";
					$pr = isset($p["priority"]) ? (int)$p["priority"] : 0;
					$on = !empty($p["enabled"]);
				?>
					<tr>
						<td><input type="checkbox" name="pon[<?= (int)$i ?>]" value="1" <?= $on ? "checked=\"checked\"" : ""; ?> /></td>
						<td><?= htmlspecialchars((string)$pr); ?></td>
						<td><?= htmlspecialchars($name); ?></td>
						<td><code><?= htmlspecialchars($act); ?></code></td>
						<td class="small"><?= htmlspecialchars($pid); ?></td>
						<td><a href="layer7_policies.php?edit=<?= (int)$i ?>" class="btn btn-xs btn-info"><?= gettext("Editar"); ?></a></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			</div>
			<button type="submit" name="save_policies" value="1" class="btn btn-primary"><?= gettext("Guardar políticas"); ?></button>
		</form>
		<form method="post" class="form-inline" style="margin-top:12px;"
			onsubmit='return confirm(<?= json_encode(gettext("Remover esta política do JSON?")); ?>);'>
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<label class="control-label" style="margin-right:8px;"><?= gettext("Remover política"); ?></label>
			<select name="delete_policy_index" class="form-control" style="display:inline-block; max-width:360px;">
				<?php foreach ($policies as $i => $p) {
					$pid = isset($p["id"]) ? $p["id"] : ("#" . $i);
					$pname = isset($p["name"]) ? $p["name"] : "";
					$lab = $pid . ($pname !== "" ? " — " . $pname : "");
				?>
				<option value="<?= (int)$i ?>"><?= htmlspecialchars($lab); ?></option>
				<?php } ?>
			</select>
			<button type="submit" name="delete_policy" value="1" class="btn btn-danger" style="margin-left:8px;"><?= gettext("Remover"); ?></button>
		</form>
		<hr />
		<?php } ?>

		<?php if ($edit_policy !== null && $edit_idx !== null) {
			$ep = $edit_policy;
			$eid = isset($ep["id"]) ? (string)$ep["id"] : "";
			$en = isset($ep["name"]) ? (string)$ep["name"] : "";
			$epr = isset($ep["priority"]) ? (int)$ep["priority"] : 0;
			$eact = isset($ep["action"]) ? (string)$ep["action"] : "monitor";
			if (!in_array($eact, array("monitor", "allow", "block", "tag"), true)) {
				$eact = "monitor";
			}
			$eon = !empty($ep["enabled"]);
			$e_apps = "";
			if (isset($ep["match"]["ndpi_app"]) && is_array($ep["match"]["ndpi_app"])) {
				$e_apps = implode(", ", $ep["match"]["ndpi_app"]);
			}
			$e_cats = "";
			if (isset($ep["match"]["ndpi_category"]) && is_array($ep["match"]["ndpi_category"])) {
				$e_cats = implode(", ", $ep["match"]["ndpi_category"]);
			}
			$ett = isset($ep["tag_table"]) ? (string)$ep["tag_table"] : "";
		?>
		<h3><?= gettext("Editar política"); ?></h3>
		<p><a href="layer7_policies.php" class="btn btn-default btn-sm"><?= gettext("Cancelar edição"); ?></a></p>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<input type="hidden" name="edit_policy_index" value="<?= (int)$edit_idx; ?>" />
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>id</code></label>
				<div class="col-sm-10">
					<p class="form-control-static"><code><?= htmlspecialchars($eid !== "" ? $eid : "(" . gettext("vazio") . ")"); ?></code>
					<span class="help-block"><?= gettext("O id não pode ser alterado pela GUI."); ?></span></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Nome"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="edit_name" class="form-control" style="max-width:480px;" maxlength="160" value="<?= htmlspecialchars($en); ?>" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Prioridade"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="edit_priority" class="form-control" style="max-width:120px;" value="<?= (int)$epr; ?>" min="0" max="99999" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ação"); ?></label>
				<div class="col-sm-10">
					<select name="edit_action" class="form-control" style="max-width:200px;">
						<option value="monitor" <?= $eact === "monitor" ? "selected=\"selected\"" : ""; ?>><?= gettext("monitor"); ?></option>
						<option value="allow" <?= $eact === "allow" ? "selected=\"selected\"" : ""; ?>><?= gettext("allow"); ?></option>
						<option value="block" <?= $eact === "block" ? "selected=\"selected\"" : ""; ?>><?= gettext("block"); ?></option>
						<option value="tag" <?= $eact === "tag" ? "selected=\"selected\"" : ""; ?>><?= gettext("tag"); ?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Apps nDPI"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="edit_ndpi_apps" class="form-control" style="max-width:480px;" value="<?= htmlspecialchars($e_apps); ?>" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Categorias"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="edit_ndpi_category" class="form-control" style="max-width:480px;" value="<?= htmlspecialchars($e_cats); ?>" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>tag_table</code></label>
				<div class="col-sm-10">
					<input type="text" name="edit_tag_table" class="form-control" style="max-width:280px;" maxlength="63"
						pattern="[A-Za-z0-9_]+" value="<?= htmlspecialchars($ett !== "" ? $ett : "layer7_tagged"); ?>" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ativa"); ?></label>
				<div class="col-sm-10">
					<input type="checkbox" name="edit_enabled" value="1" <?= $eon ? "checked=\"checked\"" : ""; ?> />
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="save_policy_edit" value="1" class="btn btn-primary"><?= gettext("Guardar alterações"); ?></button>
				</div>
			</div>
		</form>
		<hr />
		<?php } ?>

		<h3><?= gettext("Adicionar política"); ?></h3>
		<?php if ($at_limit) { ?>
		<p class="text-warning"><?= gettext("Limite de 24 políticas atingido."); ?></p>
		<?php } else { ?>
		<form method="post" class="form-horizontal">
			<input type="hidden" name="form_token" value="<?= htmlspecialchars(layer7_csrf_token()); ?>" />
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>id</code></label>
				<div class="col-sm-10">
					<input type="text" name="new_id" class="form-control" style="max-width:320px;" maxlength="80"
						pattern="[a-zA-Z0-9_-]+" required="required"
						placeholder="p-exemplo-001" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Nome"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="new_name" class="form-control" style="max-width:480px;" maxlength="160" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Prioridade"); ?></label>
				<div class="col-sm-10">
					<input type="number" name="new_priority" class="form-control" style="max-width:120px;" value="50" min="0" max="99999" />
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Ação"); ?></label>
				<div class="col-sm-10">
					<select name="new_action" class="form-control" style="max-width:200px;">
						<option value="monitor"><?= gettext("monitor"); ?></option>
						<option value="allow"><?= gettext("allow"); ?></option>
						<option value="block"><?= gettext("block"); ?></option>
						<option value="tag"><?= gettext("tag"); ?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Apps nDPI"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="new_ndpi_apps" class="form-control" style="max-width:480px;"
						placeholder="HTTP, BitTorrent" />
					<span class="help-block"><?= gettext("Separadas por vírgula (máx. 12). Vazio = qualquer app se não houver categoria."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?= gettext("Categorias"); ?></label>
				<div class="col-sm-10">
					<input type="text" name="new_ndpi_category" class="form-control" style="max-width:480px;"
						placeholder="Web" />
					<span class="help-block"><?= gettext("Separadas por vírgula (máx. 8)."); ?></span>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><code>tag_table</code></label>
				<div class="col-sm-10">
					<input type="text" name="new_tag_table" class="form-control" style="max-width:280px;" maxlength="63"
						pattern="[A-Za-z0-9_]+" placeholder="layer7_tagged" />
					<span class="help-block"><?= gettext("Obrigatório se ação = tag (tabela PF)."); ?></span>
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
					<button type="submit" name="add_policy" value="1" class="btn btn-success"><?= gettext("Adicionar política"); ?></button>
				</div>
			</div>
		</form>
		<?php } ?>
		<p class="text-muted small"><?= gettext("Alterar id da política: edite /usr/local/etc/layer7.json."); ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>
