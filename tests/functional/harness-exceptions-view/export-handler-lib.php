<?php
/**
 * Extraccao do handler export_vip_list (fonte congelada layer7_exceptions.php).
 */
if (!function_exists("l7he_export_handler_source")) {
	function l7he_export_handler_source()
	{
		$src = l7he_view_source(L7HE_EXCEPTIONS);
		$handler_start = strpos($src, 'if ($_POST["export_vip_list"] ?? false) {');
		$handler_end = strpos(
			$src,
			'if ($_POST["import_vip_list"] ?? false) {',
			$handler_start === false ? 0 : $handler_start
		);
		if ($handler_start === false || $handler_end === false || $handler_end <= $handler_start) {
			fwrite(STDERR, "FAIL export handler nao encontrado\n");
			exit(2);
		}
		return substr($src, $handler_start, $handler_end - $handler_start);
	}
}

if (!function_exists("l7he_export_handler_instrument_exit")) {
	function l7he_export_handler_instrument_exit($handler)
	{
		$replaced = preg_replace(
			'/\bexit\s*;/',
			'throw new L7heExportProbeExit();',
			$handler,
			1,
			$count
		);
		if ($count !== 1 || !is_string($replaced)) {
			fwrite(STDERR, "FAIL export handler exit nao instrumentado\n");
			exit(2);
		}
		return $replaced;
	}
}
