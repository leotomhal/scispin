<?php
/**
 * Journal-Badge: deterministisch. Kein Predatory-Verdikt.
 * Rückgabe: ['code'=>'peer'|'preprint'|'unklar', 'label'=>..., 'hint'=>...]
 */

function sc_badge(array $ex): array {
    if ($ex['is_preprint']) {
        return [
            'code'  => 'preprint',
            'label' => 'Preprint – noch nicht begutachtet',
            'hint'  => 'Diese Arbeit wurde noch nicht von unabhängigen Fachleuten geprüft. '
                     . 'Ergebnisse sind vorläufig und können sich im Begutachtungsverfahren noch ändern.',
        ];
    }
    if ($ex['crossref_type'] === 'journal-article' && $ex['in_pubmed']) {
        return [
            'code'  => 'peer',
            'label' => 'Peer-Review-Journal',
            'hint'  => 'In einer Fachzeitschrift mit Begutachtungsverfahren erschienen und in einer '
                     . 'wissenschaftlichen Datenbank indexiert.',
        ];
    }
    return [
        'code'  => 'unklar',
        'label' => 'Status unklar',
        'hint'  => 'Der Begutachtungsstatus ließ sich nicht automatisch verifizieren.',
    ];
}
