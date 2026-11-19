import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { api } from "@/api";
import { Picture } from "@/components/brand";
import { Button, Loading, Problem } from "@/components/ui";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color, radius } from "@/theme";
import { STATUS_TEXT, type Order, type Paginated } from "@/types";

type Filter = "all" | "progress" | "done" | "cancelled";
const FILTERS: { key: Filter; label: string }[] = [{ key: "all", label: "All" }, { key: "progress", label: "In progress" }, { key: "done", label: "Delivered" }, { key: "cancelled", label: "Cancelled" }];

const groupOf = (status: string): Filter => (status === "completed" ? "done" : status === "cancelled" ? "cancelled" : "progress");

/** How far along an order is, as four steps: ordered, paid, on its way, done. */
const stepOf = (status: string) => (status === "awaiting_payment" ? 1 : status === "completed" ? 4 : status === "cancelled" ? 0 : 3);

const date = (iso?: string) => (iso ? new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : "");

/** The customer's orders: the same ones as on the website. */
export default function OrdersScreen() {
  const { user, ready } = useSession();
  const [orders, setOrders] = useState<Order[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [filter, setFilter] = useState<Filter>("all");

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<Paginated<Order>>("/orders", { params: { per_page: 30 } }).then((r) => setOrders(r.data)).catch(setError);
  }, [user]);
  // Refreshes every time the tab is shown, so a payment made in the browser appears on return.
  useFocusEffect(load);

  const header = (
    <View style={s.header}>
      <Text style={s.title}>{t("Your orders")}</Text>
      {user && (
        <View style={s.chips}>
          {FILTERS.map((f) => (
            <Pressable key={f.key} onPress={() => setFilter(f.key)} style={[s.chip, filter === f.key && s.chipOn]} accessibilityRole="button" accessibilityState={{ selected: filter === f.key }}>
              <Text style={[s.chipText, filter === f.key && { color: "#fff" }]}>{t(f.label)}</Text>
            </Pressable>
          ))}
        </View>
      )}
    </View>
  );

  let body: React.ReactNode;
  if (!ready) body = <Loading />;
  else if (!user) {
    body = (
      <View style={s.center}>
        <Text style={s.text}>{t("Sign in to see your orders")}</Text>
        <Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} />
      </View>
    );
  } else if (error && !orders) body = <Problem error={error} retry={load} />;
  else if (!orders) body = <Loading />;
  else {
    const shown = orders.filter((o) => filter === "all" || groupOf(o.status) === filter);
    body = shown.length === 0
      ? <View style={s.center}><Text style={s.text}>{t("No orders yet.")}</Text></View>
      : (
        <FlatList data={shown} keyExtractor={(o) => String(o.id)} contentContainerStyle={{ padding: 14, gap: 12 }} onRefresh={load} refreshing={false}
          renderItem={({ item }) => <OrderCard order={item} />} />
      );
  }

  return (
    <SafeAreaView edges={["top"]} style={{ flex: 1, backgroundColor: "#fff" }}>
      {header}
      <View style={{ flex: 1, backgroundColor: color.bone }}>{body}</View>
    </SafeAreaView>
  );
}

function OrderCard({ order }: { order: Order }) {
  const step = stepOf(order.status);
  const name = order.product?.name ?? `${t("Order")} #${order.id}`;
  const open = () => router.push({ pathname: "/order/[id]", params: { id: String(order.id) } });
  const done = order.status === "completed";
  return (
    <Pressable onPress={open} style={s.card} accessibilityRole="button" accessibilityLabel={name}>
      <View style={s.top}>
        <View><Text style={s.topLabel}>{t("Placed")}</Text><Text style={s.topValue}>{date(order.created_at)}</Text></View>
        <View><Text style={s.topLabel}>{t("Total")}</Text><Text style={s.topValue}>{money(order.payable_amount ?? order.total_amount, order.currency)}</Text></View>
        <View><Text style={s.topLabel}>{t("Order")}</Text><Text style={s.topValue}>#{order.id}</Text></View>
      </View>
      <Text style={[s.status, order.status === "completed" && { color: color.navy }, order.status === "cancelled" && { color: color.red }]}>{t(STATUS_TEXT[order.status] ?? "In progress")}</Text>
      {order.status !== "cancelled" && <View style={s.prog}>{[1, 2, 3, 4].map((i) => <View key={i} style={[s.seg, i <= step && { backgroundColor: color.blue }]} />)}</View>}
      <View style={{ flexDirection: "row", gap: 12 }}>
        <Picture product={{ name, images: order.product?.images }} style={{ width: 78, height: 78 }} letterSize={30} />
        <View style={{ flex: 1, gap: 8 }}>
          <Text style={s.name} numberOfLines={2}>{name}</Text>
          <View style={{ flexDirection: "row", gap: 8 }}>
            <Button title={order.status === "awaiting_payment" ? t("Pay now") : t("Track order")} onPress={open} style={{ flex: 1, minHeight: 40, paddingHorizontal: 8 }} />
            {done && <Button title={t("Buy again")} kind="outline" onPress={() => router.push("/")} style={{ flex: 1, minHeight: 40, paddingHorizontal: 8 }} />}
          </View>
        </View>
      </View>
    </Pressable>
  );
}

const s = StyleSheet.create({
  header: { paddingHorizontal: 14, paddingTop: 8, paddingBottom: 12, gap: 12, backgroundColor: "#fff" },
  title: { fontSize: 26, fontWeight: "800", color: color.navy, letterSpacing: -0.6 },
  chips: { flexDirection: "row", gap: 8, flexWrap: "wrap" },
  chip: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 14, paddingVertical: 7, backgroundColor: "#fff" },
  chipOn: { backgroundColor: color.navy, borderColor: color.navy },
  chipText: { fontSize: 13, fontWeight: "600", color: color.navy },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  text: { fontSize: 16, color: color.slate, textAlign: "center" },
  card: { backgroundColor: "#fff", borderRadius: radius.big - 2, borderWidth: 1, borderColor: color.fog, padding: 16, gap: 10 },
  top: { flexDirection: "row", justifyContent: "space-between", borderBottomWidth: 1, borderBottomColor: color.fog, paddingBottom: 10 },
  topLabel: { fontSize: 11.5, color: color.slate },
  topValue: { fontSize: 13, fontWeight: "700", color: color.navy, marginTop: 2 },
  status: { fontSize: 16, fontWeight: "800", color: color.green },
  prog: { flexDirection: "row", gap: 4 },
  seg: { flex: 1, height: 5, borderRadius: 3, backgroundColor: color.fog },
  name: { fontSize: 14, fontWeight: "600", color: color.navy },
});
