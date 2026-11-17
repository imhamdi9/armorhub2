import * as WebBrowser from "expo-web-browser";
import { useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Loading, Problem, messageOf } from "@/components/ui";
import { money, t } from "@/i18n";
import { color } from "@/theme";
import { STATUS_TEXT, type Order } from "@/types";

/** One order: where it is, what it cost and, if it waits for payment, a way to pay. */
export default function OrderScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api<Order>(`/orders/${id}`).then(setOrder).catch(setError);
  }, [id]);
  // Reloads whenever the screen is shown again, so the result of a payment made in the browser is there when the person returns.
  useFocusEffect(load);

  if (error && !order) return <Problem error={error} retry={load} />;
  if (!order) return <Loading />;

  const payable = order.payable_amount ?? Number(order.total_amount);
  const waiting = order.status === "awaiting_payment";

  async function pay() {
    if (!order) return;
    setBusy(true);
    setMessage(null);
    try {
      const res = await api<{ checkout_url?: string }>(`/orders/${order.id}/payments`, { method: "POST", body: { amount: payable, payment_method: "card" } });
      if (res.checkout_url) await WebBrowser.openBrowserAsync(res.checkout_url);
      load();
    } catch (e) {
      setMessage(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
      <Text style={s.title}>{order.product?.name ?? `${t("Order")} #${order.id}`}</Text>
      <Text style={s.status}>{t(STATUS_TEXT[order.status] ?? "In progress")}</Text>
      <Card style={{ gap: 8 }}>
        <View style={s.row}><Text style={s.key}>{t("Invoice")}</Text><Text style={s.val}>{money(order.total_amount, order.currency)}</Text></View>
        {Number(order.service_fee_amount) > 0 && <View style={s.row}><Text style={s.key}>{t("Platform fee")} ({Number(order.service_fee_rate)}%)</Text><Text style={s.val}>{money(order.service_fee_amount ?? 0, order.currency)}</Text></View>}
        <View style={s.row}><Text style={[s.key, s.total]}>{t("Total to pay")}</Text><Text style={[s.val, s.total]}>{money(payable, order.currency)}</Text></View>
      </Card>
      {message ? <Text style={{ color: color.red }} accessibilityRole="alert">{message}</Text> : null}
      {waiting && <Button title={`${t("Pay now")} · ${money(payable, order.currency)}`} busy={busy} onPress={pay} />}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  title: { fontSize: 22, fontWeight: "800", color: color.navy },
  status: { fontSize: 15, fontWeight: "700", color: color.blue },
  row: { flexDirection: "row", justifyContent: "space-between" },
  key: { color: color.slate, fontSize: 15 },
  val: { color: color.ink, fontSize: 15, fontWeight: "600" },
  total: { color: color.navy, fontWeight: "800", fontSize: 17 },
});
