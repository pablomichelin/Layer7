<?php
/**
 * Harness de render — executa layer7_allowlist.php com stubs.
 * Foco view/payload/handler congelado; validadores extraidos de layer7.inc
 * (nao e prova isolada de validacao C do daemon).
 *
 *   php tests/functional/harness-allowlist-view/run.php
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
function textarea_value($html, $id)
{
	if (preg_match('/id="' . preg_quote($id, '/') . '"[^>]*>(.*)<\/textarea>/s', $html, $m) !== 1) {
		return null;
	}
	return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
}
function seed_pre_text($html)
{
	if (preg_match('/<pre[^>]*class="pre-scrollable"[^>]*>(.*)<\/pre>/s', $html, $m) !== 1) {
		return null;
	}
	return html_entity_decode($m[1], ENT_QUOTES, "UTF-8");
}
function seed_outside_form($html)
{
	$dom = new DOMDocument();
	if (@$dom->loadHTML('<?xml encoding="UTF-8">' . $html) === false) {
		return false;
	}
	$xpath = new DOMXPath($dom);
	$form = $xpath->query("//form[contains(@action,'layer7_allowlist.php')]")->item(0);
	$seed = $xpath->query("//*[@id='l7-allow-seed']")->item(0);
	if (!$form instanceof DOMElement || !$seed instanceof DOMElement) {
		return false;
	}
	$node = $seed;
	while ($node->parentNode) {
		if ($node->parentNode->isSameNode($form)) {
			return false;
		}
		$node = $node->parentNode;
	}
	return true;
}

echo "HARNESS RENDER — view efetiva layer7_allowlist.php\n";
echo "Validadores: extraidos de layer7.inc (handler real no eval; nao e suite C)\n";
echo "Nao e pfSense, nao e appliance, stubs rastreaveis\n";

$seed94 = array();
for ($i = 1; $i <= 94; $i++) {
	$seed94[] = "seed{$i}.example.com";
}

/* operador 0 / seed 0 */
$html0 = l7ha_render(array("data" => l7ha_data(array()), "seed" => array()));
check(has($html0, "<!-- L7HA_HEAD -->"), "0 entradas: head stub");
check(has($html0, "L7HA_TABS allowlist"), "0 entradas: tab allowlist");
check(has($html0, 'name="save_allowlist"'), "0 entradas: hidden save_allowlist");
check(has($html0, 'name="entries"'), "0 entradas: textarea entries");
check(!has($html0, 'name="save"'), "0 entradas: sem Save padrao");
check(!has($html0, 'type="submit" name='), "0 entradas: submit sem name");
check(seed_outside_form($html0), "0 entradas: seed fora do form");
check(has($html0, "Ficheiro de seed ausente"), "seed 0: aviso ausente");
check(has($html0, "lista-semente embutida"), "seed 0: titulo seed");
check(has($html0, "Politicas de bloqueio explicitas"), "intro revisada");
check(!has($html0, "NUNCA devem ser bloqueados"), "intro sem promessa NUNCA");
check(!has($html0, "blacklists (cobrem a allowlist)"), "seed sem receita blacklist");

/* operador 1 */
$html1 = l7ha_render(array(
	"data" => l7ha_data(array("bb.com.br")),
	"seed" => array("seed.example.com"),
));
check(textarea_value($html1, "l7-allow-entries") === "bb.com.br", "1 entrada: valor textarea");
check(has($html1, '<span class="badge">1</span>'), "seed 1: contagem badge");
check(has($html1, "seed.example.com"), "seed 1: conteudo completo");
check(has($html1, "<details>"), "seed: details nativo");
check(has($html1, "pre-scrollable"), "seed: pre-scrollable nativo");
check(!has($html1, "style="), "sem atributo style=");

/* seed 94 integral */
$html94 = l7ha_render(array("data" => l7ha_data(array()), "seed" => $seed94));
check(has($html94, '<span class="badge">94</span>'), "seed 94: contagem");
check(seed_pre_text($html94) === implode("\n", $seed94), "seed 94: conteudo integral no pre");

/* tipos validos + comentario + duplicata (handler real; nao afirmar suite validadores) */
$mixed = "bb.com.br\n# comentario\n8.8.8.8\n2001:db8::1\n200.201.0.0/16\n2001:db8::/32\nbb.com.br\n";
$html_ok = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => $mixed),
	"data" => l7ha_data(array()),
));
check(has($html_ok, "l7-savemsg"), "save valido: mensagem sucesso");
check($GLOBALS["l7ha_save_calls"] === 1, "save valido: save_json chamado");
check($GLOBALS["l7ha_apply_calls"] === 1, "save valido: apply_to_pf chamado");
check($GLOBALS["l7ha_reload_calls"] === 1, "save valido: signal_reload chamado");
check($GLOBALS["l7ha_filter_calls"] === 1, "save valido: filter_configure chamado");
$saved = $GLOBALS["l7ha_last_saved"]["layer7"]["dst_allowlist"];
check(is_array($saved) && count($saved) === 5, "save valido: 5 entradas unicas");

/* 256 entradas — comparar lista completa */
$lines256 = array();
for ($i = 1; $i <= 256; $i++) {
	$lines256[] = "host{$i}.example.com";
}
$html256 = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => implode("\n", $lines256)),
	"data" => l7ha_data(array()),
));
check(has($html256, "l7-savemsg"), "256 entradas: sucesso");
check($GLOBALS["l7ha_last_saved"]["layer7"]["dst_allowlist"] === $lines256,
	"256 entradas: todas persistidas na ordem");

/* 257 entradas — sem side effects PF */
$lines257 = $lines256;
$lines257[] = "overflow.example.com";
$html257 = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => implode("\n", $lines257)),
	"data" => l7ha_data(array("bb.com.br")),
));
check(has($html257, "l7-input-errors"), "257 entradas: erro");
check(has($html257, "Limite de 256 entradas"), "257 entradas: mensagem limite");
check($GLOBALS["l7ha_save_calls"] === 0, "257 entradas: save_json nao chamado");
check($GLOBALS["l7ha_apply_calls"] === 0, "257 entradas: apply_to_pf zero");
check($GLOBALS["l7ha_reload_calls"] === 0, "257 entradas: signal_reload zero");
check($GLOBALS["l7ha_filter_calls"] === 0, "257 entradas: filter_configure zero");
check(strpos(textarea_value($html257, "l7-allow-entries"), "overflow.example.com") !== false,
	"257 entradas: texto POST restaurado");

/* invalidos — handler real; zero side effects */
$html_inv = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => "no-dot\n999.0.0.1\nbb.com.br"),
	"data" => l7ha_data(array()),
));
check(has($html_inv, "Entradas invalidas"), "invalidos: alerta handler");
check($GLOBALS["l7ha_save_calls"] === 0, "invalidos: save_json nao chamado");
check($GLOBALS["l7ha_apply_calls"] === 0, "invalidos: apply_to_pf zero");
check($GLOBALS["l7ha_reload_calls"] === 0, "invalidos: signal_reload zero");
check($GLOBALS["l7ha_filter_calls"] === 0, "invalidos: filter_configure zero");
check(strpos(textarea_value($html_inv, "l7-allow-entries"), "no-dot") !== false,
	"invalidos: texto POST restaurado");

/* retry vazio apos falha (savefalse + dados antigos nao vazios) */
$html_empty = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => ""),
	"data" => l7ha_data(array("keep.me")),
	"save_result" => false,
));
check(!has($html_empty, "l7-savemsg"), "retry vazio: sem sucesso");
check(textarea_value($html_empty, "l7-allow-entries") === "",
	"retry vazio: textarea vazia preservada apos falha");

/* retry multilinha adversarial */
$adv = "<script>alert(1)</script>\nline2\n# c\n8.8.8.8";
$html_adv = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => $adv),
	"data" => l7ha_data(array("x.example.com")),
	"save_result" => false,
));
check(textarea_value($html_adv, "l7-allow-entries") === $adv, "retry multilinha: texto integral");
check(has($html_adv, "&lt;script&gt;alert(1)&lt;/script&gt;"), "XSS: script escapado no textarea");
check(!preg_match('/<script\b/i', $html_adv), "XSS: sem tag script activa");

/* labels */
check(has($html1, 'for="l7-allow-entries"') && has($html1, 'id="l7-allow-entries"'),
	"labels: for+id entries");

/* save_json false — preservar POST; sem mensagem nem side effects */
$html_save_fail = l7ha_render(array(
	"post" => array("save_allowlist" => "1", "entries" => "bb.com.br"),
	"data" => l7ha_data(array("old.example.com")),
	"save_result" => false,
));
check(!has($html_save_fail, "l7-savemsg"), "save_json false: sem mensagem sucesso");
check($GLOBALS["l7ha_save_calls"] === 1, "save_json false: save_json chamado uma vez");
check($GLOBALS["l7ha_apply_calls"] === 0, "save_json false: apply_to_pf zero");
check($GLOBALS["l7ha_reload_calls"] === 0, "save_json false: signal_reload zero");
check($GLOBALS["l7ha_filter_calls"] === 0, "save_json false: filter_configure zero");
check(textarea_value($html_save_fail, "l7-allow-entries") === "bb.com.br",
	"save_json false: texto POST preservado na view");

/* redeclare: segunda renderizacao sem fatal */
$html_second = l7ha_render(array("data" => l7ha_data(array("a.example.com"))));
check(has($html_second, "a.example.com"), "redeclare: segunda render sem fatal");

echo "Form vendor noise conhecido: " . (int)$GLOBALS["l7ha_form_noise"] . "\n";
check(empty($GLOBALS["l7ha_form_noise_unexpected"]), "vendor: so avisos conhecidos");

if ($fail) {
	fwrite(STDERR, "SOME ALLOWLIST RENDER HARNESS TESTS FAILED\n");
	exit(1);
}
echo "ALL ALLOWLIST RENDER HARNESS TESTS PASSED\n";
exit(0);
