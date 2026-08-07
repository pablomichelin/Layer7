<?php
##|+PRIV
##|*IDENT=page-services-layer7-mitm
##|*NAME=Services: Layer 7 (MITM)
##|*DESCR=MITM TLS inspection add-on (entitlement gated).
##|*MATCH=layer7_mitm.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

/* Nao usar $ent: head.inc faz foreach ($ifentries as $ent => ...) e sobrescreve. */
$l7_ent = layer7_entitlements();
$unlocked = !empty($l7_ent["has_mitm"]);
$l7_feat_raw = isset($l7_ent["raw"]) ? (string)$l7_ent["raw"] : "";

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("MITM"));
$pglinks = array("", "/packages/layer7/layer7_status.php", "@self");
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= htmlspecialchars(l7_t("Layer 7 - MITM / Inspecao TLS")); ?></h2>
		<?php layer7_render_tabs("mitm"); ?>
	</div>
	<div class="panel-body">
		<div class="layer7-content">
<?php if (!$unlocked): ?>
			<div class="alert alert-info" role="alert">
				<strong><?= htmlspecialchars(l7_t("Add-on nao incluido nesta licenca")); ?></strong>
				<p style="margin: 10px 0 0;">
					<?= htmlspecialchars(l7_t(
					    "A inspecao TLS (MITM) com CA no dominio requer o entitlement \"mitm\". " .
					    "A licenca actual nao inclui este add-on. " .
					    "Sem MITM, o bloqueio HTTPS continua conforme ADR-0017 (DNS sinkhole / pagina HTTP). " .
					    "Contacte a Systemup para upgrade."
					)); ?>
				</p>
				<p style="margin: 10px 0 0; color: #666; font-size: 12px;">
					features=<?= htmlspecialchars($l7_feat_raw !== "" ? $l7_feat_raw : "(base / legado)"); ?>
					· ADR-0025 / ADR-0026 · default OFF; spike 20.7 decide implementacao
				</p>
			</div>
			<p class="layer7-lead">
				<?= htmlspecialchars(l7_t(
				    "Quando o entitlement e o spike MITM forem GO, esta pagina gerira a CA, " .
				    "o toggle mitm.enabled e a bypass list — nunca activos por defeito no upgrade."
				)); ?>
			</p>
<?php else: ?>
			<div class="alert alert-success" role="alert">
				<?= htmlspecialchars(l7_t("Entitlement mitm activo.")); ?>
				<?= htmlspecialchars(l7_t("A implementacao MITM depende do spike 20.7 (GO/DEFER) — por agora so o gate comercial esta aberto.")); ?>
			</div>
			<p class="layer7-lead">
				features=<?= htmlspecialchars($l7_feat_raw); ?>
			</p>
<?php endif; ?>
		</div>
	</div>
</div>
<?php
layer7_render_footer();
include("foot.inc");
