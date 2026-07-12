<?php
// demo_data.php – statische 7-Stufen-Beispielausgabe (Willkommensklassen, MLU 093/2025).
// Format identisch zur Echtbetrieb-Ausgabe: stufen-Objekt, indiziert "-3".."3".

$ORIG = "Junge Geflüchtete verbessern ihre Sprachkenntnisse in Deutschland am ehesten, wenn sie möglichst schnell in reguläre Schulklassen kommen. Das zeigt eine neue Studie von Forschenden der Martin-Luther-Universität Halle-Wittenberg, für die sie Daten von mehr als 1.000 Jugendlichen auswerteten. Die Analyse zeigt auch: Willkommensklassen scheinen unzureichende Deutschkenntnisse nicht wie erhofft ausgleichen zu können. Die Arbeit wurde im Fachmagazin Acta Sociologica veröffentlicht.";

return [
  'stufen' => [
    '-3' => [
      'stufe' => -3,
      'text' => "Eine datengestützte Analyse zur Zweitspracherwerbsprogression minderjähriger Geflüchteter (n > 1.000) unter Berücksichtigung der Beschulungsmodalität indiziert eine signifikante Assoziation zwischen frühzeitiger Regelklassenintegration und akzelerierter L2-Kompetenzentwicklung, wohingegen segregative Vorbereitungsbeschulungsformate hinsichtlich der Kompensation initialer sprachlicher Kompetenzdefizite keine hinreichende Wirksamkeit aufweisen.",
      'kommentar' => "Fachlich korrekt, aber durch maximale Nominalisierung, Fachterminologie und Passivkonstruktion in einem einzigen verdichteten Satz praktisch unlesbar. Hier geht nicht die Wahrheit verloren, sondern die Verständlichkeit.",
      'aenderungen' => [
        ['typ'=>'veraendert','original'=>'verbessern ihre Sprachkenntnisse','neu'=>'L2-Kompetenzentwicklung','fehlertyp'=>'Fachterminologie','erklaerung'=>"Fachkürzel ('L2' für Zweitsprache) ersetzt eine allgemeinverständliche Formulierung."],
        ['typ'=>'veraendert','original'=>'schnell in reguläre Schulklassen kommen','neu'=>'frühzeitiger Regelklassenintegration','fehlertyp'=>'Nominalstil','erklaerung'=>"Verbalhandlung wird zur abstrakten Substantivkomposition verdichtet."],
        ['typ'=>'veraendert','original'=>'Willkommensklassen','neu'=>'segregative Vorbereitungsbeschulungsformate','fehlertyp'=>'Bürokratische Komposita','erklaerung'=>"Ein klarer Begriff wird durch eine mehrgliedrige Fachwortbildung ersetzt."],
        ['typ'=>'eingefuegt','original'=>'','neu'=>'signifikante Assoziation','fehlertyp'=>'Statistikjargon','erklaerung'=>"Methodensprache an prominenter Stelle, die Laien nicht entschlüsseln."],
      ],
    ],
    '-2' => [
      'stufe' => -2,
      'text' => "Eine Untersuchung der Martin-Luther-Universität Halle-Wittenberg analysiert den Einfluss institutioneller Beschulungsformen auf den Zweitspracherwerb junger Geflüchteter. Auf Basis von Daten zu mehr als 1.000 Jugendlichen zeigt sich, dass eine frühe Integration in den Regelunterricht mit einer günstigeren Sprachentwicklung assoziiert ist, während Willkommensklassen anfängliche Kompetenzdefizite nicht hinreichend kompensieren.",
      'kommentar' => "Deutlich akademisch und distanziert, mit Fachbegriffen wie 'Zweitspracherwerb' und 'assoziiert' – aber im Gegensatz zu −3 noch durchdringbar.",
      'aenderungen' => [
        ['typ'=>'veraendert','original'=>'verbessern ihre Sprachkenntnisse in Deutschland am ehesten','neu'=>'günstigeren Sprachentwicklung assoziiert','fehlertyp'=>'Akademischer Ton','erklaerung'=>"Vorsichtige Fachsprache, korrekt, aber wenig zugänglich."],
        ['typ'=>'eingefuegt','original'=>'','neu'=>'institutioneller Beschulungsformen','fehlertyp'=>'Fachbegriff','erklaerung'=>"Abstrakte Kategorie statt konkreter Benennung."],
      ],
    ],
    '-1' => [
      'stufe' => -1,
      'text' => "Eine Studie der Universität Halle hat untersucht, wie sich die Schulform auf den Deutscherwerb junger Geflüchteter auswirkt. Ausgewertet wurden Daten von mehr als 1.000 Jugendlichen. Ergebnis: Wer früh in eine reguläre Klasse kommt, lernt besser Deutsch, während Willkommensklassen anfängliche Defizite nicht wie erhofft ausgleichen.",
      'kommentar' => "Fachlich sauber und vollständig, aber trocken und ohne Aufhänger. Korrekt, doch wenig einladend.",
      'aenderungen' => [
        ['typ'=>'veraendert','original'=>'verbessern ihre Sprachkenntnisse in Deutschland am ehesten, wenn sie möglichst schnell','neu'=>'Wer früh in eine reguläre Klasse kommt, lernt besser Deutsch','fehlertyp'=>'Entschärfung','erklaerung'=>"Etwas direkter, aber noch nüchtern. Sachlich korrekt."],
      ],
    ],
    '0' => [
      'stufe' => 0,
      'text' => $ORIG,
      'kommentar' => "Die unveränderte Ausgangsmeldung – der Einstiegsabsatz der Pressemitteilung. Vergleichspunkt für alle anderen Stufen.",
      'aenderungen' => [],
    ],
    '1' => [
      'stufe' => 1,
      'text' => "Wer als geflüchtetes Kind schnell in eine normale Schulklasse kommt, lernt besser Deutsch. Das zeigt eine Studie der Universität Halle, die Daten von mehr als 1.000 Jugendlichen ausgewertet hat. Überraschend: Spezielle Willkommensklassen helfen dabei offenbar weniger als erhofft. Erschienen ist die Arbeit im Fachmagazin Acta Sociologica.",
      'kommentar' => "Das kommunikative Ziel: verständlich, konkret, mit klarem Aufhänger – und trotzdem korrekt. Die Einschränkung ('offenbar', 'weniger als erhofft') bleibt erhalten, der Zusammenhang wird nicht zur Kausalbehauptung überdreht.",
      'aenderungen' => [
        ['typ'=>'veraendert','original'=>'Junge Geflüchtete verbessern ihre Sprachkenntnisse in Deutschland am ehesten, wenn sie möglichst schnell in reguläre Schulklassen kommen.','neu'=>'Wer als geflüchtetes Kind schnell in eine normale Schulklasse kommt, lernt besser Deutsch.','fehlertyp'=>'Anschaulichkeit','erklaerung'=>"Kernaussage nach vorn, konkrete Alltagssprache. Inhaltlich vollständig gedeckt."],
        ['typ'=>'eingefuegt','original'=>'','neu'=>'Überraschend:','fehlertyp'=>'Aufhänger','erklaerung'=>"Lenkt den Blick auf den eigentlichen Befund, ohne ihn zu übertreiben."],
      ],
    ],
    '2' => [
      'stufe' => 2,
      'text' => "Schnell rein in den normalen Unterricht – das ist der Schlüssel, damit geflüchtete Kinder gut Deutsch lernen. Eine große Studie mit über 1.000 Jugendlichen zeigt: Separate Willkommensklassen bringen dabei kaum etwas.",
      'kommentar' => "Eingängig und werblich, erste Vorsicht fällt weg ('Schlüssel', 'kaum etwas' statt 'nicht wie erhofft'). Die Aussage ist zugespitzt, hält aber gerade noch, was die Daten hergeben.",
      'aenderungen' => [
        ['typ'=>'veraendert','original'=>'scheinen unzureichende Deutschkenntnisse nicht wie erhofft ausgleichen zu können','neu'=>'bringen dabei kaum etwas','fehlertyp'=>'Zuspitzung','erklaerung'=>"Verschärft den vorsichtigen Befund, ohne ihn ins Falsche zu kippen – noch vertretbar."],
        ['typ'=>'eingefuegt','original'=>'','neu'=>'der Schlüssel','fehlertyp'=>'Werbliche Überhöhung','erklaerung'=>"Suggeriert Monokausalität; grenzwertig, weil die Studie nur einen starken Zusammenhang zeigt."],
        ['typ'=>'gestrichen','original'=>'Die Arbeit wurde im Fachmagazin Acta Sociologica veröffentlicht.','neu'=>'','fehlertyp'=>'Gestrichener Beleg','erklaerung'=>"Der Quellennachweis fällt weg – die Aussage wirkt dadurch freier, als sie ist."],
      ],
    ],
    '3' => [
      'stufe' => 3,
      'text' => "Bildungs-Sensation: Willkommensklassen sind gescheitert! Studie beweist, dass sie geflüchteten Kindern beim Deutschlernen schaden – Experten fordern die sofortige Abschaffung.",
      'kommentar' => "Hier verschwindet die Korrektheit: aus 'zeigt' wird 'beweist', aus 'gleicht nicht aus' wird 'schadet', und eine politische Forderung wird der Studie untergeschoben.",
      'aenderungen' => [
        ['typ'=>'eingefuegt','original'=>'','neu'=>'Bildungs-Sensation','fehlertyp'=>'Sensationsframing','erklaerung'=>"Dramatisierung ohne Grundlage in den Daten."],
        ['typ'=>'veraendert','original'=>'Das zeigt eine neue Studie','neu'=>'Studie beweist','fehlertyp'=>'Überzogene Kausalität','erklaerung'=>"'Beweist' überschreitet, was eine Beobachtungsstudie leisten kann."],
        ['typ'=>'veraendert','original'=>'nicht wie erhofft ausgleichen zu können','neu'=>'schaden','fehlertyp'=>'Claim über die Forschung hinaus','erklaerung'=>"'Schaden' behauptet einen negativen Effekt, den die Studie nicht zeigt."],
        ['typ'=>'eingefuegt','original'=>'','neu'=>'Experten fordern die sofortige Abschaffung','fehlertyp'=>'Untergeschobene Forderung','erklaerung'=>"Politische Forderung, die nicht Teil der Meldung ist."],
        ['typ'=>'gestrichen','original'=>'für die sie Daten von mehr als 1.000 Jugendlichen auswerteten','neu'=>'','fehlertyp'=>'Gestrichene Methode','erklaerung'=>"Der Hinweis auf die Datengrundlage fällt weg – gerade er macht die Aussage einordbar."],
      ],
    ],
  ],
];
