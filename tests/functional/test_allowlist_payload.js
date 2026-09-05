#!/usr/bin/env node
/**
 * BG-174 / V5 Allowlist — paridade FormData (jsdom) baseline PHP real vs candidato.
 *
 * Renderiza o baseline versionado e a view actual com os MESMOS dados via
 * harness-allowlist-view/render-parity.php (PHP WASM). Compara FormData real.
 *
 *   LAYER7_PHP=/private/tmp/layer7-php-runtime/node_modules/.bin/php-wasm-cli \
 *   LAYER7_JSDOM=/private/tmp/layer7-dom-runtime/node_modules/jsdom \
 *   node tests/functional/test_allowlist_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");

const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-allowlist-view/render-parity.php");

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
		console.error(raw.slice(0, 400));
		process.exit(1);
	}
	return parsed;
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_allowlist.php",
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

function entriesPreview(entries) {
	return JSON.stringify(entries.map(function (pair) {
		const v = pair[1];
		const shown = v.length > 40 ? v.slice(0, 40) + "…" : v;
		return [pair[0], shown];
	}));
}

function allowlistFormPayload(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = doc.querySelector('form[action="layer7_allowlist.php"]')
		|| doc.querySelector('form[action*="layer7_allowlist.php"]');
	if (!form) {
		return { error: "form ausente" };
	}
	const submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
	if (submits.length !== 1) {
		return { error: "submit count=" + submits.length + " (esperado 1)" };
	}
	const submit = submits[0];
	const namedSubmit = submit.hasAttribute("name") && submit.getAttribute("name") !== "";
	if (typeof dom.window.FormData !== "function") {
		return { error: "FormData indisponivel" };
	}
	let fd;
	try {
		fd = new dom.window.FormData(form, submit);
	} catch (e) {
		return { error: "FormData falhou: " + (e && e.message ? e.message : e) };
	}
	const entries = Array.from(fd.entries());
	return {
		method: (form.getAttribute("method") || "get").toLowerCase(),
		action: form.getAttribute("action") || "",
		entries: entries,
		submitCount: submits.length,
		namedSubmit: namedSubmit,
	};
}

function textareaValue(html) {
	const dom = openDom(html);
	const el = dom.window.document.querySelector("#l7-allow-entries")
		|| dom.window.document.querySelector('textarea[name="entries"]');
	if (!el) {
		return null;
	}
	return el.value;
}

function seedPanelPresent(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	if (doc.querySelector("#l7-allow-seed")) {
		return true;
	}
	if (doc.body && doc.body.textContent.indexOf("Lista-semente embutida") !== -1) {
		return true;
	}
	return false;
}

function seedOutsideMutableForm(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = doc.querySelector('form[action="layer7_allowlist.php"]')
		|| doc.querySelector('form[action*="layer7_allowlist.php"]');
	const seed = doc.querySelector("#l7-allow-seed");
	if (!form || !seed) {
		return false;
	}
	let node = seed;
	while (node && node.parentNode) {
		if (node.parentNode === form) {
			return false;
		}
		node = node.parentNode;
	}
	return true;
}

function assertPayloadParity(label, base, cand) {
	check(!base.error && !cand.error, label + ": FormData extraido");
	if (base.error || cand.error) {
		if (base.error) console.log("  baseline: " + base.error);
		if (cand.error) console.log("  candidato: " + cand.error);
		return;
	}
	check(base.method === "post" && cand.method === "post", label + ": method POST");
	check(
		base.action === "layer7_allowlist.php" && cand.action === "layer7_allowlist.php",
		label + ": action layer7_allowlist.php"
	);
	check(base.submitCount === 1 && cand.submitCount === 1, label + ": exactamente um submit");
	check(!base.namedSubmit && !cand.namedSubmit, label + ": submit sem name");
	check(base.entries.length === 2 && cand.entries.length === 2, label + ": exactamente dois pares FormData");
	check(entriesEqual(base.entries, cand.entries), label + ": Array.from(fd.entries()) identico " + entriesPreview(base.entries));
	const extraNames = cand.entries.map(function (p) { return p[0]; }).filter(function (k) { return k === "save"; });
	check(extraNames.length === 0, label + ": candidato sem Save extra");
}

const lines256 = [];
for (let i = 1; i <= 256; i++) {
	lines256.push("host" + i + ".example.com");
}
const adversarial = "<script>alert(1)</script>\nline2\n# c\n8.8.8.8";

const parityCases = [
	{ name: "get0", entries: [], seed: [] },
	{ name: "get1", entries: ["bb.com.br", "8.8.8.8"], seed: ["seed.example.com"] },
	{ name: "get256", entries: lines256, seed: [] },
	{ name: "adversarial", entries: [adversarial], seed: ["seed1.example.com", "seed2.example.com"] },
];

console.log("Runtime: FormData jsdom; render baseline PHP real + candidato (mesmos dados)");
console.log("NOTA: form-original.html e fixture manual auxiliar, nao evidencia de baseline");

for (let i = 0; i < parityCases.length; i++) {
	const c = parityCases[i];
	const pair = renderPair({ entries: c.entries, seed: c.seed });
	const base = allowlistFormPayload(pair.baseline);
	const cand = allowlistFormPayload(pair.candidate);
	assertPayloadParity(c.name, base, cand);
	if (c.name === "get1" || c.name === "adversarial") {
		check(seedPanelPresent(pair.candidate), c.name + ": candidato seed visivel");
		check(seedOutsideMutableForm(pair.candidate), c.name + ": candidato seed fora do form mutavel");
	}
}

/* retry POST — comportamento novo do candidato; nao paridade com baseline */
const retryPair = renderPair({
	entries: ["keep.me"],
	seed: [],
	post: { save_allowlist: "1", entries: "" },
	save_result: false,
});
const retryVal = textareaValue(retryPair.candidate);
check(retryVal === "", "retry vazio savefalse: textarea preserva POST vazio");
check(!retryPair.candidate.includes("l7-savemsg"), "retry vazio savefalse: sem savemsg");

const retryMulti = renderPair({
	entries: ["x.example.com"],
	seed: [],
	post: { save_allowlist: "1", entries: adversarial },
	save_result: false,
});
check(textareaValue(retryMulti.candidate) === adversarial, "retry multilinha savefalse: POST integral");

if (fail) {
	console.error("SOME ALLOWLIST PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL ALLOWLIST PAYLOAD TESTS PASSED");
process.exit(0);
