<?php
##|+PRIV
##|*IDENT=page-services-layer7-remote-access
##|*NAME=Services: Layer 7 (remote access)
##|*DESCR=Guide to block remote access software.
##|*MATCH=layer7_remote_access.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$input_errors = array();
$savemsg = "";

if (isset($_POST["save_remote_access"])) {
	$blocked = array();
	if (isset($_POST["blocked"]) && is_array($_POST["blocked"])) {
		$blocked = $_POST["blocked"];
	}
	$result = layer7_remote_access_apply($blocked);
	if (empty($result["ok"])) {
		$input_errors[] = $result["msg"] ?? l7_t("Falha ao aplicar Acesso Remoto.");
	} else {
		$savemsg = $result["msg"] ?? l7_t("Acesso Remoto actualizado.");
	}
}

$catalog = layer7_remote_access_catalog_load();
$state = layer7_remote_access_state_load();
$blocked_set = array();
foreach ($state["blocked"] as $id) {
	$blocked_set[$id] = true;
}

/* Se ainda nao ha estado gravado mas a politica factory esta activa com
 * criterio, marcar itens cuja app/host esteja no match (bootstrap UX). */
$data = layer7_load_or_default();
$pid = "profile-" . (string)($catalog["profile_id"] ?? "remote-access");
$policy_on = false;
$policy_apps = array();
$policy_hosts = array();
$policy_cats = array();
if (isset($data["layer7"]["policies"]) && is_array($data["layer7"]["policies"])) {
	foreach ($data["layer7"]["policies"] as $pol) {
		if (($pol["id"] ?? "") !== $pid || empty($pol["enabled"])) {
			continue;
		}
		$policy_on = true;
		if (!empty($pol["match"]["ndpi_app"]) && is_array($pol["match"]["ndpi_app"])) {
			foreach ($pol["match"]["ndpi_app"] as $a) {
				$policy_apps[trim((string)$a)] = true;
			}
		}
		if (!empty($pol["match"]["hosts"]) && is_array($pol["match"]["hosts"])) {
			foreach ($pol["match"]["hosts"] as $h) {
				$policy_hosts[strtolower(trim((string)$h))] = true;
			}
		}
		if (!empty($pol["match"]["ndpi_category"]) && is_array($pol["match"]["ndpi_category"])) {
			foreach ($pol["match"]["ndpi_category"] as $c) {
				$policy_cats[strtolower(trim((string)$c))] = true;
			}
		}
		break;
	}
}

if (empty($blocked_set) && $policy_on) {
	foreach ($catalog["items"] as $it) {
		if (!is_array($it)) {
			continue;
		}
		$id = strtolower(trim((string)($it["id"] ?? "")));
		if ($id === "") {
			continue;
		}
		$hit = false;
		if (!empty($it["ndpi_categories"]) && is_array($it["ndpi_categories"])) {
			foreach ($it["ndpi_categories"] as $c) {
				if (isset($policy_cats[strtolower(trim((string)$c))])) {
					$hit = true;
				}
			}
		}
		if (!empty($it["ndpi_apps"]) && is_array($it["ndpi_apps"])) {
			foreach ($it["ndpi_apps"] as $a) {
				if (isset($policy_apps[trim((string)$a)])) {
					$hit = true;
				}
			}
		}
		if (!empty($it["hosts"]) && is_array($it["hosts"])) {
			foreach ($it["hosts"] as $h) {
				if (isset($policy_hosts[strtolower(trim((string)$h))])) {
					$hit = true;
				}
			}
		}
		if ($hit) {
			$blocked_set[$id] = true;
		}
	}
}

$groups = array();
foreach ($catalog["items"] as $it) {
	if (!is_array($it)) {
		continue;
	}
	$g = trim((string)($it["group"] ?? l7_t("Outros")));
	if ($g === "") {
		$g = l7_t("Outros");
	}
	$groups[$g][] = $it;
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Acesso Remoto"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Acesso Remoto"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("policies"); ?>
		<div class="layer7-content">
			<?php layer7_render_messages(); ?>
			<p class="layer7-lead">
				<?= l7_t("Editor do pacote agregado «Acesso Remoto (todos)». Para bloquear so alguns softwares (ex.: AnyDesk sim, TeamViewer nao), use os cartoes individuais do grupo Acesso remoto em Politicas / Perfis rapidos — um toggle por software.") ?>
				<a href="layer7_policies.php#l7-policies"><?= l7_t("Voltar a Politicas / Perfis rapidos") ?></a>
			</p>
			<div class="alert alert-info">
				<i class="fa fa-info-circle"></i>
				<?= l7_t("Esta pagina actualiza apenas a politica do pacote completo. Deteccao: nDPI, host/DNS/SNI, ou categoria RemoteAccess. Softwares self-host ou com CDN propria podem exigir hosts adicionais.") ?>
			</div>

			<form method="post" action="layer7_remote_access.php">
				<div class="l7-bulk-tools" style="margin-bottom:14px;">
					<button type="button" class="btn btn-xs btn-default" onclick="l7RaToggleAll(true);"><?= l7_t("Seleccionar todos") ?></button>
					<button type="button" class="btn btn-xs btn-default" onclick="l7RaToggleAll(false);"><?= l7_t("Limpar todos") ?></button>
					<button type="submit" name="save_remote_access" class="btn btn-primary btn-sm" style="margin-left:8px;">
						<i class="fa fa-save"></i> <?= l7_t("Guardar e aplicar") ?>
					</button>
				</div>

				<?php foreach ($groups as $gname => $items): ?>
				<div class="layer7-admin-block">
					<div class="layer7-admin-block__header">
						<?= htmlspecialchars($gname) ?>
						<span class="badge"><?= count($items) ?></span>
					</div>
					<div class="layer7-admin-block__body" style="padding:0;">
						<table class="table table-striped table-hover" style="margin:0;">
							<thead>
							<tr>
								<th style="width:90px;"><?= l7_t("Bloquear") ?></th>
								<th style="width:48px;"></th>
								<th><?= l7_t("Software") ?></th>
								<th style="width:160px;"><?= l7_t("Deteccao") ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ($items as $it):
								$id = strtolower(trim((string)($it["id"] ?? "")));
								if ($id === "") {
									continue;
								}
								$name = (string)($it["name"] ?? $id);
								$icon = (string)($it["icon"] ?? "fa-desktop");
								$det = (string)($it["detection"] ?? "");
								$note = trim((string)($it["note"] ?? ""));
								$checked = !empty($blocked_set[$id]) ? "checked" : "";
								$det_label = $det;
								if ($det === "ndpi+host") {
									$det_label = "nDPI + host";
								} elseif ($det === "ndpi-category") {
									$det_label = "categoria nDPI";
								} elseif ($det === "ndpi") {
									$det_label = "nDPI";
								} elseif ($det === "host") {
									$det_label = "host / DNS";
								}
							?>
							<tr>
								<td style="text-align:center; vertical-align:middle;">
									<input type="checkbox" class="l7-ra-cb" name="blocked[]" value="<?= htmlspecialchars($id) ?>" <?= $checked ?>>
								</td>
								<td style="text-align:center; vertical-align:middle; font-size:18px; color:#555;">
									<?= layer7_profile_icon_html($icon) ?>
								</td>
								<td>
									<strong><?= htmlspecialchars($name) ?></strong>
									<?php if ($note !== ""): ?>
									<br><small class="text-muted"><?= htmlspecialchars($note) ?></small>
									<?php endif; ?>
								</td>
								<td><span class="label label-default"><?= htmlspecialchars($det_label) ?></span></td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php endforeach; ?>

				<div style="margin-top:16px;">
					<button type="submit" name="save_remote_access" class="btn btn-primary">
						<i class="fa fa-save"></i> <?= l7_t("Guardar e aplicar") ?>
					</button>
				</div>
			</form>
		</div>
		<?php layer7_render_footer(); ?>
	</div>
</div>
<script type="text/javascript">
function l7RaToggleAll(on) {
	var boxes = document.querySelectorAll('.l7-ra-cb');
	for (var i = 0; i < boxes.length; i++) {
		boxes[i].checked = !!on;
	}
}
</script>
<?php
include("foot.inc");
