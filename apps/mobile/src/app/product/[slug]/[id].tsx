import { Image } from "expo-image";
import * as Linking from "expo-linking";
import { router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { NativeScrollEvent, NativeSyntheticEvent, ScrollView, StyleSheet, Text, View, useWindowDimensions } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { api } from "@/api";
import { Picture, Price, SaveTag, Stars, savingPercent } from "@/components/brand";
import { Button, Card, Loading, Problem, messageOf } from "@/components/ui";
import { WEB_URL, imageUrl } from "@/config";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color, radius } from "@/theme";
import type { ProductPage } from "@/types";

/** A product, laid out for buying: pictures, price, options, what is promised, and a bar that stays at the bottom with the total and the buy button. */
export default function ProductScreen() {
  const { slug, id } = useLocalSearchParams<{ slug: string; id: string }>();
  const { user } = useSession();
  const { width } = useWindowDimensions();
  const [data, setData] = useState<ProductPage | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [picked, setPicked] = useState<number | null>(null);
  const [slide, setSlide] = useState(0);
  const [saved, setSaved] = useState(false);
  const [note, setNote] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api<ProductPage>(`/public/storefronts/${encodeURIComponent(slug)}/products/${id}`).then(setData).catch(setError);
  }, [slug, id]);
  useEffect(load, [load]);
  useEffect(() => {
    if (user) api<number[]>("/favourites/ids").then((ids) => setSaved(ids.includes(Number(id)))).catch(() => {});
  }, [user, id]);

  if (error) return <Problem error={error} retry={load} />;
  if (!data) return <Loading />;

  const p = data.product;
  const options = p.prices.filter((r) => r.target_id !== undefined);
  const shown = picked !== null ? (options[picked]?.price ?? p.from_price) : p.from_price;
  const goods = !!p.requires_shipping;
  const page = p.page ?? {};
  const onWebOnly = goods && p.installation === "required";
  const byQuote = p.sale_mode === "quote";
  const fee = data.customer_fee_percentage ?? 0;
  const save = savingPercent(shown, p.compare_at_price);
  const photos = p.images?.length ? p.images : [null];
  const galleryWidth = width - 28;
  const stock = p.in_stock !== false;

  function buy() {
    if (!user) return router.push("/login");
    router.push({ pathname: "/checkout/[slug]/[id]", params: { slug, id: String(p.id), option: picked !== null ? String(picked) : "" } });
  }
  async function toggleSaved() {
    if (!user) return router.push("/login");
    const next = !saved;
    setSaved(next);
    try { await (next ? api("/favourites", { method: "POST", body: { product_id: p.id } }) : api(`/favourites/${p.id}`, { method: "DELETE" })); } catch { setSaved(!next); }
  }
  async function requestPrice() {
    if (!user) return router.push("/login");
    setNote(null);
    try { await api("/quote-requests", { method: "POST", body: { product_id: p.id, quantity: 1 } }); router.push("/quotes"); } catch (e) { setNote(messageOf(e)); }
  }
  async function addToCart() {
    if (!user) return router.push("/login");
    setNote(null);
    try {
      await api("/cart/items", { method: "POST", body: { product_id: p.id, quantity: 1, ...(picked !== null && options[picked]?.target_id ? { option_id: options[picked].target_id } : {}) } });
      setNote(t("Added to your cart."));
    } catch (e) { setNote(messageOf(e)); }
  }
  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => setSlide(Math.round(e.nativeEvent.contentOffset.x / galleryWidth));

  return (
    <View style={{ flex: 1, backgroundColor: color.bone }}>
      <ScrollView contentContainerStyle={{ paddingBottom: 130 }}>
        <View style={[s.gallery, { width: galleryWidth }]}>
          <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false} onScroll={onScroll} scrollEventThrottle={16}>
            {photos.map((src, i) => (
              imageUrl(src) ? <Image key={i} source={imageUrl(src)} style={{ width: galleryWidth, height: 300 }} contentFit="cover" /> : <Picture key={i} product={p} style={{ width: galleryWidth, height: 300, borderRadius: 0 }} letterSize={96} />
            ))}
          </ScrollView>
          <SaveTag percent={save} style={{ position: "absolute", top: 14, start: 14 }} />
          {photos.length > 1 && <View style={s.dots}>{photos.map((_, i) => <View key={i} style={[s.dot, i === slide && s.dotOn]} />)}</View>}
        </View>

        <View style={s.box}>
          {(p.seller || p.brand) ? <Text style={s.by}>{[p.brand, p.seller].filter(Boolean).join(" · ")}</Text> : null}
          <Text style={s.title}>{page.headline ? `${p.name}: ${page.headline}` : p.name}</Text>
          <Stars average={p.rating?.average} count={p.rating?.count} />
          <View style={{ marginTop: 6 }}><Price amount={shown} was={p.compare_at_price} currency={p.currency} size={32} /></View>
          <Text style={s.note}>{t("Includes VAT")}{fee > 0 ? ` · ${t("A platform fee of {n}% is added at payment.", { n: fee })}` : ""}</Text>

          {options.length > 1 && (
            <View style={s.opts}>
              {options.map((o, i) => (
                <Button key={o.target} title={o.target} kind="outline" onPress={() => setPicked(picked === i ? null : i)}
                  style={[s.opt, picked === i && s.optOn] as never} />
              ))}
            </View>
          )}

          <Button title={saved ? `♥  ${t("Saved to favourites")}` : `♡  ${t("Save to favourites")}`} kind="outline" onPress={toggleSaved} style={{ minHeight: 44, marginTop: 6 }} />
          {goods && !onWebOnly && !byQuote && stock ? <Button title={t("Add to cart")} kind="outline" onPress={addToCart} style={{ minHeight: 44 }} /> : null}
          {note ? <Text style={s.note} accessibilityRole="alert">{note}</Text> : null}
          <Text style={[s.stock, !stock && { color: color.red }]}>{stock ? t("In stock") : t("Out of stock")}{stock && p.units_left ? ` · ${t("Only {n} left", { n: p.units_left })}` : ""}</Text>
          <View style={s.trust}>
            <Trust icon="🔒" text={t("Secure payment")} />
            <Trust icon="↩" text={t("Cancel before you pay")} />
            {p.warranty_years ? <Trust icon="🛡" text={`${p.warranty_years} ${t("year warranty")}`} /> : null}
          </View>
        </View>

        {p.highlights && p.highlights.length > 0 && (
          <Card style={s.block}>
            <Text style={s.h}>{t("Highlights")}</Text>
            {p.highlights.map((h) => <Text key={h} style={s.body}>✓  {h}</Text>)}
          </Card>
        )}
        {p.description ? <Card style={s.block}><Text style={s.h}>{t("Description")}</Text><Text style={s.body}>{p.description}</Text></Card> : null}
        {page.specs && page.specs.length > 0 && (
          <Card style={s.block}>
            <Text style={s.h}>{t("Specifications")}</Text>
            {page.specs.map((r) => <View key={r.label} style={s.spec}><Text style={s.specKey}>{r.label}</Text><Text style={s.specVal}>{r.value}</Text></View>)}
          </Card>
        )}
        {page.faqs && page.faqs.length > 0 && (
          <Card style={s.block}>
            <Text style={s.h}>{t("Questions")}</Text>
            {page.faqs.map((f) => <View key={f.q} style={{ gap: 2, marginBottom: 8 }}><Text style={s.q}>{f.q}</Text><Text style={s.body}>{f.a}</Text></View>)}
          </Card>
        )}
      </ScrollView>

      <SafeAreaView edges={["bottom"]} style={s.bar}>
        <View>
          <Text style={s.barLabel}>{t("Total")}</Text>
          <Text style={s.barTotal}>{money(shown, p.currency)}</Text>
        </View>
        {onWebOnly
          ? <Button title={t("Open the website")} kind="outline" onPress={() => Linking.openURL(`${WEB_URL}/s/${slug}/products/${p.id}`)} style={{ flex: 1 }} />
          : byQuote ? <Button title={t("Request a price")} onPress={requestPrice} style={{ flex: 1 }} /> : <Button title={goods ? t("Buy now") : t("Request a quote")} onPress={buy} disabled={!stock} style={{ flex: 1 }} />}
      </SafeAreaView>
    </View>
  );
}

function Trust({ icon, text }: { icon: string; text: string }) {
  return <View style={s.trustItem}><Text style={{ fontSize: 18 }}>{icon}</Text><Text style={s.trustText}>{text}</Text></View>;
}

const s = StyleSheet.create({
  gallery: { alignSelf: "center", marginTop: 8, borderRadius: radius.big, overflow: "hidden", borderWidth: 1, borderColor: color.fog, backgroundColor: color.wash },
  dots: { position: "absolute", bottom: 12, alignSelf: "center", flexDirection: "row", gap: 5 },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: "#c4d0ea" },
  dotOn: { width: 20, backgroundColor: color.navy },
  box: { backgroundColor: "#fff", borderRadius: radius.big, borderWidth: 1, borderColor: color.fog, padding: 16, marginHorizontal: 14, marginTop: 10, gap: 6 },
  by: { color: color.blue, fontWeight: "700", fontSize: 12 },
  title: { fontSize: 19, fontWeight: "800", color: color.navy, lineHeight: 24, letterSpacing: -0.3 },
  note: { fontSize: 12, color: color.slate },
  opts: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginTop: 8 },
  opt: { minHeight: 38, paddingHorizontal: 14, borderWidth: 1.5, borderColor: color.fog },
  optOn: { borderColor: color.blue, backgroundColor: color.wash },
  stock: { color: color.green, fontWeight: "700", fontSize: 14, marginTop: 8 },
  trust: { flexDirection: "row", gap: 6, marginTop: 8 },
  trustItem: { flex: 1, backgroundColor: color.bone, borderWidth: 1, borderColor: color.fog, borderRadius: 16, paddingVertical: 9, paddingHorizontal: 4, alignItems: "center", gap: 2 },
  trustText: { fontSize: 11, fontWeight: "600", color: "#33415f", textAlign: "center" },
  block: { marginHorizontal: 14, marginTop: 10, gap: 6 },
  h: { fontSize: 16, fontWeight: "800", color: color.navy },
  body: { fontSize: 15, color: color.ink, lineHeight: 22 },
  q: { fontSize: 15, fontWeight: "700", color: color.navy },
  spec: { flexDirection: "row", justifyContent: "space-between", borderBottomWidth: 1, borderColor: color.fog, paddingVertical: 8 },
  specKey: { color: color.slate, fontSize: 14, flex: 1 },
  specVal: { color: color.ink, fontSize: 14, fontWeight: "600", flex: 1, textAlign: "right" },
  bar: { position: "absolute", bottom: 0, start: 0, end: 0, flexDirection: "row", alignItems: "center", gap: 16, paddingHorizontal: 14, paddingTop: 10, backgroundColor: "#fff", borderTopWidth: 1, borderColor: color.fog },
  barLabel: { fontSize: 12, color: color.slate },
  barTotal: { fontSize: 20, fontWeight: "800", color: color.navy, letterSpacing: -0.5 },
});
