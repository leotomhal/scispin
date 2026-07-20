// app.js – Kurzmeldung (5 Bits Outline). Zwei-Phasen-Architektur:
//   Phase 1 holt das Gerüst (Bits 1–4) und das Themen-Gate.
//   Phase 2 holt – erst danach – den Aufmacher (Bit 5) und die Kurzmeldung.
// So entsteht die Lede strukturell ZULETZT, wie es die Methode vorschreibt.

const $ = (s) => document.querySelector(s);
const textarea = $("#eingabe");
const startBtn = $("#start");
const output   = $("#output");

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
  output.innerHTML = `<p class="placeholder"><span class="spinner"></span>Das Gerüst wird gebaut (Bits 1–4) …</p>`;

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

    renderGeruest(bits);         // Bits 1–4 + Bit 5 als „wird zuletzt geschrieben"

    // --- Phase 2: Aufmacher (erst jetzt) ---
    setBit5Laden();
    const brief = await holePhase({ text: t, phase: 2, bits, ...meta });
    renderBit5(brief, meta);

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

function renderGeruest(bits) {
  const cards = BITS.map(b => {
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

  output.innerHTML = `
    <div class="geruest">${cards}</div>
    <article class="bit-card bit5" id="bit5">
      <header class="bit-head"><span class="bit-num">05</span>
        <span class="bit-titel">Der Aufmacher</span>
        <span class="bit-flag">wird zuletzt geschrieben</span></header>
      <div class="bit-body" id="bit5-body">
        <p class="placeholder"><span class="spinner"></span>Gerüst steht – der Aufmacher entsteht jetzt.</p>
      </div>
    </article>`;
}

function setBit5Laden() {
  const el = $("#bit5-body");
  if (el) el.innerHTML = `<p class="placeholder"><span class="spinner"></span>Aufmacher und Kurzmeldung werden geschrieben …</p>`;
}

function renderBit5(brief, meta) {
  const el = $("#bit5-body");
  if (!el) return;
  const absaetze = String(brief.kurzmeldung || "")
    .split(/\n{2,}/).map(p => p.trim()).filter(Boolean)
    .map(p => `<p>${escapeHtml(p)}</p>`).join("");

  const beleg = studienbeleg(meta);

  el.innerHTML = `
    ${brief.lede ? `<p class="lede-satz">${escapeHtml(brief.lede)}</p>` : ""}
    <div class="kurzmeldung">
      ${brief.headline ? `<h3 class="km-head">${escapeHtml(brief.headline)}</h3>` : ""}
      ${absaetze}
      ${beleg ? `<p class="km-beleg">${beleg}</p>` : ""}
    </div>
    <div class="km-actions">
      <button class="btn ghost" id="copy">Kurzmeldung kopieren</button>
      <button class="btn ghost" id="tospin" title="Prüfen, ob der eigene Aufmacher übertreibt">In den SciSpin-O-Mat →</button>
    </div>`;

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
}

function studienbeleg(meta) {
  const teile = [];
  if (meta.titel)   teile.push(escapeHtml(meta.titel));
  if (meta.journal) teile.push(`<em>${escapeHtml(meta.journal)}</em>`);
  if (meta.doi)     teile.push("DOI: " + escapeHtml(meta.doi));
  return teile.length ? "Studie: " + teile.join(", ") : "";
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}
