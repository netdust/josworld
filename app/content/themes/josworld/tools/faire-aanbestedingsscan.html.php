<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Faire aanbestedingsscan | JOS</title>
<style>
/* ============================================================
   THEMING LAYER
   Alles wat de look bepaalt zit in deze regels. Vervang de
   waarden door de tokens van de nieuwe website en de tool neemt
   de huisstijl over zonder verdere ingrepen.
   ============================================================ */
/* Self-hosted: the site's own Inter and Fraunces, no call to Google. */
@font-face{font-family:'Inter';font-weight:400;font-style:normal;font-display:swap;src:url(/content/themes/josworld/fonts/inter-400.woff2) format('woff2')}
@font-face{font-family:'Inter';font-weight:600;font-style:normal;font-display:swap;src:url(/content/themes/josworld/fonts/inter-600.woff2) format('woff2')}
@font-face{font-family:'Fraunces';font-weight:300;font-style:normal;font-display:swap;src:url(/content/themes/josworld/fonts/fraunces-300.woff2) format('woff2')}
@font-face{font-family:'Fraunces';font-weight:400;font-style:normal;font-display:swap;src:url(/content/themes/josworld/fonts/fraunces-400.woff2) format('woff2')}
:root{
  --paper:#FCFCF7;        /* achtergrond van de pagina         */
  --card:#FFFFFF;         /* vlak van vraag- en resultaatkaart */
  --ink:#35070C;          /* hoofdtekst en primaire knop       */
  --ink-soft:#866A6D;     /* secundaire tekst                  */
  --line:#EBE6E7;         /* randen en scheidingslijnen        */
  --accent:#E60B66;       /* eyebrow, goed antwoord, hoge score */
  --accent-soft:#FCE5EE;
  --warn:#C07C00;         /* deels antwoord, middenscore       */
  --warn-soft:#F8EFDA;
  --alert:#B03A20;        /* nee antwoord, lage score          */
  --alert-soft:#F7E7E2;
  --display:'Fraunces',Georgia,'Times New Roman',serif;
  --body:'Inter','Helvetica Neue',Arial,sans-serif;
  --r:14px;               /* hoekradius vlakken, 0 = strak     */
  --btn-r:999px;          /* hoekradius knoppen, 6px = strak   */
  --maxw:820px;           /* stem af op de contentbreedte van de site */
}
/* ============================================================ */

*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{background:var(--paper);color:var(--ink);font-family:var(--body);font-size:16px;line-height:1.55;-webkit-font-smoothing:antialiased}
.wrap{max-width:var(--maxw);margin:0 auto;padding:40px 20px 80px}

h1,h2,h3{font-family:var(--display);font-weight:400;letter-spacing:-.02em;margin:0}
h1{font-size:clamp(34px,6vw,54px);line-height:1.02}
h2{font-size:clamp(24px,4vw,32px);line-height:1.1}
h3{font-size:19px;font-weight:600;letter-spacing:-.01em}
p{margin:0 0 14px}
.eyebrow{font-family:var(--body);font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--accent);margin:0 0 18px}
.lead{font-size:18px;color:var(--ink-soft);max-width:56ch}
.small{font-size:14px;color:var(--ink-soft)}

.btn{display:inline-flex;align-items:center;gap:10px;font-family:var(--body);font-size:15px;font-weight:600;background:var(--ink);color:#fff;border:1px solid var(--ink);padding:14px 24px;border-radius:var(--btn-r);cursor:pointer;transition:transform .15s ease,opacity .15s ease}
.btn:hover{transform:translateY(-1px);opacity:.9}
.btn:disabled{opacity:.32;cursor:not-allowed;transform:none}
.btn-ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
.btn-ghost:hover{background:var(--card)}
.linkbtn{background:none;border:0;padding:0;font-family:var(--body);font-size:15px;font-weight:600;color:var(--accent);cursor:pointer;text-decoration:underline;text-underline-offset:3px}
:focus-visible{outline:3px solid var(--accent);outline-offset:3px}

.stance{border-left:3px solid var(--accent);padding:2px 0 2px 18px;margin:26px 0 30px;max-width:58ch}
.stance p{font-size:15.5px;color:var(--ink-soft);margin:0}
.stance p+p{margin-top:10px}

.start{display:flex;align-items:center;gap:18px;flex-wrap:wrap;margin-top:30px}
.start .sub{font-size:14px;color:var(--ink-soft)}
.detour{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;margin-top:26px;padding:20px 22px;border:1px dashed var(--line);border-radius:var(--r)}
.detour div{max-width:46ch}
.detour strong{display:block;font-weight:600;margin-bottom:2px}
.detour p{margin:0;font-size:14px;color:var(--ink-soft)}

.spine{list-style:none;margin:0;padding:0;border-top:1px solid var(--line)}
.spine li{display:grid;grid-template-columns:52px 1fr;gap:16px;padding:16px 0;border-bottom:1px solid var(--line);align-items:baseline}
.spine .num{font-family:var(--display);font-size:15px;font-weight:400;color:var(--accent)}
.spine .lbl{font-weight:600}
.spine .desc{display:block;font-weight:400;color:var(--ink-soft);font-size:15px}

.progress{display:flex;gap:6px;margin-bottom:8px}
.progress span{height:4px;flex:1;background:var(--line);border-radius:2px;transition:background .4s ease}
.progress span.done{background:var(--accent)}
.progress span.now{background:var(--ink)}
.progress-label{display:flex;justify-content:space-between;font-size:13px;color:var(--ink-soft);margin-bottom:30px}
.phase-head{margin-bottom:26px}
.phase-head .num{font-family:var(--display);font-size:13px;font-weight:400;letter-spacing:.1em;color:var(--accent);display:block;margin-bottom:8px}
.q{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:20px;margin-bottom:12px}
.q-text{font-size:16.5px;font-weight:500;margin:0 0 16px;max-width:60ch}
.opts{display:flex;gap:8px;flex-wrap:wrap}
.opt{font-family:var(--body);font-size:14px;font-weight:500;background:var(--card);border:1px solid var(--line);color:var(--ink-soft);padding:9px 18px;border-radius:var(--btn-r);cursor:pointer;text-align:left;transition:border-color .15s ease,background .15s ease,color .15s ease}
.opt:hover{border-color:var(--ink-soft)}
.opt[aria-pressed="true"][data-v="2"]{background:var(--accent-soft);border-color:var(--accent);color:var(--accent)}
.opt[aria-pressed="true"][data-v="1"]{background:var(--warn-soft);border-color:var(--warn);color:var(--warn)}
.opt[aria-pressed="true"][data-v="0"]{background:var(--alert-soft);border-color:var(--alert);color:var(--alert)}
.opt[aria-pressed="true"][data-v="na"]{background:var(--paper);border-color:var(--ink-soft);color:var(--ink-soft)}
.opt.na{font-style:italic}
.nav{display:flex;align-items:center;gap:14px;margin-top:26px;flex-wrap:wrap}
.nav .hint{font-size:14px;color:var(--ink-soft)}

.gain{display:block;font-size:13.5px;font-weight:600;color:var(--accent);margin:0 0 7px}
.gain::before{content:"→ ";}
.bron{display:block;font-size:12.5px;color:var(--ink-soft);opacity:.8;margin-top:6px}

.score-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:30px;display:grid;grid-template-columns:150px 1fr;gap:30px;align-items:center;margin-bottom:16px}
.ring{position:relative;width:150px;height:150px}
.ring svg{transform:rotate(-90deg)}
.ring .val{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;font-family:var(--display);font-size:38px;font-weight:400;letter-spacing:-.03em}
.ring .val small{font-family:var(--body);font-size:11px;font-weight:500;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-soft)}
.bars{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:26px 30px;margin-bottom:26px}
.bar-row{display:grid;grid-template-columns:1fr 90px;gap:14px;align-items:center;padding:11px 0;border-bottom:1px solid var(--line)}
.bar-row:last-child{border-bottom:0}
.bar-name{font-size:15px;font-weight:500}
.bar-name em{display:block;font-style:normal;font-size:13px;color:var(--ink-soft)}
.bar-track{height:6px;background:var(--line);border-radius:3px;overflow:hidden;margin-top:8px}
.bar-fill{height:100%;border-radius:3px;width:0;transition:width .9s cubic-bezier(.2,.7,.3,1)}
.bar-pct{font-size:13px;color:var(--ink-soft);text-align:right}
.action{background:var(--card);border:1px solid var(--line);border-left:3px solid var(--line);border-radius:10px;padding:16px 18px;margin-bottom:10px}
.action.prio{border-left-color:var(--alert)}
.action.deels{border-left-color:var(--warn)}
.action .tag{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-soft);display:block;margin-bottom:6px}
.action .what{font-weight:600;margin:0 0 4px;font-size:16px}
.action .tip{margin:0;font-size:15px;color:var(--ink-soft)}

.toggle{display:inline-flex;border:1px solid var(--line);border-radius:var(--btn-r);padding:4px;background:var(--card);margin:0 0 30px}
.toggle button{font-family:var(--body);font-size:14px;font-weight:600;border:0;background:none;color:var(--ink-soft);padding:8px 18px;border-radius:var(--btn-r);cursor:pointer}
.toggle button[aria-pressed="true"]{background:var(--ink);color:#fff}
.tipgroup{margin-bottom:34px}
.tipgroup .fase{font-family:var(--display);font-size:13px;font-weight:400;letter-spacing:.1em;color:var(--accent);display:block;margin-bottom:6px}
.tipgroup h2{margin-bottom:18px}
.tip-item{display:grid;grid-template-columns:44px 1fr;gap:14px;padding:18px 0;border-top:1px solid var(--line)}
.tip-item .n{font-family:var(--display);font-size:15px;font-weight:400;color:var(--ink-soft);padding-top:2px}
.tip-item h3{margin-bottom:4px}
.tip-item p{margin:0;font-size:15.5px;color:var(--ink-soft)}
.cta-band{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:28px 30px;margin:6px 0 26px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.cta-band div{max-width:44ch}
.cta-band strong{font-family:var(--display);font-size:22px;font-weight:400;letter-spacing:-.01em;display:block;margin-bottom:4px}
.cta-band p{margin:0;font-size:15px;color:var(--ink-soft)}

.capture{background:var(--ink);color:#fff;border-radius:var(--r);padding:32px;margin:30px 0 20px}
.capture h3{color:#fff;font-family:var(--display);font-size:24px;font-weight:400;margin-bottom:8px}
.capture p{color:rgba(255,255,255,.72);font-size:15px;max-width:48ch}
.capture form{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.capture input{flex:1;min-width:220px;font-family:var(--body);font-size:15px;padding:14px 18px;border-radius:var(--btn-r);border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.06);color:#fff}
.capture input::placeholder{color:rgba(255,255,255,.45)}
.capture .btn{background:#fff;color:var(--ink);border-color:#fff}
.capture .consent{font-size:13px;color:rgba(255,255,255,.55);margin:14px 0 0}
.done-msg{font-size:16px;color:#fff;margin-top:16px;font-weight:500}

.step{display:none}
.step.active{display:block;animation:in .35s ease both}
@keyframes in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
@media (max-width:620px){
  .score-card{grid-template-columns:1fr;justify-items:center;text-align:center}
  .bar-row{grid-template-columns:1fr}
  .bar-pct{text-align:left}
  .opts{flex-direction:column;align-items:stretch}
}
@media print{
  .btn,.capture,.progress,.progress-label,.nav,.toggle,.cta-band,.detour{display:none!important}
  body{background:#fff}
  .step{display:block!important}
}

/* Jos bar + footer */
.jw-bar{display:flex;align-items:center;justify-content:space-between;max-width:var(--maxw);margin:0 auto;padding:20px 20px 0}
.jw-bar img{display:block;height:37px;width:auto}
.jw-bar a{color:var(--ink-soft);font-size:14px;text-decoration:none}
.jw-bar a:hover{color:var(--ink)}
h1{line-height:1.12}
.jw-foot{max-width:var(--maxw);margin:0 auto;padding:0 20px 40px;font-size:13px;color:var(--ink-soft)}
.jw-foot a{color:inherit}
@media print{.jw-bar,.jw-foot{display:none}}
</style>
</head>
<body>
<header class="jw-bar">
  <a href="/gratis-aanbod/" aria-label="Jos: naar alle gratis tools"><img src="/content/themes/josworld/images/logo.png" alt="Jos" width="56" height="37"></a>
  <a href="/gratis-aanbod/">&larr; Alle gratis tools</a>
</header>
<div class="wrap">

  <!-- ============ INTRO ============ -->
  <section class="step active" id="intro">
    <p class="eyebrow">Gratis tool van JOS</p>
    <h1>Win tijd en trek gemotiveerde partners aan</h1>
    <p class="lead">Een faire aanbesteding bespaart je tijd en maakt je opdracht aantrekkelijk voor bureaus die er echt bij passen. Zij weten dan waarop ze moeten antwoorden, en jij vergelijkt makkelijker. Deze scan laat zien welke keuzes dat verschil maken.</p>

    <div class="stance">
      <p>Je wil je budget goed besteden en achteraf kunnen uitleggen waarom je koos wat je koos. Een heldere vraag en een haalbare procedure helpen bureaus om gericht te antwoorden. Zo zie jij sneller wie bij je opdracht past.</p>
      <p>Wij kennen aanbestedingen van de andere kant: wij dienen zelf offertes in. Die ervaring zetten we hier om in praktische keuzes voor jou. Per punt lees je hoe je het werkbaar houdt voor allebei, binnen de regels die voor jouw opdracht gelden.</p>
    </div>

    <ul class="spine">
      <li><span class="num">01</span><span class="lbl">Scope en voorbereiding<span class="desc">Wat koop je, met welk budget, en wie beslist?</span></span></li>
      <li><span class="num">02</span><span class="lbl">Markt en shortlist<span class="desc">Wie nodig je uit, en hoeveel is genoeg?</span></span></li>
      <li><span class="num">03</span><span class="lbl">Briefing en bestek<span class="desc">Krijgen bureaus wat ze nodig hebben om scherp te antwoorden?</span></span></li>
      <li><span class="num">04</span><span class="lbl">Selectie en vergoeding<span class="desc">Hoe beoordeel je, en wat vraag je gratis?</span></span></li>
      <li><span class="num">05</span><span class="lbl">Gunning, start en evaluatie<span class="desc">Hoe rond je af en hoe begin je samen?</span></span></li>
    </ul>

    <div class="start">
      <button class="btn" id="start-kern">Start de snelle scan</button>
      <span class="sub">12 vragen, ongeveer 3 minuten. Wil je alles bekijken? <button class="linkbtn" id="start-full">Doe de volledige scan</button></span>
    </div>

    <div class="detour">
      <div>
        <strong>Je volgende opdracht nog aan het voorbereiden?</strong>
        <p>Bekijk de tips en neem ze mee in je voorbereiding. Je ziet meteen wat elke keuze jou en de bureaus oplevert.</p>
      </div>
      <button class="linkbtn" id="go-tips">Bekijk de tips</button>
    </div>

    <p class="small" style="margin-top:22px">Je antwoorden blijven in je browser. Je kan de scan invullen zonder iets achter te laten. Je mailadres vragen we alleen als je de documenten wil ontvangen.</p>
  </section>

  <!-- ============ TIPS ============ -->
  <section class="step" id="tips">
    <p class="eyebrow">Tips voor je aanbesteding</p>
    <h1>Zo haal je meer uit je aanbesteding</h1>
    <p class="lead" style="margin-bottom:26px">Van voorbereiding tot samenwerking. Per punt lees je welke keuze je helpt om de juiste bureaus aan te trekken, voorstellen vlot te vergelijken en onnodig werk te vermijden.</p>
    <div class="toggle" role="group" aria-label="Aantal tips">
      <button id="t-kern" aria-pressed="true">12 kernpunten</button>
      <button id="t-all" aria-pressed="false">Alle 31 punten</button>
    </div>
    <div id="tips-body"></div>
    <div class="cta-band">
      <div>
        <strong>Een communicatieopdracht op komst?</strong>
        <p>Doe de snelle scan. In een minuut of drie zie je wat al goed zit en waar het nog beter kan.</p>
      </div>
      <button class="btn" id="tips-to-scan">Start de snelle scan</button>
    </div>
    <div class="capture">
      <h3>Ontvang de checklist fair aanbesteden</h3>
      <p>Alle tips in één pdf, met een voorbeeldbriefing erbij. Handig voor je volgende opdracht, of om door te sturen naar collega's.</p>
      <form id="lead-tips">
        <input type="email" required aria-label="E-mailadres" placeholder="jouw@organisatie.be">
        <button class="btn" type="submit">Stuur me de checklist</button>
      </form>
      <p class="consent">Eén mail met de checklist.</p>
    </div>
    <div class="nav"><button class="btn btn-ghost" id="tips-back">Terug naar het begin</button></div>
  </section>

  <!-- ============ SCAN ============ -->
  <section class="step" id="scan">
    <div class="progress" id="progress"></div>
    <div class="progress-label"><span id="phase-count"></span><span id="answered-count"></span></div>
    <div id="phase"></div>
    <div class="nav">
      <button class="btn btn-ghost" id="prev">Vorige fase</button>
      <button class="btn" id="next">Volgende fase</button>
      <span class="hint" id="hint"></span>
    </div>
  </section>

  <!-- ============ RESULTAAT ============ -->
  <section class="step" id="result">
    <p class="eyebrow">Jouw scorekaart</p>
    <h1 id="verdict"></h1>
    <p class="lead" id="verdict-text" style="margin-bottom:26px"></p>
    <div class="score-card">
      <div class="ring">
        <svg width="150" height="150" viewBox="0 0 150 150" aria-hidden="true">
          <circle cx="75" cy="75" r="66" fill="none" stroke="var(--line)" stroke-width="11"></circle>
          <circle id="ring-fill" cx="75" cy="75" r="66" fill="none" stroke="var(--accent)" stroke-width="11"
                  stroke-linecap="round" stroke-dasharray="414.7" stroke-dashoffset="414.7"
                  style="transition:stroke-dashoffset 1.1s cubic-bezier(.2,.7,.3,1)"></circle>
        </svg>
        <div class="val"><span id="pct">0%</span><small>faire aanpak</small></div>
      </div>
      <div>
        <h3 style="margin-bottom:8px">Wat zegt je score?</h3>
        <p class="small" id="score-note" style="margin:0"></p>
      </div>
    </div>
    <div class="bars" id="bars"></div>
    <div id="actions"></div>
    <div class="capture">
      <h3>Ontvang je scorekaart en de volledige checklist</h3>
      <p>Je scorekaart en je werkpunten als pdf, met alle tips en een voorbeeldbriefing erbij. Handig om samen met collega's te overlopen.</p>
      <form id="lead-result">
        <input type="email" required aria-label="E-mailadres" placeholder="jouw@organisatie.be">
        <button class="btn" type="submit">Stuur me de checklist</button>
      </form>
      <p class="consent">Eén mail met je scorekaart.</p>
    </div>
    <div class="nav">
      <button class="btn btn-ghost" id="print">Bewaar als pdf</button>
      <button class="btn btn-ghost" id="restart">Vul de scan opnieuw in</button>
    </div>
  </section>

</div>

<footer class="jw-foot">Een gratis tool van Jos &middot; <a href="/">jos.agency</a></footer>
<script>
/* Per vraag: t = titel, w = wat het oplevert, v = de vraag in de scan,
   o = antwoordopties met puntenwaarde, na = optie die uit de scoring valt,
   tip = uitleg, bron = verwijzing die enkel op de tipspagina verschijnt.
   Antwoorden worden bijgehouden op positie, niet op puntenwaarde, zodat
   twee opties dezelfde waarde mogen hebben (zie vraag 12 en 23). */
const STD = [["Ja",2],["Deels",1],["Nee",0]];
const OPEN_OPROEP = "Ik werk met een open oproep";

const FASES = [
{
  num:"01", titel:"Scope en voorbereiding",
  intro:"Het meeste tijdverlies ontstaat voor er ook maar één bureau gebeld is.",
  items:[
    {kern:1, t:"Bepaal welk soort partner je zoekt",
     w:"Offertes van bureaus die je vraag echt aankunnen",
     v:"Weet ik welk soort partner ik zoek: een strategisch bureau, een communicatiebureau, een reclamebureau, een freelancer, een DTP-afdeling of een drukkerij?",
     o:[["Ja, dat is duidelijk",2],["Grofweg",1],["Nog niet",0]],
     tip:"Een reclamebureau, een designstudio en een drukkerij lossen niet dezelfde vraag op. Kies eerst welk soort werk je nodig hebt, dan krijg je voorstellen die je naast elkaar kan leggen."},
    {kern:1, t:"Stem je budget intern af",
     w:"Een opdracht die past bij wat je kan uitgeven",
     v:"Ligt er een budget of een budgetvork vast, goedgekeurd door wie daarover gaat, voor ik bureaus aanspreek?",
     o:[["Ja, en het is goedgekeurd",2],["Er is een richtbedrag, nog geen akkoord",1],["Nog niet",0]],
     tip:"Spreek vooraf af hoeveel er is en wat er allemaal uit betaald moet worden. Met een goedgekeurd bedrag kan je de opdracht realistisch afbakenen."},
    {kern:1, t:"Kies een procedure die past",
     w:"Niet meer papierwerk dan nodig",
     v:"Weet ik welke procedure bij mijn opdracht kan, en waarom ik die kies?",
     o:[["Ja, dat is nagegaan",2],["Nog niet",0]],
     tip:"Blijft je opdracht onder 30.000 euro zonder btw, dan volstaat vaak een aanvaarde factuur. Vergelijk dan wel het aanbod van meerdere bureaus, hou die vergelijking bij en check ook de aankoopregels van je eigen organisatie.",
     bron:"Vlaanderen over opdrachten van beperkte waarde"},
    {t:"Spreek af wie beoordeelt en wie beslist",
     w:"Een beoordeling waarin iedereen zijn rol kent",
     v:"Weet ik vooraf wie de offertes leest, wie advies geeft en wie beslist?",
     o:[["Ja, de rollen liggen vast",2],["Dat groeit onderweg",0]],
     tip:"Betrek de mensen die straks adviseren of beslissen nu al. Spreek af wanneer je hen nodig hebt, dan raak je op tijd rond."},
    {t:"Plan ook je eigen beslismomenten",
     w:"Een planning die je ook echt haalt",
     v:"Heb ik tijd voorzien voor voorbereiding, beoordeling, interne goedkeuring en uitvoering, verlof inbegrepen?",
     o:[["Ja, voor elke stap",2],["Op enkele punten krap",1],["Nog niet",0]],
     tip:"Reken terug vanaf de dag dat het klaar moet zijn, en zet ook je eigen feedback en goedkeuring in de planning. Dan zie je op tijd waar het knelt."},
    {t:"Geef tijd voor een doordacht voorstel",
     w:"Voorstellen die voor jou gemaakt zijn, niet hergebruikt",
     v:"Hoeveel tijd krijgen bureaus tussen de volledige briefing en het indienen van hun offerte?",
     o:[["Vier weken of meer",2],["Twee tot vier weken",1],["Minder dan twee weken",0]],
     tip:"Voor een inhoudelijk voorstel is vier weken een goed vertrekpunt. Pas de termijn aan aan wat je vraagt en aan verlofperiodes, en check de minimumtermijnen van je procedure."}
  ]
},
{
  num:"02", titel:"Markt en shortlist",
  intro:"Meer kandidaten betekent niet meer keuze, wel meer leeswerk.",
  items:[
    {t:"Kijk rond voor je uitnodigt",
     w:"Een beter zicht op wie er allemaal is",
     v:"Heb ik vooraf uitgezocht welke bureaus ervaring hebben met het soort werk dat ik vraag?",
     o:[["Ja, gericht",2],["Vluchtig",1],["Nog niet",0]],
     tip:"Bekijk portfolio's, cases en de manier van werken van een paar bureaus. Scherp daarmee je eigen vraag aan, en let erop dat je niemand meer informatie geeft dan de rest."},
    {kern:1, t:"Nodig niet meer bureaus uit dan je kan beoordelen",
     w:"Een beoordeling die behapbaar blijft",
     v:"Hoeveel bureaus nodig je uit?",
     o:[["Drie of vier",2],["Vijf tot acht",1],["Meer dan acht",0],["Nog niet bepaald",0]],
     na:OPEN_OPROEP,
     tip:"Mag je zelf kiezen wie je uitnodigt, dan zijn drie tot vier bureaus een goed vertrekpunt, al leggen sommige procedures een minimum op. Bij een open oproep vraag je beter weinig uitwerking, zodat meedoen en beoordelen haalbaar blijft.",
     bron:"Wet overheidsopdrachten artikel 79"},
    {t:"Pols vooraf of ze tijd hebben",
     w:"Minder wachten op offertes die niet komen",
     v:"Check ik voor ik uitnodig of een bureau interesse en ruimte heeft?",
     o:[["Ja",2],["Nee",0]],
     na:OPEN_OPROEP,
     tip:"Eén telefoontje volstaat om te horen of ze interesse en tijd hebben. Geef iedereen dezelfde info en noteer kort wat je besproken hebt."},
    {t:"Kijk ook eens verder dan je vaste partners",
     w:"Meer keuze dan je gewoon bent",
     v:"Kijk ik bij een nieuwe opdracht ook verder dan de bureaus die ik al ken?",
     o:[["Ja, ik kijk rond",2],["Af en toe",1],["Nee, ik blijf bij dezelfde",0]],
     na:"Ik bepaal niet wie kan deelnemen",
     tip:"Een vaste partner kan perfect passen, maar kijk toch even wie er nog is. Noteer wie je bekeken hebt en waarom je keuze bij deze opdracht past."},
    {t:"Zeg erbij hoeveel bureaus je uitnodigt",
     w:"Bureaus die bewust ja zeggen",
     v:"Zeg ik erbij hoeveel bureaus ik uitnodig?",
     o:[["Ja",2],["Nee",0]],
     na:OPEN_OPROEP,
     tip:"Zet in je uitnodiging hoeveel bureaus je aanspreekt en wat je van hen verwacht. Zij schatten dan zelf in of het de moeite is, en jij weet sneller wie indient."}
  ]
},
{
  num:"03", titel:"Briefing en bestek",
  intro:"Hoe helderder je bestek, hoe beter de voorstellen die je terugkrijgt.",
  items:[
    {kern:1, t:"Maak duidelijk wat je wil bereiken",
     w:"Voorstellen die op jouw doel mikken",
     v:"Staat in mijn briefing wat ik wil bereiken en waarom, ook als ik al weet wat ik precies nodig heb?",
     o:[["Ja, en de aanpak ligt nog open",2],
        ["Ja, en ik vraag gericht om uitvoering",2],
        ["Maar half",1],
        ["Nog niet",0]],
     tip:"Schrijf op wat je wil veranderen en waarom. Laat ruimte voor advies waar dat kan, en zeg erbij welke keuzes al vastliggen."},
    {t:"Benoem voor wie je werkt en wanneer het geslaagd is",
     w:"Eén doel waarop je alle voorstellen kan afwegen",
     v:"Staan in mijn briefing de doelgroep, het doel en hoe we straks zien of het gelukt is?",
     o:[["Alle drie",2],["Eén of twee",1],["Geen van de drie",0]],
     tip:"Vul in je briefing de zin aan: dit project is geslaagd als. Hoe concreter, hoe makkelijker je er later de offertes en het werk zelf aan aftoetst."},
    {t:"Baken de opdracht af",
     w:"Een beter zicht op wat je voor je budget krijgt",
     v:"Staat in mijn briefing wat het bureau oplevert en wat er niet bij hoort?",
     o:[["Allebei",2],["Alleen wat ze opleveren",1],["Geen van beide",0]],
     tip:"Schrijf op wat er moet uitkomen en waar de opdracht stopt. Zeg bijvoorbeeld wie de teksten, vertalingen of beelden levert, dan rekenen alle bureaus hetzelfde mee."},
    {kern:1, t:"Zet je budget in de briefing",
     w:"Voorstellen die passen bij je budget",
     v:"Staat het budget of de budgetvork in mijn briefing?",
     o:[["Ja, meteen",2],["Pas als iemand het vraagt",1],["Nee",0]],
     tip:"Zet het bedrag erbij, en zeg welke kosten en btw er wel of niet in zitten. Dan stellen bureaus iets haalbaars voor en vergelijk jij wat ze voor hetzelfde geld doen."},
    {t:"Deel wat je al hebt",
     w:"Geen giswerk, en geen onderzoek dat je al betaald hebt",
     v:"Krijgen alle bureaus het materiaal dat er al is: huisstijl, onderzoek, cijfers, eerdere campagnes?",
     o:[["Ja, alles wat nuttig is",2],["Een deel",1],["Nee",0]],
     na:"Er is geen bestaand materiaal",
     tip:"Bundel wat ze nodig hebben om je vraag te snappen, en geef het aan iedereen tegelijk. Zeg er ook bij wat je zelf nog niet weet."},
    {t:"Geef ruimte voor vragen",
     w:"Vier offertes die over dezelfde opdracht gaan",
     v:"Is er een moment om de opdracht toe te lichten, en iemand bij wie ze terechtkunnen met vragen?",
     o:[["Allebei",2],["Eén van de twee",1],["Geen van beide",0]],
     tip:"Plan bij een grotere opdracht een infomoment kort na de briefing, en zeg tot wanneer ze vragen kunnen stellen. Stuur vraag en antwoord daarna naar iedereen door."},
    {t:"Vraag prijzen die je kan vergelijken",
     w:"Zien wat er achter elke prijs zit",
     v:"Vraag ik alle bureaus om hun prijs op dezelfde manier op te splitsen?",
     o:[["Ja, ik weet wat er in de prijs zit",2],["Deels",1],["Nee, elk bureau rekent anders",0]],
     tip:"Vraag dezelfde opsplitsing aan iedereen, bijvoorbeeld per fase, en laat ze erbij zetten waarvan ze uitgaan. Werkt een bureau met uren, vraag dan ook de uren en wie het werk doet: een uurtarief alleen zegt weinig."}
  ]
},
{
  num:"04", titel:"Selectie en vergoeding",
  intro:"Hier beslist je procedure of je straks drie sterke voorstellen leest of veertien halve.",
  items:[
    {kern:1, t:"Deel je criteria en hun gewicht",
     w:"Een keuze die je achteraf kan uitleggen",
     v:"Liggen mijn criteria en hun gewicht vast, en kent elk bureau ze voor het indient?",
     o:[["Ja, criteria én gewicht",2],["Alleen de criteria",1],["Geen van beide",0]],
     tip:"Kies criteria die echt bij de opdracht passen: de aanpak, het team dat eraan werkt, hoe duurzaam het voorstel is, de prijs. Zet erbij hoe zwaar elk criterium weegt en waarop je let.",
     bron:"Wet overheidsopdrachten artikelen 71 en 81"},
    {kern:1, t:"Geef kwaliteit genoeg gewicht",
     w:"Werk dat je doel haalt, niet alleen de goedkoopste rekening",
     v:"Hoe zwaar weegt kwaliteit tegenover prijs in mijn beoordeling?",
     o:[["Kwaliteit weegt zwaarder",2],["Even zwaar",1],["Prijs weegt zwaarder",0]],
     tip:"Bepaal eerst welke kwaliteit je nodig hebt om je doel te halen, en laat dat terugkomen in je criteria. Beoordeel daarna iedereen volgens de weging die je aankondigde."},
    {t:"Selecteer eerst, laat daarna uitwerken",
     w:"Drie uitgewerkte voorstellen lezen in plaats van veertien",
     v:"Laat ik alleen een voorselectie van bureaus echt uitwerken, als mijn procedure dat toelaat?",
     o:[["Ja, eerst selecteren, dan uitwerken",2],["Eén ronde, maar ik vraag weinig",1],["Iedereen werkt meteen alles uit",0]],
     na:"De procedure laat geen voorselectie toe",
     tip:"Kijk of een procedure met voorselectie bij je opdracht past, en beoordeel eerst ervaring en capaciteit. Pas daarna vraag je uitwerking, aan het aantal bureaus dat je procedure toelaat."},
    {t:"Vraag alleen op wat je nodig hebt om te kiezen",
     w:"Kortere dossiers, dus minder leeswerk",
     v:"Kan ik van elk document dat ik opvraag zeggen waarvoor ik het nodig heb?",
     o:[["Ja, van alles",2],["Van een deel",1],["Nee",0]],
     tip:"Loop je vraag na en schrap wat geen rol speelt bij de beoordeling of de uitvoering, op de verplichte documenten na. Vraag bijvoorbeeld geen mediaplan als een andere partner dat al doet."},
    {kern:1, t:"Kies je partner zonder onnodig voorwerk",
     w:"Dat de bureaus die je wil, ook meedoen",
     v:"Wat moeten bureaus maken voor ik kan kiezen?",
     o:[["Alleen referenties en hun manier van werken",2],
        ["Een afgebakende uitwerking, die ik vergoed",2],
        ["Een korte visie, onbetaald",1],
        ["Een volledig voorstel, onbetaald",0]],
     tip:"Kijk eerst of bestaand werk, het team en hun aanpak genoeg zeggen om te kiezen. Heb je toch nieuw werk nodig, houd het dan beperkt en betaal ervoor."},
    {kern:1, t:"Betaal voor werk dat je laat maken",
     w:"Bureaus die er hun beste mensen op zetten",
     v:"Krijgen de bureaus die de opdracht niet krijgen een vergoeding voor het werk dat ze maakten?",
     o:[["Ja, in verhouding tot wat ik vroeg",2],["Ja, maar het dekt maar een deel",1],["Nee",0]],
     na:"Ik vraag niemand nieuw werk te maken",
     tip:"Bepaal het bedrag op basis van wat je vraagt, en zeg vooraf wie het krijgt en wanneer je betaalt. Bij sommige procedures en bij gevraagde ontwerpen is zo'n vergoeding trouwens verplicht.",
     bron:"Wet overheidsopdrachten artikel 12/9"},
    {t:"Zet de beoordeling in de agenda",
     w:"Een beslissing op de dag die je beloofde",
     v:"Weet ik wie beoordeelt, en staat het in hun agenda?",
     o:[["Allebei geregeld",2],["Ik weet wie, maar nog geen datum",1],["Geen van beide",0]],
     tip:"Zet de beoordelingsmomenten in de agenda voor je de oproep verstuurt, met genoeg tijd per offerte. Spreek ook af hoe je de scores bespreekt en de keuze vastlegt."},
    {t:"Haal attesten zelf op",
     w:"Minder papieren opvragen en achternazitten",
     v:"Haal ik de attesten die ik zelf digitaal kan opvragen, bijvoorbeeld via Telemarc?",
     o:[["Ja, waar het kan",2],["Nee, ik vraag ze aan de bureaus",0]],
     na:"Wij hebben geen toegang tot zo'n databank",
     tip:"Haal via Telemarc of een andere databank op wat je zelf kan opvragen. Vraag aan de bureaus alleen de stukken die je daar niet vindt.",
     bron:"FOD Financiën over Telemarc"}
  ]
},
{
  num:"05", titel:"Gunning, start en evaluatie",
  intro:"Hoe je afsluit, bepaalt wie er volgende keer nog intekent.",
  items:[
    {t:"Laat iedereen op tijd iets weten",
     w:"Bureaus die volgende keer opnieuw meedoen",
     v:"Laat ik elk bureau op tijd weten wat de beslissing is, ook als het later wordt?",
     o:[["Ja, op tijd of met een seintje vooraf",2],["Iedereen hoort het, maar vaak te laat",1],["Niet iedereen hoort iets",0]],
     tip:"Laat het weten zodra je procedure dat toelaat, ook aan wie je niet koos. Loopt het uit, geef dan een seintje met een nieuwe datum."},
    {kern:1, t:"Leg uit waarom je koos wat je koos",
     w:"Betere voorstellen bij je volgende oproep",
     v:"Krijgen de bureaus die je niet koos uitleg, en mogen ze vragen stellen?",
     o:[["Uitleg én ruimte voor vragen",2],["Alleen een standaardmail",1],["Niets",0]],
     tip:"Hang je uitleg op aan de criteria die je vooraf deelde, en zeg wat sterk was en wat minder paste. Drie zinnen volstaan, en bureaus doen er hun voordeel mee."},
    {t:"Leg rechten en betaling vooraf vast",
     w:"Geen discussie als je het werk later hergebruikt",
     v:"Staan de afspraken over gebruiksrechten, bestanden en betaling in de opdracht?",
     o:[["Ja, allemaal",2],["Een deel",1],["Nee",0]],
     tip:"Zet erin welke rechten en bestanden je nodig hebt en wanneer je betaalt. Spreek ook af wat er met ideeën uit de offertes gebeurt: werk van een bureau dat je niet koos, gebruik je alleen met hun akkoord."},
    {kern:1, t:"Begin met een gezamenlijke start",
     w:"Minder correctierondes onderweg",
     v:"Plan ik bij de start een overleg over de briefing, de planning en de samenwerking?",
     o:[["Ja",2],["Nee",0]],
     tip:"Overloop bij de start de verwachtingen, de contactpersonen en de feedbackmomenten. Scherp de briefing samen aan binnen de gemaakte afspraken, dan kan iedereen vertrekken."},
    {t:"Leer van elke samenwerking",
     w:"Een volgende opdracht die vlotter loopt",
     v:"Plan ik na afloop een gesprek over het resultaat en de samenwerking?",
     o:[["Ja, na elke opdracht",2],["Alleen als er problemen waren",1],["Nee",0]],
     tip:"Overloop wat goed liep, waar tijd verloren ging en welke afspraken je meeneemt. Bij een langere samenwerking plan je ook tussentijdse momenten, dan kan je meteen bijsturen."}
  ]

}
];

const $ = s => document.querySelector(s);
const steps = {intro:$("#intro"), tips:$("#tips"), scan:$("#scan"), result:$("#result")};
const ALL = FASES.reduce((s,f)=>s+f.items.length,0);

let mode = "full";
let answers = {};   // "fi-i" -> index van de gekozen optie, of "na"
let cur = 0;

const optsOf = it => it.o || STD;
const valueOf = (it, a) => a==="na" || a===undefined ? null : optsOf(it)[a][1];
const itemsOf = fi => FASES[fi].items.map((it,i)=>({it,i})).filter(o => mode==="full" || o.it.kern);
const totalItems = () => FASES.reduce((s,_,fi)=>s+itemsOf(fi).length,0);

function show(name){
  Object.values(steps).forEach(s=>s.classList.remove("active"));
  steps[name].classList.add("active");
  window.scrollTo({top:0,behavior:"smooth"});
}

/* ---------- tipsspoor ---------- */
let tipsAll = false;
function renderTips(){
  $("#t-kern").setAttribute("aria-pressed", String(!tipsAll));
  $("#t-all").setAttribute("aria-pressed", String(tipsAll));
  $("#t-all").textContent = "Alle " + ALL + " punten";
  let n = 0;
  $("#tips-body").innerHTML = FASES.map(f=>{
    const items = f.items.map(it=>{ n++; return {it,n}; }).filter(o=>tipsAll || o.it.kern);
    if(!items.length) return "";
    return `<div class="tipgroup">
      <span class="fase">FASE ${f.num}</span>
      <h2>${f.titel}</h2>
      ${items.map(({it,n})=>`
        <div class="tip-item">
          <div class="n">${String(n).padStart(2,"0")}</div>
          <div>
            <h3>${it.t}</h3>
            <span class="gain">Dit levert het je op: ${it.w}</span>
            <p>${it.tip}</p>
            ${it.bron ? `<span class="bron">Bron: ${it.bron}</span>` : ""}
          </div>
        </div>`).join("")}
    </div>`;
  }).join("");
}
$("#t-kern").addEventListener("click", ()=>{ tipsAll=false; renderTips(); });
$("#t-all").addEventListener("click", ()=>{ tipsAll=true; renderTips(); });
$("#go-tips").addEventListener("click", ()=>{ renderTips(); show("tips"); });
$("#tips-back").addEventListener("click", ()=>show("intro"));
$("#tips-to-scan").addEventListener("click", ()=>begin("kern"));

/* ---------- scan ---------- */
function renderProgress(){
  $("#progress").innerHTML = FASES.map((_,i)=>`<span class="${i<cur?'done':i===cur?'now':''}"></span>`).join("");
  $("#phase-count").textContent = `Fase ${cur+1} van ${FASES.length}`;
  $("#answered-count").textContent = `${Object.keys(answers).length} van ${totalItems()} vragen beantwoord`;
}

function renderPhase(){
  const f = FASES[cur];
  $("#phase").innerHTML = `
    <div class="phase-head">
      <span class="num">FASE ${f.num}</span>
      <h2>${f.titel}</h2>
      <p class="small" style="margin-top:8px">${f.intro}</p>
    </div>
    ${itemsOf(cur).map(({it,i})=>{
      const key = `${cur}-${i}`;
      const a = answers[key];
      const opts = optsOf(it).map(([label,val],idx)=>
        `<button class="opt" type="button" data-i="${idx}" data-v="${val}" aria-pressed="${a===idx?'true':'false'}">${label}</button>`).join("");
      const na = it.na
        ? `<button class="opt na" type="button" data-i="na" data-v="na" aria-pressed="${a==="na"?'true':'false'}">${it.na}</button>` : "";
      return `<div class="q" data-key="${key}">
        <p class="q-text">${it.v}</p>
        <div class="opts">${opts}${na}</div>
      </div>`;
    }).join("")}`;
  $("#prev").style.visibility = cur===0 ? "hidden" : "visible";
  $("#next").textContent = cur===FASES.length-1 ? "Toon mijn scorekaart" : "Volgende fase";
  updateNav();
  renderProgress();
}

const phaseComplete = () => itemsOf(cur).every(({i})=>answers[`${cur}-${i}`]!==undefined);
function updateNav(){
  const ok = phaseComplete();
  $("#next").disabled = !ok;
  $("#hint").textContent = ok ? "" : "Beantwoord eerst alle vragen van deze fase.";
}

$("#phase").addEventListener("click", e=>{
  const btn = e.target.closest(".opt");
  if(!btn) return;
  const q = btn.closest(".q");
  answers[q.dataset.key] = btn.dataset.i==="na" ? "na" : Number(btn.dataset.i);
  q.querySelectorAll(".opt").forEach(b=>b.setAttribute("aria-pressed", b===btn?"true":"false"));
  updateNav();
  renderProgress();
});

function begin(m){ mode=m; answers={}; cur=0; show("scan"); renderPhase(); }
$("#start-kern").addEventListener("click", ()=>begin("kern"));
$("#start-full").addEventListener("click", ()=>begin("full"));
$("#prev").addEventListener("click", ()=>{ if(cur>0){cur--; renderPhase();} });
$("#next").addEventListener("click", ()=>{
  if(cur < FASES.length-1){ cur++; renderPhase(); }
  else { renderResult(); show("result"); }
});
$("#restart").addEventListener("click", ()=>{ answers={}; cur=0; show("intro"); });
$("#print").addEventListener("click", ()=>window.print());

/* ---------- resultaat ---------- */
const VERDICTS = [
  {min:85, t:"Een sterke en faire basis", n:"Op de punten die je invulde zit je aanpak goed, voor jou en voor de bureaus. Loop de laatste puntjes hieronder na en hou deze werkwijze bij voor je volgende opdracht.", c:"var(--accent)"},
  {min:65, t:"Goed op weg", n:"De basis staat. Met de punten hieronder maak je je vraag duidelijker en verloopt het indienen en beoordelen vlotter. Begin bij wat voor jouw opdracht het meeste uitmaakt.", c:"var(--accent)"},
  {min:40, t:"Je aanpak kan gerichter", n:"Een paar keuzes maken het meedoen of het beoordelen lastiger dan nodig. Begin bij de fase met de laagste score en gebruik de tips om je aanpak beter op je opdracht af te stemmen.", c:"var(--warn)"},
  {min:0,  t:"Hier gaat tijd verloren", n:"Op meerdere punten ligt je aanpak nog niet vast, en dat kost je straks tijd. Begin bij je doel, je budget en je briefing. Daarop bouw je de rest van de procedure.", c:"var(--alert)"}
];
const barColor = p => p>=75 ? "var(--accent)" : p>=45 ? "var(--warn)" : "var(--alert)";

function renderResult(){
  let got=0, max=0;
  const perPhase = FASES.map((f,fi)=>{
    let g=0, m=0;
    itemsOf(fi).forEach(({it,i})=>{
      const v = valueOf(it, answers[`${fi}-${i}`]);
      if(v===null) return;              // n.v.t. valt uit teller en noemer
      g += v; m += 2;
    });
    got+=g; max+=m;
    return {titel:f.titel, num:f.num, intro:f.intro, pct: m ? Math.round(g/m*100) : null};
  });

  const pct = max ? Math.round(got/max*100) : 0;
  const verdict = VERDICTS.find(v=>pct>=v.min);

  $("#verdict").textContent = verdict.t;
  $("#verdict-text").textContent = `Je scoort ${pct} procent op ${max/2} punten van een faire aanbesteding.`;
  $("#score-note").textContent = verdict.n;
  $("#pct").textContent = pct + "%";

  const ring = $("#ring-fill");
  ring.setAttribute("stroke", verdict.c);
  ring.style.strokeDashoffset = 414.7;
  setTimeout(()=>{ ring.style.strokeDashoffset = 414.7*(1-pct/100); }, 120);

  $("#bars").innerHTML = `<h3 style="margin-bottom:10px">Jouw score per fase</h3>` + perPhase.map(p=>`
    <div class="bar-row">
      <div>
        <div class="bar-name">${p.num} ${p.titel}<em>${p.intro}</em></div>
        ${p.pct===null ? "" : `<div class="bar-track"><div class="bar-fill" data-p="${p.pct}" style="background:${barColor(p.pct)}"></div></div>`}
      </div>
      <div class="bar-pct">${p.pct===null ? "n.v.t." : p.pct+"%"}</div>
    </div>`).join("");
  setTimeout(()=>document.querySelectorAll(".bar-fill").forEach(b=>b.style.width=b.dataset.p+"%"), 200);

  const gaps = [];
  FASES.forEach((f,fi)=>itemsOf(fi).forEach(({it,i})=>{
    const v = valueOf(it, answers[`${fi}-${i}`]);
    if(v===0 || v===1) gaps.push({fase:`Fase ${f.num} ${f.titel}`, prio:v===0, what:it.t, gain:it.w, tip:it.tip});
  }));
  gaps.sort((a,b)=>(b.prio?1:0)-(a.prio?1:0));

  $("#actions").innerHTML = gaps.length===0
    ? `<h3>Geen werkpuntjes</h3><p class="small">Op de punten die je invulde staat je aanpak sterk. Deel ze met je collega's, dan heeft iedereen dezelfde basis voor een volgende opdracht.</p>`
    : `<h3>Hier kun je je aanpak versterken</h3><p class="small" style="margin-bottom:16px">${gaps.filter(g=>g.prio).length} punten om eerst aan te pakken en ${gaps.filter(g=>!g.prio).length} punten om verder te verbeteren.</p>`
      + gaps.map(g=>`
        <div class="action ${g.prio?'prio':'deels'}">
          <span class="tag">${g.fase} · ${g.prio?'Eerst aanpakken':'Verder verbeteren'}</span>
          <p class="what">${g.what}</p>
          <span class="gain">Dit levert het je op: ${g.gain}</span>
          <p class="tip">${g.tip}</p>
        </div>`).join("");
}

/* ---------- lead capture ----------
   Koppel hier het Mailchimp- of CRM-endpoint aan. Stuur bij het
   resultaatformulier ook score, modus en werkpunten mee als merge fields. */
["#lead-tips","#lead-result"].forEach(sel=>{
  const form = $(sel);
  form.addEventListener("submit", e=>{
    e.preventDefault();
    const email = form.querySelector("input").value.trim();
    if(!email) return;
    form.style.display = "none";
    const msg = document.createElement("p");
    msg.className = "done-msg";
    msg.textContent = `Verstuurd naar ${email}. Zie je de mail niet? Kijk dan ook even in je spamfolder.`;
    form.parentNode.insertBefore(msg, form.nextSibling);
  });
});
</script>
</body>
</html>
