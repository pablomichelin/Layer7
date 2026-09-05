#!/usr/bin/env node
/**
 * V12 Identity — FormData baseline/candidato (3 submitters + combinações checkboxes).
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-identity-view/render-parity.php");
const baseOpts = {
	unlocked: true,
	identity: {
		enabled: true,
		ldap: {
			enabled: true,
			server: "dc01.lab",
			port: 636,
			use_tls: true,
			bind_dn: "CN=svc,DC=lab",
			base_dn: "DC=lab",
			user_filter: "(user)",
			group_filter: "(group)",
			group_depth: 5,
			max_members: 4096,
		},
		radius: {
			enabled: true,
			listen_port: 1813,
			bind_address: "192.168.1.1",
			nas_acl: ["10.0.0.1"],
		},
		dc_agent: {
			enabled: true,
			listen_port: 8743,
			bind_address: "192.168.1.1",
			skew_sec: 300,
			dc_acl: ["10.0.0.10"],
		},
	},
	nas_acl_text: "10.0.0.1",
	dc_acl_text: "10.0.0.10",
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
		url: "http://127.0.0.1/packages/layer7/layer7_identity.php",
		runScripts: "outside-only",
	});
}

function sortEntries(entries) {
	return entries.slice().sort(function (a, b) {
		if (a[0] !== b[0]) return a[0] < b[0] ? -1 : 1;
		return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0;
	});
}

function findForm(doc) {
	return doc.querySelector('form[action="layer7_identity.php"]') ||
		doc.querySelector("#l7-identity-form");
}

function setField(form, name, value) {
	const el = form.querySelector('[name="' + name + '"]');
	if (!el) return false;
	if (el.type === "checkbox") {
		el.checked = !!value;
	} else {
		el.value = value == null ? "" : String(value);
	}
	return true;
}

function collectFormData(html, scenario) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc);
	if (!form) return { error: "form ausente" };

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
check(pair.baseline.indexOf("save_identity") !== -1, "form: baseline");
check(pair.candidate.indexOf("save_identity") !== -1, "form: candidato");

assertParity("save-minimo", pair.baseline, pair.candidate, {
	submitter: "save_identity",
	checks: {},
	fields: {},
});

assertParity("save-flags", pair.baseline, pair.candidate, {
	submitter: "save_identity",
	checks: {
		identity_enabled: true,
		ldap_enabled: true,
		ldap_use_tls: true,
		radius_enabled: true,
		dc_enabled: true,
		ldap_clear_password: true,
		radius_clear_secret: true,
		dc_clear_secret: true,
	},
	fields: {
		ldap_server: "dc01.lab",
		ldap_port: "636",
		ldap_bind_dn: "CN=svc",
		ldap_bind_password: "synth-not-real",
		ldap_base_dn: "DC=lab",
		ldap_user_filter: "(u)",
		ldap_group_filter: "(g)",
		radius_listen_port: "1813",
		radius_bind_address: "192.168.1.1",
		radius_nas_acl: "10.0.0.1",
		radius_secret: "synth-not-real",
		dc_listen_port: "8743",
		dc_bind_address: "192.168.1.1",
		dc_acl: "10.0.0.10",
		dc_skew_sec: "300",
		dc_secret: "synth-not-real",
		ldap_group_depth: "5",
		ldap_max_members: "4096",
	},
});

assertParity("test-ldap", pair.baseline, pair.candidate, {
	submitter: "test_ldap",
	checks: { ldap_enabled: true },
	fields: { ldap_server: "dc01.lab" },
});

assertParity("dc-generate-token", pair.baseline, pair.candidate, {
	submitter: "dc_generate_token",
	checks: { dc_enabled: true },
	fields: {},
});

const locked = renderPair({ unlocked: false });
check(locked.baseline.indexOf('name="save_identity"') === -1, "locked: baseline sem form");
check(locked.candidate.indexOf('name="save_identity"') === -1, "locked: candidato sem form");

if (fail) process.exit(1);
console.log("ALL IDENTITY PAYLOAD DOM TESTS PASSED");
process.exit(0);
