<?php
/**
 * V15 Settings — gate estático view nativa (backend congelado — só visual).
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_settings.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$required = array(
	'IDENT=page-services-layer7-settings' => 'privilege IDENT',
	'$_POST["export_config"]' => 'handler export (prefixo)',
	'$_POST["import_config"]' => 'handler import (prefixo)',
	'$_POST["check_update"]' => 'handler check_update (prefixo)',
	'$_POST["do_update"]' => 'handler do_update (prefixo)',
	'$_POST["register_license"]' => 'handler register_license (prefixo)',
	'$_POST["revoke_license"]' => 'handler revoke_license (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("settings")' => 'tabs settings',
	'id="l7-settings-root"' => 'raiz',
	'id="l7-servico"' => 'ancora servico',
	'id="l7-relatorios"' => 'ancora relatorios',
	'id="l7-sistema"' => 'ancora sistema',
	'id="l7_pkg_update"' => 'ancora pkg_update',
	'action="layer7_settings.php#l7-servico"' => 'form general',
	'action="layer7_settings.php#l7-relatorios"' => 'form reports',
	'name="iface_sel[]"' => 'arrays iface_sel',
	'name="block_quic_iface_sel[]"' => 'arrays block_quic',
	'name="reports_iface_sel[]"' => 'arrays reports_iface',
	'name="enabled" id="l7s-enabled" value="1"' => 'checkbox enabled value=1',
	'name="reports_enabled"' => 'checkbox reports_enabled',
	'name="reports_event_log_enabled"' => 'checkbox reports_event_log_enabled',
	'name="register_license" value="1"' => 'submitter register_license',
	'name="revoke_license" value="1"' => 'submitter revoke_license',
	'name="export_config" value="1"' => 'submitter export_config',
	'name="import_config" value="1"' => 'submitter import_config',
	'name="import_file"' => 'campo import_file',
	'accept=".json"' => 'accept json',
	'name="pkg_url"' => 'hidden pkg_url',
	'name="do_update" value="1"' => 'submitter do_update',
	'name="check_update" value="1"' => 'submitter check_update',
	'id="l7_btn_check_update"' => 'botao ajax update',
	'id="l7_check_update_post"' => 'form compat update',
	'data-l7-update-cfg' => 'cfg update JS',
	'layer7_settings_update.js' => 'src update JS',
	'id="l7_rpt_preset"' => 'preset retencao executivo',
	'id="l7_rpt_custom"' => 'campo custom executivo',
	'id="l7_evt_preset"' => 'preset retencao detalhado',
	'id="l7_evt_custom"' => 'campo custom detalhado',
	"getElementById('l7_rpt_custom')" => 'toggle retention executivo',
	"getElementById('l7_evt_custom')" => 'toggle retention detalhado',
	"classList.remove('hidden')" => 'retention primitivo hidden',
	"onclick='return confirm(" => 'confirmDOM aspas simples',
	'text-center text-muted' => 'credito nativo',
);

foreach ($required as $needle => $name) {
	check(strpos($src, $needle) !== false, "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'render_styles',
	'layer7_render_footer()' => 'render_footer',
	'layer7-admin-block' => 'admin-block',
	'layer7-content' => 'layer7-content',
	'layer7-page' => 'layer7-page',
	'layer7-summary' => 'layer7-summary',
	'style=' => 'style inline',
	'onclick="return confirm(' => 'onclick confirm aspas duplas',
);

foreach ($forbidden as $needle => $name) {
	check(strpos($src, $needle) === false, "ausente {$name}");
}

if ($fail) { fwrite(STDERR, "SOME SETTINGS NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL SETTINGS NATIVE VIEW TESTS PASSED\n";
exit(0);
