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
