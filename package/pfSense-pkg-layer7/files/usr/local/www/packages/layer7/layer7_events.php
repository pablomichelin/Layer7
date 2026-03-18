<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV
/*
 * Visão de eventos/logs do layer7d.
 * V1: aponta para o syslog do sistema e mostra ajuda.
 */

require_once("guiconfig.inc");

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Events"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 — events"); ?></h2>
	</div>
	<div class="panel-body">
		<p><a href="layer7_status.php"><?= gettext("← Estado"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_settings.php"><?= gettext("Definições"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_policies.php"><?= gettext("Políticas"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_exceptions.php"><?= gettext("Exceções"); ?></a>
		&nbsp;|&nbsp; <a href="layer7_diagnostics.php"><?= gettext("Diagnostics"); ?></a></p>

		<p class="text-muted">
			<?= gettext("Os eventos do daemon layer7d são registados no syslog do sistema (facilidade LOG_DAEMON, ident 'layer7d')."); ?>
			<?= gettext("Utilize a página de logs do pfSense para filtrar por 'layer7d' ou configure syslog remoto para coletar estes eventos."); ?>
		</p>

		<p class="text-muted small">
			<?= gettext("Versões futuras poderão expor aqui eventos estruturados conforme o modelo em docs/core/event-model.md."); ?>
		</p>
	</div>
</div>
<?php require_once("foot.inc"); ?>

