# DB Schema (Middleware)

This documents middleware tables added to the existing `addinol_crm` database. Use existing `db.inc.php` for access.

## Table: `mw_tracked_mail`

Tracks processed mailbox messages and their lifecycle.

Fields:
- `id` BIGINT PK.
- `mailbox` Mailbox identifier.
- `uid` Provider UID for idempotency within mailbox.
- `message_id` RFC Message-ID (nullable).
- `message_hash` SHA-256 of canonical headers/body.
- `date` Message date.
- `from_addr` Parsed sender address.
- `subject` Message subject.
- `status` ENUM(`new`,`queued`,`pending_import`,`imported`,`done`,`error`,`ignored`).
- `crm_email_id` Link to CRM email record (GUID, nullable).
- `last_error` Last error text.
- `created_at`, `updated_at` Timestamps.

Indexes:
- `uniq_mailbox_uid` unique (`mailbox`, `uid`).
- `uniq_message_hash` unique (`message_hash`).
- `idx_status` (`status`).
- `idx_crm_email_id` (`crm_email_id`).
- `idx_date` (`date`).

## Table: `mw_actions_queue`

Queue for actions derived from tracked mail.

Fields:
- `id` BIGINT PK.
- `tracked_mail_id` FK -> `mw_tracked_mail.id`.
- `action_type` Action identifier.
- `idempotency_key` Unique action key.
- `payload_json` JSON payload for the action.
- `status` ENUM(`pending`,`running`,`done`,`error`,`retry`).
- `attempts` Retry count.
- `next_run_at` Schedule time for retry/next run.
- `last_error` Last error text.
- `created_at`, `updated_at` Timestamps.

Indexes:
- `uniq_idempotency_key` unique (`idempotency_key`).
- `idx_tracked_mail` (`tracked_mail_id`).
- `idx_status_next_run` (`status`, `next_run_at`).

## Table: `mw_run_log`

Execution log for poller/worker runs.

Fields:
- `id` BIGINT PK.
- `run_id` UUID for the run.
- `type` ENUM(`poll`,`worker`).
- `started_at` Start time.
- `ended_at` End time.
- `stats_json` JSON stats.
- `last_error` Last error text.

Indexes:
- `uniq_run_id` unique (`run_id`).
- `idx_type_started` (`type`, `started_at`).

## Table: `mw_addinol_refs`

Stores extracted Addinol references from PDF invoices and maps them to purchase orders.

Fields:
- `id` BIGINT PK.
- `sales_order_id` CHAR(36): linked purchase order id (historical naming kept).
- `be_order_no` VARCHAR(64): order number like `BE2026-85`.
- `at_order_no` VARCHAR(64): Addinol order/shipment ref like `AT308310` (may be empty if not yet available in PDF).
- `dachser_status` VARCHAR(191): last parsed Dachser status label (e.g. `Zugestellt`).
- `dachser_status_ts` DATETIME: status timestamp from Dachser page (parsed from `Status (dd.mm.yyyy hh:mm)`).
- `dachser_via` VARCHAR(255): parsed `Via` field from Dachser page.
- `dachser_info` VARCHAR(255): parsed `Info` field from Dachser page.
- `dachser_last_checked_at` DATETIME: when the Dachser page was last checked by middleware.
- `note_id` CHAR(36): source note (PDF attachment) id.
- `email_id` CHAR(36): source email id (nullable).
- `source_filename` VARCHAR(255): filename used for extraction.
- `extracted_at`, `updated_at`: timestamps.

Indexes / constraints:
- `uniq_sales_order` unique (`sales_order_id`).
- `uniq_note` unique (`note_id`).
- `idx_be_order_no` (`be_order_no`).
- `idx_at_order_no` (`at_order_no`).
