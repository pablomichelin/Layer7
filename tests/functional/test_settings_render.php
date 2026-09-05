<?php
/**
 * V15 Settings — render fixtures (default/custom/erro/update/licença/backup).
 */
require_once __DIR__ . "/harness-settings-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$def = l7st_render(array());
check(strpos($def, 'id="l7-servico"') !== false, "default: painel servico");
check(strpos($def, 'name="save_scope" value="general"') !== false, "default: scope general");
check(strpos($def, 'name="save_scope" value="reports"') !== false, "default: scope reports");
check(strpos($def, 'id="l7_pkg_update"') !== false, "default: bloco update");

$custom = l7st_render(array(
	"reports_cfg" => array(
		"enabled" => true,
		"retention_days" => 45,
		"collect_interval" => 15,
		"event_log_enabled" => true,
		"event_retention_days" => 11,
		"event_max_mb" => 200,
		"event_interfaces" => array("em0"),
	),
));
check(strpos($custom, 'id="l7_rpt_custom"') !== false, "custom: campo retencao executivo");
check(strpos($custom, 'value="45"') !== false, "custom: valor retencao executivo");
check(strpos($custom, 'id="l7_evt_custom"') !== false, "custom: campo retencao detalhado");

$err = l7st_render(array(
	"input_errors" => array("Erro sintetico fixture"),
	"update_err" => "Update sintetico falhou",
	"backup_err" => "Backup sintetico falhou",
));
check(strpos($err, "Erro sintetico fixture") !== false, "erro: input_errors");
check(strpos($err, "Update sintetico falhou") !== false, "erro: update_err");
check(strpos($err, "Backup sintetico falhou") !== false, "erro: backup_err");

$upd = l7st_render(array(
	"update_info" => array(
		"current" => "1.9.78",
		"latest" => "1.9.79",
		"tag" => "v1.9.79",
		"pkg_url" => "https://github.com/pablomichelin/Layer7/releases/download/v1.9.79/pfSense-pkg-layer7-1.9.79.pkg",
		"name" => "v1.9.79",
	),
));
check(strpos($upd, 'name="do_update"') !== false, "update: botao do_update");
check(strpos($upd, 'name="pkg_url"') !== false, "update: hidden pkg_url");

$lic = l7st_render(array(
	"license_status" => array(
		"valid" => false,
		"expired" => false,
		"grace" => false,
		"dev_mode" => false,
		"clock_suspect" => false,
		"hardware_id" => "HW-REG",
		"customer" => "",
		"expiry" => "",
		"days_left" => 0,
		"error" => "sem licenca",
	),
));
check(strpos($lic, 'name="register_license"') !== false, "licenca off: form registar");
check(strpos($lic, 'name="license_code"') !== false, "licenca off: campo codigo");

$lic_on = l7st_render(array(
	"license_status" => array(
		"valid" => true,
		"expired" => false,
		"grace" => false,
		"dev_mode" => false,
		"clock_suspect" => false,
		"hardware_id" => "HW-OK",
		"customer" => "Cliente",
		"expiry" => "2030-01-01",
		"days_left" => 100,
		"error" => "",
	),
));
check(strpos($lic_on, 'name="revoke_license"') !== false, "licenca on: revoke");

$confirm = l7st_render(array(
	"license_status" => array(
		"valid" => true,
		"expired" => false,
		"grace" => false,
		"dev_mode" => false,
		"clock_suspect" => false,
		"hardware_id" => "HW-OK",
		"customer" => "Cliente",
		"expiry" => "2030-01-01",
		"days_left" => 100,
		"error" => "",
	),
));
check(strpos($confirm, "onclick='return confirm(") !== false, "confirmDOM: aspas simples revoke");
check(strpos($confirm, "Deseja revogar a licenca activa") !== false, "confirmDOM: texto revoke");
check(strpos($confirm, "Substituir a configuracao actual") !== false, "confirmDOM: texto import");

$upd_confirm = l7st_render(array(
	"update_info" => array(
		"current" => "1.9.78",
		"latest" => "1.9.79",
		"tag" => "v1.9.79",
		"pkg_url" => "https://github.com/pablomichelin/Layer7/releases/download/v1.9.79/pfSense-pkg-layer7-1.9.79.pkg",
		"name" => "v1.9.79",
	),
));
check(strpos($upd_confirm, 'name="do_update"') !== false, "update: botao do_update");
check(strpos($upd_confirm, 'id="l7_btn_check_update"') !== false, "update: botao ajax");

echo $fail ? "" : "ALL SETTINGS RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
