<?php
/*
 * Espelha o padrão bloqueante de layer7_mitm_sync_helper() (ramo produção),
 * sem tocar em /usr/local/etc/layer7 nem em filter_configure.
 *
 * Ordem fiel ao produto:
 *   sysrc layer7_tlsproxy_enable=YES
 *   service layer7-tlsproxy onerestart
 *
 * Uso: PATH=mock-bin:$PATH php php-sync-exec-pattern.php
 */
$marker = getenv("L7_HARNESS_MARKER");
if ($marker !== false && $marker !== "") {
	@file_put_contents($marker, "started " . gmdate("c") . " pid=" . getmypid() . "\n");
}

fwrite(STDOUT, "effective_pre_sync=yes\n");
fflush(STDOUT);

/* Igual ao produto: caminhos absolutos tipicos do FreeBSD. Em harness o PATH
 * do processo pai já aponta mock-bin para "service"/"sysrc" se invocados sem
 * path — mas o produto usa /usr/sbin/service. Para reproduzir o bloqueio com
 * o mesmo argv, preferimos PATH-hijack via wrappers em /tmp ou env. */
$sysrc = getenv("L7_HARNESS_SYSRC");
$service = getenv("L7_HARNESS_SERVICE");
if ($sysrc === false || $sysrc === "") {
	$sysrc = "sysrc";
}
if ($service === false || $service === "") {
	$service = "service";
}

@exec(escapeshellcmd($sysrc) . " layer7_tlsproxy_enable=YES 2>/dev/null");
@exec(escapeshellcmd($service) . " layer7-tlsproxy onerestart 2>/dev/null");

fwrite(STDOUT, "sync=yes\n");
fflush(STDOUT);
if ($marker !== false && $marker !== "") {
	@file_put_contents($marker, "finished " . gmdate("c") . "\n", FILE_APPEND);
}
exit(0);
