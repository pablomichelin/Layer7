#!/usr/bin/env node
/**
 * V4-B2a — Opções de perfil: FormData paridade (baseline V4-B2 vs candidato),
 * modal Bootstrap stub, fallback GET, grupos/VIP completos.
 */
"use strict";

const path = require("path");
const { resolveJsdom, runPhp } = require("./lib/layer7-test-runtime");
const { JSDOM, VirtualConsole } = resolveJsdom();

const renderPhp = path.join(__dirname, "harness-policies-options/render-fixture.php");
const baselinePhp = path.join(__dirname, "harness-policies-options/render-baseline-fixture.php");

const HOSTILE_POST = {
	vip_hosts: "192.0.2.1\n\"></textarea><script>alert(1)</script>",
	vip_cidrs: '10.0.0.0/24 & "cidr"',
	src_cidrs: "10.1.0.0/24\n<script>x</script>",
	exc_cidrs: "192.168.1.99 & exclude",
};
const HOSTILE_GROUP_ID = 'g"><script>alert(1)</script>';
const HOSTILE_GROUP_LABEL = 'Grupo & "VIP" <test>';
const HOSTILE_IFACE_LAN = 'LAN & "quotes" <iface>';
const HOSTILE_IFACE_OPT = "OPT1</script>";
const HOSTILE_PROFILE_NAME = 'Perfil <img src=x onerror=alert(1)> & "quotes"';

let fail = 0;
const jsdomUnexpected = [];

function check(cond, name) {
	if (cond) {
		console.log("PASS: " + name);
	} else {
		console.log("FAIL: " + name);
		fail = 1;
	}
}

function extractScriptBlocks(html) {
	return [...html.matchAll(/<script>\s*([\s\S]*?)\s*<\/script>/g)].map(function (m) {
		return m[1];
	});
}

function stripScripts(html) {
	return html.replace(/<script>[\s\S]*?<\/script>/g, "<!-- L7PO_SCRIPT_STRIPPED -->");
}

function optionsForm(document) {
	const modal = document.getElementById("l7ProfileModal");
	if (modal) {
		const f = modal.querySelector("form");
		if (f) {
			return f;
		}
	}
	const page = document.getElementById("l7-profile-options");
	if (page) {
		return page.querySelector("form");
	}
	return null;
}

function fdSnapshot(form, submitter) {
	const win = form.ownerDocument.defaultView;
	const fd = new win.FormData(form, submitter || undefined);
	const out = {};
	fd.forEach(function (val, key) {
		if (out[key] === undefined) {
			out[key] = val;
		} else if (Array.isArray(out[key])) {
			out[key].push(val);
		} else {
			out[key] = [out[key], val];
		}
	});
	const keys = Object.keys(out).sort();
	const norm = {};
	keys.forEach(function (k) {
		norm[k] = out[k];
	});
	return { keys: keys, entries: norm };
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
					win.document.body.classList.add("modal-open");
				} else if (action === "hide") {
					el.classList.remove("in");
					el.style.display = "none";
					win.document.body.classList.remove("modal-open");
				}
			},
		};
	};
	win.jQuery.fn = { modal: true };
}

function loadOptionsScripts(win, html) {
	extractScriptBlocks(html)
		.filter(function (b) {
			return b.indexOf("function l7showProfileModal") >= 0 ||
				b.indexOf("function l7hideProfileModal") >= 0 ||
				b.indexOf("function l7toggleProfileDraft") >= 0 ||
				b.indexOf("function l7filterProfileGrid") >= 0 ||
				b.indexOf("function l7initProfileGroups") >= 0 ||
				b.indexOf("function l7collectProfileDraft") >= 0;
		})
		.forEach(function (block) {
			win.eval(block);
		});
}

function openInspectDom(html, pageUrl) {
	const vc = new VirtualConsole();
	vc.on("jsdomError", function (err) {
		jsdomUnexpected.push(err);
	});
	return new JSDOM(html, {
		url: pageUrl || "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1",
		runScripts: "outside-only",
		pretendToBeVisual: false,
		virtualConsole: vc,
	});
}

function inspectRegionNoInjectables(region, label) {
	if (!region) {
		check(false, label + ": regiao ausente");
		return;
	}
	check(region.querySelectorAll("script").length === 0,
		label + ": sem elemento script na regiao");
	check(region.querySelectorAll("img").length === 0,
		label + ": sem elemento img na regiao");
	region.querySelectorAll("*").forEach(function (el) {
		if (el.hasAttribute && el.hasAttribute("onerror")) {
			check(false, label + ": onerror injectado em " + el.tagName);
		}
	});
}

function installNetworkCounters(win) {
	const counters = { fetch: 0, submit: 0, requestSubmit: 0 };
	win.fetch = function () {
		counters.fetch++;
		return Promise.resolve({
			ok: true,
			json: function () { return Promise.resolve({}); },
		});
	};
	const proto = win.HTMLFormElement.prototype;
	proto.submit = function () { counters.submit++; };
	proto.requestSubmit = function () { counters.requestSubmit++; };
	return counters;
}

function entriesWithoutProfileId(snap) {
	const entries = Object.assign({}, snap.entries);
	delete entries.profile_id;
	const keys = Object.keys(entries).sort();
	const norm = {};
	keys.forEach(function (k) {
		norm[k] = entries[k];
	});
	return { keys: keys, entries: norm };
}

function profileIdFromOptionsLink(link) {
	const href = link.getAttribute("href") || "";
	const m = href.match(/profile_options=([^&#"]+)/);
	return m ? decodeURIComponent(m[1]) : "";
}

function checkboxLabelText(form, name, value) {
	const all = form.querySelectorAll('input[name="' + name + '"]');
	for (let i = 0; i < all.length; i++) {
		if (all[i].value === value) {
			let p = all[i].parentElement;
			while (p && p !== form) {
				if (p.tagName === "LABEL") {
					return p.textContent || "";
				}
				p = p.parentElement;
			}
		}
	}
	return "";
}

function openFixture(html, pageUrl, setupWin, withBootstrap) {
	const vc = new VirtualConsole();
	vc.on("jsdomError", function (err) {
		jsdomUnexpected.push(err);
	});
	const dom = new JSDOM(stripScripts(html), {
		url: pageUrl || "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1",
		runScripts: "outside-only",
		pretendToBeVisual: false,
		virtualConsole: vc,
	});
	const win = dom.window;
	if (!win.Element.prototype.scrollIntoView) {
		win.Element.prototype.scrollIntoView = function () {};
	}
	if (withBootstrap !== false) {
		makeBootstrapStub(win);
	}
	if (typeof setupWin === "function") {
		setupWin(win, dom);
	}
	loadOptionsScripts(win, html);
	return { win: win, document: win.document, dom: dom };
}

function invokeAttr(win, el, attrName, ev) {
	const code = el.getAttribute(attrName);
	if (!code) {
		return false;
	}
	const evt = ev || {
		prevented: false,
		target: el,
		currentTarget: el,
		preventDefault: function () { this.prevented = true; },
	};
	const fn = new win.Function("event", code);
	const ret = fn.call(el, evt);
	return { ret: ret, ev: evt };
}

function invokeShow(win, profileId, profileName, ev) {
	if (!ev) {
		ev = { prevented: false, preventDefault: function () { this.prevented = true; } };
	}
	const ret = win.l7showProfileModal(profileId, profileName, ev);
	return { ret: ret, ev: ev };
}

function assertModalOpenState(modal, label) {
	check(modal.classList.contains("in"), label + ": classe in");
	check(modal.getAttribute("aria-hidden") !== "true", label + ": sem aria-hidden=true");
}

function compareFormData(label, baseSnap, candSnap) {
	check(baseSnap.keys.join("|") === candSnap.keys.join("|"),
		label + ": mesmas chaves FormData");
	baseSnap.keys.forEach(function (k) {
		const b = baseSnap.entries[k];
		const c = candSnap.entries[k];
		const bs = Array.isArray(b) ? b.slice().sort().join(",") : String(b);
		const cs = Array.isArray(c) ? c.slice().sort().join(",") : String(c);
		check(bs === cs, label + ": valor " + k);
	});
}

function fillForm(form, profileId) {
	const pid = form.querySelector('[name="profile_id"]');
	if (pid) {
		pid.value = profileId;
	}
	form.querySelectorAll('input[name="profile_ifaces[]"]').forEach(function (inp) {
		inp.checked = inp.value === "lan";
	});
	form.querySelectorAll('input[name="profile_groups[]"]').forEach(function (inp) {
		inp.checked = inp.value === "lab";
	});
	form.querySelectorAll('input[name="profile_vip_groups[]"]').forEach(function (inp) {
		inp.checked = inp.value === "vip";
	});
	const srcC = form.querySelector('[name="profile_src_cidrs"]');
	if (srcC) {
		srcC.value = "192.168.10.0/24";
	}
	const excC = form.querySelector('[name="profile_src_exclude_cidrs"]');
	if (excC) {
		excC.value = "192.168.1.99";
	}
	form.querySelectorAll('input[name="profile_src_exclude_groups[]"]').forEach(function (inp) {
		inp.checked = inp.value === "vip";
	});
	const actSel = form.querySelector('[name="profile_action"]');
	if (actSel) {
		actSel.value = "monitor";
	}
}

function fillAllForm(form) {
	form.querySelectorAll('input[name="profile_ifaces[]"]').forEach(function (inp) {
		inp.checked = true;
	});
	form.querySelectorAll('input[name="profile_groups[]"]').forEach(function (inp) {
		inp.checked = true;
	});
	form.querySelectorAll('input[name="profile_vip_groups[]"]').forEach(function (inp) {
		inp.checked = true;
	});
	form.querySelectorAll('input[name="profile_src_exclude_groups[]"]').forEach(function (inp) {
		inp.checked = true;
	});
	const vipH = form.querySelector('[name="profile_vip_hosts"]');
	if (vipH) {
		vipH.value = "192.0.2.55";
	}
	const vipC = form.querySelector('[name="profile_vip_cidrs"]');
	if (vipC) {
		vipC.value = "10.9.0.0/24";
	}
	const srcC = form.querySelector('[name="profile_src_cidrs"]');
	if (srcC) {
		srcC.value = "192.168.10.0/24";
	}
	const excC = form.querySelector('[name="profile_src_exclude_cidrs"]');
	if (excC) {
		excC.value = "192.168.1.99";
	}
	const actSel = form.querySelector('[name="profile_action"]');
	if (actSel) {
		actSel.value = "allow";
	}
}

function assertLabelOrAncestor(form, input) {
	const id = input.id;
	if (id) {
		const lbl = form.querySelector('label[for="' + id + '"]');
		if (lbl) {
			return true;
		}
	}
	let p = input.parentElement;
	while (p && p !== form) {
		if (p.tagName === "LABEL") {
			return true;
		}
		p = p.parentElement;
	}
	return false;
}

function assertUniqueIds(root) {
	const seen = {};
	const dups = [];
	root.querySelectorAll("[id]").forEach(function (el) {
		const id = el.id;
		if (!id) {
			return;
		}
		if (seen[id]) {
			dups.push(id);
		}
		seen[id] = true;
	});
	return dups;
}

function parityScenario(scenarioId, label) {
	const candHtml = runPhp(renderPhp, [scenarioId]);
	const baseHtml = runPhp(baselinePhp, [scenarioId]);
	const candCtx = openFixture(candHtml);
	const baseCtx = openFixture(baseHtml);
	const candForm = optionsForm(candCtx.document);
	const baseForm = optionsForm(baseCtx.document);
	check(!!candForm && !!baseForm, label + ": forms presentes");
	if (candForm && baseForm) {
		compareFormData(label + " defaults", fdSnapshot(baseForm), fdSnapshot(candForm));
		fillAllForm(candForm);
		fillAllForm(baseForm);
		const submitBtn = candForm.querySelector('button[type="submit"]');
		compareFormData(label + " preenchido", fdSnapshot(baseForm, baseForm.querySelector('button[type="submit"]')),
			fdSnapshot(candForm, submitBtn));
	}
	candCtx.dom.window.close();
	baseCtx.dom.window.close();
}

function waitModalEvent(win, modal, eventName, action) {
	return new Promise(function (resolve, reject) {
		const timer = win.setTimeout(function () {
			reject(new Error("timeout " + eventName));
		}, 5000);
		win.jQuery(modal).one(eventName, function () {
			win.clearTimeout(timer);
			resolve();
		});
		action();
	});
}

async function proveBootstrapPinModal() {
	const jqPath = process.env.LAYER7_JQUERY_PIN_JS;
	const bsPath = process.env.LAYER7_BOOTSTRAP_PIN_JS;
	if (!jqPath || !bsPath) {
		console.log("SKIP: prova Bootstrap pin (defina LAYER7_JQUERY_PIN_JS e LAYER7_BOOTSTRAP_PIN_JS)");
		return;
	}
	const fs = require("fs");
	const vm = require("vm");
	const pinHtml = runPhp(renderPhp, ["groups2-vip"]);
	const pinVc = new VirtualConsole();
	const pinVcErrors = [];
	pinVc.on("jsdomError", function (err) {
		pinVcErrors.push(err);
	});
	pinVc.on("error", function (err) {
		pinVcErrors.push(err);
	});

	const pinDom = new JSDOM(pinHtml, {
		url: "http://127.0.0.1/packages/layer7/layer7_policies.php?library=1",
		runScripts: "outside-only",
		pretendToBeVisual: true,
		virtualConsole: pinVc,
	});
	const pinWin = pinDom.window;
	if (!pinWin.Element.prototype.scrollIntoView) {
		pinWin.Element.prototype.scrollIntoView = function () {};
	}
	const pinNet = installNetworkCounters(pinWin);
	const pinCtx = pinDom.getInternalVMContext();
	vm.runInContext(fs.readFileSync(jqPath, "utf8"), pinCtx, { filename: jqPath });
	vm.runInContext(fs.readFileSync(bsPath, "utf8"), pinCtx, { filename: bsPath });
	extractScriptBlocks(pinHtml)
		.filter(function (b) {
			return b.indexOf("function l7showProfileModal") >= 0 ||
				b.indexOf("function l7hideProfileModal") >= 0;
		})
		.forEach(function (block) {
			pinWin.eval(block);
		});

	const pinModal = pinWin.document.getElementById("l7ProfileModal");
	check(!!pinModal, "pin bootstrap: modal no HTML completo");
	check(!/id="l7ProfileModal"[^>]*aria-hidden="true"/.test(pinHtml),
		"pin bootstrap: HTML completo sem aria-hidden estatico");

	const showEv = {
		prevented: false,
		preventDefault: function () { this.prevented = true; },
	};
	try {
		await waitModalEvent(pinWin, pinModal, "shown.bs.modal", function () {
			pinWin.l7showProfileModal("social", "Social", showEv);
		});
	} catch (e) {
		check(false, "pin bootstrap: shown.bs.modal (" + (e.message || e) + ")");
	}
	check(pinModal.classList.contains("in"), "pin bootstrap: classe in apos shown.bs.modal");
	check(pinModal.getAttribute("aria-hidden") !== "true",
		"pin bootstrap: sem aria-hidden=true apos show");
	check(showEv.prevented === true, "pin bootstrap: show preventDefault");

	try {
		await waitModalEvent(pinWin, pinModal, "hidden.bs.modal", function () {
			pinWin.l7hideProfileModal();
		});
	} catch (e) {
		check(false, "pin bootstrap: hidden.bs.modal (" + (e.message || e) + ")");
	}
	check(!pinModal.classList.contains("in"), "pin bootstrap: sem in apos hidden.bs.modal");
	check(pinModal.getAttribute("aria-hidden") !== "true",
		"pin bootstrap: sem aria-hidden=true apos hide");
	check(pinNet.fetch === 0, "pin bootstrap: fetch 0");
	check(pinNet.submit === 0, "pin bootstrap: submit 0");
	check(pinNet.requestSubmit === 0, "pin bootstrap: requestSubmit 0");
	check(pinVcErrors.length === 0,
		"pin bootstrap: VirtualConsole limpo (" + pinVcErrors.length + ")");
	pinDom.window.close();
}

(async function () {
	const candHtml = runPhp(renderPhp, ["groups2-vip"]);
	const baseHtml = runPhp(baselinePhp, ["groups2-vip"]);
	const getHtml = runPhp(renderPhp, ["options-get"]);
	const postErrHtml = runPhp(renderPhp, ["post-error"]);
	const postErrFullHtml = runPhp(renderPhp, ["post-error-full"]);
	const postErrClearedHtml = runPhp(renderPhp, ["post-error-cleared-vip"]);
	const optionsLimitHtml = runPhp(renderPhp, ["options-limit24"]);
	const optionsEmptyHtml = runPhp(renderPhp, ["options-empty-catalog"]);
	const optionsHiddenHtml = runPhp(renderPhp, ["options-hidden"]);
	const optionsXssHtml = runPhp(renderPhp, ["options-xss"]);
	const optionsXssGetHtml = runPhp(renderPhp, ["options-xss-get"]);
	const libraryDraftHtml = runPhp(renderPhp, ["library-draft"]);

	check(candHtml.includes('class="modal fade" id="l7ProfileModal"'), "HTML: bootstrap modal");
	check(!/id="l7ProfileModal"[^>]*aria-hidden="true"/.test(candHtml),
		"HTML: modal sem aria-hidden estatico");
	check(candHtml.includes('name="profile_groups[]"'), "HTML: profile_groups presente");
	check(candHtml.includes('name="profile_vip_groups[]"'), "HTML: profile_vip_groups presente");
	check(candHtml.includes('name="profile_src_exclude_groups[]"'),
		"HTML: profile_src_exclude_groups presente");

	const candCtx = openFixture(candHtml);
	const baseCtx = openFixture(baseHtml);
	const candForm = optionsForm(candCtx.document);
	const baseForm = optionsForm(baseCtx.document);
	check(!!candForm && !!baseForm, "forms: modal presente baseline/candidato");

	const candDef = fdSnapshot(candForm);
	const baseDef = fdSnapshot(baseForm);
	compareFormData("defaults", baseDef, candDef);
	check(candDef.entries.add_profile_policy === "1", "defaults: add_profile_policy=1");
	check(candDef.entries.profile_action === "block", "defaults: action block");
	check(!Object.prototype.hasOwnProperty.call(candDef.entries, "save"),
		"defaults: sem Save extra");

	invokeShow(candCtx.win, "facebook", "Facebook");
	check(candCtx.document.getElementById("l7ProfileId").value === "facebook",
		"modal: profile_id apos abrir A");
	const afterA = fdSnapshot(candForm);
	check(afterA.entries.profile_id === "facebook", "modal: FormData profile_id A");

	const optLink = candCtx.document.querySelector('a[href*="profile_options="]');
	check(!!optLink, "link: ancora opcoes presente");
	check(optLink.getAttribute("href").indexOf("profile_options=") >= 0,
		"link: href funcional profile_options");

	const prog = invokeShow(candCtx.win, "social", "Social");
	check(prog.ret === false, "progressive: com Bootstrap retorna false");
	check(!!prog.ev.prevented, "progressive: com Bootstrap preventDefault");
	assertModalOpenState(candCtx.document.getElementById("l7ProfileModal"), "modal");

	const noJqCtx = openFixture(candHtml, undefined, undefined, false);
	const noJq = invokeShow(noJqCtx.win, "social", "Social");
	check(noJq.ret === true, "fallback: sem jQuery retorna true");
	check(!noJq.ev.prevented, "fallback: sem jQuery sem preventDefault");
	noJqCtx.dom.window.close();

	const noModalCtx = openFixture(candHtml, undefined, function (win) {
		win.jQuery = function () {
			return { modal: undefined };
		};
		win.jQuery.fn = {};
	}, false);
	const noModal = invokeShow(noModalCtx.win, "social", "Social");
	check(noModal.ret === true, "fallback: sem plugin modal retorna true");
	check(!noModal.ev.prevented, "fallback: sem plugin modal sem preventDefault");
	noModalCtx.dom.window.close();

	const noDomCtx = openFixture(candHtml);
	noDomCtx.document.getElementById("l7ProfileModal").remove();
	const noDom = invokeShow(noDomCtx.win, "social", "Social");
	check(noDom.ret === true, "fallback: sem modal DOM retorna true");
	check(!noDom.ev.prevented, "fallback: sem modal DOM sem preventDefault");
	noDomCtx.dom.window.close();

	fillForm(candForm, "facebook");
	fillForm(baseForm, "facebook");
	const afterFill = fdSnapshot(candForm);

	const submitBtn = candForm.querySelector('button[type="submit"]');
	const filledCand = fdSnapshot(candForm, submitBtn);
	const filledBase = fdSnapshot(baseForm, baseForm.querySelector('button[type="submit"]'));
	compareFormData("preenchido", filledBase, filledCand);
	check(!filledCand.keys.some(function (k) { return k === "" || k === "submit"; }),
		"preenchido: submit sem name no FormData");

	invokeShow(candCtx.win, "youtube", "YouTube");
	const afterB = fdSnapshot(candForm);
	check(afterB.entries.profile_id === "youtube", "modal: profile_id B apos abrir B");
	check(afterB.entries.profile_action === "monitor", "modal: accao preservada apos B");
	check(JSON.stringify(afterB.entries["profile_ifaces[]"]) === JSON.stringify(afterFill.entries["profile_ifaces[]"]),
		"modal: ifaces preservadas apos B");

	candCtx.dom.window.close();
	baseCtx.dom.window.close();

	parityScenario("groups0", "groups0");
	parityScenario("groups16-vip", "groups16");
	const g2eCand = runPhp(renderPhp, ["groups2-vip-empty"]);
	const g2eBase = runPhp(baselinePhp, ["groups2-vip-empty"]);
	const g2eCtx = openFixture(g2eCand);
	const g2eBaseCtx = openFixture(g2eBase);
	const g2eForm = optionsForm(g2eCtx.document);
	const g2eBaseForm = optionsForm(g2eBaseCtx.document);
	check(!!g2eForm && !!g2eBaseForm, "groups2-vip-empty: forms presentes");
	compareFormData("groups2-vip-empty defaults", fdSnapshot(g2eBaseForm), fdSnapshot(g2eForm));
	const g2eSnap = fdSnapshot(g2eForm);
	check(!Object.prototype.hasOwnProperty.call(g2eSnap.entries, "profile_vip_groups[]"),
		"groups2-vip-empty: sem vip grupos no FormData");
	check((g2eSnap.entries.profile_vip_hosts || "") === "",
		"groups2-vip-empty: vip hosts vazio no FormData");
	g2eCtx.dom.window.close();
	g2eBaseCtx.dom.window.close();

	const getCtx = openFixture(
		getHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php?profile_options=social"
	);
	const getForm = optionsForm(getCtx.document);
	check(!!getForm, "GET: form dedicado");
	check(getForm.getAttribute("action").indexOf("#l7-policies") >= 0, "GET: action #l7-policies");
	check(getHtml.includes('href="layer7_policies.php?library=1#l7-profiles"'),
		"GET: retorno biblioteca");
	getCtx.dom.window.close();

	check(optionsLimitHtml.includes("Limite de 24 politicas atingido. A biblioteca"),
		"GET limit24: mensagem");
	check(!optionsLimitHtml.includes("Catalogo de perfis indisponivel"),
		"GET limit24: sem catalogo vazio");
	check(!optionsLimitHtml.includes('name="add_profile_policy"'),
		"GET limit24: sem form");

	check(optionsEmptyHtml.includes("Catalogo de perfis indisponivel"),
		"GET empty: mensagem catalogo");
	check(!optionsEmptyHtml.includes("Limite de 24 politicas atingido. A biblioteca"),
		"GET empty: sem limite24");
	check(!optionsEmptyHtml.includes('name="add_profile_policy"'),
		"GET empty: sem form");

	check(optionsHiddenHtml.includes('id="l7-profile-options"'),
		"GET hidden: vista dedicada");
	check(optionsHiddenHtml.includes('value="c-hidden-active"'),
		"GET hidden: profile_id oculto");

	check(postErrHtml.includes("Perfil nao encontrado"), "POST erro: mensagem");
	check(postErrHtml.includes("192.0.2.1"), "POST erro: valor repost");
	check(postErrHtml.includes('id="l7-profile-options"'), "POST erro: vista dedicada");

	const peFullCtx = openFixture(postErrFullHtml);
	const peFullForm = optionsForm(peFullCtx.document);
	check(!!peFullForm, "POST erro full: form presente");
	const peFullSnap = fdSnapshot(peFullForm);
	check(peFullSnap.entries.profile_action === "allow", "POST erro full: action allow");
	check(peFullSnap.entries.profile_id === "invalid", "POST erro full: profile_id");
	const ifaces = peFullSnap.entries["profile_ifaces[]"];
	check(Array.isArray(ifaces) && ifaces.indexOf("lan") >= 0 && ifaces.indexOf("opt1") >= 0,
		"POST erro full: ifaces FormData");
	check(peFullSnap.entries["profile_groups[]"] === "lab", "POST erro full: grupo lab");
	check(peFullSnap.entries["profile_vip_groups[]"] === "vip", "POST erro full: vip grupo");
	check(peFullSnap.entries["profile_src_exclude_groups[]"] === "vip",
		"POST erro full: exclude grupo");
	check(peFullSnap.entries.profile_vip_hosts === HOSTILE_POST.vip_hosts,
		"POST erro full: vip hosts exacto");
	check(peFullSnap.entries.profile_vip_cidrs === HOSTILE_POST.vip_cidrs,
		"POST erro full: vip cidrs exacto");
	check(peFullSnap.entries.profile_src_cidrs === HOSTILE_POST.src_cidrs,
		"POST erro full: src cidrs exacto");
	check(peFullSnap.entries.profile_src_exclude_cidrs === HOSTILE_POST.exc_cidrs,
		"POST erro full: exclude cidrs exacto");
	peFullCtx.dom.window.close();

	const peClrCtx = openFixture(postErrClearedHtml);
	const peClrForm = optionsForm(peClrCtx.document);
	const peClrSnap = fdSnapshot(peClrForm);
	check(!Object.prototype.hasOwnProperty.call(peClrSnap.entries, "profile_ifaces[]"),
		"POST cleared: sem ifaces FormData");
	check(!Object.prototype.hasOwnProperty.call(peClrSnap.entries, "profile_groups[]"),
		"POST cleared: sem profile_groups FormData");
	check(!Object.prototype.hasOwnProperty.call(peClrSnap.entries, "profile_vip_groups[]"),
		"POST cleared: sem vip grupos FormData");
	check(!Object.prototype.hasOwnProperty.call(peClrSnap.entries, "profile_src_exclude_groups[]"),
		"POST cleared: sem exclude grupos FormData");
	check((peClrSnap.entries.profile_vip_hosts || "") === "",
		"POST cleared: vip hosts vazio FormData");
	check((peClrSnap.entries.profile_vip_cidrs || "") === "",
		"POST cleared: vip cidrs vazio FormData");
	check((peClrSnap.entries.profile_src_cidrs || "") === "",
		"POST cleared: src cidrs vazio FormData");
	check((peClrSnap.entries.profile_src_exclude_cidrs || "") === "",
		"POST cleared: exclude cidrs vazio FormData");
	peClrCtx.dom.window.close();

	const xssInspect = openInspectDom(optionsXssHtml);
	const xssDoc = xssInspect.window.document;
	const xssModal = xssDoc.getElementById("l7ProfileModal");
	check(!!xssModal, "xss inspect: modal presente no HTML original");
	const xssFormInspect = optionsForm(xssDoc);
	check(!!xssFormInspect, "xss inspect: form presente");
	inspectRegionNoInjectables(xssFormInspect, "xss inspect form");
	inspectRegionNoInjectables(xssModal.querySelector(".modal-header"), "xss inspect titulo modal");
	const hostileBtn = xssDoc.querySelector('[data-profile-id="xss-harness"]');
	const hostileRow = hostileBtn ? hostileBtn.closest("tr") : null;
	check(!!hostileRow, "xss inspect: linha perfil hostil");
	check((hostileRow.textContent || "").indexOf(HOSTILE_PROFILE_NAME) >= 0,
		"xss inspect: nome perfil hostil visivel na biblioteca");
	const hostileGroupCb = Array.prototype.find.call(
		xssFormInspect.querySelectorAll('input[name="profile_groups[]"]'),
		function (inp) { return inp.value === HOSTILE_GROUP_ID; }
	);
	check(!!hostileGroupCb, "xss inspect: checkbox grupo hostil");
	check(checkboxLabelText(xssFormInspect, "profile_groups[]", HOSTILE_GROUP_ID).indexOf(HOSTILE_GROUP_LABEL) >= 0,
		"xss inspect: label grupo hostil");
	const lanIface = Array.prototype.find.call(
		xssFormInspect.querySelectorAll('input[name="profile_ifaces[]"]'),
		function (inp) { return inp.value === "lan"; }
	);
	check(!!lanIface, "xss inspect: checkbox iface lan");
	check(checkboxLabelText(xssFormInspect, "profile_ifaces[]", "lan").indexOf(HOSTILE_IFACE_LAN) >= 0,
		"xss inspect: label iface lan hostil");
	check(checkboxLabelText(xssFormInspect, "profile_ifaces[]", "opt1").indexOf(HOSTILE_IFACE_OPT) >= 0,
		"xss inspect: label iface opt1 hostil");
	xssInspect.window.close();

	const xssGetInspect = openInspectDom(
		optionsXssGetHtml,
		"http://127.0.0.1/packages/layer7/layer7_policies.php?profile_options=xss-harness"
	);
	const xssGetDoc = xssGetInspect.window.document;
	const xssGetPanel = xssGetDoc.getElementById("l7-profile-options");
	check(!!xssGetPanel, "xss inspect GET: painel dedicado");
	inspectRegionNoInjectables(xssGetPanel, "xss inspect GET painel");
	check((xssGetPanel.textContent || "").indexOf(HOSTILE_PROFILE_NAME) >= 0,
		"xss inspect GET: nome perfil hostil no titulo");
	check(xssModal.getAttribute("aria-hidden") !== "true",
		"xss inspect: modal HTML sem aria-hidden=true");
	xssGetInspect.window.close();

	const xssCtx = openFixture(optionsXssHtml);
	const xssForm = optionsForm(xssCtx.document);
	check(!!xssForm, "xss: form presente");
	xssForm.querySelectorAll("input, select, textarea").forEach(function (inp) {
		const type = (inp.getAttribute("type") || "").toLowerCase();
		if (type === "hidden") {
			return;
		}
		check(assertLabelOrAncestor(xssForm, inp),
			"xss: label visivel " + (inp.name || inp.id));
	});
	check(assertUniqueIds(xssForm).length === 0,
		"xss: ids unicos (" + assertUniqueIds(xssForm).join(",") + ")");
	const xssLink = xssCtx.document.querySelector('a[onclick*="l7showProfileModal"][href*="profile_options="]');
	check(!!xssLink, "xss: link opcoes perfil hostil");
	const xssOpen = invokeAttr(xssCtx.win, xssLink, "onclick");
	check(xssOpen.ret === false, "xss: onclick abre modal");
	assertModalOpenState(xssCtx.document.getElementById("l7ProfileModal"), "xss onclick");
	check((xssCtx.document.getElementById("l7ProfileModalTitle").textContent || "").indexOf(HOSTILE_PROFILE_NAME) >= 0,
		"xss: nome perfil hostil no titulo modal");
	xssCtx.dom.window.close();

	let netCounters = null;
	const libCtx = openFixture(libraryDraftHtml, undefined, function (win) {
		netCounters = installNetworkCounters(win);
	});
	const search = libCtx.document.getElementById("l7ProfileSearch");
	check(!!search, "library: filtro presente");
	search.value = "face";
	if (typeof libCtx.win.l7filterProfileGrid === "function") {
		libCtx.win.l7filterProfileGrid();
	}
	const draftBtn = libCtx.document.querySelector(
		'button[data-profile-id][onclick*="l7toggleProfileDraft"]'
	);
	check(!!draftBtn, "library: botao draft presente");
	const savedDesired = draftBtn.getAttribute("data-saved");
	invokeAttr(libCtx.win, draftBtn, "onclick");
	const draft = libCtx.win.l7collectProfileDraft();
	const draftCount = draft.enableIds.length + draft.disableIds.length;
	check(draftCount >= 1, "library: draft contagem >=1 (" + draftCount + ")");
	check(draftBtn.getAttribute("data-desired") !== savedDesired,
		"library: data-desired alterado");
	check(libCtx.win.l7ProfileDraftBusy === false, "library: draft busy false");
	const draftBar = libCtx.document.getElementById("l7ProfileDraftBar");
	check(draftBar && !draftBar.hidden, "library: draft bar activo");

	const links = Array.prototype.filter.call(
		libCtx.document.querySelectorAll('a[onclick*="l7showProfileModal"]'),
		function (a) {
			return a.getAttribute("href") && a.getAttribute("href").indexOf("profile_options=") >= 0;
		}
	);
	check(links.length >= 2, "library: >=2 links opcoes");
	const linkA = links[0];
	const linkB = links[1];
	const profileIdB = profileIdFromOptionsLink(linkB);
	check(profileIdB !== "", "library: profile_id B do href");
	const modalForm = optionsForm(libCtx.document);
	fillAllForm(modalForm);
	modalForm.querySelector('[name="profile_action"]').value = "monitor";

	const clickA = invokeAttr(libCtx.win, linkA, "onclick");
	check(clickA.ret === false, "onclick A: retorna false com Bootstrap");
	check(!!clickA.ev.prevented, "onclick A: preventDefault");
	assertModalOpenState(libCtx.document.getElementById("l7ProfileModal"), "onclick A");
	check(modalForm.querySelector('[name="profile_id"]').value.length > 0,
		"onclick A: profile_id definido");
	const snapBefore = entriesWithoutProfileId(fdSnapshot(modalForm));

	const cancelBtn = libCtx.document.querySelector('button[onclick*="l7hideProfileModal"]');
	check(!!cancelBtn, "modal: botao cancelar presente");
	invokeAttr(libCtx.win, cancelBtn, "onclick");
	const modalEl = libCtx.document.getElementById("l7ProfileModal");
	check(!modalEl.classList.contains("in"), "modal: hide onclick sem submit");
	check(modalEl.getAttribute("aria-hidden") !== "true", "modal: fechado sem aria-hidden=true");

	check(search.value === "face", "library: filtro preservado apos hide");
	check(draftBar && !draftBar.hidden, "library: draft preservado apos hide");
	check(draftBtn.getAttribute("data-desired") !== savedDesired,
		"library: data-desired preservado apos hide");
	check((libCtx.win.l7collectProfileDraft().enableIds.length +
		libCtx.win.l7collectProfileDraft().disableIds.length) === draftCount,
		"library: contagem draft preservada apos hide");
	const snapAfterHide = entriesWithoutProfileId(fdSnapshot(modalForm));
	compareFormData("apos hide", snapBefore, snapAfterHide);
	check(modalForm.querySelector('[name="profile_action"]').value === "monitor",
		"modal: accao preservada apos hide");

	const clickB = invokeAttr(libCtx.win, linkB, "onclick");
	check(clickB.ret === false, "onclick B: retorna false");
	assertModalOpenState(modalEl, "onclick B");
	check(modalForm.querySelector('[name="profile_id"]').value === profileIdB,
		"modal: profile_id B exacto");
	const snapAfterB = entriesWithoutProfileId(fdSnapshot(modalForm));
	compareFormData("apos reabrir B", snapBefore, snapAfterB);
	check(modalForm.querySelector('[name="profile_action"]').value === "monitor",
		"modal: accao preservada apos reabrir B");
	check(modalForm.querySelector('[name="profile_src_cidrs"]').value.indexOf("192.168.10.0/24") >= 0,
		"modal: campos preenchidos preservados apos B");

	check(netCounters.fetch === 0, "rede: fetch 0 apos abrir/cancelar/reabrir");
	check(netCounters.submit === 0, "rede: submit 0 apos abrir/cancelar/reabrir");
	check(netCounters.requestSubmit === 0, "rede: requestSubmit 0 apos abrir/cancelar/reabrir");

	const fallbackCtx = openFixture(libraryDraftHtml, undefined, function (win) {
		win.jQuery = function () {
			return { modal: undefined };
		};
		win.jQuery.fn = {};
	}, false);
	const fbLink = fallbackCtx.document.querySelector('a[onclick*="l7showProfileModal"]');
	const fbHref = fbLink.getAttribute("href") || "";
	const fbOnclick = fbLink.getAttribute("onclick") || "";
	check(fbHref.indexOf("profile_options=") >= 0, "fallback: href profile_options capturado");
	check(fbOnclick.indexOf("l7showProfileModal") >= 0, "fallback: onclick capturado");
	const fbEv = {
		prevented: false,
		target: fbLink,
		currentTarget: fbLink,
		preventDefault: function () { this.prevented = true; },
	};
	const fbRet = invokeAttr(fallbackCtx.win, fbLink, "onclick", fbEv);
	check(fbRet.ret === true, "fallback onclick: retorna true sem plugin");
	check(!fbEv.prevented, "fallback onclick: sem preventDefault");
	fallbackCtx.dom.window.close();

	libCtx.dom.window.close();

	await proveBootstrapPinModal();

	check(jsdomUnexpected.length === 0,
		"jsdom: sem erros inesperados (" + jsdomUnexpected.length + ")");

	console.log("Runtime: jsdom outside-only; Bootstrap modal stub de fronteira");
	console.log("Limites: nao e browser/pfSense/visual/foco/teclado");

	if (fail) {
		console.error("SOME POLICIES PROFILE OPTIONS JS TESTS FAILED");
		process.exit(1);
	}
	console.log("ALL POLICIES PROFILE OPTIONS JS TESTS PASSED");
	process.exit(0);
})();
