#!/usr/bin/env node
/**
 * V8 Diagnósticos — FormData baseline/candidato (fragmentos form reais renderizados).
 * Complementa test_diagnostics_payload.php (gate estático de strings).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_diagnostics_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-diagnostics-view/render-parity.php");

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
		url: "http://127.0.0.1/packages/layer7/layer7_diagnostics.php",
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
		const shown = v.length > 48 ? v.slice(0, 48) + "…" : v;
		return [pair[0], shown];
	}));
}

function formPayload(html, actionSuffix, submitName, fill) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const forms = Array.from(doc.querySelectorAll("form")).filter(function (f) {
		const action = f.getAttribute("action") || "";
		return action.indexOf(actionSuffix) >= 0;
	});
	if (forms.length !== 1) {
		return { error: "form ambigua/ausente action*=" + actionSuffix + " (" + forms.length + ")" };
	}
	const form = forms[0];
	const submit = form.querySelector(
		'button[name="' + submitName + '"], input[name="' + submitName + '"]'
	);
	if (!submit) {
		return { error: "submitter ausente no form: " + submitName };
	}
	if (fill && typeof fill === "function") {
		fill(doc, form);
	}
	let fd;
	try {
		fd = new dom.window.FormData(form, submit);
	} catch (e) {
		return { error: "FormData falhou: " + (e && e.message ? e.message : e) };
	}
	const entries = Array.from(fd.entries());
	const summaryInForm = form.querySelector('[name="error_summary"]');
	if (summaryInForm && entries.some(function (p) { return p[0] === "error_summary"; })) {
		const val = fd.get("error_summary");
		if (val !== summaryInForm.value) {
			return { error: "error_summary fora do mesmo form" };
		}
	}
	return {
		method: (form.getAttribute("method") || "get").toLowerCase(),
		action: form.getAttribute("action") || "",
		submitValue: fd.get(submitName),
		submitAttr: submit.getAttribute("value"),
		entries: entries,
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
		base.entries.filter(function (p) { return p[0] === submitName; }).length === 1,
		label + ": um par submitter"
	);
}

const ADVERSARIAL = '\'"<script>alert(1)</script> & foo';

console.log("Runtime: FormData jsdom; view PHP real baseline/candidato (fixtures)");

/* 1 repair_pf_tables — PF em falta */
const repairPair = renderPair({ pf_any_missing: true, pf_repair_result: null });
const repairAction = "#l7-pf";
const baseRepair = formPayload(repairPair.baseline, repairAction, "repair_pf_tables");
const candRepair = formPayload(repairPair.candidate, repairAction, "repair_pf_tables");
assertParity("repair_pf_tables", baseRepair, candRepair, "repair_pf_tables");
check(
	!baseRepair.error && baseRepair.entries.length === 1,
	"repair_pf_tables: payload isolado sem campos extra"
);

/* 2 remove_anti_doh — anti-DoH activo */
const removePair = renderPair({ unbound_anti_doh: true });
const dnsAction = "#l7-dns";
const baseRemove = formPayload(removePair.baseline, dnsAction, "remove_anti_doh");
const candRemove = formPayload(removePair.candidate, dnsAction, "remove_anti_doh");
assertParity("remove_anti_doh", baseRemove, candRemove, "remove_anti_doh");
check(
	!candRemove.error && candRemove.entries.length === 1,
	"remove_anti_doh: sem error_summary no form DNS"
);

/* 3 configure_anti_doh — anti-DoH inactivo */
const cfgPair = renderPair({ unbound_anti_doh: false });
const baseCfg = formPayload(cfgPair.baseline, dnsAction, "configure_anti_doh");
const candCfg = formPayload(cfgPair.candidate, dnsAction, "configure_anti_doh");
assertParity("configure_anti_doh", baseCfg, candCfg, "configure_anti_doh");

/* 4 send_sigusr1 — daemon up */
const actionsPair = renderPair({ status_ok: true, pid: 4242 });
const actionsAction = "#l7-actions";
const baseSig = formPayload(actionsPair.baseline, actionsAction, "send_sigusr1");
const candSig = formPayload(actionsPair.candidate, actionsAction, "send_sigusr1");
assertParity("send_sigusr1", baseSig, candSig, "send_sigusr1");

/* 5 send_sighup — mesmo form, submitter distinto */
const baseHup = formPayload(actionsPair.baseline, actionsAction, "send_sighup");
const candHup = formPayload(actionsPair.candidate, actionsAction, "send_sighup");
assertParity("send_sighup", baseHup, candHup, "send_sighup");
check(
	!baseSig.error && !baseHup.error && baseSig.action === baseHup.action,
	"send_sigusr1/sighup: mesmo form action"
);

/* 6 report_error — summary vazio */
const reportEmptyPair = renderPair({ error_report_summary: "" });
const reportAction = "#l7-report-error";
const baseReportEmpty = formPayload(
	reportEmptyPair.baseline,
	reportAction,
	"report_error",
	function (_doc, form) {
		const ta = form.querySelector('[name="error_summary"]');
		if (ta) ta.value = "";
	}
);
const candReportEmpty = formPayload(
	reportEmptyPair.candidate,
	reportAction,
	"report_error",
	function (_doc, form) {
		const ta = form.querySelector('[name="error_summary"]');
		if (ta) ta.value = "";
	}
);
assertParity("report_error-empty", baseReportEmpty, candReportEmpty, "report_error");
check(
	!candReportEmpty.error &&
		candReportEmpty.entries.some(function (p) { return p[0] === "error_summary"; }),
	"report_error: error_summary no mesmo form"
);
check(
	!candReportEmpty.error &&
		candReportEmpty.entries.filter(function (p) { return p[0] === "report_error"; }).length === 1,
	"report_error: submitter unico no payload"
);

/* 7 copy_error_report — summary adversarial */
const reportAdvPair = renderPair({ error_report_summary: ADVERSARIAL });
const baseCopyAdv = formPayload(
	reportAdvPair.baseline,
	reportAction,
	"copy_error_report",
	function (_doc, form) {
		const ta = form.querySelector('[name="error_summary"]');
		if (ta) ta.value = ADVERSARIAL;
	}
);
const candCopyAdv = formPayload(
	reportAdvPair.candidate,
	reportAction,
	"copy_error_report",
	function (_doc, form) {
		const ta = form.querySelector('[name="error_summary"]');
		if (ta) ta.value = ADVERSARIAL;
	}
);
assertParity("copy_error_report-adversarial", baseCopyAdv, candCopyAdv, "copy_error_report");
check(
	!candCopyAdv.error &&
		candCopyAdv.entries.some(function (p) {
			return p[0] === "error_summary" && p[1] === ADVERSARIAL;
		}),
	"copy_error_report: summary adversarial preservado no FormData"
);
check(
	!candCopyAdv.error &&
		!candCopyAdv.entries.some(function (p) { return p[0] === "report_error"; }),
	"copy_error_report: nao inclui submitter report_error"
);

/* healthy: form report nao mistura com DNS */
const healthyPair = renderPair({ status_ok: true, pid: 1, unbound_anti_doh: false });
const healthyReport = formPayload(healthyPair.candidate, reportAction, "report_error");
const healthyDns = formPayload(healthyPair.candidate, dnsAction, "configure_anti_doh");
check(
	!healthyReport.error && !healthyDns.error &&
		healthyReport.action !== healthyDns.action,
	"healthy: forms report e DNS separados"
);

if (fail) {
	process.exit(1);
}
console.log("ALL DIAGNOSTICS PAYLOAD DOM TESTS PASSED");
process.exit(0);
