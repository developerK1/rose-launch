<?php
function pick_normalize_location_name(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\s\-]/', '', $value ?? '');
    $value = preg_replace('/\s+/', ' ', $value ?? '');
    return trim((string)$value);
}

function pick_ensure_province_id(PDO $conn, string $provinceName): int {
    $provinceName = trim($provinceName);
    if ($provinceName === '') {
        return 0;
    }

    $normalized = pick_normalize_location_name($provinceName);

    $stmt = $conn->prepare("
        SELECT province_id
        FROM location_aliases
        WHERE normalized_alias = :alias
        LIMIT 1
    ");
    $stmt->execute(['alias' => $normalized]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $stmt = $conn->prepare("SELECT id FROM provinces WHERE LOWER(name) = :name OR name = :raw LIMIT 1");
    $stmt->execute(['name' => $normalized, 'raw' => $provinceName]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $provinceId = (int)$id;
    } else {
        $stmt = $conn->prepare("INSERT INTO provinces (name) VALUES (:name)");
        $stmt->execute(['name' => $provinceName]);
        $provinceId = (int)$conn->lastInsertId();
    }

    $stmt = $conn->prepare("INSERT INTO location_aliases (province_id, town_id, alias_name, normalized_alias, alias_type, created_at) VALUES (:province_id, NULL, :alias_name, :normalized_alias, 'province', NOW())");
    $stmt->execute([
        'province_id' => $provinceId,
        'alias_name' => $provinceName,
        'normalized_alias' => $normalized,
    ]);

    return $provinceId;
}

function pick_ensure_town_id(PDO $conn, string $townName, int $provinceId = 0): int {
    $townName = trim($townName);
    if ($townName === '') {
        return 0;
    }

    $normalized = pick_normalize_location_name($townName);

    $stmt = $conn->prepare("
        SELECT town_id
        FROM location_aliases
        WHERE normalized_alias = :alias
        LIMIT 1
    ");
    $stmt->execute(['alias' => $normalized]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $sql = "SELECT id FROM towns WHERE LOWER(name) = :name OR name = :raw";
    $params = ['name' => $normalized, 'raw' => $townName];
    if ($provinceId > 0) {
        $sql .= " AND province_id = :province_id";
        $params['province_id'] = $provinceId;
    }
    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    if ($id) {
        $townId = (int)$id;
    } else {
        $stmt = $conn->prepare("INSERT INTO towns (province_id, name) VALUES (:province_id, :name)");
        $stmt->execute([
            'province_id' => $provinceId ?: null,
            'name' => $townName,
        ]);
        $townId = (int)$conn->lastInsertId();
    }

    $stmt = $conn->prepare("INSERT INTO location_aliases (province_id, town_id, alias_name, normalized_alias, alias_type, created_at) VALUES (:province_id, :town_id, :alias_name, :normalized_alias, 'town', NOW())");
    $stmt->execute([
        'province_id' => $provinceId ?: null,
        'town_id' => $townId,
        'alias_name' => $townName,
        'normalized_alias' => $normalized,
    ]);

    return $townId;
}

function pick_location_alias_search(PDO $conn, string $term): array {
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare(<<<SQL
        SELECT 'province' AS type, id, name
        FROM provinces
        WHERE name LIKE :q
        UNION ALL
        SELECT 'town' AS type, id, name
        FROM towns
        WHERE name LIKE :q
        UNION ALL
        SELECT alias_type AS type,
               COALESCE(province_id, town_id) AS id,
               alias_name AS name
        FROM location_aliases
        WHERE alias_name LIKE :q
        LIMIT 10
    SQL);
    $stmt->execute(['q' => $term . '%']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
