<?php

require_once __DIR__ . '/../middleware/config.php';
require_once __DIR__ . '/../src/MwDb.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_SERVER['HTTP_X_ACTION_KEY'] ?? '';
if ($key === '' || $key !== MW_ACTION_KEY) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'unauthorized']);
	exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'invalid_json']);
	exit;
}

$trackedId = (int)($data['tracked_mail_id'] ?? 0);
$actionType = $data['action_type'] ?? '';
$allowed = ['CREATE_ORDER_CONFIRMATION', 'EMAIL_SUPPLIER', 'CREATE_TASK'];
if ($trackedId <= 0 || !in_array($actionType, $allowed, true)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'invalid_params']);
	exit;
}

$db = MwDb::getMysqli();
if (!$db) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'db_missing']);
	exit;
}
@$db->set_charset('utf8');

$idempotencyKey = $trackedId . ':' . $actionType;

$stmt = $db->prepare('SELECT id FROM mw_actions_queue WHERE idempotency_key = ? LIMIT 1');
$stmt->bind_param('s', $idempotencyKey);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
	echo json_encode(['ok' => true, 'queued_id' => (int)$row['id'], 'idempotent' => true]);
	exit;
}

$payloadJson = json_encode(['tracked_mail_id' => $trackedId, 'action_type' => $actionType]);
$insert = $db->prepare('INSERT INTO mw_actions_queue (tracked_mail_id, action_type, idempotency_key, payload_json, status, attempts, next_run_at, created_at, updated_at) VALUES (?,?,?,?,"pending",0,NULL,NOW(),NOW())');
$insert->bind_param('isss', $trackedId, $actionType, $idempotencyKey, $payloadJson);
if (!$insert->execute()) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'insert_failed']);
	exit;
}

$queuedId = $db->insert_id;
echo json_encode(['ok' => true, 'queued_id' => (int)$queuedId]);
