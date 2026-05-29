<?php
function log_admin_action(PDO $conn, int $adminId, string $actionType, string $entityType, ?int $entityId, ?string $note = null, $oldValue = null, $newValue = null): void {
    $stmt = $conn->prepare(<<<SQL
        INSERT INTO admin_logs (
            admin_id,
            action_type,
            entity_type,
            entity_id,
            old_value,
            new_value,
            note,
            created_at
        ) VALUES (
            :admin_id,
            :action_type,
            :entity_type,
            :entity_id,
            :old_value,
            :new_value,
            :note,
            NOW()
        )
    SQL);

    $stmt->execute([
        'admin_id' => $adminId,
        'action_type' => $actionType,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'old_value' => $oldValue === null ? null : (is_string($oldValue) ? $oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE)),
        'new_value' => $newValue === null ? null : (is_string($newValue) ? $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE)),
        'note' => $note,
    ]);
}
