#!/usr/bin/env node
/**
 * V14 Blacklists — filtros/seleção visível + polling XHR stub (sem rede real).
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();


const renderPhp = path.join(__dirname, "harness-blacklists-view/render-parity.php");

function extractScriptFromHtml(html) {
	const start = html.indexOf("<script>");
	const end = html.indexOf("</script>", start);
	if (start === -1 || end === -1) return null;
	return html.slice(start + 8, end);
}

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
		url: "http://127.0.0.1/packages/layer7/layer7_blacklists.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
}

function installStubs(win) {
	let xhrOpen = "";
	let xhrSent = 0;
	win.XMLHttpRequest = function () {
		this.readyState = 0;
		this.status = 0;
		this.responseText = "";
		this.onreadystatechange = null;
	};
	win.XMLHttpRequest.prototype.open = function (method, url) {
		xhrOpen = url;
	};
	win.XMLHttpRequest.prototype.send = function () {
		xhrSent++;
		this.readyState = 4;
		this.status = 200;
		this.responseText = "INFO: update complete\n";
		if (typeof this.onreadystatechange === "function") {
			this.onreadystatechange();
		}
	};
	win.setInterval = function (fn) { fn(); return 99; };
	win.clearInterval = function () {};
	win.setTimeout = function (fn) { fn(); return 1; };
	win.eval(scriptBody);
	return { xhrOpen: function () { return xhrOpen; }, xhrSent: function () { return xhrSent; } };
}

const html = renderCand({ add_rule: true, with_rules: true });
const scriptBody = extractScriptFromHtml(html);
if (!scriptBody) {
	console.error("FAIL: bloco script ausente no render");
	process.exit(1);
}
const dom = openDom(html);
const win = dom.window;
const stubs = installStubs(win);

check(typeof win.filterRuleCats === "function", "js: filterRuleCats");
check(typeof win.toggleAllRuleCats === "function", "js: toggleAllRuleCats");
check(typeof win.pollDownloadLog === "function", "js: pollDownloadLog");

const filter = win.document.getElementById("rule_cat_filter");
const items = win.document.querySelectorAll("#rule_cats_wrap .rule-cat-item");
check(filter && items.length >= 2, "js: fixture categorias");
filter.value = "adult";
win.filterRuleCats();
let visible = 0;
for (let i = 0; i < items.length; i++) {
	if (items[i].style.display !== "none") visible++;
}
check(visible === 1, "js: filtro deixa 1 visivel");

filter.value = "";
win.filterRuleCats();
visible = 0;
for (let i = 0; i < items.length; i++) {
	if (items[i].style.display !== "none") visible++;
}
check(visible === items.length, "js: filtro limpo mostra todos");

items[0].style.display = "none";
const boxes = win.document.querySelectorAll('#rule_cats_wrap input[type="checkbox"]');
win.toggleAllRuleCats(true);
let checkedVisible = 0;
for (let i = 0; i < boxes.length; i++) {
	const item = boxes[i].closest(".rule-cat-item");
	if (item && item.style.display !== "none" && boxes[i].checked) checkedVisible++;
}
check(checkedVisible >= 1, "js: toggleAllRuleCats visiveis");

win.pollDownloadLog();
check(stubs.xhrSent() === 1, "js: pollDownloadLog XHR stub");
check(stubs.xhrOpen().indexOf("layer7_bl_ajax.php?action=progress") !== -1, "js: url progresso");

const log = win.document.getElementById("download_log");
check(log && log.value.indexOf("update complete") !== -1, "js: log preenchido");

check(scriptBody.indexOf("setInterval(pollDownloadLog, 2000)") !== -1, "js: intervalo 2000ms");
check(scriptBody.indexOf("300000") !== -1, "js: timeout 300000ms");

const delForm = win.document.querySelector('form [name="delete_rule"]');
check(delForm && delForm.closest("form").getAttribute("onsubmit").indexOf("confirm(") !== -1,
	"js: confirm delete rule");

if (fail) process.exit(1);
console.log("ALL BLACKLISTS JS TESTS PASSED");
process.exit(0);
