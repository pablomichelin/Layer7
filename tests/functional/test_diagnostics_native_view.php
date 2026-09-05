<?php
/**
 * BG-174 / V8 Diagnósticos — gate estático da view nativa.
 *
 *   php tests/functional/test_diagnostics_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php";
if (!is_file($path)) {
	fwrite(STDERR, "FAIL ficheiro em falta: {$path}\n");
	exit(1);
}
$src = file_get_contents($path);
$fail = 0;
function check($cond, $name)
{
	global $fail;
	if ($cond) {
		echo "PASS: $name\n";
	} else {
		echo "FAIL: $name\n";
		$fail = 1;
	}
}
function has_str($src, $needle)
{
	return strpos($src, $needle) !== false;
}

$required = array(
	'IDENT=page-services-layer7-diagnostics' => 'privilege IDENT',
	'function layer7_diag_pf_required_tables_ok' => 'helper pf_required_tables_ok',
	'function layer7_diag_table_referenced' => 'helper table_referenced',
	'function layer7_diag_table_ready' => 'helper table_ready',
	'function layer7_diag_pf_required_tables_ready' => 'helper pf_required_tables_ready',
	'$_POST["send_sigusr1"]' => 'handler send_sigusr1',
	'$_POST["send_sighup"]' => 'handler send_sighup',
	'$_POST["configure_anti_doh"]' => 'handler configure_anti_doh',
	'$_POST["remove_anti_doh"]' => 'handler remove_anti_doh',
	'$_POST["repair_pf_tables"]' => 'handler repair_pf_tables',
	'$_POST["report_error"]' => 'handler report_error',
	'$_POST["copy_error_report"]' => 'handler copy_error_report',
	'layer7_error_report_safe_context' => 'error report context',
	'layer7_error_report_sanitize_summary' => 'error report sanitize',
	'layer7_error_report_issue_url' => 'error report issue url',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("diagnosticos")' => 'tabs diagnosticos',
	'id="l7-diag-root"' => 'raiz funcional',
	'id="l7-ipv6-limit"' => 'aviso IPv6',
	'id="l7-diag-summary"' => 'painel resumo',
	'id="l7-pf"' => 'ancora PF',
	'id="l7-dns"' => 'ancora DNS',
	'id="l7-diag-pf-details"' => 'painel detalhes PF',
	'id="l7-actions"' => 'ancora acoes',
	'id="l7-report-error"' => 'ancora reportar erro',
	'id="l7-diag-logs"' => 'painel logs',
	'panel panel-default' => 'painel nativo',
	'dl-horizontal' => 'dl nativo',
	'data-toggle="collapse"' => 'collapse bootstrap',
	'pre-scrollable' => 'dumps pre-scrollable',
	'id="error_summary"' => 'textarea error_summary',
	'name="error_summary"' => 'campo error_summary',
	'maxlength="500"' => 'maxlength 500',
	'rows="3"' => 'rows 3',
	'name="report_error" value="1"' => 'submitter report_error',
	'name="copy_error_report" value="1"' => 'submitter copy_error_report',
	'name="repair_pf_tables" value="1"' => 'submitter repair_pf_tables',
	'name="remove_anti_doh" value="1"' => 'submitter remove_anti_doh',
	'name="configure_anti_doh" value="1"' => 'submitter configure_anti_doh',
	'name="send_sigusr1" value="1"' => 'submitter send_sigusr1',
	'name="send_sighup" value="1"' => 'submitter send_sighup',
	'JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP' => 'confirm anti-DoH JSON_HEX',
	'onsubmit=\'return confirm(' => 'confirm via onsubmit aspas simples',
	'l7d-snippet' => 'collapse snippet PF',
	'l7d-hook' => 'collapse hook',
	'l7d-rulesdebug' => 'collapse rules.debug',
	'l7d-pfctlsr' => 'collapse pfctl -sr',
	'text-center text-muted' => 'credito nativo',
	'https://www.systemup.inf.br' => 'URL credito Systemup',
	'Resumo operacional' => 'copy resumo',
	'Tabelas PF (enforcement)' => 'copy PF',
	'Anti-bypass DNS' => 'copy DNS',
	'Reportar erro' => 'copy reportar erro',
	'Logs recentes' => 'copy logs',
);

foreach ($required as $needle => $name) {
	check(has_str($src, $needle), "preserva {$name}");
}

$forbidden = array(
	'layer7_render_styles()' => 'layer7_render_styles()',
	'layer7_render_footer()' => 'layer7_render_footer()',
	'layer7-page' => 'layer7-page',
	'layer7-content' => 'layer7-content',
	'layer7-admin-block' => 'layer7-admin-block',
	'layer7-form-card' => 'layer7-form-card',
	'layer7-inline-form' => 'layer7-inline-form',
	'layer7-summary' => 'layer7-summary',
	'layer7-lead' => 'layer7-lead',
	'l7-report-flow' => 'l7-report-flow',
	'l7-report-step' => 'l7-report-step',
	'l7-report-chip' => 'l7-report-chip',
	'l7-report-meta' => 'l7-report-meta',
	'l7-report-privacy' => 'l7-report-privacy',
	'l7-report-actions' => 'l7-report-actions',
	'l7-report-url' => 'l7-report-url',
	'style=' => 'atributo style= layout',
	'<style' => '<style',
	'onclick="return confirm(' => 'confirm onclick duplicado',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

if ($fail) {
	fwrite(STDERR, "SOME DIAGNOSTICS NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL DIAGNOSTICS NATIVE VIEW TESTS PASSED\n";
exit(0);
