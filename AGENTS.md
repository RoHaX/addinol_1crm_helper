# Agent Rules

## Architecture Overview

- Mailboard UI: `middleware/mailboard.php` shows `mw_tracked_mail` with filters and action buttons.
- Poller: `bin/poll.php` reads IMAP, hashes messages, categorizes, and tracks/imports into 1CRM.
- Action endpoint: `middleware/action.php` queues actions in `mw_actions_queue`.
- Worker: `bin/worker.php` picks due jobs and runs handlers in `src/ActionHandlers.php`.
- Queue: `mw_actions_queue` stores actions with idempotency and retry metadata.

## Coding Conventions

- UI uses Bootstrap + DataTables (see `bilanz.php` for layout/style).
- Database access must use `db.inc.php` (no new DB config files).
- Keep changes minimal and consistent with existing PHP style.

## Idempotency Rules

- `mw_tracked_mail`: unique (`mailbox`,`uid`) and `message_hash`.
- `mw_actions_queue`: unique `idempotency_key = tracked_mail_id + ':' + action_type`.
- Worker must check prior `DONE` for same `tracked_mail_id + action_type` before executing.

## Logging Format

- JSON lines written to `logs/mw-YYYY-MM-DD.log`.
- Fields: `ts`, `level`, `message`, `context`.

## Adding New Actions

- Add action type in `middleware/action.php` allowlist.
- Implement handler in `src/ActionHandlers.php`.
- Keep handlers idempotent and safe to retry.

## Run Locally / Cron Examples

- Poller:
  - `php bin/poll.php`
  - `FORCE_RECHECK=1 php bin/poll.php`
- Worker:
  - `php bin/worker.php`

Cron examples:

```
*/5 * * * * php /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/bin/poll.php
*/2 * * * * php /var/www/vhosts/addinol-lubeoil.at/httpdocs/crm/roman/bin/worker.php
```
