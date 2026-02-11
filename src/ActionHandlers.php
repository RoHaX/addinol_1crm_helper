<?php

class ActionHandlers
{
	private $db;
	private $logger;

	public function __construct($db, $logger)
	{
		$this->db = $db;
		$this->logger = $logger;
	}

	public function handle($trackedId, $actionType, $payload)
	{
		if ($this->isAlreadyDone($trackedId, $actionType)) {
			return ['ok' => true, 'idempotent' => true];
		}

		switch ($actionType) {
			case 'CREATE_ORDER_CONFIRMATION':
				return $this->stub('CREATE_ORDER_CONFIRMATION', $trackedId, $payload);
			case 'EMAIL_SUPPLIER':
				return $this->stub('EMAIL_SUPPLIER', $trackedId, $payload);
			case 'CREATE_TASK':
				return $this->stub('CREATE_TASK', $trackedId, $payload);
			default:
				return ['ok' => false, 'error' => 'unknown_action'];
		}
	}

	private function isAlreadyDone($trackedId, $actionType)
	{
		$stmt = $this->db->prepare('SELECT id FROM mw_actions_queue WHERE tracked_mail_id = ? AND action_type = ? AND status = "DONE" LIMIT 1');
		$stmt->bind_param('is', $trackedId, $actionType);
		$stmt->execute();
		$res = $stmt->get_result();
		return (bool)$res->fetch_assoc();
	}

	private function stub($actionType, $trackedId, $payload)
	{
		$this->logger->info('action stub', ['action' => $actionType, 'tracked_mail_id' => $trackedId]);
		return ['ok' => true];
	}
}
