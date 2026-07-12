<?php
/**
 * Schlanker cURL-Wrapper (geteilt von check/ und spin/).
 * Gibt ['status'=>int, 'body'=>?string, 'error'=>?string] zurück; body=null bei Fehler.
 */

function sc_http_get(string $url, array $headers, int $timeout): array {
    return sc_http_request('GET', $url, $headers, null, $timeout);
}

function sc_http_post(string $url, array $headers, string $body, int $timeout): array {
    return sc_http_request('POST', $url, $headers, $body, $timeout);
}

function sc_http_request(string $method, string $url, array $headers, ?string $body, int $timeout): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'SciSpin/1.0 (+https://github.com/leotomhal/scispin)',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        return ['status' => 0, 'body' => null, 'error' => $err];
    }
    return ['status' => $code, 'body' => $resp, 'error' => null];
}
