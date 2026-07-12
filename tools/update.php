<?php
/**
 * tools/update.php – Self-Updater.
 * Zieht neue Releases (oder einen Branch-Kopf) aus dem GitHub-Repo und spielt sie
 * direkt auf den Server ein. Bewusst abgesichert:
 *
 *   - Zugriff nur mit korrektem update_token (hash_equals). Leeres Token => deaktiviert.
 *   - Privates Repo: Download braucht github_token (Lese-Zugriff).
 *   - Dateien werden nur ÜBERLAGERT (kopiert), nie gelöscht.
 *   - update_protect (z. B. lib/config.php) wird niemals überschrieben.
 *   - Zip-Slip-Schutz: keine Datei landet außerhalb des Projektverzeichnisses.
 *
 * Aufruf:
 *   ?token=SECRET&action=check              Version vergleichen (ändert nichts)
 *   ?token=SECRET&action=apply&dry_run=1    Vorschau: was WÜRDE sich ändern
 *   ?token=SECRET&action=apply              Update wirklich einspielen
 */

declare(strict_types=1);

const SCISPIN_UPDATE_LIB = true;   // im Selbsttest: Include ohne Auto-Run
const SCISPIN_APP_ROOT   = __DIR__ . '/..';

/** GitHub-API-GET (JSON). Rückgabe: [status, body]. */
function up_gh_api(string $url, string $token): array {
    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: SciSpin-Updater',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['status' => $code, 'body' => $body === false ? null : $body, 'error' => $err];
}

/**
 * Lädt den Zipball für $ref in die Datei $dest.
 * Zweistufig: 1) API-Endpunkt mit Auth -> Redirect-URL (codeload) ermitteln,
 * 2) Redirect OHNE Auth-Header herunterladen (codeload lehnt fremde Auth sonst ab).
 * Gibt eine Fehlermeldung zurück oder null bei Erfolg.
 */
function up_download_zipball(string $repo, string $ref, string $token, string $dest): ?string {
    $api = 'https://api.github.com/repos/' . $repo . '/zipball/' . rawurlencode($ref);
    $headers = ['User-Agent: SciSpin-Updater', 'Accept: application/vnd.github+json'];
    if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

    // Schritt 1: Redirect-Ziel ermitteln (nicht folgen).
    $ch = curl_init($api);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $loc  = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $err  = curl_error($ch);
    curl_close($ch);

    // Direkt 200 mit Body (kein Redirect) -> Body ist bereits das Zip.
    if ($code === 200 && $body !== false && $body !== '') {
        return file_put_contents($dest, $body) !== false ? null : 'Konnte Archiv nicht speichern.';
    }
    if ($code < 300 || $code >= 400 || $loc === '') {
        return "Download-Start fehlgeschlagen (HTTP $code" . ($err ? ", $err" : '') . ').';
    }

    // Schritt 2: Redirect OHNE Auth-Header in die Datei laden.
    $fh = fopen($dest, 'wb');
    if ($fh === false) return 'Konnte Zieldatei nicht öffnen.';
    $ch = curl_init($loc);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['User-Agent: SciSpin-Updater'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if ($ok === false || $code !== 200) {
        return "Download fehlgeschlagen (HTTP $code" . ($err ? ", $err" : '') . ').';
    }
    return null;
}

/** Neuestes Version-Tag (vX.Y.Z) ermitteln – Fallback, wenn kein Release veröffentlicht ist. */
function up_latest_tag(string $repo, string $token): ?string {
    $r = up_gh_api('https://api.github.com/repos/' . $repo . '/tags?per_page=100', $token);
    if ($r['status'] !== 200 || !$r['body']) return null;
    $tags = json_decode($r['body'], true);
    if (!is_array($tags)) return null;
    $best = null;
    foreach ($tags as $t) {
        $name = (string)($t['name'] ?? '');
        if ($name === '') continue;
        $ver = ltrim($name, 'vV');
        if (!preg_match('/^\d+(\.\d+)*/', $ver)) continue;      // nur versionsartige Tags
        if ($best === null || version_compare($ver, ltrim($best, 'vV'), '>')) $best = $name;
    }
    return $best;
}

/** VERSION-Datei eines Refs über die Contents-API lesen (für den 'branch'-Kanal). */
function up_remote_version(string $repo, string $ref, string $token): ?string {
    $r = up_gh_api('https://api.github.com/repos/' . $repo . '/contents/VERSION?ref=' . rawurlencode($ref), $token);
    if ($r['status'] !== 200 || !$r['body']) return null;
    $j = json_decode($r['body'], true);
    if (!isset($j['content'])) return null;
    return trim((string)base64_decode(str_replace("\n", '', $j['content'])));
}

/** Rekursiv das einzige Wurzelverzeichnis im entpackten Zip finden. */
function up_find_extract_root(string $dir): ?string {
    $entries = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
    if (count($entries) === 1 && is_dir($dir . '/' . $entries[0])) {
        return $dir . '/' . $entries[0];
    }
    return $dir; // Fallback: flach entpackt
}

/**
 * Überlagert das Projekt mit den Dateien aus $srcRoot.
 * Gibt ['changed'=>[], 'skipped'=>[]] zurück. Bei $dryRun wird nichts geschrieben.
 */
function up_overlay(string $srcRoot, string $appRoot, array $protect, bool $dryRun): array {
    $appReal = realpath($appRoot);
    $srcReal = realpath($srcRoot);
    $protect = array_map(fn($p) => trim(str_replace('\\', '/', $p), '/'), $protect);
    $skipDirs = ['.git', '.github'];
    $changed = [];
    $skipped = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcReal, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($srcReal) + 1));
        // Ganze Verzeichnisse überspringen (.git etc.)
        $top = explode('/', $rel)[0];
        if (in_array($top, $skipDirs, true)) continue;
        if ($item->isDir()) continue;

        // Geschützte Pfade nie anfassen.
        if (in_array($rel, $protect, true)) { $skipped[] = $rel; continue; }

        // Zip-Slip-Schutz: Zielpfad muss innerhalb des Projektverzeichnisses liegen.
        $dest = $appReal . '/' . $rel;
        $destDir = dirname($dest);
        if (strpos($rel, '..') !== false) { $skipped[] = $rel . ' (unsicher)'; continue; }

        $new = (string)file_get_contents($item->getPathname());
        $old = is_file($dest) ? (string)file_get_contents($dest) : null;
        if ($old !== null && $old === $new) continue; // unverändert

        $changed[] = $rel;
        if ($dryRun) continue;

        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        file_put_contents($dest, $new);
    }
    sort($changed);
    sort($skipped);
    return ['changed' => $changed, 'skipped' => $skipped];
}

/** Verzeichnis rekursiv löschen (Aufräumen des Temp-Ordners). */
function up_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}

/** Haupteinstieg (Web). */
function scispin_update_main(): void {
    header('Content-Type: text/plain; charset=utf-8');

    $cfg = require SCISPIN_APP_ROOT . '/lib/config.php';

    // --- Token-Gate ---
    $expected = (string)($cfg['update_token'] ?? '');
    $given    = (string)($_GET['token'] ?? $_POST['token'] ?? '');
    if ($expected === '') {
        http_response_code(403);
        echo "Updater ist deaktiviert (kein update_token in lib/config.php gesetzt).\n";
        return;
    }
    if (!hash_equals($expected, $given)) {
        http_response_code(403);
        echo "Zugriff verweigert.\n";
        return;
    }

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo "PHP-Erweiterung ZipArchive fehlt – Update nicht möglich.\n";
        return;
    }

    $repo    = (string)($cfg['github_repo'] ?? '');
    $token   = (string)($cfg['github_token'] ?? '');
    $channel = (string)($cfg['update_channel'] ?? 'release');
    $branch  = (string)($cfg['update_branch'] ?? 'main');
    $protect = (array)($cfg['update_protect'] ?? ['lib/config.php']);
    $action  = (string)($_GET['action'] ?? 'check');
    $dryRun  = !empty($_GET['dry_run']);

    $localVersion = trim((string)@file_get_contents(SCISPIN_APP_ROOT . '/VERSION')) ?: '0.0.0';

    // --- Zielversion + Ref bestimmen ---
    if ($channel === 'branch') {
        $ref = $branch;
        $remoteVersion = up_remote_version($repo, $branch, $token) ?? '?';
        $refLabel = "Branch '$branch'";
    } else {
        // Bevorzugt ein veröffentlichtes Release; sonst Fallback auf das neueste Tag.
        $ref = '';
        $refLabel = '';
        $r = up_gh_api('https://api.github.com/repos/' . $repo . '/releases/latest', $token);
        if ($r['status'] === 200 && $r['body']) {
            $rel = json_decode($r['body'], true);
            $ref = (string)($rel['tag_name'] ?? '');
            if ($ref !== '') $refLabel = "Release '$ref'";
        }
        if ($ref === '') {
            $tag = up_latest_tag($repo, $token);
            if ($tag !== null) { $ref = $tag; $refLabel = "Tag '$ref'"; }
        }
        if ($ref === '') {
            http_response_code(502);
            echo "Kein veröffentlichtes Release und kein Version-Tag gefunden.\n";
            echo "Privates Repo? Dann github_token mit Contents-Leserecht setzen.\n";
            return;
        }
        $remoteVersion = ltrim($ref, 'vV');
    }

    $updateAvailable = version_compare($remoteVersion, ltrim($localVersion, 'vV'), '>')
        || ($channel === 'branch' && $remoteVersion !== '?' && $remoteVersion !== $localVersion);

    echo "SciSpin Self-Updater\n";
    echo "Repo:            $repo\n";
    echo "Kanal:           $channel ($refLabel)\n";
    echo "Installiert:     $localVersion\n";
    echo "Verfügbar:       $remoteVersion\n";
    echo "Update nötig:    " . ($updateAvailable ? "JA" : "nein") . "\n\n";

    if ($action !== 'apply') {
        echo "Modus 'check' – es wurde nichts verändert.\n";
        echo $updateAvailable
            ? "Zum Einspielen: ?token=…&action=apply  (Vorschau: &dry_run=1)\n"
            : "Alles aktuell.\n";
        return;
    }

    if (!$updateAvailable && !$dryRun) {
        echo "Bereits aktuell – kein Update eingespielt.\n";
        return;
    }

    // --- Download ---
    $tmpBase = sys_get_temp_dir() . '/scispin_update_' . bin2hex(random_bytes(6));
    @mkdir($tmpBase, 0700, true);
    $zipFile = $tmpBase . '/release.zip';

    echo "Lade $refLabel …\n";
    $dlErr = up_download_zipball($repo, $ref, $token, $zipFile);
    if ($dlErr !== null) { up_rrmdir($tmpBase); http_response_code(502); echo "FEHLER: $dlErr\n"; return; }

    // --- Entpacken ---
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) { up_rrmdir($tmpBase); http_response_code(500); echo "FEHLER: Archiv nicht lesbar.\n"; return; }
    $extractDir = $tmpBase . '/extract';
    @mkdir($extractDir, 0700, true);
    $zip->extractTo($extractDir);
    $zip->close();

    $srcRoot = up_find_extract_root($extractDir);

    // --- Überlagern ---
    $res = up_overlay($srcRoot, SCISPIN_APP_ROOT, $protect, $dryRun);
    up_rrmdir($tmpBase);

    echo ($dryRun ? "VORSCHAU (dry_run – nichts geschrieben)\n" : "Eingespielt.\n");
    echo "Geänderte Dateien: " . count($res['changed']) . "\n";
    foreach ($res['changed'] as $f) echo "  ~ $f\n";
    if ($res['skipped']) {
        echo "Geschützt/übersprungen: " . count($res['skipped']) . "\n";
        foreach ($res['skipped'] as $f) echo "  · $f\n";
    }
    if (!$dryRun) {
        $nowVersion = trim((string)@file_get_contents(SCISPIN_APP_ROOT . '/VERSION'));
        echo "\nNeue installierte Version: $nowVersion\n";
    }
}

// Auto-Run nur im Web-Kontext (im Selbsttest wird die Datei mit definierter
// Konstante eingebunden, ohne main() auszulösen).
if (!defined('SCISPIN_UPDATE_SELFTEST')) {
    scispin_update_main();
}
