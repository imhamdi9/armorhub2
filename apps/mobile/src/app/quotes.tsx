import * as WebBrowser from "expo-web-browser";
import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Loading, Problem, messageOf } from "@/components/ui";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import type { Address, QuoteRequest } from "@/types";

/** Price requests: what the person asked sellers for, the numbered quotes that came back, and a way to accept one and pay. */
export default function QuotesScreen() {
  const { user } = useSession();
  const [rows, setRows] = useState<QuoteRequest[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<QuoteRequest[]>("/quote-requests").then(setRows).catch(setError);
  }, [user]);
  useFocusEffect(load);

  if (!user) return <View style={s.center}><Text style={s.muted}>{t("Sign in to see your price requests")}</Text><Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} /></View>;
  if (error && !rows) return <Problem error={error} retry={load} />;
  if (!rows) return <Loading />;

  async function accept(req: QuoteRequest, quoteId: number) {
    setBusy(quoteId);
    setMessage(null);
    try {
      // Delivery goes to the default saved address; people without one add it in their account on the website.
      const book = await api<Address[]>("/addresses");
      const a = book.find((x) => x.is_default) ?? book[0];
      if (!a) { setMessage(t("Save a delivery address first (Account > Addresses).")); return; }
      const group = await api<{ order_group_id: number }>(`/quote-requests/${req.id}/accept`, { method: "POST", body: { quote_id: quoteId, shipping_address: { name: a.name, phone: a.phone, line1: a.line1, city: a.city } } });
      const res = await api<{ checkout_url?: string }>(`/order-groups/${group.order_group_id}/payments`, { method: "POST", body: { payment_method: "card" } });
      if (res.checkout_url) await WebBrowser.openBrowserAsync(res.checkout_url);
      router.push("/orders");
    } catch (e) { setMessage(messageOf(e)); } finally { setBusy(null); }
  }

  return (
    <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ padding: 14, gap: 12 }}>
      {rows.length === 0 ? <Text style={s.muted}>{t("No price requests yet. Open a product sold by price request and tap Request a price.")}</Text> : null}
      {message ? <Text style={s.error} accessibilityRole="alert">{message}</Text> : null}
      {rows.map((r) => (
        <Card key={r.id} style={{ gap: 8 }}>
          <Text style={s.name}>{r.number} · {r.title ?? r.category} × {r.quantity}</Text>
          <Text style={s.muted}>{r.status}</Text>
          {r.quotes.length === 0 ? <Text style={s.muted}>{t("No quotes yet. Sellers have been asked.")}</Text> : r.quotes.map((q) => (
            <View key={q.id} style={s.quote}>
              <View style={{ flex: 1 }}>
                <Text style={s.val}>{q.number} · {money(q.total, r.currency)}</Text>
                <Text style={s.muted}>{q.lead_days ?? 0} {t("days")}{q.warranty_days ? ` · ${q.warranty_days} ${t("days warranty")}` : ""}</Text>
              </View>
              {r.status === "open" && !q.expired && q.status === "submitted" ? <Button title={t("Accept")} busy={busy === q.id} onPress={() => accept(r, q.id)} style={{ minHeight: 42 }} /> : <Text style={s.muted}>{q.expired ? t("Expired") : q.status}</Text>}
            </View>
          ))}
        </Card>
      ))}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  muted: { color: color.slate, fontSize: 13 },
  name: { fontSize: 15, fontWeight: "700", color: color.navy },
  val: { fontSize: 14, fontWeight: "700", color: color.ink },
  error: { color: color.red, fontSize: 13 },
  quote: { flexDirection: "row", alignItems: "center", gap: 10, borderTopWidth: 1, borderColor: color.fog, paddingTop: 8 },
});
