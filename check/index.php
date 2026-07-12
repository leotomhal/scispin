<?php
declare(strict_types=1);
// Letzte Analysen aus dem Archiv laden (bricht die Seite nie ab).
$recent = [];
$recentCount = 0;
try {
    $cfg = require __DIR__ . '/../lib/config.php';
    $recentCount = (int)($cfg['recent_count'] ?? 5);
    if ($recentCount > 0) {
        require_once __DIR__ . '/../lib/db.php';
        $db = sc_db($cfg);
        $recent = $db->query(
            'SELECT title, ampel, source, created_at FROM archive
             ORDER BY created_at DESC LIMIT ' . $recentCount
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $recent = [];
}
$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Studien-Check</title>
<meta name="description" content="Was sagt eine Studie wirklich – und wie weit darf man mit der Aussage gehen?">
<style>
  :root {
    --fg: #1a1a1a; --muted: #6b6b6b; --line: #e6e6e6; --bg: #f7f7f8; --card:#fff;
    --gruen: #2e7d32; --gelb: #f9a825; --rot: #c62828; --grau: #9e9e9e;
    --accent: #1565c0;
  }
  * { box-sizing: border-box; }
  body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
         color: var(--fg); background: var(--bg); margin: 0; line-height: 1.55; }
  a { color: var(--accent); }

  /* Kopfzeile */
  header { border-bottom: 1px solid var(--line); background: var(--card); }
  .bar { max-width: 760px; margin: 0 auto; padding: .9rem 1.2rem;
         display: flex; align-items: center; justify-content: space-between; }
  .brand { font-weight: 700; font-size: 1.05rem; color: var(--fg); text-decoration: none;
           display: inline-flex; align-items: center; gap: .5rem; }
  .brand .mark { width: .8rem; height: .8rem; border-radius: 50%;
                 background: linear-gradient(135deg, var(--gruen), var(--gelb)); }
  .nav a { text-decoration: none; font-size: .92rem; color: var(--muted); }
  .nav a:hover { color: var(--accent); }

  main { max-width: 760px; margin: 0 auto; padding: 2rem 1.2rem 3rem; }
  h1 { font-size: 1.45rem; margin: 0 0 .3rem; line-height: 1.25; }
  .lead { color: var(--muted); margin: 0 0 1.4rem; }

  .inputrow { display: flex; gap: .5rem; }
  input[type=url] { flex: 1; padding: .75rem .85rem; border: 1px solid var(--line);
                    border-radius: 10px; font-size: 1rem; background: var(--card); }
  input[type=url]:focus { outline: 2px solid var(--accent); outline-offset: 0; border-color: var(--accent); }
  button { padding: .75rem 1.2rem; border: 0; border-radius: 10px; background: var(--fg);
           color: #fff; font-size: 1rem; cursor: pointer; font-weight: 600; }
  button:disabled { opacity: .5; cursor: default; }
  .hint { font-size: .85rem; color: var(--muted); margin-top: .5rem; }

  /* Ampel-Legende */
  .legend { display: flex; flex-wrap: wrap; gap: .4rem .9rem; margin: 1rem 0 0;
            padding: .7rem .9rem; background: var(--card); border: 1px solid var(--line);
            border-radius: 10px; font-size: .82rem; color: var(--muted); }
  .legend span { display: inline-flex; align-items: center; gap: .4rem; }
  .dot { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; }
  .dot.gruen{background:var(--gruen)} .dot.gelb{background:var(--gelb)}
  .dot.rot{background:var(--rot)} .dot.grau{background:var(--grau)}

  /* Letzte Analysen */
  .recent { margin-top: 1.8rem; }
  .recent h2 { font-size: .82rem; text-transform: uppercase; letter-spacing: .04em;
               color: var(--muted); margin: 0 0 .5rem; }
  .recent-item { display: flex; align-items: center; gap: .6rem; width: 100%;
                 text-align: left; background: var(--card); border: 1px solid var(--line);
                 border-radius: 10px; padding: .6rem .8rem; margin-bottom: .4rem;
                 cursor: pointer; font-size: .92rem; color: var(--fg); }
  .recent-item:hover { border-color: var(--accent); }
  .recent-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

  .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px;
          padding: 1.2rem; margin-top: 1.6rem; }
  .ampel { display: inline-flex; align-items: center; gap: .5rem; padding: .35rem .7rem;
           border-radius: 999px; color: #fff; font-weight: 600; font-size: .9rem; }
  .ampel.gruen { background: var(--gruen); }
  .ampel.gelb  { background: var(--gelb); }
  .ampel.rot   { background: var(--rot); }
  .ampel.grau  { background: #eee; color: var(--grau); border: 1px dashed var(--grau); }
  .core { font-size: 1.15rem; font-weight: 600; margin: .8rem 0; }
  .badge { display: inline-block; padding: .25rem .6rem; border-radius: 6px;
           font-size: .8rem; border: 1px solid var(--line); }
  .badge.peer { background: #e8f5e9; border-color: #a5d6a7; }
  .badge.preprint { background: #fff8e1; border-color: #ffe082; }
  .badge.unklar { background: #f5f5f5; }
  .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin: 1rem 0; }
  .row2 > div { background: var(--bg); border-radius: 8px; padding: .7rem .8rem; }
  .row2 h4 { margin: 0 0 .3rem; font-size: .82rem; text-transform: uppercase;
             letter-spacing: .03em; color: var(--muted); }
  .basis { font-size: .85rem; color: var(--accent); margin-top: .8rem; }
  .toggle { display: flex; gap: .4rem; margin: 1rem 0 .6rem; }
  .toggle .chip { padding: .35rem .8rem; border: 1px solid var(--line); border-radius: 999px;
                  background: #fff; cursor: pointer; font-size: .85rem; }
  .toggle .chip.active { background: var(--fg); color: #fff; border-color: var(--fg); }
  .einfach-text { font-size: 1.05rem; line-height: 1.6; margin: .4rem 0 .2rem; }
  details { margin-top: 1rem; border-top: 1px solid var(--line); padding-top: .8rem; }
  summary { cursor: pointer; font-weight: 600; }
  section h3 { font-size: 1rem; margin: 1.1rem 0 .3rem; }
  ul { margin: .3rem 0; padding-left: 1.2rem; }
  .disclaimer { font-size: .8rem; color: var(--muted); margin-top: 1.4rem;
                border-top: 1px solid var(--line); padding-top: .8rem; }
  .err { background: #fdecea; border: 1px solid #f5c6cb; color: var(--rot);
         padding: .9rem; border-radius: 8px; margin-top: 1.6rem; }
  .spinner { color: var(--muted); margin-top: 1.6rem; }
  textarea { width: 100%; padding: .6rem .7rem; border: 1px solid var(--line); border-radius: 8px;
             font: inherit; font-size: .95rem; resize: vertical; background: var(--card); }
  textarea:focus { outline: 2px solid var(--accent); border-color: var(--accent); }

  footer { border-top: 1px solid var(--line); background: var(--card); margin-top: 2rem; }
  .foot { max-width: 760px; margin: 0 auto; padding: 1.2rem; font-size: .82rem; color: var(--muted);
          display: flex; flex-wrap: wrap; gap: .4rem 1rem; justify-content: space-between; }
  .foot a { color: var(--muted); }
  @media (max-width: 520px) { .row2 { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<header>
  <div class="bar">
    <a class="brand" href="./"><span class="mark"></span>Studien-Check</a>
    <nav class="nav"><a href="../">Start</a> &nbsp; <a href="methoden.php">Methoden</a> &nbsp; <a href="archive.php">Archiv &rarr;</a></nav>
  </div>
</header>

<main>
  <h1>Was sagt eine Studie wirklich – und wie weit darf man mit der Aussage gehen?</h1>
  <p class="lead">Link zu einer wissenschaftlichen Studie einfügen und prüfen.</p>

  <div class="inputrow">
    <input type="url" id="url" placeholder="Link zur Studie (https://…)" autocomplete="off">
    <button id="go">Prüfen</button>
  </div>
  <p class="hint">Bewertet die Reichweite der Aussage, nicht „wahr/falsch" und nicht das Medienpotenzial.</p>

  <div class="legend">
    <span><span class="dot gruen"></span>Tragfähig</span>
    <span><span class="dot gelb"></span>Mit Vorsicht</span>
    <span><span class="dot rot"></span>Überdehnt</span>
    <span><span class="dot grau"></span>Nicht bewertbar</span>
  </div>

  <div id="out"></div>

<?php if ($recent): ?>
  <div class="recent">
    <h2>Zuletzt geprüft</h2>
    <?php foreach ($recent as $r): ?>
      <button class="recent-item" data-url="<?= $esc($r['source']) ?>">
        <span class="dot <?= $esc($r['ampel']) ?>"></span>
        <span class="recent-title"><?= $esc($r['title'] ?: '(ohne Titel)') ?></span>
      </button>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>

<footer>
  <div class="foot">
    <span>Automatisierte Einschätzung, kein Ersatz für fachliche Prüfung.</span>
    <span><a href="impressum.php">Impressum</a> · <a href="datenschutz.php">Datenschutz</a></span>
  </div>
</footer>

<script>
const $ = s => document.querySelector(s);
const out = $('#out');
const btn = $('#go');

const AMPEL_TEXT = { gruen:'Tragfähig', gelb:'Mit Vorsicht', rot:'Überdehnt', grau:'Nicht bewertbar' };
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const li = arr => (Array.isArray(arr) && arr.length)
  ? '<ul>' + arr.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '<p>–</p>';

let lastUrl = '';

async function run(extra = {}) {
  const url = $('#url').value.trim() || lastUrl;
  if (!url) return;
  lastUrl = url;
  btn.disabled = true;
  out.scrollIntoView({ behavior: 'smooth', block: 'start' });
  out.innerHTML = '<p class="spinner">Studie wird ausgelesen und geprüft …</p>';
  try {
    const res = await fetch('api/analyze.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ url, ...extra })
    });
    const data = await res.json();
    if (!data.ok) { renderError(data); return; }
    if (data.not_applicable) { renderInfo(data); return; }
    render(data);
  } catch (e) {
    out.innerHTML = '<div class="err">Verbindungsfehler. Bitte erneut versuchen.</div>';
  } finally {
    btn.disabled = false;
  }
}

function renderError(d) {
  let html = '<div class="err">' + esc(d.message || 'Fehler.') + '</div>';
  if (d.error === 'no_abstract' || d.error === 'no_doi') {
    html += `
      <div class="card">
        <p>Du hast den Abstract oder die Summary vorliegen (z. B. von einer Verlagsseite)?
        Hier einfügen und prüfen lassen:</p>
        <textarea id="manual-abstract" rows="7" placeholder="Abstract oder Summary hier einfügen …"></textarea>
        <button id="manual-go" style="margin-top:.6rem">Mit eingefügtem Text prüfen</button>
        <p class="hint" id="manual-hint" style="display:none">Bitte etwas mehr Text einfügen.</p>
      </div>`;
  }
  out.innerHTML = html;
  const mg = $('#manual-go');
  if (mg) mg.addEventListener('click', () => {
    const ab = $('#manual-abstract').value.trim();
    if (ab.length < 40) { $('#manual-hint').style.display = 'block'; return; }
    run({ abstract: ab });
  });
}

function renderInfo(d) {
  const m = d.meta || {};
  out.innerHTML = `
    <div class="card">
      <span class="ampel grau">Kein Check möglich</span>
      ${m.title ? `<p class="core">${esc(m.title)}</p>` : ''}
      <p>${esc(d.message)}</p>
      <p class="basis">
        ${m.journal ? esc(m.journal) + ' · ' : ''}
        ${m.source ? `<a href="${esc(m.source)}" target="_blank" rel="noopener">Zum Originalbeitrag</a>` : ''}
      </p>
    </div>`;
}

function render(d) {
  const a = d.analysis, b = d.badge;
  const color = a.ampel.color;
  const abstractOnly = d.datenbasis === 'abstract';

  const basisHint = d.manual
    ? 'Datenbasis: manuell eingefügter Abstract.'
    : (abstractOnly
        ? 'Datenbasis: nur Abstract – Methoden-Details, die dort nicht stehen, fließen nicht ein.'
        : 'Datenbasis: Volltext.');

  const einfach = a.einfache_erklaerung || a.summary;

  out.innerHTML = `
    <div class="card">
      <span class="ampel ${esc(color)}">${esc(AMPEL_TEXT[color] || color)}</span>
      <p class="core">${esc(a.core_statement)}</p>
      <span class="badge ${esc(b.code)}">${esc(b.label)}</span>
      <p class="basis">${esc(basisHint)}</p>

      <div class="toggle">
        <span class="chip active" data-v="einfach">Einfach erklärt</span>
        <span class="chip" data-v="ausfuehrlich">Ausführlich</span>
      </div>

      <div class="view view-einfach">
        <p class="einfach-text">${esc(einfach)}</p>
        <div class="row2">
          <div><h4>Das zeigt die Studie</h4>${li(a.shows.slice(0,1))}</div>
          <div><h4>Das zeigt sie nicht</h4>${li(a.shows_not.slice(0,1))}</div>
        </div>
      </div>

      <div class="view view-ausfuehrlich" style="display:none">
        <section>
          <h3>Worum geht es?</h3><p>${esc(a.summary)}</p>

          <h3>Das zeigt die Studie – das zeigt sie nicht</h3>
          <strong>Zeigt:</strong>${li(a.shows)}
          <strong>Zeigt nicht:</strong>${li(a.shows_not)}

          <h3>Wie wurde das untersucht?</h3>
          <p><strong>${esc(a.methodik_klartext.study_type)}.</strong> ${esc(a.methodik_klartext.erklaerung)}${
            (a.methodik_klartext.study_type_key && a.methodik_klartext.study_type_key !== 'andere')
            ? ` <a href="methoden.php#${esc(a.methodik_klartext.study_type_key)}" target="_blank" rel="noopener">Was bedeutet das?</a>` : ''}</p>
          ${(a.methodik_klartext.fehlende_angaben||[]).length
            ? '<p><em>Im Text nicht angegeben:</em></p>' + li(a.methodik_klartext.fehlende_angaben) : ''}

          <h3>Passt die Methode zur Aussage?</h3>
          <p>${esc(a.methode_aussage_abgleich)}</p>

          ${abstractOnly ? '' : `
          <h3>Einschränkungen</h3>${li(a.einschraenkungen)}`}

          <h3>Einordnung</h3><p>${esc(a.einordnung)}</p>

          <h3>Datengrundlage &amp; Quelle</h3>
          <p>${esc(b.label)} · ${esc(basisHint)}<br>
          ${d.meta.journal ? esc(d.meta.journal) + '<br>' : ''}
          <a href="${esc(d.meta.source)}" target="_blank" rel="noopener">Zur Originalstudie</a></p>
        </section>
      </div>

      <p class="disclaimer">Automatisierte Einschätzung, kein Ersatz für fachliche Prüfung.
      ${abstractOnly ? 'Beruht nur auf dem Abstract und ist entsprechend eingeschränkt.' : ''}</p>
    </div>`;
}

btn.addEventListener('click', run);
$('#url').addEventListener('keydown', e => { if (e.key === 'Enter') run(); });

// Letzte Analysen: Klick prüft die Studie erneut
document.querySelectorAll('.recent-item').forEach(el => {
  el.addEventListener('click', () => { $('#url').value = el.dataset.url; run(); });
});

// Umschalter Einfach / Ausführlich
out.addEventListener('click', e => {
  const chip = e.target.closest('.toggle .chip');
  if (!chip) return;
  const card = chip.closest('.card');
  card.querySelectorAll('.toggle .chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  const v = chip.dataset.v;
  card.querySelector('.view-einfach').style.display = (v === 'einfach') ? '' : 'none';
  card.querySelector('.view-ausfuehrlich').style.display = (v === 'ausfuehrlich') ? '' : 'none';
});
</script>
</body>
</html>
