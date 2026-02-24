<?php

require_once __DIR__ . '/../helpers.php';

function lightspeedxGetConfig() {
    $hostRaw = trim((string)getAppEnv('LIGHTSPEED_X_STORE_HOST', ''));
    if ($hostRaw === '') {
        $hostRaw = trim((string)getAppEnv('LIGHTSPEED_X_API_HOST', ''));
    }
    if ($hostRaw === '') {
        $base = trim((string)getAppEnv('LIGHTSPEED_X_API_BASE', ''));
        if ($base !== '') {
            $parsedHost = parse_url($base, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $hostRaw = $parsedHost;
            } else {
                $hostRaw = $base;
            }
        }
    }
    $hostRaw = preg_replace('#^https?://#i', '', $hostRaw);
    $hostRaw = rtrim((string)$hostRaw, '/');
    $token = trim((string)getAppEnv('LIGHTSPEED_X_ACCESS_TOKEN', ''));
    return [
        'host' => $hostRaw,
        'token' => $token,
    ];
}

function lightspeedxValidateConfig() {
    $cfg = lightspeedxGetConfig();
    if ($cfg['host'] === '' || $cfg['token'] === '') {
        return [
            'ok' => false,
            'message' => 'Missing LIGHTSPEED_X_STORE_HOST (or LIGHTSPEED_X_API_HOST/LIGHTSPEED_X_API_BASE) and/or LIGHTSPEED_X_ACCESS_TOKEN.',
            'config' => $cfg,
        ];
    }
    return ['ok' => true, 'config' => $cfg];
}

function lightspeedxApiGet($path, array $query = [], array $configOverride = []) {
    $cfg = lightspeedxGetConfig();
    if (!empty($configOverride['host'])) {
        $cfg['host'] = trim((string)$configOverride['host']);
    }
    if (!empty($configOverride['token'])) {
        $cfg['token'] = trim((string)$configOverride['token']);
    }
    if ($cfg['host'] === '' || $cfg['token'] === '') {
        return ['success' => false, 'status_code' => 0, 'message' => 'Lightspeed config missing host/token.', 'body' => null];
    }
    $path = '/' . ltrim((string)$path, '/');
    $url = 'https://' . $cfg['host'] . $path;
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: Bearer ' . $cfg['token'],
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: artisan-den-lightspeed-sync/1.0',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $raw = curl_exec($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            return ['success' => false, 'status_code' => $statusCode, 'message' => 'cURL request failed: ' . $curlErr, 'body' => null];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        $statusCode = 0;
        if (!empty($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
                $statusCode = (int)$m[1];
            }
        }
        if ($raw === false) {
            return ['success' => false, 'status_code' => $statusCode, 'message' => 'HTTP request failed.', 'body' => null];
        }
    }

    $decoded = json_decode((string)$raw, true);
    $isJson = json_last_error() === JSON_ERROR_NONE;
    if (!$isJson) {
        $decoded = null;
    }
    $ok = ($statusCode >= 200 && $statusCode < 300);
    $message = $ok ? 'OK' : ('HTTP ' . $statusCode);
    if ($statusCode === 302) {
        $message = 'HTTP 302 redirect. Verify store host and token.';
    }
    if (!$ok && is_array($decoded)) {
        $apiMsg = trim((string)($decoded['message'] ?? $decoded['error'] ?? ''));
        if ($apiMsg !== '') {
            $message .= ': ' . $apiMsg;
        }
    }

    return [
        'success' => $ok,
        'status_code' => $statusCode,
        'message' => $message,
        'body' => $decoded,
        'raw_body' => $isJson ? null : (string)$raw,
        'url' => $url,
    ];
}

function lightspeedxNormalizeRowsArray($data, $injectIdFromKey = false) {
    if (!is_array($data)) {
        return [];
    }
    $isList = (array_keys($data) === range(0, count($data) - 1));
    if ($isList) {
        return $data;
    }
    $rows = [];
    foreach ($data as $k => $v) {
        if (!is_array($v)) {
            continue;
        }
        if ($injectIdFromKey && !isset($v['id']) && !isset($v['category_id']) && !isset($v['product_id'])) {
            $v['id'] = (string)$k;
        }
        $rows[] = $v;
    }
    return $rows;
}

function lightspeedxExtractRows($payload, $preferredKey = null) {
    if (!is_array($payload)) {
        return [];
    }
    if ($preferredKey !== null && isset($payload[$preferredKey]) && is_array($payload[$preferredKey])) {
        return lightspeedxNormalizeRowsArray($payload[$preferredKey], true);
    }
    if (isset($payload['data']) && is_array($payload['data'])) {
        // Common shape: { data: [...] }
        if (array_keys($payload['data']) === range(0, count($payload['data']) - 1)) {
            return $payload['data'];
        }
        // Nested shape: { data: { data: {... or [...]}, page_info: {...} } }
        if (isset($payload['data']['data']) && is_array($payload['data']['data'])) {
            $nestedData = $payload['data']['data'];
            // Observed category shape: { data: { data: { categories: [ ... ] } } }
            if (isset($nestedData['categories']) && is_array($nestedData['categories'])) {
                return lightspeedxNormalizeRowsArray($nestedData['categories'], true);
            }
            return lightspeedxNormalizeRowsArray($nestedData, true);
        }
        return lightspeedxNormalizeRowsArray($payload['data'], true);
    }
    if (array_keys($payload) === range(0, count($payload) - 1)) {
        return $payload;
    }
    foreach ($payload as $v) {
        if (is_array($v)) {
            $rows = lightspeedxNormalizeRowsArray($v, true);
            if (!empty($rows)) {
                return $rows;
            }
        }
    }
    return [];
}

function lightspeedxGetCollection($path, array $options = []) {
    $limit = max(1, min(500, (int)($options['limit'] ?? 200)));
    $maxPages = max(1, min(500, (int)($options['max_pages'] ?? 25)));
    $preferredKey = isset($options['preferred_key']) ? (string)$options['preferred_key'] : null;
    $baseQuery = isset($options['query']) && is_array($options['query']) ? $options['query'] : [];
    if (!isset($baseQuery['limit'])) {
        $baseQuery['limit'] = $limit;
    }

    $allRows = [];
    $pages = 0;
    $after = null;
    $lastVersion = null;
    while ($pages < $maxPages) {
        $pages++;
        $query = $baseQuery;
        if ($after !== null && $after !== '') {
            $query['after'] = $after;
        }
        $resp = lightspeedxApiGet($path, $query);
        if (empty($resp['success'])) {
            return [
                'success' => false,
                'message' => (string)($resp['message'] ?? 'Lightspeed API request failed.'),
                'rows' => $allRows,
                'pages' => $pages,
                'status_code' => (int)($resp['status_code'] ?? 0),
                'url' => (string)($resp['url'] ?? ''),
            ];
        }
        $rows = lightspeedxExtractRows($resp['body'], $preferredKey);
        if (empty($rows)) {
            break;
        }
        foreach ($rows as $row) {
            $allRows[] = $row;
        }
        if (count($rows) < $limit) {
            break;
        }
        $last = end($rows);
        $last = is_array($last) ? $last : [];
        $nextAfter = isset($last['version']) ? (string)$last['version'] : '';
        if ($nextAfter === '' || $nextAfter === $lastVersion) {
            break;
        }
        $lastVersion = $nextAfter;
        $after = $nextAfter;
    }

    return [
        'success' => true,
        'message' => 'OK',
        'rows' => $allRows,
        'pages' => $pages,
    ];
}
