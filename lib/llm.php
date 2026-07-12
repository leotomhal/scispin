<?php
/**
 * Geteilte LLM-Schicht (check/ und spin/).
 * Low-Level-Anthropic-Call + robuste JSON-Extraktion. Die anwendungs-
 * spezifischen Prompts und Schema-Validierungen liegen im jeweiligen Modus.
 */

require_once __DIR__ . '/http.php';

/**
 * Ein Anthropic-Messages-Call. Gibt den konkatenierten Text zurück
 * oder null bei HTTP-/Transportfehler.
 */
function sc_anthropic_text(array $cfg, array $messages, string $systemPrompt, int $maxTokens = 2000): ?string {
    $body = json_encode([
        'model'      => $cfg['anthropic_model'] ?? 'claude-sonnet-5',
        'max_tokens' => $maxTokens,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ], JSON_UNESCAPED_UNICODE);

    $r = sc_http_post(
        $cfg['anthropic_endpoint'] ?? 'https://api.anthropic.com/v1/messages',
        [
            'content-type: application/json',
            'x-api-key: ' . ($cfg['anthropic_api_key'] ?? ''),
            'anthropic-version: ' . ($cfg['anthropic_version'] ?? '2023-06-01'),
        ],
        $body,
        (int)($cfg['llm_timeout'] ?? 90)
    );

    if ($r['status'] !== 200 || !$r['body']) return null;
    $j = json_decode($r['body'], true);
    $text = '';
    foreach (($j['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') $text .= $block['text'] ?? '';
    }
    return $text !== '' ? $text : null;
}

/**
 * Erstes vollständiges, balanciertes JSON-Objekt aus einem String ziehen.
 * Unempfindlich gegen einleitenden Text oder Code-Fences.
 */
function sc_extract_json(string $s): string {
    $s = trim($s);
    if (strncmp($s, '```', 3) === 0) {
        $s = preg_replace('/^```(?:json)?\s*/', '', $s);
        $s = preg_replace('/\s*```$/', '', $s);
        $s = trim($s);
    }
    $start = strpos($s, '{');
    if ($start === false) return $s;
    $depth = 0; $inStr = false; $esc = false;
    for ($i = $start, $n = strlen($s); $i < $n; $i++) {
        $c = $s[$i];
        if ($inStr) {
            if ($esc)            { $esc = false; }
            elseif ($c === '\\') { $esc = true; }
            elseif ($c === '"')  { $inStr = false; }
            continue;
        }
        if ($c === '"')      { $inStr = true; }
        elseif ($c === '{')  { $depth++; }
        elseif ($c === '}')  { $depth--; if ($depth === 0) return substr($s, $start, $i - $start + 1); }
    }
    return substr($s, $start);
}
