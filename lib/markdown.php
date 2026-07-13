<?php
/**
 * Winziger Markdown-Renderer für die Inhaltsseiten (content/*.md).
 * Bewusst klein und ohne Abhängigkeiten – deckt genau das ab, was die
 * Info-Seiten brauchen: Überschriften, Absätze, fett/kursiv, Code, Links,
 * Listen, Zitate, Trennlinien. Rohe HTML-Blöcke (Zeilen, die mit < beginnen)
 * werden unverändert durchgereicht – so bleiben Spezial-Elemente möglich.
 *
 * Inhalte stammen aus dem Repo (vertrauenswürdig), daher keine HTML-Filterung.
 */

/** Inline-Formatierung innerhalb einer Textzeile. */
function sci_md_inline(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    // Inline-Code: `code`
    $s = preg_replace_callback('/`([^`]+)`/', fn($m) => '<code>' . $m[1] . '</code>', $s);
    // Links: [Text](url)
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', fn($m) => '<a href="' . $m[2] . '">' . $m[1] . '</a>', $s);
    // Fett: **Text**
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
    // Kursiv: *Text*
    $s = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $s);
    return $s;
}

/** Markdown-Block -> HTML. */
function sci_markdown(string $md): string {
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $lines = explode("\n", $md);
    $n = count($lines);
    $out = [];
    $para = [];
    $listType = null; // 'ul' | 'ol' | null

    $flushPara = function () use (&$para, &$out) {
        if ($para) { $out[] = '<p>' . sci_md_inline(implode(' ', $para)) . '</p>'; $para = []; }
    };
    $closeList = function () use (&$listType, &$out) {
        if ($listType) { $out[] = '</' . $listType . '>'; $listType = null; }
    };

    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        $t = trim($line);

        if ($t === '') { $flushPara(); $closeList(); continue; }

        // Roher HTML-Block: bis zur nächsten Leerzeile unverändert durchreichen.
        if ($t[0] === '<') {
            $flushPara(); $closeList();
            while ($i < $n && trim($lines[$i]) !== '') { $out[] = $lines[$i]; $i++; }
            continue;
        }

        // Überschrift: # .. ######
        if (preg_match('/^(#{1,6})\s+(.*)$/', $t, $m)) {
            $flushPara(); $closeList();
            $lvl = strlen($m[1]);
            $out[] = "<h$lvl>" . sci_md_inline($m[2]) . "</h$lvl>";
            continue;
        }

        // Trennlinie
        if (preg_match('/^(-{3,}|\*{3,})$/', $t)) {
            $flushPara(); $closeList();
            $out[] = '<hr>';
            continue;
        }

        // Zitat: zusammenhängende > Zeilen
        if ($t[0] === '>') {
            $flushPara(); $closeList();
            $q = [];
            while ($i < $n && ($qt = trim($lines[$i])) !== '' && $qt[0] === '>') {
                $q[] = ltrim(substr($qt, 1));
                $i++;
            }
            $i--;
            $out[] = '<blockquote>' . sci_md_inline(implode(' ', $q)) . '</blockquote>';
            continue;
        }

        // Ungeordnete Liste: - oder *
        if (preg_match('/^[-*]\s+(.*)$/', $t, $m)) {
            $flushPara();
            if ($listType !== 'ul') { $closeList(); $out[] = '<ul>'; $listType = 'ul'; }
            $out[] = '<li>' . sci_md_inline($m[1]) . '</li>';
            continue;
        }

        // Geordnete Liste: 1. 2. ...
        if (preg_match('/^\d+\.\s+(.*)$/', $t, $m)) {
            $flushPara();
            if ($listType !== 'ol') { $closeList(); $out[] = '<ol>'; $listType = 'ol'; }
            $out[] = '<li>' . sci_md_inline($m[1]) . '</li>';
            continue;
        }

        // sonst: Absatztext
        $closeList();
        $para[] = $t;
    }
    $flushPara();
    $closeList();
    return implode("\n", $out);
}
