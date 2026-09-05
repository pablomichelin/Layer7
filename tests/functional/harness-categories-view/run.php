<?php
/**
 * Harness de render — executa layer7_categories.php com stubs.
 * layer7_ndpi_list() devolve fixtures. Não é pfSense nem appliance.
 *
 *   php tests/functional/harness-categories-view/run.php
 */
require_once __DIR__ . "/bootstrap.php";

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
function has($html, $needle)
{
	return strpos($html, $needle) !== false;
}

$out_dir = __DIR__ . "/generated";
if (!is_dir($out_dir)) {
	@mkdir($out_dir, 0755, true);
}

echo "HARNESS RENDER — view efetiva layer7_categories.php\n";
echo "Nao e pfSense, nao e appliance, nDPI injectado, sem CSRF\n";

$html0 = l7hc_render(l7hc_ndpi(array(), array()));
file_put_contents($out_dir . "/empty.html", $html0);
check(has($html0, "Nao foi possivel obter a lista de protocolos"), "0 protocolos: aviso");
check(has($html0, 'id="l7-categories"'), "0 protocolos: ancora");
check(!has($html0, 'id="l7-cat-search"'), "0 protocolos: sem pesquisa");
check(!has($html0, "<details"), "0 protocolos: sem grupos");
check(has($html0, "<!-- L7HC_HEAD -->"), "0 protocolos: head stub");
check(has($html0, "<!-- L7HC_SUBNAV categories -->"), "0 protocolos: subnav");
check(has($html0, "Total: 0 apps em 0 categorias"), "0 protocolos: contagens");

$html1 = l7hc_render(l7hc_ndpi(array(
	"Social" => array("Facebook"),
), array("Facebook")));
file_put_contents($out_dir . "/one.html", $html1);
check(has($html1, "Facebook"), "1 protocolo: nome");
check(has($html1, "Social"), "1 protocolo: categoria");
check(has($html1, 'id="l7-cat-search"'), "1 protocolo: id pesquisa");
check(has($html1, 'for="l7-cat-search"'), "1 protocolo: for pesquisa");
check(has($html1, 'id="l7-cat-clear"'), "1 protocolo: limpar");
check(has($html1, "<details"), "1 protocolo: details");
check(has($html1, "<summary"), "1 protocolo: summary");
check(has($html1, 'data-category="social"'), "1 protocolo: data-category");
check(has($html1, 'data-proto="facebook"'), "1 protocolo: data-proto");
check(has($html1, "Total: 1 apps em 1 categorias"), "1 protocolo: contagens");
check(has($html1, "Consulta de referencia"), "1 protocolo: so consulta");
check(!has($html1, "l7-proto-tag"), "1 protocolo: sem chips");

$by472 = array();
$all472 = array();
$fixture472 = l7hc_fixture_catalog_472();
$by472 = $fixture472["protocols_by_category"];
$all472 = $fixture472["protocols"];
$html472 = l7hc_render($fixture472);
file_put_contents($out_dir . "/list-472.html", $html472);
$missing = array();
foreach ($all472 as $p) {
	if (!has($html472, $p)) {
		$missing[] = $p;
		if (count($missing) >= 5) {
			break;
		}
	}
}
check(count($missing) === 0, "472 protocolos: todos no HTML");
check(has($html472, "Total: 472 apps em " . count($by472) . " categorias"), "472 protocolos: contagens");
check(substr_count($html472, "<details") === count($by472), "472 protocolos: um details por categoria");
check(has($html472, "Proto001") && has($html472, "Proto472"), "472 protocolos: extremos");

$xss = l7hc_render(l7hc_ndpi(array(
	'<img src=x onerror=alert(1)>' => array('<script>x</script>', 'SafeApp'),
), array('<script>x</script>', 'SafeApp')));
file_put_contents($out_dir . "/xss.html", $xss);
check(has($xss, "SafeApp"), "escaping: app segura");
check(has($xss, "&lt;script&gt;x&lt;/script&gt;"), "escaping: script no texto");
check(has($xss, "&lt;img src=x onerror=alert(1)&gt;") || has($xss, "&lt;img"), "escaping: img no texto");
check(!preg_match('/<img\b/i', $xss), "escaping: tag img real ausente");
check(!preg_match('/<script\b[^>]*>x<\/script>/i', $xss), "escaping: tag script real ausente");
$xss_no_quoted = preg_replace('/="[^"]*"/', '=""', $xss);
check(!preg_match('/<[a-zA-Z][^>]*\sonerror\s*=/i', $xss_no_quoted), "escaping: atributo onerror real ausente");

check(has($html1, 'for="l7-cat-search"') && has($html1, 'id="l7-cat-search"'), "for+id l7-cat-search");

echo "HTML gerado em {$out_dir} (evidencia, nao produto)\n";

if ($fail) {
	fwrite(STDERR, "SOME CATEGORIES RENDER HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL CATEGORIES RENDER HARNESS TESTS PASSED\n";
exit(0);
