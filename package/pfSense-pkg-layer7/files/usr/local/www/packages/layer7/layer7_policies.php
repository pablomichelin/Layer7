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
		if ($ok && $apps !== null && $cats !== null &&
		    ($act === "block" || $act === "tag") &&
		    count($apps) + count($cats) === 0) {
			$input_errors[] = gettext("Para block ou tag, indique app nDPI e/ou categoria.");
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
			if ($act === "tag") {
				$rule["tag_table"] = $tag_table;
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
			if ($ok && $apps !== null && $cats !== null &&
			    ($act === "block" || $act === "tag") &&
			    count($apps) + count($cats) === 0) {
				$input_errors[] = gettext("Para block ou tag, indique app nDPI e/ou categoria.");
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
				if ($act === "tag") {
					$rule["tag_table"] = $tag_table;
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
	if (!empty($policy["tag_table"]) && (($policy["action"] ?? "") === "tag")) {
		$matches[] = gettext("Tabela PF") . ": " . $policy["tag_table"];
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

				<div class="form-group">
					<label class="col-sm-3 control-label"><?= gettext("Sites/hosts"); ?></label>
					<div class="col-sm-9">
						<textarea name="new_match_hosts" class="form-control" rows="3" style="max-width:400px" placeholder="youtube.com&#10;api.whatsapp.com"></textarea>
						<p class="help-block"><?= gettext("Um host por linha. O match aceita o host exacto e subdominios observados no log host=."); ?></p>
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
<script>
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
