<?php

function mw_cfg_env(string $key, string $default = ''): string
{
	$val = getenv($key);
	if ($val === false || $val === '') {
		return $default;
	}
	return (string)$val;
}

define('MW_IMAP_HOST', mw_cfg_env('MW_IMAP_HOST', 'server.haselsberger.at'));
define('MW_IMAP_PORT', (int)mw_cfg_env('MW_IMAP_PORT', '993'));
define('MW_IMAP_USER', mw_cfg_env('MW_IMAP_USER', ''));
define('MW_IMAP_PASS', mw_cfg_env('MW_IMAP_PASS', ''));
define('MW_IMAP_FLAGS', mw_cfg_env('MW_IMAP_FLAGS', '/imap/ssl'));
define('MW_IMAP_MAILBOXES', mw_cfg_env('MW_IMAP_MAILBOXES', 'INBOX'));
define('MW_ACTION_KEY', mw_cfg_env('MW_ACTION_KEY', ''));

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
