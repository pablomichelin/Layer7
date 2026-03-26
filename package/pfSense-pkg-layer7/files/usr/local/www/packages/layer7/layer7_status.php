<?php
##|+PRIV
##|*IDENT=page-services-layer7
##|*NAME=Services: Layer 7
##|*DESCR=Allow access to the Layer 7 package page.
##|*MATCH=layer7_status.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$daemon_ver = layer7_daemon_version();

$pidfile = "/var/run/layer7d.pid";
$running = false;
$pid = null;
if (file_exists($pidfile)) {
	$pid = trim(@file_get_contents($pidfile));
	if ($pid !== "" && ctype_digit($pid)) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $chk, $chk_code);
		$running = ($chk_code === 0);
	}
}

$data = layer7_load_or_default();
$L = isset($data["layer7"]) ? $data["layer7"] : array();
$cfg_enabled = !empty($L["enabled"]);
$cfg_mode = isset($L["mode"]) ? (string)$L["mode"] : "monitor";
$cfg_ifaces = isset($L["interfaces"]) && is_array($L["interfaces"]) ? $L["interfaces"] : array();
$n_policies = isset($L["policies"]) && is_array($L["policies"]) ? count($L["policies"]) : 0;
$n_exceptions = isset($L["exceptions"]) && is_array($L["exceptions"]) ? count($L["exceptions"]) : 0;
$n_policies_active = 0;
$n_block_policies = 0;
if (isset($L["policies"]) && is_array($L["policies"])) {
	foreach ($L["policies"] as $p) {
		if (!empty($p["enabled"])) {
			$n_policies_active++;
			if (isset($p["action"]) && $p["action"] === "block") {
				$n_block_policies++;
			}
		}
	}
}

$stats = $running ? layer7_read_stats() : null;

$uptime_str = "-";
if ($stats && isset($stats["uptime_seconds"])) {
	$up = (int)$stats["uptime_seconds"];
	$days = floor($up / 86400);
	$hours = floor(($up % 86400) / 3600);
	$mins = floor(($up % 3600) / 60);
	if ($days > 0) {
		$uptime_str = sprintf("%dd %dh %dm", $days, $hours, $mins);
	} elseif ($hours > 0) {
		$uptime_str = sprintf("%dh %dm", $hours, $mins);
	} else {
		$uptime_str = sprintf("%dm", $mins);
	}
}

$total_classified = ($stats && isset($stats["total_classified"])) ? (int)$stats["total_classified"] : 0;
$total_blocked = ($stats && isset($stats["total_blocked"])) ? (int)$stats["total_blocked"] : 0;
$total_allowed = ($stats && isset($stats["total_allowed"])) ? (int)$stats["total_allowed"] : 0;
$top_apps = ($stats && isset($stats["top_apps_blocked"]) && is_array($stats["top_apps_blocked"])) ? $stats["top_apps_blocked"] : array();
$top_sources = ($stats && isset($stats["top_sources_blocked"]) && is_array($stats["top_sources_blocked"])) ? $stats["top_sources_blocked"] : array();

$restart_msg = "";
$restart_err = "";
if (isset($_POST["restart_service"])) {
	if (layer7_restart_service()) {
		$restart_msg = l7_t("Servico layer7d reiniciado com sucesso.");
	} else {
		$restart_err = l7_t("Falha ao reiniciar o servico layer7d. Verifique o estado no terminal.");
	}
	$pid = null;
	$running = false;
	$pidfile = "/var/run/layer7d.pid";
	if (file_exists($pidfile)) {
		$pid = trim(@file_get_contents($pidfile));
		if ($pid !== "" && ctype_digit($pid)) {
			exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>&1", $chk2, $chk2_code);
			$running = ($chk2_code === 0);
		}
	}
	$stats = $running ? layer7_read_stats() : null;
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Dashboard"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("status"); ?>
		<div class="layer7-content">

		<?php if ($restart_msg !== "") { ?>
		<div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($restart_msg); ?></div>
		<?php } ?>
		<?php if ($restart_err !== "") { ?>
		<div class="alert alert-danger"><i class="fa fa-times-circle"></i> <?= htmlspecialchars($restart_err); ?></div>
		<?php } ?>

		<div class="layer7-section">
			<div class="l7-dashboard-cards">
				<div class="l7-dash-card">
					<div class="l7-dash-card-value"><?= number_format($total_classified); ?></div>
					<div class="l7-dash-card-label"><?= l7_t("Conexoes classificadas"); ?></div>
				</div>
				<div class="l7-dash-card l7-dash-card-danger">
					<div class="l7-dash-card-value"><?= number_format($total_blocked); ?></div>
					<div class="l7-dash-card-label"><?= l7_t("Bloqueios"); ?></div>
				</div>
				<div class="l7-dash-card l7-dash-card-success">
					<div class="l7-dash-card-value"><?= number_format($total_allowed); ?></div>
					<div class="l7-dash-card-label"><?= l7_t("Permitidos"); ?></div>
				</div>
				<div class="l7-dash-card">
					<div class="l7-dash-card-value"><?= $n_policies_active; ?></div>
					<div class="l7-dash-card-label"><?= l7_t("Politicas activas"); ?></div>
				</div>
			</div>
		</div>

		<div class="layer7-section">
			<div class="row">
				<div class="col-md-6">
					<h3 class="layer7-section-title"><?= l7_t("Top 10 apps bloqueadas"); ?></h3>
					<?php if (empty($top_apps)) { ?>
					<p class="text-muted"><?= l7_t("Sem dados. O daemon precisa de trafego classificado para gerar estatisticas."); ?></p>
					<?php } else { ?>
					<table class="table table-striped table-condensed">
						<thead>
							<tr>
								<th>#</th>
								<th><?= l7_t("App"); ?></th>
								<th><?= l7_t("Bloqueios"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($top_apps as $rank => $entry) {
							$app_name = isset($entry["app"]) ? $entry["app"] : "?";
							$app_count = isset($entry["count"]) ? (int)$entry["count"] : 0;
						?>
							<tr>
								<td><?= $rank + 1; ?></td>
								<td><strong><?= htmlspecialchars($app_name); ?></strong></td>
								<td><?= number_format($app_count); ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
					<?php } ?>
				</div>
				<div class="col-md-6">
					<h3 class="layer7-section-title"><?= l7_t("Top 10 clientes bloqueados"); ?></h3>
					<?php if (empty($top_sources)) { ?>
					<p class="text-muted"><?= l7_t("Sem dados."); ?></p>
					<?php } else { ?>
					<table class="table table-striped table-condensed">
						<thead>
							<tr>
								<th>#</th>
								<th><?= l7_t("IP de origem"); ?></th>
								<th><?= l7_t("Bloqueios"); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ($top_sources as $rank => $entry) {
							$src_ip = isset($entry["ip"]) ? $entry["ip"] : "?";
							$src_count = isset($entry["count"]) ? (int)$entry["count"] : 0;
						?>
							<tr>
								<td><?= $rank + 1; ?></td>
								<td><code><?= htmlspecialchars($src_ip); ?></code></td>
								<td><?= number_format($src_count); ?></td>
							</tr>
						<?php } ?>
						</tbody>
					</table>
					<?php } ?>
				</div>
			</div>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= l7_t("Estado do daemon"); ?></h3>
			<div class="layer7-callout">
				<dl class="dl-horizontal layer7-summary">
					<dt><?= l7_t("Daemon"); ?></dt>
					<dd>
						<?php if ($running) { ?>
						<span class="text-success"><i class="fa fa-check-circle"></i> <?= l7_t("Em execucao"); ?> (PID <?= htmlspecialchars($pid); ?>)</span>
						<?php } else { ?>
						<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> <?= l7_t("Parado"); ?></span>
						<?php } ?>
					</dd>

					<?php if ($daemon_ver !== "") { ?>
					<dt><?= l7_t("Versao"); ?></dt>
					<dd><code><?= htmlspecialchars($daemon_ver); ?></code></dd>
					<?php } ?>

					<dt><?= l7_t("Uptime"); ?></dt>
					<dd><?= htmlspecialchars($uptime_str); ?></dd>

					<dt><?= l7_t("Modo"); ?></dt>
					<dd>
						<?php if (!$cfg_enabled) { ?>
						<span class="label label-warning"><?= l7_t("desativado"); ?></span>
						<?php } elseif ($cfg_mode === "enforce") { ?>
						<span class="label label-danger"><?= l7_t("enforce"); ?></span>
						<?php } else { ?>
						<span class="label label-info"><?= l7_t("monitor"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Interfaces"); ?></dt>
					<dd>
						<?php if (count($cfg_ifaces) > 0) { ?>
						<code><?= htmlspecialchars(implode(", ", $cfg_ifaces)); ?></code>
						<?php } else { ?>
						<span class="text-muted"><?= l7_t("Nenhuma"); ?></span>
						<?php } ?>
					</dd>

					<dt><?= l7_t("Politicas"); ?></dt>
					<dd>
						<?= $n_policies_active; ?>/<?= $n_policies; ?> <?= l7_t("ativas"); ?>
						<?php if ($n_block_policies > 0) { ?>
						(<span class="text-danger"><?= $n_block_policies; ?> block</span>)
						<?php } ?>
					</dd>

					</dl>
			</div>
		</div>

		<div class="layer7-toolbar" id="l7-toolbar">
			<form method="post" action="layer7_status.php#l7-toolbar" style="display: inline-block; margin-right: 8px; margin-bottom: 8px;">
				<button type="submit" name="restart_service" value="1" class="btn btn-warning"
					onclick="return confirm(<?= json_encode(l7_t('Reiniciar o servico layer7d? O trafego nao sera classificado durante o restart.')) ?>);">
					<i class="fa fa-refresh"></i> <?= l7_t("Reiniciar servico"); ?>
				</button>
			</form>
			<a href="layer7_settings.php" class="btn btn-primary"><?= l7_t("Abrir definicoes"); ?></a>
			<a href="layer7_policies.php" class="btn btn-default"><?= l7_t("Ver politicas"); ?></a>
			<a href="layer7_diagnostics.php" class="btn btn-default"><?= l7_t("Diagnosticos"); ?></a>
			<a href="layer7_events.php" class="btn btn-default"><?= l7_t("Eventos"); ?></a>
		</div>
		</div>
	</div>
</div>
<style>
.l7-dashboard-cards { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 10px; }
.l7-dash-card { flex: 1; min-width: 160px; max-width: 240px; background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 6px; padding: 20px 18px; text-align: center; }
.l7-dash-card-value { font-size: 32px; font-weight: 700; color: #333; line-height: 1.1; }
.l7-dash-card-label { font-size: 13px; color: #777; margin-top: 6px; }
.l7-dash-card-danger .l7-dash-card-value { color: #d9534f; }
.l7-dash-card-success .l7-dash-card-value { color: #5cb85c; }
</style>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
