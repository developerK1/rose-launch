<?php
function pick_allowed_image_extension(string $mimeType): ?string {
    return match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
}

function pick_storage_root(): string {
    $root = realpath(__DIR__ . '/../../storage/listings');
    if ($root === false) {
        $root = __DIR__ . '/../../storage/listings';
    }
    return $root;
}

function pick_archive_listing_images(PDO $conn, int $listingId): void {
    $stmt = $conn->prepare("
        UPDATE listing_images
        SET archived_at = NOW(),
            is_cover = 0
        WHERE listing_id = :listing_id
          AND archived_at IS NULL
    ");
    $stmt->execute(['listing_id' => $listingId]);
}

function store_listing_images(PDO $conn, int $listingId, array $files, string $uploadRoot, int $coverIndex = 0, bool $archiveExisting = false): array {
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    if ($archiveExisting) {
        pick_archive_listing_images($conn, $listingId);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $saved = [];
    $uploadRoot = rtrim($uploadRoot, DIRECTORY_SEPARATOR);
    $listingDir = $uploadRoot . DIRECTORY_SEPARATOR . $listingId;

    if (!is_dir($listingDir) && !mkdir($listingDir, 0775, true) && !is_dir($listingDir)) {
        throw new RuntimeException('Unable to create listing upload directory.');
    }

    $acceptedCount = 0;
    foreach ($files['name'] as $index => $originalName) {
        if ($acceptedCount >= 3) {
            break;
        }

        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $files['tmp_name'][$index] ?? '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            continue;
        }

        if (($files['size'][$index] ?? 0) > 5 * 1024 * 1024) {
            continue;
        }

        [$width, $height] = @getimagesize($tmpName) ?: [0, 0];
        if ($width <= 0 || $height <= 0) {
            continue;
        }

        $mime = $finfo->file($tmpName);
        $ext = pick_allowed_image_extension($mime ?: '');
        if ($ext === null) {
            continue;
        }

        $filename = 'img_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $listingDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            continue;
        }

        $relativePath = 'storage/listings/' . $listingId . '/' . $filename;

        $stmt = $conn->prepare(<<<SQL
            INSERT INTO listing_images (
                listing_id,
                file_path,
                is_cover,
                sort_order,
                archived_at,
                created_at
            ) VALUES (
                :listing_id,
                :file_path,
                :is_cover,
                :sort_order,
                NULL,
                NOW()
            )
        SQL);
        $stmt->execute([
            'listing_id' => $listingId,
            'file_path' => $relativePath,
            'is_cover' => ($acceptedCount === (int)$coverIndex) ? 1 : 0,
            'sort_order' => $acceptedCount,
        ]);

        $saved[] = $relativePath;
        $acceptedCount++;
    }

    if ($saved !== []) {
        $stmt = $conn->prepare(<<<SQL
            UPDATE listing_images
            SET is_cover = CASE WHEN sort_order = :cover_sort AND archived_at IS NULL THEN 1 ELSE 0 END
            WHERE listing_id = :listing_id
        SQL);
        $stmt->execute([
            'cover_sort' => $coverIndex,
            'listing_id' => $listingId,
        ]);
    }

    return $saved;
}

function get_listing_cover_image(PDO $conn, int $listingId): ?array {
    $stmt = $conn->prepare(<<<SQL
        SELECT file_path
        FROM listing_images
        WHERE listing_id = :listing_id
          AND archived_at IS NULL
        ORDER BY is_cover DESC, sort_order ASC, id ASC
        LIMIT 1
    SQL);
    $stmt->execute(['listing_id' => $listingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pick_storage_relative_to_public(string $relativePath): string {
    return 'media.php?path=' . rawurlencode($relativePath);
}
