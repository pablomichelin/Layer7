<?php
/**
 * BG-174 / V7 Eventos — gate estático da view nativa.
 *
 *   php tests/functional/test_events_native_view.php
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_events.php";
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
	'IDENT=page-services-layer7-events' => 'privilege IDENT',
	'function layer7_events_human_bytes' => 'human_bytes',
	'function layer7_events_render_row' => 'render_row local',
	'$max_lines = 300' => 'limite 300 linhas',
	'$live_lines = 60' => 'limite 60 live',
	'ajax=1' => 'endpoint ajax',
	'JSON_UNESCAPED_UNICODE' => 'ajax JSON flags',
	'layer7_events_line_matches' => 'filtro line_matches',
	'layer7_event_explain_line' => 'explain_line',
	'layer7_log_storage_status' => 'storage status',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("eventos")' => 'tabs eventos',
	'id="l7-events-root"' => 'raiz funcional',
	'id="l7-events-storage"' => 'painel armazenamento',
	'id="l7-events-source"' => 'painel fontes',
	'id="l7-events-legend"' => 'legenda',
	'id="l7-events-live"' => 'monitor ao vivo',
	'id="l7-events-filter"' => 'filtro GET',
	'id="l7-events-all"' => 'todos os logs',
	'id="l7-live-view" class="list-group pre-scrollable"' => 'live view pre-scrollable',
	'list-group-item' => 'linha compacta list-group',
	'id="l7-show-tech"' => 'checkbox detalhe tecnico',
	'id="l7-live-toggle"' => 'botao pausar',
	'id="l7-live-refresh"' => 'botao refresh',
	'id="l7-live-clear"' => 'botao limpar visualizacao',
	"method=\"get\"" => 'form GET',
	'action="layer7_events.php"' => 'action layer7_events.php',
	'name="source"' => 'campo source',
	'name="filter"' => 'campo filter',
	'maxlength="100"' => 'filter maxlength 100',
	'layer7_events.php?source=events' => 'link source events',
	'layer7_events.php?source=operational' => 'link source operational',
	'panel panel-default' => 'painel nativo',
	'pre-scrollable' => 'raw pre-scrollable',
	'details class="l7-event-raw-wrap"' => 'raw acessivel sem JS',
	'l7-events-show-tech' => 'chave localStorage',
	'var maxLines  = 500' => 'buffer 500',
	'var refreshMs = 2000' => 'intervalo 2000ms',
	'text-center text-muted' => 'credito nativo',
	'https://www.systemup.inf.br' => 'URL credito Systemup',
	'Armazenamento de logs' => 'copy armazenamento',
	'Monitor ao vivo' => 'copy monitor',
	'Eventos de trafego' => 'copy fonte trafego',
	'Log operacional' => 'copy fonte operacional',
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
	'layer7-toolbar' => 'layer7-toolbar',
	'l7-event-list' => 'lista CSS custom',
	'l7-event-empty' => 'empty CSS custom',
	'classList.add(\'l7-show-tech\')' => 'classe CSS l7-show-tech',
	'l7-event-row--' => 'modificador CSS custom',
	'l7-event-meta' => 'meta CSS custom',
	'l7-event-title' => 'title CSS custom',
	'l7-event-summary' => 'summary CSS custom',
	'l7-event-raw"' => 'classe raw div antiga',
	'style=' => 'atributo style= layout',
	'<style' => '<style',
);

foreach ($forbidden as $needle => $name) {
	check(!has_str($src, $needle), "ausente {$name}");
}

if ($fail) {
	fwrite(STDERR, "SOME EVENTS NATIVE VIEW TESTS FAILED\n");
	exit(1);
}
echo "ALL EVENTS NATIVE VIEW TESTS PASSED\n";
exit(0);
