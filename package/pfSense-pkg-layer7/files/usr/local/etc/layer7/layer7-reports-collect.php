#!/usr/local/bin/php
<?php
require_once("/usr/local/pkg/layer7.inc");

$result = layer7_reports_ingest_log_incremental();
if (!is_array($result) || empty($result["ok"])) {
	fwrite(STDERR, "layer7-reports-collect: ingest failed\n");
	exit(1);
}
exit(0);
