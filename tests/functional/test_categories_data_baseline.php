<?php
/**
 * BG-174 / V3 — o bloco de dados e privilégios de Categorias é congelado.
 * Compara o texto exacto da leitura nDPI / ksort / PRIV com o contrato HEAD.
 *
 *   php tests/functional/test_categories_data_baseline.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_categories.php";
if (!is_file($path)) {
	fwrite(STDERR, "FAIL ficheiro em falta: {$path}\n");
	exit(1);
}
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

$priv = "##|+PRIV\n"
	. "##|*IDENT=page-services-layer7-categories\n"
	. "##|*NAME=Services: Layer 7 (categories)\n"
	. "##|*DESCR=Allow access to Layer 7 nDPI categories.\n"
	. "##|*MATCH=layer7_categories.php*\n"
	. "##|-PRIV";

$data = '$ndpi_list = layer7_ndpi_list();' . "\n"
	. '$by_cat = isset($ndpi_list["protocols_by_category"]) && is_array($ndpi_list["protocols_by_category"])' . "\n"
	. "\t? \$ndpi_list[\"protocols_by_category\"] : array();\n"
	. '$total_protos = isset($ndpi_list["protocols"]) && is_array($ndpi_list["protocols"])' . "\n"
	. "\t? count(\$ndpi_list[\"protocols\"]) : 0;\n"
	. '$total_cats = count($by_cat);' . "\n"
	. "\n"
	. 'ksort($by_cat);';

check(strpos($src, $priv) !== false, "bloco PRIV igual ao HEAD");
check(strpos($src, $data) !== false, "bloco layer7_ndpi_list/ksort/contagens igual ao HEAD");
check(strpos($src, 'layer7_categories.php') !== false, "rota layer7_categories.php");
check(substr_count($src, 'layer7_ndpi_list()') === 1, "uma unica leitura nDPI");

if ($fail) {
	fwrite(STDERR, "SOME CATEGORIES DATA BASELINE TESTS FAILED\n");
	exit(1);
}
echo "ALL CATEGORIES DATA BASELINE TESTS PASSED\n";
exit(0);
