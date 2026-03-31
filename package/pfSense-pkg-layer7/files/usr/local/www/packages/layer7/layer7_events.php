<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

$filter = isset($_GET["filter"]) ? trim($_GET["filter"]) : "";
$max_lines = 300;
$log_path = "/var/log/layer7d.log";
$live_lines = 60;

$all_logs = array();
if (file_exists($log_path)) {
	exec("/usr/bin/tail -n " . (int)$max_lines . " " . escapeshellarg($log_path) . " 2>/dev/null", $all_logs);
} elseif (file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -" . $max_lines . " 2>/dev/null", $all_logs);
}

if (isset($_GET["ajax"]) && $_GET["ajax"] === "1") {
	$live_logs = $all_logs;
	if ($filter !== "") {
		$live_logs = array();
		foreach ($all_logs as $line) {
			if (stripos($line, $filter) !== false) {
				$live_logs[] = $line;
			}
		}
	}
	$live_logs = array_slice($live_logs, -$live_lines);
	header("Content-Type: text/plain; charset=utf-8");
	echo implode("\n", $live_logs);
	exit;
}

$filtered_logs = $all_logs;
if ($filter !== "") {
	$filtered_logs = array();
	foreach ($all_logs as $line) {
		if (stripos($line, $filter) !== false) {
			$filtered_logs[] = $line;
		}
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Events"));
include("head.inc");
layer7_render_styles();
?>
<div class="panel panel-default layer7-page">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Layer 7 - Eventos"); ?></h2>
	</div>
	<div class="panel-body">
		<?php layer7_render_tabs("eventos"); ?>
		<div class="layer7-content">

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Monitor ao vivo"); ?></div>
			<div class="layer7-admin-block__body">
				<p class="small text-muted"><?= l7_t("Atualiza automaticamente os ultimos eventos do daemon. Use o filtro abaixo para restringir o fluxo exibido."); ?></p>
			<div class="layer7-toolbar">
				<button type="button" class="btn btn-success btn-sm" id="l7-live-toggle"><?= l7_t("Pausar"); ?></button>
				<button type="button" class="btn btn-default btn-sm" id="l7-live-refresh"><?= l7_t("Atualizar agora"); ?></button>
				<button type="button" class="btn btn-danger btn-sm" id="l7-live-clear"><?= l7_t("Limpar"); ?></button>
				<span id="l7-live-count" class="text-muted" style="font-size:12px; margin-left:8px;"></span>
			</div>
				<pre id="l7-live-view" class="pre-scrollable" style="max-height: 320px; font-size: 12px; white-space: pre-wrap;">Carregando...</pre>
			</div>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header"><?= l7_t("Filtrar logs"); ?></div>
			<div class="layer7-admin-block__body">
				<form method="get" class="form-inline">
					<div class="form-group">
						<input type="text" name="filter" class="form-control" style="width: 320px;" maxlength="100"
							value="<?= htmlspecialchars($filter); ?>" placeholder="<?= l7_t("Ex: enforce, flow_decide, BitTorrent, SIGUSR1..."); ?>" />
					</div>
					<button type="submit" class="btn btn-primary"><?= l7_t("Filtrar"); ?></button>
					<?php if ($filter !== "") { ?>
					<a href="layer7_events.php" class="btn btn-default"><?= l7_t("Limpar"); ?></a>
					<?php } ?>
				</form>
			</div>
		</div>

		<div class="layer7-admin-block">
			<div class="layer7-admin-block__header">
				<?= l7_t("Todos os logs"); ?>
				<span class="badge"><?= count($filtered_logs); ?></span>
			</div>
			<div class="layer7-admin-block__body">
				<?php if ($filter !== "") { ?>
				<p class="small text-muted"><?= l7_t("filtro"); ?>: <?= htmlspecialchars($filter); ?></p>
				<?php } else { ?>
				<?php } ?>
				<?php if (count($filtered_logs) > 0) { ?>
				<pre class="pre-scrollable" style="max-height: 400px; font-size: 12px;"><?= htmlspecialchars(implode("\n", $filtered_logs)); ?></pre>
				<?php } else { ?>
				<div class="alert alert-info">
					<?php if ($filter !== "") { ?>
					<?= l7_t("Nenhum log correspondente ao filtro."); ?>
					<?php } else { ?>
					<?= l7_t("Nenhum log do layer7d encontrado em /var/log/layer7d.log."); ?>
					<?php } ?>
				</div>
				<?php } ?>
			</div>
		</div>

		</div>
	</div>
</div>
<script>
(function() {
	var liveView  = document.getElementById('l7-live-view');
	var toggleBtn = document.getElementById('l7-live-toggle');
	var refreshBtn = document.getElementById('l7-live-refresh');
	var clearBtn  = document.getElementById('l7-live-clear');
	var countEl   = document.getElementById('l7-live-count');
	var paused    = false;
	var timer     = null;
	var refreshMs = 2000;
	var ajaxUrl   = 'layer7_events.php?ajax=1&filter=<?= rawurlencode($filter); ?>';

	/* Buffer acumulado — novas linhas sao sempre adicionadas, nunca removidas */
	var seenLines = [];
	var maxLines  = 500;

	function updateView() {
		if (!liveView) return;
		liveView.textContent = seenLines.length > 0
			? seenLines.join('\n')
			: 'Aguardando eventos...';
		liveView.scrollTop = liveView.scrollHeight;
		if (countEl) {
			countEl.textContent = seenLines.length > 0
				? seenLines.length + ' linha(s)' : '';
		}
	}

	function mergeIncoming(incoming) {
		if (!incoming || incoming.length === 0) return;

		var newLines = [];

		if (seenLines.length === 0) {
			/* Buffer vazio — aceitar tudo */
			newLines = incoming;
		} else {
			/*
			 * Procurar a ultima linha ja vista dentro das incoming.
			 * O que vier depois e novo.
			 */
			var lastSeen   = seenLines[seenLines.length - 1];
			var overlapIdx = -1;
			for (var i = incoming.length - 1; i >= 0; i--) {
				if (incoming[i] === lastSeen) {
					overlapIdx = i;
					break;
				}
			}

			if (overlapIdx >= 0) {
				/* Sobreposicao encontrada — so o que vem depois e novo */
				newLines = incoming.slice(overlapIdx + 1);
			} else {
				/*
				 * Sem sobreposicao (linhas antigas saiam do tail).
				 * Filtrar duplicados recentes para evitar repeticoes.
				 */
				var recentSet = {};
				var window100 = seenLines.slice(-100);
				for (var j = 0; j < window100.length; j++) {
					recentSet[window100[j]] = true;
				}
				newLines = incoming.filter(function(l) {
					return !recentSet[l];
				});
			}
		}

		if (newLines.length === 0) return;

		for (var k = 0; k < newLines.length; k++) {
			seenLines.push(newLines[k]);
		}
		/* Limitar tamanho maximo do buffer */
		if (seenLines.length > maxLines) {
			seenLines = seenLines.slice(seenLines.length - maxLines);
		}
		updateView();
	}

	function fetchLive() {
		if (paused || !liveView) return;
		var xhr = new XMLHttpRequest();
		xhr.open('GET', ajaxUrl, true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState !== 4 || xhr.status !== 200) return;
			var text = xhr.responseText.trim();
			if (!text) return; /* Resposta vazia — manter o buffer actual */
			var incoming = text.split('\n').filter(function(l) {
				return l.trim() !== '';
			});
			mergeIncoming(incoming);
		};
		xhr.send(null);
	}

	function schedule() {
		if (timer) clearInterval(timer);
		timer = setInterval(fetchLive, refreshMs);
	}

	if (toggleBtn) {
		toggleBtn.addEventListener('click', function() {
			paused = !paused;
			toggleBtn.textContent   = paused ? '<?= l7_t("Retomar") ?>' : '<?= l7_t("Pausar") ?>';
			toggleBtn.className     = paused
				? 'btn btn-warning btn-sm'
				: 'btn btn-success btn-sm';
			if (!paused) fetchLive();
		});
	}

	if (refreshBtn) {
		refreshBtn.addEventListener('click', function() { fetchLive(); });
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			seenLines = [];
			updateView();
		});
	}

	/* Carregar contexto inicial */
	liveView.textContent = 'Aguardando eventos...';
	fetchLive();
	schedule();
})();
</script>
<?php layer7_render_footer(); ?>
<?php require_once("foot.inc"); ?>
