#!/usr/bin/env node
/* Ensures supported locales cover GUI text and public block-page defaults. */
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "../..");
const local = path.join(root, "package/pfSense-pkg-layer7/files/usr/local");
const enFile = path.join(local, "etc/layer7/lang/en.php");
const esFile = path.join(local, "etc/layer7/lang/es.php");

function decodePhpDoubleQuoted(value) {
    return JSON.parse('"' + value.replace(/\n/g, "\\n") + '"');
}

function filesBelow(dir) {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const file = path.join(dir, entry.name);
        return entry.isDirectory() ? filesBelow(file) : [file];
    });
}

const english = fs.readFileSync(enFile, "utf8");
const strings = new Set();
for (const match of english.matchAll(/^\s*"((?:\\.|[^"\\])*)"\s*=>/gms)) {
    strings.add(decodePhpDoubleQuoted(match[1]));
}

const missing = new Map();
const guiFiles = [
    ...filesBelow(path.join(local, "pkg")),
    ...filesBelow(path.join(local, "www/packages/layer7")),
].filter((file) => /\.(php|inc)$/.test(file));
for (const file of guiFiles) {
    const source = fs.readFileSync(file, "utf8");
    for (const match of source.matchAll(/l7_t\(\s*"((?:\\.|[^"\\])*)"\s*\)/gs)) {
        const key = decodePhpDoubleQuoted(match[1]);
        if (!strings.has(key)) {
            missing.set(key, [...(missing.get(key) || []), path.relative(root, file)]);
        }
    }
}

const profiles = JSON.parse(fs.readFileSync(path.join(local, "etc/layer7/profiles.json"), "utf8"));
for (const profile of (Array.isArray(profiles) ? profiles : profiles.profiles || [])) {
    if (profile.description && !strings.has(profile.description)) {
        missing.set(profile.description, [...(missing.get(profile.description) || []), "profiles.json"]);
    }
}

const spanish = fs.readFileSync(esFile, "utf8");
if (!spanish.includes('include(__DIR__ . "/en.php")') ||
    !spanish.includes('"Acesso bloqueado" => "Acceso bloqueado"')) {
    missing.set("Spanish catalogue with English safe fallback", ["etc/layer7/lang/es.php"]);
}

const blockPage = fs.readFileSync(path.join(local, "www/layer7-blockpage/index.php"), "utf8");
if (!blockPage.includes("$en_defaults") || !blockPage.includes("$es_defaults") ||
    !blockPage.includes("$bp['html_lang']") ||
    blockPage.includes('<html lang="pt">')) {
    missing.set("English-aware public block page", ["www/layer7-blockpage/index.php"]);
}

if (missing.size) {
    for (const [key, locations] of missing) {
        console.error(`Missing locale coverage: ${JSON.stringify(key)} (${locations.join(", ")})`);
    }
    process.exit(1);
}
console.log(`PASS: i18n coverage (${strings.size} EN keys, ES safe fallback, ${guiFiles.length} GUI files)`);
