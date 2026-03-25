<?php
##|+PRIV
##|*IDENT=page-services-layer7-reports
##|*NAME=Services: Layer 7 (reports)
##|*DESCR=Layer 7 executive reports.
##|*MATCH=layer7_reports.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

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

$db_ready = layer7_reports_db_available();
$summary = $db_ready ? layer7_reports_fetch_summary($from_ts, $to_ts, $filters) : null;
$timeline = $db_ready ? layer7_reports_fetch_timeline($from_ts, $to_ts, $granularity, $filters) : array();
$top_devices = $db_ready ? layer7_reports_fetch_top_devices($from_ts, $to_ts, $filters, 15) : array();
$top_sites = $db_ready ? layer7_reports_fetch_top_sites($from_ts, $to_ts, $filters, 20) : array();
$events_page = $db_ready ? layer7_reports_fetch_events($from_ts, $to_ts, $filters, $page, $page_size) : array(
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
$exec_summary = array();
$exec_summary[] = "No periodo seleccionado, foram registadas " . number_format($total_events) . " tentativas de acesso.";
$exec_summary[] = number_format($blocked_events) . " tentativas foram bloqueadas (" . $block_rate . "% do total).";
$exec_summary[] = "Foram observados " . number_format($unique_devices) . " dispositivos e " . number_format($unique_sites) . " sites distintos.";

$pgtitle = array("Services", "Layer 7", l7_t("Relatorios"));
include("head.inc");
layer7_render_styles();
?>
<style>
.l7r-cards{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.l7r-card{flex:1;min-width:170px;background:#f7f9fc;border:1px solid #e0e5ec;border-radius:6px;padding:14px;text-align:center}
.l7r-val{font-size:26px;font-weight:700}
.l7r-label{font-size:12px;color:#777}
.l7r-block .l7r-val{color:#d9534f}
.l7r-allow .l7r-val{color:#5cb85c}
.l7r-chart{background:#fff;border:1px solid #e5e5e5;border-radius:5px;padding:14px;margin-bottom:18px}
.l7r-section{margin-top:26px}
.l7r-title{font-size:20px;font-weight:600;margin:0 0 12px}
.l7r-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px}
.l7r-filters .form-group{margin-bottom:0}
.l7r-summary{background:#fbfbfb;border:1px solid #e5e5e5;padding:12px 14px;border-radius:6px}
.l7r-summary ul{margin:0;padding-left:20px}
.l7r-summary li{margin:4px 0}
</style>

<div class="panel panel-default layer7-page">
<div class="panel-heading">
	<h2 class="panel-title">Layer 7 — <?= l7_t("Relatorios executivos"); ?></h2>
</div>
<div class="panel-body">
<?php layer7_render_tabs("reports"); ?>
<div class="layer7-content">
<?php layer7_render_messages(); ?>

<?php if (!$db_ready) { ?>
	<div class="alert alert-warning">
		<?= l7_t("SQLite nao esta disponivel neste ambiente. O modulo executivo de relatorios requer suporte SQLite no PHP."); ?>
	</div>
<?php } ?>

<form method="get" class="l7r-filters">
	<div class="form-group">
		<label><?= l7_t("Periodo"); ?></label><br>
		<?php
		$ranges = array("1h" => "1h", "6h" => "6h", "24h" => "24h", "7d" => "7d", "30d" => "30d");
		foreach ($ranges as $rk => $rl) {
			$cls = ($range === $rk) ? "btn btn-xs btn-primary" : "btn btn-xs btn-default";
			echo '<a class="' . $cls . '" style="margin-right:4px;" href="?range=' . $rk . '">' . htmlspecialchars($rl) . '</a>';
		}
		?>
	</div>
	<div class="form-group">
		<label><?= l7_t("Inicio"); ?></label>
		<input type="date" name="from" class="form-control input-sm" value="<?= htmlspecialchars($custom_from ?: date("Y-m-d", $from_ts)); ?>">
	</div>
	<div class="form-group">
		<label><?= l7_t("Fim"); ?></label>
		<input type="date" name="to" class="form-control input-sm" value="<?= htmlspecialchars($custom_to ?: date("Y-m-d", $to_ts)); ?>">
	</div>
	<div class="form-group">
		<label><?= l7_t("Dispositivo (IP)"); ?></label>
		<input type="text" name="src_ip" class="form-control input-sm" placeholder="192.168.10.50" value="<?= htmlspecialchars($filters["src_ip"]); ?>">
	</div>
	<div class="form-group">
		<label><?= l7_t("Site"); ?></label>
		<input type="text" name="host" class="form-control input-sm" placeholder="exemplo.com" value="<?= htmlspecialchars($filters["host"]); ?>">
	</div>
	<div class="form-group">
		<label><?= l7_t("Resultado"); ?></label>
		<select name="action" class="form-control input-sm">
			<option value=""><?= l7_t("Todos"); ?></option>
			<option value="block" <?= $filters["action"] === "block" ? 'selected' : ''; ?>><?= l7_t("Bloqueado"); ?></option>
			<option value="allow" <?= $filters["action"] === "allow" ? 'selected' : ''; ?>><?= l7_t("Permitido"); ?></option>
			<option value="monitor" <?= $filters["action"] === "monitor" ? 'selected' : ''; ?>><?= l7_t("Monitorado"); ?></option>
		</select>
	</div>
	<div class="form-group">
		<label><?= l7_t("Pesquisa livre"); ?></label>
		<input type="text" name="q" class="form-control input-sm" placeholder="<?= l7_t("app, categoria, politica..."); ?>" value="<?= htmlspecialchars($filters["q"]); ?>">
	</div>
	<input type="hidden" name="range" value="custom">
	<div class="form-group">
		<button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> <?= l7_t("Aplicar filtros"); ?></button>
		<a href="layer7_reports.php?range=24h" class="btn btn-default btn-sm"><?= l7_t("Limpar"); ?></a>
	</div>
</form>

<div style="margin-bottom:12px;">
	<a href="layer7_reports_export.php?format=html&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">HTML</a>
	<a href="layer7_reports_export.php?format=csv&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">CSV</a>
	<a href="layer7_reports_export.php?format=json&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d', $to_ts)) ?>&src_ip=<?= urlencode($filters["src_ip"]) ?>&host=<?= urlencode($filters["host"]) ?>&action=<?= urlencode($filters["action"]) ?>&q=<?= urlencode($filters["q"]) ?>" class="btn btn-sm btn-default">JSON</a>
</div>

<div class="l7r-summary">
	<strong><?= l7_t("Resumo executivo"); ?> (<?= htmlspecialchars($period_label); ?>)</strong>
	<ul>
		<?php foreach ($exec_summary as $line) { ?>
		<li><?= htmlspecialchars($line); ?></li>
		<?php } ?>
	</ul>
</div>

<div class="l7r-cards">
	<div class="l7r-card"><div class="l7r-val"><?= number_format($total_events); ?></div><div class="l7r-label"><?= l7_t("Tentativas totais"); ?></div></div>
	<div class="l7r-card l7r-block"><div class="l7r-val"><?= number_format($blocked_events); ?></div><div class="l7r-label"><?= l7_t("Tentativas bloqueadas"); ?></div></div>
	<div class="l7r-card l7r-allow"><div class="l7r-val"><?= number_format($allowed_events); ?></div><div class="l7r-label"><?= l7_t("Tentativas permitidas"); ?></div></div>
	<div class="l7r-card"><div class="l7r-val"><?= $block_rate; ?>%</div><div class="l7r-label"><?= l7_t("Indice de bloqueio"); ?></div></div>
	<div class="l7r-card"><div class="l7r-val"><?= number_format($unique_devices); ?></div><div class="l7r-label"><?= l7_t("Dispositivos observados"); ?></div></div>
	<div class="l7r-card"><div class="l7r-val"><?= number_format($unique_sites); ?></div><div class="l7r-label"><?= l7_t("Sites observados"); ?></div></div>
</div>

<div class="l7r-section">
	<h3 class="l7r-title"><?= l7_t("Evolucao no periodo"); ?></h3>
	<div class="l7r-chart"><canvas id="timelineChart" height="85"></canvas></div>
</div>

<div class="row l7r-section">
	<div class="col-md-6">
		<h3 class="l7r-title"><?= l7_t("Dispositivos com mais bloqueios"); ?></h3>
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
	</div>
	<div class="col-md-6">
		<h3 class="l7r-title"><?= l7_t("Sites mais tentados"); ?></h3>
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
	</div>
</div>

<div class="l7r-section">
	<h3 class="l7r-title"><?= l7_t("Eventos detalhados"); ?></h3>
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
				<td><?= htmlspecialchars($ev["host"] ?: "-"); ?></td>
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
		?>
		<nav>
			<ul class="pagination pagination-sm">
				<?php for ($p = 1; $p <= $total_pages; $p++) {
					$query["page"] = $p;
					$cls = ($p === $page) ? ' class="active"' : '';
					echo '<li' . $cls . '><a href="?' . htmlspecialchars(http_build_query($query)) . '">' . $p . '</a></li>';
				} ?>
			</ul>
		</nav>
	<?php } ?>
</div>

<?php layer7_render_footer(); ?>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
var timeline = <?= json_encode($timeline); ?>;
if (typeof Chart !== 'undefined' && timeline.length > 0) {
	var labels = timeline.map(function(x) {
		var dt = new Date(x.ts * 1000);
		return dt.toLocaleString();
	});
	new Chart(document.getElementById('timelineChart'), {
		type: 'line',
		data: {
			labels: labels,
			datasets: [
				{ label: '<?= l7_t("Bloqueados"); ?>', data: timeline.map(function(x){return x.blocked_events;}), borderColor: '#d9534f', backgroundColor: 'rgba(217,83,79,0.08)', fill: true, tension: 0.25, pointRadius: 2 },
				{ label: '<?= l7_t("Permitidos"); ?>', data: timeline.map(function(x){return x.allowed_events;}), borderColor: '#5cb85c', backgroundColor: 'rgba(92,184,92,0.08)', fill: true, tension: 0.25, pointRadius: 2 },
				{ label: '<?= l7_t("Total"); ?>', data: timeline.map(function(x){return x.total_events;}), borderColor: '#337ab7', backgroundColor: 'rgba(51,122,183,0.08)', fill: true, tension: 0.25, pointRadius: 2 }
			]
		},
		options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
	});
}
</script>

<?php include("foot.inc"); ?>
