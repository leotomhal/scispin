/* assets/chrome.js – fügt auf jeder Seite die geteilte Kopfleiste und den Footer
   ein, damit check/ und spin/ als EIN Produkt wirken. Jede Seite setzt vorher
   window.SCISPIN = { root: '<pfad-zur-wurzel>', active: '<seiten-key>' }.
   root ist der relative Pfad zur Projektwurzel ('' auf der Startseite,
   '../' in den Unterordnern). active ∈ home|check|spin|brief|archiv|methoden. */
(function () {
  var cfg = window.SCISPIN || {};
  var root = cfg.root || '';
  var active = cfg.active || '';
  function href(p) { return root + p; }

  var links = [
    ['check',    'Prüfen',          'check/'],
    ['spin',     'Vorführen',       'spin/'],
    ['brief',    'Kurzmeldung',     'brief/'],
    ['hilfe',    'So funktioniert\'s', 'so-funktionierts.php'],
    ['archiv',   'Archiv',          'check/archive.php'],
    ['methoden', 'Methoden',        'methoden.php'],
  ];

  var nav = document.createElement('header');
  nav.className = 'sci-nav';
  var linksHtml = links.map(function (l) {
    var on = active === l[0] ? ' class="on"' : '';
    return '<a' + on + ' href="' + href(l[2]) + '">' + l[1] + '</a>';
  }).join('');
  nav.innerHTML =
    '<div class="in">' +
      '<a class="sci-brand" href="' + (href('') || './') + '">SciSpin</a>' +
      '<nav class="sci-links">' + linksHtml + '</nav>' +
    '</div>';
  document.body.insertBefore(nav, document.body.firstChild);

  var foot = document.createElement('footer');
  foot.className = 'sci-foot';
  foot.innerHTML =
    '<div class="in">' +
      '<span>Automatisierte Werkzeuge, kein Ersatz für fachliche Prüfung.</span>' +
      '<span><a href="' + href('impressum.php') + '">Impressum</a> · ' +
      '<a href="' + href('datenschutz.php') + '">Datenschutz</a></span>' +
    '</div>';
  document.body.appendChild(foot);
})();
