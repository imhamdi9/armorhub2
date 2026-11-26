# ADR 0001: Modular monolith first

**Status:** accepted. **Context:** the platform must outlive its first product, but a team of this size cannot operate many services.
**Decision:** one deployable, with enforced internal boundaries (Core / capability ports / products). Boundaries are checked by tests, so they hold
without network walls. **Consequences:** cheap to run and change now; any module can later become a service because it already talks only through events and ports.
**Revisit when:** a module needs independent scaling or a different release cadence.
