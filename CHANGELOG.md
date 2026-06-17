# Changelog

All notable changes to `webrek/laravel-outbox` are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-06-16

### Added

- `outbox:retry` command to reset discarded (`failed`) messages back to
  `pending`, by id or `--all`, so they can be reprocessed after the downstream
  is fixed.
- Optional `retry.jitter` (0–1) that spreads retry delays at random, so a burst
  of messages that failed together does not stampede the downstream when they
  all become due at once.

## [1.0.0] - 2026-06-16

### Added

- `Outbox::publish()` to stage a message in the outbox inside your own database
  transaction, so it commits atomically with your business write.
- A relay (`OutboxRelay`) that claims ready messages with row locks — safe to run
  across multiple workers — and delivers them through a `Publisher`.
- Exponential backoff with a ceiling, a configurable attempt budget, and
  automatic reclaiming of messages left "processing" by a crashed worker.
- `outbox:work` (continuous or `--once`) and `outbox:prune` artisan commands.
- A pluggable `Publisher` contract with a default `EventPublisher` that emits an
  `OutboxMessageReady` event per message.
- Lifecycle events: `OutboxMessagePublished`, `OutboxMessageFailed` and
  `OutboxMessageDiscarded`.
- Publishable config and migration; supports Laravel 12 and 13 on PHP 8.2+.

[Unreleased]: https://github.com/webrek/laravel-outbox/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/webrek/laravel-outbox/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/webrek/laravel-outbox/releases/tag/v1.0.0
