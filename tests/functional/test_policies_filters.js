#!/usr/bin/env node
/**
 * BG-174 / V4-A Policies — l7filter / l7setChecks no jsdom 26.1.0.
 * DOM = HTML renderizado da fonte real com stubs. Dispara onkeyup/onclick
 * inline (outside-only). Não é browser, pfSense nem prova visual.
 *
 *   LAYER7_JSDOM=... LAYER7_PHP=... node tests/functional/test_policies_filters.js
 */
"use strict";

const fs = require("fs");
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const root = path.resolve(__dirname, "../..");
const phpPath = path.join(
	root,
	"package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_policies.php"
);
const renderPhp = path.join(__dirname, "harness-policies-view/render-fixture.php");
const phpSource = fs.readFileSync(phpPath, "utf8");
const scriptBlocks = [...phpSource.matchAll(/<script>\s*([\s\S]*?)\s*<\/script>/g)].map(function (m) {
	return m[1];
});
const productScript = scriptBlocks.find(function (s) {
	return s.includes("function l7filter(");
});
if (!productScript) {
	console.error("FAIL: script do produto em falta");
	process.exit(1);
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

const fnFilter = extractFn(productScript, "l7filter");
const fnSetChecks = extractFn(productScript, "l7setChecks");
if (!fnFilter || !fnSetChecks) {
	console.error("FAIL: l7filter ou l7setChecks em falta no produto");
	process.exit(1);
}
const filterScript = fnFilter + "\n" + fnSetChecks + "\n";

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

check(!/\$\s*\(/.test(filterScript), "script sem jQuery $");
check(filterScript.includes("function l7filter"), "extrai l7filter da fonte");
check(filterScript.includes("function l7setChecks"), "extrai l7setChecks da fonte");

function htmlWithoutProductScript(html) {
	return html.replace(/<script>[\s\S]*?<\/script>/g, "<!-- L7PF_SCRIPT_STRIPPED -->");
}

function openFixture(html) {
	const dom = new JSDOM(htmlWithoutProductScript(html), {
		url: "http://127.0.0.1/packages/layer7/layer7_policies.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
	const win = dom.window;
	win.eval(filterScript);
	return { win, document: win.document, dom };
}

function invokeAttr(win, el, attrName) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return false;
	}
	const fn = new win.Function("event", code);
	fn.call(el, { target: el, currentTarget: el });
	return true;
}

function findButtonByOnclick(rootEl, needle) {
	const buttons = rootEl.querySelectorAll("button[onclick]");
	for (let i = 0; i < buttons.length; i++) {
		const oc = buttons[i].getAttribute("onclick") || "";
		if (oc.indexOf(needle) >= 0) {
			return buttons[i];
		}
	}
	return null;
}

function checkboxByValue(listEl, value) {
	const boxes = listEl.querySelectorAll('input[type="checkbox"]');
	for (let i = 0; i < boxes.length; i++) {
		if (boxes[i].value === value) {
			return boxes[i];
		}
	}
	return null;
}

function isHiddenCheckbox(cb) {
	const label = cb.closest("label");
	return !!(label && label.style.display === "none");
}

function filterViaOnkeyup(win, input, value) {
	input.value = value;
	return invokeAttr(win, input, "onkeyup");
}

function testWiring(win, document, prefix, label) {
	const appsFilter = document.getElementById(prefix + "_apps_list_filter");
	const catsFilter = document.getElementById(prefix + "_cats_list_filter");
	const appsList = document.getElementById(prefix + "_apps_list");
	const catsList = document.getElementById(prefix + "_cats_list");
	const ifacesList = document.getElementById(prefix + "_ifaces_list");

	check(!!appsFilter && !!catsFilter && !!appsList && !!catsList && !!ifacesList,
		label + ": controlos apps/cats/ifaces presentes");

	const appsKeyup = appsFilter.getAttribute("onkeyup") || "";
	const catsKeyup = catsFilter.getAttribute("onkeyup") || "";
	check(appsKeyup.indexOf("l7filter(this,'" + prefix + "_apps_list')") >= 0,
		label + ": onkeyup apps filtro");
	check(catsKeyup.indexOf("l7filter(this,'" + prefix + "_cats_list')") >= 0,
		label + ": onkeyup cats filtro");

	const appsWrap = appsFilter.closest(".form-group") || appsFilter.parentElement;
	const selectVisible = findButtonByOnclick(appsWrap || document, "l7setChecks('" + prefix + "_apps_list', true, true)");
	const clearAll = findButtonByOnclick(appsWrap || document, "l7setChecks('" + prefix + "_apps_list', false, false)");
	check(!!selectVisible && !!clearAll, label + ": botoes selecionar visiveis/limpar apps");

	const ifacesWrap = ifacesList.previousElementSibling;
	const selectAllIf = findButtonByOnclick(ifacesWrap || document, "l7setChecks('" + prefix + "_ifaces_list', true)");
	const clearIf = findButtonByOnclick(ifacesWrap || document, "l7setChecks('" + prefix + "_ifaces_list', false)");
	check(!!selectAllIf && !!clearIf, label + ": botoes selecionar/limpar ifaces");

	filterViaOnkeyup(win, appsFilter, "http");
	check(isHiddenCheckbox(checkboxByValue(appsList, "YouTube")), label + ": onkeyup apps esconde YouTube");
	filterViaOnkeyup(win, appsFilter, "");
	check(!isHiddenCheckbox(checkboxByValue(appsList, "YouTube")), label + ": onkeyup apps vazio mostra todos");

	filterViaOnkeyup(win, catsFilter, "web");
	const mediaCb = checkboxByValue(catsList, "Media");
	check(!!mediaCb && isHiddenCheckbox(mediaCb), label + ": onkeyup cats filtra");
	filterViaOnkeyup(win, catsFilter, "");

	invokeAttr(win, clearIf, "onclick");
	check(
		Array.prototype.every.call(ifacesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
			return !cb.checked;
		}),
		label + ": onclick limpar ifaces"
	);
	invokeAttr(win, selectAllIf, "onclick");
	check(
		Array.prototype.every.call(ifacesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
			return cb.checked;
		}),
		label + ": onclick selecionar ifaces"
	);

	return {
		appsFilter,
		appsList,
		selectVisible,
		clearAll,
	};
}

function testHiddenPreserved(win, ctx, label) {
	const youtube = checkboxByValue(ctx.appsList, "YouTube");
	const http = checkboxByValue(ctx.appsList, "HTTP");
	check(!!youtube && youtube.checked, label + ": YouTube pre-seleccionado no fixture");

	filterViaOnkeyup(win, ctx.appsFilter, "http");
	check(isHiddenCheckbox(youtube), label + ": YouTube oculto com filtro http");

	invokeAttr(win, ctx.selectVisible, "onclick");
	check(youtube.checked, label + ": YouTube oculto permanece seleccionado");
	check(!!http && !isHiddenCheckbox(http) && http.checked,
		label + ": HTTP visivel fica seleccionado");
}

function testAllUncheckedThenVisible(win, ctx, label) {
	invokeAttr(win, ctx.clearAll, "onclick");
	check(!checkboxByValue(ctx.appsList, "YouTube").checked, label + ": limpar tudo desmarca YouTube");
	check(!checkboxByValue(ctx.appsList, "HTTP").checked, label + ": limpar tudo desmarca HTTP");

	filterViaOnkeyup(win, ctx.appsFilter, "http");
	invokeAttr(win, ctx.selectVisible, "onclick");
	check(!checkboxByValue(ctx.appsList, "YouTube").checked,
		label + ": lista vazia — oculto continua desmarcado");
	check(checkboxByValue(ctx.appsList, "HTTP").checked,
		label + ": lista vazia — visivel fica marcado");
}

const editHtml = runPhp(renderPhp, ["edit"]);
const newHtml = runPhp(renderPhp, ["new"]);

check(editHtml.includes('id="edit_apps_list"'), "edit HTML renderizado");
check(newHtml.includes('id="new_apps_list"'), "new HTML renderizado");

const edit = openFixture(editHtml);
const editCtx = testWiring(edit.win, edit.document, "edit", "edit");
testHiddenPreserved(edit.win, editCtx, "edit");
testAllUncheckedThenVisible(edit.win, editCtx, "edit");
edit.dom.window.close();

const nw = openFixture(newHtml);
testWiring(nw.win, nw.document, "new", "new");
const newCtx = {
	appsFilter: nw.document.getElementById("new_apps_list_filter"),
	appsList: nw.document.getElementById("new_apps_list"),
	selectVisible: findButtonByOnclick(
		nw.document.getElementById("new_apps_list_filter").closest(".form-group") || nw.document,
		"l7setChecks('new_apps_list', true, true)"
	),
	clearAll: findButtonByOnclick(
		nw.document.getElementById("new_apps_list_filter").closest(".form-group") || nw.document,
		"l7setChecks('new_apps_list', false, false)"
	),
};
testAllUncheckedThenVisible(nw.win, newCtx, "new");
nw.dom.window.close();

console.log("Runtime: jsdom via LAYER7_JSDOM ou require(\"jsdom\"); PHP via LAYER7_PHP ou php");
console.log("Limites: nao e browser, nao e pfSense, sem layout/tema/visual/CSRF/appliance");

if (fail) {
	console.error("SOME POLICIES FILTERS JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL POLICIES FILTERS JS TESTS PASSED");
process.exit(0);
