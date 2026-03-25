#!/bin/sh
#
# layer7-stats-purge.sh — Remove stats history older than retention period.
# Called by cron daily. Reads retention from layer7.json or uses default 30 days.
#

set -eu

REPORTS_DIR="/usr/local/etc/layer7/reports"
HISTORY_FILE="${REPORTS_DIR}/stats-history.jsonl"
CONFIG="/usr/local/etc/layer7.json"
PHP="/usr/local/bin/php"
COLLECT_LIB="/usr/local/pkg/layer7.inc"

if [ ! -f "${HISTORY_FILE}" ]; then
	exit 0
fi

if [ ! -x "${PHP}" ]; then
	exit 1
fi

"${PHP}" -r '
if (file_exists("'"${COLLECT_LIB}"'")) {
	require_once("'"${COLLECT_LIB}"'");
	$cfg = layer7_reports_config();
	$ret = isset($cfg["retention_days"]) ? (int)$cfg["retention_days"] : 30;
	layer7_reports_purge_db($ret);
}
'

"${PHP}" -r '
$config_path = "'"${CONFIG}"'";
$history_path = "'"${HISTORY_FILE}"'";

$retention_days = 30;
if (file_exists($config_path)) {
	$cfg = @json_decode(@file_get_contents($config_path), true);
	if (isset($cfg["layer7"]["reports"]["retention_days"])) {
		$r = (int)$cfg["layer7"]["reports"]["retention_days"];
		if ($r >= 1 && $r <= 365) {
			$retention_days = $r;
		}
	}
}

$cutoff = time() - ($retention_days * 86400);

$lines = @file($history_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($lines) || count($lines) === 0) {
	exit(0);
}

$kept = array();
foreach ($lines as $line) {
	$obj = @json_decode($line, true);
	if (!is_array($obj) || !isset($obj["ts"])) {
		continue;
	}
	if ((int)$obj["ts"] >= $cutoff) {
		$kept[] = $line;
	}
}

if (count($kept) < count($lines)) {
	$tmp = $history_path . ".tmp";
	file_put_contents($tmp, implode("\n", $kept) . "\n", LOCK_EX);
	rename($tmp, $history_path);
}
'
