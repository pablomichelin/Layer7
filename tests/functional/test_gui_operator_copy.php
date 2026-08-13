<?php
/*
 * GUI operador: paginas MITM / Identity / Settings nao devem expor
 * ADRs, gates de trilha, paths de docs/ nem checklist de lab.
 * Uso: php tests/functional/test_gui_operator_copy.php
 */
$root = dirname(__DIR__, 2);
$files = array(
	$root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_mitm.php",
	$root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_identity.php",
	$root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_settings.php",
);
$forbidden = array(
	"ADR-00",
	"docs/01-architecture",
	"20.8",
	"20.10a",
	"20.10b",
	"20.11",
	"1.9.47 P3",
	"Squid rejeitado",
	"SKU Y",
	"P4.1",
	"(30.14)",
	"(N3)",
	"GI2/GI3",
);

function l7_gui_strip_comments($src)
{
	$src = preg_replace('#/\*.*?\*/#s', "", $src);
	$src = preg_replace('#^[ \t]*//.*$#m', "", $src);
	return $src;
}

$fail = 0;
foreach ($files as $path) {
	if (!is_file($path)) {
		fwrite(STDERR, "FAIL ficheiro em falta: {$path}\n");
		exit(1);
	}
	$body = l7_gui_strip_comments(file_get_contents($path));
	foreach ($forbidden as $needle) {
		if (strpos($body, $needle) !== false) {
			fwrite(STDERR, "FAIL " . basename($path) . " expoe copy interno: {$needle}\n");
			$fail = 1;
		}
	}
}
if ($fail) {
	exit(1);
}
fwrite(STDOUT, "PASS test_gui_operator_copy.php\n");
