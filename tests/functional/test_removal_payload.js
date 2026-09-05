#!/usr/bin/env node
/**
 * V11 Remoção — FormData baseline/candidato (4 combinações checkboxes + confirmação).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_removal_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-removal-view/render-parity.php");
const readyOpts = { pkg_installed: true, job_running: false };

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
		url: "http://127.0.0.1/packages/layer7/layer7_removal.php",
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

function sortEntries(entries) {
	return entries.slice().sort(function (a, b) {
		if (a[0] !== b[0]) {
			return a[0] < b[0] ? -1 : 1;
		}
		return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0;
	});
}

function findRemoveForm(doc) {
	const forms = Array.from(doc.querySelectorAll("form"));
	for (const form of forms) {
		if (form.querySelector('[name="layer7_pkg_remove_do"]')) {
			return form;
		}
	}
	return null;
}

function collectFormData(html, combo) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findRemoveForm(doc);
	if (!form) {
		return { error: "form ausente" };
	}
	const lic = form.querySelector('[name="keep_license"]');
	const cfg = form.querySelector('[name="keep_config"]');
	const confirm = form.querySelector('[name="layer7_remove_confirm"]');
	const submit = form.querySelector('[name="layer7_pkg_remove_do"]');
	if (!lic || !cfg || !confirm || !submit) {
		return { error: "campos ausentes" };
	}
	lic.checked = !!combo.keep_license;
	cfg.checked = !!combo.keep_config;
	confirm.value = combo.confirm || "";
	const fd = new dom.window.FormData(form);
	fd.append(submit.getAttribute("name"), submit.getAttribute("value") || "1");
	return { entries: sortEntries(Array.from(fd.entries())) };
}

function expectedEntries(combo) {
	const entries = [];
	if (combo.keep_license) {
		entries.push(["keep_license", "1"]);
	}
	if (combo.keep_config) {
		entries.push(["keep_config", "1"]);
	}
	if (combo.confirm) {
		entries.push(["layer7_remove_confirm", combo.confirm]);
	}
	entries.push(["layer7_pkg_remove_do", "1"]);
	return sortEntries(entries);
}

function assertParity(label, baseHtml, candHtml, combo) {
	const expected = expectedEntries(combo);
	const base = collectFormData(baseHtml, combo);
	const cand = collectFormData(candHtml, combo);
	check(!base.error, label + ": baseline form (" + (base.error || "ok") + ")");
	check(!cand.error, label + ": candidato form (" + (cand.error || "ok") + ")");
	if (base.error || cand.error) {
		return;
	}
	check(entriesEqual(base.entries, expected), label + ": baseline FormData esperado");
	check(entriesEqual(cand.entries, expected), label + ": candidato FormData esperado");
	check(entriesEqual(base.entries, cand.entries), label + ": paridade baseline/candidato");
}

const pair = renderPair(readyOpts);
check(pair.baseline.indexOf("layer7_pkg_remove_do") !== -1, "form: baseline tem submitter");
check(pair.candidate.indexOf("layer7_pkg_remove_do") !== -1, "form: candidato tem submitter");

const combos = [
	{ label: "nenhum", combo: { keep_license: false, keep_config: false, confirm: "REMOVER" } },
	{ label: "licenca", combo: { keep_license: true, keep_config: false, confirm: "REMOVER" } },
	{ label: "config", combo: { keep_license: false, keep_config: true, confirm: "REMOVER" } },
	{ label: "ambos", combo: { keep_license: true, keep_config: true, confirm: "REMOVER" } },
];

for (const item of combos) {
	assertParity("checkbox-" + item.label, pair.baseline, pair.candidate, item.combo);
}

const notInstalled = renderPair({ pkg_installed: false });
check(notInstalled.baseline.indexOf('name="keep_license"') === -1, "notinstalled: baseline sem form");
check(notInstalled.candidate.indexOf('name="keep_license"') === -1, "notinstalled: candidato sem form");

const running = renderPair({ pkg_installed: true, job_running: true });
check(running.baseline.indexOf('name="layer7_pkg_remove_do"') === -1, "running: baseline sem submitter");
check(running.candidate.indexOf('name="layer7_pkg_remove_do"') === -1, "running: candidato sem submitter");

if (fail) {
	process.exit(1);
}
console.log("ALL REMOVAL PAYLOAD DOM TESTS PASSED");
process.exit(0);
