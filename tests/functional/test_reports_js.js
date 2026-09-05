#!/usr/bin/env node
"use strict";
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-reports-view/render-parity.php");
let fail = 0;
function check(c, n) { if (c) console.log("PASS: " + n); else { console.log("FAIL: " + n); fail = 1; } }

function renderCand(opts) {
	return JSON.parse(runPhp(renderPhp, [JSON.stringify(opts)])).candidate;
}

function installSubmitHandler(win, form) {
	const code = form.getAttribute("onsubmit");
	if (!code) return false;
	form.removeAttribute("onsubmit");
	form.addEventListener("submit", function (ev) {
		const fn = win.eval("(function(event) { " + code + " })");
		const ret = fn.call(form, ev);
		if (ret === false) { ev.preventDefault(); ev.stopPropagation(); }
	});
	return true;
}

const html = renderCand({ timeline: [] });
const dom = new JSDOM(html, {
	url: "http://127.0.0.1/packages/layer7/layer7_reports.php",
	runScripts: "outside-only",
});
const win = dom.window;
const doc = win.document;
const form = doc.querySelector('form[action*="layer7_reports.php#l7-tools"]');
check(!!form, "clear: form encontrado");
const onsubmit = form ? form.getAttribute("onsubmit") : "";
check(!!onsubmit && onsubmit.indexOf("confirm") >= 0, "clear: onsubmit decodificado");
check(installSubmitHandler(win, form), "clear: handler instalado");
let calls = 0;
win.confirm = function () { calls++; return false; };
const evF = new win.Event("submit", { bubbles: true, cancelable: true });
const retF = form.dispatchEvent(evF);
check(calls === 1, "clear: confirm 1x false");
check(!retF && evF.defaultPrevented, "clear: submit bloqueado");
calls = 0;
win.confirm = function () { calls++; return true; };
const evT = new win.Event("submit", { bubbles: true, cancelable: true });
const retT = form.dispatchEvent(evT);
check(calls === 1, "clear: confirm 1x true");
check(retT && !evT.defaultPrevented, "clear: submit permitido");

const htmlChart = renderCand({ timeline: [] });
const dom2 = new JSDOM(htmlChart, {
	url: "http://127.0.0.1/packages/layer7/layer7_reports.php",
	runScripts: "dangerously",
	pretendToBeVisual: false,
});
const d2 = dom2.window.document;
const canvas = d2.getElementById("timelineChart");
const empty = d2.getElementById("l7r-chart-empty");
check(canvas && empty, "chart: canvas e fallback");
check(!empty.classList.contains("hidden"), "chart: fallback visivel sem dados");
check(canvas.classList.contains("hidden"), "chart: canvas oculto sem dados");

if (fail) process.exit(1);
console.log("ALL REPORTS JS TESTS PASSED");
