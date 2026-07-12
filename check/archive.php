<?php
/**
 * archive.php – durchsuchbare Übersicht aller analysierten Studien.
 */
declare(strict_types=1);
session_start();
date_default_timezone_set('Europe/Berlin');
$cfg = require __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/db.php';

// --- optionales Passwort-Gate ---
$pw = (string)($cfg['archive_password'] ?? '');
if ($pw !== '') {
    if (isset($_POST['pw']) && hash_equals($pw, (string)$_POST['pw'])) {
        $_SESSION['archive_ok'] = true;
    }
    if (empty($_SESSION['archive_ok'])) {
        echo '<!doctype html><meta charset="utf-8"><title>Archiv</title>'
           . '<body style="font-family:system-ui;max-width:320px;margin:4rem auto">'
           . '<form method="post"><p>Passwort:</p>'
           . '<input type="password" name="pw" autofocus style="width:100%;padding:.6rem">'
           . '<button style="margin-top:.6rem;padding:.6rem 1rem">Anmelden</button></form></body>';
        exit;
    }
}

$rows = [];
$error = null;
try {
    $db = sc_db($cfg);
    $rows = $db->query(
        'SELECT doi, title, journal, ampel, badge, is_preprint, datenbasis,
                core_statement, source, created_at, updated_at, result
         FROM archive ORDER BY created_at DESC'
    )->fetchAll();
} catch (Throwable $e) {
    $error = 'Archiv-Tabelle nicht gefunden. schema.sql importiert?';
}

$AMPEL = ['gruen' => 'Tragfähig', 'gelb' => 'Mit Vorsicht', 'rot' => 'Überdehnt', 'grau' => 'Nicht bewertbar'];
$BADGE = ['peer' => 'Peer-Review', 'preprint' => 'Preprint', 'unklar' => 'unklar'];
$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/** Liste als <ul> aus einem Array. */
$ul = function(?array $items) use ($esc): string {
    if (!$items) return '<p>–</p>';
    return '<ul>' . implode('', array_map(fn($x) => '<li>' . $esc($x) . '</li>', $items)) . '</ul>';
};

/** Vollständigen Report aus dem gespeicherten result-JSON rendern. */
$renderDetail = function(?string $resultJson) use ($esc, $ul): string {
    $d = json_decode((string)$resultJson, true);
    $a = $d['analysis'] ?? null;
    if (!is_array($a)) return '<p>Keine Detaildaten gespeichert.</p>';
    $h = '';
    if (!empty($a['einfache_erklaerung'])) $h .= '<h4>Einfach erklärt</h4><p>' . $esc($a['einfache_erklaerung']) . '</p>';
    if (!empty($a['summary']))             $h .= '<h4>Worum geht es?</h4><p>' . $esc($a['summary']) . '</p>';
    $h .= '<h4>Das zeigt die Studie</h4>' . $ul($a['shows'] ?? []);
    $h .= '<h4>Das zeigt sie nicht</h4>' . $ul($a['shows_not'] ?? []);
    if (!empty($a['methodik_klartext']['study_type'])) {
        $h .= '<h4>Wie wurde das untersucht?</h4><p><strong>' . $esc($a['methodik_klartext']['study_type'])
            . '.</strong> ' . $esc($a['methodik_klartext']['erklaerung'] ?? '');
        $k = $a['methodik_klartext']['study_type_key'] ?? '';
        if ($k && $k !== 'andere') {
            $h .= ' <a href="methoden.php#' . $esc($k) . '" target="_blank" rel="noopener">Was bedeutet das?</a>';
        }
        $h .= '</p>';
        if (!empty($a['methodik_klartext']['fehlende_angaben']))
            $h .= '<p><em>Im Text nicht angegeben:</em></p>' . $ul($a['methodik_klartext']['fehlende_angaben']);
    }
    if (!empty($a['methode_aussage_abgleich']))
        $h .= '<h4>Passt die Methode zur Aussage?</h4><p>' . $esc($a['methode_aussage_abgleich']) . '</p>';
    if (!empty($a['einschraenkungen'])) $h .= '<h4>Einschränkungen</h4>' . $ul($a['einschraenkungen']);
    if (!empty($a['einordnung']))       $h .= '<h4>Einordnung</h4><p>' . $esc($a['einordnung']) . '</p>';
    return $h;
};
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Studien-Check – Archiv</title>
<style>
  :root { --fg:#1a1a1a; --muted:#666; --line:#e2e2e2; --bg:#fafafa;
          --gruen:#2e7d32; --gelb:#f9a825; --rot:#c62828; --grau:#9e9e9e; --blue:#1565c0; }
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,"Segoe UI",sans-serif; color:var(--fg);
         background:var(--bg); margin:0; }
  main { max-width:1000px; margin:0 auto; padding:2rem 1.2rem 4rem; }
  h1 { font-size:1.4rem; margin:0 0 .2rem; }
  .meta { color:var(--muted); margin:0 0 1.4rem; font-size:.9rem; }
  .controls { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:1rem; }
  input[type=search] { flex:1; min-width:200px; padding:.55rem .7rem; border:1px solid var(--line);
                       border-radius:8px; font-size:.95rem; }
  .chip { padding:.35rem .7rem; border:1px solid var(--line); border-radius:999px;
          background:#fff; cursor:pointer; font-size:.85rem; }
  .chip.active { background:var(--fg); color:#fff; border-color:var(--fg); }
  table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line);
          border-radius:10px; overflow:hidden; }
  th, td { text-align:left; padding:.6rem .7rem; border-bottom:1px solid var(--line);
           font-size:.9rem; vertical-align:top; }
  th { background:var(--bg); cursor:pointer; user-select:none; white-space:nowrap; }
  th[data-sort]::after { content:" \2195"; color:var(--muted); font-size:.8em; }
  tr:last-child td { border-bottom:0; }
  .dot { display:inline-block; width:.7rem; height:.7rem; border-radius:50%; margin-right:.4rem; vertical-align:middle; }
  .dot.gruen{background:var(--gruen)} .dot.gelb{background:var(--gelb)}
  .dot.rot{background:var(--rot)} .dot.grau{background:var(--grau)}
  .title a { color:var(--fg); text-decoration:none; font-weight:600; }
  .title a:hover { text-decoration:underline; }
  .sub { color:var(--muted); font-size:.82rem; margin-top:.15rem; }
  .badge { font-size:.78rem; padding:.1rem .5rem; border:1px solid var(--line); border-radius:6px; white-space:nowrap; }
  .detail { margin-top:.5rem; }
  .detail summary { cursor:pointer; font-size:.82rem; color:var(--blue); }
  .detail-body { margin-top:.5rem; padding:.6rem .8rem; background:var(--bg); border-radius:8px; }
  .detail-body h4 { margin:.7rem 0 .2rem; font-size:.85rem; }
  .detail-body h4:first-child { margin-top:0; }
  .detail-body ul { margin:.2rem 0; padding-left:1.1rem; }
  .detail-body p { margin:.2rem 0; }
  .err { background:#fdecea; border:1px solid #f5c6cb; color:var(--rot); padding:1rem; border-radius:8px; }
  .empty { color:var(--muted); padding:2rem; text-align:center; }
</style>
</head>
<body>
<main>
  <h1>Studien-Check – Archiv</h1>
  <p class="meta"><a href="index.php">&larr; Neue Studie prüfen</a> · <a href="methoden.php">Methoden</a> · <a href="../">Start</a> · <span id="count"><?= count($rows) ?></span> Einträge</p>

<?php if ($error): ?>
  <div class="err"><?= $esc($error) ?></div>
<?php elseif (!$rows): ?>
  <p class="empty">Noch keine analysierten Studien im Archiv.</p>
<?php else: ?>
  <div class="controls">
    <input type="search" id="q" placeholder="Suche in Titel, Journal, Kernaussage …">
    <span class="chip active" data-f="all">Alle</span>
    <span class="chip" data-f="gruen">Tragfähig</span>
    <span class="chip" data-f="gelb">Mit Vorsicht</span>
    <span class="chip" data-f="rot">Überdehnt</span>
    <span class="chip" data-f="grau">Unklar</span>
  </div>

  <table id="tbl">
    <thead>
      <tr>
        <th data-sort="date">Datum</th>
        <th data-sort="ampel">Ampel</th>
        <th>Studie</th>
        <th data-sort="journal">Journal</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $hay = mb_strtolower(($r['title'] ?? '') . ' ' . ($r['journal'] ?? '') . ' ' . ($r['core_statement'] ?? '')); ?>
      <tr data-ampel="<?= $esc($r['ampel']) ?>"
          data-journal="<?= $esc(mb_strtolower((string)$r['journal'])) ?>"
          data-date="<?= (int)$r['created_at'] ?>"
          data-hay="<?= $esc($hay) ?>">
        <td>
          <?= date('d.m.Y', (int)$r['created_at']) ?>
          <div class="sub"><?= date('H:i', (int)$r['created_at']) ?> Uhr</div>
        </td>
        <td><span class="dot <?= $esc($r['ampel']) ?>"></span><?= $esc($AMPEL[$r['ampel']] ?? $r['ampel']) ?></td>
        <td class="title">
          <?php if ($r['source']): ?>
            <a href="<?= $esc($r['source']) ?>" target="_blank" rel="noopener"><?= $esc($r['title'] ?: '(ohne Titel)') ?></a>
          <?php else: ?>
            <?= $esc($r['title'] ?: '(ohne Titel)') ?>
          <?php endif; ?>
          <?php if ($r['core_statement']): ?><div class="sub"><?= $esc($r['core_statement']) ?></div><?php endif; ?>
          <details class="detail">
            <summary>Vollständige Auswertung</summary>
            <div class="detail-body">
              <?= $renderDetail($r['result'] ?? null) ?>
              <p class="sub">Hinzugefügt: <?= date('d.m.Y H:i', (int)$r['created_at']) ?> Uhr<?php
                if ((int)$r['updated_at'] > (int)$r['created_at'] + 60)
                    echo ' · zuletzt aktualisiert: ' . date('d.m.Y H:i', (int)$r['updated_at']) . ' Uhr'; ?></p>
            </div>
          </details>
        </td>
        <td><?= $esc($r['journal'] ?: '–') ?></td>
        <td><span class="badge"><?= $esc($BADGE[$r['badge']] ?? $r['badge']) ?></span>
            <div class="sub"><?= $r['datenbasis'] === 'abstract' ? 'nur Abstract' : 'Volltext' ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</main>

<script>
(function(){
  const tbody = document.querySelector('#tbl tbody');
  if (!tbody) return;
  const rows = [...tbody.querySelectorAll('tr')];
  const q = document.querySelector('#q');
  const count = document.querySelector('#count');
  let filter = 'all';

  function apply() {
    const term = (q.value || '').toLowerCase().trim();
    let shown = 0;
    rows.forEach(r => {
      const okA = filter === 'all' || r.dataset.ampel === filter;
      const okQ = !term || r.dataset.hay.includes(term);
      const vis = okA && okQ;
      r.style.display = vis ? '' : 'none';
      if (vis) shown++;
    });
    count.textContent = shown;
  }

  q.addEventListener('input', apply);
  document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => {
    document.querySelectorAll('.chip').forEach(x => x.classList.remove('active'));
    c.classList.add('active');
    filter = c.dataset.f;
    apply();
  }));

  const order = { gruen:0, gelb:1, rot:2, grau:3 };
  document.querySelectorAll('th[data-sort]').forEach(th => {
    let asc = true;
    th.addEventListener('click', () => {
      const key = th.dataset.sort;
      const sorted = rows.slice().sort((a,b) => {
        let va, vb;
        if (key === 'date') { va = +a.dataset.date; vb = +b.dataset.date; }
        else if (key === 'ampel') { va = order[a.dataset.ampel]; vb = order[b.dataset.ampel]; }
        else { va = a.dataset.journal; vb = b.dataset.journal; }
        return va < vb ? -1 : va > vb ? 1 : 0;
      });
      if (!asc) sorted.reverse();
      asc = !asc;
      sorted.forEach(r => tbody.appendChild(r));
    });
  });
})();
</script>
</body>
</html>
