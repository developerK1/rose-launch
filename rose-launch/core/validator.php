<?php

function sanitize_string($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function validate_price($value) {
    return is_numeric($value) && $value >= 0;
}

function validate_phone($value) {
    return preg_match('/^[0-9+]{8,15}$/', $value);
}