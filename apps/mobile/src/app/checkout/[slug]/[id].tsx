import * as WebBrowser from "expo-web-browser";
import { router, useLocalSearchParams } from "expo-router";
import { useEffect, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { Picture, Price } from "@/components/brand";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import type { Order, ProductPage } from "@/types";

type Store = { market?: { code: string } | null };

/**
 * Placing an order: the delivery address for goods, then the order is created and paid. The platform fee is shown before anything is paid.
 * Card payment opens in the secure browser, exactly as on the website; the order screen shows the result when the person comes back.
 */
export default function CheckoutScreen() {
  const { slug, id, option } = useLocalSearchParams<{ slug: string; id: string; option?: string }>();
  const { user, ready } = useSession();
  const [data, setData] = useState<ProductPage | null>(null);
  const [country, setCountry] = useState("SA");
  const [form, setForm] = useState({ name: "", phone: "", line1: "", city: "" });
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [error, setError] = useState<unknown>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    api<ProductPage>(`/public/storefronts/${encodeURIComponent(slug)}/products/${id}`).then(setData).catch(setError);
    api<{ market?: Store["market"] }>(`/public/storefronts/${encodeURIComponent(slug)}`).then((r) => r.market?.code && setCountry(r.market.code)).catch(() => undefined);
  }, [slug, id]);

  useEffect(() => {
    if (user) setForm((f) => ({ ...f, name: f.name || user.name, phone: f.phone || (user.phone ?? "") }));
  }, [user]);

  useEffect(() => {
    if (ready && !user) router.replace("/login");
  }, [ready, user]);

  if (error) return <Problem error={error} />;
  if (!data || !user) return <Loading />;

  const p = data.product;
  const goods = !!p.requires_shipping;
  const options = p.prices.filter((r) => r.target_id !== undefined);
  const chosen = option !== undefined && option !== "" ? options[Number(option)] : options[0];
  const price = chosen?.price ?? p.from_price;
  const fee = Math.round(price * (data.customer_fee_percentage ?? 0)) / 100;

  const set = (k: keyof typeof form) => (v: string) => { setForm((f) => ({ ...f, [k]: v })); setFieldErrors((e) => ({ ...e, [`shipping_address.${k}`]: "" })); };
  const err = (k: string) => fieldErrors[`shipping_address.${k}`] || undefined;

  async function place() {
    if (!chosen?.target_id) return;
    setBusy(true);
    setError(null);
    setFieldErrors({});
    setMessage(null);
    try {
      const created = await api<{ order: Order }>("/orders", {
        method: "POST",
        body: { product_id: p.id, target_type: chosen.target_type, targets: [chosen.target_id], ...(goods ? { shipping_address: { ...form, country } } : {}) },
      });
      const order = created.order;

      // Goods are paid now; a service waits for quotes from partners first.
      if (goods && (order.payable_amount ?? Number(order.total_amount)) > 0) {
        const paid = await api<{ checkout_url?: string }>(`/orders/${order.id}/payments`, { method: "POST", body: { amount: order.payable_amount ?? Number(order.total_amount), payment_method: "card" } });
        if (paid.checkout_url) {
          setMessage(t("Payment opens in your browser."));
          router.replace({ pathname: "/order/[id]", params: { id: String(order.id) } });
          await WebBrowser.openBrowserAsync(paid.checkout_url);
          return;
        }
      }
      router.replace({ pathname: "/order/[id]", params: { id: String(order.id) } });
    } catch (e) {
      const errors = (e as { errors?: Record<string, string[]> }).errors ?? {};
      setFieldErrors(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
      setMessage(messageOf(e));
      setBusy(false);
    }
  }

  return (
    <View style={{ flex: 1, backgroundColor: color.bone }}>
      <View style={s.steps}>
        {[t("Address"), t("Review"), t("Pay")].map((label, i) => (
          <View key={label} style={{ flex: 1, gap: 6 }}>
            <View style={[s.stepBar, i === 0 && { backgroundColor: color.blue }]} />
            <Text style={[s.stepText, i === 0 && { color: color.navy }]}>{label}</Text>
          </View>
        ))}
      </View>
      <ScrollView contentContainerStyle={s.wrap} keyboardShouldPersistTaps="handled">
        {goods && (
          <Card style={{ gap: 12 }}>
            <Text style={s.section}>{t("Delivering to {name}", { name: form.name || user.name })}</Text>
            <Field label={t("Full name")} value={form.name} onChangeText={set("name")} error={err("name")} autoComplete="name" />
            <Field label={t("Phone")} value={form.phone} onChangeText={set("phone")} error={err("phone")} keyboardType="phone-pad" autoComplete="tel" />
            <Field label={t("Address")} value={form.line1} onChangeText={set("line1")} error={err("line1")} autoComplete="street-address" />
            <Field label={t("City")} value={form.city} onChangeText={set("city")} error={err("city")} />
          </Card>
        )}

        <Card style={{ gap: 10 }}>
          <Text style={s.section}>{t("Your item")}</Text>
          <View style={{ flexDirection: "row", gap: 12, alignItems: "center" }}>
            <Picture product={p} style={{ width: 68, height: 68 }} letterSize={28} />
            <View style={{ flex: 1, gap: 4 }}>
              <Text style={s.itemName} numberOfLines={2}>{p.name}{chosen && options.length > 1 ? ` · ${chosen.target}` : ""}</Text>
              <Price amount={price} was={p.compare_at_price} currency={p.currency} />
            </View>
          </View>
        </Card>

        <Card style={{ gap: 8 }}>
          <Text style={s.section}>{t("Order summary")}</Text>
          <View style={s.row}><Text style={s.key}>{t("Items")}</Text><Text style={s.val}>{money(price, p.currency)}</Text></View>
          {goods && <View style={s.row}><Text style={s.key}>{t("Delivery")}</Text><Text style={[s.val, { color: color.green }]}>{t("Free")}</Text></View>}
          {fee > 0 && <View style={s.row}><Text style={s.key}>{t("Platform fee")} ({data.customer_fee_percentage}%)</Text><Text style={s.val}>{money(fee, p.currency)}</Text></View>}
          {goods && <View style={[s.row, s.totalRow]}><Text style={s.total}>{t("Total")}</Text><Text style={s.total}>{money(price + fee, p.currency)}</Text></View>}
        </Card>
        {message ? <Text style={s.message} accessibilityRole="alert">{message}</Text> : null}
      </ScrollView>

      <SafeAreaView edges={["bottom"]} style={s.bar}>
        <Button title={goods ? t("Place order") : t("Request a quote")} busy={busy} disabled={!chosen?.target_id || (goods && (!form.name || !form.phone || !form.line1 || !form.city))} onPress={place} style={{ alignSelf: "stretch" }} />
        {goods && <Text style={s.hint}>{t("Card payment opens in the secure browser")}</Text>}
      </SafeAreaView>
    </View>
  );
}

const s = StyleSheet.create({
  steps: { flexDirection: "row", gap: 6, paddingHorizontal: 14, paddingTop: 10, paddingBottom: 12, backgroundColor: "#fff" },
  stepBar: { height: 5, borderRadius: 3, backgroundColor: color.fog },
  stepText: { fontSize: 12, fontWeight: "600", color: color.slate },
  wrap: { padding: 14, gap: 12, paddingBottom: 130 },
  section: { fontSize: 16, fontWeight: "800", color: color.navy },
  itemName: { fontSize: 14, fontWeight: "600", color: color.navy },
  row: { flexDirection: "row", justifyContent: "space-between" },
  key: { color: "#33415f", fontSize: 14 },
  val: { color: color.ink, fontSize: 14, fontWeight: "600" },
  totalRow: { borderTopWidth: 1, borderTopColor: color.fog, paddingTop: 10, marginTop: 4 },
  total: { color: color.navy, fontWeight: "800", fontSize: 18 },
  message: { color: color.red, fontSize: 14 },
  bar: { position: "absolute", bottom: 0, start: 0, end: 0, paddingHorizontal: 14, paddingTop: 10, backgroundColor: "#fff", borderTopWidth: 1, borderColor: color.fog, gap: 6 },
  hint: { textAlign: "center", fontSize: 11, color: color.slate, paddingBottom: 4 },
});
