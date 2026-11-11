# ADR 0004: Zero core modification

**Status:** accepted. **Decision:** new behaviour arrives as configuration, a module, a plugin, an integration or an extension. When it cannot, the fix is a new
extension point in core (reviewed once), never a product-specific branch. **Enforcement:** `tests/Architecture` fails the build if code under `app/Core` imports
a product, a model of a product, or a vendor SDK, and if the second-product fixture needs a core change to work.
