<?php

class CrmClient
{
	private $baseUrl;
	private $token;

	public function __construct($baseUrl = null, $token = null)
	{
		$this->baseUrl = rtrim($baseUrl ?: $this->env('CRM_BASE_URL', defined('CRM_BASE_URL') ? CRM_BASE_URL : ''), '/');
		$this->token = $token ?: $this->env('CRM_AUTH_TOKEN', defined('CRM_AUTH_TOKEN') ? CRM_AUTH_TOKEN : '');
		if ($this->baseUrl === '' || $this->token === '') {
			throw new RuntimeException('CRM config missing');
		}
	}

	public function get($path, $query = [])
	{
		$url = $this->buildUrl($path, $query);
		return $this->request('GET', $url);
	}

	public function post($path, $payload = [])
	{
		$url = $this->buildUrl($path);
		return $this->request('POST', $url, $payload);
	}

	public function searchEmailByMessageId($messageId)
	{
		if (!$messageId) {
			return null;
		}
		$path = defined('CRM_EMAIL_SEARCH_PATH') ? CRM_EMAIL_SEARCH_PATH : 'Emails';
		$params = ['message_id' => $messageId];
		$response = $this->get($path, $params);

		return $this->extractId($response);
	}

	public function createEmail($payload)
	{
		$path = defined('CRM_EMAIL_CREATE_PATH') ? CRM_EMAIL_CREATE_PATH : 'Emails';
		$response = $this->post($path, $payload);
		return $this->extractId($response);
	}

	public function uploadAttachment($emailId, $filename, $contentBase64, $mimeType = 'application/octet-stream')
	{
		if (!$emailId) {
			return false;
		}
		$path = defined('CRM_EMAIL_ATTACH_PATH') ? CRM_EMAIL_ATTACH_PATH : ('Emails/' . urlencode($emailId) . '/attachments');
		$payload = [
			'filename' => $filename,
			'mime_type' => $mimeType,
			'content_base64' => $contentBase64
		];
		$response = $this->post($path, $payload);
		return $this->extractId($response) ? true : false;
	}

	private function request($method, $url, $payload = null)
	{
		$headers = [
			'Authorization: Bearer ' . $this->token,
			'Accept: application/json'
		];
		$opts = [
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers)
			]
		];
		if ($payload !== null) {
			$opts['http']['header'] .= "\r\nContent-Type: application/json";
			$opts['http']['content'] = json_encode($payload);
		}
		$context = stream_context_create($opts);
		$response = @file_get_contents($url, false, $context);
		if ($response === false) {
			throw new RuntimeException('CRM request failed');
		}
		$data = json_decode($response, true);
		return $data === null ? $response : $data;
	}

	private function buildUrl($path, $query = [])
	{
		$url = $this->baseUrl . '/' . ltrim($path, '/');
		if (!empty($query)) {
			$url .= '?' . http_build_query($query);
		}
		return $url;
	}

	private function env($key, $fallback = '')
	{
		$val = getenv($key);
		return $val !== false ? $val : $fallback;
	}

	private function extractId($response)
	{
		if (is_array($response)) {
			if (isset($response['id'])) {
				return $response['id'];
			}
			if (isset($response['records'][0]['id'])) {
				return $response['records'][0]['id'];
			}
			if (isset($response[0]['id'])) {
				return $response[0]['id'];
			}
		}
		return null;
	}
}
