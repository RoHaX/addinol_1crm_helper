-- Middleware schema (MySQL)
-- Generated: 2026-02-05

CREATE TABLE IF NOT EXISTS mw_tracked_mail (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	mailbox VARCHAR(255) NOT NULL,
	uid VARCHAR(128) NOT NULL,
	message_id VARCHAR(255) NULL,
	message_hash CHAR(64) NOT NULL,
	date DATETIME NULL,
	from_addr VARCHAR(255) NULL,
	subject VARCHAR(512) NULL,
	status ENUM('new','queued','pending_import','imported','done','error','ignored') NOT NULL DEFAULT 'new',
	crm_email_id CHAR(36) NULL,
	last_error TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uniq_mailbox_uid (mailbox, uid),
	UNIQUE KEY uniq_message_hash (message_hash),
	KEY idx_status (status),
	KEY idx_crm_email_id (crm_email_id),
	KEY idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS mw_actions_queue (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	tracked_mail_id BIGINT UNSIGNED NOT NULL,
	action_type VARCHAR(64) NOT NULL,
	idempotency_key VARCHAR(255) NOT NULL,
	payload_json JSON NOT NULL,
	status ENUM('pending','running','done','error','retry') NOT NULL DEFAULT 'pending',
	attempts INT UNSIGNED NOT NULL DEFAULT 0,
	next_run_at DATETIME NULL,
	last_error TEXT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	UNIQUE KEY uniq_idempotency_key (idempotency_key),
	KEY idx_tracked_mail (tracked_mail_id),
	KEY idx_status_next_run (status, next_run_at),
	CONSTRAINT fk_actions_tracked_mail
		FOREIGN KEY (tracked_mail_id) REFERENCES mw_tracked_mail(id)
		ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS mw_run_log (
	id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	run_id CHAR(36) NOT NULL,
	type ENUM('poll','worker') NOT NULL,
	started_at DATETIME NOT NULL,
	ended_at DATETIME NULL,
	stats_json JSON NULL,
	last_error TEXT NULL,
	PRIMARY KEY (id),
	KEY idx_type_started (type, started_at),
	UNIQUE KEY uniq_run_id (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Existing table in this project (created separately): mw_addinol_refs
-- Ensure Dachser tracking columns exist.
ALTER TABLE mw_addinol_refs
	ADD COLUMN IF NOT EXISTS dachser_status VARCHAR(191) NULL AFTER at_order_no,
	ADD COLUMN IF NOT EXISTS dachser_status_ts DATETIME NULL AFTER dachser_status,
	ADD COLUMN IF NOT EXISTS dachser_via VARCHAR(255) NULL AFTER dachser_status_ts,
	ADD COLUMN IF NOT EXISTS dachser_info VARCHAR(255) NULL AFTER dachser_via,
	ADD COLUMN IF NOT EXISTS dachser_last_checked_at DATETIME NULL AFTER dachser_info;
