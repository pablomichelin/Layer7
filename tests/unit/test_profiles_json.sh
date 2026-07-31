#!/bin/sh
# Valida profiles.json: JSON, campos, ids unicos, hosts, limites, icones, nDPI.
set -u
RC=0
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
PROFILES="$ROOT/package/pfSense-pkg-layer7/files/usr/local/etc/layer7/profiles.json"
NDPI_PROTOS="$ROOT/tests/fixtures/ndpi-protocol-names-builder.txt"
NDPI_CATS="$ROOT/tests/fixtures/ndpi-category-names-builder.txt"

pass() { printf "PASS: %s\n" "$1"; }
fail() { printf "FAIL: %s\n" "$1"; RC=1; }

[ -f "$PROFILES" ] || { fail "profiles.json ausente"; exit 1; }

# JSON valido
if ! python3 -c "import json; json.load(open('$PROFILES'))" 2>/dev/null; then
	if command -v php >/dev/null 2>&1; then
		php -r 'json_decode(file_get_contents("'"$PROFILES"'"), true) or exit(1);' || { fail "JSON invalido"; exit 1; }
	else
		fail "JSON invalido (python3/php indisponivel)"
		exit 1
	fi
fi
pass "JSON valido"

# Validacao detalhada via PHP (disponivel no dev e no appliance)
PHP_BIN=$(command -v php 2>/dev/null || true)
if [ -z "$PHP_BIN" ]; then
	printf "SKIP: php ausente para validacao detalhada\n"
	exit "$RC"
fi

"$PHP_BIN" -r '
$path = "'"$PROFILES"'";
$proto_file = "'"$NDPI_PROTOS"'";
$cat_file = "'"$NDPI_CATS"'";
$raw = file_get_contents($path);
$j = json_decode($raw, true);
if (!is_array($j) || !isset($j["profiles"]) || !is_array($j["profiles"])) {
	fwrite(STDERR, "FAIL: estrutura profiles[] em falta\n");
	exit(1);
}
$valid_protos = array();
if (is_readable($proto_file)) {
	foreach (file($proto_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
		$valid_protos[trim($ln)] = true;
	}
}
$valid_cats = array();
if (is_readable($cat_file)) {
	foreach (file($cat_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
		$valid_cats[trim($ln)] = true;
	}
}
$ids = array();
$fa_re = "/^fa-[a-z0-9-]+$/";
$host_re = "/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$/i";
$ndpi_removed = array();
$ndpi_ok = array();
foreach ($j["profiles"] as $i => $p) {
	$label = "profile[$i]";
	foreach (array("id", "name", "description") as $req) {
		if (empty($p[$req]) || !is_string($p[$req])) {
			fwrite(STDERR, "FAIL: $label campo obrigatorio $req\n");
			exit(1);
		}
	}
	if (!preg_match("/^[a-z0-9-]{1,64}$/", $p["id"])) {
		fwrite(STDERR, "FAIL: id invalido {$p["id"]}\n");
		exit(1);
	}
	if (isset($ids[$p["id"]])) {
		fwrite(STDERR, "FAIL: id duplicado {$p["id"]}\n");
		exit(1);
	}
	$ids[$p["id"]] = true;
	if (isset($p["icon"]) && $p["icon"] !== "" && !preg_match($fa_re, $p["icon"])) {
		fwrite(STDERR, "FAIL: icon invalido em {$p["id"]}: {$p["icon"]}\n");
		exit(1);
	}
	$apps = (isset($p["ndpi_apps"]) && is_array($p["ndpi_apps"])) ? $p["ndpi_apps"] : array();
	$cats = (isset($p["ndpi_categories"]) && is_array($p["ndpi_categories"])) ? $p["ndpi_categories"] : array();
	$hosts = (isset($p["hosts"]) && is_array($p["hosts"])) ? $p["hosts"] : array();
	if (count($apps) > 64) { fwrite(STDERR, "FAIL: {$p["id"]} >64 apps\n"); exit(1); }
	if (count($cats) > 8) { fwrite(STDERR, "FAIL: {$p["id"]} >8 cats\n"); exit(1); }
	if (count($hosts) > 64) { fwrite(STDERR, "FAIL: {$p["id"]} >64 hosts\n"); exit(1); }
	foreach ($hosts as $h) {
		if (!is_string($h) || !preg_match($host_re, $h)) {
			fwrite(STDERR, "FAIL: host invalido em {$p["id"]}: $h\n");
			exit(1);
		}
	}
	foreach ($apps as $a) {
		if (!is_string($a) || $a === "") { fwrite(STDERR, "FAIL: app vazia em {$p["id"]}\n"); exit(1); }
		if (!empty($valid_protos) && !isset($valid_protos[$a])) {
			$ndpi_removed[] = "{$p["id"]}:app:$a";
		} else {
			$ndpi_ok[] = "{$p["id"]}:app:$a";
		}
	}
	foreach ($cats as $c) {
		if (!is_string($c) || $c === "") { fwrite(STDERR, "FAIL: cat vazia em {$p["id"]}\n"); exit(1); }
		if (!empty($valid_cats) && !isset($valid_cats[$c])) {
			$ndpi_removed[] = "{$p["id"]}:cat:$c";
		} else {
			$ndpi_ok[] = "{$p["id"]}:cat:$c";
		}
	}
}
printf("OK: %d perfis, ids unicos, limites e hosts validos\n", count($j["profiles"]));
if (!empty($ndpi_removed)) {
	fwrite(STDERR, "FAIL: nDPI invalidos (builder): ".implode(", ", $ndpi_removed)."\n");
	exit(1);
}
if (!empty($valid_protos)) {
	printf("OK: nDPI validado contra builder (%d refs)\n", count($ndpi_ok));
}
' || { fail "validacao detalhada profiles.json"; exit 1; }

pass "profiles.json estrutura, limites e nDPI"
exit "$RC"
