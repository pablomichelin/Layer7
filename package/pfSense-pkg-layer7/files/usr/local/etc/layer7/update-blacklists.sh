#!/bin/sh
# update-blacklists.sh — Consome apenas snapshots assinadas da trilha F1.3
# Layer7 para pfSense CE — Systemup (www.systemup.inf.br)
#
# Uso:
#   update-blacklists.sh                — download + apply (cron)
#   update-blacklists.sh --download     — download + validacao + apply
#   update-blacklists.sh --apply        — SIGHUP ao daemon
#   update-blacklists.sh --restore-lkg  — restaura a last-known-good validada

set -eu

BL_DIR="/usr/local/etc/layer7/blacklists"
CONFIG="$BL_DIR/config.json"
DISCOVERED="$BL_DIR/discovered.json"
PROGRESS="/tmp/layer7-bl-progress.txt"
TMP="/tmp/layer7-bl-update.$$"
LOCK="/tmp/layer7-bl-update.lock"
LOG="/var/log/layer7-bl-update.log"
PID_FILE="/var/run/layer7d.pid"
STATE_DIR="$BL_DIR/.state"
ACTIVE_STATE="$STATE_DIR/active-snapshot.state"
MANAGED_LIST="$STATE_DIR/managed-categories.txt"
CACHE_DIR="$BL_DIR/.cache"
LKG_DIR="$BL_DIR/.last-known-good"
LKG_STATE="$LKG_DIR/snapshot.state"
PUBKEY="/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem"
PRIMARY_MANIFEST_URL="https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt"
MIRROR_MANIFEST_URLS="https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt"
LEGACY_HTTP_URL="http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz"
LEGACY_HTTPS_URL="https://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz"
MANIFEST_NAME="layer7-blacklists-manifest.v1.txt"
SIG_NAME="${MANIFEST_NAME}.sig"
PUBKEY_ASSET="blacklists-signing-public-key.pem"
SNAPSHOT_ASSET="layer7-blacklists-ut1.tar.gz"
MIN_SIZE=1000000

LOCK_HELD="0"
PROMOTION_ACTIVE="0"
PREVIOUS_DIR=""
INCOMING_DIR=""
PENDING_MANAGED_LIST="$TMP/pending-managed.txt"

CANDIDATE_MANIFEST_LOCAL=""
CANDIDATE_SIG_LOCAL=""
CANDIDATE_ARCHIVE_LOCAL=""
CANDIDATE_EXTRACT_ROOT=""
CANDIDATE_SNAPSHOT_ID=""
CANDIDATE_SOURCE_ROLE=""
CANDIDATE_MANIFEST_URL=""
CANDIDATE_SNAPSHOT_URL=""
CANDIDATE_UPSTREAM_URL=""
CANDIDATE_UPSTREAM_ACQUIRED_AT=""
CANDIDATE_CATEGORIES_COUNT=""
CANDIDATE_DOMAINS_COUNT=""
CANDIDATE_VALIDATED_AT=""

log() {
	_msg="$(date '+%Y-%m-%d %H:%M:%S') $*"
	echo "$_msg" >> "$LOG"
	echo "$_msg" >> "$PROGRESS"
	echo "$*"
}

restore_previous() {
	if [ "$PROMOTION_ACTIVE" != "1" ] || [ -z "$PREVIOUS_DIR" ] || [ ! -d "$PREVIOUS_DIR" ]; then
		return
	fi

	if [ -f "$PENDING_MANAGED_LIST" ]; then
		while IFS= read -r _cat; do
			[ -n "$_cat" ] || continue
			rm -rf "$BL_DIR/$_cat" 2>/dev/null || true
		done < "$PENDING_MANAGED_LIST"
	fi

	for _dir in "$PREVIOUS_DIR"/*; do
		[ -d "$_dir" ] || continue
		_cat="$(basename "$_dir")"
		rm -rf "$BL_DIR/$_cat" 2>/dev/null || true
		mv "$_dir" "$BL_DIR/$_cat" 2>/dev/null || true
	done
	rm -rf "$PREVIOUS_DIR" 2>/dev/null || true
}

cleanup() {
	_rc=$?

	if [ "$_rc" -ne 0 ]; then
		restore_previous
	fi

	[ -n "$INCOMING_DIR" ] && rm -rf "$INCOMING_DIR" 2>/dev/null || true
	if [ "$_rc" -eq 0 ] && [ -n "$PREVIOUS_DIR" ]; then
		rm -rf "$PREVIOUS_DIR" 2>/dev/null || true
	fi
	rm -rf "$TMP" 2>/dev/null || true
	if [ "$LOCK_HELD" = "1" ]; then
		rmdir "$LOCK" 2>/dev/null || true
	fi
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

sha256_hex() {
	_file="$1"
	if command -v sha256 >/dev/null 2>&1; then
		sha256 -q "$_file"
	elif command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$_file" | awk '{print $1}'
	else
		openssl dgst -sha256 "$_file" | awk '{print $2}'
	fi
}

file_size_bytes() {
	_file="$1"
	if stat -f '%z' "$_file" >/dev/null 2>&1; then
		stat -f '%z' "$_file"
	elif stat -c '%s' "$_file" >/dev/null 2>&1; then
		stat -c '%s' "$_file"
	else
		wc -c < "$_file" | tr -d ' '
	fi
}

fingerprint_public_key_sha256() {
	_pubkey="$1"
	openssl pkey -pubin -in "$_pubkey" -outform DER 2>/dev/null \
		| openssl dgst -sha256 \
		| awk '{print $2}'
}

manifest_value() {
	_key="$1"
	_manifest="$2"
	sed -n "s/^${_key}=//p" "$_manifest" | head -1
}

json_escape() {
	printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

read_config_source_url() {
	if [ -f "$CONFIG" ]; then
		sed -n 's/.*"source_url"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$CONFIG" | head -1
	fi
}

is_official_manifest_url() {
	_url="$1"
	if [ "$_url" = "$PRIMARY_MANIFEST_URL" ]; then
		return 0
	fi
	for _mirror in $MIRROR_MANIFEST_URLS; do
		if [ "$_url" = "$_mirror" ]; then
			return 0
		fi
	done
	return 1
}

build_candidate_list() {
	_candidates="$TMP/candidates.txt"
	: > "$_candidates"

	_cfg_url="$(read_config_source_url || true)"
	if [ -n "${_cfg_url:-}" ]; then
		if is_official_manifest_url "$_cfg_url"; then
			echo "primary|$_cfg_url" >> "$_candidates"
		elif [ "$_cfg_url" = "$LEGACY_HTTP_URL" ] || [ "$_cfg_url" = "$LEGACY_HTTPS_URL" ]; then
			log "INFO: ignoring legacy upstream URL from config; using official origin"
		else
			log "WARN: ignoring non-official blacklist source from config: $_cfg_url"
		fi
	fi

	if ! grep -Fqx "primary|$PRIMARY_MANIFEST_URL" "$_candidates" 2>/dev/null; then
		echo "primary|$PRIMARY_MANIFEST_URL" >> "$_candidates"
	fi
	for _mirror in $MIRROR_MANIFEST_URLS; do
		if ! grep -Fqx "mirror|$_mirror" "$_candidates" 2>/dev/null; then
			echo "mirror|$_mirror" >> "$_candidates"
		fi
	done

	echo "$_candidates"
}

validate_snapshot_tree() {
	_root="$1"
	[ -d "$_root/blacklists" ] || return 1
	for _catdir in "$_root"/blacklists/*; do
		[ -d "$_catdir" ] || continue
		[ -f "$_catdir/domains" ] || continue
		return 0
	done
	return 1
}

categories_count_from_root() {
	_root="$1"
	find "$_root/blacklists" -mindepth 1 -maxdepth 1 -type d 2>/dev/null \
		| wc -l | tr -d ' '
}

domains_count_from_root() {
	_root="$1"
	find "$_root/blacklists" -type f -name domains 2>/dev/null \
		| while IFS= read -r _file; do
			wc -l < "$_file"
		done \
		| awk '{sum += $1} END {print sum + 0}'
}

build_current_managed_list() {
	_out="$1"
	: > "$_out"
	if [ -f "$MANAGED_LIST" ]; then
		cp "$MANAGED_LIST" "$_out"
		return
	fi
	for _dir in "$BL_DIR"/*; do
		[ -d "$_dir" ] || continue
		_cat="$(basename "$_dir")"
		case "$_cat" in
		.*|_custom) continue ;;
		esac
		[ -f "$_dir/domains" ] || continue
		echo "$_cat" >> "$_out"
	done
}

write_discovered_json() {
	_out="$1"
	printf '{"source":"%s","snapshot_id":"%s","discovered_at":"%s","categories":[' \
		"$(json_escape "$CANDIDATE_MANIFEST_URL")" \
		"$(json_escape "$CANDIDATE_SNAPSHOT_ID")" \
		"$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "$_out"

	_first="1"
	for _catdir in "$CANDIDATE_EXTRACT_ROOT"/blacklists/*; do
		[ -d "$_catdir" ] || continue
		_cat="$(basename "$_catdir")"
		_domfile="$_catdir/domains"
		[ -f "$_domfile" ] || continue
		_count="$(wc -l < "$_domfile" | tr -d ' ')"
		if [ "$_first" = "0" ]; then
			printf ',' >> "$_out"
		fi
		printf '{"id":"%s","domains_count":%s}' \
			"$(json_escape "$_cat")" "$_count" >> "$_out"
		_first="0"
	done
	echo ']}' >> "$_out"
}

write_state_file() {
	_out="$1"
	cat > "$_out" <<-EOF
		snapshot_id=$CANDIDATE_SNAPSHOT_ID
		manifest_url=$CANDIDATE_MANIFEST_URL
		snapshot_url=$CANDIDATE_SNAPSHOT_URL
		source_role=$CANDIDATE_SOURCE_ROLE
		validated_at_utc=$CANDIDATE_VALIDATED_AT
		applied_at_utc=$(date -u '+%Y-%m-%dT%H:%M:%SZ')
		upstream_url=$CANDIDATE_UPSTREAM_URL
		upstream_acquired_at_utc=$CANDIDATE_UPSTREAM_ACQUIRED_AT
		categories_count=$CANDIDATE_CATEGORIES_COUNT
		domains_count=$CANDIDATE_DOMAINS_COUNT
		cache_dir=$CACHE_DIR/$CANDIDATE_SNAPSHOT_ID
	EOF
}

stage_categories() {
	_target_dir="$1"
	_list_file="$2"

	: > "$_list_file"
	mkdir -p "$_target_dir"
	for _catdir in "$CANDIDATE_EXTRACT_ROOT"/blacklists/*; do
		[ -d "$_catdir" ] || continue
		_cat="$(basename "$_catdir")"
		_domfile="$_catdir/domains"
		[ -f "$_domfile" ] || continue
		mkdir -p "$_target_dir/$_cat"
		cp "$_domfile" "$_target_dir/$_cat/domains"
		echo "$_cat" >> "$_list_file"
	done
}

update_lkg() {
	_discovered_tmp="$1"
	_state_tmp="$2"
	_lkg_tmp="$BL_DIR/.last-known-good.tmp.$$"

	rm -rf "$_lkg_tmp"
	mkdir -p "$_lkg_tmp/blacklists"
	for _catdir in "$CANDIDATE_EXTRACT_ROOT"/blacklists/*; do
		[ -d "$_catdir" ] || continue
		_cat="$(basename "$_catdir")"
		_domfile="$_catdir/domains"
		[ -f "$_domfile" ] || continue
		mkdir -p "$_lkg_tmp/blacklists/$_cat"
		cp "$_domfile" "$_lkg_tmp/blacklists/$_cat/domains"
	done
	cp "$CANDIDATE_MANIFEST_LOCAL" "$_lkg_tmp/$MANIFEST_NAME"
	cp "$CANDIDATE_SIG_LOCAL" "$_lkg_tmp/$SIG_NAME"
	cp "$CANDIDATE_ARCHIVE_LOCAL" "$_lkg_tmp/$SNAPSHOT_ASSET"
	cp "$_discovered_tmp" "$_lkg_tmp/discovered.json"
	cp "$_state_tmp" "$_lkg_tmp/snapshot.state"

	rm -rf "$LKG_DIR"
	mv "$_lkg_tmp" "$LKG_DIR"
}

promote_candidate() {
	_discovered_tmp="$TMP/discovered.json"
	_state_tmp="$TMP/active-snapshot.state"
	_cache_stage="$CACHE_DIR/.incoming-$CANDIDATE_SNAPSHOT_ID.$$"
	_current_managed="$TMP/current-managed.txt"

	mkdir -p "$BL_DIR" "$STATE_DIR" "$CACHE_DIR"
	write_discovered_json "$_discovered_tmp"
	write_state_file "$_state_tmp"
	build_current_managed_list "$_current_managed"

	INCOMING_DIR="$BL_DIR/.incoming.$$"
	PREVIOUS_DIR="$BL_DIR/.previous.$$"
	rm -rf "$INCOMING_DIR" "$PREVIOUS_DIR"
	mkdir -p "$INCOMING_DIR" "$PREVIOUS_DIR"
	stage_categories "$INCOMING_DIR" "$PENDING_MANAGED_LIST"

	PROMOTION_ACTIVE="1"
	if [ -f "$_current_managed" ]; then
		while IFS= read -r _cat; do
			[ -n "$_cat" ] || continue
			if [ -d "$BL_DIR/$_cat" ]; then
				mv "$BL_DIR/$_cat" "$PREVIOUS_DIR/$_cat"
			fi
		done < "$_current_managed"
	fi

	for _catdir in "$INCOMING_DIR"/*; do
		[ -d "$_catdir" ] || continue
		mv "$_catdir" "$BL_DIR/"
	done

	cp "$_discovered_tmp" "$DISCOVERED"
	cp "$_state_tmp" "$ACTIVE_STATE"
	cp "$PENDING_MANAGED_LIST" "$MANAGED_LIST"
	date -u '+%Y-%m-%dT%H:%M:%SZ' > "$BL_DIR/last-update.txt"

	rm -rf "$_cache_stage"
	mkdir -p "$_cache_stage"
	cp "$CANDIDATE_MANIFEST_LOCAL" "$_cache_stage/$MANIFEST_NAME"
	cp "$CANDIDATE_SIG_LOCAL" "$_cache_stage/$SIG_NAME"
	cp "$CANDIDATE_ARCHIVE_LOCAL" "$_cache_stage/$SNAPSHOT_ASSET"
	rm -rf "$CACHE_DIR/$CANDIDATE_SNAPSHOT_ID"
	mv "$_cache_stage" "$CACHE_DIR/$CANDIDATE_SNAPSHOT_ID"

	update_lkg "$_discovered_tmp" "$_state_tmp"

	PROMOTION_ACTIVE="0"
	rm -rf "$PREVIOUS_DIR" "$INCOMING_DIR"
	PREVIOUS_DIR=""
	INCOMING_DIR=""

	send_sighup
	log "INFO: activated snapshot $CANDIDATE_SNAPSHOT_ID from $CANDIDATE_SOURCE_ROLE"
}

try_candidate() {
	_source_role="$1"
	_manifest_url="$2"
	_manifest_local="$TMP/$MANIFEST_NAME"
	_sig_local="$TMP/$SIG_NAME"
	_archive_local="$TMP/$SNAPSHOT_ASSET"
	_extract_root="$TMP/extracted"
	_actual_pubkey_fingerprint="$(fingerprint_public_key_sha256 "$PUBKEY")"

	case "$_manifest_url" in
	https://*) ;;
	*)
		log "WARN: rejecting non-HTTPS source: $_manifest_url"
		return 1
		;;
	esac

	if ! is_official_manifest_url "$_manifest_url"; then
		log "WARN: rejecting non-official source: $_manifest_url"
		return 1
	fi

	rm -f "$_manifest_local" "$_sig_local" "$_archive_local"
	rm -rf "$_extract_root"
	mkdir -p "$_extract_root"

	log "INFO: trying $_source_role source $_manifest_url"
	if ! fetch -o "$_manifest_local" "$_manifest_url" 2>>"$LOG"; then
		log "WARN: failed to fetch manifest from $_manifest_url"
		return 1
	fi

	if ! fetch -o "$_sig_local" "${_manifest_url}.sig" 2>>"$LOG"; then
		log "WARN: failed to fetch manifest signature from ${_manifest_url}.sig"
		return 1
	fi

	_manifest_version="$(manifest_value manifest_version "$_manifest_local")"
	_dataset="$(manifest_value dataset "$_manifest_local")"
	_snapshot_id="$(manifest_value snapshot_id "$_manifest_local")"
	_snapshot_asset="$(manifest_value snapshot_asset "$_manifest_local")"
	_snapshot_size="$(manifest_value snapshot_size "$_manifest_local")"
	_snapshot_sha256="$(manifest_value snapshot_sha256 "$_manifest_local")"
	_categories_count="$(manifest_value categories_count "$_manifest_local")"
	_domains_count="$(manifest_value domains_count "$_manifest_local")"
	_checksum_algo="$(manifest_value checksum_algorithm "$_manifest_local")"
	_signing_scheme="$(manifest_value signing_scheme "$_manifest_local")"
	_publisher_role="$(manifest_value publisher_role "$_manifest_local")"
	_public_key_asset="$(manifest_value public_key_asset "$_manifest_local")"
	_pubkey_fingerprint="$(manifest_value public_key_fingerprint_sha256 "$_manifest_local")"
	_signer_role="$(manifest_value signer_role "$_manifest_local")"
	_signer_generated_at="$(manifest_value signer_generated_at_utc "$_manifest_local")"
	_upstream_url="$(manifest_value upstream_url "$_manifest_local")"
	_upstream_acquired_at="$(manifest_value upstream_acquired_at_utc "$_manifest_local")"

	[ "$_manifest_version" = "1" ] || {
		log "WARN: manifest_version inesperado em $_manifest_url"
		return 1
	}
	[ "$_dataset" = "ut1" ] || {
		log "WARN: dataset inesperado em $_manifest_url"
		return 1
	}
	[ -n "$_snapshot_id" ] || {
		log "WARN: snapshot_id ausente em $_manifest_url"
		return 1
	}
	[ "$_snapshot_asset" = "$SNAPSHOT_ASSET" ] || {
		log "WARN: snapshot_asset inesperado em $_manifest_url"
		return 1
	}
	[ -n "$_snapshot_size" ] || {
		log "WARN: snapshot_size ausente em $_manifest_url"
		return 1
	}
	[ -n "$_snapshot_sha256" ] || {
		log "WARN: snapshot_sha256 ausente em $_manifest_url"
		return 1
	}
	[ -n "$_categories_count" ] || {
		log "WARN: categories_count ausente em $_manifest_url"
		return 1
	}
	[ -n "$_domains_count" ] || {
		log "WARN: domains_count ausente em $_manifest_url"
		return 1
	}
	[ "$_checksum_algo" = "sha256" ] || {
		log "WARN: checksum_algorithm inesperado em $_manifest_url"
		return 1
	}
	[ "$_signing_scheme" = "ed25519-openssl-pkeyutl-v1" ] || {
		log "WARN: signing_scheme inesperado em $_manifest_url"
		return 1
	}
	[ "$_publisher_role" = "publisher" ] || {
		log "WARN: publisher_role inesperado em $_manifest_url"
		return 1
	}
	[ "$_public_key_asset" = "$PUBKEY_ASSET" ] || {
		log "WARN: public_key_asset inesperado em $_manifest_url"
		return 1
	}
	[ -n "$_pubkey_fingerprint" ] || {
		log "WARN: public_key_fingerprint_sha256 ausente em $_manifest_url"
		return 1
	}
	[ "$_pubkey_fingerprint" = "$_actual_pubkey_fingerprint" ] || {
		log "WARN: fingerprint da chave publica nao bate com o pacote em $_manifest_url"
		return 1
	}
	[ "$_signer_role" = "signer" ] || {
		log "WARN: signer_role inesperado em $_manifest_url"
		return 1
	}
	[ -n "$_signer_generated_at" ] || {
		log "WARN: signer_generated_at_utc ausente em $_manifest_url"
		return 1
	}

	if ! openssl pkeyutl -verify -rawin \
		-pubin \
		-inkey "$PUBKEY" \
		-sigfile "$_sig_local" \
		-in "$_manifest_local" >/dev/null 2>&1; then
		log "WARN: manifest signature invalid for $_manifest_url"
		return 1
	fi

	_snapshot_url="${_manifest_url%/*}/$_snapshot_asset"
	if ! fetch -o "$_archive_local" "$_snapshot_url" 2>>"$LOG"; then
		log "WARN: failed to fetch snapshot asset from $_snapshot_url"
		return 1
	fi

	_actual_size="$(file_size_bytes "$_archive_local")"
	if [ "$_actual_size" -lt "$MIN_SIZE" ]; then
		log "WARN: downloaded snapshot too small ($_actual_size bytes) from $_snapshot_url"
		return 1
	fi
	[ "$_actual_size" = "$_snapshot_size" ] || {
		log "WARN: snapshot_size diverge for $_snapshot_url"
		return 1
	}
	_actual_sha="$(sha256_hex "$_archive_local")"
	[ "$_actual_sha" = "$_snapshot_sha256" ] || {
		log "WARN: snapshot_sha256 diverge for $_snapshot_url"
		return 1
	}

	if ! tar xzf "$_archive_local" -C "$_extract_root" 2>>"$LOG"; then
		log "WARN: failed to extract snapshot from $_snapshot_url"
		return 1
	fi
	if ! validate_snapshot_tree "$_extract_root"; then
		log "WARN: extracted snapshot has invalid structure from $_snapshot_url"
		return 1
	fi

	_actual_categories="$(categories_count_from_root "$_extract_root")"
	_actual_domains="$(domains_count_from_root "$_extract_root")"
	[ "$_actual_categories" = "$_categories_count" ] || {
		log "WARN: categories_count diverge for $_snapshot_url"
		return 1
	}
	[ "$_actual_domains" = "$_domains_count" ] || {
		log "WARN: domains_count diverge for $_snapshot_url"
		return 1
	}

	CANDIDATE_MANIFEST_LOCAL="$_manifest_local"
	CANDIDATE_SIG_LOCAL="$_sig_local"
	CANDIDATE_ARCHIVE_LOCAL="$_archive_local"
	CANDIDATE_EXTRACT_ROOT="$_extract_root"
	CANDIDATE_SNAPSHOT_ID="$_snapshot_id"
	CANDIDATE_SOURCE_ROLE="$_source_role"
	CANDIDATE_MANIFEST_URL="$_manifest_url"
	CANDIDATE_SNAPSHOT_URL="$_snapshot_url"
	CANDIDATE_UPSTREAM_URL="$_upstream_url"
	CANDIDATE_UPSTREAM_ACQUIRED_AT="$_upstream_acquired_at"
	CANDIDATE_CATEGORIES_COUNT="$_actual_categories"
	CANDIDATE_DOMAINS_COUNT="$_actual_domains"
	CANDIDATE_VALIDATED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

	log "INFO: validated snapshot $_snapshot_id from $_source_role"
	return 0
}

do_apply() {
	send_sighup
	log "INFO: apply complete"
}

do_restore_lkg() {
	if [ ! -d "$LKG_DIR" ] || [ ! -f "$LKG_STATE" ]; then
		log "ERROR: no last-known-good available to restore"
		exit 1
	fi
	if [ ! -f "$LKG_DIR/$MANIFEST_NAME" ] || [ ! -f "$LKG_DIR/$SIG_NAME" ] || [ ! -f "$LKG_DIR/$SNAPSHOT_ASSET" ]; then
		log "ERROR: last-known-good is incomplete"
		exit 1
	fi

	CANDIDATE_MANIFEST_LOCAL="$LKG_DIR/$MANIFEST_NAME"
	CANDIDATE_SIG_LOCAL="$LKG_DIR/$SIG_NAME"
	CANDIDATE_ARCHIVE_LOCAL="$LKG_DIR/$SNAPSHOT_ASSET"
	CANDIDATE_EXTRACT_ROOT="$TMP/lkg-extract"
	mkdir -p "$CANDIDATE_EXTRACT_ROOT"

	if ! openssl pkeyutl -verify -rawin \
		-pubin \
		-inkey "$PUBKEY" \
		-sigfile "$CANDIDATE_SIG_LOCAL" \
		-in "$CANDIDATE_MANIFEST_LOCAL" >/dev/null 2>&1; then
		log "ERROR: last-known-good signature is invalid"
		exit 1
	fi

	if ! tar xzf "$CANDIDATE_ARCHIVE_LOCAL" -C "$CANDIDATE_EXTRACT_ROOT" 2>>"$LOG"; then
		log "ERROR: failed to extract last-known-good archive"
		exit 1
	fi
	if ! validate_snapshot_tree "$CANDIDATE_EXTRACT_ROOT"; then
		log "ERROR: last-known-good snapshot has invalid structure"
		exit 1
	fi

	CANDIDATE_SNAPSHOT_ID="$(manifest_value snapshot_id "$CANDIDATE_MANIFEST_LOCAL")"
	CANDIDATE_MANIFEST_URL="$(manifest_value manifest_url "$LKG_STATE")"
	CANDIDATE_SNAPSHOT_URL="$(manifest_value snapshot_url "$LKG_STATE")"
	CANDIDATE_UPSTREAM_URL="$(manifest_value upstream_url "$LKG_STATE")"
	CANDIDATE_UPSTREAM_ACQUIRED_AT="$(manifest_value upstream_acquired_at_utc "$LKG_STATE")"
	CANDIDATE_CATEGORIES_COUNT="$(categories_count_from_root "$CANDIDATE_EXTRACT_ROOT")"
	CANDIDATE_DOMAINS_COUNT="$(domains_count_from_root "$CANDIDATE_EXTRACT_ROOT")"
	CANDIDATE_SOURCE_ROLE="last-known-good"
	CANDIDATE_VALIDATED_AT="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"

	promote_candidate
	log "INFO: restored last-known-good snapshot $CANDIDATE_SNAPSHOT_ID"
}

do_download() {
	if ! mkdir "$LOCK" 2>/dev/null; then
		log "ERROR: update already running (lock exists)"
		exit 1
	fi
	LOCK_HELD="1"

	if [ ! -f "$PUBKEY" ]; then
		log "ERROR: blacklist public key not found at $PUBKEY"
		exit 1
	fi

	: > "$PROGRESS"
	log "INFO: starting trusted blacklist update"

	AVAIL=$(df -k /usr/local/etc 2>/dev/null | tail -1 | awk '{print $4}')
	if [ -n "$AVAIL" ] && [ "$AVAIL" -lt 250000 ] 2>/dev/null; then
		log "ERROR: insufficient disk space (need 250MB, have ${AVAIL}KB)"
		exit 1
	fi

	mkdir -p "$TMP" "$BL_DIR" "$STATE_DIR" "$CACHE_DIR"
	_candidates_file="$(build_candidate_list)"
	_success="0"

	while IFS='|' read -r _source_role _manifest_url; do
		[ -n "$_manifest_url" ] || continue
		if try_candidate "$_source_role" "$_manifest_url"; then
			_success="1"
			break
		fi
	done < "$_candidates_file"

	if [ "$_success" != "1" ]; then
		log "ERROR: no official blacklist source produced a valid snapshot; keeping current active version"
		exit 1
	fi

	promote_candidate
	log "INFO: update complete"
}

MODE=""
case "${1:-}" in
--download) MODE="download" ;;
--apply) MODE="apply" ;;
--restore-lkg) MODE="restore-lkg" ;;
"") MODE="full" ;;
*)
	echo "Usage: $0 [--download|--apply|--restore-lkg]" >&2
	exit 1
	;;
esac

case "$MODE" in
download) do_download ;;
apply) do_apply ;;
restore-lkg) do_restore_lkg ;;
full) do_download ;;
esac
