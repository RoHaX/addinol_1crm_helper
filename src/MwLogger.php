<?php

class MwLogger
{
	private $dir;

	public function __construct($dir)
	{
		$this->dir = rtrim($dir, '/');
	}

	public function log($level, $message, $context = [])
	{
		$line = [
			'ts' => date('c'),
			'level' => $level,
			'message' => $message,
			'context' => $context
		];
		$path = $this->dir . '/mw-' . date('Y-m-d') . '.log';
		file_put_contents($path, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
	}

	public function info($message, $context = [])
	{
		$this->log('info', $message, $context);
	}

	public function error($message, $context = [])
	{
		$this->log('error', $message, $context);
	}
}
