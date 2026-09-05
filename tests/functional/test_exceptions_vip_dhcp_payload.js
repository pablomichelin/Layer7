#!/usr/bin/env node
/**
 * V6b2a — FormData modo DHCP baseline V6b2a vs candidato (lista exacta de IPs).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_dhcp_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");

const { JSDOM } = resolveJsdom();
const renderPhp = path.join(__dirname, "harness-exceptions-view/render-vip-dhcp-parity.php");

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
	return JSON.parse(raw);
}

function dhcpForm(doc) {
	return doc.querySelector('form[action*="layer7_exceptions.php#l7-vip-list"]');
}

function openDom(html, url, extra) {
	return new JSDOM(html, Object.assign({
		url: url || "http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip_dhcp=1",
		runScripts: "outside-only",
	}, extra || {}));
}

function entriesEqual(a, b) {
	if (a.length !== b.length) return false;
	for (let i = 0; i < a.length; i++) {
		if (a[i][0] !== b[i][0] || a[i][1] !== b[i][1]) return false;
	}
	return true;
}

function sortedIps(entries) {
	return entries
		.filter(function (p) { return p[0] === "vip_dhcp_ip[]"; })
		.map(function (p) { return p[1]; })
		.sort();
}

function formPayload(html, submitName, fill) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = doc.querySelector('form[action*="layer7_exceptions.php#l7-vip-list"]');
	const submit = form ? form.querySelector('[name="' + submitName + '"]') : null;
	if (!form || !submit) {
		return { error: "form/submitter ausente: " + submitName };
	}
	if (typeof fill === "function") fill(form, doc);
	let fd;
	try {
		fd = new dom.window.FormData(form, submit);
	} catch (e) {
		return { error: "FormData falhou: " + (e && e.message ? e.message : e) };
	}
	return {
		method: (form.getAttribute("method") || "get").toLowerCase(),
		action: form.getAttribute("action") || "",
		submitValue: fd.get(submitName),
		entries: Array.from(fd.entries()),
	};
}

function largeCfg() {
	const dhcpd = {};
	const interfaces = {};
	["lan", "wan", "opt1", "opt2"].forEach(function (ifid, idx) {
		const maps = [];
		for (let j = 1; j <= 8; j++) {
			const oct = 10 + idx * 8 + j;
			maps.push({
				ipaddr: "192.168." + oct + ".10",
				mac: "aa:bb:cc:dd:ee:" + String(oct).padStart(2, "0"),
			});
		}
		dhcpd[ifid] = { staticmap: maps };
		interfaces[ifid] = { descr: ifid.toUpperCase() };
	});
	const lan6 = {
		staticmap: [{ ipaddrv6: "2001:db8::1", mac: "aa:bb:cc:dd:ee:f1", descr: "IPv6 lab" }],
	};
	dhcpd.lan6 = lan6;
	interfaces.lan6 = { descr: "LAN6" };
	return { dhcpd: dhcpd, dhcpdv6: { lan6: lan6 }, interfaces: interfaces };
}

function expectedLargeIps(cfg) {
	const ips = [];
	["lan", "wan", "opt1", "opt2"].forEach(function (ifid, idx) {
		for (let j = 1; j <= 8; j++) {
			const oct = 10 + idx * 8 + j;
			ips.push("192.168." + oct + ".10");
		}
	});
	ips.push("2001:db8::1");
	return ips.sort();
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
		lan: { staticmap: [{ ipaddr: "192.168.1.50", mac: "aa:bb:cc:dd:ee:01", descr: "LAN" }] },
		wan: { staticmap: [{ ipaddr: "192.168.2.50", mac: "aa:bb:cc:dd:ee:02", descr: "WAN" }] },
	},
	interfaces: { lan: { descr: "LAN" }, wan: { descr: "WAN" } },
};

const pair1 = renderPair({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
const fill1 = function (form) {
	const boxes = form.querySelectorAll('[name="vip_dhcp_ip[]"]');
	if (boxes[0]) boxes[0].checked = true;
};
const base1 = formPayload(pair1.baseline, "add_vip_from_dhcp", fill1);
const cand1 = formPayload(pair1.candidate, "add_vip_from_dhcp", fill1);
check(!base1.error && !cand1.error, "dhcp1: FormData extraido");
if (!base1.error && !cand1.error) {
	check(base1.method === "post" && cand1.method === "post", "dhcp1: method POST");
	check(base1.action === cand1.action, "dhcp1: action identico");
	check(entriesEqual(base1.entries, cand1.entries), "dhcp1: entries iguais");
	check(base1.submitValue === "1" && cand1.submitValue === "1", "dhcp1: submitter value=1");
}

const pair2 = renderPair({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
const fill2 = function (form) {
	form.querySelectorAll('[name="vip_dhcp_ip[]"]').forEach(function (cb, idx) {
		cb.checked = idx < 2;
	});
};
const base2 = formPayload(pair2.baseline, "add_vip_from_dhcp", fill2);
const cand2 = formPayload(pair2.candidate, "add_vip_from_dhcp", fill2);
check(!base2.error && !cand2.error, "dhcp2: FormData extraido");
check(entriesEqual(base2.entries, cand2.entries), "dhcp2: duas seleccoes paridade");

const bigCfg = largeCfg();
const expected33 = expectedLargeIps(bigCfg);
const pairLarge = renderPair({ data: vipSchema, config: bigCfg, vip_dhcp_mode: true });

function allSelectedPayload(html) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const boxes = doc.querySelectorAll('[name="vip_dhcp_ip[]"]');
	check(boxes.length === expected33.length, "dhcp33: " + boxes.length + " checkboxes no DOM");
	boxes.forEach(function (cb) { cb.checked = true; });
	const form = doc.querySelector('form[action*="layer7_exceptions.php#l7-vip-list"]');
	const submit = form ? form.querySelector('[name="add_vip_from_dhcp"]') : null;
	check(!!form && !!submit, "dhcp33: form/submitter presentes");
	if (!form || !submit) {
		return { error: "form ausente fixture grande" };
	}
	const fd = new dom.window.FormData(form, submit);
	return { entries: Array.from(fd.entries()) };
}

const baseLarge = allSelectedPayload(pairLarge.baseline);
const candLarge = allSelectedPayload(pairLarge.candidate);
check(!baseLarge.error && !candLarge.error, "dhcp33: FormData fixture grande extraido");
if (!baseLarge.error && !candLarge.error) {
	check(entriesEqual(baseLarge.entries, candLarge.entries), "dhcp33: entries baseline/candidato identicos");
	const baseIps = sortedIps(baseLarge.entries);
	const candIps = sortedIps(candLarge.entries);
	check(baseIps.length === expected33.length, "dhcp33: contagem IPs baseline " + baseIps.length);
	check(candIps.length === expected33.length, "dhcp33: contagem IPs candidato " + candIps.length);
	check(baseIps.join(",") === expected33.join(","), "dhcp33: lista exacta baseline");
	check(candIps.join(",") === expected33.join(","), "dhcp33: lista exacta candidato");
	check(new Set(baseIps).size === expected33.length, "dhcp33: baseline sem duplicados");
	check(new Set(candIps).size === expected33.length, "dhcp33: candidato sem duplicados");
	let ipOk = true;
	for (let i = 0; i < expected33.length; i++) {
		if (baseIps[i] !== expected33[i] || candIps[i] !== expected33[i]) {
			ipOk = false;
			break;
		}
	}
	check(ipOk, "dhcp33: ordem sorted alinhada com fixture");
}

/* WAN marcado + filtro LAN + limpar visiveis: FormData mantem WAN (JS real da pagina) */
const pairWan = renderPair({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
const domWan = openDom(pairWan.candidate, undefined, { runScripts: "dangerously" });
const docWan = domWan.window.document;
const wanCb = docWan.querySelector('[name="vip_dhcp_ip[]"][value="192.168.2.50"]');
const lanBtn = docWan.querySelector('.l7-dhcp-iface-btn[data-iface="lan"]');
const clearBtn = docWan.getElementById("l7-dhcp-clear-visible");
const submitWan = docWan.querySelector('[name="add_vip_from_dhcp"]');
const formWan = dhcpForm(docWan);
check(!!wanCb, "dhcp wan: checkbox WAN presente");
check(!!lanBtn, "dhcp wan: botao filtro LAN presente");
check(!!clearBtn, "dhcp wan: botao Limpar presente");
check(!!submitWan, "dhcp wan: submit presente");
check(!!formWan, "dhcp wan: form presente");
if (wanCb && lanBtn && clearBtn && submitWan && formWan) {
	wanCb.checked = true;
	lanBtn.click();
	clearBtn.click();
	const fdWan = new domWan.window.FormData(formWan, submitWan);
	const wanIps = fdWan.getAll("vip_dhcp_ip[]");
	check(wanIps.indexOf("192.168.2.50") >= 0, "dhcp wan: FormData inclui WAN apos limpar visiveis LAN");
	check(wanIps.indexOf("192.168.1.50") < 0, "dhcp wan: FormData sem LAN apos limpar visiveis");
}

/* sem JS: checkbox manual + submit possivel */
const pairNoJs = renderPair({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
const domNoJs = openDom(pairNoJs.candidate);
const manualCb = domNoJs.window.document.querySelector('[name="vip_dhcp_ip[]"]');
const manualSubmit = domNoJs.window.document.querySelector('[name="add_vip_from_dhcp"]');
check(!!manualCb, "dhcp no-js: checkbox presente");
check(!!manualSubmit, "dhcp no-js: submit presente");
if (manualCb && manualSubmit) {
	manualCb.checked = true;
	const manualFd = new domNoJs.window.FormData(dhcpForm(domNoJs.window.document), manualSubmit);
	check(manualFd.getAll("vip_dhcp_ip[]").length === 1, "dhcp no-js: selecao manual no FormData");
}

if (fail) {
	console.error("SOME EXCEPTIONS VIP DHCP PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP DHCP PAYLOAD TESTS PASSED");
process.exit(0);
