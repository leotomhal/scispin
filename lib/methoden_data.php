<?php
/**
 * Liest content/methoden.md: ein Einleitungstext (vor dem ersten "### key")
 * und darunter ein Studientyp pro "### key"-Block mit festen Feldern
 * (Name/Rang/Gruppe/Kurz/Zeigt/Grenzen). Bewusst kein generisches Markdown,
 * weil methoden.php daraus Pyramide + Karten baut statt reinen Fließtext.
 */

/** @return array{0:string,1:array<string,array<string,string>>} [$intro, $entries] */
function sci_methoden_parse(string $raw): array {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
    $n = count($lines);

    $intro = [];
    $i = 0;
    while ($i < $n && !preg_match('/^###\s+/', trim($lines[$i]))) {
        $t = trim($lines[$i]);
        if ($t !== '' && strncmp($t, '<!--', 4) !== 0) $intro[] = $t;
        $i++;
    }

    $entries = [];
    $key = null;
    $fields = [];
    for (; $i < $n; $i++) {
        $t = trim($lines[$i]);
        if (preg_match('/^###\s+(.+)$/', $t, $m)) {
            if ($key !== null) $entries[$key] = $fields;
            $key = trim($m[1]);
            $fields = [];
            continue;
        }
        if ($key === null || $t === '') continue;
        if (preg_match('/^(Name|Rang|Gruppe|Kurz|Zeigt|Grenzen):\s*(.*)$/u', $t, $m)) {
            $fields[$m[1]] = trim($m[2]);
        }
    }
    if ($key !== null) $entries[$key] = $fields;

    return [implode(' ', $intro), $entries];
}

/**
 * Baut die $M-Struktur, die methoden.php fürs Rendern erwartet:
 * key => [Name, Rang, Gruppe('pyr'|'sonder'), Kurz, Zeigt, Grenzen].
 * Unbekannte/fehlende Gruppe fällt sicher auf 'sonder' (taucht dann unten
 * auf, statt die Pyramide mit falscher Breite zu verzerren).
 */
function sci_methoden_build(array $entries): array {
    $M = [];
    foreach ($entries as $key => $f) {
        $gruppe = (trim($f['Gruppe'] ?? '') === 'pyramide') ? 'pyr' : 'sonder';
        $M[$key] = [
            $f['Name'] ?? $key,
            $f['Rang'] ?? '–',
            $gruppe,
            $f['Kurz'] ?? '',
            $f['Zeigt'] ?? '',
            $f['Grenzen'] ?? '',
        ];
    }
    return $M;
}
