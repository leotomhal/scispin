// app.js – SciSpin-O-Mat Frontend-Logik (Einzelstufen-Architektur)
// Ein Klick holt die Stufen NACHEINANDER (ein Call pro Stufe, Stufe 0 zuerst
// als Themen-Gate). Der Slider zeigt jeweils die schon geladene Stufe; noch
// nicht geladene Stufen erscheinen, sobald ihr Call zurückkommt.

const STUFEN = {
  '3':  { name:"Was hat das noch mit der Arbeit zu tun?", badge:"hot",     btext:"Entgleist" },
  '2':  { name:"Zugespitzt",                              badge:"hot",     btext:"Grenzwertig" },
  '1':  { name:"Griffig gemacht",                          badge:"good",    btext:"Das Ziel" },
  '0':  { name:"Original",                                 badge:"neutral", btext:"Ausgangstext" },
  '-1': { name:"Solide, aber spröde",                      badge:"neutral", btext:"Trocken" },
  '-2': { name:"Fachsprachlich",                           badge:"cold",    btext:"Akademisch" },
  '-3': { name:"Liest eh keiner",                          badge:"cold",    btext:"Unlesbar" },
};

let stufenDaten = null; // { "-3":{...}, ..., "3":{...} } nach erfolgreichem Call

const $ = (s) => document.querySelector(s);
const beispielSelect = $("#beispiel");
const textarea = $("#eingabe");
const startBtn = $("#start");
const sliderWrap = $("#slider-wrap");
const slider = $("#slider");
const railLabels = $("#rail-labels");
const output = $("#output");
const tip = $("#tip");

// Beispiele füllen
(window.SCISPIN_BEISPIELE || []).forEach((b,i)=>{
  const o=document.createElement("option"); o.value=i; o.textContent=b.titel; beispielSelect.appendChild(o);
});
beispielSelect.addEventListener("change",()=>{
  const i=beispielSelect.value; if(i!=="") textarea.value=window.SCISPIN_BEISPIELE[i].text;
});

startBtn.addEventListener("click", starten);

slider.addEventListener("input",()=> markLabel(Number(slider.value)));
slider.addEventListener("change",()=> zeigeStufe(Number(slider.value)));

function markLabel(n){
  railLabels.querySelectorAll("span").forEach(s=> s.classList.toggle("on", Number(s.dataset.stufe)===n));
}

// Holt EINE Stufe vom Backend. Liest erst Text, dann JSON (robuste Fehleranzeige).
async function holeStufe(text, n) {
  const res = await fetch("api/generate.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ text: text, stufe: n })
  });

  const rohtext = await res.text();

  // Falls der Server mit einem HTML-Fehler (wie 502 Bad Gateway) antwortet:
  if (rohtext.trim().startsWith("<")) {
    document.getElementById("output").innerHTML = `
      <div style="padding:16px; background:#fff5f5; color:#c92a2a; border:1px solid #ffc9c9; font-family:monospace; white-space:pre-wrap; margin-bottom:20px;">
        <h3>Server-Fehler (HTTP ${res.status}):</h3>
        <p>Der Server hat die Verbindung abgebrochen. Das passiert bei manchen Hostern, wenn die API-Abfrage zu lange dauert.</p>
        <details><summary>Fehler-Details anzeigen</summary>${rohtext}</details>
      </div>`;
    throw new Error(`Server lieferte HTML statt JSON (HTTP ${res.status})`);
  }

  try {
    const json = JSON.parse(rohtext);
    if (json.error) throw new Error(json.error);
    return json;
  } catch (e) {
    console.error("Rohtext war:", rohtext);
    throw new Error("JSON-Parsing fehlgeschlagen: " + e.message);
  }
}

const STUFEN_REIHENFOLGE=[0,1,2,3,-1,-2,-3]; // 0 zuerst (Themen-Gate + Original)

async function starten(){
  const t=textarea.value.trim();
  if(!t){ textarea.focus(); return; }

  stufenDaten={};
  sliderWrap.hidden=true;
  startBtn.disabled=true;
  output.innerHTML=`<p class="placeholder"><span class="spinner"></span>Stufe 0 wird geprüft …</p>`;

  try{
    // 1) Stufe 0 zuerst – sie entscheidet über die Themenprüfung.
    const erste=await holeStufe(t,0);
    if(erste.ist_studie===false){
      output.innerHTML=`<div class="text-card" style="border-color:var(--hot)">
        <strong>Das sieht nicht nach einer Forschungsmeldung aus.</strong><br>
        ${escapeHtml(erste.ablehnungsgrund||"Bitte füge eine Studie, ein Abstract oder eine Wissenschaftsmeldung ein.")}
      </div>`;
      return;
    }
    stufenDaten["0"]=erste;
    sliderWrap.hidden=false;
    slider.value="0";
    markLabel(0);
    zeigeStufe(0);

    // 2) Restliche Stufen nacheinander nachladen; Slider füllt sich live.
    for(const n of STUFEN_REIHENFOLGE){
      if(n===0) continue;
      markLadefortschritt(n);

      // Kurze Pause zwischen den Calls (schont langsame Shared-Hosting-Server).
      await new Promise(resolve => setTimeout(resolve, 3000));

      try {
        stufenDaten[String(n)] = await holeStufe(t, n);
      }catch(e){
        // Eine fehlgeschlagene Stufe blockiert die anderen nicht.
        stufenDaten[String(n)]={fehler:e.message};
      }
      // Wenn der Nutzer gerade diese Stufe ansieht, sofort aktualisieren.
      if(Number(slider.value)===n) zeigeStufe(n);
    }
    markLadefortschritt(null);
  }catch(e){
    output.innerHTML=`<p class="placeholder">Fehler: ${escapeHtml(e.message)}</p>`;
  }finally{
    startBtn.disabled=false;
  }
}

// Markiert noch ladende Stufen-Labels dezent, fertige normal.
function markLadefortschritt(aktuelleLadestufe){
  railLabels.querySelectorAll("span").forEach(s=>{
    const n=Number(s.dataset.stufe);
    const geladen = stufenDaten && stufenDaten[String(n)]!==undefined;
    s.classList.toggle("laedt", n===aktuelleLadestufe);
    s.classList.toggle("bereit", geladen);
  });
}

function zeigeStufe(n){
  if(!stufenDaten) return;
  markLabel(n);
  const d=stufenDaten[String(n)];
  if(!d){ output.innerHTML=`<p class="placeholder"><span class="spinner"></span>Stufe ${n>0?'+'+n:n} wird noch erzeugt …</p>`; return; }
  if(d.fehler){ output.innerHTML=`<p class="placeholder">Diese Stufe konnte nicht erzeugt werden: ${escapeHtml(d.fehler)}</p>`; return; }
  const meta=STUFEN[String(n)];
  const dels=(d.aenderungen||[]).filter(a=>a.typ==='gestrichen');

  output.innerHTML=`
    <div class="out-head">
      <span class="out-num">${n>0?'+'+n:n}</span>
      <span class="out-stufe">${escapeHtml(meta.name)}</span>
      <span class="badge ${meta.badge}">${escapeHtml(meta.btext)}</span>
    </div>
    <div class="text-card">${baueMarkierten(d.text, d.aenderungen||[])}</div>
    ${dels.length?`<div class="text-card del-card">
      <span class="del-title">Gestrichen gegenüber Original</span>
      ${dels.map(a=>`<p><del class="change" data-tip="${escapeAttr(a.erklaerung)}" data-type="${escapeAttr(a.fehlertyp||'')}">${escapeHtml(a.original)}</del></p>`).join('')}
    </div>`:''}
    ${d.kommentar?`<div class="kommentar">${escapeHtml(d.kommentar)}</div>`:''}
    <div class="ref-toggle">Bezug:
      <button class="btn ghost" disabled>gegen Original</button>
      <button class="btn ghost" disabled title="kommt in v2">gegen Nachbarstufe</button>
    </div>`;
  bindeTooltips();
}

// Setzt die Markierungen für eine Stufe. Arbeitet auf dem ROHTEXT (nicht auf
// escaptem HTML), findet die Span tolerant gegenüber Mehrfach-Leerzeichen und
// escapt erst beim Zusammenbauen. So matchen auch Spans mit ", & oder Umlauten.
function baueMarkierten(text, aenderungen){
  const spans = aenderungen
    .filter(a => (a.typ==='eingefuegt'||a.typ==='veraendert') && a.neu)
    .map(a => ({ needle:a.neu, erklaerung:a.erklaerung, fehlertyp:a.fehlertyp||'' }));

  // Treffer im Rohtext suchen (erst exakt, dann whitespace-tolerant).
  const treffer = [];
  for(const s of spans){
    let idx = text.indexOf(s.needle);
    let len = s.needle.length;
    if(idx === -1){
      // Whitespace-tolerant: needle als Regex mit \s+ zwischen den Wörtern.
      const pat = s.needle.trim().split(/\s+/).map(escapeRegExp).join('\\s+');
      const m = new RegExp(pat).exec(text);
      if(m){ idx = m.index; len = m[0].length; }
    }
    if(idx !== -1) treffer.push({ start:idx, end:idx+len, ...s });
  }

  // Überlappungen entfernen (frühester Treffer gewinnt).
  treffer.sort((a,b)=> a.start-b.start || b.end-a.end);
  const sauber=[]; let lastEnd=-1;
  for(const t of treffer){ if(t.start>=lastEnd){ sauber.push(t); lastEnd=t.end; } }

  if(!sauber.length) return escapeHtml(text);
  return baueMarkiertenRest(text, sauber);
}

// Sauberer Mehrfach-Einsatz: zerlegt den Rohtext an allen Treffergrenzen und
// escapt jedes Segment einzeln. Vermeidet das Doppel-Escape-Problem.
function baueMarkiertenRest(text, treffer){
  treffer = treffer.slice().sort((a,b)=> a.start-b.start);
  let out=''; let pos=0;
  for(const t of treffer){
    out += escapeHtml(text.slice(pos,t.start));
    out += `<mark class="change" data-tip="${escapeAttr(t.erklaerung)}" data-type="${escapeAttr(t.fehlertyp)}">${escapeHtml(text.slice(t.start,t.end))}</mark>`;
    pos = t.end;
  }
  out += escapeHtml(text.slice(pos));
  return out;
}

function escapeRegExp(s){ return String(s).replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }

function bindeTooltips(){
  document.querySelectorAll("[data-tip]").forEach(el=>{
    el.tabIndex=0;
    el.addEventListener("mouseenter",e=>zeigeTip(e,el));
    el.addEventListener("mousemove",positioniereTip);
    el.addEventListener("mouseleave",versteckeTip);
    el.addEventListener("focus",e=>zeigeTip(e,el));
    el.addEventListener("blur",versteckeTip);
  });
}
function zeigeTip(e,el){
  const typ=el.dataset.type?`<span class="tip-type">${escapeHtml(el.dataset.type)}</span>`:"";
  tip.innerHTML=typ+escapeHtml(el.dataset.tip); tip.classList.add("show"); positioniereTip(e);
}
function positioniereTip(e){
  const x=(e.clientX||0)+14, y=(e.clientY||0)+14;
  tip.style.left=Math.min(x, window.innerWidth-320)+"px"; tip.style.top=y+"px";
}
function versteckeTip(){ tip.classList.remove("show"); }

function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));}
function escapeAttr(s){return escapeHtml(s);}
