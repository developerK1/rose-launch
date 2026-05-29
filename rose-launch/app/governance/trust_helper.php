<?php
function pick_base_trust_score(): int {
    return 50;
}

function pick_adjust_trust_score(PDO $conn, int $userId, int $delta, string $reason = ''): void {
    $stmt = $conn->prepare("
        UPDATE users
        SET trust_score = GREATEST(0, LEAST(100, COALESCE(trust_score, 50) + :delta))
        WHERE id = :id
    ");
    $stmt->execute([
        'delta' => $delta,
        'id' => $userId,
    ]);
}

function pick_recalculate_landlord_trust(PDO $conn, int $userId): int {
    $stmt = $conn->prepare("SELECT COALESCE(whatsapp_verified, 0) AS whatsapp_verified, COALESCE(profile_completed, 0) AS profile_completed, COALESCE(trust_score, 50) AS trust_score FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $score = 50;
    if ((int)($user['whatsapp_verified'] ?? 0) === 1) {
        $score += 15;
    }
    if ((int)($user['profile_completed'] ?? 0) === 1) {
        $score += 5;
    }

    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN report_status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports,
            SUM(CASE WHEN report_status = 'pending' THEN 1 ELSE 0 END) AS pending_reports
        FROM reports r
        JOIN listings l ON l.id = r.listing_id
        WHERE l.user_id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $reports = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $score -= ((int)($reports['resolved_reports'] ?? 0) * 8);
    $score -= ((int)($reports['pending_reports'] ?? 0) * 2);

    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM listings
        WHERE user_id = :user_id AND listing_status IN ('expired', 'grace_period')
    ");
    $stmt->execute(['user_id' => $userId]);
    $expired = (int)$stmt->fetchColumn();
    $score -= min(20, $expired * 2);

    $score = max(0, min(100, $score));

    $stmt = $conn->prepare("UPDATE users SET trust_score = :score WHERE id = :id");
    $stmt->execute(['score' => $score, 'id' => $userId]);

    return $score;
}

function pick_listing_completeness(array $listing): int {
    $score = 0;
    foreach (['title', 'description', 'price', 'contact_number', 'area'] as $field) {
        if (!empty(trim((string)($listing[$field] ?? '')))) {
            $score += 15;
        }
    }
    if (!empty($listing['property_type'])) {
        $score += 10;
    }
    if (!empty($listing['cover_image']) || !empty($listing['image_count'])) {
        $score += 20;
    }
    return min(100, $score);
}
