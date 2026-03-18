<?php
##|+PRIV
##|*IDENT=page-services-layer7
##|*NAME=Services: Layer 7
##|*DESCR=Allow access to the Layer 7 package page.
##|*MATCH=layer7_status.php*
##|-PRIV
/*
 * Layer 7 — página mínima (Bloco 5). Operacional completo: Bloco 9.
 */

require_once("guiconfig.inc");

$pgtitle = array(gettext("Services"), gettext("Layer 7"));
include("head.inc");
?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?= gettext("Layer 7"); ?></h2>
	</div>
	<div class="panel-body">
		<p><?= gettext("Pacote instalado. Binário /usr/local/sbin/layer7d (C). Config omissão: /usr/local/etc/layer7.json — ver layer7.json.sample."); ?></p>
		<p><?= gettext("Versão: System / Package Manager ou pkg info pfSense-pkg-layer7."); ?></p>
	</div>
</div>
<?php require_once("foot.inc"); ?>

