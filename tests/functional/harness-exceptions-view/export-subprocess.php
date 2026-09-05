<?php
/**
 * Subprocesso isolado — POST export_vip_list (handler real, exit com texto).
 * Entrada JSON stdin: {"data":{...}}
 * Saida: corpo text/plain (headers nao capturados aqui; teste principal valida conteudo).
 */
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/export-handler-lib.php";

$raw = isset($argv[1]) ? (string)$argv[1] : stream_get_contents(STDIN);
$opts = json_decode($raw, true);
if (!is_array($opts)) {
	fwrite(STDERR, "FAIL JSON invalido\n");
	exit(2);
}
$_POST = array("export_vip_list" => "1");
$_GET = array();
$_SERVER["REQUEST_METHOD"] = "POST";
$GLOBALS["l7he_data"] = isset($opts["data"]) && is_array($opts["data"])
	? $opts["data"] : l7he_vip_data(array());
eval(l7he_export_handler_source());
