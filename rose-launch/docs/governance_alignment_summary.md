# PickMzansi Governance Alignment Refactor Summary

## What changed
- Centralized the public visibility rule to one source of truth.
- Standardized listing lifecycle states into moderation, listing lifecycle, and verification.
- Removed the verification gate from publishing flow.
- Added secure multi-image upload support and cover image selection.
- Added listing revision tracking for sensitive changes.
- Standardized admin logging with a single schema and helper.
- Added a reports queue for fake listings, unavailable rooms, suspicious behavior, and wrong details.
- Added a simple lifecycle expiry flow with grace-period handling.
- Updated public pages and admin pages to use the finalized governance model.

## Old logic removed
- `verification_status = 'live'` style visibility checks.
- Mixed `status` / `verification_status` moderation logic.
- Publishing gates that blocked landlords from listing without verification.
- GET-based destructive admin actions.
- Broken legacy renewal/payment-based expiry flow.
- Fragmented admin log field names (`action`, `reference_type`, `reference_id`, `description`).

## Remaining risks
- Location data is still only as clean as the underlying province/town records.
- If the hosting stack is older than the migration syntax, the `IF NOT EXISTS` column additions may need manual application.
- Media deletion/version rollback is not yet automatic.
- Duplicate detection is still rule-based rather than deep semantic matching.
- WhatsApp verification remains manual by design.

## Operational outcome
The platform now behaves like a trust-first marketplace instead of a loosely connected listing app. Public inventory depends on approval plus active/grace status, landlord publishing is free, and admin governance now has a traceable audit trail.
