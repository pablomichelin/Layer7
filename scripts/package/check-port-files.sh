#!/bin/sh
# Garante que cada entrada em pkg-plist (exceto binário gerado no build) existe em files/.
# Correr a partir da raiz do clone: sh scripts/package/check-port-files.sh
set -e
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PORT="$ROOT/package/pfSense-pkg-layer7"
PLIST="$PORT/pkg-plist"
err=0

if [ ! -f "$PLIST" ]; then
	echo "check-port-files: pkg-plist em falta: $PLIST" >&2
	exit 1
fi

while IFS= read -r line || [ -n "$line" ]; do
	case "$line" in
	'' | \#*) continue ;;
	@*) continue ;;
	sbin/layer7d) continue ;;
	esac

	case "$line" in
	etc/inc/*)
		rel="$PORT/files/$line"
		;;
	%%DATADIR%%/*)
		rel="$PORT/files/usr/local/share/pfSense-pkg-layer7/${line##*/}"
		;;
	*)
		rel="$PORT/files/usr/local/$line"
		;;
	esac

	if [ ! -e "$rel" ]; then
		echo "check-port-files: plist='$line' -> não existe: $rel" >&2
		err=1
	fi
done < "$PLIST"

if [ "$err" -ne 0 ]; then
	echo "check-port-files: FALHOU" >&2
	exit 1
fi
echo "check-port-files: OK"
