<?php
require_once __DIR__ . '/../governance/listing_governance.php';

function listing_publicly_visible(array $listing): bool {
    return pick_listing_is_public($listing);
}

function verified_badge_visible(array $listing): bool {
    return pick_listing_badge_visible($listing);
}

function listing_complete(array $listing): bool {
    return pick_listing_is_complete($listing);
}
