<?php
/**
 * BG-174 / V1-B — payload real dos formulários batch, medido no HTML
 * renderizado pela view (fixtures sintéticos). Não estima n+1/n+2.
 *
 * CSRF: as Form_* pinadas não emitem token; no appliance o csrf-magic
 * entra via head.inc. A conta reporta csrf_simulado=1 à parte.
 *
 *   php tests/functional/test_devices_batch_payload.php
 */
require_once __DIR__ . "/harness-devices-view/bootstrap.php";

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

$max_input_vars = 1000;
$csrf_sim = 1;

foreach (array(0, 1, 60, 674) as $n) {
	$html = l7h_render(array(
		"get" => array("mode" => "batch"),
		"inventory" => l7h_inventory($n),
	));
	$alias_form = l7h_form_inner($html, "l7-form-aliases");
	$assign_form = l7h_form_inner($html, "l7-form-assign");
	check($alias_form !== "", "n={$n}: form aliases renderizado");
	check($assign_form !== "", "n={$n}: form assign renderizado");

	$alias_names = l7h_named_attrs($alias_form);
	$assign_names = l7h_named_attrs($assign_form);
	$n_alias = l7h_count_names($alias_names, "alias[");
	$n_save = l7h_count_names($alias_names, "save_aliases");
	$n_macs = l7h_count_names($assign_names, "assign_macs[]");
	$n_grp = l7h_count_names($assign_names, "assign_group");
	$n_asg = l7h_count_names($assign_names, "assign_to_group");

	check($n_alias === $n, "n={$n}: {$n} inputs alias[MAC] no HTML");
	check($n_save === 1, "n={$n}: um botao save_aliases");
	check($n_macs === $n, "n={$n}: {$n} assign_macs[] no HTML");
	check($n_grp === 1, "n={$n}: um assign_group");
	check($n_asg === 1, "n={$n}: um assign_to_group");
	check(l7h_count_names($alias_names, "assign_macs[]") === 0, "n={$n}: aliases isolado (sem assign_macs)");
	check(l7h_count_names($assign_names, "alias[") === 0, "n={$n}: assign isolado (sem alias)");
	check(l7h_count_names($alias_names, "assign_to_group") === 0, "n={$n}: aliases isolado (sem assign_to_group)");
	check(l7h_count_names($assign_names, "save_aliases") === 0, "n={$n}: assign isolado (sem save_aliases)");

	$alias_vars = count($alias_names);
	$assign_vars = count($assign_names);
	$alias_with_csrf = $alias_vars + $csrf_sim;
	$assign_with_csrf = $assign_vars + $csrf_sim;
	$combined_rendered = $alias_vars + $assign_vars;
	$combined_with_csrf = $combined_rendered + $csrf_sim;

	echo "PAYLOAD n={$n} alias_html={$alias_vars} assign_html={$assign_vars}"
		. " alias+csrf={$alias_with_csrf} assign+csrf={$assign_with_csrf}"
		. " combined_html={$combined_rendered} combined+csrf={$combined_with_csrf}\n";

	check($alias_with_csrf < $max_input_vars, "n={$n}: POST aliases+csrf < {$max_input_vars}");
	check($assign_with_csrf < $max_input_vars, "n={$n}: POST assign+csrf < {$max_input_vars}");
}

$html_combo = l7h_render(array(
	"get" => array("mode" => "batch"),
	"inventory" => l7h_inventory(674),
));
$a = count(l7h_named_attrs(l7h_form_inner($html_combo, "l7-form-aliases")));
$b = count(l7h_named_attrs(l7h_form_inner($html_combo, "l7-form-assign")));
check(($a + $b + $csrf_sim) > $max_input_vars, "674 combinado+csrf (baseline) > max_input_vars=1000");

if ($fail) {
	fwrite(STDERR, "SOME DEVICES BATCH PAYLOAD TESTS FAILED\n");
	exit(1);
}
echo "ALL DEVICES BATCH PAYLOAD TESTS PASSED\n";
exit(0);
