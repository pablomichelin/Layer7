<?php
/**
 * V13 MITM — gate estático view nativa (fila 20.37 fechada — só visual).
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_mitm.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$required = array(
	'IDENT=page-services-layer7-mitm' => 'privilege IDENT',
	'$_POST["mitm_break_glass"]' => 'handler break-glass (prefixo)',
	'$_POST["mitm_save_bypass"]' => 'handler save (prefixo)',
	'$_POST["mitm_ca_generate"]' => 'handler ca generate (prefixo)',
	'$_POST["mitm_ca_import"]' => 'handler ca import (prefixo)',
	'$_POST["mitm_ca_delete"]' => 'handler ca delete (prefixo)',
	'$_POST["mitm_ca_export"]' => 'handler ca export (prefixo)',
	'layer7_mitm_expire_if_needed' => 'expirar janela no load (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("mitm")' => 'tabs mitm',
	'id="l7-mitm-root"' => 'raiz',
	'id="l7-mitm-form"' => 'form principal',
	'id="l7-mitm-ca-generate-form"' => 'form gerar ca',
	'id="l7-mitm-ca-import-form"' => 'form importar ca',
	'name="ca_cn"' => 'campo ca_cn',
	'name="ca_cert_pem"' => 'campo ca_cert_pem',
	'name="ca_key_pem"' => 'campo ca_key_pem',
	'name="mitm_enabled"' => 'campo mitm_enabled',
	'name="mitm_duration_mode"' => 'campo duration mode',
	'name="mitm_max_window"' => 'campo max window',
	'name="intercept_source_cidr"' => 'campo source cidr',
	'name="intercept_dest_cidr"' => 'campo dest cidr',
	'name="intercept_block_sni"' => 'campo block sni',
	'name="quic_mode"' => 'campo quic mode',
	'name="bypass_sni"' => 'campo bypass sni',
	'name="bypass_cidr"' => 'campo bypass cidr',
	'maxlength="64"' => 'ca_cn maxlength 64',
	'min="1" max="240"' => 'janela min1 max240',
	'name="mitm_save_bypass"' => 'botao gravar',
	'name="mitm_break_glass"' => 'botao break-glass',
	'name="mitm_ca_export"' => 'botao export ca',
	'name="mitm_ca_delete"' => 'botao delete ca',
	'name="mitm_ca_generate"' => 'botao generate ca',
	'name="mitm_ca_import"' => 'botao import ca',
	'class="radio"' => 'radio nativo vertical',
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
);

foreach ($forbidden as $needle => $name) {
	check(strpos($src, $needle) === false, "ausente {$name}");
}

if ($fail) { fwrite(STDERR, "SOME MITM NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL MITM NATIVE VIEW TESTS PASSED\n";
exit(0);
