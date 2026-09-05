<?php
/**
 * V6b2b — freeze prefixo/handlers; migração bulk/import intencional vs baseline V6b2b; V6c visual vs baseline V6c.
 *
 *   PHP=8.3 LAYER7_PHP=... php tests/functional/test_exceptions_vip_freeze.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php";
$baseline_v6b1 = $root . "/tests/functional/baseline-v6b1-vip/layer7_exceptions.php";
$baseline_v6b2a = $root . "/tests/functional/baseline-v6b2a-dhcp/layer7_exceptions.php";
$baseline_v6b2b = $root . "/tests/functional/baseline-v6b2b-vip-import/layer7_exceptions.php";
$baseline_v6c = $root . "/tests/functional/baseline-v6c-exceptions/layer7_exceptions.php";
$expected_sha_v6b1 = "b0efcd8be554147c0594ad6f341daf3bdf37256f5f8b50f483da0ec71b7669cc";
$expected_sha_v6b2a = "e3d216918399a3f2a54538f425e197d24b15ae2d4cd34758aa9801e7ccdfa7ea";
$expected_sha_v6b2b = "c72a5db5c4d61f8c3dbb0405a6932707664f90f929a4020686123e97ecb64754";
$expected_sha_v6c = "749b54d90a33641174f520c6dc19d87c95566d6d28a8466679193766f702cd43";

if (!is_file($current) || !is_file($baseline_v6b1) || !is_file($baseline_v6b2a) ||
    !is_file($baseline_v6b2b) || !is_file($baseline_v6c)) {
	fwrite(STDERR, "FAIL ficheiro em falta\n");
	exit(1);
}

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

$extract_bulk_embedded = function ($src) {
	$start = strpos($src, '<h4 class="layer7-form-card__title"><?= l7_t("Editar lista em lote"); ?>');
	$end = strpos(
		$src,
		'<?= l7_t("Tambem pode exportar/importar um ficheiro .txt (Excel e Bloco de notas). JSON antigo continua a ser aceite."); ?></p>',
		$start === false ? 0 : $start
	);
	if ($start === false || $end === false) {
		return null;
	}
	$end += strlen(
		'<?= l7_t("Tambem pode exportar/importar um ficheiro .txt (Excel e Bloco de notas). JSON antigo continua a ser aceite."); ?></p>'
	);
	return substr($src, $start, $end - $start);
};

$extract_dhcp_embedded = function ($src) {
	$start = strpos($src, '<h4 class="layer7-form-card__title"><?= l7_t("Reservas DHCP (IPs prefixados)"); ?>');
	if ($start === false) {
		return null;
	}
	$end = strpos($src, '<h4 class="layer7-form-card__title"><?= l7_t("Editar lista em lote"); ?>', $start);
	if ($end === false) {
		return null;
	}
	return substr($src, $start, $end - $start);
};

$cur_src = (string)file_get_contents($current);
$ref_v6b1 = (string)file_get_contents($baseline_v6b1);
$ref_v6b2a = (string)file_get_contents($baseline_v6b2a);
$ref_v6b2b = (string)file_get_contents($baseline_v6b2b);
$ref_v6c = (string)file_get_contents($baseline_v6c);
$v6a = $root . "/tests/functional/baseline-v6a-exceptions/layer7_exceptions.php";
$v6a_src = is_file($v6a) ? (string)file_get_contents($v6a) : "";

check(hash_file("sha256", $baseline_v6b1) === $expected_sha_v6b1, "baseline V6b1 SHA256 pinado");
check(hash_file("sha256", $baseline_v6b2a) === $expected_sha_v6b2a, "baseline V6b2a SHA256 pinado");
check(hash_file("sha256", $baseline_v6b2b) === $expected_sha_v6b2b, "baseline V6b2b SHA256 pinado");
check(hash_file("sha256", $baseline_v6c) === $expected_sha_v6c, "baseline V6c SHA256 pinado");

$bulk_embedded_v6b2b = $extract_bulk_embedded($ref_v6b2b);
check($bulk_embedded_v6b2b !== null, "V6b2b: baseline pre-migracao tem lote/import embutido");
check($extract_bulk_embedded($cur_src) === null, "V6b2b: candidato sem lote embutido na consulta VIP");
check(strpos($cur_src, 'vip_bulk=1#l7-vip-list') !== false, "V6b2b: link modo bulk exclusivo");
check(strpos($cur_src, 'vip_import=1#l7-vip-list') !== false, "V6b2b: link modo import exclusivo");
check(strpos($cur_src, '$l7_vip_mode === "bulk"') !== false, "V6b2b: modo bulk declarado");
check(strpos($cur_src, '$l7_vip_mode === "import"') !== false, "V6b2b: modo import declarado");

$dhcp_embedded_v6b2a = $extract_dhcp_embedded($ref_v6b2a);
check($dhcp_embedded_v6b2a !== null, "V6b2a: baseline pre-migracao tem DHCP embutido");
check($extract_dhcp_embedded($cur_src) === null, "V6b2a: candidato sem DHCP embutido na consulta VIP");
check(strpos($cur_src, 'vip_dhcp=1#l7-vip-list') !== false, "V6b2a: link modo DHCP exclusivo");
check(strpos($cur_src, '$l7_vip_mode === "dhcp"') !== false, "V6b2a: modo dhcp declarado");

$freeze_v6b1 = $extract_bulk_embedded($ref_v6b1);
check($freeze_v6b1 !== null, "freeze V6b1: bloco lote/import/export baseline extraivel");
check($bulk_embedded_v6b2b === $freeze_v6b1, "freeze V6b2b baseline: lote/import/export identico V6b1 (" .
    strlen($bulk_embedded_v6b2b) . " bytes)");

$extract_prefix = function ($src) {
	$start = strpos($src, '$data = layer7_load_or_default();');
	$end = strpos($src, '$edit_ex = null;', $start === false ? 0 : $start);
	if ($start === false || $end === false) {
		return null;
	}
	return substr($src, $start, $end + strlen('$edit_ex = null;') - $start);
};
$prefix_cur = $extract_prefix($cur_src);
$prefix_v6a = $extract_prefix($v6a_src);
check($prefix_cur !== null && $prefix_v6a !== null, "freeze: prefixo data/edit extraivel");
if ($prefix_cur !== null && $prefix_v6a !== null) {
	check($prefix_cur === $prefix_v6a, "freeze: prefixo data/edit identico V6a (" . strlen($prefix_cur) . " bytes)");
}

require_once __DIR__ . "/harness-exceptions-view/bootstrap.php";
$extract_handlers = function ($src, $start_marker, $end_marker) {
	$start = strpos($src, $start_marker);
	$end = strpos($src, $end_marker, $start === false ? 0 : $start);
	if ($start === false || $end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
};
$exc_cur = $extract_handlers(
	$cur_src,
	'if ($_POST["add_exception"] ?? false) {',
	"\$data = layer7_load_or_default();\n\$exceptions = isset(\$data[\"layer7\"][\"exceptions\"])"
);
$exc_ref = $extract_handlers(
	$v6a_src,
	'if ($_POST["add_exception"] ?? false) {',
	"\$data = layer7_load_or_default();\n\$exceptions = isset(\$data[\"layer7\"][\"exceptions\"])"
);
$vip_cur = $extract_handlers(
	$cur_src,
	'if ($_POST["export_vip_list"] ?? false) {',
	'if ($_POST["add_exception"] ?? false) {'
);
$vip_ref = $extract_handlers(
	$v6a_src,
	'if ($_POST["export_vip_list"] ?? false) {',
	'if ($_POST["add_exception"] ?? false) {'
);
check($exc_cur !== null && $exc_ref !== null, "freeze: handlers excecoes gerais extraidos");
if ($exc_cur !== null && $exc_ref !== null) {
	check($exc_cur === $exc_ref, "freeze: handlers excecoes gerais V6a preservados");
}
check($vip_cur !== null && $vip_ref !== null, "freeze: handlers VIP extraidos");
if ($vip_cur !== null && $vip_ref !== null) {
	check($vip_cur === $vip_ref, "freeze: handlers VIP (export/import/bulk) preservados");
}

/* V6c — migracao visual intencional vs baseline pre-V6c */
check(strpos($cur_src, "layer7_render_styles()") === false, "V6c: sem layer7_render_styles");
check(strpos($cur_src, "layer7_render_footer()") === false, "V6c: sem layer7_render_footer global");
check(strpos($cur_src, "layer7-admin-block") === false, "V6c: sem layer7-admin-block");
check(strpos($cur_src, "layer7-page") === false, "V6c: sem layer7-page");
check(strpos($cur_src, 'class="panel panel-default" id="l7-vip-list"') !== false, "V6c: painel nativo Lista VIP");
check(strpos($cur_src, "text-center text-muted") !== false, "V6c: credito nativo local");
check(strpos($cur_src, 'classList.add("hidden")') !== false, "V6c: filtro VIP usa hidden Bootstrap");
check(strpos($ref_v6c, "layer7_render_styles()") !== false, "V6c baseline: pre-migracao tinha render_styles");
$handlers_v6c = substr($ref_v6c, 0, strpos($ref_v6c, '$pgtitle = array('));
$handlers_cur = substr($cur_src, 0, strpos($cur_src, '$pgtitle = array('));
check($handlers_v6c !== false && $handlers_cur !== false, "V6c: bloco handlers extraivel");
if ($handlers_v6c !== false && $handlers_cur !== false) {
	check($handlers_cur === $handlers_v6c, "V6c: handlers byte-identicos pre-view (" . strlen($handlers_cur) . " bytes)");
}

if ($fail) {
	fwrite(STDERR, "SOME EXCEPTIONS VIP FREEZE TESTS FAILED\n");
	exit(1);
}
echo "ALL EXCEPTIONS VIP FREEZE TESTS PASSED\n";
exit(0);
