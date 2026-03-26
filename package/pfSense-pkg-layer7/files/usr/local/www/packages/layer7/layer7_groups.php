<?php
##|+PRIV
##|*IDENT=page-services-layer7-groups
##|*NAME=Services: Layer 7 (groups)
##|*DESCR=Allow access to Layer 7 device groups.
##|*MATCH=layer7_groups.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

if ($_POST["add_group"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$ok = true;

	if (count($groups) >= 16) {
		$input_errors[] = l7_t("Limite de 16 grupos.");
		$ok = false;
	}

	$gid = trim($_POST["new_group_id"] ?? "");
	if ($ok && !layer7_group_id_valid($gid)) {
		$input_errors[] = l7_t("ID invalido (letras, numeros, _ e -; max. 80).");
		$ok = false;
	}
	if ($ok) {
		foreach ($groups as $existing) {
			if (isset($existing["id"]) && (string)$existing["id"] === $gid) {
				$input_errors[] = l7_t("Ja existe um grupo com esse ID.");
				$ok = false;
				break;
			}
		}
	}

	$gname = trim($_POST["new_group_name"] ?? "");
	if ($ok && strlen($gname) > 160) {
		$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
		$ok = false;
	}

	$cidrs = layer7_parse_cidr_textarea($_POST["new_group_cidrs"] ?? "");
	$hosts = layer7_parse_ip_textarea($_POST["new_group_hosts"] ?? "");

	if ($ok && empty($cidrs) && empty($hosts)) {
		$input_errors[] = l7_t("Indique pelo menos um CIDR ou IP.");
		$ok = false;
	}

	if ($ok) {
		$group = array(
			"id" => $gid,
			"name" => $gname !== "" ? $gname : $gid
		);
		if (!empty($cidrs)) {
			$group["cidrs"] = $cidrs;
		}
		if (!empty($hosts)) {
			$group["hosts"] = $hosts;
		}
		$groups[] = $group;
		if (layer7_save_json($data)) {
			layer7_signal_reload();
			$savemsg = l7_t("Grupo adicionado.");
		}
	}
	unset($groups);
}

if ($_POST["delete_group"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$idx = (int)($_POST["delete_group_index"] ?? -1);
	if ($idx < 0 || $idx >= count($groups)) {
		$input_errors[] = l7_t("Indice de grupo invalido.");
	} else {
		$del_id = isset($groups[$idx]["id"]) ? (string)$groups[$idx]["id"] : "";
		$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
			? $data["layer7"]["policies"] : array();
		$in_use = false;
		foreach ($policies as $pol) {
			if (isset($pol["match"]["groups"]) && is_array($pol["match"]["groups"])) {
				if (in_array($del_id, $pol["match"]["groups"], true)) {
					$in_use = true;
					break;
				}
			}
		}
		if ($in_use) {
			$input_errors[] = sprintf(l7_t("O grupo '%s' esta em uso por uma politica. Remova a referencia antes de apagar."), $del_id);
		} else {
			array_splice($groups, $idx, 1);
			if (layer7_save_json($data)) {
				layer7_signal_reload();
				$savemsg = l7_t("Grupo removido.");
			}
		}
	}
	unset($groups);
}

$layer7_group_edit_retry = null;

if ($_POST["save_group_edit"] ?? false) {
	$data = layer7_load_or_default();
	if (!isset($data["layer7"]["groups"]) || !is_array($data["layer7"]["groups"])) {
		$data["layer7"]["groups"] = array();
	}
	$groups = &$data["layer7"]["groups"];
	$idx = (int)($_POST["edit_group_index"] ?? -1);
	if ($idx < 0 || $idx >= count($groups)) {
		$input_errors[] = l7_t("Indice de grupo invalido.");
	} else {
		$layer7_group_edit_retry = $idx;
		$ok = true;
		$orig = $groups[$idx];
		$gid = isset($orig["id"]) ? (string)$orig["id"] : "";

		$gname = trim($_POST["edit_group_name"] ?? "");
		if ($ok && strlen($gname) > 160) {
			$input_errors[] = l7_t("Nome demasiado longo (max. 160).");
			$ok = false;
		}

		$cidrs = layer7_parse_cidr_textarea($_POST["edit_group_cidrs"] ?? "");
		$hosts = layer7_parse_ip_textarea($_POST["edit_group_hosts"] ?? "");

		if ($ok && empty($cidrs) && empty($hosts)) {
			$input_errors[] = l7_t("Indique pelo menos um CIDR ou IP.");
			$ok = false;
		}

		if ($ok) {
			$group = array(
				"id" => $gid,
				"name" => $gname !== "" ? $gname : $gid
			);
			if (!empty($cidrs)) {
				$group["cidrs"] = $cidrs;
			}
			if (!empty($hosts)) {
				$group["hosts"] = $hosts;
			}
			$groups[$idx] = $group;
			if (layer7_save_json($data)) {
				layer7_signal_reload();
				header("Location: layer7_groups.php");
				exit;
			}
			$input_errors[] = l7_t("Nao foi possivel gravar a configuracao.");
		}
	}
	unset($groups);
}

$data = layer7_load_or_default();
$groups = isset($data["layer7"]["groups"]) && is_array($data["layer7"]["groups"])
	? $data["layer7"]["groups"] : array();
$at_limit = count($groups) >= 16;

$edit_idx = null;
$edit_group = null;
if ($layer7_group_edit_retry !== null && $layer7_group_edit_retry >= 0 &&
    $layer7_group_edit_retry < count($groups)) {
	$edit_idx = (int)$layer7_group_edit_retry;
	$edit_group = $groups[$edit_idx];
} elseif (isset($_GET["edit"]) && ctype_digit((string)$_GET["edit"])) {
	$ec = (int)$_GET["edit"];
	if ($ec >= 0 && $ec < count($groups)) {
		$edit_idx = $ec;
		$edit_group = $groups[$ec];
	}
}

$policies = isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])
	? $data["layer7"]["policies"] : array();

function layer7_group_policy_count($gid, $policies)
{
	$count = 0;
	foreach ($policies as $pol) {
		if (isset($pol["match"]["groups"]) && is_array($pol["match"]["groups"])) {
			if (in_array($gid, $pol["match"]["groups"], true)) {
				$count++;
			}
		}
	}
	return $count;
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Grupos"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Grupos de dispositivos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("policies"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>

			<p class="layer7-lead"><?= l7_t("Crie grupos nomeados de dispositivos (ex.: Funcionarios, Visitantes) e aplique politicas por grupo em vez de repetir CIDRs manualmente."); ?></p>

		<div class="layer7-admin-block" id="l7-groups">
			<div class="layer7-admin-block__header"><?= l7_t("Grupos actuais"); ?></div>
			<div class="layer7-admin-block__body">
				<?php if (count($groups) === 0) { ?>
				<div class="alert alert-info"><?= l7_t("Nenhum grupo criado. Adicione o primeiro grupo abaixo."); ?></div>
				<?php } else { ?>
				<div class="table-responsive">
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th><code>id</code></th>
								<th><?= l7_t("Nome"); ?></th>
								<th><?= l7_t("CIDRs"); ?></th>
								<th><?= l7_t("IPs"); ?></th>
								<th><?= l7_t("Politicas"); ?></th>
								<th><?= l7_t("Acoes"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($groups as $i => $grp) {
							$gid = isset($grp["id"]) ? (string)$grp["id"] : "";
							$gname = isset($grp["name"]) ? (string)$grp["name"] : "";
							$gcidrs = isset($grp["cidrs"]) && is_array($grp["cidrs"]) ? $grp["cidrs"] : array();
							$ghosts = isset($grp["hosts"]) && is_array($grp["hosts"]) ? $grp["hosts"] : array();
							$pcount = layer7_group_policy_count($gid, $policies);
						?>
							<tr>
								<td><code><?= htmlspecialchars($gid); ?></code></td>
								<td><?= htmlspecialchars($gname); ?></td>
								<td class="small"><?= htmlspecialchars(implode(", ", $gcidrs)); ?></td>
								<td class="small"><?= htmlspecialchars(implode(", ", $ghosts)); ?></td>
								<td><?= (int)$pcount; ?></td>
								<td class="layer7-table-actions">
									<a href="layer7_groups.php?edit=<?= (int)$i; ?>" class="btn btn-xs btn-info"><?= l7_t("Editar"); ?></a>
								</td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>

				<div class="layer7-callout layer7-danger-zone">
					<form method="post" action="layer7_groups.php#l7-groups" class="form-inline layer7-inline-form"
						onsubmit='return confirm(<?= json_encode(l7_t("Remover este grupo?")); ?>);'>
						<div class="form-group">
							<label class="control-label" for="delete_group_index"><?= l7_t("Remover grupo"); ?></label>
							<select id="delete_group_index" name="delete_group_index" class="form-control">
								<?php foreach ($groups as $i => $grp) {
									$gid = isset($grp["id"]) ? (string)$grp["id"] : ("#" . $i);
									$gname = isset($grp["name"]) ? (string)$grp["name"] : "";
									$label = $gid . ($gname !== "" ? " - " . $gname : "");
								?>
								<option value="<?= (int)$i; ?>"><?= htmlspecialchars($label); ?></option>
								<?php } ?>
							</select>
							<button type="submit" name="delete_group" value="1" class="btn btn-danger"><?= l7_t("Remover"); ?></button>
						</div>
					</form>
				</div>
				<?php } ?>
			</div>
		</div>

		<?php if ($edit_group !== null && $edit_idx !== null) {
			$eg_id = isset($edit_group["id"]) ? (string)$edit_group["id"] : "";
			$eg_name = isset($edit_group["name"]) ? (string)$edit_group["name"] : "";
			$eg_cidrs = isset($edit_group["cidrs"]) && is_array($edit_group["cidrs"])
				? implode("\n", $edit_group["cidrs"]) : "";
			$eg_hosts = isset($edit_group["hosts"]) && is_array($edit_group["hosts"])
				? implode("\n", $edit_group["hosts"]) : "";
		?>
		<div class="layer7-admin-block" id="l7-edit-group">
			<div class="layer7-admin-block__header"><?= l7_t("Editar grupo"); ?></div>
			<div class="layer7-admin-block__body">
			<div class="layer7-toolbar">
				<a href="layer7_groups.php" class="btn btn-default"><?= l7_t("Cancelar edicao"); ?></a>
			</div>
			<form method="post" action="layer7_groups.php#l7-edit-group" class="form-horizontal">
				<input type="hidden" name="edit_group_index" value="<?= (int)$edit_idx; ?>" />

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-9">
						<p class="form-control-static"><code><?= htmlspecialchars($eg_id); ?></code></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Nome"); ?></label>
					<div class="col-sm-6">
						<input type="text" name="edit_group_name" class="form-control" maxlength="160" value="<?= htmlspecialchars($eg_name); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-6">
						<textarea name="edit_group_cidrs" class="form-control" rows="4" placeholder="192.168.10.0/24&#10;10.0.85.0/24"><?= htmlspecialchars($eg_cidrs); ?></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("IPs individuais"); ?></label>
					<div class="col-sm-6">
						<textarea name="edit_group_hosts" class="form-control" rows="4" placeholder="10.0.85.100&#10;10.0.85.101"><?= htmlspecialchars($eg_hosts); ?></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="save_group_edit" value="1" class="btn btn-primary"><?= l7_t("Guardar alteracoes"); ?></button>
					</div>
				</div>
			</form>
			</div>
		</div>
		<?php } ?>

		<div class="layer7-admin-block" id="l7-add-group">
			<div class="layer7-admin-block__header"><?= l7_t("Adicionar grupo"); ?></div>
			<div class="layer7-admin-block__body">
			<p class="layer7-lead"><?= l7_t("Defina um grupo de dispositivos com CIDRs e/ou IPs individuais. Depois associe o grupo a politicas na pagina de politicas."); ?></p>
			<?php if ($at_limit) { ?>
			<div class="alert alert-warning"><?= l7_t("Limite de 16 grupos atingido."); ?></div>
			<?php } else { ?>
			<form method="post" action="layer7_groups.php#l7-add-group" class="form-horizontal">

				<div class="form-group">
					<label class="col-sm-3 control-label"><code>id</code></label>
					<div class="col-sm-6">
						<input type="text" name="new_group_id" class="form-control" maxlength="80"
							pattern="[a-zA-Z0-9_-]+" required="required" placeholder="funcionarios" />
						<p class="help-block"><?= l7_t("Identificador unico (letras, numeros, _ e -)."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("Nome"); ?></label>
					<div class="col-sm-6">
						<input type="text" name="new_group_name" class="form-control" maxlength="160"
							placeholder="<?= l7_t("Ex.: Funcionarios"); ?>" />
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("CIDRs"); ?></label>
					<div class="col-sm-6">
						<textarea name="new_group_cidrs" class="form-control" rows="4" placeholder="192.168.10.0/24&#10;10.0.85.0/24"></textarea>
						<p class="help-block"><?= l7_t("Um CIDR por linha (max. 8). Ex.: 10.0.85.0/24."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= l7_t("IPs individuais"); ?></label>
					<div class="col-sm-6">
						<textarea name="new_group_hosts" class="form-control" rows="4" placeholder="10.0.85.100&#10;10.0.85.101"></textarea>
						<p class="help-block"><?= l7_t("Um IPv4 por linha (max. 16). Opcional se ja tiver CIDRs."); ?></p>
					</div>
				</div>

				<div class="form-group">
					<div class="col-sm-offset-3 col-sm-9">
						<button type="submit" name="add_group" value="1" class="btn btn-success"><?= l7_t("Adicionar grupo"); ?></button>
					</div>
				</div>
			</form>
			<?php } ?>
			</div>
		</div>
		</div>
	</div>
</div>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
