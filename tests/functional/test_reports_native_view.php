<?php
/**
 * V10 Relatórios — gate estático view nativa.
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_reports.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }
function has($s, $n) { return strpos($s, $n) !== false; }

$req = array(
	'IDENT=page-services-layer7-reports' => 'IDENT',
	'function layer7_reports_compact_pages' => 'paginacao',
	'$_POST["clear_all_reports"]' => 'handler clear',
	'layer7_reports_ingest_log_incremental' => 'ingestao prefixo',
	'id="l7-reports-filters"' => 'painel filtros',
	'id="l7r-filters-form"' => 'form filtro',
	'for="l7r-filter-from"' => 'label from',
	'name="range" value="custom"' => 'hidden range custom',
	'id="l7-tools"' => 'ancora tools',
	'name="clear_all_reports" value="1"' => 'clear submitter',
	'layer7_reports_export.php?format=html' => 'export html',
	'layer7_reports_export.php?format=csv' => 'export csv',
	'layer7_reports_export.php?format=json' => 'export json',
	'id="l7-reports-summary"' => 'painel resumo',
	'id="l7-reports-chart"' => 'painel grafico',
	'id="timelineChart" height="85"' => 'canvas height 85',
	'chart.umd.min.js' => 'Chart.js local',
	'id="l7-reports-tops"' => 'painel tops',
	'id="l7-reports-events"' => 'painel eventos',
	'resolveIdentityByIp' => 'identity',
	'Host inferido (DNS)' => 'host inferido',
	'text-center text-muted' => 'credito',
);
foreach ($req as $k => $n) { check(has($src, $k), "preserva $n"); }

$bad = array('layer7_render_styles()' => 'styles', 'layer7-page' => 'layer7-page', 'l7-kpi-cards' => 'kpi', '<style' => 'style tag', 'style=' => 'inline style');
foreach ($bad as $k => $n) { check(!has($src, $k), "ausente $n"); }

require_once __DIR__ . "/harness-reports-view/bootstrap.php";
$html = l7hr_render(array());
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xp = new DOMXPath($dom);
foreach (array("from", "to", "src_ip", "host", "action", "q") as $f) {
	$el = $xp->query('//*[@id="l7r-filter-' . $f . '"]')->item(0);
	check($el instanceof DOMElement, "DOM: id l7r-filter-$f");
}
if ($fail) { fwrite(STDERR, "SOME REPORTS NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL REPORTS NATIVE VIEW TESTS PASSED\n";
exit(0);
