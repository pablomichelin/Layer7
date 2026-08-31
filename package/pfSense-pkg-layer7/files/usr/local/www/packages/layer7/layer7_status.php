<?php
##|+PRIV
##|*IDENT=page-services-layer7
##|*NAME=Services: Layer 7
##|*DESCR=Allow access to the Layer 7 package page.
##|*MATCH=layer7_status.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");
require_once("classes/Form.class.php");

$daemon_ver = layer7_daemon_version();

$pidfile = "/var/run/layer7d.pid";
$running = false;
$pid = null;
$pid = layer7_daemon_pid_from_file($pidfile);
if ($pid !== null) {
	exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $chk, $chk_code);
	$running = ($chk_code === 0);
}

$data = layer7_load_or_default();
$L = isset($data["layer7"]) ? $data["layer7"] : array();
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
	$pid = layer7_daemon_pid_from_file($pidfile);
	if ($pid !== null) {
		exec("/bin/kill -0 " . escapeshellarg($pid) . " 2>/dev/null", $chk2, $chk2_code);
		$running = ($chk2_code === 0);
	}
	$stats = $running ? layer7_read_stats() : null;
}

$enf = layer7_gui_enforce_state($data, $stats);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"));
include("head.inc");

layer7_render_tabs("status");

if ($restart_msg !== "") {
	print_info_box($restart_msg, "success");
}
if ($restart_err !== "") {
	print_info_box($restart_err, "danger");
}

if ($running) {
	$daemon_html = '<span class="text-success"><i class="fa fa-check-circle"></i> ' .
	    htmlspecialchars(l7_t("Em execucao")) . ' (PID ' . htmlspecialchars((string)$pid) . ')</span>';
} else {
	$daemon_html = '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' .
	    htmlspecialchars(l7_t("Parado")) . '</span>';
}

if (count($cfg_ifaces) > 0) {
	$ifaces_html = '<code>' . htmlspecialchars(implode(", ", $cfg_ifaces)) . '</code>';
} else {
	$ifaces_html = '<span class="text-muted">' . htmlspecialchars(l7_t("Nenhuma")) . '</span>';
}

$policies_html = (int)$n_policies_active . '/' . (int)$n_policies . ' ' . htmlspecialchars(l7_t("ativas"));
if ($n_block_policies > 0) {
	if (!empty($enf["enforce_armed"])) {
		$policies_html .= ' (<span class="text-danger">' . (int)$n_block_policies . ' block</span>)';
	} else {
		$policies_html .= ' (<span class="text-muted">' . (int)$n_block_policies . ' ' .
		    htmlspecialchars(l7_t("block gravada, nao aplicada")) . '</span>)';
	}
}

$form = new Form(false);

$sec_state = new Form_Section(l7_t("Estado do daemon"));
$sec_state->addInput(new Form_StaticText(l7_t("Daemon"), $daemon_html));
if ($daemon_ver !== "") {
	$sec_state->addInput(new Form_StaticText(
	    l7_t("Versao"),
	    '<code>' . htmlspecialchars($daemon_ver) . '</code>'
	));
}
$sec_state->addInput(new Form_StaticText(l7_t("Uptime"), htmlspecialchars($uptime_str)));
$sec_state->addInput(new Form_StaticText(l7_t("Modo"), layer7_gui_mode_badge_html($enf)));
$sec_state->addInput(new Form_StaticText(l7_t("Interfaces"), $ifaces_html));
$sec_state->addInput(new Form_StaticText(l7_t("Politicas"), $policies_html));
$form->add($sec_state);

$sec_resumo = new Form_Section(l7_t("Resumo"));
$sec_resumo->addInput(new Form_StaticText(
    l7_t("Conexoes classificadas"),
    htmlspecialchars(number_format($total_classified))
));
$sec_resumo->addInput(new Form_StaticText(
    l7_t("Bloqueios"),
    htmlspecialchars(number_format($total_blocked))
));
$sec_resumo->addInput(new Form_StaticText(
    l7_t("Permitidos"),
    htmlspecialchars(number_format($total_allowed))
));
$sec_resumo->addInput(new Form_StaticText(
    l7_t("Politicas activas"),
    htmlspecialchars((string)$n_policies_active)
));
$form->add($sec_resumo);

print($form);
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Top 10 apps bloqueadas")); ?></h2>
	</div>
	<div class="panel-body">
		<?php if (empty($top_apps)) { ?>
		<p class="text-muted"><?= htmlspecialchars(l7_t("Sem dados. O daemon precisa de trafego classificado para gerar estatisticas.")); ?></p>
		<?php } else { ?>
		<div class="table-responsive">
			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th>#</th>
						<th><?= htmlspecialchars(l7_t("App")); ?></th>
						<th><?= htmlspecialchars(l7_t("Bloqueios")); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($top_apps as $rank => $entry) {
					$app_name = isset($entry["app"]) ? $entry["app"] : "?";
					$app_count = isset($entry["count"]) ? (int)$entry["count"] : 0;
				?>
					<tr>
						<td><?= (int)($rank + 1); ?></td>
						<td><?= htmlspecialchars($app_name); ?></td>
						<td><?= htmlspecialchars(number_format($app_count)); ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php } ?>
	</div>
</div>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Top 10 clientes bloqueados")); ?></h2>
	</div>
	<div class="panel-body">
		<?php if (empty($top_sources)) { ?>
		<p class="text-muted"><?= htmlspecialchars(l7_t("Sem dados.")); ?></p>
		<?php } else {
			$dev_by_ip = array();
			if (function_exists("layer7_device_inventory")) {
				$inv = layer7_device_inventory();
				foreach ((is_array($inv) ? $inv : array()) as $d) {
					$ip = (string)($d["ip"] ?? "");
					if ($ip === "") { continue; }
					$lbl = trim((string)($d["alias"] ?? ""));
					if ($lbl === "") { $lbl = trim((string)($d["hostname"] ?? "")); }
					if ($lbl === "") { $lbl = trim((string)($d["vendor"] ?? "")); }
					if ($lbl !== "" && $lbl !== "-") { $dev_by_ip[$ip] = $lbl; }
				}
			}
		?>
		<div class="table-responsive">
			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th>#</th>
						<th><?= htmlspecialchars(l7_t("IP de origem")); ?></th>
						<th><?= htmlspecialchars(l7_t("Dispositivo")); ?></th>
						<th><?= htmlspecialchars(l7_t("Bloqueios")); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($top_sources as $rank => $entry) {
					$src_ip = isset($entry["ip"]) ? $entry["ip"] : "?";
					$src_count = isset($entry["count"]) ? (int)$entry["count"] : 0;
					$dev_lbl = isset($dev_by_ip[$src_ip]) ? $dev_by_ip[$src_ip] : "";
				?>
					<tr>
						<td><?= (int)($rank + 1); ?></td>
						<td><code><?= htmlspecialchars($src_ip); ?></code></td>
						<td><?= $dev_lbl !== "" ? htmlspecialchars($dev_lbl) : '<span class="text-muted">-</span>'; ?></td>
						<td><?= htmlspecialchars(number_format($src_count)); ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<?php } ?>
	</div>
</div>
<div class="panel panel-default" id="l7-toolbar">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Acoes")); ?></h2>
	</div>
	<div class="panel-body">
		<form method="post" action="layer7_status.php#l7-toolbar">
			<button type="submit" name="restart_service" value="1" class="btn btn-warning"
				onclick="return confirm(<?= json_encode(l7_t('Reiniciar o servico layer7d? O trafego nao sera classificado durante o restart.')) ?>);">
				<i class="fa fa-refresh"></i> <?= htmlspecialchars(l7_t("Reiniciar servico")); ?>
			</button>
			<span class="help-block"><?= htmlspecialchars(l7_t("Reiniciar o servico layer7d? O trafego nao sera classificado durante o restart.")); ?></span>
		</form>
		<a href="layer7_settings.php" class="btn btn-primary"><?= htmlspecialchars(l7_t("Abrir definicoes")); ?></a>
		<a href="layer7_diagnostics.php" class="btn btn-default"><?= htmlspecialchars(l7_t("Diagnosticos")); ?></a>
		<a href="layer7_policies.php" class="btn btn-default"><i class="fa fa-sliders"></i> <?= htmlspecialchars(l7_t("Perfis rapidos")); ?></a>
		<a href="layer7_events.php" class="btn btn-default"><i class="fa fa-list"></i> <?= htmlspecialchars(l7_t("Eventos")); ?></a>
		<a href="layer7_reports.php" class="btn btn-default"><i class="fa fa-bar-chart"></i> <?= htmlspecialchars(l7_t("Relatorios")); ?></a>
	</div>
</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc"); ?>
