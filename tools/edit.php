<?php
/**
 * tools/edit.php – Inhalts-Editor.
 * Bearbeitet content/*.md direkt auf dem Server, ohne GitHub. Getrennt vom
 * Self-Updater: eigenes Token (content_edit_token), schreibt Dateien direkt
 * (kein Git, keine Versionsnummer). Nur bestehende .md-Dateien unter
 * content/ sind editierbar – neue Seiten anzulegen bleibt ein Code-Schritt.
 */

declare(strict_types=1);

const SCISPIN_EDIT_APP_ROOT = __DIR__ . '/..';
const SCISPIN_EDIT_CONTENT_DIR = SCISPIN_EDIT_APP_ROOT . '/content';

function edit_esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** Bekannte Live-Seiten zu content/*.md, fürs "ansehen"-Link. Unbekannte Dateien: kein Link. */
function edit_page_url(string $file): ?string {
    $map = [
        'so-funktionierts.md' => '../so-funktionierts.php',
        'impressum.md'        => '../impressum.php',
        'datenschutz.md'      => '../datenschutz.php',
        'ueber.md'            => '../spin/ueber.php',
        'methoden.md'         => '../methoden.php',
    ];
    return $map[$file] ?? null;
}

function edit_main(): void {
    header('Content-Type: text/html; charset=utf-8');
    $cfg = require SCISPIN_EDIT_APP_ROOT . '/lib/config.php';

    $expected = (string)($cfg['content_edit_token'] ?? '');
    $given    = (string)($_GET['token'] ?? $_POST['token'] ?? '');

    if ($expected === '') {
        http_response_code(403);
        echo '<p>Editor ist deaktiviert (kein content_edit_token in lib/config.php gesetzt).</p>';
        return;
    }
    if (!hash_equals($expected, $given)) {
        http_response_code(403);
        echo '<p>Zugriff verweigert.</p>';
        return;
    }

    $notice = '';
    $file = (string)($_GET['file'] ?? $_POST['file'] ?? '');
    $validName = (bool)preg_match('/^[a-z0-9_-]+\.md$/i', $file);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$validName) {
            http_response_code(400);
            echo '<p>Ungültiger Dateiname.</p>';
            return;
        }
        $path = SCISPIN_EDIT_CONTENT_DIR . '/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            echo '<p>Datei existiert nicht.</p>';
            return;
        }
        $text = str_replace("\r\n", "\n", (string)($_POST['content'] ?? ''));
        file_put_contents($path, $text);
        $notice = 'Gespeichert.';
    }

    $files = [];
    foreach (glob(SCISPIN_EDIT_CONTENT_DIR . '/*.md') ?: [] as $p) $files[] = basename($p);
    sort($files);

    $editing = ($validName && in_array($file, $files, true)) ? $file : null;
    $token = edit_esc($given);
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inhalte bearbeiten – SciSpin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="../assets/scispin.css">
<style>
  body{font-family:var(--sci-font);color:var(--sci-ink);background:var(--sci-bg);margin:0;}
  .wrap{max-width:820px;margin:0 auto;padding:32px 20px 60px;}
  h1{font-family:var(--sci-display);font-size:1.6rem;margin:0 0 1.2rem;}
  .notice{background:rgba(31,157,92,.12);border:1px solid rgba(31,157,92,.35);border-radius:8px;padding:.6rem .9rem;margin-bottom:1.2rem;font-size:.92rem;}
  ul.files{list-style:none;padding:0;margin:0;}
  ul.files li{border:1px solid var(--sci-line);border-radius:8px;padding:.7rem 1rem;margin-bottom:.5rem;display:flex;justify-content:space-between;align-items:center;background:var(--sci-card);}
  ul.files a{color:var(--sci-accent);text-decoration:none;font-weight:600;}
  ul.files a:hover{text-decoration:underline;}
  .view-link{font-size:.85rem;color:var(--sci-muted);}
  textarea{width:100%;min-height:60vh;font-family:var(--sci-mono);font-size:.92rem;line-height:1.5;padding:.8rem;border:1px solid var(--sci-line);border-radius:8px;box-sizing:border-box;}
  .actions{margin-top:.8rem;display:flex;gap:.9rem;align-items:center;flex-wrap:wrap;}
  button{font:inherit;font-weight:700;background:var(--sci-accent);color:#fff;border:0;border-radius:8px;padding:.6rem 1.3rem;cursor:pointer;}
  button:hover{opacity:.9;}
  .back{display:inline-block;margin-bottom:1.2rem;color:var(--sci-muted);text-decoration:none;font-size:.9rem;}
</style>
</head>
<body>
<div class="wrap">
<?php if ($notice !== ''): ?><div class="notice"><?= edit_esc($notice) ?></div><?php endif; ?>

<?php if ($editing !== null): ?>
  <a class="back" href="?token=<?= $token ?>">← Alle Seiten</a>
  <h1><?= edit_esc($editing) ?></h1>
  <?php $liveUrl = edit_page_url($editing); if ($liveUrl !== null): ?>
    <p class="view-link"><a href="<?= edit_esc($liveUrl) ?>" target="_blank" rel="noopener">Seite ansehen ↗</a></p>
  <?php endif; ?>
  <form method="post" action="?token=<?= $token ?>">
    <input type="hidden" name="token" value="<?= $token ?>">
    <input type="hidden" name="file" value="<?= edit_esc($editing) ?>">
    <textarea name="content" spellcheck="true"><?= edit_esc((string)file_get_contents(SCISPIN_EDIT_CONTENT_DIR . '/' . $editing)) ?></textarea>
    <div class="actions">
      <button type="submit">Speichern</button>
      <span class="view-link">Schreibt sofort live – kein GitHub, keine Versionsnummer nötig.</span>
    </div>
  </form>
<?php else: ?>
  <h1>Inhalte bearbeiten</h1>
  <ul class="files">
    <?php foreach ($files as $f): ?>
      <li>
        <a href="?token=<?= $token ?>&amp;file=<?= edit_esc($f) ?>"><?= edit_esc($f) ?></a>
        <?php $liveUrl = edit_page_url($f); if ($liveUrl !== null): ?>
          <a class="view-link" href="<?= edit_esc($liveUrl) ?>" target="_blank" rel="noopener">ansehen ↗</a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
</body>
</html>
<?php
}

edit_main();
