<?php
/**
 * Owere & Associates — SMTP configuration (Zoho Mail Lite)
 *
 * Mailbox routing per the agent blueprint:
 *   director@owereassociates.com — Managing Director & executive partnerships
 *   info@owereassociates.com     — General inquiry inbox & web booking alerts
 *   finance@owereassociates.com  — Client billing, tax invoicing & retainers
 *
 * Web consultation bookings are routed to the general inbox (info@).
 *
 * NOTE: The same file works with any provider (Zoho, Google Workspace, etc.).
 * The panel at /admin → Settings → SMTP Email Settings lets a non-technical
 * user change host/port/user/pass without touching code.
 *
 * Zoho Mail: use an "App-Specific Password" (Zoho Accounts → Security →
 * App Passwords). Google Workspace also requires an App Password when
 * 2-Step Verification is enabled.
 */

declare(strict_types=1);

define('SMTP_HOST',     'smtp.zoho.com');
define('SMTP_PORT',     465);            // 465 = implicit TLS, 587 = STARTTLS
define('SMTP_USER',     '');             // e.g. info@owereassociates.com
define('SMTP_PASS',     '');             // App-specific password (not the account password)
define('MAIL_FROM',     'info@owereassociates.com');
define('MAIL_FROM_NAME','Owere & Associates');

/**
 * Recipient(s) for a booking/inquiry notification.
 * All web consultation alerts go to the general inbox, configurable from
 * /admin settings (notification_email).
 */
function smtp_route_recipient(string $service): array
{
    return [get_setting('notification_email', 'info@owereassociates.com')];
}
