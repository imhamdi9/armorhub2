# Standalone UI screens

Presentational Tailwind components with a preview page each, at `/ui/<name>` (noindex). They draw a screen and call the functions you pass in; none of them is wired to ArmorHub's API, and all sample names, figures and legal wording are fictional.

Components live in `apps/web/src/components/auth` (sign-in and onboarding screens) and `apps/web/src/components/layout` (everything else).

| Preview | Component |
|---|---|
| passkey-sign-in, invitation, oauth-consent, checkout-identity | `auth/*` |
| workspace-branding, setup-complete, welcome-modal, notification-prefs, invite-team, role-selection, product-tour, checklist | onboarding steps |
| international-header, compliance-footer, help-centre, dashboard-shell, inbox, team-admin, analytics, inventory, activity-feed, webhook-inspector | app and marketing surfaces |
| case-studies, social-wall | proof blocks |
| bug-report, callback-request, file-upload, cookie-consent, toasts | forms and feedback |

Each was checked in a real browser at desktop and 390px width: its interactions, horizontal overflow and a 44px minimum for primary controls.

- `/ui/shop-storefront`: "Shop"-style storefront theme preview (white canvas, 28px image cards, pill search with one accent colour, icon rail). Stand-in tiles replace product photos; not yet wired to the real storefront.
- `/ui/shop-home`: full Shop-style ArmorHub home (shopping first, calmer platform pitch after), same stand-in tiles.
