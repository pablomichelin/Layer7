<?php
/*
 * L1 logging: parser dos novos eventos e ingestao que atravessa uma rotacao.
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

function fail_logging_test($message)
{
	fwrite(STDERR, "FAIL: " . $message . "\n");
	exit(1);
}

$flow = layer7_reports_event_from_log_line(
	"2026-07-29 10:00:00 [notice] flow_decide: iface=vmx0 src=10.0.0.10 dst=1.1.1.1 host=example.com app=TLS cat=Web action=block reason=policy policy=p1\n"
);
if (!is_array($flow) || $flow["type"] !== "flow" ||
    $flow["action"] !== "block" || $flow["iface"] !== "vmx0") {
	fail_logging_test("flow_decide novo nao foi interpretado");
}

$enforce = layer7_reports_event_from_log_line(
	"2026-07-29 10:00:01 [notice] enforce_block: kind=dst_scoped table=layer7_pdst_0 src=10.0.0.10 dst=1.1.1.1 ip=1.1.1.1 policy=p1 reason=flow_block\n"
);
if (!is_array($enforce) || $enforce["type"] !== "enforce" ||
    $enforce["action"] !== "enforced" ||
    $enforce["src_ip"] !== "10.0.0.10" ||
    $enforce["dst_ip"] !== "1.1.1.1") {
	fail_logging_test("enforce_block novo nao foi interpretado");
}

if (!class_exists("SQLite3")) {
	echo "SKIP: SQLite3 indisponivel\n";
	exit(0);
}

$dir = sys_get_temp_dir() . "/layer7-reports-" . getmypid();
@mkdir($dir, 0700, true);
$log = $dir . "/events.log";
$rotated = $log . ".1";
$cursor = $dir . "/cursor.json";
$db_path = $dir . "/reports.db";

file_put_contents($rotated,
	"2026-07-29 10:00:00 [info] dns_query: iface=vmx0 src=10.0.0.10 resolver=192.168.100.254 qname=old.example\n"
);
file_put_contents($log,
	"2026-07-29 10:01:00 [notice] flow_decide: iface=vmx0 src=10.0.0.10 dst=1.1.1.1 host=new.example app=TLS cat=Web action=block reason=policy policy=p1\n"
);
$rst = stat($rotated);
layer7_reports_cursor_save($cursor, (string)$rst["ino"], 0);

$db = new SQLite3($db_path);
if (!layer7_reports_init_schema($db)) {
	fail_logging_test("schema SQLite");
}
$insert = $db->prepare("INSERT INTO events(ts, ts_text, type, src_ip, src_hostname, dst_ip, host, app, category, action, policy, iface, raw)
	VALUES(:ts,:ts_text,:type,:src_ip,:src_hostname,:dst_ip,:host,:app,:category,:action,:policy,:iface,:raw)");
$days = array();
$result = layer7_reports_ingest_path($db, $insert,
	array("event_interfaces" => array()), $log, $cursor, $days);
if (empty($result["ok"]) || (int)$result["ingested"] !== 2) {
	fail_logging_test("ingestao nao atravessou arquivo rotacionado");
}
if ((int)$db->querySingle("SELECT COUNT(*) FROM events") !== 2) {
	fail_logging_test("SQLite nao recebeu os dois eventos");
}
$saved = layer7_reports_cursor_load($cursor, "", 0);
$cst = stat($log);
if ((string)$saved["inode"] !== (string)$cst["ino"] ||
    (int)$saved["offset"] !== filesize($log)) {
	fail_logging_test("cursor nao terminou no arquivo activo");
}

$db->close();
@unlink($db_path);
@unlink($cursor);
@unlink($rotated);
@unlink($log);
@rmdir($dir);

echo "PASS: test_logging_reports\n";
exit(0);
