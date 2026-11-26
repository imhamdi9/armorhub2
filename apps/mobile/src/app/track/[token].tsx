import { useFocusEffect, useLocalSearchParams } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Card, Loading, Problem } from "@/components/ui";
import { money, t } from "@/i18n";
import { color } from "@/theme";
import { STATUS_TEXT, type GuestOrder } from "@/types";

/** An order bought without an account, reached by its secret link (armorhub://track/<token> or the same link in an e-mail). */
export default function TrackScreen() {
  const { token } = useLocalSearchParams<{ token: string }>();
  const [order, setOrder] = useState<GuestOrder | null>(null);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(() => {
    setError(null);
    api<GuestOrder>(`/guest-orders/${encodeURIComponent(token)}`).then(setOrder).catch(setError);
  }, [token]);
  useFocusEffect(load);

  if (error && !order) return <Problem error={error} retry={load} />;
  if (!order) return <Loading />;

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
      <Text style={s.title}>{order.product.name}</Text>
      <Text style={s.status}>{t(STATUS_TEXT[order.status] ?? "In progress")}</Text>
      <Card style={{ gap: 8 }}>
        <View style={s.row}><Text style={s.key}>{t("Invoice")}</Text><Text style={s.val}>{money(order.total, order.currency)}</Text></View>
        {(order.service_fee ?? 0) > 0 && <View style={s.row}><Text style={s.key}>{t("Platform fee")}</Text><Text style={s.val}>{money(order.service_fee ?? 0, order.currency)}</Text></View>}
        <View style={s.row}><Text style={[s.key, s.total]}>{t("Total to pay")}</Text><Text style={[s.val, s.total]}>{money(order.payable ?? order.total, order.currency)}</Text></View>
      </Card>
      {order.shipments.map((sh, i) => (
        <Card key={i}><Text style={s.val}>{sh.carrier ?? ""} {sh.tracking_number ?? ""}</Text><Text style={s.key}>{sh.status}</Text></Card>
      ))}
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
