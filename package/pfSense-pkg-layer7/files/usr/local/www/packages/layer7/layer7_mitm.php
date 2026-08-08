<?php
##|+PRIV
##|*IDENT=page-services-layer7-mitm
##|*NAME=Services: Layer 7 (MITM)
##|*DESCR=MITM TLS inspection add-on (entitlement gated; DEFER).
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
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("mitm"); ?>
		<div class="layer7-content">
			<p class="layer7-lead">
				<?= htmlspecialchars(l7_t(
				    "Inspecao TLS (MITM) esta diferida (spike 20.7a). " .
				    "Esta pagina so mostra o gate comercial; nao activa decifragem."
				)); ?>
			</p>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header">
					<?= htmlspecialchars(l7_t("Estado do add-on")); ?>
				</div>
				<div class="layer7-admin-block__body">
<?php if (!$unlocked): ?>
					<div class="alert alert-info" role="alert" style="margin-bottom:0;">
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
							· ADR-0025 / ADR-0026 · default OFF
						</p>
					</div>
<?php else: ?>
					<div class="alert alert-success" role="alert" style="margin-bottom:12px;">
						<?= htmlspecialchars(l7_t("Entitlement mitm activo.")); ?>
						<?= htmlspecialchars(l7_t("A implementacao MITM depende de novo GO apos o DEFER 20.7a.")); ?>
					</div>
					<p class="help-block" style="margin:0;">
						features=<?= htmlspecialchars($l7_feat_raw); ?>
					</p>
<?php endif; ?>
				</div>
			</div>

			<div class="layer7-admin-block">
				<div class="layer7-admin-block__header">
					<?= htmlspecialchars(l7_t("Proximos passos (quando houver GO)")); ?>
				</div>
				<div class="layer7-admin-block__body">
					<p class="layer7-lead" style="margin:0;">
						<?= htmlspecialchars(l7_t(
						    "Quando o entitlement e o spike MITM forem GO, esta pagina gerira a CA, " .
						    "o toggle mitm.enabled e a bypass list — nunca activos por defeito no upgrade."
						)); ?>
					</p>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
layer7_render_footer();
include("foot.inc");
