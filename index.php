<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SciSpin – Wissenschaftskommunikation prüfen und vorführen</title>
<meta name="description" content="Zwei Werkzeuge für Wissenschaftskommunikation: Studien-Check erkennt Spin, der SciSpin-O-Mat führt ihn vor.">
<link rel="stylesheet" href="assets/scispin.css">
<style>
  :root{
    --fg:#1a2030; --muted:#566076; --dim:#8b94a8; --line:#dce1ec; --bg:#f4f6fb; --card:#fff;
    --gruen:#1f9d5c; --gelb:#f9a825; --rot:#e0492a; --accent:#3b5bdb; --cold:#1593a8;
  }
  *{box-sizing:border-box;}
  body{margin:0; font-family:'Inter',system-ui,-apple-system,"Segoe UI",sans-serif; color:var(--fg);
       line-height:1.6;
       background:radial-gradient(1200px 600px at 70% -10%, rgba(59,91,219,.07), transparent 60%), var(--bg);}
  .wrap{max-width:900px; margin:0 auto; padding:0 24px;}
  header.hero-wrap{padding:56px 0 24px;}
  .eyebrow{font-size:12px; letter-spacing:.18em; text-transform:uppercase; color:var(--accent);
           font-weight:600; margin:0 0 14px;}
  h1{font-size:clamp(32px,5.5vw,52px); line-height:1.05; letter-spacing:-.02em; margin:0 0 18px;}
  h1 .turn{background:linear-gradient(90deg,var(--cold),var(--gruen) 50%,var(--rot));
           -webkit-background-clip:text; background-clip:text; color:transparent; font-style:italic;}
  .lede{font-size:19px; color:var(--muted); max-width:64ch; margin:0 0 8px;}

  .cards{display:grid; grid-template-columns:1fr 1fr; gap:20px; margin:40px 0 24px;}
  @media (max-width:640px){ .cards{grid-template-columns:1fr;} }
  .card{display:block; text-decoration:none; color:inherit; background:var(--card);
        border:1px solid var(--line); border-radius:14px; padding:26px 24px;
        box-shadow:0 1px 3px rgba(26,32,48,.06), 0 12px 32px rgba(26,32,48,.08);
        transition:transform .1s, border-color .15s;}
  .card:hover{transform:translateY(-2px); border-color:var(--accent);}
  .tag{display:inline-block; font-size:11px; letter-spacing:.06em; text-transform:uppercase;
       font-weight:700; padding:3px 9px; border-radius:20px; margin-bottom:14px;}
  .tag.check{color:var(--gruen); background:rgba(31,157,92,.12);}
  .tag.spin{color:var(--rot); background:rgba(224,73,42,.10);}
  .card h2{font-size:1.35rem; margin:0 0 .4rem;}
  .card p{color:var(--muted); margin:0 0 1rem; font-size:.98rem;}
  .go{font-weight:600; color:var(--accent); font-size:.95rem;}

  .concept{background:var(--card); border:1px solid var(--line); border-radius:14px;
           padding:22px 24px; margin:8px 0 24px; color:var(--muted);}
  .concept strong{color:var(--fg);}
</style>
</head>
<body>
<header class="wrap hero-wrap">
  <p class="eyebrow">Wissenschaftskommunikation</p>
  <h1>Wie weit trägt eine Aussage – und wo <span class="turn">kippt</span> sie?</h1>
  <p class="lede">Zwei Werkzeuge, dieselbe Frage: Deckt sich die Kommunikation mit dem,
  was die Wissenschaft hergibt? Das eine erkennt Spin, das andere führt ihn vor.</p>
  <p style="margin:2px 0 0"><a href="so-funktionierts.php" style="color:var(--accent);font-weight:600">So funktioniert's →</a></p>
</header>

<main class="wrap">
  <div class="cards">
    <a class="card" href="check/">
      <span class="tag check">Spin erkennen</span>
      <h2>Studien-Check</h2>
      <p>Link zu einer Studie einfügen – die App liest sie aus und bewertet auf einer
      Ampel, wie weit die Aussage trägt: was die Studie zeigt und was nicht.</p>
      <span class="go">Zum Studien-Check →</span>
    </a>
    <a class="card" href="spin/">
      <span class="tag spin">Spin vorführen</span>
      <h2>SciSpin-O-Mat</h2>
      <p>Eine Forschungsmeldung einfügen und den Regler ziehen: von „liest eh keiner"
      bis „völlig überdreht" – jede Stufe markiert, was sich verändert, und warum.</p>
      <span class="go">Zum SciSpin-O-Mat →</span>
    </a>
  </div>

  <div class="concept">
    <strong>Zwei Seiten einer Münze.</strong> Der SciSpin-O-Mat erzeugt Spin – er
    übertreibt und verkompliziert dieselbe Meldung. Der Studien-Check erkennt ihn –
    er prüft, was eine Studie wirklich hergibt. Wer beides nebeneinander sieht,
    versteht schneller, wie Framing eine wissenschaftliche Aussage trägt oder verzerrt.
  </div>
</main>

<script>window.SCISPIN = { root: '', active: 'home' };</script>
<script src="assets/chrome.js"></script>
</body>
</html>
