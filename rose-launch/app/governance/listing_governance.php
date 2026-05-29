<?php
require_once __DIR__ . '/roles.php';

function pick_listing_public_where(string $alias = 'l', ?string $userAlias = 'u' ): string {
    $clause = "{$alias}.moderation_status = 'approved' AND {$alias}.listing_status IN ('active', 'grace_period')";
    if ($userAlias !== null && $userAlias !== '') {
        $clause .= " AND COALESCE({$userAlias}.account_state, 'verified') NOT IN ('suspended', 'archived', 'identity_review_required')";
    }
    return $clause;
}

function pick_listing_is_public(array $listing): bool {
    $landlordState = (string)($listing['account_state'] ?? 'verified');
    return (($listing['moderation_status'] ?? '') === 'approved')
        && in_array(($listing['listing_status'] ?? ''), ['active', 'grace_period'], true)
        && !in_array($landlordState, ['suspended', 'archived', 'identity_review_required'], true);
}

function pick_listing_badge_visible(array $listing): bool {
    return (($listing['verification_status'] ?? '') === 'verified')
        && !in_array((string)($listing['account_state'] ?? 'verified'), ['suspended', 'archived'], true);
}

function pick_listing_requires_requeue(array $before, array $after): bool {
    $tracked = ['province_id', 'town_id', 'area', 'price', 'contact_number', 'property_type', 'title', 'description'];
    foreach ($tracked as $field) {
        if ((string)($before[$field] ?? '') !== (string)($after[$field] ?? '')) {
            return true;
        }
    }
    return false;
}

function pick_listing_revision_pairs(array $before, array $after): array {
    $tracked = ['title', 'description', 'price', 'province_id', 'town_id', 'area', 'contact_number', 'property_type'];
    $changes = [];
    foreach ($tracked as $field) {
        $old = $before[$field] ?? null;
        $new = $after[$field] ?? null;
        if ((string)$old !== (string)$new) {
            $changes[] = [
                'field_name' => $field,
                'old_value' => is_scalar($old) || $old === null ? (string)$old : json_encode($old),
                'new_value' => is_scalar($new) || $new === null ? (string)$new : json_encode($new),
            ];
        }
    }
    return $changes;
}

function pick_listing_admin_search_clause(string $alias, string $query, array &$params): string {
    $query = trim($query);
    if ($query === '') {
        return '';
    }
    $params['query'] = '%' . $query . '%';
    return " AND (" .
        "{$alias}.title LIKE :query OR " .
        "{$alias}.area LIKE :query OR " .
        "{$alias}.property_type LIKE :query OR " .
        "CAST({$alias}.id AS CHAR) LIKE :query OR " .
        "u.full_name LIKE :query OR " .
        "u.whatsapp_number LIKE :query OR " .
        "p.name LIKE :query OR " .
        "t.name LIKE :query" .
    ")";
}

function pick_listing_public_search_clause(string $alias, string $query, array &$params): string {
    $query = trim($query);
    if ($query === '') {
        return '';
    }
    $params['query'] = '%' . $query . '%';
    return " AND (" .
        "{$alias}.title LIKE :query OR " .
        "{$alias}.area LIKE :query OR " .
        "{$alias}.property_type LIKE :query OR " .
        "p.name LIKE :query OR " .
        "t.name LIKE :query" .
    ")";
}

function pick_listing_public_order_sql(string $alias = 'l', string $userAlias = 'u'): string {
    return "CASE WHEN {$alias}.verification_status = 'verified' THEN 0 ELSE 1 END, COALESCE({$userAlias}.trust_score, 50) DESC, COALESCE({$alias}.last_confirmed_at, {$alias}.created_at) DESC";
}

function pick_listing_is_complete(array $listing): bool {
    foreach (['title','description','price','contact_number','area','property_type'] as $field) {
        if (trim((string)($listing[$field] ?? '')) === '') {
            return false;
        }
    }
    return true;
}
