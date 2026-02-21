<?php
require_once __DIR__ . '/../src/MwLogger.php';
if (file_exists(__DIR__ . '/config.php')) {
	require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/../db.inc.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
	exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$reference = trim((string)($payload['reference'] ?? ''));
$purchaseOrderId = trim((string)($payload['purchase_order_id'] ?? ''));
if ($reference === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'missing_reference']);
	exit;
}

$trackingBase = trim((string)(getenv('DACHSER_TRACKING_URL_BASE')
	?: (defined('MW_DACHSER_TRACKING_URL_BASE') ? MW_DACHSER_TRACKING_URL_BASE : 'https://elogistics.dachser.com/shp2s/?javalocale=de_DE&search=')));
$timeoutSec = (int)(getenv('DACHSER_TRACKING_TIMEOUT')
	?: (defined('MW_DACHSER_TRACKING_TIMEOUT') ? MW_DACHSER_TRACKING_TIMEOUT : 20));
if ($timeoutSec < 3) {
	$timeoutSec = 3;
}
if ($timeoutSec > 60) {
	$timeoutSec = 60;
}

$requestUrl = $trackingBase . rawurlencode($reference);

function html_to_text(string $html): string {
	$html = preg_replace('~<script\b[^>]*>.*?</script>~is', ' ', $html) ?? $html;
	$html = preg_replace('~<style\b[^>]*>.*?</style>~is', ' ', $html) ?? $html;
	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace('/\s+/u', ' ', $text) ?? $text;
	return trim($text);
}

function clean_inner_text(string $html): string {
	$text = strip_tags($html);
	$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace('/\s+/u', ' ', $text) ?? $text;
	return trim($text);
}

function absolutize_url(string $baseUrl, string $maybeRelative): string {
	$maybeRelative = trim($maybeRelative);
	if ($maybeRelative === '') {
		return '';
	}
	if (preg_match('~^https?://~i', $maybeRelative)) {
		return $maybeRelative;
	}
	$base = parse_url($baseUrl);
	if (!is_array($base) || empty($base['host'])) {
		return $maybeRelative;
	}
	$scheme = $base['scheme'] ?? 'https';
	$host = $base['host'];
	$port = isset($base['port']) ? ':' . (int)$base['port'] : '';
	if (strpos($maybeRelative, '//') === 0) {
		return $scheme . ':' . $maybeRelative;
	}
	if (strpos($maybeRelative, '/') === 0) {
		return $scheme . '://' . $host . $port . $maybeRelative;
	}
	$path = $base['path'] ?? '/';
	$dir = preg_replace('~/[^/]*$~', '/', $path);
	return $scheme . '://' . $host . $port . $dir . $maybeRelative;
}

function extract_continue_url(string $html, string $currentUrl): string {
	if (preg_match('~<a[^>]+href=["\']([^"\']+)["\'][^>]*>~is', $html, $m) && !empty($m[1])) {
		return absolutize_url($currentUrl, html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
	if (preg_match('~<meta[^>]+http-equiv=["\']refresh["\'][^>]+content=["\'][^"\']*url=([^"\']+)["\']~is', $html, $m) && !empty($m[1])) {
		return absolutize_url($currentUrl, html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
	return '';
}

function curl_fetch_html(string $url, int $timeoutSec, string $cookieFile): array {
	$ch = curl_init();
	$headers = [
		'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language: de-DE,de;q=0.9,en;q=0.8',
		'User-Agent: Mozilla/5.0 (compatible; 1CRM-Lieferstatus/1.0)',
	];
	curl_setopt_array($ch, [
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 5,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_TIMEOUT => $timeoutSec,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_COOKIEJAR => $cookieFile,
		CURLOPT_COOKIEFILE => $cookieFile,
	]);
	$body = curl_exec($ch);
	$err = curl_error($ch);
	$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	return [
		'body' => $body,
		'err' => $err,
		'http_code' => $httpCode,
		'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
	];
}

function extract_status_from_html(string $html, string $text): array {
	$candidates = [];
	$trimStatus = static function (string $value): string {
		$value = trim($value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		// Cut off trailing blocks that belong to other fields.
		$value = preg_replace('/\s+\b(Via|Info|Sendung|Transport|NVE\/SSCC|Referenzen)\b.*$/iu', '', $value) ?? $value;
		return trim($value);
	};
	$addCandidate = static function (string $value) use (&$candidates): void {
		$value = trim($value);
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		if ($value === '') {
			return;
		}
		// Ignore UI-only arrows/placeholders.
		if (preg_match('/^[\-\>\.\|:]+$/', $value)) {
			return;
		}
		$candidates[] = $value;
	};

	$structuredPatterns = [
		'~(?:sendungsstatus|shipment status|status)\s*</[^>]+>\s*<[^>]+>\s*([^<]{2,120})\s*<~isu',
		'~(?:sendungsstatus|shipment status|status)\s*[:\-]\s*([^<\r\n]{2,120})~isu',
	];
	foreach ($structuredPatterns as $pattern) {
		if (preg_match($pattern, $html, $m) && !empty($m[1])) {
			$val = $trimStatus(clean_inner_text((string)$m[1]));
			$addCandidate($val);
		}
	}

	// Most reliable: status directly after "Status (timestamp)".
	if (preg_match('~Aktueller\s+Status\s+Status\s*\([^)]+\)\s*(.*?)(?=\s+\bVia\b|\s+\bInfo\b|\s+\bSendung\b|\s+\bTransport\b|\s+\bNVE/SSCC\b|\s+\bReferenzen\b|$)~iu', $text, $m) && !empty($m[1])) {
		$addCandidate($trimStatus((string)$m[1]));
	}

	// Common layout in Dachser page text:
	// "Aktueller Status Status (..timestamp..) Eingang Umschlagterminal Via Dachser ..."
	if (preg_match('~Aktueller\s+Status\s+Status\s*\([^)]+\)\s*(.*?)\s+Via\s+Dachser~iu', $text, $m) && !empty($m[1])) {
		$addCandidate($trimStatus((string)$m[1]));
	}
	if (preg_match('~Aktueller\s+Status\s+Status\s*\([^)]+\)\s*([^\n\r]{3,120})~iu', $text, $m) && !empty($m[1])) {
		$addCandidate($trimStatus((string)$m[1]));
	}

	$keywordMap = [
		'Zugestellt' => '/\b(zugestellt|delivered)\b/iu',
		'In Zustellung' => '/\b(in zustellung|out for delivery)\b/iu',
		'Unterwegs' => '/\b(unterwegs|in transit|transportiert)\b/iu',
		'Abgeholt' => '/\b(abgeholt|picked up)\b/iu',
		'Sendung erfasst' => '/\b(sendung erfasst|announced|data received|auftragsdaten erhalten)\b/iu',
	];
	foreach ($keywordMap as $label => $pattern) {
		if (preg_match($pattern, $text)) {
			$addCandidate($label);
		}
	}

	$candidates = array_values(array_unique(array_filter(array_map('trim', $candidates), static function ($v) {
		return $v !== '';
	})));

	return [
		'best' => $candidates ? $candidates[0] : '',
		'candidates' => $candidates,
	];
}

function extract_detail_value(string $text, string $label): string {
	$pattern = '~\b' . preg_quote($label, '~') . '\s+(.+?)(?=\s+(?:NVE/SSCC|Sendungs-Nummer|Auftragsdatum|PLZ Empfänger|Gewicht|Referenzen|Auftrags-Nummer|Lieferschein-Nummern|Bestell-Nummern|Status\s*\(|Via Dachser|Erfahren Sie|Impressum)\b|$)~isu';
	if (preg_match($pattern, $text, $m) && !empty($m[1])) {
		$val = trim((string)$m[1]);
		$val = preg_replace('/\s+/u', ' ', $val) ?? $val;
		return $val;
	}
	return '';
}

function extract_shipment_details(string $text): array {
	$details = [
		'status_timestamp' => '',
		'via' => '',
		'info' => '',
		'nve_sscc' => '',
		'shipment_number' => '',
		'order_date' => '',
		'recipient_postcode' => '',
		'weight' => '',
		'order_number' => '',
		'delivery_note_numbers' => '',
		'purchase_order_numbers' => '',
	];

	if (preg_match('~Status\s*\(([^)]+)\)~iu', $text, $m) && !empty($m[1])) {
		$details['status_timestamp'] = trim((string)$m[1]);
	}
	if (preg_match('~\bVia\s+Dachser\s+(.*?)(?=\s+\bInfo\b|\s+\bSendung\b|\s+\bTransport\b|$)~iu', $text, $m) && !empty($m[1])) {
		$via = trim((string)$m[1]);
		$via = preg_replace('/\s+/u', ' ', $via) ?? $via;
		$details['via'] = $via;
	}
	if (preg_match('~\bInfo\s+(.*?)(?=\s+\bSendung\b|\s+\bTransport\b|\s+\bNVE/SSCC\b|\s+\bReferenzen\b|$)~iu', $text, $m) && !empty($m[1])) {
		$info = trim((string)$m[1]);
		$info = preg_replace('/\s+/u', ' ', $info) ?? $info;
		if ($info === '---') {
			$info = '';
		}
		$details['info'] = $info;
	}

	$details['nve_sscc'] = extract_detail_value($text, 'NVE/SSCC');
	$details['shipment_number'] = extract_detail_value($text, 'Sendungs-Nummer');
	$details['order_date'] = extract_detail_value($text, 'Auftragsdatum');
	$details['recipient_postcode'] = extract_detail_value($text, 'PLZ Empfänger');
	$details['weight'] = extract_detail_value($text, 'Gewicht');
	$details['order_number'] = extract_detail_value($text, 'Auftrags-Nummer');
	$details['delivery_note_numbers'] = extract_detail_value($text, 'Lieferschein-Nummern');
	$details['purchase_order_numbers'] = extract_detail_value($text, 'Bestell-Nummern');

	return array_filter($details, static function ($v) {
		return $v !== '';
	});
}

function parse_dachser_status_datetime(string $value): ?string {
	$value = trim($value);
	if ($value === '') {
		return null;
	}
	$formats = ['d.m.Y H:i', 'd.m.Y H:i:s'];
	foreach ($formats as $fmt) {
		$dt = DateTime::createFromFormat($fmt, $value);
		if ($dt instanceof DateTime) {
			return $dt->format('Y-m-d H:i:s');
		}
	}
	return null;
}

function persist_dachser_status(?mysqli $mysqli, string $purchaseOrderId, string $reference, string $status, ?string $statusTs, string $via, string $info): array {
	if (!$mysqli) {
		return ['saved' => false, 'reason' => 'db_missing'];
	}
	if ($status === '') {
		return ['saved' => false, 'reason' => 'empty_status'];
	}

	$savedBy = '';
	$affected = 0;

	if ($purchaseOrderId !== '') {
		$sql = "UPDATE mw_addinol_refs
			SET dachser_status = ?, dachser_status_ts = ?, dachser_via = ?, dachser_info = ?, dachser_last_checked_at = NOW(), updated_at = NOW()
			WHERE sales_order_id = ?
			LIMIT 1";
		$stmt = $mysqli->prepare($sql);
		if ($stmt) {
			$stmt->bind_param('sssss', $status, $statusTs, $via, $info, $purchaseOrderId);
			if ($stmt->execute()) {
				$affected = (int)$stmt->affected_rows;
				if ($affected > 0) {
					$savedBy = 'sales_order_id';
				}
			}
			$stmt->close();
		}
	}

	if ($affected === 0 && $reference !== '') {
		$sql = "UPDATE mw_addinol_refs
			SET dachser_status = ?, dachser_status_ts = ?, dachser_via = ?, dachser_info = ?, dachser_last_checked_at = NOW(), updated_at = NOW()
			WHERE at_order_no = ?
			ORDER BY id DESC
			LIMIT 1";
		$stmt = $mysqli->prepare($sql);
		if ($stmt) {
			$stmt->bind_param('sssss', $status, $statusTs, $via, $info, $reference);
			if ($stmt->execute()) {
				$affected = (int)$stmt->affected_rows;
				if ($affected > 0) {
					$savedBy = 'at_order_no';
				}
			}
			$stmt->close();
		}
	}

	return ['saved' => $affected > 0, 'saved_by' => $savedBy];
}

$logger = new MwLogger(__DIR__ . '/../logs');
$cookieFile = tempnam(sys_get_temp_dir(), 'dachser_cookie_');
$cookiePath = is_string($cookieFile) ? $cookieFile : '';
$fetch = curl_fetch_html($requestUrl, $timeoutSec, $cookiePath);
$responseBody = $fetch['body'];
$curlErr = (string)$fetch['err'];
$httpCode = (int)$fetch['http_code'];
$effectiveUrl = (string)$fetch['effective_url'];

if ($responseBody === false) {
	$logger->error('dachser_tracking_curl_failed', [
		'reference' => $reference,
		'url' => $trackingBase,
		'error' => $curlErr,
	]);
	http_response_code(502);
	echo json_encode([
		'ok' => false,
		'error' => 'curl_failed',
		'message' => $curlErr !== '' ? $curlErr : 'request failed',
	]);
	exit;
}

$html = (string)$responseBody;
$textFirst = html_to_text($html);
$followedContinue = false;
if (stripos($textFirst, 'both JavaScript and meta-refresh are not support') !== false) {
	$continueUrl = extract_continue_url($html, $effectiveUrl !== '' ? $effectiveUrl : $requestUrl);
	if ($continueUrl !== '') {
		$fetch2 = curl_fetch_html($continueUrl, $timeoutSec, $cookiePath);
		if ($fetch2['body'] !== false) {
			$html = (string)$fetch2['body'];
			$httpCode = (int)$fetch2['http_code'];
			$effectiveUrl = (string)$fetch2['effective_url'];
			$followedContinue = true;
		}
	}
}
if ($cookiePath !== '' && is_file($cookiePath)) {
	@unlink($cookiePath);
}

$title = '';
if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m) && !empty($m[1])) {
	$title = clean_inner_text((string)$m[1]);
}
$text = html_to_text($html);
$parsedStatus = extract_status_from_html($html, $text);
$shipmentDetails = extract_shipment_details($text);
$statusTimestamp = isset($shipmentDetails['status_timestamp']) ? (string)$shipmentDetails['status_timestamp'] : '';
$statusVia = isset($shipmentDetails['via']) ? (string)$shipmentDetails['via'] : '';
$statusInfo = isset($shipmentDetails['info']) ? (string)$shipmentDetails['info'] : '';
$statusTsDb = parse_dachser_status_datetime($statusTimestamp);
$saveResult = persist_dachser_status($mysqli ?? null, $purchaseOrderId, $reference, (string)$parsedStatus['best'], $statusTsDb, $statusVia, $statusInfo);

$logger->info('dachser_tracking_request', [
	'reference' => $reference,
	'purchase_order_id' => $purchaseOrderId,
	'url' => $trackingBase,
	'http_code' => $httpCode,
	'effective_url' => $effectiveUrl,
	'followed_continue' => $followedContinue,
	'title' => $title,
	'extracted_status' => $parsedStatus['best'],
	'saved' => $saveResult['saved'] ?? false,
	'saved_by' => $saveResult['saved_by'] ?? '',
	'shipment_details_found' => !empty($shipmentDetails),
]);

$ok = $httpCode >= 200 && $httpCode < 300;
http_response_code($ok ? 200 : 502);
echo json_encode([
	'ok' => $ok,
	'http_code' => $httpCode,
	'reference' => $reference,
	'purchase_order_id' => $purchaseOrderId,
	'request_url' => $requestUrl,
	'effective_url' => $effectiveUrl,
	'followed_continue' => $followedContinue,
	'title' => $title,
	'extracted_status' => $parsedStatus['best'],
	'status_candidates' => $parsedStatus['candidates'],
	'shipment_details' => $shipmentDetails,
	'db_saved' => $saveResult['saved'] ?? false,
	'db_saved_by' => $saveResult['saved_by'] ?? '',
	'raw_text' => substr($text, 0, 3000),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
