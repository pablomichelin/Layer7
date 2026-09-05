#!/usr/bin/env node
/**
 * V15 Settings — FormData baseline/candidato por form (general/reports/licença/backup/update).
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-settings-view/render-parity.php");
const baseOpts = {
	enabled: true,
	mode: "enforce",
	block_dot_doq: true,
	sni_inspection: true,
	syslog_remote: true,
	syslog_remote_host: "10.0.0.50",
	pfsense_ifaces: [
		{ ifid: "lan", real: "em0", descr: "LAN", checked: true },
		{ ifid: "wan", real: "em1", descr: "WAN", checked: false },
	],
	block_quic_ifaces: ["em0"],
	reports_cfg: {
		enabled: true,
		retention_days: 30,
		collect_interval: 10,
		event_log_enabled: true,
		event_retention_days: 7,
		event_max_mb: 150,
		event_interfaces: ["em0"],
	},
	bp_cfg: {
		enabled: true,
		portal_ip: "192.168.1.1",
		title: "Bloqueio",
		message: "Msg",
		contact: "admin@example.test",
		show_host: true,
		show_policy: true,
		sinkhole_blacklists: true,
		blacklist_domain_limit: 256,
		force_dns: true,
	},
	update_info: {
		current: "1.9.78",
		latest: "1.9.79",
		tag: "v1.9.79",
		pkg_url: "https://github.com/pablomichelin/Layer7/releases/download/v1.9.79/pfSense-pkg-layer7-1.9.79.pkg",
		name: "v1.9.79",
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
		url: "http://127.0.0.1/packages/layer7/layer7_settings.php",
		runScripts: "outside-only",
	});
}

function jsdomRoot() {
	if (process.env.LAYER7_JSDOM) {
		const p = process.env.LAYER7_JSDOM;
		if (p.endsWith(".js")) return path.join(p, "..", "..", "..");
		return p;
	}
	return path.dirname(require.resolve("jsdom/package.json"));
}

function setControlledFileList(win, input, files) {
	const root = jsdomRoot();
	const FileListMod = require(path.join(root, "lib/jsdom/living/generated/FileList.js"));
	const utils = require(path.join(root, "lib/jsdom/living/generated/utils.js"));
	const impl = FileListMod.createImpl(win, []);
	files.forEach(function (file) {
		impl.push(utils.implForWrapper(file));
	});
	input.files = utils.wrapperForImpl(impl);
}

function sortEntries(entries) {
	return entries.slice().sort(function (a, b) {
		if (a[0] !== b[0]) return a[0] < b[0] ? -1 : 1;
		const va = a[1];
		const vb = b[1];
		if (va && typeof va === "object" && va.file) {
			const sb = vb && vb.file ? vb.name + vb.size : "";
			return (va.name + va.size).localeCompare(sb);
		}
		return String(va) < String(vb) ? -1 : String(va) > String(vb) ? 1 : 0;
	});
}

function findForm(doc, matcher) {
	const forms = Array.from(doc.querySelectorAll("form"));
	return forms.find(matcher) || null;
}

function collectGeneral(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc, function (f) {
		return f.id === "l7-settings-general-form" || f.querySelector('[name="save_scope"][value="general"]');
	});
	if (!form) return { error: "form general ausente" };
	const submit = form.querySelector('[name="save"][value="1"]');
	if (!submit) return { error: "submitter save ausente" };
	const fd = new dom.window.FormData(form, submit);
	return { entries: sortEntries(Array.from(fd.entries())) };
}

function collectReports(html, opts) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc, function (f) {
		return f.id === "l7-settings-reports-form" || f.querySelector('[name="save_scope"][value="reports"]');
	});
	if (!form) return { error: "form reports ausente" };
	if (opts && opts.checkReports) {
		const re = form.querySelector('[name="reports_enabled"]');
		const rev = form.querySelector('[name="reports_event_log_enabled"]');
		if (re) re.checked = true;
		if (rev) rev.checked = true;
	}
	const fd = new dom.window.FormData(form);
	return { entries: sortEntries(Array.from(fd.entries())) };
}

function collectBySubmitter(html, submitter) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findForm(doc, function (f) {
		return !!f.querySelector('[name="' + submitter + '"]');
	});
	if (!form) return { error: "form ausente: " + submitter };
	const submit = form.querySelector('[name="' + submitter + '"]');
	const fd = new dom.window.FormData(form, submit);
	return { entries: sortEntries(Array.from(fd.entries())) };
}

function collectImport(html) {
	const dom = openDom(html);
	const win = dom.window;
	const doc = dom.window.document;
	const form = findForm(doc, function (f) {
		return !!f.querySelector('[name="import_config"]');
	});
	if (!form) return { error: "form import ausente" };
	const input = form.querySelector('[name="import_file"]');
	const file = new win.File(
		['{"layer7_backup":true,"layer7":{}}'],
		"layer7-backup-synth.json",
		{ type: "application/json" }
	);
	setControlledFileList(win, input, [file]);
	const submit = form.querySelector('[name="import_config"]');
	const fd = new win.FormData(form, submit);
	const entries = [];
	for (const pair of fd.entries()) {
		const v = pair[1];
		if (v instanceof win.File) {
			entries.push([pair[0], { file: true, name: v.name, type: v.type, size: v.size }]);
		} else {
			entries.push([pair[0], String(v)]);
		}
	}
	return { entries: sortEntries(entries) };
}

function assertParity(label, base, cand) {
	check(!base.error && !cand.error, label + ": forms (" + (base.error || cand.error || "ok") + ")");
	if (base.error || cand.error) return;
	check(JSON.stringify(base.entries) === JSON.stringify(cand.entries), label + ": paridade FormData");
}

const pair = renderPair(baseOpts);
check(pair.baseline.indexOf("l7-settings-general-form") !== -1 || pair.baseline.indexOf('save_scope" value="general"') !== -1,
	"render: baseline general");
check(pair.candidate.indexOf("l7-settings-reports-form") !== -1 || pair.candidate.indexOf('save_scope" value="reports"') !== -1,
	"render: candidato reports");

assertParity("general-save", collectGeneral(pair.baseline), collectGeneral(pair.candidate));
assertParity("reports-save", collectReports(pair.baseline, { checkReports: true }), collectReports(pair.candidate, { checkReports: true }));

const gen = collectGeneral(pair.candidate);
const rep = collectReports(pair.candidate, { checkReports: true });
check(gen.entries.some(function (e) { return e[0] === "enabled" && e[1] === "1"; }), "general: enabled=1");
check(gen.entries.some(function (e) { return e[0] === "save_scope" && e[1] === "general"; }), "general: save_scope");
check(gen.entries.some(function (e) { return e[0] === "save" && e[1] === "1"; }), "general: save=1 submitter");
check(rep.entries.some(function (e) { return e[0] === "reports_enabled" && e[1] === "on"; }), "reports: reports_enabled=on");
check(rep.entries.some(function (e) { return e[0] === "reports_event_log_enabled" && e[1] === "on"; }), "reports: event_log=on");
check(rep.entries.some(function (e) { return e[0] === "save" && e[1] === "1"; }), "reports: hidden save=1");
check(!rep.entries.some(function (e) { return e[0] === "reports_enabled" && e[1] === "1"; }), "reports: nao reports_enabled=1");

assertParity("export-config", collectBySubmitter(pair.baseline, "export_config"), collectBySubmitter(pair.candidate, "export_config"));

const licPair = renderPair(Object.assign({}, baseOpts, {
	license_status: {
		valid: false,
		expired: false,
		grace: false,
		dev_mode: false,
		clock_suspect: false,
		hardware_id: "HW-REG",
		customer: "",
		expiry: "",
		days_left: 0,
		error: "sem licenca",
	},
}));
assertParity("register-license",
	collectBySubmitter(licPair.baseline, "register_license"),
	collectBySubmitter(licPair.candidate, "register_license"));

assertParity("check-update", collectBySubmitter(pair.baseline, "check_update"), collectBySubmitter(pair.candidate, "check_update"));
assertParity("do-update", collectBySubmitter(pair.baseline, "do_update"), collectBySubmitter(pair.candidate, "do_update"));
assertParity("import-config-file", collectImport(pair.baseline), collectImport(pair.candidate));

if (fail) {
	console.error("SOME SETTINGS PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL SETTINGS PAYLOAD TESTS PASSED");
process.exit(0);
