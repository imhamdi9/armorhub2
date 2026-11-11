// A stand-in for an arbitrary hosted-checkout payment gateway, for end-to-end tests only.
//  POST /v1/payments  (JSON, X-Api-Key)  -> {data: {id, pay_url}}
//  GET  /pay/:id                          -> a payment page; "Pay now" sends the API an HMAC-SIGNED webhook
import crypto from "node:crypto";
import http from "node:http";

export function startFakeGateway({ port = 12112, apiUrl, apiKey, webhookSecret }) {
  const payments = new Map();
  const send = (res, status, body, type = "application/json") => {
    res.writeHead(status, { "Content-Type": type });
    res.end(typeof body === "string" ? body : JSON.stringify(body));
  };

  const server = http.createServer(async (req, res) => {
    const chunks = [];
    for await (const c of req) chunks.push(c);
    const raw = Buffer.concat(chunks).toString();
    const url = new URL(req.url, `http://127.0.0.1:${port}`);

    if (req.method === "POST" && url.pathname === "/v1/payments") {
      if (req.headers["x-api-key"] !== apiKey) return send(res, 401, { error: "bad key" });
      const body = JSON.parse(raw);
      const id = `gw_${crypto.randomBytes(5).toString("hex")}`;
      payments.set(id, { id, body });
      return send(res, 200, { data: { id, pay_url: `http://127.0.0.1:${port}/pay/${id}` } });
    }

    const pay = url.pathname.match(/^\/pay\/(\w+)(\/confirm)?$/);
    const p = pay && payments.get(pay[1]);
    if (!p) return send(res, 404, "unknown", "text/plain");

    if (req.method === "GET") {
      return send(res, 200, `<html><body><h1>Fake Gateway</h1><p>${p.body.note}: ${p.body.amount} ${p.body.currency}</p>
        <form method="post" action="/pay/${p.id}/confirm"><button>Confirm payment</button></form></body></html>`, "text/html");
    }

    const payload = JSON.stringify({ data: { id: p.id, status: "PAID", reference: p.body.metadata.order_id, amount: p.body.amount, currency: p.body.currency } });
    const signature = crypto.createHmac("sha256", webhookSecret).update(payload).digest("hex");
    setTimeout(() => {
      fetch(`${apiUrl}/api/webhooks/http`, { method: "POST", headers: { "Content-Type": "application/json", "X-Signature": signature }, body: payload })
        .catch((e) => console.error("webhook delivery failed", e.message));
    }, 3000);
    res.writeHead(302, { Location: p.body.success_url });
    res.end();
  });

  return new Promise((resolve) => server.listen(port, "127.0.0.1", () => resolve(server)));
}
