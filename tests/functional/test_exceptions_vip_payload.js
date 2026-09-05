#!/usr/bin/env node
/**
 * V6b1 — FormData baseline V6b1 pinada vs candidato (schema vip-isentos real).
 *
 *   LAYER7_PHP=... LAYER7_JSDOM=... node tests/functional/test_exceptions_vip_payload.js
 */
"use strict";

const path = require("path");
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

function renderPair(opts) {
	const raw = runPhp(renderPhp, [JSON.stringify(opts)]);
	let parsed;
	try {
		parsed = JSON.parse(raw);
	} catch (e) {
		console.error("FAIL: JSON invalido de render-vip-parity.php");
		console.error(raw.slice(0, 500));
		process.exit(1);
	}
	return parsed;
}

function openDom(html, url) {
	return new JSDOM(html, {
		url: url || "http://127.0.0.1/packages/layer7/layer7_exceptions.php?vip=1",
		runScripts: "outside-only",
	});
}

function entriesEqual(a, b) {
	if (a.length !== b.length) return false;
	for (let i = 0; i < a.length; i++) {
		if (a[i][0] !== b[i][0] || a[i][1] !== b[i][1]) return false;
	}
	return true;
}

function entriesPreview(entries) {
	return JSON.stringify(entries.map(function (p) {
		const v = p[1];
		return [p[0], v.length > 48 ? v.slice(0, 48) + "…" : v];
	}));
}

function findFormBySubmitter(doc, submitName, target) {
	const forms = Array.from(doc.querySelectorAll('form[action*="layer7_exceptions.php#l7-vip-list"]'));
	if (target) {
		const hit = forms.find(function (f) {
			const hidden = f.querySelector('[name="vip_remove_target"]');
			return hidden && hidden.getAttribute("value") === target &&
				f.querySelector('[name="' + submitName + '"]');
		});
		if (hit) return hit;
	}
	return forms.find(function (f) {
		return f.querySelector('[name="' + submitName + '"]');
	}) || null;
}

function formPayload(html, submitName, fill, target) {
	const dom = openDom(html);
	const doc = dom.window.document;
	const form = findFormBySubmitter(doc, submitName, target);
	if (!form) {
		return { error: "form ausente: " + submitName + (target ? "@" + target : "") };
	}
	if (typeof fill === "function") {
		fill(form, doc);
	}
	const submit = form.querySelector('[name="' + submitName + '"]');
	if (!submit) {
		return { error: "submitter ausente: " + submitName };
	}
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
		submitAttr: submit.getAttribute("value"),
		entries: Array.from(fd.entries()),
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
	check(base.action === cand.action, label + ": action " + base.action);
	check(entriesEqual(base.entries, cand.entries), label + ": entries " + entriesPreview(base.entries));
	check(base.submitValue === "1" && cand.submitValue === "1", label + ": submitter value=1");
	check(base.submitAttr === "1" && cand.submitAttr === "1", label + ": attr value=1");
	check(
		base.entries.filter(function (p) { return p[0] === submitName; }).length === 1,
		label + ": um par submitter"
	);
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

function vipData(hosts, labels, extra) {
	const exc = Object.assign({}, vipSchema.layer7.exceptions[0]);
	if (hosts && hosts.length) exc.hosts = hosts;
	const data = { layer7: { exceptions: [exc] } };
	if (labels && Object.keys(labels).length) {
		data.layer7.vip_meta = { labels: labels };
	}
	if (extra) Object.assign(data.layer7, extra.layer7 || {});
	return data;
}

function buildFullVipData() {
	const hosts = [];
	const labels = {};
	for (let i = 1; i <= 32; i++) {
		const h = "10.0.0." + i;
		hosts.push(h);
		labels[h] = "host" + i;
	}
	const exc = Object.assign({}, vipSchema.layer7.exceptions[0], { hosts: hosts });
	const cidrs = [];
	for (let i = 0; i < 16; i++) {
		const c = "192.168." + i + ".0/24";
		cidrs.push(c);
		labels[c] = "cidr" + i;
	}
	exc.cidrs = cidrs;
	return { layer7: { exceptions: [exc], vip_meta: { labels: labels } } };
}

console.log("Runtime: FormData jsdom; baseline V6b1 pinada + candidato");

/* add vazio (baseline inline / candidato vip_add) */
const addPair = renderPair({ data: vipSchema, vip_add_mode: true });
const fillAdd = function (form) {
	const d = form.querySelector('[name="vip_description"]');
	const t = form.querySelector('[name="vip_target"]');
	if (d) d.value = "Director";
	if (t) t.value = "192.168.1.50";
};
const baseAdd = formPayload(addPair.baseline, "add_vip_entry", fillAdd);
const candAdd = formPayload(addPair.candidate, "add_vip_entry", fillAdd);
assertParity("add4", baseAdd, candAdd, "add_vip_entry");

/* add6 paridade completa */
const fillAdd6 = function (form) {
	form.querySelector('[name="vip_description"]').value = "IPv6";
	form.querySelector('[name="vip_target"]').value = "2001:db8::1";
};
const add6Pair = renderPair({ data: vipSchema, vip_add_mode: true });
const baseAdd6 = formPayload(add6Pair.baseline, "add_vip_entry", fillAdd6);
const candAdd6 = formPayload(add6Pair.candidate, "add_vip_entry", fillAdd6);
assertParity("add6", baseAdd6, candAdd6, "add_vip_entry");

/* CIDR paridade completa */
const fillCidr = function (form) {
	form.querySelector('[name="vip_description"]').value = "Rede";
	form.querySelector('[name="vip_target"]').value = "192.0.2.0/24";
};
const cidrPair = renderPair({ data: vipSchema, vip_add_mode: true });
const baseCidr = formPayload(cidrPair.baseline, "add_vip_entry", fillCidr);
const candCidr = formPayload(cidrPair.candidate, "add_vip_entry", fillCidr);
assertParity("add CIDR", baseCidr, candCidr, "add_vip_entry");

/* remove 0 entradas — sem forms */
const emptyPair = renderPair({ data: vipSchema, config: dhcpCfg });
const baseRm0 = formPayload(emptyPair.baseline, "remove_vip_entry");
const candRm0 = formPayload(emptyPair.candidate, "remove_vip_entry");
check(!!baseRm0.error && !!candRm0.error, "remove0: nenhum form em lista vazia");

/* remove 1 entrada */
const rmData1 = vipData(["10.0.0.1"], { "10.0.0.1": "A" });
const rmPair1 = renderPair({ data: rmData1, config: dhcpCfg });
const baseRm1 = formPayload(rmPair1.baseline, "remove_vip_entry", null, "10.0.0.1");
const candRm1 = formPayload(rmPair1.candidate, "remove_vip_entry", null, "10.0.0.1");
assertParity("remove1", baseRm1, candRm1, "remove_vip_entry");

/* remove 48 entradas — paridade por linha */
const fullData = buildFullVipData();
const rmPair48 = renderPair({ data: fullData, config: dhcpCfg });
const targets48 = [];
for (let i = 1; i <= 32; i++) targets48.push("10.0.0." + i);
for (let i = 0; i < 16; i++) targets48.push("192.168." + i + ".0/24");
let rm48ok = true;
for (let t = 0; t < targets48.length; t++) {
	const tgt = targets48[t];
	const b = formPayload(rmPair48.baseline, "remove_vip_entry", null, tgt);
	const c = formPayload(rmPair48.candidate, "remove_vip_entry", null, tgt);
	if (b.error || c.error || !entriesEqual(b.entries, c.entries)) {
		rm48ok = false;
		break;
	}
}
check(rm48ok, "remove48: pares completos em " + targets48.length + " linhas");

const dhcpPair = renderPair({ data: vipSchema, config: dhcpCfg, vip_dhcp_mode: true });
const baseDhcp = formPayload(dhcpPair.baseline, "add_vip_from_dhcp", function (form) {
	const cb = form.querySelector('[name="vip_dhcp_ip[]"]');
	if (cb) cb.checked = true;
});
const candDhcp = formPayload(dhcpPair.candidate, "add_vip_from_dhcp", function (form) {
	const cb = form.querySelector('[name="vip_dhcp_ip[]"]');
	if (cb) cb.checked = true;
});
if (!baseDhcp.error && !candDhcp.error) {
	check(entriesEqual(baseDhcp.entries, candDhcp.entries), "dhcp: entries " + entriesPreview(baseDhcp.entries));
	check(baseDhcp.action === candDhcp.action, "dhcp: action identico");
} else {
	check(false, "dhcp: forms extraidos");
}

/* export na consulta (permanece inline) */
const listPair = renderPair({ data: vipSchema, config: dhcpCfg });
const baseExp = formPayload(listPair.baseline, "export_vip_list");
const candExp = formPayload(listPair.candidate, "export_vip_list");
assertParity("export", baseExp, candExp, "export_vip_list");

/* retry raw separado (candidato) — render proprio, DOM intacto antes da assercao */
const retryPair = renderPair({
	data: vipSchema,
	post: {
		add_vip_entry: "1",
		vip_description: "raw-desc",
		vip_target: "bad-ip",
	},
});
const retryDom = openDom(retryPair.candidate, "http://127.0.0.1/packages/layer7/layer7_exceptions.php");
const desc = retryDom.window.document.querySelector('[name="vip_description"]');
const tgt = retryDom.window.document.querySelector('[name="vip_target"]');
check(desc && desc.value === "raw-desc", "retry: descricao raw no DOM");
check(tgt && tgt.value === "bad-ip", "retry: target raw no DOM");

if (fail) {
	console.error("SOME EXCEPTIONS VIP PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL EXCEPTIONS VIP PAYLOAD TESTS PASSED");
process.exit(0);
