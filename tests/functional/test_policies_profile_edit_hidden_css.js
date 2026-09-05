#!/usr/bin/env node
/**
 * V4-B2b — prova opcional de cascata CSS nativa (Bootstrap/pfSense pin via env).
 * Requer LAYER7_BOOTSTRAP_PIN_CSS (runner local só invoca com env definido).
 * LAYER7_PFSENSE_PIN_CSS opcional mas falha se informado e inexistente.
 * Nao e homologacao visual/leitor/teclado — apenas getComputedStyle isolado.
 */
"use strict";

const fs = require("fs");
const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-policies-edit/render-fixture.php");
const bsCssPath = process.env.LAYER7_BOOTSTRAP_PIN_CSS;
const pfCssPath = process.env.LAYER7_PFSENSE_PIN_CSS;

let fail = 0;

function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function isEditDomHidden(el) {
	return !el || el.hasAttribute("hidden") || el.classList.contains("hidden");
}

function makeBootstrapStub(win) {
	win.jQuery = function (sel) {
		const el = typeof sel === "string" ? win.document.querySelector(sel) : sel;
		return {
			modal: function (action) {
				if (!el) {
					return;
				}
				if (action === "show") {
					el.classList.add("in");
					el.style.display = "block";
				} else if (action === "hide") {
					el.classList.remove("in");
					el.style.display = "none";
				}
			},
		};
	};
	win.jQuery.fn = { modal: true };
}

function loadEditScripts(win, html) {
	[...html.matchAll(/<script>\s*([\s\S]*?)\s*<\/script>/g)].forEach(function (m) {
		const block = m[1];
		if (block.indexOf("l7ProfileEditData") >= 0 ||
			block.indexOf("function l7showProfileEditModal") >= 0 ||
			block.indexOf("function l7profileEditSetVisible") >= 0 ||
			block.indexOf("function l7confirmProfileEditSave") >= 0 ||
			block.indexOf("function l7filter") >= 0 ||
			block.indexOf("function l7clearEditFilter") >= 0) {
			win.eval(block);
		}
	});
}

function injectPinCss(doc) {
	if (!doc.head) {
		const head = doc.createElement("head");
		doc.documentElement.insertBefore(head, doc.body || null);
	}
	function addStyle(id, filePath) {
		const st = doc.createElement("style");
		st.id = id;
		st.textContent = fs.readFileSync(filePath, "utf8");
		doc.head.appendChild(st);
	}
	addStyle("l7-pin-bootstrap", bsCssPath);
	if (pfCssPath) {
		addStyle("l7-pin-pfsense", pfCssPath);
	}
}

function editDeleteBtn(doc) {
	const page = doc.getElementById("l7-profile-edit");
	if (page) {
		const btn = page.querySelector("#l7EditProfileDeleteBtn");
		if (btn) {
			return btn;
		}
	}
	return doc.getElementById("l7EditProfileDeleteBtn");
}

function probeGetScenario(scenario, expectHidden) {
	const html = runPhp(renderPhp, [scenario]);
	const dom = new JSDOM(html, {
		url: "http://127.0.0.1/layer7_policies.php",
		runScripts: "outside-only",
	});
	injectPinCss(dom.window.document);
	const btn = editDeleteBtn(dom.window.document);
	check(!!btn, scenario + ": botao apagar presente");
	if (!btn) {
		dom.window.close();
		return;
	}
	const hasHiddenAttr = btn.hasAttribute("hidden");
	const hasHiddenClass = btn.classList.contains("hidden");
	const display = dom.window.getComputedStyle(btn).display;
	check(hasHiddenAttr === expectHidden, scenario + ": atributo hidden=" + expectHidden);
	check(hasHiddenClass === expectHidden, scenario + ": classe hidden=" + expectHidden);
	if (expectHidden) {
		check(display === "none", scenario + ": computed display none (" + display + ")");
	} else {
		check(display === "inline-block", scenario + ": computed display inline-block (" + display + ")");
	}
	dom.window.close();
}

if (!bsCssPath) {
	console.error("FAIL: LAYER7_BOOTSTRAP_PIN_CSS nao definido");
	process.exit(1);
}
if (!fs.existsSync(bsCssPath)) {
	console.error("FAIL: LAYER7_BOOTSTRAP_PIN_CSS em falta: " + bsCssPath);
	process.exit(1);
}
if (pfCssPath && !fs.existsSync(pfCssPath)) {
	console.error("FAIL: LAYER7_PFSENSE_PIN_CSS em falta: " + pfCssPath);
	process.exit(1);
}

probeGetScenario("edit-new-get", true);
probeGetScenario("edit-factory-get", true);
probeGetScenario("edit-custom-get", false);

function probeModalScenario(html, profileId, isNew, expectHidden) {
	const dom = new JSDOM(html, {
		url: "http://127.0.0.1/layer7_policies.php?library=1",
		runScripts: "outside-only",
	});
	injectPinCss(dom.window.document);
	makeBootstrapStub(dom.window);
	loadEditScripts(dom.window, html);
	const win = dom.window;
	win.l7showProfileEditModal(profileId, isNew, { preventDefault: function () {} });
	const btn = win.document.getElementById("l7EditProfileDeleteBtn");
	const label = "modal-" + (profileId || "new") + (isNew ? "-new" : "");
	check(!!btn, label + ": botao apagar presente");
	if (!btn) {
		dom.window.close();
		return;
	}
	const display = win.getComputedStyle(btn).display;
	check(isEditDomHidden(btn) === expectHidden, label + ": hidden=" + expectHidden);
	if (expectHidden) {
		check(display === "none", label + ": computed display none (" + display + ")");
	} else {
		check(display === "inline-block", label + ": computed display inline-block (" + display + ")");
	}
	dom.window.close();
}

const libraryHtml = runPhp(renderPhp, ["library-links"]);
probeModalScenario(libraryHtml, "social", false, true);
probeModalScenario(libraryHtml, "c-harness-edit", false, false);
probeModalScenario(libraryHtml, "", true, true);

if (fail) {
	console.error("SOME POLICIES PROFILE EDIT HIDDEN CSS TESTS FAILED");
	process.exit(1);
}
console.log("ALL POLICIES PROFILE EDIT HIDDEN CSS TESTS PASSED");
process.exit(0);
