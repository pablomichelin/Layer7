#!/usr/bin/env node
/**
 * V6b1 — JS VIP: filtro interface, confirm remover, ponte bookmark GET-only.
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_js.js
 */
"use strict";

const path = require("path");
const vm = require("vm");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-exceptions-view/render-vip-parity.php");

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

function runBridge(html, pageUrl) {
	const dom = new JSDOM(html, {
		url: pageUrl,
		runScripts: "outside-only",
	});
	const bridge = dom.window.document.getElementById("l7-vip-bookmark-bridge");
	if (!bridge || !bridge.textContent) {
		return null;
	}
	let replaceUrl = null;
	const hash = pageUrl.includes("#") ? pageUrl.slice(pageUrl.indexOf("#")) : "";
	const location = {
		hash: hash,
		replace: function (u) {
			replaceUrl = u;
		},
	};
	vm.runInNewContext(bridge.textContent, {
		window: { location: location },
		location: location,
	});
	return replaceUrl;
}

function openDom(html, pageUrl, extra) {
	const opts = Object.assign({
		url: pageUrl,
		runScripts: "dangerously",
		pretendToBeVisual: false,
	}, extra || {});
	return new JSDOM(html, opts);
}

function invokeAttr(win, el, attrName, ev) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return { ret: undefined, ev: ev };
	}
	const evt = ev || { preventDefault: function () { this.prevented = true; } };
	const fn = win.eval("(function(event) { " + code + " })");
	const ret = fn.call(el, evt);
	return { ret: ret, ev: evt };
}

function installNetworkCounters(win) {
	const counters = { fetch: 0, submit: 0, requestSubmit: 0 };
	win.fetch = function () {
		counters.fetch++;
		return Promise.resolve({ ok: true, json: function () { return Promise.resolve({}); } });
	};
	const proto = win.HTMLFormElement.prototype;
	proto.submit = function () { counters.submit++; };
	proto.requestSubmit = function () { counters.requestSubmit++; };
	return counters;
}

function visibleRows(doc, iface) {
	return Array.from(doc.querySelectorAll(".l7-iface-row")).filter(function (row) {
		return iface === "" || row.getAttribute("data-iface") === iface;
	}).filter(function (row) {
	return String(row.className).indexOf("hidden") === -1;
	});
}

function testFilter(html, label) {
	const dom = openDom(html, "http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip=1");
	const win = dom.window;
	const counters = installNetworkCounters(win);
	const doc = win.document;
	const filterWrap = doc.getElementById("l7-vip-iface-filter");
	check(!!filterWrap, label + ": filtro presente");
	const lanBtn = filterWrap ? filterWrap.querySelector('.l7-vip-iface-btn[data-iface="lan"]') : null;
	const allBtn = filterWrap ? filterWrap.querySelector('.l7-vip-iface-btn[data-iface=""]') : null;
	check(!!lanBtn && !!allBtn, label + ": botoes lan/todas");
	if (lanBtn && allBtn) {
		lanBtn.click();
		check(visibleRows(doc, "lan").length >= 1, label + ": LAN visivel apos filtro");
		check(visibleRows(doc, "wan").length === 0, label + ": WAN oculta apos filtro");
		allBtn.click();
		check(visibleRows(doc, "lan").length >= 1, label + ": LAN visivel apos Todas");
		check(visibleRows(doc, "wan").length >= 1, label + ": WAN visivel apos Todas");
	}
	check(counters.fetch === 0 && counters.submit === 0 && counters.requestSubmit === 0,
		label + ": zero fetch/submit/requestSubmit");
}

const bridgeId = "l7-vip-bookmark-bridge";
const hashUrl = "http://127.0.0.1/packages/layer7/layer7_exceptions.php#l7-vip-list";
const plainUrl = "http://127.0.0.1/packages/layer7/layer7_exceptions.php";

const ex1 = [{ id: "mgmt", enabled: true, priority: 500, action: "allow", hosts: ["10.0.0.1"] }];

const CONFIRM_KEY = "Remover este isento da Lista VIP?";
const CONFIRM_ADV = 'Remover "VIP" & <b>x</b>\'s isento?';

const filterData = {
	get: { vip: "1" },
	data: {
		layer7: {
			exceptions: [{
				id: "vip-isentos",
				enabled: true,
				priority: 9000,
				action: "allow",
				managed_by: "profiles",
				hosts: ["192.168.1.10", "192.168.2.10"],
			}],
			vip_meta: { labels: { "192.168.1.10": "a", "192.168.2.10": "b" } },
		},
	},
	config: {
		dhcpd: {
			lan: { staticmap: [{ ipaddr: "192.168.1.10", mac: "aa:bb:cc:dd:ee:01" }] },
			wan: { staticmap: [{ ipaddr: "192.168.2.10", mac: "aa:bb:cc:dd:ee:02" }] },
		},
		interfaces: {
			lan: { descr: "LAN" },
			wan: { descr: "WAN" },
		},
	},
};

/* ponte: GET consulta geral + hash → redirect */
const htmlGeneral = renderCand({ exceptions: ex1, vip_general_only: true });
check(htmlGeneral.includes('id="' + bridgeId + '"'), "bookmark: script presente em GET geral");
const generalReplace = runBridge(htmlGeneral, hashUrl);
check(generalReplace === "layer7_exceptions.php?vip=1#l7-vip-list", "bookmark: GET geral+hash chama replace");

/* ponte: GET geral sem hash → sem redirect */
const generalNoHash = runBridge(htmlGeneral, plainUrl);
check(generalNoHash === null, "bookmark: GET geral sem hash nao redirecciona");

/* ponte: ausente/inoperante em GET new+hash */
const htmlNew = renderCand({ exceptions: ex1, get: { new: "1" } });
check(!htmlNew.includes('id="' + bridgeId + '"'), "bookmark: ausente em GET new (HTML)");
check(runBridge(htmlNew, hashUrl) === null, "bookmark: GET new+hash sem replace");

/* ponte: ausente em GET edit+hash */
const htmlEdit = renderCand({ exceptions: ex1, get: { edit: "0" } });
check(!htmlEdit.includes('id="' + bridgeId + '"'), "bookmark: ausente em GET edit (HTML)");
check(runBridge(htmlEdit, hashUrl) === null, "bookmark: GET edit+hash sem replace");

const emptyVipData = {
	layer7: {
		exceptions: [{
			id: "vip-isentos",
			enabled: true,
			priority: 9000,
			action: "allow",
			managed_by: "profiles",
		}],
	},
};

/* ponte: ausente em ?vip=1 */
const htmlVip = renderCand({ get: { vip: "1" }, data: emptyVipData });
check(!htmlVip.includes('id="' + bridgeId + '"'), "bookmark: ausente em vip=1");
check(runBridge(htmlVip, hashUrl) === null, "bookmark: vip=1+hash sem replace");

/* ponte: POST add_vip erro — preserva raw, sem replace */
const htmlVipErr = renderCand({
	post: {
		add_vip_entry: "1",
		vip_description: "desc-raw",
		vip_target: "bad-target",
	},
	data: emptyVipData,
});
check(!htmlVipErr.includes('id="' + bridgeId + '"'), "bookmark: ausente em POST add_vip erro (HTML)");
check(htmlVipErr.includes("desc-raw"), "bookmark: POST erro preserva descricao");
check(runBridge(htmlVipErr, hashUrl) === null, "bookmark: POST add_vip erro+hash sem replace");

/* ponte: POST add_vip savefalse */
const htmlVipSf = renderCand({
	post: {
		add_vip_entry: "1",
		vip_description: "sf-raw",
		vip_target: "10.0.0.9",
	},
	data: emptyVipData,
	save_result: false,
});
check(!htmlVipSf.includes('id="' + bridgeId + '"'), "bookmark: ausente em POST add_vip savefalse");
check(runBridge(htmlVipSf, hashUrl) === null, "bookmark: POST savefalse+hash sem replace");

/* filtro VIP em lista normal */
testFilter(renderCand(filterData), "filtro");

/* filtro apos POST remove savefalse (permanece em lista) */
testFilter(renderCand(Object.assign({}, filterData, {
	post: { remove_vip_entry: "1", vip_remove_target: "192.168.1.10" },
	save_result: false,
})), "filtro pos POST savefalse");

/* confirm remover: true/false — mensagem independente da fixture l7_t */
const htmlRm = renderCand(Object.assign({
	get: { vip: "1" },
	data: {
		layer7: {
			exceptions: [{
				id: "vip-isentos",
				enabled: true,
				priority: 9000,
				action: "allow",
				managed_by: "profiles",
				hosts: ["10.0.0.5"],
			}],
			vip_meta: { labels: { "10.0.0.5": "rm" } },
		},
	},
	l7_t_fixture: { [CONFIRM_KEY]: CONFIRM_ADV },
}));
const domRm = openDom(htmlRm, plainUrl + "?vip=1", { runScripts: "outside-only" });
const winRm = domRm.window;
const countersRm = installNetworkCounters(winRm);
const rmForm = Array.from(winRm.document.querySelectorAll("form")).find(function (f) {
	return f.querySelector('[name="remove_vip_entry"]');
});
check(!!rmForm, "remove: form encontrado");
if (rmForm) {
	const onsubmit = rmForm.getAttribute("onsubmit");
	check(!!onsubmit && onsubmit.indexOf("confirm") >= 0, "remove: onsubmit com confirm");
	const expectedMsg = CONFIRM_ADV;
	let calls = 0;
	let lastMsg = null;
	winRm.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return false;
	};
	const blocked = invokeAttr(winRm, rmForm, "onsubmit", { preventDefault: function () {} }).ret === false;
	check(calls === 1, "remove: confirm chamado 1x (false)");
	check(blocked, "remove: confirm false bloqueia");
	check(lastMsg === expectedMsg, "remove: texto fixture adversarial (false)");
	calls = 0;
	lastMsg = null;
	winRm.confirm = function (msg) {
		calls++;
		lastMsg = msg;
		return true;
	};
	const allowed = invokeAttr(winRm, rmForm, "onsubmit", { preventDefault: function () {} }).ret !== false;
	check(calls === 1, "remove: confirm chamado 1x (true)");
	check(allowed, "remove: confirm true permite");
	check(lastMsg === expectedMsg, "remove: texto fixture adversarial (true)");
	check(countersRm.submit === 0 && countersRm.requestSubmit === 0,
		"remove: sem submit real no harness");
}

if (fail) {
	console.error("SOME EXCEPTIONS VIP JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP JS TESTS PASSED");
process.exit(0);
