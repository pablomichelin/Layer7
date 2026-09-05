#!/usr/bin/env node
/**
 * BG-174 / V6a Exceptions — paridade FormData baseline PHP real vs candidato.
 * Exige method/action exactos, submitter value=1, payloads completos.
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");

const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-exceptions-view/render-parity.php");

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

function openDom(html, url) {
	return new JSDOM(html, {
		url: url || "http://127.0.0.1/packages/layer7/layer7_exceptions.php",
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

function formPayload(html, formSelector, submitName) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = doc.querySelector(formSelector);
	if (!form) {
		return { error: "form ausente: " + formSelector };
	}
	const submit = form.querySelector(
		'button[name="' + submitName + '"], input[name="' + submitName + '"]'
	);
	if (!submit) {
		return { error: "submitter ausente: " + submitName };
	}
	let fd;
	try {
		fd = new dom.window.FormData(form, submit);
	} catch (e) {
		return { error: "FormData falhou: " + (e && e.message ? e.message : e) };
	}
	return {
		method: (form.getAttribute("method") || "get").toLowerCase(),
		action: form.getAttribute("action") || "",
		submitValue: fd.get(submitName),
		submitAttr: submit.getAttribute("value"),
		entries: Array.from(fd.entries()),
	};
}

function assertParity(label, base, cand, submitName) {
	check(!base.error && !cand.error, label + ": FormData extraido");
	if (base.error || cand.error) {
		if (base.error) console.log("  baseline: " + base.error);
		if (cand.error) console.log("  cand: " + cand.error);
		return;
	}
	check(base.method === "post" && cand.method === "post", label + ": method POST");
	check(base.action === cand.action, label + ": action identico (" + base.action + ")");
	check(
		entriesEqual(base.entries, cand.entries),
		label + ": entries identicos " + entriesPreview(base.entries)
	);
	check(base.submitValue === "1" && cand.submitValue === "1", label + ": submitter value=1");
	check(base.submitAttr === "1" && cand.submitAttr === "1", label + ": attr value=1");
	check(
		(base.entries.filter(function (p) { return p[0] === submitName; })).length === 1,
		label + ": um par submitter"
	);
}

const ex1 = [{
	id: "mgmt",
	enabled: true,
	priority: 500,
	action: "allow",
	hosts: ["10.0.0.1"],
}];
const exFull = [{
	id: "full",
	enabled: true,
	priority: 0,
	action: "monitor",
	hosts: ["10.0.0.1", "2001:db8::1"],
	cidrs: ["192.0.2.0/24"],
	interfaces: ["lan"],
}];
const ex2 = [
	{ id: "a", enabled: true, priority: 500, action: "allow", hosts: ["10.0.0.1"] },
	{ id: "b", enabled: false, priority: 100, action: "block", cidrs: ["192.0.2.0/24"] },
];
const ex16 = [];
for (let i = 1; i <= 16; i++) {
	ex16.push({
		id: "ex" + i,
		enabled: true,
		priority: 500 - i,
		action: "allow",
		hosts: ["10.0.0." + i],
	});
}

console.log("Runtime: FormData jsdom; baseline PHP real + candidato");

/* lista save_exceptions */
const listPair = renderPair({ exceptions: ex2 });
const listSel = 'form[action="layer7_exceptions.php#l7-exceptions"]';
const baseList = formPayload(listPair.baseline, listSel, "save_exceptions");
const candList = formPayload(listPair.candidate, listSel, "save_exceptions");
assertParity("list-save", baseList, candList, "save_exceptions");

/* add */
const addPair = renderPair({ exceptions: ex1, get: { new: "1" } });
const addSel = 'form[action="layer7_exceptions.php#l7-add-exc"]';
const baseAdd = formPayload(addPair.baseline, addSel, "add_exception");
const candAdd = formPayload(addPair.candidate, addSel, "add_exception");
assertParity("add-form", baseAdd, candAdd, "add_exception");
check(
	candAdd.entries.some(function (p) { return p[0] === "new_action"; }),
	"add: campo new_action presente"
);
["allow", "block", "monitor", "tag"].forEach(function (act) {
	check(
		candAdd.entries.some(function (p) { return p[0] === "new_action" && p[1] === act; })
			|| addPair.candidate.indexOf('value="' + act + '"') >= 0,
		"add: opcao " + act + " no select"
	);
});

/* edit completo */
const editPair = renderPair({ exceptions: exFull, get: { edit: "0" } });
const editSel = 'form[action="layer7_exceptions.php#l7-edit-exc"]';
const baseEdit = formPayload(editPair.baseline, editSel, "save_exception_edit");
const candEdit = formPayload(editPair.candidate, editSel, "save_exception_edit");
assertParity("edit-form", baseEdit, candEdit, "save_exception_edit");
check(
	candEdit.entries.some(function (p) { return p[0] === "edit_hosts" && p[1].indexOf("2001:db8::1") >= 0; }),
	"edit: hosts IPv6 no payload"
);
check(
	candEdit.entries.some(function (p) { return p[0] === "edit_cidrs" && p[1].indexOf("192.0.2.0/24") >= 0; }),
	"edit: CIDR no payload"
);
check(
	candEdit.entries.some(function (p) { return p[0] === "edit_exc_ifaces[]" && p[1] === "lan"; }),
	"edit: interface lan marcada"
);
check(
	candEdit.entries.some(function (p) { return p[0] === "edit_action" && p[1] === "monitor"; }),
	"edit: action monitor"
);
check(
	candEdit.entries.some(function (p) { return p[0] === "edit_priority" && p[1] === "0"; }),
	"edit: prioridade 0"
);

/* delete */
const delPair = renderPair({ exceptions: ex2 });
const baseDelDom = openDom(delPair.baseline);
const candDelDom = openDom(delPair.candidate);
const baseDelForm = Array.from(baseDelDom.window.document.querySelectorAll('form[action*="layer7_exceptions.php#l7-exceptions"]'))
	.find(function (f) { return f.querySelector('[name="delete_exception"]'); });
const candDelForm = Array.from(candDelDom.window.document.querySelectorAll('form[action*="layer7_exceptions.php#l7-exceptions"]'))
	.find(function (f) { return f.querySelector('[name="delete_exception"]'); });
check(!!baseDelForm && !!candDelForm, "delete: form encontrado");
if (baseDelForm && candDelForm) {
	const mk = function (win, form, name) {
		const btn = form.querySelector('[name="' + name + '"]');
		const fd = new win.FormData(form, btn);
		return {
			method: (form.getAttribute("method") || "get").toLowerCase(),
			action: form.getAttribute("action") || "",
			submitValue: fd.get(name),
			submitAttr: btn.getAttribute("value"),
			entries: Array.from(fd.entries()),
		};
	};
	const bd = mk(baseDelDom.window, baseDelForm, "delete_exception");
	const cd = mk(candDelDom.window, candDelForm, "delete_exception");
	check(bd.action === cd.action, "delete: action identico");
	check(entriesEqual(bd.entries, cd.entries), "delete: entries identicos " + entriesPreview(bd.entries));
	check(bd.submitValue === "1" && cd.submitValue === "1", "delete: submitter value=1");
}

/* lista 16: todos eon marcados */
const list16Pair = renderPair({ exceptions: ex16 });
const dom16 = openDom(list16Pair.candidate);
const form16 = dom16.window.document.querySelector(listSel);
check(!!form16, "lista16: form");
if (form16) {
	const boxes = form16.querySelectorAll('input[name^="eon["]');
	boxes.forEach(function (cb) { cb.checked = true; });
	const saveBtn = form16.querySelector('[name="save_exceptions"]');
	const fd16 = new dom16.window.FormData(form16, saveBtn);
	const eonPairs = Array.from(fd16.entries()).filter(function (p) { return p[0].indexOf("eon[") === 0; });
	check(eonPairs.length === 16, "lista16: 16 pares eon no FormData");
	eonPairs.forEach(function (p) {
		check(p[1] === "1", "lista16: eon value literal 1 (" + p[0] + ")");
	});
}

/* retry add: prioridade vazia */
const retryAdd = renderPair({
	exceptions: ex1,
	get: { new: "1" },
	post: {
		add_exception: "1",
		new_id: "x",
		new_hosts: "",
		new_cidrs: "",
		new_priority: "",
		new_action: "allow",
	},
});
const retryDom = openDom(retryAdd.candidate);
const pri = retryDom.window.document.querySelector('input[name="new_priority"]');
check(pri && pri.getAttribute("value") === "", "retry add: prioridade vazia no DOM");

/* retry add savefalse: enabled off + interfaces vazio */
const retrySavefalse = renderPair({
	exceptions: ex1,
	get: { new: "1" },
	post: {
		add_exception: "1",
		new_id: "raw",
		new_hosts: "10.0.0.5",
		new_cidrs: "",
		new_priority: "123",
		new_action: "tag",
	},
	save_result: false,
});
const sfDom = openDom(retrySavefalse.candidate);
const sfForm = sfDom.window.document.querySelector(addSel);
check(!!sfForm, "add savefalse: form");
if (sfForm) {
	const btn = sfForm.querySelector('[name="add_exception"]');
	const fd = new sfDom.window.FormData(sfForm, btn);
	check(fd.get("new_action") === "tag", "add savefalse: action tag");
	check(!fd.get("new_enabled"), "add savefalse: sem new_enabled");
	check(!Array.from(fd.entries()).some(function (p) { return p[0] === "new_exc_ifaces[]"; }),
		"add savefalse: sem interfaces");
}

if (fail) {
	console.error("SOME EXCEPTIONS PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS PAYLOAD TESTS PASSED");
process.exit(0);
