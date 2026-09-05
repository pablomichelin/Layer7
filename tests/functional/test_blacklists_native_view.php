<?php
/**
 * V14 Blacklists — gate estático view nativa (backend congelado — só visual).
 */
$root = dirname(__DIR__, 2);
$path = $root . "/package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_blacklists.php";
$src = file_get_contents($path);
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$required = array(
	'IDENT=page-services-layer7-blacklists' => 'privilege IDENT',
	'$_POST["do_download"]' => 'handler download (prefixo)',
	'$_POST["save_rule"]' => 'handler save_rule (prefixo)',
	'$_POST["delete_rule"]' => 'handler delete_rule (prefixo)',
	'$_POST["save_whitelist"]' => 'handler save_whitelist (prefixo)',
	'$_POST["save_cat_sites"]' => 'handler save_cat_sites (prefixo)',
	'$_POST["delete_cat_sites"]' => 'handler delete_cat_sites (prefixo)',
	'$_POST["save_settings"]' => 'handler save_settings (prefixo)',
	'head.inc' => 'head.inc',
	'foot.inc' => 'foot.inc',
	'layer7_render_tabs("blacklists")' => 'tabs blacklists',
	'id="l7-blacklists-root"' => 'raiz',
	'id="l7-download"' => 'ancora download',
	'id="l7-rules"' => 'ancora rules',
	'id="l7-custom"' => 'ancora custom',
	'id="l7-whitelist"' => 'ancora whitelist',
	'id="l7-bl-settings"' => 'ancora settings',
	'action="layer7_blacklists.php#l7-download"' => 'form download',
	'action="layer7_blacklists.php#l7-rules"' => 'form rules',
	'name="rule_cats[]"' => 'checkbox categorias',
	'name="whitelist"' => 'campo whitelist',
	'name="cat_sites"' => 'campo cat_sites',
	'name="max_entries"' => 'campo max_entries',
	'min="1000000" max="5000000"' => 'bounds max_entries',
	'id="download_log"' => 'log download',
	'id="rule_cat_filter"' => 'filtro categorias',
	'id="rule_cats_wrap"' => 'wrap categorias',
	'function filterRuleCats' => 'js filterRuleCats',
	'function toggleAllRuleCats' => 'js toggleAllRuleCats',
	'function pollDownloadLog' => 'js pollDownloadLog',
	'setInterval(pollDownloadLog, 2000)' => 'polling 2000ms',
	'300000' => 'polling timeout 300000ms',
	'layer7_bl_ajax.php?action=progress' => 'url ajax progresso',
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
	'layer7-form-card' => 'form-card',
	'style=' => 'style inline',
);

foreach ($forbidden as $needle => $name) {
	check(strpos($src, $needle) === false, "ausente {$name}");
}

if ($fail) { fwrite(STDERR, "SOME BLACKLISTS NATIVE VIEW TESTS FAILED\n"); exit(1); }
echo "ALL BLACKLISTS NATIVE VIEW TESTS PASSED\n";
exit(0);
