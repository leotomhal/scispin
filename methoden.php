<?php
declare(strict_types=1);

require __DIR__ . '/lib/markdown.php';
require __DIR__ . '/lib/methoden_data.php';

$raw = @file_get_contents(__DIR__ . '/content/methoden.md');
[$intro, $entries] = sci_methoden_parse($raw !== false ? $raw : '');
$M = sci_methoden_build($entries);

$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$pyr = array_filter($M, fn($v) => $v[2] === 'pyr');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Methoden & Evidenz – Studien-Check</title>
<meta name="description" content="Welche Studientypen es gibt und wie tragfähig ihre Aussagen sind.">
<link rel="stylesheet" href="assets/scispin.css">
<style>
  :root { --fg:#1a1a1a; --muted:#6b6b6b; --line:#e6e6e6; --bg:#f7f7f8; --card:#fff;
          --gruen:#2e7d32; --gelb:#f9a825; --rot:#c62828; --grau:#9e9e9e; --accent:#3b5bdb; }
  * { box-sizing:border-box; }
  body { font-family:'Inter',system-ui,-apple-system,sans-serif; color:var(--fg); background:var(--bg); margin:0; line-height:1.55; }
  a { color:var(--accent); }
  header { border-bottom:1px solid var(--line); background:var(--card); }
  .bar { max-width:820px; margin:0 auto; padding:.9rem 1.2rem; display:flex; justify-content:space-between; align-items:center; }
  .brand { font-weight:700; color:var(--fg); text-decoration:none; }
  .nav a { color:var(--muted); text-decoration:none; font-size:.92rem; margin-left:1rem; }
  .nav a:hover { color:var(--accent); }
  main { max-width:820px; margin:0 auto; padding:2rem 1.2rem 4rem; }
  h1 { font-size:1.45rem; margin:0 0 .3rem; }
  .lead { color:var(--muted); margin:0 0 1.2rem; }
  .note { background:var(--card); border:1px solid var(--line); border-left:3px solid var(--gelb);
          border-radius:8px; padding:.8rem 1rem; font-size:.9rem; color:#555; margin-bottom:1.8rem; }

  /* Pyramide */
  .pyramid { display:flex; flex-direction:column; align-items:center; gap:.3rem; margin:1.5rem 0 2.5rem; }
  .prow { color:#fff; text-decoration:none; text-align:center; padding:.55rem .8rem; border-radius:6px;
          font-size:.9rem; font-weight:600; display:flex; justify-content:center; align-items:center; gap:.5rem; }
  .prow small { font-weight:400; opacity:.9; }
  .axis { font-size:.8rem; color:var(--muted); margin:.2rem 0; }

  .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:1.1rem 1.2rem; margin-bottom:1rem; scroll-margin-top:1rem; }
  .card h2 { font-size:1.1rem; margin:0 0 .2rem; }
  .rang { display:inline-block; font-size:.78rem; padding:.12rem .55rem; border-radius:999px; border:1px solid var(--line); color:var(--muted); margin-bottom:.6rem; }
  .card p { margin:.35rem 0; }
  .card .k { font-size:.82rem; text-transform:uppercase; letter-spacing:.03em; color:var(--muted); }
  .group-title { margin:2rem 0 .6rem; font-size:1.05rem; }
  footer { border-top:1px solid var(--line); background:var(--card); margin-top:2rem; }
  .foot { max-width:820px; margin:0 auto; padding:1rem 1.2rem; font-size:.82rem; color:var(--muted); }
</style>
</head>
<body>
<main>
  <h1>Methoden &amp; Evidenz</h1>
  <p class="lead">Welche Studientypen es gibt – und wie weit ihre Aussagen tragen.</p>

  <?php if ($intro !== ''): ?><div class="note"><?= sci_md_inline($intro) ?></div><?php endif; ?>

  <div class="axis">▲ höhere Tragfähigkeit für Ursache-Wirkung-Aussagen</div>
  <div class="pyramid">
    <?php
      $i = 0; $n = count($pyr);
      $palette = ['#1b5e20','#2e7d32','#388e3c','#7cb342','#c0ca33','#f9a825','#ef9a3d','#bdbdbd'];
      foreach ($pyr as $key => $m):
        $w = 40 + $i * (55 / max(1,$n-1));         // oben schmal (40%) bis unten breit (95%)
        $bg = $palette[$i] ?? '#bdbdbd';
    ?>
      <a class="prow" href="#<?= $esc($key) ?>" style="width:<?= $w ?>%; background:<?= $bg ?>;">
        <?= $esc($m[0]) ?> <small>· <?= $esc($m[1]) ?></small>
      </a>
    <?php $i++; endforeach; ?>
  </div>
  <div class="axis">▼ eher Hinweise, Hypothesen, Einzelbeobachtungen</div>

  <h2 class="group-title">Im Detail</h2>
  <?php foreach ($pyr as $key => $m): ?>
    <div class="card" id="<?= $esc($key) ?>">
      <h2><?= $esc($m[0]) ?></h2>
      <span class="rang">Evidenz: <?= $esc($m[1]) ?></span>
      <p><?= $esc($m[3]) ?></p>
      <p><span class="k">Zeigt:</span> <?= $esc($m[4]) ?></p>
      <p><span class="k">Grenzen:</span> <?= $esc($m[5]) ?></p>
    </div>
  <?php endforeach; ?>

  <h2 class="group-title">Andere Ebenen (nicht auf der Pyramide)</h2>
  <p class="lead">Diese Typen liegen auf einer anderen Achse: Sie liefern Mechanismen, Vorstufen oder
  Modellrechnungen – nicht direkt belastbare Aussagen über den Menschen.</p>
  <?php foreach ($M as $key => $m): if ($m[2] !== 'sonder') continue; ?>
    <div class="card" id="<?= $esc($key) ?>">
      <h2><?= $esc($m[0]) ?></h2>
      <span class="rang"><?= $esc($m[1]) ?></span>
      <p><?= $esc($m[3]) ?></p>
      <?php if ($m[4] !== '–' && $m[4] !== ''): ?><p><span class="k">Zeigt:</span> <?= $esc($m[4]) ?></p><?php endif; ?>
      <p><span class="k">Grenzen:</span> <?= $esc($m[5]) ?></p>
    </div>
  <?php endforeach; ?>
</main>
<script>window.SCISPIN = { root: '', active: 'methoden' };</script>
<script src="assets/chrome.js"></script>
</body>
</html>
