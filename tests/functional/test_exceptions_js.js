#!/usr/bin/env node
/**
 * BG-174 / V6a Exceptions — onclick real dos botoes + confirm delete (zero side-effects).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_js.js
 */
"use strict";

const fs = require("fs");
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const root = path.resolve(__dirname, "../..");
const phpPath = path.join(
	root,
	"package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php"
);
const renderPhp = path.join(__dirname, "harness-exceptions-view/render-parity.php");
const phpSource = fs.readFileSync(phpPath, "utf8");

function extractFn(src, name) {
	const re = new RegExp("function\\s+" + name + "\\s*\\([^)]*\\)\\s*\\{", "m");
	const m = src.match(re);
	if (!m || m.index === undefined) {
		return null;
	}
	let i = m.index + m[0].length - 1;
	let depth = 0;
	for (; i < src.length; i++) {
		const ch = src[i];
		if (ch === "{") {
			depth++;
		} else if (ch === "}") {
			depth--;
			if (depth === 0) {
				return src.slice(m.index, i + 1);
			}
		}
	}
	return null;
}

const fnSetChecks = extractFn(phpSource, "l7setChecks");
if (!fnSetChecks) {
	console.error("FAIL: l7setChecks em falta no produto");
	process.exit(1);
}

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function renderCand(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	const parsed = JSON.parse(raw);
	return parsed.candidate;
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_exceptions.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
}

function invokeAttr(win, el, attrName, ev) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return { ret: undefined, ev: ev };
	}
	const evt = ev || { preventDefault: function () { this.prevented = true; } };
	const fn = new win.Function("event", code);
	const ret = fn.call(el, evt);
	return { ret: ret, ev: evt };
}

function installNetworkCounters(win) {
	const counters = { fetch: 0, submit: 0, requestSubmit: 0 };
	win.fetch = function () {
		counters.fetch++;
		return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } });
	};
	const proto = win.HTMLFormElement.prototype;
	proto.submit = function () { counters.submit++; };
	proto.requestSubmit = function () { counters.requestSubmit++; };
	return counters;
}

function injectScript(win) {
	win.eval(fnSetChecks);
}

function ifaceButtons(doc, listId) {
	const list = doc.getElementById(listId);
	if (!list || !list.previousElementSibling) {
		return null;
	}
	const tools = list.previousElementSibling;
	const buttons = tools.querySelectorAll('button[type="button"]');
	if (buttons.length < 2) {
		return null;
	}
	return { list: list, selectBtn: buttons[0], clearBtn: buttons[1] };
}

function allChecked(boxes, want) {
	for (let i = 0; i < boxes.length; i++) {
		if (!!boxes[i].checked !== want) {
			return false;
		}
	}
	return boxes.length > 0;
}

const ex1 = [{
	id: "mgmt",
	enabled: true,
	priority: 500,
	action: "allow",
	hosts: ["10.0.0.1"],
}];

/* new: onclick real selecionar/limpar — todas as caixas */
const htmlNew = renderCand({ exceptions: ex1, get: { new: "1" } });
const domNew = openDom(htmlNew);
const winNew = domNew.window;
injectScript(winNew);
const countersNew = installNetworkCounters(winNew);
const newBtns = ifaceButtons(winNew.document, "new_exc_ifaces_list");
check(!!newBtns, "new: botoes interface presentes");
if (newBtns) {
	check(newBtns.selectBtn.getAttribute("type") === "button", "new: botao selecionar type=button");
	check(newBtns.clearBtn.getAttribute("type") === "button", "new: botao limpar type=button");
	const boxesNew = newBtns.list.querySelectorAll('input[type="checkbox"]');
	check(boxesNew.length >= 2, "new: checkboxes interfaces");
	invokeAttr(winNew, newBtns.selectBtn, "onclick", {});
	check(allChecked(boxesNew, true), "new: onclick selecionar marca todas");
	invokeAttr(winNew, newBtns.clearBtn, "onclick", {});
	check(allChecked(boxesNew, false), "new: onclick limpar desmarca todas");
	check(countersNew.fetch === 0 && countersNew.submit === 0 && countersNew.requestSubmit === 0,
		"new: zero fetch/submit/requestSubmit");
}

/* edit: onclick real — todas as caixas */
const htmlEdit = renderCand({ exceptions: ex1, get: { edit: "0" } });
const domEdit = openDom(htmlEdit);
const winEdit = domEdit.window;
injectScript(winEdit);
const countersEdit = installNetworkCounters(winEdit);
const editBtns = ifaceButtons(winEdit.document, "edit_exc_ifaces_list");
check(!!editBtns, "edit: botoes interface presentes");
if (editBtns) {
	const boxesEdit = editBtns.list.querySelectorAll('input[type="checkbox"]');
	invokeAttr(winEdit, editBtns.selectBtn, "onclick", {});
	check(allChecked(boxesEdit, true), "edit: onclick selecionar marca todas");
	invokeAttr(winEdit, editBtns.clearBtn, "onclick", {});
	check(allChecked(boxesEdit, false), "edit: onclick limpar desmarca todas");
	check(countersEdit.fetch === 0 && countersEdit.submit === 0 && countersEdit.requestSubmit === 0,
		"edit: zero fetch/submit/requestSubmit");
}

/* delete: confirm true/false via onsubmit real — texto original, 1 chamada */
const ex2 = [
	{ id: "a", enabled: true, priority: 500, action: "allow", hosts: ["10.0.0.1"] },
	{ id: "b", enabled: false, priority: 100, action: "block", hosts: ["10.0.0.2"] },
];
const htmlList = renderCand({ exceptions: ex2 });
const domList = openDom(htmlList);
const winList = domList.window;
const countersDel = installNetworkCounters(winList);
const doc = winList.document;
const delForm = Array.from(doc.querySelectorAll('form[action*="layer7_exceptions.php#l7-exceptions"]'))
	.find(function (f) {
		return f.querySelector('[name="delete_exception"]');
	});
check(!!delForm, "delete: form encontrado");
if (delForm) {
	const onsubmit = delForm.getAttribute("onsubmit");
	check(!!onsubmit && onsubmit.indexOf("confirm") >= 0, "delete: onsubmit com confirm");
	const msgMatch = onsubmit.match(/confirm\((.+)\)/);
	check(!!msgMatch, "delete: mensagem confirm extraivel");
	let expectedMsg = "";
	if (msgMatch) {
		try {
			expectedMsg = JSON.parse(msgMatch[1].replace(/&quot;/g, '"'));
		} catch (e) {
			expectedMsg = msgMatch[1].replace(/^['"]|['"]$/g, "");
		}
	}
	let calls = 0;
	let lastMsg = null;
	winList.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return false;
	};
	const blocked = invokeAttr(winList, delForm, "onsubmit", { preventDefault: function () {} }).ret === false;
	check(calls === 1, "delete: confirm chamado exactamente 1 vez (false)");
	check(blocked, "delete: confirm false bloqueia onsubmit");
	check(lastMsg === expectedMsg, "delete: texto confirm original");
	calls = 0;
	lastMsg = null;
	winList.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return true;
	};
	const allowed = invokeAttr(winList, delForm, "onsubmit", { preventDefault: function () {} }).ret !== false;
	check(calls === 1, "delete: confirm chamado exactamente 1 vez (true)");
	check(allowed, "delete: confirm true permite onsubmit");
	check(lastMsg === expectedMsg, "delete: texto confirm original (true)");
	check(countersDel.submit === 0 && countersDel.requestSubmit === 0,
		"delete: sem submit real no harness");
}

/* VIP JS intacto na lista (nao executar como aprovado — só presenca) */
check(htmlList.includes("function l7filterIface"), "lista: JS VIP presente (fora de escopo exec)");

if (fail) {
	console.error("SOME EXCEPTIONS JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS JS TESTS PASSED");
process.exit(0);
