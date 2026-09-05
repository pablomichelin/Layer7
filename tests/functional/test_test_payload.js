#!/usr/bin/env node
/**
 * V9 Teste — FormData + fixture nDPI V3 (472 protocolos / 20 categorias) baseline/candidato.
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_test_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-test-view/render-parity.php");

/** Espelha l7hc_fixture_catalog_472() (V3 Categories harness). */
function buildV3FixtureCatalog472() {
	const protos = [];
	for (let i = 1; i <= 472; i++) {
		protos.push("Proto" + String(i).padStart(3, "0"));
	}
	const cats = [];
	for (let c = 1; c <= 20; c++) {
		cats.push("Cat" + String(c).padStart(2, "0"));
	}
	protos.sort();
	cats.sort();
	return { protos: protos, cats: cats };
}

const fixture472 = buildV3FixtureCatalog472();
const sortedProtos = fixture472.protos;
const sortedCats = fixture472.cats;

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function renderPair(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	let parsed;
	try {
		parsed = JSON.parse(raw);
	} catch (e) {
		console.error("FAIL: JSON invalido de render-parity.php");
		console.error(raw.slice(0, 500));
		process.exit(1);
	}
	return parsed;
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_test.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
}

function entriesEqual(a, b) {
	if (a.length !== b.length) {
		return false;
	}
	for (let i = 0; i < a.length; i++) {
		if (a[i][0] !== b[i][0] || a[i][1] !== b[i][1]) {
			return false;
		}
	}
	return true;
}

function testForm(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = doc.querySelector('form[action*="layer7_test.php#l7-test"]');
	if (!form) {
		return { error: "form ausente" };
	}
	const submit = form.querySelector('button[name="run_test"], input[name="run_test"]');
	if (!submit) {
		return { error: "run_test ausente" };
	}
	const fd = new dom.window.FormData(form);
	fd.append(submit.getAttribute("name"), submit.getAttribute("value") || "1");
	const entries = Array.from(fd.entries()).sort(function (a, b) {
		if (a[0] !== b[0]) {
			return a[0] < b[0] ? -1 : 1;
		}
		return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0;
	});
	return { form: form, entries: entries };
}

function selectValues(html, name) {
	const dom = openDom(html);
	const sel = dom.window.document.querySelector('select[name="' + name + '"]');
	if (!sel) {
		return null;
	}
	return Array.from(sel.options).map(function (o) { return o.value; });
}

function assertParity(label, baseHtml, candHtml, expectedEntries) {
	const base = testForm(baseHtml);
	const cand = testForm(candHtml);
	check(!base.error, label + ": baseline form (" + (base.error || "ok") + ")");
	check(!cand.error, label + ": candidato form (" + (cand.error || "ok") + ")");
	if (base.error || cand.error) {
		return;
	}
	check(entriesEqual(base.entries, expectedEntries), label + ": baseline FormData esperado");
	check(entriesEqual(cand.entries, expectedEntries), label + ": candidato FormData esperado");
	check(entriesEqual(base.entries, cand.entries), label + ": paridade baseline/candidato");
}

const emptyEntries = [
	["run_test", "1"],
	["test_domain", ""],
	["test_ndpi_app", ""],
	["test_ndpi_cat", ""],
	["test_src_ip", ""],
];
const pairEmpty = renderPair({});
assertParity("vazio", pairEmpty.baseline, pairEmpty.candidate, emptyEntries);

const retryOpts = {
	test_domain: "youtube.com",
	test_src_ip: "10.0.85.50",
	test_ndpi_app: "Proto042",
	test_ndpi_cat: "Cat02",
};
const retryEntries = [
	["run_test", "1"],
	["test_domain", "youtube.com"],
	["test_ndpi_app", "Proto042"],
	["test_ndpi_cat", "Cat02"],
	["test_src_ip", "10.0.85.50"],
];
const pairRetry = renderPair(retryOpts);
assertParity("retry", pairRetry.baseline, pairRetry.candidate, retryEntries);

const adv = '\'"<script>alert(1)</script>';
const advEntries = [
	["run_test", "1"],
	["test_domain", adv],
	["test_ndpi_app", ""],
	["test_ndpi_cat", ""],
	["test_src_ip", ""],
];
const pairAdv = renderPair({ test_domain: adv });
assertParity("adversarial-dominio", pairAdv.baseline, pairAdv.candidate, advEntries);

const baseAppOpts = selectValues(pairEmpty.baseline, "test_ndpi_app");
const candAppOpts = selectValues(pairEmpty.candidate, "test_ndpi_app");
const baseCatOpts = selectValues(pairEmpty.baseline, "test_ndpi_cat");
const candCatOpts = selectValues(pairEmpty.candidate, "test_ndpi_cat");
const expectedApp = [""].concat(sortedProtos);
const expectedCat = [""].concat(sortedCats);

check(expectedApp.length === 473, "fixture V3: 472 protocolos + opcao vazia (473)");
check(expectedCat.length === 21, "fixture V3: 20 categorias + opcao vazia (21)");
check(expectedApp[1] === "Proto001" && expectedApp[472] === "Proto472",
	"fixture V3: extremos protocolo");
check(expectedCat[1] === "Cat01" && expectedCat[20] === "Cat20",
	"fixture V3: extremos categoria");

check(Array.isArray(baseAppOpts), "fixture V3: baseline app select");
check(Array.isArray(candAppOpts), "fixture V3: candidato app select");
check(baseAppOpts.length === expectedApp.length, "fixture V3: app count baseline (" +
	baseAppOpts.length + " vs " + expectedApp.length + ")");
check(candAppOpts.length === expectedApp.length, "fixture V3: app count candidato");
check(JSON.stringify(baseAppOpts) === JSON.stringify(expectedApp),
	"fixture V3: app ordem baseline");
check(JSON.stringify(candAppOpts) === JSON.stringify(expectedApp),
	"fixture V3: app ordem candidato");
check(JSON.stringify(baseAppOpts) === JSON.stringify(candAppOpts),
	"fixture V3: app paridade baseline/candidato");

check(baseCatOpts.length === expectedCat.length, "fixture V3: cat count baseline");
check(candCatOpts.length === expectedCat.length, "fixture V3: cat count candidato");
check(JSON.stringify(baseCatOpts) === JSON.stringify(expectedCat),
	"fixture V3: cat ordem baseline");
check(JSON.stringify(candCatOpts) === JSON.stringify(expectedCat),
	"fixture V3: cat ordem candidato");
check(JSON.stringify(baseCatOpts) === JSON.stringify(candCatOpts),
	"fixture V3: cat paridade baseline/candidato");

if (fail) {
	process.exit(1);
}
console.log("ALL TEST PAYLOAD DOM TESTS PASSED");
process.exit(0);
