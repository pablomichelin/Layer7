<?php
/**
 * V9 Teste — render resultados simulados (fixtures; sem DNS/handlers reais).
 *
 *   php tests/functional/test_test_results.php
 */
require_once __DIR__ . "/harness-test-view/bootstrap.php";

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

function render_scenario($opts)
{
	return l7ht_render($opts);
}

$policy_row = array(
	"type" => "policy",
	"id" => "profile-youtube",
	"name" => "YouTube",
	"action" => "block",
	"priority" => 100,
	"matched" => true,
	"reason" => "match.hosts youtube.com",
);

$block = render_scenario(array(
	"test_domain" => "youtube.com",
	"test_results" => array(
		"mode" => "enforce",
		"resolved_ips" => array("142.250.185.46", "142.250.185.78"),
		"results" => array(
			$policy_row,
			array(
				"type" => "verdict",
				"action" => "block",
				"label" => "BLOQUEADO",
				"reason" => "BLOQUEADO — politica `profile-youtube`",
				"detail" => "Politica 'profile-youtube' casou: match.hosts",
				"enforce" => true,
			),
		),
	),
));
check(strpos($block, 'id="l7-test-results"') !== false, "block: painel resultados");
check(strpos($block, 'id="l7-test-verdict"') !== false, "block: veredicto");
check(strpos($block, "alert-danger") !== false, "block: alert-danger");
check(strpos($block, "BLOQUEADO") !== false, "block: label");
check(strpos($block, "142.250.185.46") !== false, "block: resolved IPs");
check(strpos($block, "Modo enforce activo") !== false, "block: nota enforce");
check(strpos($block, "profile-youtube") !== false, "block: tabela politica");

$allow = render_scenario(array(
	"test_results" => l7ht_fixture_verdict("allow", true, array(
		array(
			"type" => "exception",
			"id" => "vip-isentos",
			"name" => "vip-isentos",
			"action" => "allow",
			"matched" => true,
			"reason" => "IP origem = 10.0.0.1",
		),
	)),
));
check(strpos($allow, "alert-success") !== false, "allow: alert-success");
check(strpos($allow, "PERMITIDO") !== false, "allow: label");
check(strpos($allow, "vip-isentos") !== false, "allow: excepcao na tabela");

$monitor = render_scenario(array(
	"test_results" => l7ht_fixture_verdict("monitor", false, array()),
));
check(strpos($monitor, "alert-info") !== false, "monitor: alert-info");
check(strpos($monitor, "MONITORIZADO") !== false, "monitor: label");
check(strpos($monitor, "Modo monitor: nenhuma accao") !== false, "monitor: nota simulacao");

$sem = render_scenario(array("test_results" => null));
check(strpos($sem, 'id="l7-test-results"') === false, "semresultado: sem painel resultados");
check(strpos($sem, 'id="l7-test"') !== false, "semresultado: formulario presente");

$error = render_scenario(array(
	"input_errors" => array('Indique pelo menos um dominio/IP ou app nDPI para testar.'),
	"test_results" => null,
));
check(strpos($error, "l7-input-errors") !== false, "error: input_errors renderizado");
check(strpos($error, "dominio/IP ou app nDPI") !== false, "error: mensagem");
check(strpos($error, 'id="l7-test-results"') === false, "error: sem resultados");

foreach (array($block, $allow, $monitor) as $html) {
	check(strpos($html, "Esta simulacao usa as politicas") !== false, "simulacao: nota explicativa");
}

if ($fail) {
	fwrite(STDERR, "SOME TEST RESULTS TESTS FAILED\n");
	exit(1);
}
echo "ALL TEST RESULTS TESTS PASSED\n";
exit(0);
