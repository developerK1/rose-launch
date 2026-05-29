<?php
function pick_report_severity(string $reportType): string {
    return match ($reportType) {
        'fake_listing', 'suspicious_behavior' => 'critical',
        'unavailable_room', 'wrong_details' => 'medium',
        default => 'low',
    };
}

function pick_create_incident_case_from_report(PDO $conn, array $report, int $openedByAdminId, string $note = ''): int {
    $severity = pick_report_severity((string)($report['report_type'] ?? ''));

    $stmt = $conn->prepare(<<<SQL
        INSERT INTO incident_cases (
            report_id,
            listing_id,
            landlord_id,
            severity,
            status,
            opened_by,
            note,
            created_at,
            updated_at
        ) VALUES (
            :report_id,
            :listing_id,
            :landlord_id,
            :severity,
            'open',
            :opened_by,
            :note,
            NOW(),
            NOW()
        )
    SQL);
    $stmt->execute([
        'report_id' => $report['id'] ?? null,
        'listing_id' => $report['listing_id'] ?? null,
        'landlord_id' => $report['landlord_id'] ?? null,
        'severity' => $severity,
        'opened_by' => $openedByAdminId,
        'note' => $note,
    ]);

    return (int)$conn->lastInsertId();
}

function pick_record_incident_evidence(PDO $conn, int $caseId, string $evidenceType, string $payload, ?int $addedBy = null): void {
    $stmt = $conn->prepare(<<<SQL
        INSERT INTO incident_evidence (
            case_id,
            evidence_type,
            evidence_payload,
            added_by,
            created_at
        ) VALUES (
            :case_id,
            :evidence_type,
            :evidence_payload,
            :added_by,
            NOW()
        )
    SQL);
    $stmt->execute([
        'case_id' => $caseId,
        'evidence_type' => $evidenceType,
        'evidence_payload' => $payload,
        'added_by' => $addedBy,
    ]);
}

function pick_handle_repeat_offender(PDO $conn, int $landlordId): array {
    $stmt = $conn->prepare(<<<SQL
        SELECT COUNT(*) AS severe_cases
        FROM incident_cases
        WHERE landlord_id = :landlord_id
          AND severity = 'critical'
          AND created_at > DATE_SUB(NOW(), INTERVAL 12 MONTH)
    SQL);
    $stmt->execute(['landlord_id' => $landlordId]);
    $count = (int)$stmt->fetchColumn();

    if ($count >= 3) {
        $conn->prepare("UPDATE users SET account_state = 'suspended' WHERE id = :id")->execute(['id' => $landlordId]);
        $conn->prepare("UPDATE listings SET listing_status = 'suspended' WHERE user_id = :id AND listing_status <> 'archived'")->execute(['id' => $landlordId]);
        return ['action' => 'suspended', 'count' => $count];
    }

    if ($count >= 2) {
        $conn->prepare("UPDATE users SET account_state = 'identity_review_required' WHERE id = :id")->execute(['id' => $landlordId]);
        return ['action' => 'identity_review_required', 'count' => $count];
    }

    return ['action' => 'monitor', 'count' => $count];
}
