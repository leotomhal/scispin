<?php
/**
 * Extraktions-Pipeline: Identifier zuerst, HTML nur als letzter Fallback.
 *
 * Rückgabe von sc_extract():
 *   [ 'doi'=>?string, 'title'=>?string, 'journal'=>?string,
 *     'crossref_type'=>?string, 'subtype'=>?string, 'is_preprint'=>bool,
 *     'in_pubmed'=>bool, 'abstract'=>?string, 'datenbasis'=>'abstract'|'volltext',
 *     'article_type'=>?string, 'error'=>?string ]
 */

require_once __DIR__ . '/../../lib/http.php';

/** DOI/Identifier aus Link gewinnen. */
function sc_extract_doi(string $url): array {
    // arXiv
    if (preg_match('~arxiv\.org/(?:abs|pdf)/([0-9]{4}\.[0-9]{4,5}(?:v\d+)?)~i', $url, $m)) {
        $id = preg_replace('~v\d+$~', '', $m[1]);
        return ['doi' => '10.48550/arXiv.' . $id, 'pmid' => null, 'arxiv' => $id];
    }
    // PubMed
    if (preg_match('~pubmed\.ncbi\.nlm\.nih\.gov/(\d+)~i', $url, $m)) {
        return ['doi' => null, 'pmid' => $m[1], 'arxiv' => null];
    }
    // Direkte DOI im Link
    if (preg_match('~10\.\d{4,9}/[-._;()/:A-Za-z0-9]+~', rawurldecode($url), $m)) {
        return ['doi' => rtrim($m[0], '.,);'), 'pmid' => null, 'arxiv' => null];
    }
    return ['doi' => null, 'pmid' => null, 'arxiv' => null];
}

/** Crossref-Metadaten. */
function sc_crossref(string $doi, string $mailto, int $timeout): ?array {
    $url = 'https://api.crossref.org/works/' . rawurlencode($doi) . '?mailto=' . rawurlencode($mailto);
    $r = sc_http_get($url, ['Accept: application/json'], $timeout);
    if ($r['status'] !== 200 || !$r['body']) return null;
    $j = json_decode($r['body'], true);
    $msg = $j['message'] ?? null;
    if (!$msg) return null;
    $abstract = isset($msg['abstract']) ? sc_strip_jats($msg['abstract']) : null;
    return [
        'title'    => isset($msg['title'][0]) ? $msg['title'][0] : null,
        'journal'  => isset($msg['container-title'][0]) ? $msg['container-title'][0] : null,
        'type'     => $msg['type'] ?? null,
        'subtype'  => $msg['subtype'] ?? null,
        'abstract' => $abstract,
    ];
}

/** Europe PMC: Abstract + PubMed-Indexierung. Funktioniert auch per PMID. */
function sc_europepmc(string $query, int $timeout): ?array {
    $url = 'https://www.ebi.ac.uk/europepmc/webservices/rest/search?query='
         . rawurlencode($query) . '&resultType=core&format=json&pageSize=1';
    $r = sc_http_get($url, ['Accept: application/json'], $timeout);
    if ($r['status'] !== 200 || !$r['body']) return null;
    $j = json_decode($r['body'], true);
    $res = $j['resultList']['result'][0] ?? null;
    if (!$res) return null;
    return [
        'abstract'  => $res['abstractText'] ?? null,
        'doi'       => $res['doi'] ?? null,
        'title'     => $res['title'] ?? null,
        'journal'   => $res['journalTitle'] ?? ($res['journalInfo']['journal']['title'] ?? null),
        'in_pubmed' => (($res['source'] ?? '') === 'MED') || !empty($res['pmid']),
    ];
}

/** arXiv-Abstract über die arXiv-API (Crossref liefert für Preprints oft keinen). */
function sc_arxiv_abstract(string $arxivId, int $timeout): ?string {
    $url = 'http://export.arxiv.org/api/query?id_list=' . rawurlencode($arxivId);
    $r = sc_http_get($url, ['Accept: application/atom+xml'], $timeout);
    if ($r['status'] !== 200 || !$r['body']) return null;
    if (preg_match('~<summary>(.*?)</summary>~s', $r['body'], $m)) {
        return trim(preg_replace('~\s+~', ' ', html_entity_decode($m[1], ENT_QUOTES | ENT_XML1)));
    }
    return null;
}

/** Fallback: Meta-Tags der HTML-Seite. Kein JS-Rendering. */
function sc_fallback_scrape(string $url, int $timeout): array {
    $r = sc_http_get($url, ['Accept: text/html'], $timeout);
    if ($r['status'] < 200 || $r['status'] >= 400 || !$r['body']) {
        return ['abstract' => null, 'title' => null, 'doi' => null, 'article_type' => null];
    }
    $html = $r['body'];
    $abstract = sc_meta($html, ['citation_abstract', 'dc.description', 'description'])
             ?? sc_og($html, 'og:description');
    $title = sc_meta($html, ['citation_title', 'dc.title']) ?? sc_og($html, 'og:title');
    $doi = sc_meta($html, ['citation_doi', 'dc.identifier']);
    if ($doi && preg_match('~10\.\d{4,9}/[-._;()/:A-Za-z0-9]+~', $doi, $m)) {
        $doi = rtrim($m[0], '.,);');
    } else {
        $doi = null;
    }
    $type = sc_meta($html, ['citation_article_type', 'dc.type', 'prism.section']);
    return ['abstract' => $abstract, 'title' => $title, 'doi' => $doi, 'article_type' => $type];
}

function sc_meta(string $html, array $names): ?string {
    foreach ($names as $name) {
        $p = '~<meta[^>]+name=["\']' . preg_quote($name, '~') . '["\'][^>]+content=["\'](.*?)["\']~is';
        if (preg_match($p, $html, $m)) return trim(html_entity_decode($m[1], ENT_QUOTES));
    }
    return null;
}
function sc_og(string $html, string $prop): ?string {
    $p = '~<meta[^>]+property=["\']' . preg_quote($prop, '~') . '["\'][^>]+content=["\'](.*?)["\']~is';
    if (preg_match($p, $html, $m)) return trim(html_entity_decode($m[1], ENT_QUOTES));
    return null;
}

/** JATS-/HTML-Tags aus Crossref-Abstract entfernen. */
function sc_strip_jats(string $s): string {
    $s = preg_replace('~<jats:title>.*?</jats:title>~is', '', $s);
    $s = strip_tags($s);
    return trim(preg_replace('~\s+~', ' ', html_entity_decode($s, ENT_QUOTES)));
}

/** Orchestriert die gesamte Extraktion. */
function sc_extract(string $url, string $mailto, int $timeout): array {
    $out = [
        'doi' => null, 'title' => null, 'journal' => null,
        'crossref_type' => null, 'subtype' => null, 'is_preprint' => false,
        'in_pubmed' => false, 'abstract' => null, 'datenbasis' => 'abstract',
        'article_type' => null,
        'error' => null,
    ];

    $ident = sc_extract_doi($url);

    // PubMed-Pfad: PMID -> Europe PMC (liefert DOI + Abstract)
    if ($ident['pmid']) {
        $epmc = sc_europepmc('EXT_ID:' . $ident['pmid'] . ' AND SRC:MED', $timeout);
        if ($epmc) {
            $out['doi']       = $epmc['doi'] ?: ('PMID:' . $ident['pmid']);
            $out['title']     = $epmc['title'];
            $out['journal']   = $epmc['journal'];
            $out['abstract']  = $epmc['abstract'];
            $out['in_pubmed'] = true;
            $ident['doi']     = $epmc['doi'] ?: $ident['doi'];
        }
    }

    $doi = $ident['doi'];

    // Crossref-Metadaten
    if ($doi) {
        $out['doi'] = $doi;
        $cr = sc_crossref($doi, $mailto, $timeout);
        if ($cr) {
            $out['title']         = $out['title'] ?: $cr['title'];
            $out['journal']       = $out['journal'] ?: $cr['journal'];
            $out['crossref_type'] = $cr['type'];
            $out['subtype']       = $cr['subtype'];
            if (!$out['abstract'] && $cr['abstract']) $out['abstract'] = $cr['abstract'];
        }
        $out['is_preprint'] = sc_is_preprint($doi, $out['crossref_type'], $out['subtype'], $url);
    }

    // Abstract noch leer? Europe PMC per DOI versuchen
    if (!$out['abstract'] && $doi && strpos($doi, 'PMID:') !== 0) {
        $epmc = sc_europepmc('DOI:' . $doi, $timeout);
        if ($epmc) {
            if ($epmc['abstract']) $out['abstract'] = $epmc['abstract'];
            if ($epmc['in_pubmed']) $out['in_pubmed'] = true;
            $out['title']   = $out['title']   ?: $epmc['title'];
            $out['journal'] = $out['journal'] ?: $epmc['journal'];
        }
    }

    // arXiv-Abstract
    if (!$out['abstract'] && $ident['arxiv']) {
        $out['abstract'] = sc_arxiv_abstract($ident['arxiv'], $timeout);
    }

    // Fallback-Scraping
    if (!$out['abstract'] || !$out['doi']) {
        $scr = sc_fallback_scrape($url, $timeout);
        if (!$out['abstract'] && $scr['abstract']) $out['abstract'] = $scr['abstract'];
        if (!$out['title'] && $scr['title']) $out['title'] = $scr['title'];
        if (!$out['article_type'] && !empty($scr['article_type'])) $out['article_type'] = $scr['article_type'];
        if (!$out['doi'] && $scr['doi']) {
            $out['doi'] = $scr['doi'];
            $cr = sc_crossref($scr['doi'], $mailto, $timeout);
            if ($cr) {
                $out['title']         = $out['title'] ?: $cr['title'];
                $out['journal']       = $out['journal'] ?: $cr['journal'];
                $out['crossref_type'] = $cr['type'];
                $out['subtype']       = $cr['subtype'];
                if (!$out['abstract'] && $cr['abstract']) $out['abstract'] = $cr['abstract'];
            }
            $out['is_preprint'] = sc_is_preprint($out['doi'], $out['crossref_type'], $out['subtype'], $url);
        }
    }

    // Datenbasis / Fehlerzustand
    if (!$out['abstract']) {
        if (sc_is_nonresearch($out['article_type'], $out['crossref_type'])) {
            $out['error'] = 'nonresearch';
        } else {
            $out['error'] = $out['doi'] ? 'no_abstract' : 'no_doi';
        }
    } else {
        $out['datenbasis'] = 'abstract'; // Volltext-Methodik via diese APIs praktisch nie
    }

    return $out;
}

/**
 * Erkennt redaktionelle / nicht-empirische Beitragstypen (Editorial, Erratum,
 * Korrektur, Rezension, News). Bewusst KONSERVATIV: Reviews und Meta-Analysen
 * gelten als Forschung und werden NICHT erfasst.
 */
function sc_is_nonresearch(?string $articleType, ?string $crossrefType): bool {
    $t = strtolower(trim((string)$articleType));
    if ($t !== '') {
        $marker = ['editorial', 'erratum', 'correction', 'corrigendum', 'retraction',
                   'book review', 'book-review', 'bookreview', 'news', 'addendum',
                   'preface', 'foreword', 'introduction', 'in memoriam', 'obituary'];
        foreach ($marker as $m) if (strpos($t, $m) !== false) return true;
    }
    if (in_array($crossrefType, ['editorial', 'book-review', 'erratum', 'correction'], true)) return true;
    return false;
}

/** Preprint-Heuristik. */
function sc_is_preprint(?string $doi, ?string $type, ?string $subtype, string $url): bool {
    if ($subtype === 'preprint') return true;
    if ($type === 'posted-content') return true;
    if ($doi && stripos($doi, '10.48550/arxiv') === 0) return true;     // arXiv
    if ($doi && strpos($doi, '10.1101/') === 0) return true;            // bioRxiv/medRxiv
    $hosts = ['arxiv.org', 'biorxiv.org', 'medrxiv.org', 'osf.io', 'ssrn.com', 'researchsquare', 'preprints.org'];
    foreach ($hosts as $h) if (stripos($url, $h) !== false) return true;
    return false;
}
