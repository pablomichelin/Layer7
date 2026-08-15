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
	["Inspecao por SNI (TLS)", "SNI inspection (TLS)"],
	["Modelo de enforcement PF", "PF enforcement model"],
	["Pagina de bloqueio", "Block page"],
	["IP portal", "Portal IP"],
	["Titulo da pagina", "Page title"],
	["Mensagem", "Message"],
	["Redireccionar todo o DNS (porta 53) dos clientes para o resolver local", "Redirect all client DNS (port 53) to the local resolver"],
	["Incluir dominios de categorias activas no sinkhole", "Include domains from active categories in the sinkhole"],
	["Limite de dominios blacklist", "Blacklist domain limit"],
	["Mostrar nome da politica", "Show policy name"],
	["Selecione as interfaces onde QUIC (UDP 443) deve ser bloqueado. Vazio = desativado. Forca apps a usar HTTPS/TLS, melhorando a deteccao por SNI.", "Select the interfaces where QUIC (UDP 443) must be blocked. Empty = disabled. Forces apps to use HTTPS/TLS, improving SNI detection."],
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
const localeHelper = fs.readFileSync(path.join(local, "pkg/layer7.inc"), "utf8");
if (!localeHelper.includes("function layer7_t_for_language") ||
    !localeHelper.includes("function layer7_blockpage_default_texts")) {
    failures.set("Localized built-in block-page defaults", ["pkg/layer7.inc"]);
}
if (!settings.includes("Only migrate the package defaults on a language switch")) {
    failures.set("Default block-page copy migration on language switch", ["www/packages/layer7/layer7_settings.php"]);
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
