<?php
##|+PRIV
##|*IDENT=page-services-layer7-reports
##|*NAME=Services: Layer 7 (reports)
##|*DESCR=Layer 7 traffic reports and analytics.
##|*MATCH=layer7_reports.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$input_errors = array();
$savemsg = "";

$range = isset($_GET["range"]) ? $_GET["range"] : "24h";
$custom_from = isset($_GET["from"]) ? $_GET["from"] : "";
$custom_to = isset($_GET["to"]) ? $_GET["to"] : "";
$ip_search = isset($_GET["ip"]) ? trim($_GET["ip"]) : "";

$now = time();
switch ($range) {
	case "1h":   $from_ts = $now - 3600; break;
	case "6h":   $from_ts = $now - 21600; break;
	case "24h":  $from_ts = $now - 86400; break;
	case "7d":   $from_ts = $now - 604800; break;
	case "30d":  $from_ts = $now - 2592000; break;
	case "custom":
		$from_ts = $custom_from ? strtotime($custom_from) : ($now - 86400);
		$now = $custom_to ? strtotime($custom_to . " 23:59:59") : $now;
		if ($from_ts === false) $from_ts = $now - 86400;
		break;
	default:     $from_ts = $now - 86400; break;
}
$to_ts = $now;

$history = layer7_reports_load_history($from_ts, $to_ts);
$granularity = layer7_reports_granularity_for_range($from_ts, $to_ts);
$traffic = layer7_reports_aggregate_traffic($history, $granularity);
$top_apps = layer7_reports_aggregate_top($history, "top_apps", 20);
$top_sources = layer7_reports_aggregate_top($history, "top_sources", 20);
$top_bl_cats = layer7_reports_aggregate_top($history, "bl_top_cats", 20);

$total_classified = 0; $total_blocked = 0; $total_allowed = 0;
foreach ($traffic as $t) {
	$total_classified += $t["classified"];
	$total_blocked += $t["blocked"];
	$total_allowed += $t["allowed"];
}
$block_rate = ($total_classified > 0) ? round(($total_blocked / $total_classified) * 100, 1) : 0;

$ip_events = array();
if ($ip_search !== "" && filter_var($ip_search, FILTER_VALIDATE_IP)) {
	$ip_events = layer7_reports_parse_log($from_ts, $to_ts, array("ip" => $ip_search));
}

$policy_stats = array();
$log_events = layer7_reports_parse_log($from_ts, $to_ts, array("type" => "flow"));
foreach ($log_events as $ev) {
	if (isset($ev["fields"]["policy"]) && $ev["fields"]["policy"] !== "-") {
		$pid = $ev["fields"]["policy"];
		if (!isset($policy_stats[$pid])) {
			$policy_stats[$pid] = array("name" => $pid, "block" => 0, "tag" => 0, "total" => 0);
		}
		$policy_stats[$pid]["total"]++;
		$act = isset($ev["fields"]["action"]) ? $ev["fields"]["action"] : "";
		if ($act === "block") $policy_stats[$pid]["block"]++;
		elseif ($act === "tag") $policy_stats[$pid]["tag"]++;
	}
}
usort($policy_stats, function($a, $b) { return $b["total"] - $a["total"]; });

$bl_domains = array();
$bl_log = layer7_reports_parse_log($from_ts, $to_ts, array("type" => "blacklist"));
foreach ($bl_log as $ev) {
	if (isset($ev["fields"]["domain"])) {
		$d = $ev["fields"]["domain"];
		$c = isset($ev["fields"]["cat"]) ? $ev["fields"]["cat"] : "?";
		if (!isset($bl_domains[$d])) {
			$bl_domains[$d] = array("domain" => $d, "cat" => $c, "count" => 0);
		}
		$bl_domains[$d]["count"]++;
	}
}
usort($bl_domains, function($a, $b) { return $b["count"] - $a["count"]; });
$bl_domains = array_slice($bl_domains, 0, 50);

$has_data = count($history) > 0;

$pgtitle = array("Services", "Layer 7", l7_t("Relatorios"));
include("head.inc");
layer7_render_styles();
?>
<style>
.l7r-cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
.l7r-card { flex: 1; min-width: 180px; background: #f7f9fc; border: 1px solid #e0e5ec; border-radius: 6px; padding: 16px 20px; text-align: center; }
.l7r-card .l7r-val { font-size: 28px; font-weight: 700; color: #333; }
.l7r-card .l7r-label { font-size: 13px; color: #777; margin-top: 4px; }
.l7r-card.l7r-block .l7r-val { color: #d9534f; }
.l7r-card.l7r-rate .l7r-val { color: #f0ad4e; }
.l7r-chart-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 4px; padding: 16px; margin-bottom: 24px; }
.l7r-period { margin-bottom: 20px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.l7r-period .btn.active { font-weight: 700; }
.l7r-section { margin-top: 32px; }
.l7r-section-title { font-size: 20px; font-weight: 600; margin: 0 0 14px; }
.l7r-no-data { text-align: center; padding: 40px 20px; color: #999; }
.l7r-export-bar { margin-bottom: 18px; display: flex; gap: 8px; flex-wrap: wrap; }
</style>

<div class="panel panel-default layer7-page">
<div class="panel-heading">
	<h2 class="panel-title">Layer 7 — <?= l7_t("Relatorios"); ?></h2>
</div>
<div class="panel-body">
<?php layer7_render_tabs("reports"); ?>
<div class="layer7-content">
<?php layer7_render_messages(); ?>

<!-- Period filter -->
<div class="l7r-period">
	<span style="font-weight:600; margin-right:4px;"><?= l7_t("Periodo"); ?>:</span>
	<?php
	$ranges = array("1h" => "1h", "6h" => "6h", "24h" => "24h", "7d" => "7 " . l7_t("dias"), "30d" => "30 " . l7_t("dias"));
	foreach ($ranges as $rk => $rl) {
		$cls = ($range === $rk) ? "btn btn-sm btn-primary active" : "btn btn-sm btn-default";
		echo '<a href="?range=' . $rk . '" class="' . $cls . '">' . htmlspecialchars($rl) . '</a>';
	}
	?>
	<span style="margin-left:8px; color:#999;">|</span>
	<form method="get" style="display:inline-flex; align-items:center; gap:4px; margin:0;">
		<input type="hidden" name="range" value="custom">
		<input type="date" name="from" class="form-control input-sm" style="width:140px;" value="<?= htmlspecialchars($custom_from ?: date('Y-m-d', $from_ts)); ?>">
		<span>—</span>
		<input type="date" name="to" class="form-control input-sm" style="width:140px;" value="<?= htmlspecialchars($custom_to ?: date('Y-m-d')); ?>">
		<button type="submit" class="btn btn-sm btn-default"><i class="fa fa-search"></i></button>
	</form>
</div>

<!-- Export bar -->
<div class="l7r-export-bar">
	<a href="layer7_reports_export.php?format=csv&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d')) ?>" class="btn btn-sm btn-default" title="CSV"><i class="fa fa-file-text-o"></i> CSV</a>
	<a href="layer7_reports_export.php?format=html&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d')) ?>" class="btn btn-sm btn-default" title="HTML"><i class="fa fa-file-code-o"></i> HTML</a>
	<a href="layer7_reports_export.php?format=json&range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from ?: date('Y-m-d', $from_ts)) ?>&to=<?= urlencode($custom_to ?: date('Y-m-d')) ?>" class="btn btn-sm btn-default" title="JSON"><i class="fa fa-file-o"></i> JSON</a>
</div>

<?php if (!$has_data) { ?>
<div class="l7r-no-data">
	<i class="fa fa-bar-chart" style="font-size:48px; color:#ccc; display:block; margin-bottom:12px;"></i>
	<p style="font-size:16px;"><?= l7_t("Sem dados historicos para o periodo seleccionado."); ?></p>
	<p style="font-size:13px; color:#aaa;"><?= l7_t("Os dados sao recolhidos automaticamente a cada 5 minutos. Aguarde alguns minutos apos a instalacao."); ?></p>
</div>
<?php } else { ?>

<!-- Summary cards -->
<div class="l7r-cards">
	<div class="l7r-card">
		<div class="l7r-val"><?= number_format($total_classified); ?></div>
		<div class="l7r-label"><?= l7_t("Classificados"); ?></div>
	</div>
	<div class="l7r-card l7r-block">
		<div class="l7r-val"><?= number_format($total_blocked); ?></div>
		<div class="l7r-label"><?= l7_t("Bloqueados"); ?></div>
	</div>
	<div class="l7r-card">
		<div class="l7r-val"><?= number_format($total_allowed); ?></div>
		<div class="l7r-label"><?= l7_t("Permitidos"); ?></div>
	</div>
	<div class="l7r-card l7r-rate">
		<div class="l7r-val"><?= $block_rate; ?>%</div>
		<div class="l7r-label"><?= l7_t("Taxa de bloqueio"); ?></div>
	</div>
</div>

<!-- Traffic chart -->
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-line-chart"></i> <?= l7_t("Trafego ao longo do tempo"); ?></h3>
	<div class="l7r-chart-box">
		<canvas id="trafficChart" height="80"></canvas>
	</div>
</div>

<!-- Top Apps -->
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-ban"></i> <?= l7_t("Top apps bloqueadas"); ?></h3>
	<?php if (empty($top_apps)) { ?>
	<p class="text-muted"><?= l7_t("Nenhuma app bloqueada no periodo."); ?></p>
	<?php } else { ?>
	<div class="row">
		<div class="col-md-6">
			<div class="l7r-chart-box"><canvas id="appsChart" height="120"></canvas></div>
		</div>
		<div class="col-md-6">
			<table class="table table-striped table-condensed">
			<thead><tr><th>#</th><th><?= l7_t("App"); ?></th><th><?= l7_t("Bloqueios"); ?></th></tr></thead>
			<tbody>
			<?php foreach ($top_apps as $i => $a) { ?>
			<tr><td><?= $i + 1; ?></td><td><code><?= htmlspecialchars($a["name"]); ?></code></td><td><?= number_format($a["count"]); ?></td></tr>
			<?php } ?>
			</tbody>
			</table>
		</div>
	</div>
	<?php } ?>
</div>

<!-- Top Clients -->
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-users"></i> <?= l7_t("Top clientes bloqueados"); ?></h3>
	<?php if (empty($top_sources)) { ?>
	<p class="text-muted"><?= l7_t("Nenhum cliente bloqueado no periodo."); ?></p>
	<?php } else { ?>
	<div class="row">
		<div class="col-md-6">
			<div class="l7r-chart-box"><canvas id="clientsChart" height="120"></canvas></div>
		</div>
		<div class="col-md-6">
			<table class="table table-striped table-condensed">
			<thead><tr><th>#</th><th><?= l7_t("IP"); ?></th><th><?= l7_t("Bloqueios"); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ($top_sources as $i => $s) { ?>
			<tr>
				<td><?= $i + 1; ?></td>
				<td><code><?= htmlspecialchars($s["name"]); ?></code></td>
				<td><?= number_format($s["count"]); ?></td>
				<td><a href="?range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from) ?>&to=<?= urlencode($custom_to) ?>&ip=<?= urlencode($s["name"]) ?>#ip-detail" class="btn btn-xs btn-default"><i class="fa fa-search"></i></a></td>
			</tr>
			<?php } ?>
			</tbody>
			</table>
		</div>
	</div>
	<?php } ?>
</div>

<!-- Blacklist categories -->
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-shield"></i> <?= l7_t("Blacklists — categorias"); ?></h3>
	<?php if (empty($top_bl_cats)) { ?>
	<p class="text-muted"><?= l7_t("Nenhum hit de blacklist no periodo."); ?></p>
	<?php } else { ?>
	<div class="row">
		<div class="col-md-5">
			<div class="l7r-chart-box"><canvas id="blCatsChart" height="140"></canvas></div>
		</div>
		<div class="col-md-7">
			<table class="table table-striped table-condensed">
			<thead><tr><th><?= l7_t("Categoria"); ?></th><th><?= l7_t("Hits"); ?></th><th>%</th></tr></thead>
			<tbody>
			<?php
			$bl_total = array_sum(array_column($top_bl_cats, "count"));
			foreach ($top_bl_cats as $cat) {
				$pct = ($bl_total > 0) ? round(($cat["count"] / $bl_total) * 100, 1) : 0;
				echo '<tr><td><code>' . htmlspecialchars($cat["name"]) . '</code></td><td>' . number_format($cat["count"]) . '</td><td>' . $pct . '%</td></tr>';
			}
			?>
			</tbody>
			</table>
		</div>
	</div>
	<?php } ?>
</div>

<!-- Top blocked domains -->
<?php if (!empty($bl_domains)) { ?>
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-globe"></i> <?= l7_t("Top dominios bloqueados"); ?></h3>
	<table class="table table-striped table-condensed">
	<thead><tr><th>#</th><th><?= l7_t("Dominio"); ?></th><th><?= l7_t("Categoria"); ?></th><th><?= l7_t("Bloqueios"); ?></th></tr></thead>
	<tbody>
	<?php foreach ($bl_domains as $i => $d) { ?>
	<tr><td><?= $i + 1; ?></td><td><code><?= htmlspecialchars($d["domain"]); ?></code></td><td><?= htmlspecialchars($d["cat"]); ?></td><td><?= number_format($d["count"]); ?></td></tr>
	<?php } ?>
	</tbody>
	</table>
</div>
<?php } ?>

<!-- Policy report -->
<div class="l7r-section">
	<h3 class="l7r-section-title"><i class="fa fa-gavel"></i> <?= l7_t("Relatorio por politica"); ?></h3>
	<?php if (empty($policy_stats)) { ?>
	<p class="text-muted"><?= l7_t("Nenhuma decisao de politica registada no log para o periodo."); ?></p>
	<?php } else { ?>
	<table class="table table-striped table-condensed">
	<thead><tr><th><?= l7_t("Politica"); ?></th><th><?= l7_t("Total"); ?></th><th><?= l7_t("Bloqueios"); ?></th><th><?= l7_t("Tags"); ?></th></tr></thead>
	<tbody>
	<?php foreach ($policy_stats as $ps) { ?>
	<tr><td><code><?= htmlspecialchars($ps["name"]); ?></code></td><td><?= number_format($ps["total"]); ?></td><td class="text-danger"><?= number_format($ps["block"]); ?></td><td><?= number_format($ps["tag"]); ?></td></tr>
	<?php } ?>
	</tbody>
	</table>
	<?php } ?>
</div>

<!-- IP search -->
<div class="l7r-section" id="ip-detail">
	<h3 class="l7r-section-title"><i class="fa fa-search"></i> <?= l7_t("Consulta por IP"); ?></h3>
	<form method="get" style="display:flex; gap:8px; align-items:center; margin-bottom:14px;">
		<input type="hidden" name="range" value="<?= htmlspecialchars($range); ?>">
		<input type="hidden" name="from" value="<?= htmlspecialchars($custom_from); ?>">
		<input type="hidden" name="to" value="<?= htmlspecialchars($custom_to); ?>">
		<input type="text" name="ip" class="form-control" style="width:200px;" placeholder="<?= l7_t("Ex: 192.168.10.50"); ?>" value="<?= htmlspecialchars($ip_search); ?>">
		<button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> <?= l7_t("Pesquisar"); ?></button>
		<?php if ($ip_search !== "") { ?>
		<a href="?range=<?= urlencode($range) ?>&from=<?= urlencode($custom_from) ?>&to=<?= urlencode($custom_to) ?>" class="btn btn-default"><?= l7_t("Limpar"); ?></a>
		<?php } ?>
	</form>
	<?php if ($ip_search !== "" && filter_var($ip_search, FILTER_VALIDATE_IP)) { ?>
		<?php if (empty($ip_events)) { ?>
		<p class="text-muted"><?= l7_t("Nenhum evento encontrado para"); ?> <code><?= htmlspecialchars($ip_search); ?></code> <?= l7_t("no periodo seleccionado."); ?></p>
		<?php } else { ?>
		<p><strong><?= count($ip_events); ?></strong> <?= l7_t("eventos encontrados para"); ?> <code><?= htmlspecialchars($ip_search); ?></code></p>
		<div class="table-responsive">
		<table class="table table-striped table-condensed" style="font-size:12px;">
		<thead><tr><th><?= l7_t("Data/Hora"); ?></th><th><?= l7_t("Tipo"); ?></th><th><?= l7_t("Detalhes"); ?></th></tr></thead>
		<tbody>
		<?php foreach (array_slice(array_reverse($ip_events), 0, 200) as $ev) { ?>
		<tr>
			<td style="white-space:nowrap;"><?= date("Y-m-d H:i:s", $ev["ts"]); ?></td>
			<td>
				<?php
				$badge = "default";
				if ($ev["type"] === "flow") $badge = "info";
				if ($ev["type"] === "blacklist") $badge = "danger";
				if ($ev["type"] === "dns_block") $badge = "warning";
				if ($ev["type"] === "enforce") $badge = "danger";
				?>
				<span class="label label-<?= $badge; ?>"><?= htmlspecialchars($ev["type"]); ?></span>
			</td>
			<td>
				<?php if (isset($ev["fields"])) {
					$parts = array();
					foreach ($ev["fields"] as $fk => $fv) {
						$parts[] = '<strong>' . htmlspecialchars($fk) . '</strong>=' . htmlspecialchars($fv);
					}
					echo implode(" &nbsp; ", $parts);
				} else {
					echo htmlspecialchars($ev["raw"]);
				} ?>
			</td>
		</tr>
		<?php } ?>
		</tbody>
		</table>
		</div>
		<?php if (count($ip_events) > 200) { ?>
		<p class="text-muted"><?= l7_t("Mostrando os 200 eventos mais recentes de"); ?> <?= count($ip_events); ?> <?= l7_t("total."); ?></p>
		<?php } ?>
		<?php } ?>
	<?php } elseif ($ip_search !== "") { ?>
		<div class="alert alert-warning"><?= l7_t("IP invalido."); ?></div>
	<?php } ?>
</div>

<?php } /* end has_data */ ?>

<?php layer7_render_footer(); ?>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
<?php if ($has_data) { ?>
var trafficData = <?= json_encode($traffic); ?>;
var topApps = <?= json_encode(array_slice($top_apps, 0, 10)); ?>;
var topSources = <?= json_encode(array_slice($top_sources, 0, 10)); ?>;
var blCats = <?= json_encode($top_bl_cats); ?>;

if (typeof Chart !== 'undefined') {
	var tLabels = trafficData.map(function(d) {
		var dt = new Date(d.ts * 1000);
		var granSec = <?= $granularity; ?>;
		if (granSec <= 3600) return dt.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
		if (granSec <= 21600) return dt.toLocaleDateString([], {month:'short', day:'numeric'}) + ' ' + dt.toLocaleTimeString([], {hour:'2-digit'}) + 'h';
		return dt.toLocaleDateString([], {month:'short', day:'numeric'});
	});

	new Chart(document.getElementById('trafficChart'), {
		type: 'line',
		data: {
			labels: tLabels,
			datasets: [
				{ label: '<?= l7_t("Classificados"); ?>', data: trafficData.map(function(d){return d.classified;}), borderColor: '#337ab7', backgroundColor: 'rgba(51,122,183,0.08)', fill: true, tension: 0.3, pointRadius: 2 },
				{ label: '<?= l7_t("Bloqueados"); ?>', data: trafficData.map(function(d){return d.blocked;}), borderColor: '#d9534f', backgroundColor: 'rgba(217,83,79,0.08)', fill: true, tension: 0.3, pointRadius: 2 },
				{ label: '<?= l7_t("Permitidos"); ?>', data: trafficData.map(function(d){return d.allowed;}), borderColor: '#5cb85c', backgroundColor: 'rgba(92,184,92,0.08)', fill: true, tension: 0.3, pointRadius: 2 }
			]
		},
		options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
	});

	if (topApps.length > 0 && document.getElementById('appsChart')) {
		new Chart(document.getElementById('appsChart'), {
			type: 'bar',
			data: {
				labels: topApps.map(function(d){return d.name;}),
				datasets: [{ label: '<?= l7_t("Bloqueios"); ?>', data: topApps.map(function(d){return d.count;}), backgroundColor: '#d9534f' }]
			},
			options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
		});
	}

	if (topSources.length > 0 && document.getElementById('clientsChart')) {
		new Chart(document.getElementById('clientsChart'), {
			type: 'bar',
			data: {
				labels: topSources.map(function(d){return d.name;}),
				datasets: [{ label: '<?= l7_t("Bloqueios"); ?>', data: topSources.map(function(d){return d.count;}), backgroundColor: '#f0ad4e' }]
			},
			options: { indexAxis: 'y', responsive: true, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
		});
	}

	if (blCats.length > 0 && document.getElementById('blCatsChart')) {
		var palette = ['#d9534f','#f0ad4e','#5cb85c','#5bc0de','#337ab7','#9b59b6','#e74c3c','#2ecc71','#3498db','#e67e22'];
		new Chart(document.getElementById('blCatsChart'), {
			type: 'doughnut',
			data: {
				labels: blCats.map(function(d){return d.name;}),
				datasets: [{ data: blCats.map(function(d){return d.count;}), backgroundColor: blCats.map(function(d,i){return palette[i % palette.length];}) }]
			},
			options: { responsive: true, plugins: { legend: { position: 'right' } } }
		});
	}
}
<?php } ?>
</script>

<?php include("foot.inc"); ?>
