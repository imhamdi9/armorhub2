// A tiny stand-in for Stripe Checkout, for local end-to-end tests only.
// POST /v1/checkout/sessions -> {id, url}; the pay page then sends the API a correctly
// SIGNED checkout.session.completed webhook and redirects back, like the real thing.
import crypto from "node:crypto";
import http from "node:http";

export function startFakeStripe({ port = 12111, apiUrl, webhookSecret }) {
  const sessions = new Map();

  const send = (res, status, body, type = "application/json") => {
    res.writeHead(status, { "Content-Type": type });
    res.end(typeof body === "string" ? body : JSON.stringify(body));
  };

  const server = http.createServer(async (req, res) => {
    const chunks = [];
    for await (const c of req) chunks.push(c);
    const raw = Buffer.concat(chunks).toString();
    const url = new URL(req.url, `http://localhost:${port}`);

    if (req.method === "POST" && url.pathname === "/v1/checkout/sessions") {
      const f = Object.fromEntries(new URLSearchParams(raw));
      const id = `cs_test_${crypto.randomBytes(6).toString("hex")}`;
      sessions.set(id, {
        id,
        order_id: f["metadata[order_id]"],
        amount: Number(f["line_items[0][price_data][unit_amount]"]),
        currency: f["line_items[0][price_data][currency]"],
        success_url: f.success_url,
        cancel_url: f.cancel_url,
      });
      return send(res, 200, { id, url: `http://localhost:${port}/pay/${id}` });
    }

    const pay = url.pathname.match(/^\/pay\/(\w+)(\/complete|\/cancel)?$/);
    if (pay) {
      const s = sessions.get(pay[1]);
      if (!s) return send(res, 404, "unknown session", "text/plain");

      if (req.method === "GET" && !pay[2]) {
        return send(res, 200, `<html><body><h1>Fake Stripe Checkout</h1>
          <p>Order ${s.order_id}: ${s.amount} ${s.currency}</p>
          <form method="post" action="/pay/${s.id}/complete"><button>Pay now</button></form>
          <form method="post" action="/pay/${s.id}/cancel"><button>Back</button></form></body></html>`, "text/html");
      }

      if (pay[2] === "/cancel") {
        res.writeHead(302, { Location: s.cancel_url });
        return res.end();
      }

      // "complete": the provider tells the API, signed with the shared secret...
      const payload = JSON.stringify({
        type: "checkout.session.completed",
        data: { object: { id: s.id, payment_status: "paid", amount_total: s.amount, currency: s.currency, metadata: { order_id: s.order_id } } },
      });
      const t = Math.floor(Date.now() / 1000);
      const v1 = crypto.createHmac("sha256", webhookSecret).update(`${t}.${payload}`).digest("hex");
      // ...a few seconds later than the customer's redirect, like real life.
      setTimeout(() => {
        fetch(`${apiUrl}/api/webhooks/stripe`, {
          method: "POST",
          headers: { "Content-Type": "application/json", "Stripe-Signature": `t=${t},v1=${v1}` },
          body: payload,
        }).catch((e) => console.error("webhook delivery failed", e.message));
      }, 4000);
      res.writeHead(302, { Location: s.success_url });
      return res.end();
    }

    send(res, 404, "not found", "text/plain");
  });

  return new Promise((resolve) => server.listen(port, () => resolve(server)));
}
