#!/usr/bin/env node
"use strict";
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-reports-view/render-parity.php");
let fail = 0;
function check(c, n) { if (c) console.log("PASS: " + n); else { console.log("FAIL: " + n); fail = 1; } }
function pair(opts) { return JSON.parse(runPhp(renderPhp, [JSON.stringify(opts)])); }
function dom(html) {
	return new JSDOM(html, { url: "http://127.0.0.1/packages/layer7/layer7_reports.php", runScripts: "outside-only" });
}
function filterForm(html) {
	const doc = dom(html).window.document;
	return doc.querySelector("#l7r-filters-form")
		|| doc.querySelector("form.l7r-filters")
		|| doc.querySelector('form input[name="range"][value="custom"]')?.closest("form");
}
function fdEntries(html, formSel, submitName) {
	const d = dom(html);
	const form = typeof formSel === "function" ? formSel(html) : d.window.document.querySelector(formSel);
	if (!form) return { error: "form ausente" };
	const fd = new d.window.FormData(form);
	if (submitName) {
		const sub = form.querySelector('[name="' + submitName + '"]');
		if (sub) fd.append(sub.getAttribute("name"), sub.getAttribute("value") || "1");
	}
	return { entries: Array.from(fd.entries()).sort((a, b) => a[0].localeCompare(b[0]) || String(a[1]).localeCompare(String(b[1]))) };
}
function eq(a, b) { return a.length === b.length && a.every((x, i) => x[0] === b[i][0] && x[1] === b[i][1]); }
function exportHrefs(html) {
	return Array.from(dom(html).window.document.querySelectorAll('#l7-tools a[href*="layer7_reports_export"]'))
		.map((a) => a.getAttribute("href"));
}

const opts = {
	range: "custom",
	custom_from: "2024-01-01",
	custom_to: "2024-01-02",
	filters: { src_ip: "10.0.0.5", host: "evil.com", action: "block", q: "app1" },
	from_ts: 1704067200,
	to_ts: 1704153599,
};
const p = pair(opts);
const fe = fdEntries(p.baseline, filterForm);
const fc = fdEntries(p.candidate, filterForm);
check(!fe.error && !fc.error, "filtro: forms encontrados");
const expected = [
	["action", "block"], ["from", "2024-01-01"], ["host", "evil.com"],
	["q", "app1"], ["range", "custom"], ["src_ip", "10.0.0.5"], ["to", "2024-01-02"],
];
check(eq(fe.entries, expected), "filtro: baseline FormData");
check(eq(fc.entries, expected), "filtro: candidato FormData");

const pc = pair({});
const clearB = fdEntries(pc.baseline, 'form[action*="layer7_reports.php#l7-tools"]', "clear_all_reports");
const clearC = fdEntries(pc.candidate, 'form[action*="layer7_reports.php#l7-tools"]', "clear_all_reports");
check(eq(clearB.entries, [["clear_all_reports", "1"]]), "clear: baseline FormData");
check(eq(clearC.entries, [["clear_all_reports", "1"]]), "clear: candidato FormData");

const eb = exportHrefs(p.baseline);
const ec = exportHrefs(p.candidate);
check(eb.length === 3 && ec.length === 3, "export: tres links");
check(JSON.stringify(eb) === JSON.stringify(ec), "export: paridade hrefs baseline/candidato");
check(eb[0].indexOf("format=html") >= 0 && eb[0].indexOf("src_ip=10.0.0.5") >= 0, "export: queryparams");

if (fail) process.exit(1);
console.log("ALL REPORTS PAYLOAD DOM TESTS PASSED");
