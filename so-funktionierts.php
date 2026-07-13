<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>So funktioniert's – SciSpin</title>
<meta name="description" content="Wie der Studien-Check und der SciSpin-O-Mat funktionieren: Bedienung, Ausgabe und was die Ampel bzw. die Skala bedeuten.">
<link rel="stylesheet" href="assets/scispin.css">
<style>
  *{box-sizing:border-box;}
  body{margin:0; font-family:var(--sci-font); color:var(--sci-ink); line-height:1.65;
       background:radial-gradient(1200px 600px at 70% -10%, rgba(59,91,219,.06), transparent 60%), var(--sci-bg);}
  .wrap{max-width:760px; margin:0 auto; padding:0 24px;}
  header.hero{padding:48px 0 8px;}
  .eyebrow{font-family:var(--sci-mono); font-size:12px; letter-spacing:.16em; text-transform:uppercase;
           color:var(--sci-accent); margin:0 0 12px;}
  h1{font-family:var(--sci-display); font-weight:900; font-size:clamp(30px,5vw,46px);
     line-height:1.05; letter-spacing:-.02em; margin:0 0 14px;}
  .lede{font-size:18px; color:var(--sci-muted); margin:0 0 8px;}
  main{padding-bottom:48px;}

  .toc{display:flex; gap:.6rem; flex-wrap:wrap; margin:20px 0 8px; font-family:var(--sci-mono); font-size:13px;}
  .toc a{text-decoration:none; color:var(--sci-accent); border:1px solid var(--sci-line);
         border-radius:999px; padding:.35rem .8rem; background:var(--sci-card);}
  .toc a:hover{border-color:var(--sci-accent);}

  section.tool{background:var(--sci-card); border:1px solid var(--sci-line); border-radius:14px;
    padding:26px 28px; margin:28px 0; box-shadow:0 1px 3px rgba(26,32,48,.05),0 12px 32px rgba(26,32,48,.06);
    scroll-margin-top:70px;}
  .tag{display:inline-block; font-family:var(--sci-mono); font-size:11px; letter-spacing:.06em;
       text-transform:uppercase; font-weight:700; padding:3px 9px; border-radius:20px; margin-bottom:10px;}
  .tag.check{color:var(--sci-good); background:rgba(31,157,92,.12);}
  .tag.spin{color:var(--sci-hot); background:rgba(224,73,42,.10);}
  h2{font-family:var(--sci-display); font-size:1.6rem; margin:.1rem 0 .3rem;}
  h3{font-size:1.02rem; margin:1.4rem 0 .3rem;}
  p{margin:.5rem 0;}
  ol.steps{margin:.4rem 0 .4rem; padding-left:1.2rem;}
  ol.steps li{margin:.35rem 0;}
  ul{margin:.3rem 0; padding-left:1.2rem;}
  li{margin:.2rem 0;}
  a{color:var(--sci-accent);}
  .muted{color:var(--sci-muted);}

  /* Ampel-Legende */
  .ampel{display:grid; grid-template-columns:auto 1fr; gap:.4rem .7rem; align-items:center;
         margin:.6rem 0; font-size:.95rem;}
  .dot{width:.8rem; height:.8rem; border-radius:50%; display:inline-block;}
  .dot.gruen{background:var(--sci-good);} .dot.gelb{background:#f9a825;}
  .dot.rot{background:var(--sci-hot);} .dot.grau{background:#9e9e9e;}

  /* Skala-Band */
  .scale{margin:.8rem 0; border:1px solid var(--sci-line); border-radius:10px; overflow:hidden;}
  .scale .band{height:10px; background:linear-gradient(90deg,var(--sci-cold),#b9c3d8 28%,var(--sci-good) 50%,#e6c2b6 72%,var(--sci-hot));}
  .scale .marks{display:flex; justify-content:space-between; font-family:var(--sci-mono);
                font-size:11px; color:var(--sci-dim); padding:.4rem .7rem;}
  .marks b{color:var(--sci-good);}

  .mark-demo{background:#fdecdf; border-bottom:2px solid var(--sci-hot); padding:1px 3px; border-radius:3px;}
  .del-demo{color:#9aa0b0; text-decoration:line-through; text-decoration-color:var(--sci-hot);}

  .bridge-note{border-left:3px solid var(--sci-accent); background:rgba(59,91,219,.05);
    border-radius:0 8px 8px 0; padding:14px 18px; margin:24px 0;}
  .disclaimer{font-size:.85rem; color:var(--sci-dim); margin-top:8px;}
</style>
</head>
<body>
<header class="hero wrap">
  <p class="eyebrow">So funktioniert's</p>
  <h1>Zwei Werkzeuge, eine Frage</h1>
  <p class="lede">Deckt sich die Kommunikation mit dem, was die Wissenschaft hergibt?
  Der <strong>Studien-Check</strong> erkennt Spin, der <strong>SciSpin-O-Mat</strong> führt ihn vor.
  Hier steht, wie du beide bedienst und wie du die Ausgabe liest.</p>
  <div class="toc">
    <a href="#check">↓ Studien-Check</a>
    <a href="#spin">↓ SciSpin-O-Mat</a>
    <a href="#bruecke">↓ Die Brücke</a>
  </div>
</header>

<main class="wrap">

  <section class="tool" id="check">
    <span class="tag check">Spin erkennen</span>
    <h2>Studien-Check</h2>
    <p>Beantwortet: <em>Wie weit trägt die Aussage einer Studie?</em> Bewertet wird die
    <strong>Reichweite der Aussage</strong> – nicht, ob sie „wahr" ist, und nicht die Qualität des Journals.</p>

    <h3>So benutzt du ihn</h3>
    <ol class="steps">
      <li>Den <strong>Link zu einer Studie</strong> einfügen (DOI-Link, PubMed, arXiv oder Verlagsseite) und „Prüfen".</li>
      <li>Die App liest die Studie automatisch aus: Kennung (DOI/PMID/arXiv) erkennen,
      Metadaten über <span class="muted">Crossref</span>, Abstract über <span class="muted">Europe PMC / arXiv</span>,
      sonst die Meta-Angaben der Seite.</li>
      <li>Findet sie keinen Abstract, kannst du ihn <strong>selbst einfügen</strong> und trotzdem prüfen lassen.</li>
    </ol>

    <h3>Was die Ampel bedeutet</h3>
    <div class="ampel">
      <span class="dot gruen"></span><span><strong>Tragfähig</strong> – die Aussage ist durch Methode und Daten gedeckt.</span>
      <span class="dot gelb"></span><span><strong>Mit Vorsicht</strong> – im Kern in Ordnung, aber leicht überdehnt oder eingeschränkt.</span>
      <span class="dot rot"></span><span><strong>Überdehnt</strong> – die Aussage geht weiter, als die Studie hergibt.</span>
      <span class="dot grau"></span><span><strong>Nicht bewertbar</strong> – nötige Methodendetails fehlen im Text (oft nur Abstract).</span>
    </div>

    <h3>Was du im Ergebnis siehst</h3>
    <ul>
      <li><strong>Kernaussage</strong> und eine Fassung „<strong>einfach erklärt</strong>" (ohne Fachjargon).</li>
      <li><strong>Das zeigt die Studie – das zeigt sie nicht</strong>: konkret, keine Floskeln.</li>
      <li><strong>Wie wurde untersucht?</strong> – der Studientyp in Alltagssprache, verlinkt zu den
      <a href="methoden.php">Methoden</a> (Evidenz-Einordnung).</li>
      <li><strong>Passt die Methode zur Aussage?</strong> – das Herzstück, begründet die Ampel.</li>
      <li>Ein <strong>Badge</strong>: Peer-Review, Preprint (noch nicht begutachtet) oder Status unklar.</li>
    </ul>
    <p class="muted">Jede Prüfung landet im durchsuchbaren <a href="check/archive.php">Archiv</a>.</p>

    <h3>Grenzen</h3>
    <ul>
      <li>Meist liegt nur der <strong>Abstract</strong> vor – Methodendetails, die dort fehlen, fließen nicht ein.</li>
      <li>Kein Urteil über <em>Raubverlage</em>; nicht-biomedizinische Felder sind schwächer abgedeckt.</li>
    </ul>
    <p class="disclaimer">Automatisierte Einschätzung, kein Ersatz für fachliche Prüfung.</p>
  </section>

  <section class="tool" id="spin">
    <span class="tag spin">Spin vorführen</span>
    <h2>SciSpin-O-Mat</h2>
    <p>Beantwortet: <em>Wie verändert Framing dieselbe Aussage?</em> Er schreibt eine Forschungsmeldung
    auf einer Skala um – von unlesbar-fachlich bis reißerisch-entstellt – und zeigt an jeder Stufe, was passiert.</p>

    <h3>So benutzt du ihn</h3>
    <ol class="steps">
      <li>Den Text einer <strong>Forschungsmeldung oder eines Abstracts</strong> einfügen und „Skala anwenden".</li>
      <li>Den <strong>Regler</strong> ziehen. Jede Stufe zeigt den umgeschriebenen Text mit Markierungen und einem Kommentar.</li>
    </ol>

    <h3>Die Skala von −3 bis +3</h3>
    <div class="scale">
      <div class="band"></div>
      <div class="marks"><span>−3 unlesbar</span><span><b>+1 das Ziel</b></span><span>+3 entgleist</span></div>
    </div>
    <ul>
      <li><strong>Links (−3 … −1):</strong> immer fachsprachlicher und verschachtelter – die <em>Verständlichkeit</em> verschwindet.</li>
      <li><strong>Mitte (0):</strong> die Original-Meldung als Vergleichspunkt.</li>
      <li><strong>+1 – das Ziel:</strong> verständlich <em>und</em> korrekt, Einschränkungen bleiben erhalten.</li>
      <li><strong>Rechts (+2 … +3):</strong> immer zugespitzter – die <em>Korrektheit</em> verschwindet.</li>
    </ul>

    <h3>Was die Markierungen bedeuten</h3>
    <ul>
      <li><span class="mark-demo">Hervorgehoben</span>: hier wurde etwas eingefügt oder verändert.</li>
      <li><span class="del-demo">Durchgestrichen</span>: hier wurde etwas <strong>weggelassen</strong> – oft das Irreführende (z. B. eine gestrichene Einschränkung), darum eigens ausgewiesen.</li>
      <li>Fahr über eine Markierung: ein <strong>Tooltip</strong> erklärt den jeweiligen Kommunikationsfehler.</li>
    </ul>

    <p class="muted">Die zentrale Asymmetrie: links darf die <em>Sprache</em> überziehen, rechts nie der <em>Inhalt</em> –
    es werden keine Fakten erfunden, die Entstellung entsteht durch Framing und Weglassen.
    Mehr zum Gedanken dahinter auf der Seite <a href="spin/ueber.html">Über den SciSpin-O-Mat</a>.</p>
    <p class="disclaimer">Bewusst zugespitztes Demonstrationswerkzeug, kein Autoren-Tool.</p>
  </section>

  <section class="tool" id="bruecke">
    <h2>Die Brücke</h2>
    <p>Beide Tools greifen ineinander: Nach einem Studien-Check führt der Button
    <strong>„→ Wie überdreht man das?"</strong> den Ergebnis-Text direkt in den SciSpin-O-Mat.
    So siehst du an <em>einem</em> Beispiel beides – was die Studie wirklich sagt und wie leicht
    daraus Spin wird.</p>
    <div class="bridge-note">
      Kurz gesagt: Der Studien-Check misst, <strong>wie weit eine Aussage trägt</strong>.
      Der SciSpin-O-Mat zeigt, <strong>wie Framing sie trägt oder kippt</strong>.
    </div>
  </section>

</main>

<script>window.SCISPIN = { root: '', active: 'hilfe' };</script>
<script src="assets/chrome.js"></script>
</body>
</html>
