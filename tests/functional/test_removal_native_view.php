<?php
/**
 * V11 Remoção — gate estático view nativa.
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_removal.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$required = array(
	'IDENT=page-services-layer7-removal' => 'privilege IDENT',
	'MATCH=layer7_removal.php*' => 'privilege MATCH',
	'$_POST["layer7_pkg_remove_do"]' => 'handler remove (prefixo)',
	'layer7_pkg_rm.sh' => 'script job (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("removal")' => 'tabs removal',
	'id="l7-removal-warning"' => 'painel aviso',
	'alert alert-danger' => 'alerta risco',
	'pfSense-pkg-layer7' => 'nome pacote no aviso',
	'id="l7-removal-state"' => 'painel estado',
	'id="l7-removal-after"' => 'painel apos remover',
	'require_once("classes/Form.class.php")' => 'Form.class.php na view',
	'new Form(false)' => 'Form(false)',
	'"keep_license"' => 'checkbox licenca',
	'"keep_config"' => 'checkbox config',
	'new Form_Checkbox' => 'Form_Checkbox',
	'"layer7_remove_confirm"' => 'campo confirmacao',
	'new Form_Input' => 'Form_Input confirmacao',
	'autocomplete", "off"' => 'autocomplete off',
	'name="layer7_pkg_remove_do" value="1"' => 'botao remover',
	'btn btn-danger' => 'botao vermelho',
	'prevalece' => 'aviso precedencia',
	'/usr/local/etc/layer7.lic' => 'caminho licenca',
	'layer7.json' => 'caminho config',
	'DNS Resolver' => 'instrucao Unbound',
	'text-center text-muted' => 'credito nativo',
);

foreach ($required as $needle => $name) {
	check(strpos($src, $needle) !== false, "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'render_styles',
	'layer7_render_footer()' => 'render_footer',
	'layer7-admin-block' => 'admin-block',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'style=' => 'style inline',
	'onclick=' => 'onclick extra',
	'onsubmit=' => 'onsubmit extra',
);

foreach ($forbidden as $needle => $name) {
	check(strpos($src, $needle) === false, "ausente {$name}");
}

if ($fail) { fwrite(STDERR, "SOME REMOVAL NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL REMOVAL NATIVE VIEW TESTS PASSED\n";
exit(0);
