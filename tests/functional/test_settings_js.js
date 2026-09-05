#!/usr/bin/env node
/**
 * V15 Settings — DOM update/retention + confirm revoke/import via click real.
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-settings-view/render-parity.php");
const REVOKE_MSG = "Deseja revogar a licenca activa?";
const IMPORT_MSG = "Substituir a configuracao actual? Esta accao nao pode ser desfeita.";

let fail = 0;
function check(cond, name) {
	if (cond) console.log("PASS: " + name);
	else { console.log("FAIL: " + name); fail = 1; }
}

function renderCand(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	return JSON.parse(raw).candidate;
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_settings.php",
		runScripts: "outside-only",
	});
}

function parseConfirmMessage(attr) {
	if (!attr) return null;
	const msgMatch = attr.match(/confirm\((.+)\)/);
	if (!msgMatch) return null;
	try {
		return JSON.parse(msgMatch[1]);
	} catch (e) {
		return null;
	}
}

function installClickHandler(win, btn) {
	const code = btn.getAttribute("onclick");
	if (!code) return false;
	btn.removeAttribute("onclick");
	btn.addEventListener("click", function (ev) {
		const fn = win.eval("(function(event) { " + code + " })");
		const ret = fn.call(btn, ev);
		if (ret === false) {
			ev.preventDefault();
			ev.stopPropagation();
		}
	});
	return true;
}

function testConfirmClick(label, html, buttonName, expectedMsg) {
	const dom = openDom(html);
	const win = dom.window;
	const btn = win.document.querySelector('[name="' + buttonName + '"]');
	check(!!btn, label + ": botao encontrado");
	if (!btn) return;

	const onclick = btn.getAttribute("onclick");
	check(!!onclick && onclick.indexOf("confirm") >= 0, label + ": onclick com confirm");
	check(parseConfirmMessage(onclick) === expectedMsg, label + ": mensagem confirm");

	check(installClickHandler(win, btn), label + ": handler onclick instalado");

	let calls = 0;
	let lastMsg = null;
	win.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return false;
	};
	const evFalse = new win.MouseEvent("click", { bubbles: true, cancelable: true });
	const retFalse = btn.dispatchEvent(evFalse);
	check(calls === 1, label + ": confirm 1x false");
	check(retFalse === false || evFalse.defaultPrevented, label + ": click cancelado (false)");
	check(lastMsg === expectedMsg, label + ": texto false");

	calls = 0;
	lastMsg = null;
	win.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return true;
	};
	const evTrue = new win.MouseEvent("click", { bubbles: true, cancelable: true });
	const retTrue = btn.dispatchEvent(evTrue);
	check(calls === 1, label + ": confirm 1x true");
	check(retTrue !== false, label + ": click permitido (true)");
	check(lastMsg === expectedMsg, label + ": texto true");
}

const htmlDefault = renderCand({
	reports_cfg: {
		enabled: false,
		retention_days: 45,
		collect_interval: 5,
		event_log_enabled: false,
		event_retention_days: 11,
		event_max_mb: 100,
		event_interfaces: [],
	},
});

const htmlRevoke = renderCand({
	license_status: {
		valid: true,
		expired: false,
		grace: false,
		dev_mode: false,
		clock_suspect: false,
		hardware_id: "HW-OK",
		customer: "Cliente",
		expiry: "2030-01-01",
		days_left: 100,
		error: "",
	},
});

const dom = openDom(htmlDefault);
const doc = dom.window.document;

[
	"l7_pkg_update",
	"l7_update_status",
	"l7_update_versions",
	"l7_update_actions",
	"l7_btn_check_update",
	"l7_check_update_post",
	"l7_rpt_preset",
	"l7_rpt_custom",
	"l7_evt_preset",
	"l7_evt_custom",
].forEach(function (id) {
	check(!!doc.getElementById(id), "DOM id " + id);
});

check(htmlDefault.indexOf("layer7_settings_update.js") !== -1, "update: script src externo");
check(htmlDefault.indexOf("onclick='return confirm(") !== -1, "render: aspas simples onclick confirm");
check(htmlDefault.indexOf('onclick="return confirm(') === -1, "render: sem onclick aspas duplas");

testConfirmClick("revoke", htmlRevoke, "revoke_license", REVOKE_MSG);
testConfirmClick("import", htmlDefault, "import_config", IMPORT_MSG);

const doUpdateHtml = renderCand({
	update_info: {
		current: "1.9.78",
		latest: "1.9.79",
		tag: "v1.9.79",
		pkg_url: "https://github.com/pablomichelin/Layer7/releases/download/v1.9.79/pfSense-pkg-layer7-1.9.79.pkg",
		name: "v1.9.79",
	},
});
check(doUpdateHtml.indexOf('name="do_update"') !== -1, "update: do_update presente");
check(doUpdateHtml.indexOf('name="do_update"') === -1 ||
	doUpdateHtml.split('name="do_update"')[1].indexOf("onclick='return confirm(") === -1,
	"update: sem confirm em do_update");

if (fail) {
	console.error("SOME SETTINGS JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL SETTINGS JS TESTS PASSED");
process.exit(0);
