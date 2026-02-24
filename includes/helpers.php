<?php
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Read local env values from .env.local/.env without requiring shell source.
 * getenv() still wins if variable is already present in process env.
 */
function getAppEnv($key, $default = null) {
    $key = trim((string)$key);
    if ($key === '') {
        return $default;
    }
    $runtimeVal = getenv($key);
    if ($runtimeVal !== false && $runtimeVal !== '') {
        return $runtimeVal;
    }
    static $loaded = false;
    static $envMap = [];
    if (!$loaded) {
        $loaded = true;
        $rootDir = dirname(__DIR__);
        $envFiles = [$rootDir . '/.env.local', $rootDir . '/.env'];
        foreach ($envFiles as $envFile) {
            if (!is_file($envFile)) {
                continue;
            }
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$name, $value] = array_map('trim', explode('=', $line, 2));
                if ($name === '') {
                    continue;
                }
                $value = trim((string)$value, "\"'");
                if (!array_key_exists($name, $envMap)) {
                    $envMap[$name] = $value;
                }
            }
        }
    }
    return array_key_exists($key, $envMap) ? $envMap[$key] : $default;
}
function formatCurrency($amount) {
    return '$' . number_format(floatval($amount), 2);
}
function formatPercentage($value) {
    return number_format(floatval($value), 2) . '%';
}

function formatDateForUser($value, $fallback = '-') {
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }
    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $dt = new DateTime($raw, new DateTimeZone(TIMEZONE));
            return $dt->format('m/d/y');
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('m/d/y', $ts);
    } catch (Throwable $e) {
        return $raw;
    }
}

function formatDateTimeForUser($value, $fallback = '-') {
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }
    try {
        $dt = new DateTime($raw);
        $dt->setTimezone(new DateTimeZone(TIMEZONE));
        return $dt->format('m/d/y g:i A');
    } catch (Throwable $e) {
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('m/d/y g:i A', $ts);
    }
}
