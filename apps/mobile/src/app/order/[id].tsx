import * as WebBrowser from "expo-web-browser";
import { useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { money, t } from "@/i18n";
import { color } from "@/theme";
import { STATUS_TEXT, type Order, type OrderMessage, type RefundState } from "@/types";

/** One order: where it is, what it cost and, if it waits for payment, a way to pay. */
export default function OrderScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [order, setOrder] = useState<Order | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [refund, setRefund] = useState<RefundState | null>(null);
  const [reason, setReason] = useState("");
  const [asking, setAsking] = useState(false);
  const [chat, setChat] = useState<OrderMessage[]>([]);
  const [draft, setDraft] = useState("");

  const load = useCallback(() => {
    setError(null);
    api<Order>(`/orders/${id}`).then(setOrder).catch(setError);
    api<RefundState>(`/orders/${id}/refund-request`).then(setRefund).catch(() => setRefund(null));
    api<OrderMessage[]>(`/orders/${id}/messages`).then(setChat).catch(() => setChat([]));
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
      {refund?.refund_status ? (
        <Card><Text style={s.val}>{refund.refund_status === "pending" ? t("Your refund is being sent back to you.") : t("Your refund was sent.")}</Text></Card>
      ) : refund?.request?.status === "requested" ? (
        <Card><Text style={s.val}>{t("We received your return request. We will answer soon.")}</Text></Card>
      ) : refund?.can_request ? (
        asking ? (
          <Card style={{ gap: 10 }}>
            <Field label={t("Why do you want to cancel or return it?")} value={reason} onChangeText={setReason} multiline />
            <Button title={t("Send request")} disabled={reason.trim().length < 3} onPress={async () => {
              try { await api(`/orders/${id}/refund-request`, { method: "POST", body: { reason } }); setAsking(false); setReason(""); load(); } catch (e) { setMessage(messageOf(e)); }
            }} />
          </Card>
        ) : <Button title={t("Cancel or return this order")} kind="outline" onPress={() => setAsking(true)} />
      ) : null}

      {chat.length > 0 || order.status !== "awaiting_payment" ? (
        <Card style={{ gap: 8 }}>
          <Text style={s.total}>{t("Messages about this order")}</Text>
          {chat.map((m) => <View key={m.id} style={{ alignSelf: m.mine ? "flex-end" : "flex-start", backgroundColor: m.mine ? color.wash : color.bone, borderRadius: 14, padding: 10, maxWidth: "85%" }}><Text style={{ color: color.ink }}>{m.body}</Text></View>)}
          <Field label={t("Write a message")} value={draft} onChangeText={setDraft} />
          <Button title={t("Send")} kind="outline" disabled={draft.trim().length < 2} onPress={async () => {
            try { await api(`/orders/${id}/messages`, { method: "POST", body: { body: draft } }); setDraft(""); load(); } catch (e) { setMessage(messageOf(e)); }
          }} />
        </Card>
      ) : null}
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
