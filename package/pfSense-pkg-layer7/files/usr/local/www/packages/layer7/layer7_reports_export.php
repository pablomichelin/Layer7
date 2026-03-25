<?php
##|+PRIV
##|*IDENT=page-services-layer7-reports-export
##|*NAME=Services: Layer 7 (reports export)
##|*DESCR=Export Layer 7 executive reports.
##|*MATCH=layer7_reports_export.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$format = isset($_GET["format"]) ? (string)$_GET["format"] : "html";
$range = isset($_GET["range"]) ? (string)$_GET["range"] : "24h";
$custom_from = isset($_GET["from"]) ? (string)$_GET["from"] : "";
$custom_to = isset($_GET["to"]) ? (string)$_GET["to"] : "";
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
	default: $from_ts = $now - 86400; break;
}
$to_ts = $now;
$period_label = date("Y-m-d H:i", $from_ts) . " - " . date("Y-m-d H:i", $to_ts);
$filename_base = "layer7-executive-" . date("Ymd", $from_ts) . "-" . date("Ymd", $to_ts);

$summary = layer7_reports_fetch_summary($from_ts, $to_ts, $filters);
$timeline = layer7_reports_fetch_timeline($from_ts, $to_ts, layer7_reports_granularity_for_range($from_ts, $to_ts), $filters);
$top_devices = layer7_reports_fetch_top_devices($from_ts, $to_ts, $filters, 30);
$top_sites = layer7_reports_fetch_top_sites($from_ts, $to_ts, $filters, 50);
$events = layer7_reports_fetch_events($from_ts, $to_ts, $filters, 1, 2000);
$rows = $events["rows"];

if ($summary === null) {
	$history = layer7_reports_load_history($from_ts, $to_ts);
	$traffic = layer7_reports_aggregate_traffic($history, layer7_reports_granularity_for_range($from_ts, $to_ts));
	$classified = 0; $blocked = 0; $allowed = 0;
	foreach ($traffic as $t) {
		$classified += (int)($t["classified"] ?? 0);
		$blocked += (int)($t["blocked"] ?? 0);
		$allowed += (int)($t["allowed"] ?? 0);
	}
	$summary = array(
		"total_events" => $classified,
		"blocked_events" => $blocked,
		"allowed_events" => $allowed,
		"monitor_events" => max(0, $classified - $blocked - $allowed),
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

$total_events = (int)($summary["total_events"] ?? 0);
$blocked_events = (int)($summary["blocked_events"] ?? 0);
$allowed_events = (int)($summary["allowed_events"] ?? 0);
$monitor_events = (int)($summary["monitor_events"] ?? 0);
$unique_devices = (int)($summary["unique_devices"] ?? 0);
$unique_sites = (int)($summary["unique_sites"] ?? 0);
$block_rate = $total_events > 0 ? round(($blocked_events / $total_events) * 100, 1) : 0;

if ($format === "json") {
	header("Content-Type: application/json; charset=utf-8");
	header("Content-Disposition: attachment; filename=\"{$filename_base}.json\"");
	echo json_encode(array(
		"generated_at" => date("c"),
		"period" => array("from" => date("c", $from_ts), "to" => date("c", $to_ts)),
		"filters" => $filters,
		"summary" => array(
			"total_events" => $total_events,
			"blocked_events" => $blocked_events,
			"allowed_events" => $allowed_events,
			"monitor_events" => $monitor_events,
			"unique_devices" => $unique_devices,
			"unique_sites" => $unique_sites,
			"block_rate_pct" => $block_rate
		),
		"timeline" => $timeline,
		"top_devices" => $top_devices,
		"top_sites" => $top_sites,
		"events" => $rows
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit;
}

if ($format === "csv") {
	header("Content-Type: text/csv; charset=utf-8");
	header("Content-Disposition: attachment; filename=\"{$filename_base}.csv\"");
	$out = fopen("php://output", "w");

	fputcsv($out, array(l7_t("Relatorio executivo Layer7"), $period_label));
	fputcsv($out, array(l7_t("Total"), $total_events, l7_t("Bloqueados"), $blocked_events, l7_t("Permitidos"), $allowed_events, l7_t("Monitorados"), $monitor_events));
	fputcsv($out, array(l7_t("Dispositivos unicos"), $unique_devices, l7_t("Sites unicos"), $unique_sites, l7_t("Indice de bloqueio %"), $block_rate));
	fputcsv($out, array());

	fputcsv($out, array(l7_t("Dispositivos com maior incidencia")));
	fputcsv($out, array(l7_t("Posicao"), l7_t("Dispositivo"), "IP", l7_t("Bloqueados"), l7_t("Total")));
	foreach ($top_devices as $i => $d) {
		$id = resolveIdentityByIp($d["src_ip"]);
		fputcsv($out, array($i + 1, $id["display_name"], $d["src_ip"], (int)$d["blocked_events"], (int)$d["total_events"]));
	}
	fputcsv($out, array());

	fputcsv($out, array(l7_t("Sites mais tentados")));
	fputcsv($out, array(l7_t("Posicao"), l7_t("Site"), l7_t("Bloqueados"), l7_t("Total")));
	foreach ($top_sites as $i => $s) {
		fputcsv($out, array($i + 1, $s["host"], (int)$s["blocked_events"], (int)$s["total_events"]));
	}
	fputcsv($out, array());

	fputcsv($out, array(l7_t("Eventos detalhados")));
	fputcsv($out, array(l7_t("Data/Hora"), l7_t("Dispositivo"), "IP", l7_t("Site"), l7_t("Aplicacao"), l7_t("Categoria"), l7_t("Resultado"), l7_t("Politica"), l7_t("Interface"), l7_t("Destino")));
	foreach ($rows as $ev) {
		$id = resolveIdentityByIp($ev["src_ip"]);
		fputcsv($out, array(
			$ev["ts_text"],
			$id["display_name"],
			$ev["src_ip"],
			$ev["host"],
			$ev["app"],
			$ev["category"],
			$ev["action"],
			$ev["policy"],
			$ev["iface"],
			$ev["dst_ip"]
		));
	}
	fclose($out);
	exit;
}

header("Content-Type: text/html; charset=utf-8");
header("Content-Disposition: attachment; filename=\"{$filename_base}.html\"");
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(l7_t("Layer7 - Relatorio Executivo")); ?></title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:980px;margin:0 auto;padding:26px;color:#222}
h1{font-size:24px;margin:0 0 10px}
h2{font-size:18px;margin:26px 0 8px}
table{width:100%;border-collapse:collapse;margin:10px 0 20px;font-size:13px}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}
th{background:#f6f8fa}
.cards{display:flex;gap:12px;flex-wrap:wrap;margin:16px 0}
.card{flex:1;min-width:160px;border:1px solid #ddd;border-radius:5px;padding:10px;text-align:center}
.val{font-size:24px;font-weight:700}
.lbl{font-size:12px;color:#666}
.footer{margin-top:30px;padding-top:10px;border-top:1px solid #ddd;color:#777;font-size:11px;text-align:center}
</style>
</head>
<body>
<h1><?= htmlspecialchars(l7_t("Layer7 - Relatorio Executivo")); ?></h1>
<p><strong><?= l7_t("Periodo"); ?>:</strong> <?= htmlspecialchars($period_label); ?><br>
<strong><?= l7_t("Gerado em"); ?>:</strong> <?= date("Y-m-d H:i:s"); ?></p>

<div class="cards">
	<div class="card"><div class="val"><?= number_format($total_events); ?></div><div class="lbl"><?= l7_t("Tentativas totais"); ?></div></div>
	<div class="card"><div class="val" style="color:#d9534f;"><?= number_format($blocked_events); ?></div><div class="lbl"><?= l7_t("Tentativas bloqueadas"); ?></div></div>
	<div class="card"><div class="val" style="color:#5cb85c;"><?= number_format($allowed_events); ?></div><div class="lbl"><?= l7_t("Tentativas permitidas"); ?></div></div>
	<div class="card"><div class="val"><?= $block_rate; ?>%</div><div class="lbl"><?= l7_t("Indice de bloqueio"); ?></div></div>
	<div class="card"><div class="val"><?= number_format($unique_devices); ?></div><div class="lbl"><?= l7_t("Dispositivos observados"); ?></div></div>
	<div class="card"><div class="val"><?= number_format($unique_sites); ?></div><div class="lbl"><?= l7_t("Sites observados"); ?></div></div>
</div>

<h2><?= l7_t("Resumo executivo"); ?></h2>
<ul>
	<li><?= sprintf(l7_t("Foram registadas %s tentativas no periodo."), number_format($total_events)); ?></li>
	<li><?= sprintf(l7_t("%s tentativas foram bloqueadas (%s)."), number_format($blocked_events), $block_rate . "%"); ?></li>
	<li><?= sprintf(l7_t("%s dispositivos tentaram acesso a %s sites."), number_format($unique_devices), number_format($unique_sites)); ?></li>
</ul>

<h2><?= l7_t("Dispositivos com maior incidencia"); ?></h2>
<table>
<tr><th>#</th><th><?= l7_t("Dispositivo"); ?></th><th>IP</th><th><?= l7_t("Bloqueios"); ?></th><th><?= l7_t("Total"); ?></th></tr>
<?php foreach ($top_devices as $i => $d) {
	$id = resolveIdentityByIp($d["src_ip"]); ?>
<tr>
	<td><?= $i + 1; ?></td>
	<td><?= htmlspecialchars($id["display_name"]); ?></td>
	<td><?= htmlspecialchars($d["src_ip"]); ?></td>
	<td><?= number_format((int)$d["blocked_events"]); ?></td>
	<td><?= number_format((int)$d["total_events"]); ?></td>
</tr>
<?php } ?>
</table>

<h2><?= l7_t("Sites mais tentados"); ?></h2>
<table>
<tr><th>#</th><th><?= l7_t("Site"); ?></th><th><?= l7_t("Bloqueios"); ?></th><th><?= l7_t("Total"); ?></th></tr>
<?php foreach ($top_sites as $i => $s) { ?>
<tr>
	<td><?= $i + 1; ?></td>
	<td><?= htmlspecialchars($s["host"]); ?></td>
	<td><?= number_format((int)$s["blocked_events"]); ?></td>
	<td><?= number_format((int)$s["total_events"]); ?></td>
</tr>
<?php } ?>
</table>

<h2><?= l7_t("Eventos detalhados (amostra)"); ?></h2>
<table>
<tr><th><?= l7_t("Data/Hora"); ?></th><th><?= l7_t("Dispositivo"); ?></th><th>IP</th><th><?= l7_t("Site"); ?></th><th><?= l7_t("Aplicacao"); ?></th><th><?= l7_t("Resultado"); ?></th></tr>
<?php foreach (array_slice($rows, 0, 300) as $ev) {
	$id = resolveIdentityByIp($ev["src_ip"]); ?>
<tr>
	<td><?= htmlspecialchars($ev["ts_text"]); ?></td>
	<td><?= htmlspecialchars($id["display_name"]); ?></td>
	<td><?= htmlspecialchars($ev["src_ip"]); ?></td>
	<td><?= htmlspecialchars($ev["host"]); ?></td>
	<td><?= htmlspecialchars($ev["app"]); ?></td>
	<td><?= htmlspecialchars($ev["action"]); ?></td>
</tr>
<?php } ?>
</table>

<div class="footer"><?= l7_t("Gerado por Layer7 para pfSense CE - Systemup Solucao em Tecnologia"); ?></div>
</body>
</html>
<?php exit; ?>
