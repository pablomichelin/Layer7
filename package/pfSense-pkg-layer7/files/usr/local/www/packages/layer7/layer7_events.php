<?php
##|+PRIV
##|*IDENT=page-services-layer7-events
##|*NAME=Services: Layer 7 (events)
##|*DESCR=View Layer 7 daemon events.
##|*MATCH=layer7_events.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("/usr/local/pkg/layer7.inc");

function layer7_events_human_bytes($bytes)
{
	$bytes = max(0, (int)$bytes);
	if ($bytes >= 1073741824) {
		return number_format($bytes / 1073741824, 1) . " GiB";
	}
	if ($bytes >= 1048576) {
		return number_format($bytes / 1048576, 1) . " MiB";
	}
	if ($bytes >= 1024) {
		return number_format($bytes / 1024, 1) . " KiB";
	}
	return $bytes . " B";
}

function layer7_events_render_row($ex)
{
	$tone = preg_replace('/[^a-z]/', '', (string)($ex["tone"] ?? "info"));
	if ($tone === "") {
		$tone = "info";
	}
	$itemClass = "list-group-item";
	if ($tone === "block") {
		$itemClass .= " list-group-item-danger";
	} elseif ($tone === "monitor") {
		$itemClass .= " list-group-item-info";
	} elseif ($tone === "warn" || $tone === "warning") {
		$itemClass .= " list-group-item-warning";
	}
	echo '<div class="' . $itemClass . ' l7-event-row">';
	echo '<div class="small text-muted">' . htmlspecialchars((string)($ex["when"] ?? "")) . '</div>';
	echo '<div><strong>' . htmlspecialchars((string)($ex["title"] ?? "")) . '</strong></div>';
	echo '<div class="small">' . htmlspecialchars((string)($ex["summary"] ?? "")) . '</div>';
	echo '<details class="l7-event-raw-wrap"><summary class="small text-muted">' .
	    htmlspecialchars(l7_t("Mostrar detalhe tecnico")) . '</summary>';
	echo '<pre class="pre-scrollable small">' . htmlspecialchars((string)($ex["raw"] ?? "")) . '</pre>';
	echo '</details></div>';
}

$filter = isset($_GET["filter"]) ? trim($_GET["filter"]) : "";
$source = isset($_GET["source"]) ? trim((string)$_GET["source"]) : "events";
if (!in_array($source, array("events", "operational"), true)) {
	$source = "events";
}
$max_lines = 300;
$log_path = $source === "operational"
	? "/var/log/layer7d.log" : "/var/log/layer7-events.log";
$live_lines = 60;
$storage = layer7_log_storage_status();

$all_logs = array();
if (file_exists($log_path)) {
	exec("/usr/bin/tail -n " . (int)$max_lines . " " . escapeshellarg($log_path) . " 2>/dev/null", $all_logs);
} elseif ($source === "operational" && file_exists("/var/log/system.log")) {
	exec("grep 'layer7d' /var/log/system.log | tail -" . $max_lines . " 2>/dev/null", $all_logs);
}

if (isset($_GET["ajax"]) && $_GET["ajax"] === "1") {
	$live_logs = array();
	foreach ($all_logs as $line) {
		if (layer7_events_line_matches($line, $filter)) {
			$live_logs[] = $line;
		}
	}
	$live_logs = array_slice($live_logs, -$live_lines);
	$explained = array();
	foreach ($live_logs as $line) {
		$explained[] = layer7_event_explain_line($line);
	}
	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($explained, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

$filtered_logs = array();
foreach ($all_logs as $line) {
	if (layer7_events_line_matches($line, $filter)) {
		$filtered_logs[] = $line;
	}
}

$pgtitle = array(l7_t("Services"), l7_t("Layer 7"), l7_t("Events"));
include("head.inc");
?>
<?php layer7_render_tabs("eventos"); ?>
<div id="l7-events-root">

<div class="panel panel-default" id="l7-events-storage">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Armazenamento de logs"); ?></h2>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-sm-4">
				<strong><?= l7_t("Operacional"); ?>:</strong>
				<?= htmlspecialchars(layer7_events_human_bytes($storage["operational"]["bytes"])); ?>
				<span class="text-muted">(<?= (int)$storage["operational"]["files"]; ?> <?= l7_t("arquivos"); ?>)</span>
			</div>
			<div class="col-sm-4">
				<strong><?= l7_t("Eventos"); ?>:</strong>
				<?= htmlspecialchars(layer7_events_human_bytes($storage["events"]["bytes"])); ?>
				<span class="text-muted">(<?= (int)$storage["events"]["files"]; ?> <?= l7_t("arquivos"); ?>)</span>
			</div>
			<div class="col-sm-4">
				<strong>SQLite:</strong>
				<?= htmlspecialchars(layer7_events_human_bytes($storage["db_bytes"])); ?>
				<span class="text-muted">/ <?= (int)$storage["db_max_mb"]; ?> MiB</span>
			</div>
		</div>
		<p class="help-block text-muted">
			<?= sprintf(l7_t("Rotacao automatica: %d MiB por arquivo, %d copias antigas."),
			    (int)$storage["max_mb_per_file"], (int)$storage["keep_files"]); ?>
			<?= !empty($storage["event_log_enabled"])
			    ? l7_t("Log detalhado activo.")
			    : l7_t("Log detalhado desactivado; bloqueios continuam auditados."); ?>
		</p>
	</div>
</div>

<div class="panel panel-default" id="l7-events-source">
	<div class="panel-body">
		<a class="btn btn-sm <?= $source === "events" ? "btn-primary" : "btn-default"; ?>"
			href="layer7_events.php?source=events"><?= l7_t("Eventos de trafego"); ?></a>
		<a class="btn btn-sm <?= $source === "operational" ? "btn-primary" : "btn-default"; ?>"
			href="layer7_events.php?source=operational"><?= l7_t("Log operacional"); ?></a>
		<label class="checkbox-inline">
			<input type="checkbox" id="l7-show-tech" />
			<?= l7_t("Mostrar detalhe tecnico"); ?>
		</label>
	</div>
</div>

<div class="panel panel-default" id="l7-events-legend">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("O que significa cada linha"); ?></h2>
	</div>
	<div class="panel-body">
		<ul class="help-block">
			<li><?= l7_t("Trafego observado: o Layer7 viu um acesso e nao bloqueou."); ?></li>
			<li><?= l7_t("Pedido de nome (DNS): o aparelho perguntou o endereco de um site."); ?></li>
			<li><?= l7_t("Nome encontrado (DNS): o sistema descobriu o numero (IP) desse site."); ?></li>
		</ul>
	</div>
</div>

<div class="panel panel-default" id="l7-events-live">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Monitor ao vivo"); ?></h2>
	</div>
	<div class="panel-body">
		<p class="help-block text-muted"><?= l7_t("Atualiza automaticamente os ultimos eventos do daemon. Use o filtro abaixo para restringir o fluxo exibido."); ?></p>
		<p>
			<button type="button" class="btn btn-success btn-sm" id="l7-live-toggle"><?= l7_t("Pausar"); ?></button>
			<button type="button" class="btn btn-default btn-sm" id="l7-live-refresh"><?= l7_t("Atualizar agora"); ?></button>
			<button type="button" class="btn btn-default btn-sm" id="l7-live-clear"><?= l7_t("Limpar visualizacao"); ?></button>
			<span id="l7-live-count" class="text-muted small"></span>
		</p>
		<div id="l7-live-view" class="list-group pre-scrollable" role="log" aria-live="polite">
			<p class="list-group-item text-muted"><?= l7_t("Aguardando eventos..."); ?></p>
		</div>
	</div>
</div>

<div class="panel panel-default" id="l7-events-filter">
	<div class="panel-heading">
		<h2 class="panel-title"><?= l7_t("Filtrar logs"); ?></h2>
	</div>
	<div class="panel-body">
		<form method="get" action="layer7_events.php" class="form-inline">
			<input type="hidden" name="source" value="<?= htmlspecialchars($source); ?>">
			<div class="form-group">
				<label class="sr-only" for="l7-events-filter-input"><?= l7_t("Filtrar"); ?></label>
				<input type="text" name="filter" id="l7-events-filter-input" class="form-control"
					maxlength="100" value="<?= htmlspecialchars($filter); ?>"
					placeholder="<?= l7_t("Ex: TikTok, bloqueado, 172.16..."); ?>" />
			</div>
			<button type="submit" class="btn btn-primary"><?= l7_t("Filtrar"); ?></button>
			<?php if ($filter !== "") { ?>
			<a href="layer7_events.php?source=<?= urlencode($source); ?>" class="btn btn-default"><?= l7_t("Limpar filtro"); ?></a>
			<?php } ?>
		</form>
	</div>
</div>

<div class="panel panel-default" id="l7-events-all">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?= l7_t("Todos os logs"); ?>
			<span class="badge"><?= count($filtered_logs); ?></span>
		</h2>
	</div>
	<div class="panel-body">
		<?php if ($filter !== "") { ?>
		<p class="help-block text-muted"><?= l7_t("filtro"); ?>: <?= htmlspecialchars($filter); ?></p>
		<?php } ?>
		<?php if (count($filtered_logs) > 0) { ?>
		<div id="l7-events-list" class="list-group pre-scrollable">
			<?php foreach ($filtered_logs as $line) {
				layer7_events_render_row(layer7_event_explain_line($line));
			} ?>
		</div>
		<?php } else { ?>
		<div class="alert alert-info">
			<?php if ($filter !== "") { ?>
			<?= l7_t("Nenhum log correspondente ao filtro."); ?>
			<?php } else { ?>
			<?= sprintf(l7_t("Nenhum evento encontrado em %s."), htmlspecialchars($log_path)); ?>
			<?php } ?>
		</div>
		<?php } ?>
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
	var techBox   = document.getElementById('l7-show-tech');
	var paused    = false;
	var timer     = null;
	var refreshMs = 2000;
	var ajaxUrl   = 'layer7_events.php?ajax=1&source=<?= rawurlencode($source); ?>&filter=<?= rawurlencode($filter); ?>';
	var l7WaitingEvents = <?= json_encode(l7_t("Aguardando eventos...")); ?>;
	var l7LineSuffix = <?= json_encode(l7_t("linha(s)")); ?>;
	var techKey = 'l7-events-show-tech';

	/* Buffer acumulado — novas linhas sao sempre adicionadas, nunca removidas */
	var seenLines = [];
	var maxLines  = 500;

	function eventRaw(item) {
		if (!item) return '';
		if (typeof item === 'string') return item;
		return item.raw || '';
	}

	function renderRow(ex) {
		var tone = String((ex && ex.tone) || 'info').replace(/[^a-z]/g, '');
		if (!tone) tone = 'info';
		var itemClass = 'list-group-item l7-event-row';
		if (tone === 'block') {
			itemClass = 'list-group-item list-group-item-danger l7-event-row';
		} else if (tone === 'monitor') {
			itemClass = 'list-group-item list-group-item-info l7-event-row';
		} else if (tone === 'warn' || tone === 'warning') {
			itemClass = 'list-group-item list-group-item-warning l7-event-row';
		}
		var row = document.createElement('div');
		row.className = itemClass;
		var meta = document.createElement('div');
		meta.className = 'small text-muted';
		meta.textContent = (ex && ex.when) ? ex.when : '';
		var titleWrap = document.createElement('div');
		var title = document.createElement('strong');
		title.textContent = (ex && ex.title) ? ex.title : '';
		titleWrap.appendChild(title);
		var summary = document.createElement('div');
		summary.className = 'small';
		summary.textContent = (ex && ex.summary) ? ex.summary : '';
		var details = document.createElement('details');
		details.className = 'l7-event-raw-wrap';
		var summaryEl = document.createElement('summary');
		summaryEl.className = 'small text-muted';
		summaryEl.textContent = <?= json_encode(l7_t("Mostrar detalhe tecnico")); ?>;
		var raw = document.createElement('pre');
		raw.className = 'pre-scrollable small';
		raw.textContent = eventRaw(ex);
		details.appendChild(summaryEl);
		details.appendChild(raw);
		row.appendChild(meta);
		row.appendChild(titleWrap);
		row.appendChild(summary);
		row.appendChild(details);
		if (techBox && techBox.checked) {
			details.open = true;
		}
		return row;
	}

	function applyTech() {
		if (!techBox) return;
		var open = techBox.checked;
		var nodes = document.querySelectorAll('.l7-event-raw-wrap');
		for (var i = 0; i < nodes.length; i++) {
			nodes[i].open = open;
		}
		try {
			localStorage.setItem(techKey, open ? '1' : '0');
		} catch (e) {}
	}

	function updateView() {
		if (!liveView) return;
		liveView.textContent = '';
		if (seenLines.length === 0) {
			var empty = document.createElement('p');
			empty.className = 'list-group-item text-muted';
			empty.textContent = l7WaitingEvents;
			liveView.appendChild(empty);
		} else {
			for (var i = 0; i < seenLines.length; i++) {
				liveView.appendChild(renderRow(seenLines[i]));
			}
		}
		liveView.scrollTop = liveView.scrollHeight;
		if (countEl) {
			countEl.textContent = seenLines.length > 0
				? seenLines.length + ' ' + l7LineSuffix : '';
		}
		applyTech();
	}

	function mergeIncoming(incoming) {
		if (!incoming || incoming.length === 0) return;

		var newLines = [];

		if (seenLines.length === 0) {
			newLines = incoming;
		} else {
			var lastSeen = eventRaw(seenLines[seenLines.length - 1]);
			var overlapIdx = -1;
			for (var i = incoming.length - 1; i >= 0; i--) {
				if (eventRaw(incoming[i]) === lastSeen) {
					overlapIdx = i;
					break;
				}
			}

			if (overlapIdx >= 0) {
				newLines = incoming.slice(overlapIdx + 1);
			} else {
				var recentSet = {};
				var window100 = seenLines.slice(-100);
				for (var j = 0; j < window100.length; j++) {
					recentSet[eventRaw(window100[j])] = true;
				}
				newLines = incoming.filter(function(l) {
					return !recentSet[eventRaw(l)];
				});
			}
		}

		if (newLines.length === 0) return;

		for (var k = 0; k < newLines.length; k++) {
			seenLines.push(newLines[k]);
		}
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
			var incoming = [];
			try {
				incoming = JSON.parse(xhr.responseText);
			} catch (e) {
				return;
			}
			if (!incoming || !incoming.length) return;
			mergeIncoming(incoming);
		};
		xhr.send(null);
	}

	function schedule() {
		if (timer) clearInterval(timer);
		timer = setInterval(fetchLive, refreshMs);
	}

	if (techBox) {
		try {
			techBox.checked = localStorage.getItem(techKey) === '1';
		} catch (e) {}
		techBox.addEventListener('change', applyTech);
		applyTech();
	}

	if (toggleBtn) {
		toggleBtn.addEventListener('click', function() {
			paused = !paused;
			toggleBtn.textContent   = paused ? <?= json_encode(l7_t("Retomar")); ?> : <?= json_encode(l7_t("Pausar")); ?>;
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

	updateView();
	fetchLive();
	schedule();
})();
</script>
<p class="text-center text-muted">
	Layer7 para pfSense CE &mdash;
	<a href="https://www.systemup.inf.br" target="_blank">Systemup</a>
	Solu&ccedil;&atilde;o em Tecnologia
</p>
<?php require_once("foot.inc"); ?>
