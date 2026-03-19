<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV
/*
 * Visao de eventos/logs do layer7d.
 * V1: aponta para o syslog do sistema e mostra ajuda operacional.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$pgtitle = array(gettext("Services"), gettext("Layer 7"), gettext("Events"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7 - events"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("events"); ?>

		<p class="layer7-lead"><?= gettext("Esta pagina concentra a orientacao operacional para leitura dos eventos do daemon enquanto a visao estruturada de eventos ainda nao faz parte da V1."); ?></p>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Onde consultar"); ?></h3>
			<p><?= gettext("Os eventos do layer7d sao enviados ao syslog do sistema com facility LOG_DAEMON e ident 'layer7d'."); ?></p>
			<p><?= gettext("No pfSense, abra os logs do sistema e filtre por 'layer7d' para acompanhar start, stop, reload e mensagens de troubleshooting."); ?></p>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Leitura recomendada"); ?></h3>
			<ul>
				<li><?= gettext("Use a pagina de Diagnostics para confirmar PID, comandos uteis e caminho da configuracao ativa."); ?></li>
				<li><?= gettext("Ative syslog remoto em Definicoes quando quiser reter historico fora do appliance."); ?></li>
				<li><?= gettext("Durante o lab, aumente temporariamente o debug para capturar reloads e comportamento do daemon."); ?></li>
			</ul>
		</div>

		<div class="layer7-section">
			<h3 class="layer7-section-title"><?= gettext("Estado da V1"); ?></h3>
			<div class="alert alert-info">
				<?= gettext("A interface ainda nao expoe uma timeline propria de eventos. Nesta fase, o caminho oficial continua a ser o syslog do pfSense."); ?>
			</div>
		</div>
	</div>
</div>
<?php require_once("foot.inc"); ?>
