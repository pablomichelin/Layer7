#!/usr/bin/env node
/**
 * V8 Diagnósticos — jsdom: confirm remove_anti_doh via submit real (handler onsubmit instalado).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_diagnostics_js.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-diagnostics-view/render-parity.php");
const CONFIRM_KEY = "Remover overrides anti-DoH do Unbound?";
const CONFIRM_ADV = '\' say "no" && alert(1)';

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
	let parsed;
	try {
		parsed = JSON.parse(raw);
	} catch (e) {
		console.error("FAIL: JSON invalido de render-parity.php");
		console.error(raw.slice(0, 500));
		process.exit(1);
	}
	return parsed.candidate;
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_diagnostics.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
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

function installNetworkCounters(win) {
	const counters = { submit: 0, requestSubmit: 0 };
	const formProto = win.HTMLFormElement.prototype;
	const origSubmit = formProto.submit;
	const origRequestSubmit = formProto.requestSubmit;
	formProto.submit = function () { counters.submit++; return origSubmit.call(this); };
	if (typeof origRequestSubmit === "function") {
		formProto.requestSubmit = function () {
			counters.requestSubmit++;
			return origRequestSubmit.apply(this, arguments);
		};
	}
	return counters;
}

function findRemoveForm(doc) {
	return Array.from(doc.querySelectorAll("form")).find(function (f) {
		const action = f.getAttribute("action") || "";
		return action.indexOf("#l7-dns") >= 0 && f.querySelector('[name="remove_anti_doh"]');
	}) || null;
}

function parseConfirmMessage(onsubmit) {
	const msgMatch = onsubmit.match(/confirm\((.+)\)/);
	if (!msgMatch) {
		return null;
	}
	try {
		return JSON.parse(msgMatch[1]);
	} catch (e) {
		return null;
	}
}

function testConfirmScenario(label, html, expectedMsg, msgLabel) {
	const dom = openDom(html);
	const win = dom.window;
	const counters = installNetworkCounters(win);
	const form = findRemoveForm(win.document);
	check(!!form, label + ": form remove encontrado");
	if (!form) {
		return;
	}

	const onsubmit = form.getAttribute("onsubmit");
	check(!!onsubmit && onsubmit.indexOf("confirm") >= 0, label + ": atributo onsubmit decodificado");

	const parsedMsg = parseConfirmMessage(onsubmit);
	check(parsedMsg === expectedMsg, label + ": mensagem confirm do atributo (" +
		JSON.stringify(parsedMsg) + ")");

	check(installSubmitHandler(win, form), label + ": handler onsubmit instalado no DOM");

	let confirmCalls = 0;
	let lastMsg = null;
	win.confirm = function (msg) {
		confirmCalls++;
		lastMsg = msg;
		return false;
	};
	const evFalse = new win.Event("submit", { bubbles: true, cancelable: true });
	const retFalse = form.dispatchEvent(evFalse);
	check(confirmCalls === 1, label + ": confirm chamado 1x (false)");
	check(!retFalse, label + ": submit cancelado quando confirm false");
	check(evFalse.defaultPrevented, label + ": defaultPrevented quando confirm false");
	check(lastMsg === expectedMsg, label + ": " + msgLabel + " (false)");

	confirmCalls = 0;
	lastMsg = null;
	win.confirm = function (msg) {
		confirmCalls++;
		lastMsg = msg;
		return true;
	};
	const evTrue = new win.Event("submit", { bubbles: true, cancelable: true });
	const retTrue = form.dispatchEvent(evTrue);
	check(confirmCalls === 1, label + ": confirm chamado 1x (true)");
	check(retTrue, label + ": submit permitido quando confirm true");
	check(!evTrue.defaultPrevented, label + ": sem defaultPrevented quando confirm true");
	check(lastMsg === expectedMsg, label + ": " + msgLabel + " (true)");
	check(counters.submit === 0 && counters.requestSubmit === 0,
		label + ": sem form.submit() nativo no harness");
}

console.log("Runtime: jsdom + render PHP view real (unbound_anti_doh=1)");

const htmlPlain = renderCand({ unbound_anti_doh: true });
testConfirmScenario("plain", htmlPlain, CONFIRM_KEY, "texto confirm");

const htmlAdv = renderCand({
	unbound_anti_doh: true,
	l7_t_fixture: { [CONFIRM_KEY]: CONFIRM_ADV },
});
testConfirmScenario("adversarial", htmlAdv, CONFIRM_ADV, "texto fixture adversarial");

check(htmlPlain.indexOf("onsubmit='return confirm(") !== -1,
	"render: delimitador aspas simples no HTML fonte candidato");
check(htmlPlain.indexOf('onclick="return confirm(') === -1,
	"render: sem onclick confirm no candidato");

if (fail) {
	process.exit(1);
}
console.log("ALL DIAGNOSTICS JS TESTS PASSED");
process.exit(0);
