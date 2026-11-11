# Shipping

Physical products can be shipped with **any carrier**. Carriers are drivers, like payment providers: an admin
connects and chooses them at runtime (Admin → Shipping), with no deploy.

## How an order ships

1. A product marked **Physical product** (Products → edit) asks the customer for a delivery address when they order.
   The address is checked (the mobile number against the store's country) and stored on the order.
2. After the order is **paid**, staff (the assigned partner's manager or technician, the store owner, or an admin)
   book a shipment from the order or the partner's Jobs page.
3. The customer sees the carrier, tracking number, a tracking link and a timeline on their order, and is notified
   when it ships, is out for delivery, delivered, or fails.

Privacy: the delivery address is only returned to the customer and the staff handling that parcel. It is never in
order lists, partner request screens, or the public site.

## Providers

| Provider | Use it when |
|---|---|
| **Manual (any carrier)** | Works today with any carrier. Staff book with the carrier themselves and enter the carrier name and tracking number. Set a tracking link template such as `https://track.example.com/?n={tracking}`. Staff move the status along by hand. |
| **Custom carrier (HTTP API)** | The carrier has a JSON/form HTTP API (SMSA, Aramex, Bosta, J&T, DHL…). In the admin page describe: the booking URL and body template, where the tracking number is in the reply, and optionally price quotes, a tracking lookup URL and a signed webhook. |
| **Demo carrier** | Try the flow without real parcels. Not for production. |

Carriers that need OAuth, SOAP/XML or a multi-step booking need their own driver: add a class implementing
`App\Services\Shipping\ShippingProvider` in `app/Services/Shipping/Providers` and register it in `AppServiceProvider`.
Its fields appear in the admin page automatically.

### Connecting a carrier with the custom driver

1. Admin → Shipping → Custom carrier → Settings.
2. Fill the auth header/value (stored encrypted), the **booking URL** (https, public address) and **body template**.
   Placeholders: `{order_id} {declared_value} {currency} {recipient_name} {phone} {address_line1} {address_line2} {city}
   {region} {postal_code} {country} {service} {weight_kg} {origin_*}`.
3. Set where the **tracking number** is in the reply (e.g. `data.tracking_number`). This is the only required path.
4. Optional: tracking lookup (`track_url` with `{tracking}`), status words (`DLV=delivered, OFD=out_for_delivery`),
   and the webhook: give the carrier `https://<api>/api/webhooks/shipping/http`, then enter the signing secret and paths.
5. Book a real test parcel before relying on it. Then **Make default**, or just pick it per shipment.

## Safety

- Carrier addresses go through the same protection as payment gateways: https only, no private or internal networks,
  and the connection is pinned to the checked address.
- Webhooks must carry a valid HMAC signature. Repeated deliveries are stored once, and a late "in transit" can never
  undo "out for delivery" or "delivered".
- Links from a carrier reply (tracking, label) are kept only if they are https.
- Credentials are encrypted, never shown again, and never written to the audit log.
