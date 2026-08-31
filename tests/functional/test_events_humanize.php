<?php
/**
 * BG-172 — frases de operador para linhas de Eventos.
 *
 *   php tests/functional/test_events_humanize.php
 */
$root = dirname(__DIR__, 2);
require_once $root . "/package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc";

$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$fail = 1;
	}
}

$flow_monitor = "2026-08-31 14:08:04 [debug] flow_decide: iface=oce0.60 src=172.16.8.106 dst=71.18.42.254 host=v45.tiktokcdn.com app=TikTok cat=SocialNetwork action=monitor reason=policy_match";
$m = layer7_event_explain_line($flow_monitor);
check($m["kind"] === "flow_decide", "flow_decide kind");
check($m["tone"] === "monitor", "flow_decide tone monitor");
check($m["title"] === "Trafego observado", "flow_decide title");
check(strpos($m["summary"], "172.16.8.106") !== false, "flow_decide keeps src");
check(strpos($m["summary"], "v45.tiktokcdn.com") !== false, "flow_decide keeps host");
check(strpos($m["summary"], "TikTok") !== false, "flow_decide keeps app");
check(strpos($m["summary"], "rede social") !== false, "flow_decide maps SocialNetwork");
check(strpos($m["summary"], "nao bloqueou") !== false, "flow_decide says watching");
check($m["raw"] === $flow_monitor, "flow_decide keeps raw");

$flow_tls = "2026-08-31 14:08:05 [debug] flow_decide: iface=oce0.60 src=172.16.8.106 dst=1.2.3.4 host=g.alicdn.com app=TLS cat=Web action=monitor reason=policy_match";
$t = layer7_event_explain_line($flow_tls);
check(strpos($t["summary"], "g.alicdn.com") !== false, "generic TLS prefers host");
check(strpos($t["summary"], "TLS") === false, "generic TLS omitted from summary");
check(strpos($t["summary"], "site web") !== false, "Web maps to site web");

$flow_block = "2026-08-31 14:08:06 [info] flow_decide: iface=oce0.60 src=172.16.8.10 dst=9.9.9.9 host=pornhub.com app=HTTP cat=AdultContent action=block reason=policy_match";
$b = layer7_event_explain_line($flow_block);
check($b["title"] === "Trafego bloqueado", "block title");
check($b["tone"] === "block", "block tone");
check(strpos($b["summary"], "bloqueou") !== false, "block says blocked");

$dns_q = "2026-08-31 14:08:07 [info] dns_query: iface=oce0.60 src=172.16.8.106 resolver=127.0.0.1 qname=feed32-normal-sg.capcutapi.com";
$q = layer7_event_explain_line($dns_q);
check($q["kind"] === "dns_query", "dns_query kind");
check($q["title"] === "Pedido de nome (DNS)", "dns_query title");
check(strpos($q["summary"], "feed32-normal-sg.capcutapi.com") !== false, "dns_query keeps qname");

$dns_r = "2026-08-31 14:08:08 [info] dns_resolved: iface=oce0.60 client=172.16.8.106 domain=g.alicdn.com ip=2.16.197.143 ttl=20";
$r = layer7_event_explain_line($dns_r);
check($r["kind"] === "dns_resolved", "dns_resolved kind");
check($r["title"] === "Nome encontrado (DNS)", "dns_resolved title");
check(strpos($r["summary"], "g.alicdn.com") !== false, "dns_resolved keeps domain");
check(strpos($r["summary"], "2.16.197.143") !== false, "dns_resolved keeps ip");
check(layer7_reports_event_from_log_line($dns_r) === null,
	"dns_resolved stays out of reports ingest");

$dns_b = "2026-08-31 14:08:09 [info] dns_block: iface=oce0.60 src=172.16.8.106 qname=tracker.bad.example";
$db = layer7_event_explain_line($dns_b);
check($db["title"] === "Nome bloqueado (DNS)", "dns_block title");
check($db["tone"] === "block", "dns_block tone");

check(layer7_events_line_matches($flow_monitor, "TikTok"), "filter raw TikTok");
check(layer7_events_line_matches($flow_monitor, "observado"), "filter human title");
check(layer7_events_line_matches($flow_block, "bloqueado"), "filter human blocked");
check(!layer7_events_line_matches($flow_monitor, "BitTorrent"), "filter miss");

$unknown = "2026-08-31 14:08:10 [info] SIGUSR1 received";
$u = layer7_event_explain_line($unknown);
check($u["title"] === "Mensagem do sistema", "unknown stays system message");
check($u["raw"] === $unknown, "unknown keeps raw");

if ($fail) {
	fwrite(STDERR, "SOME EVENT HUMANIZE TESTS FAILED\n");
	exit(1);
}
echo "ALL EVENT HUMANIZE TESTS PASSED\n";
exit(0);
