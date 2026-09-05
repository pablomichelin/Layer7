<?php
/**
 * Fixtures JSON para testes export V6b2b (Node orquestra runPhp).
 * Saida: {"empty":{...},"one":{...},"full":{...},"expected_lines":{...}}
 */
require_once __DIR__ . "/bootstrap.php";

if (!function_exists("l7he_export_data_lines")) {
	function l7he_export_data_lines($data)
	{
		$text = layer7_vip_export_text($data, true);
		$out = array();
		foreach (explode("\n", $text) as $line) {
			$line = rtrim($line, "\r");
			if ($line === "" || (isset($line[0]) && $line[0] === "#")) {
				continue;
			}
			$out[] = $line;
		}
		return $out;
	}
}

$empty = l7he_vip_data(array());
$one = l7he_vip_data(array(
	array("target" => "10.0.0.1", "description" => "Um"),
));
$full = l7he_vip_build_full();

echo json_encode(array(
	"empty" => $empty,
	"one" => $one,
	"full" => $full,
	"expected_lines" => array(
		"empty" => l7he_export_data_lines($empty),
		"one" => l7he_export_data_lines($one),
		"full" => l7he_export_data_lines($full),
	),
), JSON_UNESCAPED_UNICODE);
