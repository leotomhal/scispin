// app.js – Kurzmeldung (5 Bits Outline). Zwei-Phasen-Architektur:
//   Phase 1 holt das Gerüst (Bits 1–4), das Themen-Gate, das Evidenz-Etikett und
//   eine Warnung, falls schon das Original-Abstract Hype-Vokabular enthält.
//   Phase 2 holt – erst danach – den Aufmacher (Bit 5), die Kurzmeldung, die
//   markierbaren Regel-Eingriffe und (automatisch, serverseitig) das Ergebnis
//   des Studien-Checks zum Original-Material.
// So entsteht die Lede strukturell ZULETZT, wie es die Methode vorschreibt.
// Ausgabe ist dreispaltig: Abstract -> 5-Bits-Gerüst -> fertige Meldung.

const $ = (s) => document.querySelector(s);
const textarea = $("#eingabe");
const startBtn = $("#start");
const output   = $("#output");
const tip      = $("#tip");

// Brücke aus dem Studien-Check: dort übernommenen Text vorbefüllen.
try {
  const seed = sessionStorage.getItem("scispin_seed");
  if (seed) {
    sessionStorage.removeItem("scispin_seed");
    textarea.value = seed;
    const note = document.createElement("div");
    note.className = "sci-seed-note";
    note.textContent = 'Text aus dem Studien-Check übernommen – jetzt „Kurzmeldung erstellen".';
    textarea.parentNode.insertBefore(note, textarea);
    startBtn.focus();
  }
} catch (_) {}

startBtn.addEventListener("click", starten);

function metaLesen() {
  return {
    titel:   $("#m-titel").value.trim(),
    journal: $("#m-journal").value.trim(),
    doi:     $("#m-doi").value.trim(),
  };
}

// Holt EINE Phase vom Backend. Liest erst Text, dann JSON (robuste Fehleranzeige).
async function holePhase(body) {
  const res = await fetch("api/generate.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  const rohtext = await res.text();

  if (rohtext.trim().startsWith("<")) {
    throw new Error(`Server lieferte HTML statt JSON (HTTP ${res.status}). Bei manchen Hostern bricht die Verbindung ab, wenn die API-Abfrage zu lange dauert.`);
  }
  let json;
  try { json = JSON.parse(rohtext); }
  catch (e) { console.error("Rohtext war:", rohtext); throw new Error("JSON-Parsing fehlgeschlagen: " + e.message); }
  if (json.error) throw new Error(json.error);
  return json;
}

async function starten() {
  const t = textarea.value.trim();
  if (!t) { textarea.focus(); return; }
  const meta = metaLesen();

  startBtn.disabled = true;
  renderGeruest3(t, meta, null); // Skelett: Abstract sofort sichtbar, Gerüst/Meldung laden

  try {
    // --- Phase 1: Gerüst ---
    const bits = await holePhase({ text: t, phase: 1, ...meta });

    if (bits.ist_studie === false) {
      output.innerHTML = `<div class="bit-card reject">
        <strong>Das sieht nicht nach einer Forschungsarbeit aus.</strong><br>
        ${escapeHtml(bits.ablehnungsgrund || "Bitte füge eine Studie, ein Abstract oder eine Forschungsmeldung ein.")}
      </div>`;
      return;
    }

    renderGeruest3(t, meta, bits); // Abstract-Etikett + Gerüst füllen, Meldung noch ladend

    // --- Phase 2: Aufmacher + automatischer Studien-Check (erst jetzt) ---
    const brief = await holePhase({ text: t, phase: 2, bits, ...meta });
    renderMeldung(brief, meta);

  } catch (e) {
    output.innerHTML = `<p class="placeholder err">Fehler: ${escapeHtml(e.message)}</p>`;
  } finally {
    startBtn.disabled = false;
  }
}

const BITS = [
  { key: "frage",       num: "01", titel: "Die Frage",            hint: "Welche sehr spezifische Frage beantwortet die Arbeit?" },
  { key: "methoden",    num: "02", titel: "Die Methoden",         hint: "Nur die für die Schlussfolgerung entscheidenden Methoden." },
  { key: "engpass",     num: "03", titel: "Der Engpass im Feld",  hint: "Was hat das Feld bisher aufgehalten?" },
  { key: "fortschritt", num: "04", titel: "Der Fortschritt",      hint: "Wie bringt diese Arbeit das Feld voran?" },
];

/** Baut das dreispaltige Grundgerüst. bits === null -> alles außer Abstract lädt noch. */
function renderGeruest3(text, meta, bits) {
  output.innerHTML = `
    <div class="dreispalten">
      <section class="spalte spalte-abstract" id="spalte-abstract">
        ${spalteAbstract(text, bits)}
      </section>
      <section class="spalte spalte-geruest" id="spalte-geruest">
        ${bits ? geruestKarten(bits) : `<p class="placeholder"><span class="spinner"></span>Gerüst wird gebaut (Bits 1–4) …</p>`}
      </section>
      <section class="spalte spalte-meldung" id="spalte-meldung">
        <p class="placeholder">${bits ? '<span class="spinner"></span>Aufmacher, Kurzmeldung und Studien-Check werden erzeugt …' : 'Wartet auf das Gerüst …'}</p>
      </section>
    </div>`;
  bindeTooltips();
}

function spalteAbstract(text, bits) {
  return `
    <h3 class="spalte-titel">Abstract</h3>
    <div class="abstract-text">${escapeHtml(text)}</div>
    <p class="disclaimer">Basiert ausschließlich auf dem eingegebenen Abstract.</p>
    <div id="evidenz-slot">${bits ? evidenzChips(bits.evidenz) + hypeWarnung(bits.abstract_hype_warnung) : ''}</div>`;
}

function evidenzChips(evidenz) {
  if (!evidenz) return '';
  const preprintLabel = evidenz.preprint === 'ja' ? 'Preprint'
    : evidenz.preprint === 'nein' ? 'kein Preprint'
    : 'Preprint: ' + evidenz.preprint;
  const items = [evidenz.studientyp, evidenz.stichprobengroesse, evidenz.population, preprintLabel];
  return `<div class="evidenz-etikett">
    <span class="evidenz-label">Evidenz-Etikett</span>
    <div class="evidenz-chips">${items.map(i => `<span class="chip">${escapeHtml(i)}</span>`).join('')}</div>
  </div>`;
}

function hypeWarnung(warnung) {
  if (!warnung) return '';
  return `<div class="hype-warnung">⚠ Schon das Original-Abstract wirbt: ${escapeHtml(warnung)}</div>`;
}

function geruestKarten(bits) {
  return BITS.map(b => {
    let body;
    if (b.key === "methoden") {
      const list = (bits.methoden || []);
      body = list.length
        ? `<ul class="bit-list">${list.map(m => `<li>${escapeHtml(m)}</li>`).join("")}</ul>`
          + (bits.methoden_recap ? `<p class="bit-recap">${escapeHtml(bits.methoden_recap)}</p>` : "")
        : `<p>${escapeHtml(bits.methoden_recap || "—")}</p>`;
    } else {
      body = `<p>${escapeHtml(bits[b.key] || "—")}</p>`;
    }
    return `<article class="bit-card">
      <header class="bit-head"><span class="bit-num">${b.num}</span>
        <span class="bit-titel">${escapeHtml(b.titel)}</span></header>
      <div class="bit-body">${body}</div>
    </article>`;
  }).join("");
}

function renderMeldung(brief, meta) {
  const spalte = $("#spalte-meldung");
  if (!spalte) return;

  const hinweise = brief.regel_hinweise || [];
  const absaetze = String(brief.kurzmeldung || "")
    .split(/\n{2,}/).map(p => p.trim()).filter(Boolean)
    .map(p => `<p>${markiereRegeln(p, hinweise)}</p>`).join("");

  const beleg = studienbeleg(meta);

  spalte.innerHTML = `
    <article class="bit-card bit5">
      <header class="bit-head"><span class="bit-num">05</span>
        <span class="bit-titel">Der Aufmacher</span>
        <span class="bit-flag">zuletzt geschrieben</span></header>
      <div class="bit-body">
        ${brief.lede ? `<p class="lede-satz">${escapeHtml(brief.lede)}</p>` : ""}
        <div class="kurzmeldung">
          ${brief.headline ? `<h3 class="km-head">${escapeHtml(brief.headline)}</h3>` : ""}
          ${absaetze}
          ${beleg ? `<p class="km-beleg">${beleg}</p>` : ""}
        </div>
        ${hinweise.length ? `<p class="regel-legende">Markierte Stellen: hier hat eine Vorsichts-Regel gegriffen – antippen für die Erklärung.</p>` : ""}
        <div class="km-actions">
          <button class="btn ghost" id="copy">Kurzmeldung kopieren</button>
          <button class="btn" id="tospin" title="Prüfen, ob der eigene Aufmacher übertreibt">In den SciSpin-O-Mat →</button>
        </div>
      </div>
    </article>
    ${studienCheckPanel(brief.studien_check)}`;

  const klartext = (brief.headline ? brief.headline + "\n\n" : "") + brief.kurzmeldung;
  $("#copy").addEventListener("click", async (ev) => {
    try { await navigator.clipboard.writeText(klartext); ev.target.textContent = "Kopiert ✓"; }
    catch (_) { ev.target.textContent = "Kopieren nicht möglich"; }
    setTimeout(() => { ev.target.textContent = "Kurzmeldung kopieren"; }, 1600);
  });
  $("#tospin").addEventListener("click", () => {
    try { sessionStorage.setItem("scispin_seed", brief.kurzmeldung); } catch (_) {}
    location.href = "../spin/";
  });
  bindeTooltips();
}

function studienCheckPanel(sc) {
  if (!sc) {
    return `<div class="check-panel check-panel-empty">
      <span class="check-titel">Studien-Check zum Original</span>
      <p>Nicht verfügbar (z. B. Tageskontingent erreicht). Die Kurzmeldung bleibt davon unberührt.</p>
    </div>`;
  }
  const ampel = sc.ampel || {};
  const color = ["gruen", "gelb", "rot", "grau"].includes(ampel.color) ? ampel.color : "grau";
  const einschr = sc.einschraenkungen || [];
  return `<div class="check-panel">
    <div class="check-head">
      <span class="dot ${color}"></span>
      <span class="check-titel">Studien-Check zum Original</span>
      ${sc.study_type ? `<span class="check-studientyp">${escapeHtml(sc.study_type)}</span>` : ""}
    </div>
    ${sc.core_statement ? `<p class="check-core">${escapeHtml(sc.core_statement)}</p>` : ""}
    ${ampel.begruendung ? `<p class="check-begruendung">${escapeHtml(ampel.begruendung)}</p>` : ""}
    ${einschr.length ? `<ul class="check-einschraenkungen">${einschr.map(e => `<li>${escapeHtml(e)}</li>`).join("")}</ul>` : ""}
    <a class="check-link" href="../check/">Volle Analyse im Studien-Check →</a>
  </div>`;
}

function studienbeleg(meta) {
  const teile = [];
  if (meta.titel)   teile.push(escapeHtml(meta.titel));
  if (meta.journal) teile.push(`<em>${escapeHtml(meta.journal)}</em>`);
  if (meta.doi)     teile.push("DOI: " + escapeHtml(meta.doi));
  return teile.length ? "Studie: " + teile.join(", ") : "";
}

// Markiert Textstellen in "text", an denen laut "hinweise" eine Vorsichts-Regel
// gegriffen hat. Sucht erst exakt, dann whitespace-tolerant (wie die Änderungs-
// markierungen im SciSpin-O-Mat) – findet sich keine Stelle, wird der Hinweis
// still übergangen statt die Anzeige zu brechen.
function markiereRegeln(text, hinweise) {
  const treffer = [];
  for (const h of hinweise) {
    if (!h.text) continue;
    let idx = text.indexOf(h.text);
    let len = h.text.length;
    if (idx === -1) {
      const pat = h.text.trim().split(/\s+/).map(escapeRegExp).join("\\s+");
      try {
        const m = new RegExp(pat).exec(text);
        if (m) { idx = m.index; len = m[0].length; }
      } catch (_) { /* ungültiges Muster: überspringen */ }
    }
    if (idx !== -1) treffer.push({ start: idx, end: idx + len, regel: h.regel || "", hinweis: h.hinweis || "" });
  }
  treffer.sort((a, b) => a.start - b.start || b.end - a.end);
  const sauber = []; let lastEnd = -1;
  for (const t of treffer) { if (t.start >= lastEnd) { sauber.push(t); lastEnd = t.end; } }
  if (!sauber.length) return escapeHtml(text);

  let out = ""; let pos = 0;
  for (const t of sauber) {
    out += escapeHtml(text.slice(pos, t.start));
    out += `<mark class="regel-mark" data-tip="${escapeAttr(t.hinweis)}" data-type="${escapeAttr(t.regel)}">${escapeHtml(text.slice(t.start, t.end))}</mark>`;
    pos = t.end;
  }
  out += escapeHtml(text.slice(pos));
  return out;
}

function bindeTooltips() {
  document.querySelectorAll("[data-tip]").forEach(el => {
    el.tabIndex = 0;
    el.addEventListener("mouseenter", e => zeigeTip(e, el));
    el.addEventListener("mousemove", positioniereTip);
    el.addEventListener("mouseleave", versteckeTip);
    el.addEventListener("focus", e => zeigeTip(e, el));
    el.addEventListener("blur", versteckeTip);
  });
}
function zeigeTip(e, el) {
  const typ = el.dataset.type ? `<span class="tip-type">${escapeHtml(el.dataset.type)}</span>` : "";
  tip.innerHTML = typ + escapeHtml(el.dataset.tip);
  tip.classList.add("show");
  positioniereTip(e);
}
function positioniereTip(e) {
  const x = (e.clientX || 0) + 14, y = (e.clientY || 0) + 14;
  tip.style.left = Math.min(x, window.innerWidth - 320) + "px";
  tip.style.top = y + "px";
}
function versteckeTip() { tip.classList.remove("show"); }

function escapeRegExp(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }
function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
function escapeAttr(s) { return escapeHtml(s); }
