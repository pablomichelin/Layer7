<?php
/**
 * V9 Teste — gate estático strings form (não prova mesmo form DOM).
 *
 *   php tests/functional/test_test_payload.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_test.php";
$src = file_get_contents($path);
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

check(strpos($src, 'action="layer7_test.php#l7-test"') !== false ||
    strpos($src, 'setAction("layer7_test.php#l7-test")') !== false,
	"payload: action layer7_test.php#l7-test");
check(strpos($src, 'Form_Input("test_domain"') !== false, "payload: test_domain");
check(strpos($src, 'maxlength", "255"') !== false, "payload: maxlength dominio 255");
check(strpos($src, 'Form_Input("test_src_ip"') !== false, "payload: test_src_ip");
check(strpos($src, 'maxlength", "48"') !== false, "payload: maxlength origem 48");
check(strpos($src, 'Form_Select("test_ndpi_app"') !== false, "payload: test_ndpi_app");
check(strpos($src, 'Form_Select("test_ndpi_cat"') !== false, "payload: test_ndpi_cat");
check(strpos($src, 'name="run_test"') !== false && strpos($src, 'value="1"') !== false,
	"payload: run_test value1");
check(strpos($src, 'setAttribute("placeholder", "youtube.com ou 142.250.185.46")') !== false,
	"payload: placeholder dominio");
check(strpos($src, 'setAttribute("placeholder", "10.0.85.50")') !== false, "payload: placeholder origem");
check(strpos($src, "— qualquer —") !== false, "payload: opcao vazia catalogo");
check(strpos($src, '<select name="test_ndpi_app"') === false,
	"payload: candidato sem select manual app");
check(strpos($src, '<select name="test_ndpi_cat"') === false,
	"payload: candidato sem select manual cat");

if ($fail) {
	fwrite(STDERR, "SOME TEST PAYLOAD STATIC TESTS FAILED\n");
	exit(1);
}
echo "ALL TEST PAYLOAD STATIC TESTS PASSED\n";
exit(0);
