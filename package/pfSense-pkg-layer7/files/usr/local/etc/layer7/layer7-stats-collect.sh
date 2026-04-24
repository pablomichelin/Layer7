#!/bin/sh
#
# layer7-stats-collect.sh — Append current stats snapshot to history JSONL.
# Called by cron every 5 minutes. Designed for minimal resource usage.
#

set -eu

STATS_JSON="/tmp/layer7-stats.json"
REPORTS_DIR="/usr/local/etc/layer7/reports"
HISTORY_FILE="${REPORTS_DIR}/stats-history.jsonl"
PIDFILE="/var/run/layer7d.pid"
PHP="/usr/local/bin/php"
COLLECT_PHP="/usr/local/etc/layer7/layer7-reports-collect.php"
COLLECT_LIB="/usr/local/pkg/layer7.inc"

/bin/mkdir -p "${REPORTS_DIR}"

if [ -x "${PHP}" ] && [ -f "${COLLECT_PHP}" ]; then
	"${PHP}" "${COLLECT_PHP}" >/dev/null 2>&1 || true
fi

if [ ! -f "${PIDFILE}" ]; then
	exit 0
fi

_pid=""
if ! read -r _pid <"${PIDFILE}" 2>/dev/null; then
	exit 0
fi
_pid=$(printf '%s' "$_pid" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
if [ -z "${_pid}" ]; then
	exit 0
fi
case "$_pid" in
*[!0-9]*)
	exit 0
	;;
esac

kill -0 "${_pid}" 2>/dev/null || exit 0

kill -USR1 "${_pid}" 2>/dev/null || true
sleep 1

if [ ! -f "${STATS_JSON}" ]; then
	exit 0
fi

if [ ! -x "${PHP}" ]; then
	exit 1
fi

"${PHP}" -r '
$history_enabled = true;
if (file_exists("'"${COLLECT_LIB}"'")) {
	require_once("'"${COLLECT_LIB}"'");
	$cfg = layer7_reports_config();
	$history_enabled = !empty($cfg["enabled"]);
}
if (!$history_enabled) exit(0);

$raw = @file_get_contents("/tmp/layer7-stats.json");
if (!$raw) exit(0);
$s = @json_decode($raw, true);
if (!is_array($s)) exit(0);

$line = array(
	"ts" => time(),
	"classified" => isset($s["total_classified"]) ? (int)$s["total_classified"] : 0,
	"blocked" => isset($s["total_blocked"]) ? (int)$s["total_blocked"] : 0,
	"allowed" => isset($s["total_allowed"]) ? (int)$s["total_allowed"] : 0,
	"pf_ok" => isset($s["pf_add_ok"]) ? (int)$s["pf_add_ok"] : 0,
	"pf_fail" => isset($s["pf_add_fail"]) ? (int)$s["pf_add_fail"] : 0,
	"dst_ok" => isset($s["dst_add_ok"]) ? (int)$s["dst_add_ok"] : 0,
	"dst_fail" => isset($s["dst_add_fail"]) ? (int)$s["dst_add_fail"] : 0,
	"bl_lookups" => isset($s["bl_lookups"]) ? (int)$s["bl_lookups"] : 0,
	"bl_hits" => isset($s["bl_hits"]) ? (int)$s["bl_hits"] : 0,
	"policies" => isset($s["policies_active"]) ? (int)$s["policies_active"] : 0,
	"enforce" => isset($s["enforce_mode"]) ? (int)$s["enforce_mode"] : 0,
	"top_apps" => isset($s["top_apps_blocked"]) ? array_slice($s["top_apps_blocked"], 0, 10) : array(),
	"top_sources" => isset($s["top_sources_blocked"]) ? array_slice($s["top_sources_blocked"], 0, 10) : array(),
	"bl_top_cats" => isset($s["bl_top_categories"]) ? array_slice($s["bl_top_categories"], 0, 10) : array()
);

$json = json_encode($line, JSON_UNESCAPED_SLASHES);
if ($json === false) exit(1);

file_put_contents("'"${HISTORY_FILE}"'", $json . "\n", FILE_APPEND | LOCK_EX);
'
