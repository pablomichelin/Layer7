#!/usr/bin/env node
/**
 * V13 MITM — FormData baseline/candidato (6 submitters + combinações checkboxes/radio).
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-mitm-view/render-parity.php");
const baseMitm = {
	enabled: true,
	quic_mode: "block",
	ca: {
		present: true,
		cn: "Layer7 MITM CA",
		subject: "CN=SYNTH-CA",
		fingerprint_sha256: "SYNTH-FP",
		not_after: "2099-12-31",
	},
	window: { max_minutes: 30, deadline_unix: 0 },
	intercept: {
		source_cidr: ["192.168.100.24/32"],
		dest_cidr: ["203.0.113.10"],
		block_sni: ["blocked.example"],
	},
	bypass: {
		sni: ["bank.example.pt"],
		cidr: ["10.0.0.0/8", "127.0.0.1/32", "::1/128"],
	},
};

const baseOpts = {
	unlocked: true,
	ca_ok: true,
	toggle_ok: true,
	runtime_ok: true,
	effective: true,
	mitm: baseMitm,
};

let fail = 0;
function check(cond, name) {
	if (cond) console.log("PASS: " + name);
	else { console.log("FAIL: " + name); fail = 1; }
}

function renderPair(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	return JSON.parse(raw);
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_mitm.php",
		runScripts: "outside-only",
	});
}

function sortEntries(entries) {
	return entries.slice().sort(function (a, b) {
		if (a[0] !== b[0]) return a[0] < b[0] ? -1 : 1;
		return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0;
	});
}

function findForm(doc, submitter) {
	const forms = Array.from(doc.querySelectorAll("form"));
	for (const form of forms) {
		if (form.querySelector('[name="' + submitter + '"]')) {
			return form;
		}
	}
	return null;
}

function setField(form, name, value) {
	const nodes = form.querySelectorAll('[name="' + name + '"]');
	if (!nodes.length) return false;
	const el = nodes[0];
	if (el.type === "checkbox") {
		el.checked = !!value;
		return true;
	}
	if (el.type === "radio") {
		nodes.forEach(function (r) {
			r.checked = (r.value === String(value));
		});
		return true;
	}
	el.value = value == null ? "" : String(value);
	return true;
}

function collectFormData(html, scenario) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc, scenario.submitter);
	if (!form) return { error: "form ausente: " + scenario.submitter };

	const s = scenario || {};
	const checks = s.checks || {};
	Object.keys(checks).forEach(function (name) {
		setField(form, name, checks[name]);
	});
	const fields = s.fields || {};
	Object.keys(fields).forEach(function (name) {
		setField(form, name, fields[name]);
	});

	const submit = form.querySelector('[name="' + s.submitter + '"]');
	if (!submit) return { error: "submitter ausente: " + s.submitter };
	const fd = new dom.window.FormData(form);
	fd.append(submit.getAttribute("name"), submit.getAttribute("value") || "1");
	return { entries: sortEntries(Array.from(fd.entries())) };
}

function assertParity(label, baseHtml, candHtml, scenario) {
	const base = collectFormData(baseHtml, scenario);
	const cand = collectFormData(candHtml, scenario);
	check(!base.error, label + ": baseline (" + (base.error || "ok") + ")");
	check(!cand.error, label + ": candidato (" + (cand.error || "ok") + ")");
	if (base.error || cand.error) return;
	check(JSON.stringify(base.entries) === JSON.stringify(cand.entries),
		label + ": paridade FormData");
}

const pair = renderPair(baseOpts);
check(pair.baseline.indexOf("mitm_save_bypass") !== -1, "form: baseline save");
check(pair.candidate.indexOf("mitm_save_bypass") !== -1, "form: candidato save");

assertParity("save-minimo", pair.baseline, pair.candidate, {
	submitter: "mitm_save_bypass",
	checks: {},
	fields: {},
});

assertParity("save-completo", pair.baseline, pair.candidate, {
	submitter: "mitm_save_bypass",
	checks: {
		mitm_enabled: true,
		mitm_duration_mode: "timed",
	},
	fields: {
		ca_cn: "Layer7 MITM CA",
		mitm_max_window: "45",
		intercept_source_cidr: "192.168.100.24/32",
		intercept_dest_cidr: "203.0.113.10",
		intercept_block_sni: "blocked.example",
		quic_mode: "block",
		bypass_sni: "bank.example.pt",
		bypass_cidr: "10.0.0.0/8",
	},
});

assertParity("save-until-off", pair.baseline, pair.candidate, {
	submitter: "mitm_save_bypass",
	checks: { mitm_duration_mode: "until_off" },
	fields: { mitm_max_window: "15" },
});

assertParity("break-glass", pair.baseline, pair.candidate, {
	submitter: "mitm_break_glass",
	checks: {},
	fields: {},
});

assertParity("ca-export", pair.baseline, pair.candidate, {
	submitter: "mitm_ca_export",
	checks: {},
	fields: {},
});

assertParity("ca-delete", pair.baseline, pair.candidate, {
	submitter: "mitm_ca_delete",
	checks: {},
	fields: {},
});

assertParity("ca-generate", pair.baseline, pair.candidate, {
	submitter: "mitm_ca_generate",
	checks: {},
	fields: { ca_cn: "SYNTH-CN-FIXTURE" },
});

assertParity("ca-import", pair.baseline, pair.candidate, {
	submitter: "mitm_ca_import",
	checks: {},
	fields: {
		ca_cert_pem: "-----BEGIN CERTIFICATE-----\nSYNTH-ONLY\n-----END CERTIFICATE-----",
		ca_key_pem: "-----BEGIN PRIVATE KEY-----\nSYNTH-ONLY-NOT-REAL\n-----END PRIVATE KEY-----",
	},
});

const locked = renderPair({ unlocked: false });
check(locked.baseline.indexOf('name="mitm_save_bypass"') === -1, "locked: baseline sem save");
check(locked.candidate.indexOf('name="mitm_save_bypass"') === -1, "locked: candidato sem save");

if (fail) process.exit(1);
console.log("ALL MITM PAYLOAD DOM TESTS PASSED");
process.exit(0);
