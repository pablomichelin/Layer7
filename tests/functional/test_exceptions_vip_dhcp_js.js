#!/usr/bin/env node
/**
 * V6b2a — JS DHCP: funcoes REAIS da view, seleccao oculta, filtro por interface.
 * Gate portatil: corre sem CSS externo (LAYER7_PHP + LAYER7_JSDOM).
 *
 * Auditoria CSS opcional (nao e homologacao visual nativa completa):
 *   LAYER7_BOOTSTRAP_PIN_CSS aponta para Bootstrap pinado; SKIP se ausente;
 *   FAIL se ficheiro em falta ou SHA != pin oficial.
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_dhcp_js.js
 *   LAYER7_BOOTSTRAP_PIN_CSS=... (opcional) — mesmo comando com env para auditoria CSS.
 */
"use strict";

const crypto = require("crypto");
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const root = path.resolve(__dirname, "../..");
const phpPath = path.join(
	root,
	"package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_exceptions.php"
);
const renderPhp = path.join(__dirname, "harness-exceptions-view/render-vip-dhcp-parity.php");
const phpSource = fs.readFileSync(phpPath, "utf8");
const bsCssPath = process.env.LAYER7_BOOTSTRAP_PIN_CSS;
const BS_PIN_SHA256 =
	"c28eb8900abce3c478234e62390838556d839c10b7073b2ba42bcbae20d6e2fc";

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function extractFn(src, name) {
	const re = new RegExp("function\\s+" + name + "\\s*\\([^)]*\\)\\s*\\{", "m");
	const m = src.match(re);
	if (!m || m.index === undefined) {
		return null;
	}
	let i = m.index + m[0].length - 1;
	let depth = 0;
	for (; i < src.length; i++) {
		const ch = src[i];
		if (ch === "{") {
			depth++;
		} else if (ch === "}") {
			depth--;
			if (depth === 0) {
				return src.slice(m.index, i + 1);
			}
		}
	}
	return null;
}

const fnSetChecks = extractFn(phpSource, "l7setChecks");
const fnSetVisibleChecks = extractFn(phpSource, "l7setVisibleChecks");
const fnSetVisibleDhcpChecks = extractFn(phpSource, "l7setVisibleDhcpChecks");
const fnFilterDhcpIface = extractFn(phpSource, "l7filterDhcpIface");

check(!!fnSetChecks, "produto: l7setChecks extraido");
check(!!fnSetVisibleChecks, "produto: l7setVisibleChecks extraido");
check(!!fnSetVisibleDhcpChecks, "produto: l7setVisibleDhcpChecks extraido");
check(!!fnFilterDhcpIface, "produto: l7filterDhcpIface extraido");
if (!fnSetChecks || !fnSetVisibleChecks || !fnSetVisibleDhcpChecks || !fnFilterDhcpIface) {
	process.exit(1);
}

function renderCand(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	return JSON.parse(raw).candidate;
}

function openDom(html, pageUrl, extra) {
	return new JSDOM(html, Object.assign({
		url: pageUrl || "http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_dhcp=1",
		runScripts: "dangerously",
		pretendToBeVisual: false,
	}, extra || {}));
}

function injectProductFns(win) {
	win.eval(fnSetChecks);
	win.eval(fnSetVisibleChecks);
	win.eval(fnSetVisibleDhcpChecks);
	win.eval(fnFilterDhcpIface);
}

function installNetworkCounters(win) {
	const counters = { fetch: 0, submit: 0, requestSubmit: 0 };
	win.fetch = function () {
		counters.fetch++;
		return Promise.resolve({ ok: true });
	};
	const proto = win.HTMLFormElement.prototype;
	proto.submit = function () { counters.submit++; };
	proto.requestSubmit = function () { counters.requestSubmit++; };
	return counters;
}

function panelVisible(panel) {
	if (!panel) return false;
	return !panel.classList.contains("hidden");
}

function fileSha256(filePath) {
	return crypto.createHash("sha256").update(fs.readFileSync(filePath)).digest("hex");
}

function dhcpForm(doc) {
	return doc.querySelector('form[action*="layer7_exceptions.php#l7-vip-list"]');
}

function dhcpSubmit(doc) {
	const form = dhcpForm(doc);
	return form ? form.querySelector('[name="add_vip_from_dhcp"]') : null;
}

function formDataIps(win, doc) {
	const form = dhcpForm(doc);
	const submit = dhcpSubmit(doc);
	if (!form || !submit) {
		return null;
	}
	const fd = new win.FormData(form, submit);
	return fd.getAll("vip_dhcp_ip[]");
}

const vipSchema = {
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
const dhcpCfg = {
	dhcpd: {
		lan: { staticmap: [{ ipaddr: "192.168.1.50", mac: "aa:bb:cc:dd:ee:01" }] },
		wan: { staticmap: [{ ipaddr: "192.168.2.50", mac: "aa:bb:cc:dd:ee:02" }] },
	},
	interfaces: { lan: { descr: "LAN" }, wan: { descr: "WAN" } },
};

/* l7setVisibleChecks real: listId inexistente nao marca nada */
const legacyDom = new JSDOM("<!DOCTYPE html><body></body>", { runScripts: "outside-only" });
injectProductFns(legacyDom.window);
legacyDom.window.document.body.innerHTML =
	'<div id="vip_dhcp_list">' +
	'<div class="l7-iface-col"><input type="checkbox" id="in-list" /></div>' +
	'<div class="l7-iface-col hidden"><input type="checkbox" id="hidden-col" /></div>' +
	"</div>" +
	'<input type="checkbox" id="outside" />';
legacyDom.window.l7setVisibleChecks("id-inexistente", true);
check(!legacyDom.window.document.getElementById("in-list").checked, "prod l7setVisibleChecks: listId ausente nao marca in-list");
check(!legacyDom.window.document.getElementById("hidden-col").checked, "prod l7setVisibleChecks: listId ausente nao marca hidden-col");
check(!legacyDom.window.document.getElementById("outside").checked, "prod l7setVisibleChecks: listId ausente nao marca fora");
legacyDom.window.l7setVisibleChecks("vip_dhcp_list", true);
check(legacyDom.window.document.getElementById("in-list").checked, "prod l7setVisibleChecks: listId valido marca visivel");
check(!legacyDom.window.document.getElementById("hidden-col").checked, "prod l7setVisibleChecks: listId valido ignora coluna oculta");

const html = renderCand({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
check(!html.includes('style="display:inline'), "dhcp js: filtro sem style inline");
check(!html.includes('class="l7-bulk-tools" style='), "dhcp js: tools sem style inline");

const dom = openDom(html);
const win = dom.window;
const counters = installNetworkCounters(win);
const doc = win.document;

const filter = doc.getElementById("l7-dhcp-iface-filter");
const lanBtn = filter ? filter.querySelector('.l7-dhcp-iface-btn[data-iface="lan"]') : null;
const allBtn = filter ? filter.querySelector('.l7-dhcp-iface-btn[data-iface=""]') : null;
const selectAll = doc.getElementById("l7-dhcp-select-visible");
const clearAll = doc.getElementById("l7-dhcp-clear-visible");
const lanPanel = doc.querySelector('.l7-dhcp-iface-panel[data-iface="lan"]');
const wanPanel = doc.querySelector('.l7-dhcp-iface-panel[data-iface="wan"]');
const wanCb = wanPanel ? wanPanel.querySelector('[name="vip_dhcp_ip[]"][value="192.168.2.50"]') : null;
const lanGroupBtn = lanPanel ? lanPanel.querySelector(".l7-dhcp-select-group") : null;
const wanGroupBtn = wanPanel ? wanPanel.querySelector(".l7-dhcp-select-group") : null;
const submit = dhcpSubmit(doc);

check(!!filter, "dhcp js: filtro presente");
check(!!lanBtn, "dhcp js: botao LAN presente");
check(!!allBtn, "dhcp js: botao Todas presente");
check(!!selectAll, "dhcp js: botao Selecionar tudo presente");
check(!!clearAll, "dhcp js: botao Limpar presente");
check(!!lanPanel, "dhcp js: painel LAN presente");
check(!!wanPanel, "dhcp js: painel WAN presente");
check(!!wanCb, "dhcp js: checkbox WAN presente");
check(!!lanGroupBtn, "dhcp js: botao Selecionar interface LAN presente");
check(!!wanGroupBtn, "dhcp js: botao Selecionar interface WAN presente");
check(!!submit, "dhcp js: submit add_vip_from_dhcp presente");

if (!fail) {
	lanBtn.click();
	check(panelVisible(lanPanel), "dhcp js: painel LAN visivel apos filtro");
	check(!panelVisible(wanPanel), "dhcp js: painel WAN oculto apos filtro");
	allBtn.click();
	check(panelVisible(lanPanel) && panelVisible(wanPanel), "dhcp js: ambos paineis visiveis apos Todas");

	wanCb.checked = true;
	lanBtn.click();
	clearAll.click();
	check(wanCb.checked, "dhcp js: WAN permanece marcado apos limpar visiveis LAN");
	const ipsAfterClear = formDataIps(win, doc);
	check(Array.isArray(ipsAfterClear) && ipsAfterClear.indexOf("192.168.2.50") >= 0,
		"dhcp js: FormData ainda inclui WAN apos limpar visiveis LAN");

	allBtn.click();
	wanCb.checked = true;
	const lanCbs = lanPanel.querySelectorAll('[name="vip_dhcp_ip[]"]');
	lanCbs.forEach(function (cb) { cb.checked = false; });
	lanGroupBtn.click();
	check(wanCb.checked, "dhcp js: WAN inalterado apos Selecionar esta interface LAN");
	check(Array.from(lanCbs).every(function (cb) { return cb.checked; }),
		"dhcp js: todos LAN marcados por Selecionar esta interface");
	wanGroupBtn.click();
	check(Array.from(wanPanel.querySelectorAll('[name="vip_dhcp_ip[]"]')).every(function (cb) {
		return cb.checked;
	}), "dhcp js: WAN marcado por Selecionar esta interface WAN");
	check(Array.from(lanCbs).every(function (cb) { return cb.checked; }),
		"dhcp js: LAN inalterado ao Selecionar interface WAN");

	check(counters.fetch === 0 && counters.submit === 0 && counters.requestSubmit === 0,
		"dhcp js: zero fetch/submit automatico");
}

/* bookmark */
const bridgeId = "l7-vip-bookmark-bridge";
const hashUrl = "http://127.0.0.1/packages/layer7/layer7_exceptions.php#l7-vip-list";
function runBridge(pageHtml, pageUrl) {
	const bdom = new JSDOM(pageHtml, { url: pageUrl, runScripts: "outside-only" });
	const bridge = bdom.window.document.getElementById(bridgeId);
	if (!bridge || !bridge.textContent) return null;
	let replaceUrl = null;
	const location = { hash: "#l7-vip-list", replace: function (u) { replaceUrl = u; } };
	vm.runInNewContext(bridge.textContent, { window: { location: location }, location: location });
	return replaceUrl;
}
const htmlGeneral = renderCand({
	exceptions: [{ id: "mgmt", enabled: true, priority: 500, action: "allow", hosts: ["10.0.0.1"] }],
	vip_general_only: true,
});
check(runBridge(htmlGeneral, hashUrl) === "layer7_exceptions.php?vip=1#l7-vip-list", "dhcp bookmark: geral+hash redirecciona");
check(!html.includes('id="' + bridgeId + '"'), "dhcp bookmark: ausente em vip_dhcp");

const htmlPostOk = renderCand({
	get: { vip_dhcp: "1" },
	post: { add_vip_from_dhcp: "1", vip_dhcp_ip: ["192.168.1.50"] },
	data: vipSchema,
	config: dhcpCfg,
	save_result: true,
});
check(!htmlPostOk.includes('name="add_vip_from_dhcp"'), "dhcp js: POST sucesso+vip_dhcp abre lista");
check(htmlPostOk.includes("192.168.1.50"), "dhcp js: POST sucesso+vip_dhcp mostra entrada");

const htmlPostErr = renderCand({
	get: { vip_add: "1" },
	post: { add_vip_from_dhcp: "1" },
	data: vipSchema,
	config: dhcpCfg,
});
check(htmlPostErr.includes('name="add_vip_from_dhcp"'), "dhcp js: POST erro DHCP prevalece sobre vip_add");
check(!htmlPostErr.includes('name="add_vip_entry"'), "dhcp js: POST erro DHCP nao abre manual");

dom.window.close();

/* auditoria CSS Bootstrap pinada — opcional; SKIP sem env; FAIL se env invalido */
if (!bsCssPath) {
	console.log("SKIP: dhcp css audit (LAYER7_BOOTSTRAP_PIN_CSS nao definido)");
} else if (!fs.existsSync(bsCssPath)) {
	console.error("FAIL: LAYER7_BOOTSTRAP_PIN_CSS em falta: " + bsCssPath);
	fail = 1;
} else {
	const cssHash = fileSha256(bsCssPath);
	if (cssHash !== BS_PIN_SHA256) {
		console.error("FAIL: LAYER7_BOOTSTRAP_PIN_CSS SHA inesperado: " + cssHash +
			" (esperado " + BS_PIN_SHA256 + ")");
		fail = 1;
	} else {
		check(true, "dhcp css: SHA pin oficial");
		const cssHtml = renderCand({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
		const cssDom = openDom(cssHtml, undefined, { runScripts: "outside-only" });
		const cssDoc = cssDom.window.document;
		if (!cssDoc.head) {
			const head = cssDoc.createElement("head");
			cssDoc.documentElement.insertBefore(head, cssDoc.body);
		}
		const st = cssDoc.createElement("style");
		st.id = "l7-pin-bootstrap-dhcp";
		st.textContent = fs.readFileSync(bsCssPath, "utf8");
		cssDoc.head.appendChild(st);
		injectProductFns(cssDom.window);
		const cssLanBtn = cssDoc.querySelector('.l7-dhcp-iface-btn[data-iface="lan"]');
		const cssWanPanel = cssDoc.querySelector('.l7-dhcp-iface-panel[data-iface="wan"]');
		check(!!cssLanBtn, "dhcp css: botao filtro LAN presente");
		check(!!cssWanPanel, "dhcp css: painel WAN presente");
		if (cssLanBtn && cssWanPanel) {
			cssLanBtn.click();
			cssDom.window.l7filterDhcpIface("lan");
			check(cssWanPanel.classList.contains("hidden"), "dhcp css: painel WAN classe hidden");
			const display = cssDom.window.getComputedStyle(cssWanPanel).display;
			check(display === "none", "dhcp css: painel WAN computed display none (" + display + ")");
			const heading = cssWanPanel.querySelector(".panel-heading");
			check(!!heading, "dhcp css: heading WAN presente no painel");
			check(cssWanPanel.contains(heading), "dhcp css: heading WAN descendente do painel oculto");
			/* jsdom: filho pode reportar display:block dentro de ancestral display:none — nao assertar heading */
		}
		cssDom.window.close();
	}
}

if (fail) {
	console.error("SOME EXCEPTIONS VIP DHCP JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP DHCP JS TESTS PASSED");
process.exit(0);
