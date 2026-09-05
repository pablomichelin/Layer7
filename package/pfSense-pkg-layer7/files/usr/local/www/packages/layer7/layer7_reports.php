<?php
##|+PRIV
##|*IDENT=page-services-layer7-reports
##|*NAME=Services: Layer 7 (reports)
##|*DESCR=Layer 7 executive reports.
##|*MATCH=layer7_reports.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function layer7_reports_compact_pages($page, $total_pages, $radius = 2)
{
	$pages = array(1, $total_pages);
	$start = max(1, (int)$page - (int)$radius);
	$end = min((int)$total_pages, (int)$page + (int)$radius);
	$p = 0;

	for ($p = $start; $p <= $end; $p++) {
		$pages[] = $p;
	}
	$pages = array_values(array_unique($pages));
	sort($pages, SORT_NUMERIC);
	return $pages;
}

$clear_msg = "";
if (isset($_POST["clear_all_reports"])) {
	$result = layer7_reports_clear_all();
	if ($result["ok"]) {
		$clear_msg = l7_t("Banco detalhado e historico executivo foram apagados; os arquivos rotativos de log foram preservados.") .
		    " (" . number_format($result["deleted"]) . " " . l7_t("eventos removidos") . ")";
	}
}

$range = isset($_GET["range"]) ? (string)$_GET["range"] : "24h";
$custom_from = isset($_GET["from"]) ? (string)$_GET["from"] : "";
$custom_to = isset($_GET["to"]) ? (string)$_GET["to"] : "";
$page = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$page_size = 50;

$filters = array(
	"src_ip" => trim((string)($_GET["src_ip"] ?? "")),
	"host" => trim((string)($_GET["host"] ?? "")),
	"action" => trim((string)($_GET["action"] ?? "")),
	"q" => trim((string)($_GET["q"] ?? ""))
);
if (!in_array($filters["action"], array("", "block", "allow", "monitor"), true)) {
	$filters["action"] = "";
}

$now = time();
switch ($range) {
	case "1h":   $from_ts = $now - 3600; break;
	case "6h":   $from_ts = $now - 21600; break;
	case "24h":  $from_ts = $now - 86400; break;
	case "7d":   $from_ts = $now - 604800; break;
	case "30d":  $from_ts = $now - 2592000; break;
	case "custom":
		$from_ts = $custom_from ? strtotime($custom_from . " 00:00:00") : ($now - 86400);
		$now = $custom_to ? strtotime($custom_to . " 23:59:59") : $now;
		if ($from_ts === false) {
			$from_ts = $now - 86400;
		}
		break;
	default:
		$from_ts = $now - 86400;
		break;
}
$to_ts = $now;
$granularity = layer7_reports_granularity_for_range($from_ts, $to_ts);
$rpt_cfg = layer7_reports_config();
$rpt_enabled = !empty($rpt_cfg["enabled"]);
$rpt_detail_enabled = !empty($rpt_cfg["event_log_enabled"]);
$rpt_event_retention = (int)($rpt_cfg["event_retention_days"] ?? 15);
$rpt_detail_ifaces = layer7_reports_normalize_interfaces(
	$rpt_cfg["event_interfaces"] ?? array());
$detail_cutoff_ts = time() - ($rpt_event_retention * 86400);
$detail_range_truncated = ($from_ts < $detail_cutoff_ts);
$filters_empty = layer7_reports_filters_empty($filters);
$use_history_summary = (($rpt_enabled && !$rpt_detail_enabled) ||
	($rpt_enabled && $filters_empty && $detail_range_truncated) ||
	(!$rpt_enabled && !$rpt_detail_enabled));

$db_ready = layer7_reports_db_available();
$ingest_failed = false;
if ($db_ready && $rpt_detail_enabled) {
	$ing = layer7_reports_ingest_log_incremental();
	if (!is_array($ing) || empty($ing["ok"])) {
		$ingest_failed = true;
	}
}
$summary = (!$use_history_summary && $db_ready) ?
	layer7_reports_fetch_summary($from_ts, $to_ts, $filters) : null;
$timeline = (!$use_history_summary && $db_ready) ?
	layer7_reports_fetch_timeline($from_ts, $to_ts, $granularity, $filters) : array();
$top_devices = ($db_ready && $rpt_detail_enabled) ?
	layer7_reports_fetch_top_devices($from_ts, $to_ts, $filters, 15) : array();
$top_sites = ($db_ready && $rpt_detail_enabled) ?
	layer7_reports_fetch_top_sites($from_ts, $to_ts, $filters, 20) : array();
$events_page = ($db_ready && $rpt_detail_enabled) ?
	layer7_reports_fetch_events($from_ts, $to_ts, $filters, $page, $page_size) : array(
	"rows" => array(), "total" => 0, "page" => 1, "page_size" => $page_size
);

if (!$db_ready || $summary === null) {
	$history = layer7_reports_load_history($from_ts, $to_ts);
	$traffic = layer7_reports_aggregate_traffic($history, $granularity);
	$total_classified_fallback = 0;
	$total_blocked_fallback = 0;
	$total_allowed_fallback = 0;
	foreach ($traffic as $t) {
		$total_classified_fallback += (int)($t["classified"] ?? 0);
		$total_blocked_fallback += (int)($t["blocked"] ?? 0);
		$total_allowed_fallback += (int)($t["allowed"] ?? 0);
	}
	$summary = array(
		"total_events" => $total_classified_fallback,
		"blocked_events" => $total_blocked_fallback,
		"allowed_events" => $total_allowed_fallback,
		"monitor_events" => max(0, $total_classified_fallback - $total_blocked_fallback - $total_allowed_fallback),
		"unique_devices" => 0,
		"unique_sites" => 0
	);
	$timeline = array();
	foreach ($traffic as $t) {
		$timeline[] = array(
			"ts" => (int)$t["ts"],
			"total_events" => (int)($t["classified"] ?? 0),
			"blocked_events" => (int)($t["blocked"] ?? 0),
			"allowed_events" => (int)($t["allowed"] ?? 0)
		);
	}
}

$rows = $events_page["rows"];
$total_rows = (int)$events_page["total"];
$total_pages = max(1, (int)ceil($total_rows / $page_size));

$total_events = (int)($summary["total_events"] ?? 0);
$blocked_events = (int)($summary["blocked_events"] ?? 0);
$allowed_events = (int)($summary["allowed_events"] ?? 0);
$monitor_events = (int)($summary["monitor_events"] ?? 0);
$unique_devices = (int)($summary["unique_devices"] ?? 0);
$unique_sites = (int)($summary["unique_sites"] ?? 0);
$block_rate = $total_events > 0 ? round(($blocked_events / $total_events) * 100, 1) : 0;

$period_label = date("d/m/Y H:i", $from_ts) . " - " . date("d/m/Y H:i", $to_ts);

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Relatorios"));
include("head.inc");
$l7_clear_reports_confirm = json_encode(
	l7_t('Apagar o banco detalhado e o historico executivo? Os arquivos rotativos permanecem.'),
	JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
?>
<?php layer7_render_tabs("reports"); ?>

<?php layer7_render_messages(); ?>

<?php if ($clear_msg !== "") { ?>
<div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($clear_msg); ?></div>
<?php } ?>

<div id="l7-reports-root">

<?php
$_rpt_notices = array();
if (!$db_ready) {
	$_rpt_notices[] = l7_t("SQLite indisponivel.");
}
if (!$rpt_enabled) {
	$_rpt_notices[] = l7_t("Historico executivo desactivado.");
}
if (!$rpt_detail_enabled) {
	$_rpt_notices[] = l7_t("Log detalhado desactivado.");
}
if ($ingest_failed) {
	$_rpt_notices[] = l7_t("Coleta incremental falhou.");
}
if ($rpt_detail_enabled && !empty($rpt_detail_ifaces)) {
	$_rpt_notices[] = l7_t("Log limitado a: ") . implode(", ", $rpt_detail_ifaces);
}
if ($rpt_enabled && $rpt_detail_enabled && $detail_range_truncated) {
	$_rpt_notices[] = sprintf(l7_t("Retencao: %d dias."), $rpt_event_retention);
}
if (!empty($_rpt_notices)) { ?>
<div class="alert alert-warning" role="status">
	<i class="fa fa-info-circle"></i> <?= implode(" &middot; ", array_map('htmlspecialchars', $_rpt_notices)); ?>
</div>
<?php } ?>

<div class="panel panel-default" id="l7-reports-filters">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Filtros e exportacao"); ?></h2>
	</div>
	<div class="panel-body">
<form method="get" class="form-horizontal" id="l7r-filters-form">
	<div class="form-group">
		<label class="col-sm-2 control-label"><?= l7_t("Periodo"); ?></label>
		<div class="col-sm-10">
			<div class="btn-group" role="group">
		<?php
		$ranges = array("1h" => "1h", "6h" => "6h", "24h" => "24h", "7d" => "7d", "30d" => "30d");
		foreach ($ranges as $rk => $rl) {
			$cls = ($range === $rk) ? "btn btn-xs btn-primary" : "btn btn-xs btn-default";
			echo '<a class="' . $cls . '" href="?range=' . htmlspecialchars($rk) . '">' . htmlspecialchars($rl) . '</a>';
		}
		?>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-from"><?= l7_t("Inicio"); ?></label>
		<div class="col-sm-4">
		<input type="date" name="from" id="l7r-filter-from" class="form-control input-sm" value="<?= htmlspecialchars($custom_from ?: date("Y-m-d", $from_ts)); ?>">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-to"><?= l7_t("Fim"); ?></label>
		<div class="col-sm-4">
		<input type="date" name="to" id="l7r-filter-to" class="form-control input-sm" value="<?= htmlspecialchars($custom_to ?: date("Y-m-d", $to_ts)); ?>">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-src_ip"><?= l7_t("Dispositivo (IP)"); ?></label>
		<div class="col-sm-4">
		<input type="text" name="src_ip" id="l7r-filter-src_ip" class="form-control input-sm" placeholder="192.168.10.50" value="<?= htmlspecialchars($filters["src_ip"]); ?>">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-host"><?= l7_t("Site"); ?></label>
		<div class="col-sm-4">
		<input type="text" name="host" id="l7r-filter-host" class="form-control input-sm" placeholder="exemplo.com" value="<?= htmlspecialchars($filters["host"]); ?>">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-action"><?= l7_t("Resultado"); ?></label>
		<div class="col-sm-4">
		<select name="action" id="l7r-filter-action" class="form-control input-sm">
			<option value=""><?= l7_t("Todos"); ?></option>
			<option value="block" <?= $filters["action"] === "block" ? 'selected' : ''; ?>><?= l7_t("Bloqueado"); ?></option>
			<option value="allow" <?= $filters["action"] === "allow" ? 'selected' : ''; ?>><?= l7_t("Permitido"); ?></option>
			<option value="monitor" <?= $filters["action"] === "monitor" ? 'selected' : ''; ?>><?= l7_t("Monitorado"); ?></option>
		</select>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label" for="l7r-filter-q"><?= l7_t("Pesquisa livre"); ?></label>
		<div class="col-sm-6">
		<input type="text" name="q" id="l7r-filter-q" class="form-control input-sm" placeholder="<?= l7_t("app, categoria, politica..."); ?>" value="<?= htmlspecialchars($filters["q"]); ?>">
		</div>
	</div>
	<input type="hidden" name="range" value="custom">
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
		<button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> <?= l7_t("Aplicar filtros"); ?></button>
		<a href="layer7_reports.php?range=24h" class="btn btn-default btn-sm"><?= l7_t("Limpar"); ?></a>
		</div>
	</div>
</form>

<div id="l7-tools" class="clearfix">
	<div class="pull-left">
		<a href="layer7_reports_export.php?format=html&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">HTML</a>
		<a href="layer7_reports_export.php?format=csv&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">CSV</a>
		<a href="layer7_reports_export.php?format=json&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">JSON</a>
	</div>
	<form method="post" action="layer7_reports.php#l7-tools" class="pull-right"
		onsubmit='return confirm(<?= $l7_clear_reports_confirm; ?>);'>
		<button type="submit" name="clear_all_reports" value="1" class="btn btn-sm btn-danger">
			<i class="fa fa-trash"></i> <?= l7_t("Limpar dados de relatorios"); ?>
		</button>
	</form>
</div>
	</div>
</div>

<div class="panel panel-default" id="l7-reports-summary">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Resumo"); ?> <span class="badge"><?= htmlspecialchars($period_label); ?></span></h2>
	</div>
	<div class="panel-body">
<div class="row">
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead text-primary"><?= number_format($total_events); ?></p>
			<p class="text-muted small"><?= l7_t("Tentativas totais"); ?></p>
		</div>
	</div>
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead text-danger"><?= number_format($blocked_events); ?></p>
			<p class="text-muted small"><?= l7_t("Tentativas bloqueadas"); ?></p>
		</div>
	</div>
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead text-success"><?= number_format($allowed_events); ?></p>
			<p class="text-muted small"><?= l7_t("Tentativas permitidas"); ?></p>
		</div>
	</div>
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead"><?= $block_rate; ?>%</p>
			<p class="text-muted small"><?= l7_t("Indice de bloqueio"); ?></p>
		</div>
	</div>
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead"><?= number_format($unique_devices); ?></p>
			<p class="text-muted small"><?= l7_t("Dispositivos observados"); ?></p>
		</div>
	</div>
	<div class="col-sm-4 col-md-2">
		<div class="well well-sm text-center">
			<p class="lead"><?= number_format($unique_sites); ?></p>
			<p class="text-muted small"><?= l7_t("Sites observados"); ?></p>
		</div>
	</div>
</div>
	</div>
</div>

<div class="panel panel-default" id="l7-reports-chart">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Evolucao no periodo"); ?></h2>
	</div>
	<div class="panel-body">
	<div id="l7r-chart-wrap">
		<canvas id="timelineChart" height="85"></canvas>
		<div id="l7r-chart-empty" class="alert alert-info hidden" role="status"><?= l7_t("Grafico indisponivel (biblioteca offline ausente ou sem dados no periodo)."); ?></div>
	</div>
	</div>
</div>

<div class="panel panel-default" id="l7-reports-tops">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Tops"); ?></h2>
	</div>
	<div class="panel-body">
<div class="row">
	<div class="col-md-6">
		<h4><?= l7_t("Dispositivos com mais bloqueios"); ?></h4>
		<?php if (!$rpt_detail_enabled) { ?>
		<div class="alert alert-info"><?= l7_t("Esta visao requer log detalhado activo."); ?></div>
		<?php } else { ?>
		<table class="table table-striped table-condensed">
			<thead><tr><th>#</th><th><?= l7_t("Dispositivo"); ?></th><th><?= l7_t("Bloqueios"); ?></th><th><?= l7_t("Total"); ?></th></tr></thead>
			<tbody>
			<?php if (empty($top_devices)) { ?>
				<tr><td colspan="4" class="text-muted"><?= l7_t("Sem dados no periodo."); ?></td></tr>
			<?php } else { foreach ($top_devices as $i => $d) {
				$identity = resolveIdentityByIp($d["src_ip"]);
				$label = $identity["display_name"] !== $d["src_ip"] ? ($identity["display_name"] . " (" . $d["src_ip"] . ")") : $d["src_ip"];
			?>
				<tr>
					<td><?= $i + 1; ?></td>
					<td><?= htmlspecialchars($label); ?></td>
					<td><?= number_format((int)$d["blocked_events"]); ?></td>
					<td><?= number_format((int)$d["total_events"]); ?></td>
				</tr>
			<?php }} ?>
			</tbody>
		</table>
		<?php } ?>
	</div>
	<div class="col-md-6">
		<h4><?= l7_t("Sites mais tentados"); ?></h4>
		<?php if (!$rpt_detail_enabled) { ?>
		<div class="alert alert-info"><?= l7_t("Esta visao requer log detalhado activo."); ?></div>
		<?php } else { ?>
		<table class="table table-striped table-condensed">
			<thead><tr><th>#</th><th><?= l7_t("Site"); ?></th><th><?= l7_t("Bloqueios"); ?></th><th><?= l7_t("Total"); ?></th></tr></thead>
			<tbody>
			<?php if (empty($top_sites)) { ?>
				<tr><td colspan="4" class="text-muted"><?= l7_t("Sem dados no periodo."); ?></td></tr>
			<?php } else { foreach ($top_sites as $i => $s) { ?>
				<tr>
					<td><?= $i + 1; ?></td>
					<td><?= htmlspecialchars($s["host"]); ?></td>
					<td><?= number_format((int)$s["blocked_events"]); ?></td>
					<td><?= number_format((int)$s["total_events"]); ?></td>
				</tr>
			<?php }} ?>
			</tbody>
		</table>
		<?php } ?>
	</div>
</div>
	</div>
</div>

<div class="panel panel-default" id="l7-reports-events">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Eventos detalhados"); ?></h2>
	</div>
	<div class="panel-body">
	<?php if (!$rpt_detail_enabled) { ?>
		<div class="alert alert-info"><?= l7_t("Eventos detalhados estao desactivados neste appliance. Active o log detalhado em Definicoes para pesquisa operacional."); ?></div>
	<?php } else { ?>
	<table class="table table-striped table-condensed">
		<thead>
			<tr>
				<th><?= l7_t("Data/Hora"); ?></th>
				<th><?= l7_t("Dispositivo"); ?></th>
				<th><?= l7_t("Site"); ?></th>
				<th><?= l7_t("Aplicacao"); ?></th>
				<th><?= l7_t("Resultado"); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if (empty($rows)) { ?>
			<tr><td colspan="5" class="text-muted"><?= l7_t("Sem eventos para os filtros seleccionados."); ?></td></tr>
		<?php } else { foreach ($rows as $ev) {
			$identity = resolveIdentityByIp($ev["src_ip"]);
			$disp = $identity["display_name"] !== $ev["src_ip"] ? ($identity["display_name"] . " (" . $ev["src_ip"] . ")") : $ev["src_ip"];
		?>
			<tr>
				<td><?= htmlspecialchars($ev["ts_text"]); ?></td>
				<td><?= htmlspecialchars($disp); ?></td>
				<td>
					<?= htmlspecialchars($ev["host"] ?: "-"); ?>
					<?php if (!empty($ev["host_inferred"])) { ?>
						<span class="label label-warning"><?= l7_t("Host inferido (DNS)"); ?></span>
					<?php } ?>
				</td>
				<td><?= htmlspecialchars($ev["app"] ?: "-"); ?></td>
				<td>
					<?php
					$a = strtolower((string)$ev["action"]);
					$badge = "default";
					if ($a === "block") $badge = "danger";
					if ($a === "allow") $badge = "success";
					if ($a === "monitor") $badge = "info";
					?>
					<span class="label label-<?= $badge; ?>"><?= htmlspecialchars($a === "" ? "monitor" : $a); ?></span>
				</td>
			</tr>
		<?php }} ?>
		</tbody>
	</table>

	<?php if ($total_pages > 1) {
		$query = $_GET;
		$compact_pages = layer7_reports_compact_pages($page, $total_pages, 2);
		$last_drawn = 0;
		?>
		<nav>
			<ul class="pagination pagination-sm">
				<?php if ($page > 1) {
					$query["page"] = $page - 1;
					echo '<li><a href="?' . htmlspecialchars(http_build_query($query)) . '">&laquo;</a></li>';
				}
				foreach ($compact_pages as $p) {
					if ($last_drawn !== 0 && ($p - $last_drawn) > 1) {
						echo '<li class="disabled"><span>...</span></li>';
					}
					$query["page"] = $p;
					$cls = ($p === $page) ? ' class="active"' : '';
					echo '<li' . $cls . '><a href="?' . htmlspecialchars(http_build_query($query)) . '">' . $p . '</a></li>';
					$last_drawn = $p;
				}
				if ($page < $total_pages) {
					$query["page"] = $page + 1;
					echo '<li><a href="?' . htmlspecialchars(http_build_query($query)) . '">&raquo;</a></li>';
				} ?>
			</ul>
		</nav>
	<?php } ?>
	<?php } ?>
	</div>
</div>

</div>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>

<script src="/packages/layer7/js/chart.umd.min.js"></script>
<script>
var timeline = <?= json_encode($timeline); ?>;
var chartCanvas = document.getElementById('timelineChart');
var chartEmpty = document.getElementById('l7r-chart-empty');
if (typeof Chart !== 'undefined' && timeline.length > 0 && chartCanvas) {
	var labels = timeline.map(function(x) {
		var dt = new Date(x.ts * 1000);
		return dt.toLocaleString();
	});
	new Chart(chartCanvas, {
		type: 'line',
		data: {
			labels: labels,
			datasets: [
			{ label: <?= json_encode(l7_t("Bloqueados")) ?>, data: timeline.map(function(x){return x.blocked_events;}), borderColor: '#d9534f', backgroundColor: 'rgba(217,83,79,0.08)', fill: true, tension: 0.25, pointRadius: 2 },
			{ label: <?= json_encode(l7_t("Permitidos")) ?>, data: timeline.map(function(x){return x.allowed_events;}), borderColor: '#5cb85c', backgroundColor: 'rgba(92,184,92,0.08)', fill: true, tension: 0.25, pointRadius: 2 },
			{ label: <?= json_encode(l7_t("Total")) ?>, data: timeline.map(function(x){return x.total_events;}), borderColor: '#337ab7', backgroundColor: 'rgba(51,122,183,0.08)', fill: true, tension: 0.25, pointRadius: 2 }
			]
		},
		options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
	});
} else {
	if (chartCanvas) { chartCanvas.classList.add('hidden'); }
	if (chartEmpty) { chartEmpty.classList.remove('hidden'); }
}
</script>

<?php include("foot.inc"); ?>
