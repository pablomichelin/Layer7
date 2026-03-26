#!/bin/sh
# update-blacklists.sh — Descarga e gestao de blacklists UT1
# Layer7 para pfSense CE — Systemup (www.systemup.inf.br)
#
# Uso:
#   update-blacklists.sh              — download + apply (cron)
#   update-blacklists.sh --download   — download + auto-descoberta
#   update-blacklists.sh --apply      — SIGHUP ao daemon
#
# Fonte: Universite Toulouse Capitole (CC-BY-SA 4.0)

set -eu

BL_DIR="/usr/local/etc/layer7/blacklists"
CONFIG="$BL_DIR/config.json"
DISCOVERED="$BL_DIR/discovered.json"
PROGRESS="/tmp/layer7-bl-progress.txt"
TMP="/tmp/layer7-bl-update.$$"
LOCK="/tmp/layer7-bl-update.lock"
LOG="/var/log/layer7-bl-update.log"
PID_FILE="/var/run/layer7d.pid"
DEFAULT_URL="http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz"
MIN_SIZE=1000000

log() {
	_msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
	echo "$_msg" >> "$LOG"
	echo "$_msg" >> "$PROGRESS"
	echo "$*"
}

cleanup() {
	rm -rf "$TMP"
	rmdir "$LOCK" 2>/dev/null || true
}
trap cleanup EXIT

send_sighup() {
	if [ -f "$PID_FILE" ]; then
		kill -HUP "$(cat "$PID_FILE")" 2>/dev/null || true
		log "INFO: sent SIGHUP to daemon"
	else
		log "WARN: PID file not found, daemon may not be running"
	fi
}

do_apply() {
	send_sighup
	log "INFO: apply complete"
}

do_download() {
	if ! mkdir "$LOCK" 2>/dev/null; then
		log "ERROR: update already running (lock exists)"
		exit 1
	fi

	BL_URL=""
	if [ -f "$CONFIG" ]; then
		BL_URL=$(sed -n 's/.*"source_url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$CONFIG" | head -1)
	fi
	BL_URL="${BL_URL:-$DEFAULT_URL}"

	: > "$PROGRESS"
	log "INFO: starting blacklist update from $BL_URL"

	AVAIL=$(df -k /usr/local/etc 2>/dev/null | tail -1 | awk '{print $4}')
	if [ -n "$AVAIL" ] && [ "$AVAIL" -lt 250000 ] 2>/dev/null; then
		log "ERROR: insufficient disk space (need 250MB, have ${AVAIL}KB)"
		exit 1
	fi

	mkdir -p "$TMP"
	log "INFO: downloading blacklists.tar.gz..."
	if ! fetch -o "$TMP/blacklists.tar.gz" "$BL_URL" 2>>"$LOG"; then
		log "ERROR: download failed"
		exit 1
	fi

	SIZE=$(stat -f%z "$TMP/blacklists.tar.gz" 2>/dev/null || echo 0)
	if [ "$SIZE" -lt "$MIN_SIZE" ]; then
		log "ERROR: downloaded file too small ($SIZE bytes, minimum $MIN_SIZE)"
		exit 1
	fi
	log "INFO: download complete (${SIZE} bytes)"

	log "INFO: extracting archive..."
	if ! tar xzf "$TMP/blacklists.tar.gz" -C "$TMP" 2>>"$LOG"; then
		log "ERROR: extraction failed"
		exit 1
	fi
	log "INFO: extraction complete"

	log "INFO: discovering categories..."
	mkdir -p "$BL_DIR"

	printf '{"source":"%s","discovered_at":"%s","categories":[' \
		"$BL_URL" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$DISCOVERED.tmp"

	_first=1
	_total_cats=0
	_total_domains=0
	for _catdir in "$TMP"/blacklists/*/; do
		[ -d "$_catdir" ] || continue
		_cat=$(basename "$_catdir")
		_domfile="$_catdir/domains"
		if [ -f "$_domfile" ]; then
			_count=$(wc -l < "$_domfile" | tr -d ' ')
			if [ "$_first" -eq 0 ]; then
				printf ',' >> "$DISCOVERED.tmp"
			fi
			printf '{"id":"%s","domains_count":%d}' "$_cat" "$_count" >> "$DISCOVERED.tmp"
			_first=0
			_total_cats=$((_total_cats + 1))
			_total_domains=$((_total_domains + _count))
		fi
	done
	echo ']}' >> "$DISCOVERED.tmp"
	mv "$DISCOVERED.tmp" "$DISCOVERED"
	log "INFO: discovered $_total_cats categories ($_total_domains total domains)"

	log "INFO: copying category files to $BL_DIR..."
	for _catdir in "$TMP"/blacklists/*/; do
		[ -d "$_catdir" ] || continue
		_cat=$(basename "$_catdir")
		if [ -f "$_catdir/domains" ]; then
			mkdir -p "$BL_DIR/$_cat"
			cp "$_catdir/domains" "$BL_DIR/$_cat/domains"
		fi
	done
	log "INFO: all category files copied"

	date -u '+%Y-%m-%dT%H:%M:%SZ' > "$BL_DIR/last-update.txt"

	send_sighup

	log "INFO: update complete"
}

MODE=""
case "${1:-}" in
	--download) MODE="download" ;;
	--apply)    MODE="apply" ;;
	"")         MODE="full" ;;
	*)
		echo "Usage: $0 [--download|--apply]" >&2
		exit 1
		;;
esac

case "$MODE" in
	download) do_download ;;
	apply)    do_apply ;;
	full)     do_download ;;
esac
