#!/usr/bin/env node
/**
 * V14 Blacklists — FormData baseline/candidato (7 submitters; value vazio quando ausente).
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-blacklists-view/render-parity.php");
const baseOpts = {
	with_rules: true,
	with_custom: true,
	rules: [{
		name: "Lab",
		enabled: true,
		force_dns: true,
		categories: ["adult", "games"],
		src_cidrs: ["192.168.10.0/24"],
		except_ips: ["192.168.10.1"],
	}],
	bl_config: {
		enabled: true,
		auto_update: true,
		update_interval_hours: 12,
		max_entries: 4000000,
		mem_percent: 20,
		whitelist: ["safe.example"],
		rules: [],
		category_custom: { custom_local: ["a.example", "b.example"] },
	},
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
		url: "http://127.0.0.1/packages/layer7/layer7_blacklists.php",
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
	if (nodes.length > 1 || nodes[0].type === "checkbox") {
		nodes.forEach(function (el) {
			if (el.type === "checkbox") {
				if (Array.isArray(value)) {
					el.checked = value.indexOf(el.value) !== -1;
				} else {
					el.checked = !!value;
				}
			}
		});
		return true;
	}
	const el = nodes[0];
	if (el.type === "checkbox") {
		el.checked = !!value;
	} else {
		el.value = value == null ? "" : String(value);
	}
	return true;
}

function appendSubmitter(fd, submit) {
	const name = submit.getAttribute("name");
	const val = submit.hasAttribute("value") ? submit.getAttribute("value") : "";
	fd.append(name, val);
}

function collectFormData(html, scenario) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc, scenario.submitter);
	if (!form) return { error: "form ausente: " + scenario.submitter };

	const s = scenario || {};
	Object.keys(s.checks || {}).forEach(function (name) {
		setField(form, name, s.checks[name]);
	});
	Object.keys(s.fields || {}).forEach(function (name) {
		setField(form, name, s.fields[name]);
	});

	const submit = form.querySelector('[name="' + s.submitter + '"]');
	if (!submit) return { error: "submitter ausente: " + s.submitter };
	const fd = new dom.window.FormData(form);
	appendSubmitter(fd, submit);
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

const pair = renderPair(Object.assign({}, baseOpts, { add_rule: true }));
check(pair.baseline.indexOf("save_rule") !== -1, "form: baseline save_rule");
check(pair.candidate.indexOf("save_rule") !== -1, "form: candidato save_rule");

assertParity("do-download", pair.baseline, pair.candidate, {
	submitter: "do_download",
});

const delPair = renderPair(baseOpts);
assertParity("delete-rule", delPair.baseline, delPair.candidate, {
	submitter: "delete_rule",
	fields: { rule_index: "0" },
});

assertParity("save-rule-min", pair.baseline, pair.candidate, {
	submitter: "save_rule",
	fields: { rule_name: "Nova" },
});

assertParity("save-rule-full", pair.baseline, pair.candidate, {
	submitter: "save_rule",
	checks: {
		rule_enabled: true,
		rule_force_dns: true,
		"rule_cats[]": ["adult", "games"],
	},
	fields: {
		rule_name: "Completa",
		rule_cidrs: "192.168.10.0/24",
		rule_except: "192.168.10.1",
	},
});

const catPair = renderPair(baseOpts);
assertParity("save-cat-sites", catPair.baseline, catPair.candidate, {
	submitter: "save_cat_sites",
	fields: {
		cat_id: "erp_apps",
		cat_sites: "app1.example\napp2.example",
	},
});

assertParity("delete-cat-sites", catPair.baseline, catPair.candidate, {
	submitter: "delete_cat_sites",
	fields: { cat_id: "custom_local" },
});

assertParity("save-whitelist", catPair.baseline, catPair.candidate, {
	submitter: "save_whitelist",
	fields: { whitelist: "never.block.example\nsafe.example" },
});

assertParity("save-settings", catPair.baseline, catPair.candidate, {
	submitter: "save_settings",
	checks: { auto_update: true },
	fields: {
		update_interval_hours: "12",
		max_entries: "4000000",
		mem_percent: "20",
	},
});

const emptyVal = collectFormData(pair.candidate, { submitter: "do_download" });
const dlEntry = (emptyVal.entries || []).find(function (e) { return e[0] === "do_download"; });
check(dlEntry && dlEntry[1] === "", "payload: do_download valor vazio");

if (fail) process.exit(1);
console.log("ALL BLACKLISTS PAYLOAD DOM TESTS PASSED");
process.exit(0);
