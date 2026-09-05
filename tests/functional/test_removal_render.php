<?php
/**
 * V11 Remoção — render fixtures (3 estados + adversarial).
 */
require_once __DIR__ . "/harness-removal-view/bootstrap.php";
$fail = 0;
function check($c, $n) { global $fail; if ($c) echo "PASS: $n\n"; else { echo "FAIL: $n\n"; $fail = 1; } }

$ready = l7hr_render(array("pkg_installed" => true, "job_running" => false));
check(strpos($ready, 'name="keep_license"') !== false, "installed: checkbox licenca");
check(strpos($ready, 'name="keep_config"') !== false, "installed: checkbox config");
check(strpos($ready, 'name="layer7_remove_confirm"') !== false, "installed: confirmacao");
check(strpos($ready, 'name="layer7_pkg_remove_do"') !== false, "installed: submitter");
check(strpos($ready, "prevalece") !== false, "installed: aviso precedencia");

$missing = l7hr_render(array("pkg_installed" => false));
check(strpos($missing, 'name="keep_license"') === false, "notinstalled: sem form licenca");
check(strpos($missing, "nao esta instalado") !== false, "notinstalled: alerta estado");

$running = l7hr_render(array("pkg_installed" => true, "job_running" => true));
check(strpos($running, 'name="layer7_pkg_remove_do"') === false, "running: sem submitter");
check(strpos($running, "pedido de remocao") !== false, "running: alerta job");

$log_adv = '/tmp/l7_"<script>.log';
$adv = l7hr_render(array("log_rm" => $log_adv));
check(strpos($adv, htmlspecialchars($log_adv)) !== false, "adversarial: log escapado");

$err = l7hr_render(array("input_errors" => array('Digite REMOVER na caixa de confirmacao.')));
check(strpos($err, "Digite REMOVER") !== false, "erro: mensagem input");

echo $fail ? "" : "ALL REMOVAL RENDER TESTS PASSED\n";
exit($fail ? 1 : 0);
