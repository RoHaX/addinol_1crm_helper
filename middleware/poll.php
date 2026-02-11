<?php

require_once __DIR__ . '/../middleware/config.php';

header('Content-Type: application/json; charset=utf-8');

$key = $_SERVER['HTTP_X_ACTION_KEY'] ?? '';
if ($key === '' || $key !== MW_ACTION_KEY) {
	http_response_code(401);
	echo json_encode(['ok' => false, 'error' => 'unauthorized']);
	exit;
}

require_once __DIR__ . '/../bin/poll.php';

echo json_encode(['ok' => true]);
