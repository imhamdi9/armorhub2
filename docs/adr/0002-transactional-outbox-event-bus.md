# ADR 0002: Transactional outbox, broker-agnostic

**Status:** accepted. **Decision:** `EventBus::publish` writes the event and its planned deliveries in the caller's database transaction, then dispatches
after commit. Delivery is per subscriber, idempotent, retried with backoff, dead-lettered after N attempts, and replayable. Transport is a port.
**Why:** an event is never lost if the business change commits, and never emitted if it rolls back. No broker is needed on free hosting.
**Consequences:** at-least-once delivery, so subscribers must be idempotent; ordering is best-effort per aggregate. A broker adapter can replace the database transport later.
