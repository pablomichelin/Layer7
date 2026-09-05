#!/usr/bin/env node
/**
 * V7 Eventos — jsdom: pause/refresh/clear, detalhe tecnico, buffer, XHR simulado.
 *
 *   LAYER7_JSDOM=... node tests/functional/test_events_js.js
 */
"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");
const { resolveJsdom } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const root = path.join(__dirname, "..", "..");
const eventsPath = path.join(root, "package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php");

let fail = 0;
function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function extractScript(src) {
	const m = src.match(/<script>\s*\(function\(\)[\s\S]*?\)\(\);\s*<\/script>/);
	if (!m) {
		throw new Error("script block ausente");
	}
	let script = m[0].replace(/^<script>\s*/, "").replace(/\s*<\/script>$/, "");
	script = script.replace(
		/var ajaxUrl\s*=\s*'layer7_events\.php\?ajax=1&source=<\?= rawurlencode\(\$source\); \?>&filter=<\?= rawurlencode\(\$filter\); \?>';/,
		"var ajaxUrl = 'layer7_events.php?ajax=1&source=events&filter=TikTok';"
	);
	script = script.replace(/<\?= json_encode\(l7_t\("[^"]+"\)\); \?>/g, function (tag) {
		const inner = tag.match(/l7_t\("([^"]+)"\)/);
		return JSON.stringify(inner ? inner[1] : "");
	});
	return script;
}

function buildHtml(extraList) {
	return '<!DOCTYPE html><html><body><div id="l7-events-root">' +
		'<input type="checkbox" id="l7-show-tech" />' +
		'<button type="button" id="l7-live-toggle">Pausar</button>' +
		'<button type="button" id="l7-live-refresh">Atualizar agora</button>' +
		'<button type="button" id="l7-live-clear">Limpar visualizacao</button>' +
		'<span id="l7-live-count"></span>' +
		'<div id="l7-live-view" class="list-group pre-scrollable"></div>' +
		(extraList || "") +
		"</div><script>" + extractScript(fs.readFileSync(eventsPath, "utf8")) + "</script></body></html>";
}

function installXhr(win, responses) {
	let calls = 0;
	const queue = responses.slice();
	function MockXHR() {
		this.readyState = 0;
		this.status = 0;
		this.responseText = "";
		this.onreadystatechange = null;
	}
	MockXHR.prototype.open = function () {
		calls++;
	};
	MockXHR.prototype.send = function () {
		const payload = queue.shift() || "[]";
		this.readyState = 4;
		this.status = 200;
		this.responseText = payload;
		if (typeof this.onreadystatechange === "function") {
			this.onreadystatechange();
		}
	};
	win.XMLHttpRequest = MockXHR;
	win.__l7XhrStats = { get calls() { return calls; } };
	return win.__l7XhrStats;
}

function openPage(html, responses) {
	const dom = new JSDOM(html, {
		url: "http://127.0.0.1/packages/layer7/layer7_events.php?source=events&filter=TikTok",
		runScripts: "dangerously",
		pretendToBeVisual: true,
		beforeParse: function (window) {
			installXhr(window, responses);
		},
	});
	const win = dom.window;
	return { win: win, doc: win.document, xhr: win.__l7XhrStats, storage: win.localStorage };
}

const sampleA = JSON.stringify([{
	when: "2026-08-31 12:00:00",
	title: "Trafego observado",
	summary: "Host v45.tiktokcdn.com",
	raw: "line-a",
	tone: "monitor",
}]);
const sampleB = JSON.stringify([{
	when: "2026-08-31 12:00:01",
	title: "Trafego bloqueado",
	summary: "Bloqueio TikTok",
	raw: "line-b",
	tone: "block",
}]);
const sampleOverlap = JSON.stringify([
	{ when: "t0", title: "A", summary: "s", raw: "line-a", tone: "info" },
	{ when: "t1", title: "B", summary: "s", raw: "line-b", tone: "block" },
]);

const staticDetails =
	'<details class="l7-event-raw-wrap"><summary>Detalhe</summary><pre>static-raw</pre></details>';

{
	const page = openPage(buildHtml(staticDetails), [sampleA, sampleB]);
	const doc = page.doc;
	check(doc.querySelectorAll(".l7-event-row").length >= 1, "live: primeira resposta renderiza linha");
	check(page.xhr.calls >= 1, "live: XHR inicial disparado");
}

{
	const page = openPage(buildHtml(staticDetails), [sampleA, sampleOverlap]);
	const doc = page.doc;
	const toggle = doc.getElementById("l7-live-toggle");
	const before = doc.querySelectorAll(".l7-event-row").length;
	toggle.click();
	check(toggle.textContent.indexOf("Retomar") !== -1, "pause: botao Retomar");
	const callsPaused = page.xhr.calls;
	doc.getElementById("l7-live-refresh").click();
	check(page.xhr.calls === callsPaused, "pause: refresh nao dispara XHR enquanto pausado");
	toggle.click();
	check(toggle.textContent.indexOf("Pausar") !== -1, "resume: botao Pausar");
	doc.getElementById("l7-live-refresh").click();
	check(page.xhr.calls > callsPaused, "resume: refresh dispara XHR");
	check(doc.querySelectorAll(".l7-event-row").length >= before, "merge: linhas acumuladas");
}

{
	const page = openPage(buildHtml(staticDetails), [sampleA]);
	const doc = page.doc;
	doc.getElementById("l7-live-clear").click();
	check(doc.querySelectorAll(".l7-event-row").length === 0, "clear: remove linhas live");
	check(doc.getElementById("l7-live-view").textContent.indexOf("Aguardando") !== -1,
		"clear: estado vazio");
	check(page.xhr && page.xhr.calls >= 1, "clear: fetch inicial ocorreu antes do clear");
}

{
	const page = openPage(buildHtml(staticDetails), [sampleA]);
	const doc = page.doc;
	const tech = doc.getElementById("l7-show-tech");
	tech.checked = true;
	tech.dispatchEvent(new page.win.Event("change"));
	const liveDetails = doc.querySelector("#l7-live-view .l7-event-raw-wrap");
	const staticWrap = doc.querySelector("details.l7-event-raw-wrap");
	check(!!liveDetails, "tech: linha live presente");
	check(!!staticWrap, "tech: raw estatico presente");
	if (liveDetails) {
		check(liveDetails.open === true, "tech: abre raw live");
	}
	check(!!staticWrap && staticWrap.open === true, "tech: abre raw estatico");
	check(page.storage.getItem("l7-events-show-tech") === "1", "tech: localStorage 1");
	tech.checked = false;
	tech.dispatchEvent(new page.win.Event("change"));
	if (liveDetails) {
		check(liveDetails.open === false, "tech: fecha raw live");
	}
	check(page.storage.getItem("l7-events-show-tech") === "0", "tech: localStorage 0");
}

{
	const big = [];
	for (let i = 0; i <= 505; i++) {
		big.push({ when: "t", title: "x", summary: "s", raw: "raw-" + i, tone: "info" });
	}
	const page = openPage(buildHtml(""), [JSON.stringify(big.slice(0, 10)), JSON.stringify(big)]);
	const rows = page.doc.querySelectorAll(".l7-event-row").length;
	check(rows <= 500, "buffer: maxLines 500 respeitado (" + rows + ")");
}

if (fail) {
	process.exit(1);
}
console.log("ALL EVENTS JS TESTS PASSED");
process.exit(0);
