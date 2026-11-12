import { WebhookInspector, type Delivery } from "@/components/layout/webhook-inspector";

// Sample deliveries for previewing the layout only.
const DELIVERIES: Delivery[] = [
  { id: "d1", event: "invoice.payment_failed", endpoint: "https://api.acme.example/webhooks/billing", when: "2 min ago", durationMs: 3012, signature: "t=1741081200,v1=9f2c7b0a41d85e6c3b7a09f1d2e4c8a5b6f70123456789abcdef0123456789ab", status: "failed", code: 500,
    payload: { id: "evt_1QxA", type: "invoice.payment_failed", data: { invoice: "in_7421", amount_due: 4900, currency: "usd", attempt_count: 2 } }, response: '{"error":"internal_error","message":"Database connection timed out"}', attempts: [{ n: 1, code: 500 }] },
  { id: "d2", event: "customer.created", endpoint: "https://api.acme.example/webhooks/crm", when: "14 min ago", durationMs: 184, signature: "t=1741080360,v1=1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f809", status: "succeeded", code: 200,
    payload: { id: "evt_1QxB", type: "customer.created", data: { customer: "cus_9981", email: "amira@northwind.example" } }, response: '{"received":true}', attempts: [{ n: 1, code: 200 }] },
  { id: "d3", event: "order.shipped", endpoint: "https://hooks.partner.example/orders", when: "1 hour ago", durationMs: 10001, signature: "t=1741077600,v1=0f0e0d0c0b0a09080706050403020100ffeeddccbbaa99887766554433221100", status: "failed", code: 504,
    payload: { id: "evt_1QxC", type: "order.shipped", data: { order: "ord_5530", carrier: "DHL", tracking: "JD0146000" } }, response: "<html><body><h1>504 Gateway Time-out</h1></body></html>", attempts: [{ n: 1, code: 504 }, { n: 2, code: 504 }] },
  { id: "d4", event: "invoice.paid", endpoint: "https://api.acme.example/webhooks/billing", when: "3 hours ago", durationMs: 221, signature: "t=1741070400,v1=aa11bb22cc33dd44ee55ff66aa11bb22cc33dd44ee55ff66aa11bb22cc33dd44", status: "succeeded", code: 200,
    payload: { id: "evt_1QxD", type: "invoice.paid", data: { invoice: "in_7399", amount_paid: 4900 } }, response: '{"received":true}', attempts: [{ n: 1, code: 200 }] },
  { id: "d5", event: "subscription.updated", endpoint: "https://api.acme.example/webhooks/billing", when: "Yesterday", durationMs: 305, signature: "t=1740994800,v1=cafebabecafebabecafebabecafebabecafebabecafebabecafebabecafebabe", status: "succeeded", code: 200,
    payload: { id: "evt_1QxE", type: "subscription.updated", data: { subscription: "sub_302", plan: "team", seats: 12 } }, response: '{"received":true}', attempts: [{ n: 1, code: 200 }] },
];

export default function WebhookInspectorPreview() {
  return <div className="min-h-screen bg-neutral-50 px-4 py-10"><WebhookInspector initial={DELIVERIES} /></div>;
}
