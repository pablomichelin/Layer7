<?php
/**
 * V12 Identity — render fixtures (locked/unlocked/token/ldap test/adversarial).
 */
require_once __DIR__ . "/harness-identity-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$locked = l7hi_render(array("unlocked" => false));
check(strpos($locked, "nao incluido nesta licenca") !== false, "locked: aviso entitlement");
check(strpos($locked, 'name="save_identity"') === false, "locked: sem form");

$ready = l7hi_render(array("unlocked" => true));
check(strpos($ready, 'name="ldap_server"') !== false, "unlocked: form ldap");
check(strpos($ready, 'name="radius_nas_acl"') !== false, "unlocked: campo nas acl");
check(strpos($ready, "palavra-passe definida") === false, "unlocked: pwd vazio sem flag");

$secrets = l7hi_render(array(
	"unlocked" => true,
	"pwd_set" => true,
	"radius_secret_set" => true,
	"dc_secret_set" => true,
));
check(strpos($secrets, "palavra-passe definida") !== false, "secrets: ldap definida");
check(strpos($secrets, "secret definido") !== false, "secrets: radius definido");
check(strpos($secrets, "token definido") !== false, "secrets: dc definido");
check(strpos($secrets, 'value=""') !== false, "secrets: passwords vazios");

$token = l7hi_render(array(
	"unlocked" => true,
	"dc_token_once" => "SYNTH-TOKEN-ONLY-FIXTURE",
));
check(strpos($token, "SYNTH-TOKEN-ONLY-FIXTURE") !== false, "token: sintetico escapado");
check(strpos($token, "user-select") === false, "token: sem style user-select");

$ldap_on = l7hi_render(array(
	"unlocked" => true,
	"identity" => array_merge(l7hi_identity_defaults(), array(
		"ldap" => array_merge(l7hi_identity_defaults()["ldap"], array("enabled" => true)),
	)),
));
check(strpos($ldap_on, 'name="test_ldap"') !== false, "ldap on: botao teste");
check(strpos($ldap_on, "disabled") === false, "ldap on: teste habilitado");

$ldap_test = l7hi_render(array(
	"unlocked" => true,
	"ldap_test" => array(
		"ok" => true,
		"message" => "fixture ok",
		"phase" => "bind",
		"server" => "dc01.lab",
		"port" => 636,
		"tls" => true,
		"base_ok" => true,
		"ms" => 42,
		"tested_at" => "2026-01-01T00:00:00Z",
	),
));
check(strpos($ldap_test, "Ultimo teste LDAP") !== false, "ldap test: painel");
check(strpos($ldap_test, "fixture ok") !== false, "ldap test: mensagem");

$adv = l7hi_render(array(
	"unlocked" => true,
	"identity" => array_merge(l7hi_identity_defaults(), array(
		"ldap" => array_merge(l7hi_identity_defaults()["ldap"], array(
			"server" => '\'"<script>',
		)),
	)),
));
check(strpos($adv, 'value="' . htmlspecialchars('\'"<script>', ENT_QUOTES, "UTF-8") . '"') !== false,
	"adversarial: server escapado");

echo $fail ? "" : "ALL IDENTITY RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
