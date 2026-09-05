#!/usr/bin/env node
/**
 * V6b2b — export handler real (subprocess exit) + zero efeitos (probe com handler real).
 * Orquestracao Node/runPhp — sem proc_open dentro PHP WASM.
 *
 *   LAYER7_PHP=... node tests/functional/test_exceptions_vip_bulk_export.js
 *
 * Headers HTTP: pendente (fonte congelada no handler; nao provado aqui).
 */
"use strict";

const path = require("path");
const { runPhp } = require("./lib/layer7-test-runtime");

const harnessDir = path.join(__dirname, "harness-exceptions-view");
const exportSub = path.join(harnessDir, "export-subprocess.php");
const exportProbe = path.join(harnessDir, "export-probe.php");
const exportFixtures = path.join(harnessDir, "export-fixtures.php");

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

const fixtures = JSON.parse(runPhp(exportFixtures, []));

function runExportBody(data) {
	return runPhp(exportSub, [JSON.stringify({ data: data })]);
}

function runExportProbe(data) {
	return JSON.parse(runPhp(exportProbe, [JSON.stringify({ data: data })]));
}

function linesInBody(body, expectedLines, labelPrefix) {
	check(Array.isArray(expectedLines), labelPrefix + ": fixture expected_lines presente");
	if (!Array.isArray(expectedLines)) {
		return;
	}
	expectedLines.forEach(function (line, idx) {
		check(body.indexOf(line) !== -1, labelPrefix + ": linha " + (idx + 1) + " " + line);
	});
	check(
		expectedLines.length === 0 || body.split("\n").filter(function (l) {
			return l !== "" && l.charAt(0) !== "#";
		}).length >= expectedLines.length,
		labelPrefix + ": contagem linhas dados >= fixture"
	);
}

["empty", "one", "full"].forEach(function (key) {
	const data = fixtures[key];
	const probe = runExportProbe(data);
	check(probe.handler_exit === true, "export " + key + ": handler exit instrumentado");
	check(probe.save_calls === 0 && probe.resync_calls === 0,
		"export " + key + ": zero save/resync (handler real)");
	check(typeof probe.text === "string" && probe.text.length > 0,
		"export " + key + ": texto probe nao vazio");

	let body;
	try {
		body = runExportBody(data);
	} catch (e) {
		check(false, "export " + key + ": subprocess handler executou");
		return;
	}
	check(body === probe.text, "export " + key + ": subprocess identico ao handler real");
	check(body.indexOf("<") === -1 && body.indexOf(">") === -1, "export " + key + ": sem HTML");
	if (key === "empty") {
		check(body.indexOf("# Lista VIP Layer7") !== -1, "export0: cabecalho congelado");
	}
	if (key === "full") {
		check((fixtures.expected_lines.full || []).length === 48, "export48: fixture 48 linhas dados");
	}
	linesInBody(body, fixtures.expected_lines[key], "export " + key);
});

if (fail) {
	console.error("SOME EXCEPTIONS VIP BULK EXPORT TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP BULK EXPORT TESTS PASSED");
process.exit(0);
