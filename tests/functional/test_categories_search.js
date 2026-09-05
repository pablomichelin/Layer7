#!/usr/bin/env node
/**
 * BG-174 / V3 — JS do catálogo nDPI no jsdom 26.1.0 (runtime isolado).
 * DOM = HTML renderizado da fonte real com stubs. Não é browser, pfSense
 * nem prova visual/layout/tema.
 *
 * Runtime: LAYER7_JSDOM + LAYER7_PHP, ou require("jsdom") e php no PATH.
 * Sem caminho fixo de máquina. Sem dependência no produto.
 * Recursos externos desligados. Enter/Space no navegador: pendentes.
 *
 *   LAYER7_JSDOM=... LAYER7_PHP=... node tests/functional/test_categories_search.js
 */
"use strict";

const fs = require("fs");
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const root = path.resolve(__dirname, "../..");
const phpPath = path.join(
	root,
	"package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_categories.php"
);
const renderPhp = path.join(__dirname, "harness-categories-view/render-fixture.php");
const phpSource = fs.readFileSync(phpPath, "utf8");
const scriptMatch = phpSource.match(/<script>\s*([\s\S]*?)\s*<\/script>/);
if (!scriptMatch) {
	console.error("FAIL: script do produto em falta");
	process.exit(1);
}
const productScript = scriptMatch[1];

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

check(!/\$\s*\(/.test(productScript), "script sem jQuery $");
check(!/jQuery/.test(productScript), "script sem jQuery");
check(productScript.includes("events.push(l7CatInit)"), "regista events.push");
check(productScript.includes("DOMContentLoaded"), "fallback DOMContentLoaded");

const rendered = runPhp(renderPhp);

check(rendered.includes("<!-- L7HC_HEAD -->"), "HTML da fonte real (head stub)");
check(rendered.includes('id="l7-cat-search"'), "HTML tem pesquisa");
check(rendered.includes("Facebook") && rendered.includes("WhatsApp"), "HTML tem fixture de busca");
check(/<details[\s\S]*data-category="social"/.test(rendered), "HTML tem details social");

function htmlWithoutProductScript(html) {
	return html.replace(/<script>[\s\S]*?<\/script>/, "<!-- L7HC_SCRIPT_STRIPPED -->");
}

function openDom() {
	return new JSDOM(htmlWithoutProductScript(rendered), {
		url: "http://127.0.0.1/packages/layer7/layer7_categories.php",
		runScripts: "outside-only",
		pretendToBeVisual: false,
	});
}

function isHidden(el) {
	return !el || el.hasAttribute("hidden") || el.classList.contains("hidden");
}

function visibleGroups(document) {
	return Array.prototype.filter.call(document.querySelectorAll("details[data-category]"), function (el) {
		return !isHidden(el);
	});
}

function row(document, proto) {
	return document.querySelector('[data-proto="' + proto + '"]');
}

function group(document, cat) {
	return document.querySelector('details[data-category="' + cat + '"]');
}

function typeSearch(window, value) {
	const input = window.document.getElementById("l7-cat-search");
	input.value = value;
	input.dispatchEvent(new window.Event("input", { bubbles: true }));
}

/* 1) dependencia ausente antes de foot: sem $; events[] ainda nao flushed */
const before = openDom();
const beforeWin = before.window;
delete beforeWin.$;
delete beforeWin.jQuery;
beforeWin.events = [];
const searchBefore = beforeWin.document.getElementById("l7-cat-search");
let boundBefore = false;
const addBefore = searchBefore.addEventListener.bind(searchBefore);
searchBefore.addEventListener = function (type, fn, opts) {
	if (type === "input") {
		boundBefore = true;
	}
	return addBefore(type, fn, opts);
};

let threwBefore = false;
try {
	beforeWin.eval(productScript);
} catch (e) {
	threwBefore = true;
	console.error(e);
}
check(typeof beforeWin.$ === "undefined", "antes de foot: $ ausente");
check(!threwBefore, "antes de foot: sem $ nao lanca");
check(boundBefore === false, "antes de foot: SEARCH_HANDLER_BOUND=false");
check(beforeWin.events.length === 1, "antes de foot: init apenas em events[]");

beforeWin.events[0]();
check(boundBefore === true, "apos events[]: SEARCH_HANDLER_BOUND=true");
before.window.close();

/* 2) segundo DOM: flush e semantica de busca / click */
const dom = openDom();
const win = dom.window;
delete win.$;
win.events = [];
win.eval(productScript);
check(win.events.length === 1, "segundo DOM: init em fila");
win.events[0]();

const document = win.document;
const search = document.getElementById("l7-cat-search");
const clearBtn = document.getElementById("l7-cat-clear");
const emptyMsg = document.getElementById("l7-cat-empty");
const social = group(document, "social");
const voip = group(document, "voip");
const streaming = group(document, "streaming");

check(!!search && !!clearBtn && !!emptyMsg, "controlos presentes no DOM renderizado");
check(!!social && !!voip && !!streaming, "tres grupos no DOM renderizado");
check(document.querySelector('label[for="l7-cat-search"]'), "label for corresponde ao id");

typeSearch(win, "");
check(visibleGroups(document).length === 3, "busca vazia: todos visiveis");
check(isHidden(emptyMsg), "busca vazia: sem aviso vazio");
check(!row(document, "facebook").classList.contains("info"), "busca vazia: sem highlight");

typeSearch(win, "face");
check(!isHidden(social), "busca app: social visivel");
check(isHidden(voip), "busca app: voip escondida");
check(isHidden(streaming), "busca app: streaming escondida");
check(!isHidden(row(document, "facebook")) && row(document, "facebook").classList.contains("info"), "busca app: facebook destacado");
check(isHidden(row(document, "tiktok")), "busca app: tiktok escondido");
check(social.open === true, "busca app: grupo expandido");

typeSearch(win, "voip");
check(!isHidden(voip), "busca categoria: voip visivel");
check(isHidden(social), "busca categoria: social escondida");
check(voip.open === true, "busca categoria: voip expandido");
check(!isHidden(row(document, "whatsapp")), "busca categoria: apps da categoria visiveis");

typeSearch(win, "zzzz-inexistente");
check(visibleGroups(document).length === 0, "sem resultados: grupos escondidos");
check(!isHidden(emptyMsg), "sem resultados: aviso visivel");

clearBtn.click();
check(search.value === "", "limpar click: campo vazio");
check(visibleGroups(document).length === 3, "limpar click: todos visiveis");
check(isHidden(emptyMsg), "limpar click: aviso escondido");

/* Contrato estrutural details/summary + click no jsdom.
 * Enter/Space reais no navegador continuam pendentes — nao simular. */
typeSearch(win, "");
check(social.tagName === "DETAILS", "estrutural: grupo e details");
check(!!social.querySelector("summary"), "estrutural: summary presente");
check(social.querySelector("summary").parentNode === social, "estrutural: summary filho directo");
social.open = false;
const summary = social.querySelector("summary");
summary.click();
check(social.open === true, "click summary: expande");
summary.click();
check(social.open === false, "click summary: recolhe");

console.log("Runtime: jsdom via LAYER7_JSDOM ou require(\"jsdom\"); PHP via LAYER7_PHP ou php");
console.log("Limites: nao e browser, nao e pfSense, sem layout/tema/visual, sem recursos externos");
console.log("PENDENTE: Enter/Space reais no navegador (jsdom nao prova teclado nativo)");

if (fail) {
	console.error("SOME CATEGORIES SEARCH JS TESTS FAILED");
	process.exit(1);
}
console.log("ALL CATEGORIES SEARCH JS TESTS PASSED");
process.exit(0);
