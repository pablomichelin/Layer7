<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$filter = isset($_GET["filter"]) ? trim($_GET["filter"]) : "";
$max_lines = 100;

$all_logs = array();
if (file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -" . $max_lines . " 2>/dev/null", $all_logs);
}

$filtered_logs = $all_logs;
if ($filter !== "") {
	$filtered_logs = array();
	foreach ($all_logs as $line) {
		if (stripos($line, $filter) !== false) {
			$filtered_logs[] = $line;
		}
	}
}

$enforce_events = array();
$classify_events = array();
foreach ($all_logs as $line) {
	if (strpos($line, "enforce_action") !== false || strpos($line, "pfctl add") !== false) {
		$enforce_events[] = $line;
	}
	if (strpos($line, "flow_decide") !== false) {
		$classify_events[] = $line;
	}
}
$enforce_events = array_slice($enforce_events, -30);
$classify_events = array_slice($classify_events, -30);

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Events"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - events"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("events"); ?>
		<div class="layer7-content">

		<p class="layer7-lead"><?= gettext("Eventos do daemon extraidos do syslog do sistema. Use os filtros para encontrar eventos especificos."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Filtrar logs"); ?></h3>
			<form method="get" class="form-inline">
				<div class="form-group">
					<input type="text" name="filter" class="form-control" style="width: 320px;" maxlength="100"
						value="<?= htmlspecialchars($filter); ?>" placeholder="<?= gettext("Ex: enforce, flow_decide, BitTorrent, SIGUSR1..."); ?>" />
				</div>
				<button type="submit" class="btn btn-primary"><?= gettext("Filtrar"); ?></button>
				<?php if ($filter !== "") { ?>
				<a href="layer7_events.php" class="btn btn-default"><?= gettext("Limpar"); ?></a>
				<?php } ?>
			</form>
		</div>

		<?php if (count($enforce_events) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Eventos de enforcement"); ?> <span class="badge"><?= count($enforce_events); ?></span></h3>
			<p class="small text-muted"><?= gettext("Acoes de block/tag executadas pelo daemon (pfctl -T add). Ultimo evento no final."); ?></p>
			<pre class="pre-scrollable" style="max-height: 250px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $enforce_events)); ?></pre>
		</div>
		<?php } ?>

		<?php if (count($classify_events) > 0) { ?>
		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Classificacoes nDPI"); ?> <span class="badge"><?= count($classify_events); ?></span></h3>
			<p class="small text-muted"><?= gettext("Fluxos classificados pelo nDPI (visivel com log_level=debug). Mostra src, app, cat, action, reason."); ?></p>
			<pre class="pre-scrollable" style="max-height: 250px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $classify_events)); ?></pre>
		</div>
		<?php } ?>

		<div class="layer7-section">
			<h3 class="layer7-section-title">
				<?= gettext("Todos os logs"); ?>
				<span class="badge"><?= count($filtered_logs); ?></span>
				<?php if ($filter !== "") { ?>
				<small class="text-muted"> (<?= gettext("filtro"); ?>: <?= htmlspecialchars($filter); ?>)</small>
				<?php } ?>
			</h3>
			<?php if (count($filtered_logs) > 0) { ?>
			<pre class="pre-scrollable" style="max-height: 400px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $filtered_logs)); ?></pre>
			<?php } else { ?>
			<div class="alert alert-info">
				<?php if ($filter !== "") { ?>
				<?= gettext("Nenhum log correspondente ao filtro."); ?>
				<?php } else { ?>
				<?= gettext("Nenhum log do layer7d encontrado em /var/log/system.log."); ?>
				<?php } ?>
			</div>
			<?php } ?>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Dicas"); ?></h3>
			<ul class="small">
				<li><?= gettext("Ative log_level=debug em Definicoes para ver decisoes de cada fluxo classificado."); ?></li>
				<li><?= gettext("Use debug_minutes para elevar temporariamente sem editar o JSON."); ?></li>
				<li><?= gettext("Configure syslog remoto em Definicoes para reter historico fora do appliance."); ?></li>
				<li><?= gettext("SIGUSR1 (pagina Diagnostics) gera um resumo de estatisticas que aparece aqui."); ?></li>
			</ul>
		</div>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
