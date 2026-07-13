<?php
/**
 * Rendert eine Inhaltsseite aus einer Markdown-Datei in das geteilte
 * SciSpin-Layout (Kopfleiste + Footer + Designsprache). Die Seiten-Dateien
 * (z. B. so-funktionierts.php) sind damit nur noch dünne Hüllen; der Text
 * liegt in content/*.md und lässt sich dort einfach bearbeiten.
 *
 * sci_page([
 *   'title'        => 'Titel – SciSpin',
 *   'description'  => 'Meta-Description',
 *   'root'         => '',            // '' in der Wurzel, '../' in Unterordnern
 *   'active'       => 'hilfe',       // aktiver Menüpunkt (oder '')
 *   'content_file' => __DIR__.'/content/xy.md',
 *   'extra_css'    => '...'          // optionales Seiten-CSS
 * ]);
 */

require_once __DIR__ . '/markdown.php';

function sci_page(array $o): void {
    $title    = $o['title'] ?? 'SciSpin';
    $desc     = $o['description'] ?? '';
    $root     = $o['root'] ?? '';
    $active   = $o['active'] ?? '';
    $extraCss = $o['extra_css'] ?? '';

    $md = @file_get_contents($o['content_file'] ?? '');
    $content = ($md === false || trim($md) === '')
        ? '<p>Inhalt nicht gefunden.</p>'
        : sci_markdown($md);

    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $esc($title) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= $esc($desc) ?>">
<?php endif; ?><link rel="stylesheet" href="<?= $esc($root) ?>assets/scispin.css">
<style>
  *{box-sizing:border-box;}
  body{margin:0; font-family:var(--sci-font); color:var(--sci-ink); line-height:1.65;
       background:radial-gradient(1200px 600px at 70% -10%, rgba(59,91,219,.06), transparent 60%), var(--sci-bg);}
  .sci-article{max-width:760px; margin:0 auto; padding:40px 24px 56px;}
  .sci-article h1{font-family:var(--sci-display); font-weight:900; font-size:clamp(28px,5vw,44px);
       line-height:1.06; letter-spacing:-.02em; margin:.2rem 0 .7rem;}
  .sci-article h2{font-family:var(--sci-display); font-size:1.55rem; margin:1.9rem 0 .3rem;}
  .sci-article h3{font-size:1.05rem; margin:1.3rem 0 .2rem;}
  .sci-article p{margin:.6rem 0;}
  .sci-article ul,.sci-article ol{margin:.4rem 0; padding-left:1.3rem;}
  .sci-article li{margin:.25rem 0;}
  .sci-article a{color:var(--sci-accent);}
  .sci-article blockquote{border-left:3px solid var(--sci-accent); background:rgba(59,91,219,.05);
       border-radius:0 8px 8px 0; margin:1rem 0; padding:.8rem 1.1rem; color:var(--sci-muted);}
  .sci-article code{font-family:var(--sci-mono); background:rgba(26,32,48,.06);
       padding:.1em .35em; border-radius:5px; font-size:.92em;}
  .sci-article hr{border:0; border-top:1px solid var(--sci-line); margin:1.7rem 0;}
<?= $extraCss ?>
</style>
</head>
<body>
<article class="sci-article">
<?= $content ?>
</article>
<script>window.SCISPIN = <?= json_encode(['root' => $root, 'active' => $active]) ?>;</script>
<script src="<?= $esc($root) ?>assets/chrome.js"></script>
</body>
</html>
<?php
}
