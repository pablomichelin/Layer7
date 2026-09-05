<?php
/**
 * V12 Identity — gate estático view nativa (fila 20.37 fechada — só visual).
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_identity.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$required = array(
	'IDENT=page-services-layer7-identity' => 'privilege IDENT',
	'$_POST["test_ldap"]' => 'handler test_ldap (prefixo)',
	'$_POST["save_identity"]' => 'handler save (prefixo)',
	'$_POST["dc_generate_token"]' => 'handler token (prefixo)',
	'layer7_identity_ldap_test()' => 'ldap test (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("identity")' => 'tabs identity',
	'id="l7-identity-root"' => 'raiz',
	'id="l7-identity-form"' => 'form',
	'action="layer7_identity.php"' => 'action form',
	'name="identity_enabled"' => 'campo identity_enabled',
	'name="ldap_enabled"' => 'campo ldap_enabled',
	'name="ldap_bind_password"' => 'campo ldap password',
	'name="ldap_clear_password"' => 'clear ldap password',
	'name="radius_secret"' => 'campo radius secret',
	'name="radius_clear_secret"' => 'clear radius secret',
	'name="dc_secret"' => 'campo dc secret',
	'name="dc_clear_secret"' => 'clear dc secret',
	'name="save_identity"' => 'botao guardar',
	'name="test_ldap"' => 'botao test ldap',
	'name="dc_generate_token"' => 'botao gerar token',
	'autocomplete="new-password"' => 'autocomplete passwords',
	'id="l7-identity-ldap-test"' => 'painel teste ldap',
	'text-center text-muted' => 'credito nativo',
);

foreach ($required as $needle => $name) {
	if ($needle === false || $name === false) {
		continue;
	}
	check(strpos($src, $needle) !== false, "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'render_styles',
	'layer7_render_footer()' => 'render_footer',
	'layer7-admin-block' => 'admin-block',
	'layer7-content' => 'layer7-content',
	'layer7-lead' => 'layer7-lead',
	'style=' => 'style inline',
	'new Form(' => 'Form_*',
);

foreach ($forbidden as $needle => $name) {
	check(strpos($src, $needle) === false, "ausente {$name}");
}

if ($fail) { fwrite(STDERR, "SOME IDENTITY NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL IDENTITY NATIVE VIEW TESTS PASSED\n";
exit(0);
