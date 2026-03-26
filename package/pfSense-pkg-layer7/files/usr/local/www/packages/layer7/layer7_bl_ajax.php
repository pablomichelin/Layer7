<?php
##|+PRIV
##|*IDENT=page-services-layer7-blacklists
##|*NAME=Services: Layer 7 (blacklists ajax)
##|*DESCR=Allow access to Layer 7 blacklists ajax.
##|*MATCH=layer7_bl_ajax.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$action = isset($_GET["action"]) ? $_GET["action"] : "";

if ($action === "progress") {
	header("Content-Type: text/plain; charset=utf-8");
	header("Cache-Control: no-cache, no-store");
	echo layer7_bl_download_status();
	exit;
}

header("HTTP/1.1 400 Bad Request");
header("Content-Type: text/plain; charset=utf-8");
echo "Unknown action";
