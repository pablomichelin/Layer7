<?php
/**
 * V13 MITM — render fixtures (locked/unlocked/CA/effective/gates/timed/until_off).
 */
require_once __DIR__ . "/harness-mitm-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$locked = l7hm_render(array("unlocked" => false));
check(strpos($locked, "nao incluido nesta licenca") !== false, "locked: aviso entitlement");
check(strpos($locked, 'name="mitm_save_bypass"') === false, "locked: sem form save");

$no_ca = l7hm_render(array(
	"unlocked" => true,
	"ca_ok" => false,
	"toggle_ok" => false,
));
check(strpos($no_ca, 'name="mitm_enabled"') !== false, "unlocked: checkbox enabled");
check(strpos($no_ca, 'disabled="disabled"') !== false, "sem ca: toggle disabled");
check(strpos($no_ca, "Instale uma CA") !== false, "sem ca: aviso");

$with_ca = l7hm_render(array(
	"unlocked" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array(
		"ca" => array(
			"present" => true,
			"cn" => "Layer7 MITM CA",
			"subject" => "CN=SYNTH-CA-FIXTURE",
			"fingerprint_sha256" => "SYNTH-FP-ONLY-FIXTURE",
			"not_after" => "2099-12-31",
		),
	)),
	"ca_ok" => true,
	"toggle_ok" => true,
	"runtime_ok" => true,
));
check(strpos($with_ca, "SYNTH-CA-FIXTURE") !== false, "ca presente: subject sintetico");
check(strpos($with_ca, 'name="mitm_ca_export"') !== false, "ca presente: export");
check(strpos($with_ca, 'name="ca_key_pem"') !== false, "ca: textarea key vazio");
check(strpos($with_ca, "BEGIN PRIVATE KEY") === false || strpos($with_ca, "placeholder") !== false,
	"ca: sem chave persistida na view");

$effective = l7hm_render(array(
	"unlocked" => true,
	"effective" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array(
		"enabled" => true,
		"ca" => array(
			"present" => true,
			"cn" => "Layer7 MITM CA",
			"subject" => "CN=SYNTH",
			"fingerprint_sha256" => "SYNTH",
			"not_after" => "2099-12-31",
		),
	)),
	"ca_ok" => true,
	"toggle_ok" => true,
	"runtime_ok" => true,
));
check(strpos($effective, 'name="mitm_break_glass"') !== false, "effective: break-glass");
check(strpos($effective, "Inspeccao TLS ligada") !== false, "effective: alert success");

$timed = l7hm_render(array(
	"unlocked" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array(
		"window" => array("max_minutes" => 30, "deadline_unix" => 0),
	)),
	"win_status" => array(
		"max_minutes" => 30,
		"until_off" => false,
		"remaining_sec" => 900,
		"source_cidr" => array(),
		"dest_cidr" => array(),
		"block_sni" => array(),
		"quic_mode" => "bypass",
	),
));
check(strpos($timed, 'value="timed"') !== false || strpos($timed, 'value="timed" checked') !== false,
	"timed: radio timed");
check(strpos($timed, 'name="mitm_max_window"') !== false, "timed: campo minutos");

$until_off = l7hm_render(array(
	"unlocked" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array(
		"window" => array("max_minutes" => 0, "deadline_unix" => 0),
	)),
	"win_status" => array(
		"max_minutes" => 0,
		"until_off" => true,
		"remaining_sec" => 0,
		"source_cidr" => array(),
		"dest_cidr" => array(),
		"block_sni" => array(),
		"quic_mode" => "bypass",
	),
));
check(preg_match('/value="until_off"[^>]*checked="checked"/', $until_off) === 1,
	"until_off: radio checked");

$gates_off = l7hm_render(array(
	"unlocked" => true,
	"runtime_ok" => false,
	"effective" => false,
	"ca_ok" => true,
	"toggle_ok" => true,
));
check(strpos($gates_off, "motor de inspeccao nao esta disponivel") !== false, "gates off: runtime");

$adv = l7hm_render(array(
	"unlocked" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array(
		"intercept" => array(
			"source_cidr" => array('\'"<script>'),
			"dest_cidr" => array(),
			"block_sni" => array(),
		),
	)),
));
check(strpos($adv, htmlspecialchars('\'"<script>', ENT_QUOTES, "UTF-8")) !== false,
	"adversarial: source escapado");

$confirm = l7hm_render(array(
	"unlocked" => true,
	"effective" => true,
	"mitm" => array_merge(l7hm_mitm_defaults(), array("enabled" => true)),
));
check(strpos($confirm, "onclick=\"return confirm(") !== false, "confirmDOM: atributo presente");
check(strpos($confirm, "Desligar a inspeccao TLS agora") !== false, "confirmDOM: texto break-glass");
check(strpos($confirm, "&quot;") === false, "confirmDOM: sem JSON cru escapado");

echo $fail ? "" : "ALL MITM RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
