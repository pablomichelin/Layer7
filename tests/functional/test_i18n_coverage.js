#!/usr/bin/env node
/* Guards the Layer7-owned UI against incomplete or mixed PT/EN/ES catalogues. */
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "../..");
const local = path.join(root, "package/pfSense-pkg-layer7/files/usr/local");
const ptFile = path.join(local, "etc/layer7/lang/pt.php");
const enFile = path.join(local, "etc/layer7/lang/en.php");
const esFile = path.join(local, "etc/layer7/lang/es.php");

function decodePhpDoubleQuoted(value) {
    return JSON.parse('"' + value.replace(/\n/g, "\\n") + '"');
}

function catalogue(file) {
    const parsed = new Map();
    const source = fs.readFileSync(file, "utf8");
    for (const match of source.matchAll(/^\s*"((?:\\.|[^"\\])*)"\s*=>\s*"((?:\\.|[^"\\])*)",?\s*$/gms)) {
        parsed.set(decodePhpDoubleQuoted(match[1]), decodePhpDoubleQuoted(match[2]));
    }
    return { source, parsed };
}

function filesBelow(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(dir, entry.name);
        return entry.isDirectory() ? filesBelow(file) : [file];
    });
}

const portuguese = catalogue(ptFile);
const english = catalogue(enFile);
const spanish = catalogue(esFile);
const sourceKeys = new Set();
const failures = new Map();
const guiFiles = [
    ...filesBelow(path.join(local, "pkg")),
    ...filesBelow(path.join(local, "www/packages/layer7")),
].filter((file) => /\.(php|inc)$/.test(file));

for (const file of guiFiles) {
    const source = fs.readFileSync(file, "utf8");
    for (const expression of [/l7_t\(\s*"((?:\\.|[^"\\])*)"\s*\)/gs, /l7_t\(\s*'((?:\\.|[^'\\])*)'\s*\)/gs]) {
        for (const match of source.matchAll(expression)) {
            const key = decodePhpDoubleQuoted(match[1]);
            sourceKeys.add(key);
            if (!english.parsed.has(key)) {
                failures.set(key, [...(failures.get(key) || []), path.relative(root, file)]);
            }
        }
    }
}

const profiles = JSON.parse(fs.readFileSync(path.join(local, "etc/layer7/profiles.json"), "utf8"));
for (const profile of (Array.isArray(profiles) ? profiles : profiles.profiles || [])) {
    if (profile.description) sourceKeys.add(profile.description);
}

for (const key of sourceKeys) {
    if (!english.parsed.has(key)) {
        failures.set(key, [...(failures.get(key) || []), "EN catalogue"]);
    }
    if (!spanish.parsed.has(key) || !spanish.parsed.get(key).trim()) {
        failures.set(key, [...(failures.get(key) || []), "ES catalogue"]);
    }
}

if (spanish.source.includes('include(__DIR__ . "/en.php")')) {
    failures.set("Spanish catalogue must not fall back to English", ["etc/layer7/lang/es.php"]);
}

const criticalSpanish = new Map([
    ["Relatorios executivos", "Informes ejecutivos"],
    ["Armazenamento de logs", "Almacenamiento de registros"],
    ["Allowlist", "Lista de permitidos"],
    ["Configuracao do servico", "Configuración del servicio"],
    ["Settings", "Configuración"],
    ["Events", "Eventos"],
    ["Services", "Servicios"],
    ["Aguardando eventos...", "Esperando eventos..."],
]);
for (const [key, value] of criticalSpanish) {
    if (spanish.parsed.get(key) !== value) {
        failures.set(`Spanish translation for ${JSON.stringify(key)}`, ["etc/layer7/lang/es.php"]);
    }
}

const criticalEnglish = new Map([
    ["Relatorios executivos", "Executive reports"],
    ["Armazenamento de logs", "Log storage"],
    ["Allowlist", "Allowlist"],
    ["Configuracao do servico", "Service configuration"],
    ["Aguardando eventos...", "Waiting for events..."],
]);
for (const [key, value] of criticalEnglish) {
    if (english.parsed.get(key) !== value) {
        failures.set(`English translation for ${JSON.stringify(key)}`, ["etc/layer7/lang/en.php"]);
    }
}

const criticalPortuguese = new Map([
    ["Services", "Serviços"],
    ["Settings", "Definições"],
    ["Policies", "Políticas"],
    ["Blacklists", "Listas de bloqueio"],
    ["Allowlist", "Lista de permitidos"],
    ["Identity", "Identidade"],
    ["Diagnostics", "Diagnósticos"],
]);
for (const [key, value] of criticalPortuguese) {
    if (portuguese.parsed.get(key) !== value) {
        failures.set(`Portuguese translation for ${JSON.stringify(key)}`, ["etc/layer7/lang/pt.php"]);
    }
}

const settings = fs.readFileSync(path.join(local, "www/packages/layer7/layer7_settings.php"), "utf8");
const events = fs.readFileSync(path.join(local, "www/packages/layer7/layer7_events.php"), "utf8");
if (settings.includes(" / Language")) {
    failures.set("Hard-coded mixed language label", ["www/packages/layer7/layer7_settings.php"]);
}
if (events.includes("'Aguardando eventos...'" ) || events.includes("' linha(s)'")) {
    failures.set("Hard-coded event-page text bypasses locale catalogues", ["www/packages/layer7/layer7_events.php"]);
}

const blockPage = fs.readFileSync(path.join(local, "www/layer7-blockpage/index.php"), "utf8");
if (!blockPage.includes("$en_defaults") || !blockPage.includes("$es_defaults") ||
    !blockPage.includes("$bp['html_lang']") || blockPage.includes('<html lang="pt">')) {
    failures.set("Localized public block page", ["www/layer7-blockpage/index.php"]);
}

if (failures.size) {
    for (const [key, locations] of failures) {
        console.error(`i18n coverage failure: ${JSON.stringify(key)} (${locations.join(", ")})`);
    }
    process.exit(1);
}
console.log(`PASS: ${portuguese.parsed.size} PT overrides + ${english.parsed.size} EN and ${spanish.parsed.size} ES translations across ${guiFiles.length} GUI files`);
