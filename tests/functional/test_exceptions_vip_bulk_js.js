#!/usr/bin/env node
/**
 * V6b2b — JS bulk/import: confirm via evento submit real no DOM (handler onsubmit instalado).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_bulk_js.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-exceptions-view/render-vip-bulk-parity.php");
const BULK_CONFIRM_KEY =
	"Guardar a lista em lote substitui as entradas directas da Lista VIP (grupos isentos sao limpos). Continuar?";
const BULK_CONFIRM_ADV = 'Guardar "lote" & grupos isentos <script> — continuar?';
const IMPORT_CONFIRM_KEY =
	"Importar substitui entradas directas da Lista VIP (grupos isentos sao limpos). Continuar?";
const IMPORT_CONFIRM_ADV = 'Importar "VIP" & grupos isentos <script> — continuar?';

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
	return JSON.parse(raw).candidate;
}

function openDom(html, pageUrl, extra) {
	return new JSDOM(html, Object.assign({
		url: pageUrl,
		runScripts: "outside-only",
	}, extra || {}));
}

function installSubmitHandler(win, form) {
	const code = form.getAttribute("onsubmit");
	if (!code) {
		return false;
	}
	form.removeAttribute("onsubmit");
	form.addEventListener("submit", function (ev) {
		const fn = win.eval("(function(event) { " + code + " })");
		const ret = fn.call(form, ev);
		if (ret === false) {
			ev.preventDefault();
			ev.stopPropagation();
		}
	});
	return true;
}

function testConfirm(html, submitName, expectedMsg, pageUrl) {
	const dom = openDom(html, pageUrl);
	const win = dom.window;
	const form = Array.from(win.document.querySelectorAll("form")).find(function (f) {
		return f.querySelector('[name="' + submitName + '"]');
	});
	check(!!form, submitName + ": form encontrado");
	if (!form) return;
	check(installSubmitHandler(win, form), submitName + ": handler onsubmit instalado no DOM");

	let confirmCalls = 0;
	let lastMsg = null;
	let submitEvents = 0;
	form.addEventListener("submit", function () {
		submitEvents++;
	});

	win.confirm = function (msg) {
		confirmCalls++;
		lastMsg = msg;
		return false;
	};
	const evFalse = new win.Event("submit", { bubbles: true, cancelable: true });
	const notCancelledFalse = form.dispatchEvent(evFalse);
	check(confirmCalls === 1, submitName + ": confirm 1x false");
	check(!notCancelledFalse, submitName + ": false bloqueia submit real");
	check(submitEvents === 1, submitName + ": um evento submit false");
	check(lastMsg === expectedMsg, submitName + ": texto fixture adversarial (false)");

	confirmCalls = 0;
	lastMsg = null;
	submitEvents = 0;
	win.confirm = function (msg) {
		confirmCalls++;
		lastMsg = msg;
		return true;
	};
	const evTrue = new win.Event("submit", { bubbles: true, cancelable: true });
	const allowedTrue = form.dispatchEvent(evTrue);
	check(confirmCalls === 1, submitName + ": confirm 1x true");
	check(allowedTrue, submitName + ": true permite submit real");
	check(submitEvents === 1, submitName + ": um evento submit true");
	check(lastMsg === expectedMsg, submitName + ": texto fixture adversarial (true)");
}

const vipData = {
	layer7: {
		exceptions: [{
			id: "vip-isentos",
			enabled: true,
			priority: 9000,
			action: "allow",
			managed_by: "profiles",
			hosts: ["10.0.0.1"],
		}],
	},
};

const htmlBulk = renderCand({
	data: vipData,
	vip_bulk_mode: true,
	l7_t_fixture: { [BULK_CONFIRM_KEY]: BULK_CONFIRM_ADV },
});
testConfirm(
	htmlBulk,
	"save_vip_bulk",
	BULK_CONFIRM_ADV,
	"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_bulk=1"
);

const htmlImport = renderCand({
	data: vipData,
	vip_import_mode: true,
	l7_t_fixture: { [IMPORT_CONFIRM_KEY]: IMPORT_CONFIRM_ADV },
});
testConfirm(
	htmlImport,
	"import_vip_list",
	IMPORT_CONFIRM_ADV,
	"http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_import=1"
);

if (fail) {
	console.error("SOME EXCEPTIONS VIP BULK JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP BULK JS TESTS PASSED");
process.exit(0);
