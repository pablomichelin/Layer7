#!/usr/bin/env node
/**
 * BG-174 — legendas dos Form_Button + dispatch equivalente (não vazio).
 * Formulários manuais do lote continuam value=1. Sem Save extra.
 * PT/EN/ES a partir dos catálogos reais. Não é browser nem prova visual.
 *
 *   LAYER7_JSDOM=... LAYER7_PHP=... node tests/functional/test_form_buttons_payload.js
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");

const { JSDOM } = resolveJsdom();
let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function handlerTruthy(value) {
	return value != null && String(value) !== "" && String(value) !== "0";
}

const renderPhp = path.join(__dirname, "harness-form-buttons/render-pages.php");
const raw = runPhp(renderPhp);
let byLang;
try {
	byLang = JSON.parse(raw);
} catch (e) {
	console.error("FAIL: JSON do render PHP invalido");
	console.error(raw.slice(0, 400));
	process.exit(1);
}

function openDom(html) {
	return new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/form-buttons.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
}

function inspect(window, buttonName) {
	const btn = window.document.querySelector(
		'button[name="' + buttonName + '"], input[name="' + buttonName + '"]'
	);
	if (!btn) {
		return { missing: true };
	}
	const form = btn.form || btn.closest("form");
	if (!form) {
		return { missingForm: true };
	}
	if (typeof window.FormData !== "function") {
		return { noFormData: true };
	}
	let fd;
	try {
		fd = new window.FormData(form, btn);
	} catch (e) {
		return { formDataError: String(e && e.message ? e.message : e) };
	}
	const names = [];
	fd.forEach(function (_v, k) {
		names.push(k);
	});
	return {
		value: fd.get(buttonName),
		attr: btn.getAttribute("value"),
		text: (btn.textContent || "").replace(/\s+/g, " ").trim(),
		names: names,
		hasSave: names.indexOf("save") !== -1,
	};
}

function assertNative(lang, pageHtml, name, legend, extras) {
	extras = extras || {};
	const dom = openDom(pageHtml);
	const p = inspect(dom.window, name);
	const prefix = lang + " " + name;
	check(!p.missing && !p.missingForm, prefix + ": botao no form");
	check(!p.formDataError && !p.noFormData, prefix + ": FormData(form, submitter)");
	check(legend && legend !== "1", prefix + ": catalogo nao e 1");
	check(p.attr === legend, prefix + ": value=legenda");
	check(p.text.indexOf(legend) !== -1, prefix + ": texto visivel");
	check(p.attr !== "1" && p.text !== "1", prefix + ": nunca legenda 1");
	check(p.value === legend && handlerTruthy(p.value), prefix + ": dispatch equivalente (nao vazio)");
	check(!p.hasSave, prefix + ": sem Save extra");
	if (extras.without) {
		extras.without.forEach(function (other) {
			check((p.names || []).indexOf(other) === -1, prefix + ": sem " + other);
		});
	}
	if (extras.icon) {
		check(pageHtml.indexOf(extras.icon) !== -1, prefix + ": " + extras.icon);
	}
	dom.window.close();
	return p;
}

["pt", "en", "es"].forEach(function (lang) {
	const pack = byLang[lang];
	check(!!pack && !!pack.legends && !!pack.devices_edit, lang + ": render presente");
	if (!pack) {
		return;
	}
	const L = pack.legends;
	assertNative(lang, pack.devices_edit, "save_aliases", L.save_aliases, {
		without: ["assign_to_group"],
		icon: "fa fa-save",
	});

	const batch = openDom(pack.devices_batch);
	const pAlias = inspect(batch.window, "save_aliases");
	const pAssign = inspect(batch.window, "assign_to_group");
	check(pAlias.value === "1" && handlerTruthy(pAlias.value), lang + " batch aliases: value=1");
	check(pAlias.text.indexOf(L.save_aliases) !== -1 && pAlias.text !== "1", lang + " batch aliases: legenda");
	check(pAssign.value === "1" && handlerTruthy(pAssign.value), lang + " batch assign: value=1");
	check(pAssign.text.indexOf(L.assign_to_group) !== -1 && pAssign.text !== "1", lang + " batch assign: legenda");
	check((pAlias.names || []).indexOf("assign_to_group") === -1, lang + " batch: aliases sem assign");
	check((pAssign.names || []).indexOf("save_aliases") === -1, lang + " batch: assign sem aliases");
	check(!pAlias.hasSave && !pAssign.hasSave, lang + " batch: sem Save extra");
	batch.window.close();

	assertNative(lang, pack.groups_edit, "save_group_edit", L.save_group_edit, {
		without: ["add_group"],
		icon: "fa fa-save",
	});
	assertNative(lang, pack.groups_new, "add_group", L.add_group, { icon: "fa fa-plus" });
	assertNative(lang, pack.groups_list, "resync_devices", L.resync_devices, {
		without: ["delete_group"],
		icon: "fa fa-refresh",
	});
	assertNative(lang, pack.groups_list, "delete_group", L.delete_group, {
		without: ["resync_devices"],
		icon: "fa fa-trash",
	});
});

console.log("Form_Button: value=legenda (API oficial); lote manual: value=1; handlers = truthy");
console.log("Nao e browser, nao e prova visual, nao e aprovacao de UI");
if (fail) {
	console.error("SOME FORM BUTTON PAYLOAD TESTS FAILED");
	process.exit(1);
}
console.log("ALL FORM BUTTON PAYLOAD TESTS PASSED");
process.exit(0);
