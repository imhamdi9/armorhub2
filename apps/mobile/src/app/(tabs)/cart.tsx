import * as WebBrowser from "expo-web-browser";
import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color, radius } from "@/theme";
import type { Address, CartView } from "@/types";

/** The cart: quantities, a discount code, where to deliver, and one payment for everything (even from several sellers). */
export default function CartScreen() {
  const { user } = useSession();
  const [cart, setCart] = useState<CartView | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [saved, setSaved] = useState<Address[]>([]);
  const [form, setForm] = useState({ name: "", phone: "", line1: "", city: "" });
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [speed, setSpeed] = useState<"standard" | "express">("standard");
  const [day, setDay] = useState("");
  const [gift, setGift] = useState("");
  const [usePoints, setUsePoints] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<CartView>("/cart").then(setCart).catch(setError);
    api<Address[]>("/addresses").then((list) => {
      setSaved(list);
      const d = list.find((a) => a.is_default);
      setForm((f) => (f.line1 ? f : d ? { name: d.name, phone: d.phone, line1: d.line1, city: d.city } : { ...f, name: f.name || user.name }));
    }).catch(() => {});
  }, [user]);
  // Reloads whenever the tab is shown, so what was just added is there.
  useFocusEffect(load);

  async function run<T>(fn: () => Promise<T>, then?: (r: T) => void) {
    setMessage(null);
    try { const r = await fn(); then?.(r); } catch (e) { setMessage(messageOf(e)); }
  }

  async function placeOrder() {
    setBusy(true);
    setMessage(null);
    try {
      const group = await api<{ id: number }>("/checkout", { method: "POST", body: { shipping_address: form, delivery_speed: speed, ...(/^\d{4}-\d{2}-\d{2}$/.test(day) ? { preferred_day: day } : {}), ...(gift.trim() ? { gift_note: gift.trim() } : {}), ...(usePoints && cart?.points?.max_use ? { use_points: cart.points.max_use } : {}) } });
      const res = await api<{ checkout_url?: string }>(`/order-groups/${group.id}/payments`, { method: "POST", body: { payment_method: "card" } });
      if (res.checkout_url) await WebBrowser.openBrowserAsync(res.checkout_url);
      setCart(null);
      router.push("/orders");
    } catch (e) {
      setMessage(messageOf(e));
    } finally {
      setBusy(false);
    }
  }

  if (!user) {
    return (
      <SafeAreaView edges={["top"]} style={s.page}>
        <Text style={s.title}>{t("Cart")}</Text>
        <View style={s.center}>
          <Text style={s.muted}>{t("Sign in to use your cart")}</Text>
          <Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} />
        </View>
      </SafeAreaView>
    );
  }
  if (error && !cart) return <Problem error={error} retry={load} />;
  if (!cart) return <Loading />;

  const cur = cart.currency ?? "";
  const extra = speed === "express" ? (cart.delivery_options ?? []).find((o) => o.key === "express")?.extra_fee ?? 0 : 0;
  const pointsOff = usePoints && cart.points ? cart.points.max_use * cart.points.value : 0;
  const payable = cart.payable_amount + extra - pointsOff;

  return (
    <SafeAreaView edges={["top"]} style={s.page}>
      <Text style={s.title}>{t("Cart")}</Text>
      {cart.items.length === 0 ? (
        <View style={s.center}><Text style={s.muted}>{t("Your cart is empty.")}</Text></View>
      ) : (
        <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ padding: 14, gap: 12 }}>
          {cart.items.map((l) => (
            <Card key={l.id} style={{ gap: 8 }}>
              <Text style={s.name}>{l.name}</Text>
              {l.problem ? <Text style={s.error}>{l.problem}</Text> : <Text style={s.muted}>{money(l.unit_price ?? 0, l.currency ?? cur)}</Text>}
              <View style={s.row}>
                <View style={s.qty}>
                  <Pressable accessibilityLabel={t("Fewer")} disabled={l.quantity <= 1} onPress={() => run(() => api<CartView>(`/cart/items/${l.id}`, { method: "PUT", body: { quantity: l.quantity - 1 } }), setCart)} style={s.qtyBtn}><Text style={s.qtyText}>−</Text></Pressable>
                  <Text style={s.qtyNum}>{l.quantity}</Text>
                  <Pressable accessibilityLabel={t("More")} onPress={() => run(() => api<CartView>(`/cart/items/${l.id}`, { method: "PUT", body: { quantity: l.quantity + 1 } }), setCart)} style={s.qtyBtn}><Text style={s.qtyText}>+</Text></Pressable>
                </View>
                <Button title={t("Remove")} kind="outline" onPress={() => run(() => api<CartView>(`/cart/items/${l.id}`, { method: "DELETE" }), setCart)} style={{ minHeight: 40 }} />
              </View>
            </Card>
          ))}

          <Card style={{ gap: 10 }}>
            <Text style={s.h}>{t("Delivery address")}</Text>
            {saved.length > 0 && (
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                {saved.map((a) => (
                  <Pressable key={a.id} onPress={() => setForm({ name: a.name, phone: a.phone, line1: a.line1, city: a.city })} style={[s.chip, form.line1 === a.line1 && s.chipOn]}>
                    <Text style={s.chipText}>{a.label || a.line1}</Text>
                  </Pressable>
                ))}
              </ScrollView>
            )}
            <Field label={t("Full name")} value={form.name} onChangeText={(v) => setForm({ ...form, name: v })} />
            <Field label={t("Phone")} value={form.phone} onChangeText={(v) => setForm({ ...form, phone: v })} keyboardType="phone-pad" />
            <Field label={t("Address")} value={form.line1} onChangeText={(v) => setForm({ ...form, line1: v })} />
            <Field label={t("City")} value={form.city} onChangeText={(v) => setForm({ ...form, city: v })} />
          </Card>

          {(cart.delivery_options ?? []).length > 0 && (
            <Card style={{ gap: 10 }}>
              <Text style={s.h}>{t("How fast, and when?")}</Text>
              {cart.delivery_options!.map((o) => (
                <Pressable key={o.key} accessibilityRole="radio" accessibilityState={{ selected: speed === o.key }} onPress={() => setSpeed(o.key)} style={[s.chip, speed === o.key && s.chipOn, { borderRadius: 12 }]}>
                  <Text style={s.chipText}>{o.key === "express" ? t("Express delivery") : t("Standard delivery")}{o.eta_days ? ` · ${t("about {n} days", { n: o.eta_days })}` : ""}{o.extra_fee > 0 ? ` · +${money(o.extra_fee, cur)}` : ""}</Text>
                </Pressable>
              ))}
              <Field label={t("Preferred delivery day (optional)")} value={day} onChangeText={setDay} placeholder="YYYY-MM-DD" autoCapitalize="none" />
              <Field label={t("Gift note (optional)")} value={gift} onChangeText={setGift} maxLength={200} multiline />
            </Card>
          )}
          {cart.points && cart.points.max_use > 0 ? (
            <Pressable accessibilityRole="checkbox" accessibilityState={{ checked: usePoints }} onPress={() => setUsePoints(!usePoints)} style={[s.chip, usePoints && s.chipOn, { borderRadius: 12 }]}>
              <Text style={s.chipText}>{usePoints ? "☑ " : "☐ "}{t("Use {n} points", { n: cart.points.max_use })} · {t("Takes {amount} off. You have {balance} points.", { amount: money(cart.points.max_use * cart.points.value, cur), balance: cart.points.balance })}</Text>
            </Pressable>
          ) : null}

          <Card style={{ gap: 10 }}>
            {cart.promo_code ? (
              <View style={s.row}>
                <Text style={s.val}>{t("Code")} {cart.promo_code}</Text>
                <Button title={t("Remove")} kind="outline" onPress={() => run(() => api<CartView>("/cart/promo", { method: "DELETE" }), setCart)} style={{ minHeight: 40 }} />
              </View>
            ) : (
              <View style={{ gap: 8 }}>
                <Field label={t("Discount code")} value={code} onChangeText={setCode} autoCapitalize="characters" />
                <Button title={t("Apply")} kind="outline" disabled={!code.trim()} onPress={() => run(() => api<CartView>("/cart/promo", { method: "POST", body: { code } }), (c) => { setCart(c); setCode(""); })} style={{ minHeight: 44 }} />
              </View>
            )}
            {cart.promo_problem ? <Text style={s.error}>{cart.promo_problem}</Text> : null}
          </Card>

          <Card style={{ gap: 8 }}>
            <View style={s.row}><Text style={s.key}>{t("Items")}</Text><Text style={s.val}>{money(cart.items_amount, cur)}</Text></View>
            {cart.discount_amount > 0 && <View style={s.row}><Text style={[s.key, { color: color.green }]}>{t("Discount")}</Text><Text style={[s.val, { color: color.green }]}>−{money(cart.discount_amount, cur)}</Text></View>}
            <View style={s.row}><Text style={s.key}>{t("Delivery")}</Text><Text style={s.val}>{cart.shipping_amount > 0 ? money(cart.shipping_amount, cur) : t("Free")}</Text></View>
            {cart.service_fee_amount > 0 && <View style={s.row}><Text style={s.key}>{t("Platform fee")}</Text><Text style={s.val}>{money(cart.service_fee_amount, cur)}</Text></View>}
            <View style={s.row}><Text style={[s.key, s.total]}>{t("Total to pay")}</Text><Text style={[s.val, s.total]}>{money(payable, cur)}</Text></View>
            {cart.eta_days ? <Text style={s.muted}>{t("Usually delivered in about {n} days.", { n: cart.eta_days })}</Text> : null}
          </Card>

          {message ? <Text style={s.error} accessibilityRole="alert">{message}</Text> : null}
          <Button title={`${t("Place order")} · ${money(payable, cur)}`} busy={busy} disabled={!cart.can_checkout || !form.line1 || !form.phone} onPress={placeOrder} />
        </ScrollView>
      )}
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  page: { flex: 1, backgroundColor: "#fff" },
  title: { fontSize: 24, fontWeight: "800", color: color.navy, padding: 16 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  muted: { color: color.slate, fontSize: 14 },
  name: { fontSize: 16, fontWeight: "700", color: color.navy },
  h: { fontSize: 16, fontWeight: "800", color: color.navy },
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  key: { color: color.slate, fontSize: 15 },
  val: { color: color.ink, fontSize: 15, fontWeight: "600" },
  total: { color: color.navy, fontWeight: "800", fontSize: 17 },
  error: { color: color.red, fontSize: 13 },
  qty: { flexDirection: "row", alignItems: "center", gap: 12 },
  qtyBtn: { width: 38, height: 38, borderRadius: radius.pill, borderWidth: 1.5, borderColor: color.navy, alignItems: "center", justifyContent: "center", backgroundColor: "#fff" },
  qtyText: { fontSize: 20, color: color.navy, fontWeight: "700" },
  qtyNum: { fontSize: 16, fontWeight: "700", color: color.navy, minWidth: 20, textAlign: "center" },
  chip: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: "#fff" },
  chipOn: { borderColor: color.blue, backgroundColor: color.wash },
  chipText: { color: color.navy, fontWeight: "600", fontSize: 13 },
});
