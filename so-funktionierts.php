<?php
require __DIR__ . '/lib/page.php';
sci_page([
    'title'        => "So funktioniert's – SciSpin",
    'description'  => "Wie der Studien-Check und der SciSpin-O-Mat funktionieren: Bedienung, Ausgabe und was die Ampel bzw. die Skala bedeuten.",
    'root'         => '',
    'active'       => 'hilfe',
    'content_file' => __DIR__ . '/content/so-funktionierts.md',
    'extra_css'    => <<<'CSS'
  .sci-article .eyebrow{font-family:var(--sci-mono);font-size:12px;letter-spacing:.16em;text-transform:uppercase;color:var(--sci-accent);margin:0 0 6px;}
  .sci-article .anchor{display:block;height:0;scroll-margin-top:72px;}
  .toc{display:flex;gap:.5rem;flex-wrap:wrap;margin:16px 0 8px;font-family:var(--sci-mono);font-size:13px;}
  .toc a{text-decoration:none;color:var(--sci-accent);border:1px solid var(--sci-line);border-radius:999px;padding:.3rem .8rem;background:var(--sci-card);}
  .toc a:hover{border-color:var(--sci-accent);}
  .tag{display:inline-block;font-family:var(--sci-mono);font-size:11px;letter-spacing:.06em;text-transform:uppercase;font-weight:700;padding:3px 9px;border-radius:20px;margin:.4rem 0 0;}
  .tag.check{color:var(--sci-good);background:rgba(31,157,92,.12);}
  .tag.spin{color:var(--sci-hot);background:rgba(224,73,42,.10);}
  .ampel{display:grid;grid-template-columns:auto 1fr;gap:.4rem .7rem;align-items:center;margin:.7rem 0;}
  .dot{width:.8rem;height:.8rem;border-radius:50%;display:inline-block;}
  .dot.gruen{background:var(--sci-good);}.dot.gelb{background:#f9a825;}.dot.rot{background:var(--sci-hot);}.dot.grau{background:#9e9e9e;}
  .scale{margin:.8rem 0;border:1px solid var(--sci-line);border-radius:10px;overflow:hidden;}
  .scale .band{height:10px;background:linear-gradient(90deg,var(--sci-cold),#b9c3d8 28%,var(--sci-good) 50%,#e6c2b6 72%,var(--sci-hot));}
  .scale .marks{display:flex;justify-content:space-between;font-family:var(--sci-mono);font-size:11px;color:var(--sci-dim);padding:.4rem .7rem;}
  .scale .marks b{color:var(--sci-good);}
  .mark-demo{background:#fdecdf;border-bottom:2px solid var(--sci-hot);padding:1px 3px;border-radius:3px;}
  .del-demo{color:#9aa0b0;text-decoration:line-through;text-decoration-color:var(--sci-hot);}
CSS,
]);
