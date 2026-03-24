<?php
##|+PRIV
##|*IDENT=page-services-layer7-reports-export
##|*NAME=Services: Layer 7 (reports export)
##|*DESCR=Export Layer 7 reports.
##|*MATCH=layer7_reports_export.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$format = isset($_GET["format"]) ? $_GET["format"] : "csv";
$range = isset($_GET["range"]) ? $_GET["range"] : "24h";
$custom_from = isset($_GET["from"]) ? $_GET["from"] : "";
$custom_to = isset($_GET["to"]) ? $_GET["to"] : "";
$sections = isset($_GET["sections"]) ? explode(",", $_GET["sections"]) : array("traffic", "apps", "clients", "blacklists", "policies");

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
$top_apps = layer7_reports_aggregate_top($history, "top_apps", 50);
$top_sources = layer7_reports_aggregate_top($history, "top_sources", 50);
$top_bl_cats = layer7_reports_aggregate_top($history, "bl_top_cats", 50);

$total_classified = 0; $total_blocked = 0; $total_allowed = 0;
foreach ($traffic as $t) {
	$total_classified += $t["classified"];
	$total_blocked += $t["blocked"];
	$total_allowed += $t["allowed"];
}

$policy_stats = array();
if (in_array("policies", $sections)) {
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
}

$bl_domains = array();
if (in_array("blacklists", $sections)) {
	$bl_log = layer7_reports_parse_log($from_ts, $to_ts, array("type" => "blacklist"));
	foreach ($bl_log as $ev) {
		if (isset($ev["fields"]["domain"])) {
			$d = $ev["fields"]["domain"];
			$c = isset($ev["fields"]["cat"]) ? $ev["fields"]["cat"] : "?";
			if (!isset($bl_domains[$d])) $bl_domains[$d] = array("domain" => $d, "cat" => $c, "count" => 0);
			$bl_domains[$d]["count"]++;
		}
	}
	usort($bl_domains, function($a, $b) { return $b["count"] - $a["count"]; });
}

$period_label = date("Y-m-d H:i", $from_ts) . " — " . date("Y-m-d H:i", $to_ts);
$filename_base = "layer7-report-" . date("Ymd", $from_ts) . "-" . date("Ymd", $to_ts);

if ($format === "json") {
	header("Content-Type: application/json; charset=utf-8");
	header("Content-Disposition: attachment; filename=\"{$filename_base}.json\"");

	$export = array(
		"generated" => date("c"),
		"period" => array("from" => date("c", $from_ts), "to" => date("c", $to_ts)),
		"summary" => array("classified" => $total_classified, "blocked" => $total_blocked, "allowed" => $total_allowed)
	);
	if (in_array("traffic", $sections)) $export["traffic"] = $traffic;
	if (in_array("apps", $sections)) $export["top_apps"] = $top_apps;
	if (in_array("clients", $sections)) $export["top_clients"] = $top_sources;
	if (in_array("blacklists", $sections)) {
		$export["blacklist_categories"] = $top_bl_cats;
		$export["blacklist_domains"] = $bl_domains;
	}
	if (in_array("policies", $sections)) $export["policies"] = $policy_stats;

	echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit;
}

if ($format === "csv") {
	header("Content-Type: text/csv; charset=utf-8");
	header("Content-Disposition: attachment; filename=\"{$filename_base}.csv\"");

	$out = fopen("php://output", "w");

	fputcsv($out, array("Layer7 Report", $period_label));
	fputcsv($out, array());

	if (in_array("traffic", $sections)) {
		fputcsv($out, array("=== Traffic ==="));
		fputcsv($out, array("Timestamp", "Classified", "Blocked", "Allowed"));
		foreach ($traffic as $t) {
			fputcsv($out, array(date("Y-m-d H:i", $t["ts"]), $t["classified"], $t["blocked"], $t["allowed"]));
		}
		fputcsv($out, array());
	}

	if (in_array("apps", $sections) && !empty($top_apps)) {
		fputcsv($out, array("=== Top Apps Blocked ==="));
		fputcsv($out, array("Rank", "App", "Count"));
		foreach ($top_apps as $i => $a) {
			fputcsv($out, array($i + 1, $a["name"], $a["count"]));
		}
		fputcsv($out, array());
	}

	if (in_array("clients", $sections) && !empty($top_sources)) {
		fputcsv($out, array("=== Top Clients Blocked ==="));
		fputcsv($out, array("Rank", "IP", "Count"));
		foreach ($top_sources as $i => $s) {
			fputcsv($out, array($i + 1, $s["name"], $s["count"]));
		}
		fputcsv($out, array());
	}

	if (in_array("blacklists", $sections) && !empty($top_bl_cats)) {
		fputcsv($out, array("=== Blacklist Categories ==="));
		fputcsv($out, array("Category", "Hits"));
		foreach ($top_bl_cats as $c) {
			fputcsv($out, array($c["name"], $c["count"]));
		}
		fputcsv($out, array());
		if (!empty($bl_domains)) {
			fputcsv($out, array("=== Top Blocked Domains ==="));
			fputcsv($out, array("Rank", "Domain", "Category", "Count"));
			foreach ($bl_domains as $i => $d) {
				fputcsv($out, array($i + 1, $d["domain"], $d["cat"], $d["count"]));
			}
			fputcsv($out, array());
		}
	}

	if (in_array("policies", $sections) && !empty($policy_stats)) {
		fputcsv($out, array("=== Policy Report ==="));
		fputcsv($out, array("Policy", "Total", "Blocks", "Tags"));
		foreach ($policy_stats as $ps) {
			fputcsv($out, array($ps["name"], $ps["total"], $ps["block"], $ps["tag"]));
		}
	}

	fclose($out);
	exit;
}

/* HTML report */
header("Content-Type: text/html; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename_base}.html\"");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title>Layer7 — <?= htmlspecialchars($period_label); ?></title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 900px; margin: 0 auto; padding: 30px; color: #333; line-height: 1.5; }
h1 { font-size: 22px; border-bottom: 2px solid #337ab7; padding-bottom: 8px; color: #337ab7; }
h2 { font-size: 17px; margin-top: 32px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
table { width: 100%; border-collapse: collapse; margin: 12px 0 24px; font-size: 13px; }
th, td { border: 1px solid #ddd; padding: 6px 10px; text-align: left; }
th { background: #f5f7fa; font-weight: 600; }
tr:nth-child(even) { background: #fafbfc; }
.summary { display: flex; gap: 16px; margin: 16px 0 24px; }
.summary-card { flex: 1; border: 1px solid #ddd; border-radius: 4px; padding: 12px; text-align: center; }
.summary-card .val { font-size: 24px; font-weight: 700; }
.summary-card .lbl { font-size: 12px; color: #777; }
.block .val { color: #d9534f; }
.footer { margin-top: 40px; padding-top: 12px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #999; }
@media print { body { padding: 10px; } }
</style>
</head>
<body>
<h1>Layer7 — Relatorio</h1>
<p><strong>Periodo:</strong> <?= htmlspecialchars($period_label); ?><br>
<strong>Gerado em:</strong> <?= date("Y-m-d H:i:s"); ?></p>

<div class="summary">
	<div class="summary-card"><div class="val"><?= number_format($total_classified); ?></div><div class="lbl">Classificados</div></div>
	<div class="summary-card block"><div class="val"><?= number_format($total_blocked); ?></div><div class="lbl">Bloqueados</div></div>
	<div class="summary-card"><div class="val"><?= number_format($total_allowed); ?></div><div class="lbl">Permitidos</div></div>
	<div class="summary-card"><div class="val"><?= ($total_classified > 0) ? round(($total_blocked / $total_classified) * 100, 1) : 0; ?>%</div><div class="lbl">Taxa bloqueio</div></div>
</div>

<?php if (in_array("traffic", $sections) && !empty($traffic)) { ?>
<h2>Trafego ao longo do tempo</h2>
<table>
<tr><th>Hora</th><th>Classificados</th><th>Bloqueados</th><th>Permitidos</th></tr>
<?php foreach ($traffic as $t) { ?>
<tr><td><?= date("Y-m-d H:i", $t["ts"]); ?></td><td><?= number_format($t["classified"]); ?></td><td><?= number_format($t["blocked"]); ?></td><td><?= number_format($t["allowed"]); ?></td></tr>
<?php } ?>
</table>
<?php } ?>

<?php if (in_array("apps", $sections) && !empty($top_apps)) { ?>
<h2>Top Apps Bloqueadas</h2>
<table>
<tr><th>#</th><th>App</th><th>Bloqueios</th></tr>
<?php foreach ($top_apps as $i => $a) { ?>
<tr><td><?= $i + 1; ?></td><td><?= htmlspecialchars($a["name"]); ?></td><td><?= number_format($a["count"]); ?></td></tr>
<?php } ?>
</table>
<?php } ?>

<?php if (in_array("clients", $sections) && !empty($top_sources)) { ?>
<h2>Top Clientes Bloqueados</h2>
<table>
<tr><th>#</th><th>IP</th><th>Bloqueios</th></tr>
<?php foreach ($top_sources as $i => $s) { ?>
<tr><td><?= $i + 1; ?></td><td><?= htmlspecialchars($s["name"]); ?></td><td><?= number_format($s["count"]); ?></td></tr>
<?php } ?>
</table>
<?php } ?>

<?php if (in_array("blacklists", $sections) && !empty($top_bl_cats)) { ?>
<h2>Blacklists — Categorias</h2>
<table>
<tr><th>Categoria</th><th>Hits</th><th>%</th></tr>
<?php
$bl_total = array_sum(array_column($top_bl_cats, "count"));
foreach ($top_bl_cats as $c) {
	$pct = ($bl_total > 0) ? round(($c["count"] / $bl_total) * 100, 1) : 0;
	echo '<tr><td>' . htmlspecialchars($c["name"]) . '</td><td>' . number_format($c["count"]) . '</td><td>' . $pct . '%</td></tr>';
}
?>
</table>
<?php if (!empty($bl_domains)) { ?>
<h2>Top Dominios Bloqueados</h2>
<table>
<tr><th>#</th><th>Dominio</th><th>Categoria</th><th>Bloqueios</th></tr>
<?php foreach (array_slice($bl_domains, 0, 50) as $i => $d) { ?>
<tr><td><?= $i + 1; ?></td><td><?= htmlspecialchars($d["domain"]); ?></td><td><?= htmlspecialchars($d["cat"]); ?></td><td><?= number_format($d["count"]); ?></td></tr>
<?php } ?>
</table>
<?php } ?>
<?php } ?>

<?php if (in_array("policies", $sections) && !empty($policy_stats)) { ?>
<h2>Relatorio por Politica</h2>
<table>
<tr><th>Politica</th><th>Total</th><th>Bloqueios</th><th>Tags</th></tr>
<?php foreach ($policy_stats as $ps) { ?>
<tr><td><?= htmlspecialchars($ps["name"]); ?></td><td><?= number_format($ps["total"]); ?></td><td><?= number_format($ps["block"]); ?></td><td><?= number_format($ps["tag"]); ?></td></tr>
<?php } ?>
</table>
<?php } ?>

<div class="footer">
	Gerado por Layer7 para pfSense CE &mdash; <a href="https://www.systemup.inf.br">Systemup</a> Solucao em Tecnologia
</div>
</body>
</html>
<?php exit; ?>
