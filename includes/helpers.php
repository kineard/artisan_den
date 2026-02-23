<?php
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
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
