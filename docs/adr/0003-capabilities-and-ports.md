# ADR 0003: Capabilities and ports

**Status:** accepted. **Decision:** the business depends on capabilities it defines (take payment, ship parcel, send message, store file), never on a vendor.
A provider is an adapter selected by configuration per tenant. **Rule:** no domain code, event name, table or setting may contain a vendor name
(vendors appear only in adapters and configuration data). Payments and shipping already follow this; M3 generalises it.
