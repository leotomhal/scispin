<?php
// demo_data.php – statische 2-Phasen-Beispielausgabe (Willkommensklassen, MLU 093/2025).
// Format identisch zur Echtbetrieb-Ausgabe: je ein Objekt pro Phase, inklusive
// Evidenz-Etikett und markierbaren Regel-Hinweisen.

return [
  'phase1' => [
    'ist_studie'     => true,
    'frage'          => 'Lernen jugendliche Geflüchtete schneller Deutsch, wenn sie direkt in reguläre Schulklassen kommen – oder helfen ihnen separate Willkommensklassen mehr?',
    'methoden'       => [
      'Auswertung von Längsschnittdaten zu mehr als 1.000 jugendlichen Geflüchteten',
      'Vergleich der Sprachentwicklung nach Beschulungsform (Regelklasse vs. Willkommensklasse)',
    ],
    'methoden_recap' => 'Das Team verglich, wie sich die Deutschkenntnisse von über 1.000 Jugendlichen entwickelten – je nachdem, ob sie früh in normale Klassen kamen oder zunächst separate Willkommensklassen besuchten.',
    'engpass'        => 'Willkommensklassen gelten als Standardweg, um Deutschkenntnisse aufzuholen. Ob sie das tatsächlich leisten, war mangels vergleichender Daten über größere Gruppen bislang offen.',
    'fortschritt'    => 'Erstmals zeigt eine große Datenauswertung, dass die frühe Integration in den Regelunterricht mit besserem Spracherwerb einhergeht, während Willkommensklassen anfängliche Defizite nicht wie erhofft ausgleichen.',
    'evidenz'        => [
      'studientyp'         => 'Beobachtungsstudie (Kohorte)',
      'stichprobengroesse' => 'n > 1.000',
      'population'         => 'Mensch',
      'preprint'           => 'nein',
    ],
    'abstract_hype_warnung' => null,
  ],
  'phase2' => [
    'lede'        => 'Wer als geflüchtetes Kind schnell in eine normale Schulklasse kommt, lernt besser Deutsch – separate Willkommensklassen helfen dabei offenbar weniger als gedacht.',
    'headline'    => 'Früh in die Regelklasse: der bessere Weg zum Deutschlernen',
    'kurzmeldung' => "Wer als geflüchtetes Kind schnell in eine normale Schulklasse kommt, lernt besser Deutsch – separate Willkommensklassen helfen dabei offenbar weniger als gedacht.\n\nFür die Studie werteten Forschende der Martin-Luther-Universität Halle-Wittenberg Daten von mehr als 1.000 Jugendlichen aus und verglichen ihre Sprachentwicklung nach Beschulungsform. Willkommensklassen gelten als Standardweg, um Deutschkenntnisse aufzuholen; ob sie das leisten, war über größere Gruppen bislang kaum belegt.\n\nDie Auswertung legt nahe, dass die frühe Integration in den Regelunterricht mit einem günstigeren Spracherwerb einhergeht. Ein kausaler Beweis ist das nicht – wohl aber ein deutlicher Hinweis für die Debatte um die Beschulung Geflüchteter.",
    'regel_hinweise' => [
      [
        'text'    => 'Die Auswertung legt nahe, dass die frühe Integration in den Regelunterricht mit einem günstigeren Spracherwerb einhergeht.',
        'regel'   => 'Korrelation statt Kausalität',
        'hinweis' => "Beobachtungsstudie ohne zufällige Zuteilung zu den Beschulungsformen – daher 'legt nahe' und 'einhergeht' statt 'beweist' oder 'verursacht'.",
      ],
      [
        'text'    => 'helfen dabei offenbar weniger als gedacht',
        'regel'   => 'Behauptung nicht verschärft',
        'hinweis' => "Bewusst zurückhaltend formuliert, nicht als 'nutzlos' oder 'wirkungslos' – das würde über den Befund hinausgehen.",
      ],
    ],
  ],
];
