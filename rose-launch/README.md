# PickMzansi MVP v2.5

Governance-hardened room marketplace MVP.

## Key features
- Manual WhatsApp-based identity review
- Listing moderation and lifecycle control
- Notification engine
- Support / appeal tickets
- Admin audit logs
- Image hardening and historical retention
- Location alias handling
- Internal trust scoring
- Incident governance
- Grace-period expiry flow

## Setup notes
1. Import the database migration files in order.
2. Ensure `storage/` is writable by the web server.
3. Configure database credentials in `config/database.php` or via environment variables.
4. Run the cron scripts daily:
   - `cron/listing_lifecycle.php`
   - `cron/expire_listings.php`
5. Route public access to `home/index.php` if needed.

## Manual verification flow
Landlords submit property walkthroughs via the official WhatsApp Business channel.
Admins review manually and mark identity review completed or request more proof.
