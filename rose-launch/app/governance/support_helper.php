<?php
function pick_support_severity_for_category(string $category): string {
    return match ($category) {
        'scam_report', 'identity_review_issue', 'appeal' => 'high',
        'listing_issue', 'verification_issue', 'expiry_issue' => 'medium',
        'technical_issue', 'password_recovery', 'support_request' => 'low',
        default => 'low',
    };
}

function pick_create_support_ticket(PDO $conn, int $userId, ?int $listingId, string $category, string $subject, string $message): int {
    $severity = pick_support_severity_for_category($category);

    $stmt = $conn->prepare(<<<SQL
        INSERT INTO support_tickets (
            user_id,
            listing_id,
            category,
            severity,
            subject,
            status,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :listing_id,
            :category,
            :severity,
            :subject,
            'open',
            NOW(),
            NOW()
        )
    SQL);
    $stmt->execute([
        'user_id' => $userId,
        'listing_id' => $listingId,
        'category' => $category,
        'severity' => $severity,
        'subject' => $subject,
    ]);

    $ticketId = (int)$conn->lastInsertId();
    pick_add_support_message($conn, $ticketId, 'landlord', $userId, $message);

    return $ticketId;
}

function pick_add_support_message(PDO $conn, int $ticketId, string $senderRole, ?int $senderId, string $message): void {
    $stmt = $conn->prepare(<<<SQL
        INSERT INTO support_ticket_messages (
            ticket_id,
            sender_role,
            sender_id,
            message,
            created_at
        ) VALUES (
            :ticket_id,
            :sender_role,
            :sender_id,
            :message,
            NOW()
        )
    SQL);
    $stmt->execute([
        'ticket_id' => $ticketId,
        'sender_role' => $senderRole,
        'sender_id' => $senderId,
        'message' => $message,
    ]);
}

function pick_update_support_ticket(PDO $conn, int $ticketId, string $status, ?int $assignedTo = null, ?string $resolutionNote = null): void {
    $stmt = $conn->prepare("
        UPDATE support_tickets
        SET status = :status,
            assigned_to = :assigned_to,
            resolution_note = :resolution_note,
            updated_at = NOW(),
            resolved_at = CASE WHEN :status = 'resolved' THEN NOW() ELSE resolved_at END
        WHERE id = :id
    ");
    $stmt->execute([
        'status' => $status,
        'assigned_to' => $assignedTo,
        'resolution_note' => $resolutionNote,
        'id' => $ticketId,
    ]);
}
