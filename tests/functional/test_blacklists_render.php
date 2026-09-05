<?php
/**
 * V14 Blacklists — render fixtures (lista/edição/erro/rawHTML/regras/vazio).
 */
require_once __DIR__ . "/harness-blacklists-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$list = l7bl_render(array("with_rules" => true));
check(strpos($list, 'name="do_download"') !== false, "lista: download");
check(strpos($list, "Lab") !== false, "lista: regra fixture");
check(strpos($list, 'id="l7-download"') !== false, "lista: ancora download");

$add = l7bl_render(array("add_rule" => true, "with_rules" => true));
check(strpos($add, 'name="save_rule"') !== false, "add: form regra");
check(strpos($add, 'name="rule_cats[]"') !== false, "add: categorias");

$edit = l7bl_render(array(
	"with_rules" => true,
	"edit_idx" => 0,
	"rules" => array(array(
		"name" => "EditMe",
		"enabled" => true,
		"force_dns" => false,
		"categories" => array("games"),
		"src_cidrs" => array("10.0.0.0/8"),
		"except_ips" => array(),
	)),
));
check(strpos($edit, 'value="EditMe"') !== false, "edit: nome regra");
check(strpos($edit, 'name="rule_index"') !== false, "edit: hidden rule_index");

$empty = l7bl_render(array());
check(strpos($empty, "Nenhuma regra configurada") !== false, "vazio: aviso regras");

$custom = l7bl_render(array("with_custom" => true, "cat_editing" => true));
check(strpos($custom, 'name="save_cat_sites"') !== false, "custom: form");
check(strpos($custom, "readonly") !== false, "custom: cat_id readonly em edicao");

$err = l7bl_render(array(
	"input_errors" => array("Erro sintetico fixture"),
	"with_rules" => true,
));
check(strpos($err, "Erro sintetico fixture") !== false, "erro: input_errors");

$adv = l7bl_render(array(
	"with_rules" => true,
	"rules" => array(array(
		"name" => '\'"<script>',
		"enabled" => true,
		"force_dns" => true,
		"categories" => array("adult"),
		"src_cidrs" => array(),
		"except_ips" => array(),
	)),
));
check(strpos($adv, htmlspecialchars('\'"<script>', ENT_QUOTES, "UTF-8")) !== false,
	"adversarial: nome regra escapado");

$poll = l7bl_render(array("bl_download_poll" => true));
check(strpos($poll, "startDownloadPolling()") !== false, "poll: autostart flag");

$confirm = l7bl_render(array("with_rules" => true));
check(strpos($confirm, "onsubmit=\"return confirm(") !== false, "confirmDOM: delete rule");
check(strpos($confirm, "Remover esta regra") !== false, "confirmDOM: texto rule");

echo $fail ? "" : "ALL BLACKLISTS RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
