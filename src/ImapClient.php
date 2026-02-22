<?php

class ImapClient
{
	private $host;
	private $port;
	private $user;
	private $pass;
	private $flags;
	private $stream;
	private $mailbox;

	public function __construct($host, $port, $user, $pass, $flags = '/imap/ssl')
	{
		$this->host = $host;
		$this->port = $port;
		$this->user = $user;
		$this->pass = $pass;
		$this->flags = $flags;
	}

	public function connect($mailbox = 'INBOX')
	{
		$this->mailbox = $mailbox;
		$path = sprintf('{%s:%d%s}%s', $this->host, $this->port, $this->flags, $mailbox);
		$this->stream = @imap_open($path, $this->user, $this->pass);
		if (!$this->stream) {
			throw new RuntimeException(imap_last_error() ?: 'IMAP connect failed');
		}
		return $this;
	}

	public function disconnect()
	{
		if ($this->stream) {
			imap_close($this->stream);
			$this->stream = null;
		}
	}

	public function search($criteria = 'ALL')
	{
		$this->ensureConnected();
		$uids = imap_search($this->stream, $criteria, SE_UID) ?: [];
		return $uids;
	}

	public function fetchHeaders($uid)
	{
		$this->ensureConnected();
		$raw = @imap_fetchheader($this->stream, $uid, FT_UID);
		if (!is_string($raw)) {
			$raw = '';
		}
		$header = @imap_rfc822_parse_headers($raw);
		if (!is_object($header)) {
			$header = (object)[];
		}
		$fallback = $this->parseHeaderFallback($raw);
		return [
			'raw' => $raw,
			'subject' => isset($header->subject) ? imap_utf8($header->subject) : ($fallback['subject'] ?? null),
			'from' => isset($header->from) ? $this->formatAddress($header->from) : ($fallback['from'] ?? null),
			'message_id' => $header->message_id ?? ($fallback['message_id'] ?? null),
			'date' => $header->date ?? ($fallback['date'] ?? null),
		];
	}

	public function fetchBody($uid)
	{
		$this->ensureConnected();
		$structure = imap_fetchstructure($this->stream, $uid, FT_UID);
		return $this->extractBody($uid, $structure);
	}

	public function listAttachments($uid)
	{
		$this->ensureConnected();
		$structure = imap_fetchstructure($this->stream, $uid, FT_UID);
		$attachments = [];
		if (!empty($structure->parts)) {
			foreach ($structure->parts as $index => $part) {
				$filename = $this->getFilename($part);
				if ($filename) {
					$attachments[] = [
						'part' => $index + 1,
						'part_number' => (string)($index + 1),
						'filename' => $filename,
						'encoding' => $part->encoding ?? null,
						'size' => $part->bytes ?? null,
						'mime' => $this->getMimeType($part)
					];
				}
			}
		}
		return $attachments;
	}

	public function fetchRaw($uid)
	{
		$this->ensureConnected();
		$header = imap_fetchheader($this->stream, $uid, FT_UID);
		$body = imap_body($this->stream, $uid, FT_UID);
		return $header . "\r\n" . $body;
	}

	public function fetchAttachmentContent($uid, $partNumber, $encoding)
	{
		$this->ensureConnected();
		$data = imap_fetchbody($this->stream, $uid, $partNumber, FT_UID);
		return $this->decodePart($data, $encoding);
	}

	private function extractBody($uid, $structure, $partIndex = null)
	{
		if (!$structure) {
			return '';
		}

		if (!empty($structure->parts)) {
			foreach ($structure->parts as $index => $part) {
				$partNumber = $partIndex ? $partIndex . '.' . ($index + 1) : (string)($index + 1);
				if ($this->isTextPart($part)) {
					return $this->decodePart(imap_fetchbody($this->stream, $uid, $partNumber, FT_UID), $part->encoding);
				}
				if (!empty($part->parts)) {
					$body = $this->extractBody($uid, $part, $partNumber);
					if ($body !== '') {
						return $body;
					}
				}
			}
		} else {
			if ($this->isTextPart($structure)) {
				return $this->decodePart(imap_body($this->stream, $uid, FT_UID), $structure->encoding);
			}
		}

		return '';
	}

	private function isTextPart($part)
	{
		return isset($part->type) && $part->type === 0;
	}

	private function decodePart($data, $encoding)
	{
		switch ((int)$encoding) {
			case 3:
				return base64_decode($data);
			case 4:
				return quoted_printable_decode($data);
			default:
				return $data;
		}
	}

	private function getFilename($part)
	{
		$filename = null;
		if (!empty($part->dparameters)) {
			foreach ($part->dparameters as $param) {
				if (strtolower($param->attribute) === 'filename') {
					$filename = $param->value;
				}
			}
		}
		if (!$filename && !empty($part->parameters)) {
			foreach ($part->parameters as $param) {
				if (strtolower($param->attribute) === 'name') {
					$filename = $param->value;
				}
			}
		}
		return $filename ? imap_utf8($filename) : null;
	}

	private function getMimeType($part)
	{
		$types = [
			'text',
			'multipart',
			'message',
			'application',
			'audio',
			'image',
			'video',
			'other'
		];
		$type = isset($part->type) ? $types[(int)$part->type] : 'application';
		$subtype = isset($part->subtype) ? strtolower($part->subtype) : 'octet-stream';
		return $type . '/' . $subtype;
	}

	private function formatAddress($list)
	{
		$out = [];
		foreach ($list as $addr) {
			$mailbox = $addr->mailbox ?? '';
			$host = $addr->host ?? '';
			$out[] = $mailbox && $host ? ($mailbox . '@' . $host) : trim($mailbox . '@' . $host, '@');
		}
		return implode(', ', $out);
	}

	private function ensureConnected()
	{
		if (!$this->stream) {
			throw new RuntimeException('IMAP not connected');
		}
	}

	private function parseHeaderFallback($raw)
	{
		$out = [
			'subject' => null,
			'from' => null,
			'message_id' => null,
			'date' => null,
		];
		if (!is_string($raw) || $raw === '') {
			return $out;
		}
		if (preg_match('/^Subject:\s*(.+)$/mi', $raw, $m)) {
			$out['subject'] = trim($m[1]);
		}
		if (preg_match('/^From:\s*(.+)$/mi', $raw, $m)) {
			$out['from'] = trim($m[1]);
		}
		if (preg_match('/^Message-ID:\s*(.+)$/mi', $raw, $m)) {
			$out['message_id'] = trim($m[1]);
		}
		if (preg_match('/^Date:\s*(.+)$/mi', $raw, $m)) {
			$out['date'] = trim($m[1]);
		}
		return $out;
	}
}
