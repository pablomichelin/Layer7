<?php
/**
 * BG-174 / V4-A Policies — handlers POST congelados (prefixo byte a byte).
 *
 * Referência pinada: extract de harness-policies-view/source-baseline.php
 * (SHA256 conhecido). Candidato = layer7_policies.php actual.
 * HEAD via git show só quando shell devolve conteúdo plausível (não WASM).
 *
 *   php tests/functional/test_policies_handlers_baseline.php
 */
$root = dirname(__DIR__, 2);
$current = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php";
$baseline_file = __DIR__ . "/harness-policies-view/source-baseline.php";
/** SHA256 do prefixo handlers em source-baseline.php (pin de integridade). */
const L7HP_HANDLERS_SHA256 = "921d918bb1d178ae827cf27045e6b316f2230cea18bec9b24b8d84727d01e5b6";

if (!is_file($current)) {
	fwrite(STDERR, "FAIL actual em falta: {$current}\n");
	exit(1);
}
if (!is_file($baseline_file)) {
	fwrite(STDERR, "FAIL referencia pinada em falta: {$baseline_file}\n");
	exit(1);
}

$extract = function ($src) {
	$start = strpos($src, 'if ($_POST["add_profile_policy"] ?? false) {');
	if ($start === false) {
		return null;
	}
	$marker = '$data = layer7_load_or_default();' . "\n" . '$policies = isset($data["layer7"]["policies"])';
	$end = strpos($src, $marker, $start);
	if ($end === false || $end <= $start) {
		return null;
	}
	return substr($src, $start, $end - $start);
};

$base_h = $extract((string)file_get_contents($baseline_file));
if ($base_h === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair handlers da referencia pinada\n");
	exit(1);
}
$base_sha = hash("sha256", $base_h);
if ($base_sha !== L7HP_HANDLERS_SHA256) {
	fwrite(STDERR, "FAIL SHA da referencia pinada diverge (got {$base_sha}, esperado " . L7HP_HANDLERS_SHA256 . ")\n");
	exit(1);
}

$cur_h = $extract((string)file_get_contents($current));
if ($cur_h === null) {
	fwrite(STDERR, "FAIL nao foi possivel extrair handlers actuais\n");
	exit(1);
}

if ($cur_h !== $base_h) {
	fwrite(STDERR, "FAIL handlers Policies divergem da referencia pinada (source-baseline.php)\n");
	exit(1);
}

$git_checked = false;
if (function_exists("shell_exec")) {
	$git = @shell_exec("git -C " . escapeshellarg($root) .
		" show HEAD:package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php 2>/dev/null");
	if (is_string($git) && strlen($git) > 1000 && strpos($git, 'add_profile_policy') !== false) {
		$git_h = $extract($git);
		if ($git_h !== null) {
			$git_checked = true;
			if ($cur_h !== $git_h) {
				fwrite(STDERR, "FAIL handlers Policies divergem de HEAD\n");
				exit(1);
			}
		}
	}
}

$ref_note = $git_checked ? "source-baseline.php + HEAD" : "source-baseline.php (git indisponivel neste runtime)";
echo "PASS: handlers iguais a {$ref_note} (" . strlen($cur_h) . " bytes, sha256=" . substr(L7HP_HANDLERS_SHA256, 0, 12) . "…)\n";
echo "ALL POLICIES HANDLER BASELINE TESTS PASSED\n";
exit(0);
