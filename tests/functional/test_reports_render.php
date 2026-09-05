<?php
/**
 * V10 Relatórios — render fixtures (metricas/tops/eventos/avisos).
 */
require_once __DIR__ . "/harness-reports-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$h = l7hr_render(array());
check(strpos($h, "1,200") !== false || strpos($h, "1200") !== false, "metricas: total eventos");
check(strpos($h, "480") !== false, "metricas: bloqueados");
check(strpos($h, "Laptop-Pablo") !== false, "tops: identity label");
check(strpos($h, "youtube.com") !== false, "tops: sites");
check(strpos($h, "Host inferido (DNS)") !== false, "eventos: inferido");
check(strpos($h, "label-danger") !== false || strpos($h, "label label-danger") !== false, "eventos: badge block");

$off = l7hr_render(array("rpt_detail_enabled" => false));
check(strpos($off, "log detalhado activo") !== false, "detalhe off: aviso tops");
check(strpos($off, "desactivados neste appliance") !== false, "detalhe off: aviso eventos");

$warn = l7hr_render(array("db_ready" => false, "ingest_failed" => true));
check(strpos($warn, "SQLite indisponivel") !== false, "erro: sqlite");
check(strpos($warn, "Coleta incremental falhou") !== false, "erro: ingest");

$adv_q = '\'"<script>';
$adv = l7hr_render(array("filters" => array("q" => $adv_q)));
check(strpos($adv, 'value="' . htmlspecialchars($adv_q, ENT_QUOTES, "UTF-8") . '"') !== false,
	"adversarial: q escapado no value");

echo $fail ? "" : "ALL REPORTS RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
