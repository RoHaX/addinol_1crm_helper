<?php

define('MW_IMAP_HOST', 'server.haselsberger.at');
define('MW_IMAP_PORT', 993);
define('MW_IMAP_USER', 'h.egger@addinol-lubeoil.at');
define('MW_IMAP_PASS', 'REDACTED_IMAP_PASSWORD');
define('MW_IMAP_FLAGS', '/imap/ssl');
define('MW_IMAP_MAILBOXES', 'INBOX');
define('MW_ACTION_KEY', 'REDACTED_ACTION_KEY');

// Optional Dachser shipment-status API config (used by middleware/dachser_status.php)
define('MW_DACHSER_API_STATUS_URL', 'https://api-gateway.dachser.com');
// define('MW_DACHSER_API_REFERENCE_PARAM', 'referenceNumber');
// define('MW_DACHSER_API_KEY', '...');
// define('MW_DACHSER_API_BEARER', '...');
// define('MW_DACHSER_API_TIMEOUT', 20);

// Optional Dachser Tracking URL base for quick-open from Lieferstatus.
// Must end with "search=" so reference is appended automatically.
// Example:
// define('MW_DACHSER_TRACKING_URL_BASE', 'https://elogistics.dachser.com/shp2s/?sesid=...&javalocale=de_DE&search=');
// define('MW_DACHSER_TRACKING_TIMEOUT', 20);
