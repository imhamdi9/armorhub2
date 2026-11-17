import { Link, router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Loading, Problem } from "@/components/ui";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import { STATUS_TEXT, type Order, type Paginated } from "@/types";

/** The customer's orders: the same ones as on the website. */
export default function OrdersScreen() {
  const { user, ready } = useSession();
  const [orders, setOrders] = useState<Order[] | null>(null);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<Paginated<Order>>("/orders", { params: { per_page: 30 } }).then((r) => setOrders(r.data)).catch(setError);
  }, [user]);
  // Refreshes every time the tab is shown, so a payment made in the browser appears on return.
  useFocusEffect(load);

  if (!ready) return <Loading />;
  if (!user) {
    return (
      <View style={s.center}>
        <Text style={s.text}>{t("Sign in to see your orders")}</Text>
        <Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} />
      </View>
    );
  }
  if (error) return <Problem error={error} retry={load} />;
  if (!orders) return <Loading />;
  if (orders.length === 0) return <View style={s.center}><Text style={s.text}>{t("No orders yet.")}</Text></View>;

  return (
    <FlatList data={orders} keyExtractor={(o) => String(o.id)} contentContainerStyle={{ padding: 16, gap: 12 }} onRefresh={load} refreshing={false}
      renderItem={({ item }) => (
        <Link href={{ pathname: "/order/[id]", params: { id: String(item.id) } }} asChild>
          <Pressable accessibilityRole="button">
            <Card style={{ gap: 6 }}>
              <Text style={s.title}>{item.product?.name ?? `${t("Order")} #${item.id}`}</Text>
              <Text style={s.status}>{t(STATUS_TEXT[item.status] ?? "In progress")}</Text>
              <Text style={s.amount}>{money(item.payable_amount ?? item.total_amount, item.currency)}</Text>
            </Card>
          </Pressable>
        </Link>
      )} />
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  text: { fontSize: 16, color: color.slate, textAlign: "center" },
  title: { fontSize: 16, fontWeight: "800", color: color.navy },
  status: { fontSize: 13, color: color.blue, fontWeight: "700" },
  amount: { fontSize: 15, color: color.ink },
});
