<?php
function pick_notify_user(PDO $conn, int $userId, string $type, string $title, string $message, ?string $entityType = null, ?int $entityId = null, int $priority = 1): void {
    $stmt = $conn->prepare(<<<SQL
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            related_entity_type,
            related_entity_id,
            priority,
            is_read,
            created_at
        ) VALUES (
            :user_id,
            :type,
            :title,
            :message,
            :related_entity_type,
            :related_entity_id,
            :priority,
            0,
            NOW()
        )
    SQL);
    $stmt->execute([
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'related_entity_type' => $entityType,
        'related_entity_id' => $entityId,
        'priority' => $priority,
    ]);
}

function pick_notify_role(PDO $conn, int|array $roleIds, string $type, string $title, string $message, ?string $entityType = null, ?int $entityId = null, int $priority = 1): void {
    $roleIds = is_array($roleIds) ? $roleIds : [$roleIds];
    if ($roleIds === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
    $stmt = $conn->prepare("SELECT id FROM users WHERE role_id IN ($placeholders)");
    $stmt->execute($roleIds);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($userIds as $userId) {
        pick_notify_user($conn, (int)$userId, $type, $title, $message, $entityType, $entityId, $priority);
    }
}

function pick_count_unread_notifications(PDO $conn, int $userId): int {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute(['user_id' => $userId]);
    return (int)$stmt->fetchColumn();
}

function pick_mark_notification_read(PDO $conn, int $notificationId, int $userId): void {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([
        'id' => $notificationId,
        'user_id' => $userId,
    ]);
}
