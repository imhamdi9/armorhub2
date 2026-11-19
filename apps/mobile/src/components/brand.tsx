import { Image } from "expo-image";
import { router } from "expo-router";
import { useState } from "react";
import { Pressable, StyleSheet, Text, TextInput, View, type ViewStyle } from "react-native";
import Svg, { Circle, Path, Rect } from "react-native-svg";

import { imageUrl } from "@/config";
import { money, t } from "@/i18n";
import { color, radius } from "@/theme";
import type { Product } from "@/types";

/** The platform's shield mark, as on the website. */
export function Logo({ size = 30 }: { size?: number }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 34 34">
      <Rect width="34" height="34" rx="10" fill={color.navy} />
      <Path d="M17 6.5 8.5 9.8v6.2c0 5.3 3.6 8.6 8.5 10.2 4.9-1.6 8.5-4.9 8.5-10.2V9.8L17 6.5Z" fill={color.sky} />
      <Path d="m12.8 16.6 3 3 5.4-6" fill="none" stroke={color.navy} strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

export function BigShield({ size = 120 }: { size?: number }) {
  return (
    <Svg width={size} height={size} viewBox="0 0 34 34">
      <Path d="M17 3.5 6.5 7.6v7.7c0 6.6 4.5 10.7 10.5 12.7 6-2 10.5-6.1 10.5-12.7V7.6L17 3.5Z" fill={color.sky} />
      <Path d="m11.5 16.5 4 4 7-8" fill="none" stroke={color.navy} strokeWidth={2.6} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

/** Tab-bar icons: the same line style everywhere. */
export function TabIcon({ name, active }: { name: "home" | "orders" | "cart" | "account"; active: boolean }) {
  const stroke = active ? color.blue : color.slate;
  const fill = active ? color.wash : "none";
  const common = { stroke, strokeWidth: 1.8, strokeLinecap: "round" as const, strokeLinejoin: "round" as const, fill };
  return (
    <Svg width={24} height={24} viewBox="0 0 24 24">
      {name === "home" && <Path {...common} d="M3 11 12 4l9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z" />}
      {name === "orders" && <Path {...common} d="M21 8 12 3 3 8v8l9 5 9-5ZM3 8l9 5 9-5M12 13v8" />}
      {name === "cart" && <Path {...common} d="M3 4h2l2.4 11h10.2L20 7H6.2M9 20a1 1 0 1 0 0-.01M17 20a1 1 0 1 0 0-.01" />}
      {name === "account" && (<><Circle {...common} cx="12" cy="8" r="4" /><Path {...common} d="M4 21a8 8 0 0 1 16 0" /></>)}
    </Svg>
  );
}

function SearchGlass() {
  return (
    <Svg width={18} height={18} viewBox="0 0 24 24">
      <Circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="#fff" strokeWidth={2.4} />
      <Path d="m15.5 15.5 5 5" stroke="#fff" strokeWidth={2.4} strokeLinecap="round" />
    </Svg>
  );
}

/** The search field at the top of the screens: a round field with the blue search button, as on the website. */
export function SearchBar({ initial = "", onSubmit, autoFocus, placeholder }: { initial?: string; onSubmit: (q: string) => void; autoFocus?: boolean; placeholder?: string }) {
  const [q, setQ] = useState(initial);
  return (
    <View style={s.search}>
      <TextInput value={q} onChangeText={setQ} placeholder={placeholder ?? t("Search products and stores")} placeholderTextColor="#7b86a3" style={s.searchInput}
        returnKeyType="search" onSubmitEditing={() => onSubmit(q.trim())} autoFocus={autoFocus} accessibilityLabel={t("Search")} />
      <Pressable onPress={() => onSubmit(q.trim())} style={s.searchBtn} accessibilityRole="button" accessibilityLabel={t("Search")}><SearchGlass /></Pressable>
    </View>
  );
}

export function Stars({ average, count }: { average: number | null | undefined; count?: number }) {
  if (average === null || average === undefined || !count) return null;
  const full = Math.round(average);
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 6 }} accessibilityLabel={`${average} / 5`}>
      <Text style={{ color: color.star, fontSize: 13, letterSpacing: 1 }}>{"★".repeat(full)}{"☆".repeat(5 - full)}</Text>
      <Text style={{ color: color.slate, fontSize: 12 }}>{count}</Text>
    </View>
  );
}

/** A price, with the old price struck through and the saving, when there is one. */
export function Price({ amount, was, currency, size = 17 }: { amount: number; was?: number | null; currency: string; size?: number }) {
  return (
    <View style={{ flexDirection: "row", alignItems: "baseline", gap: 6, flexWrap: "wrap" }}>
      <Text style={{ fontSize: size, fontWeight: "800", color: color.navy, letterSpacing: -0.5 }}>{money(amount, currency)}</Text>
      {was && was > amount ? <Text style={{ fontSize: Math.max(12, size - 6), color: color.muted, textDecorationLine: "line-through" }}>{money(was, currency)}</Text> : null}
    </View>
  );
}

export const savingPercent = (price: number, was?: number | null) => (was && was > price ? Math.round(((was - price) / was) * 100) : 0);

export function SaveTag({ percent, style }: { percent: number; style?: ViewStyle }) {
  if (percent < 1) return null;
  return <View style={[s.tag, style]}><Text style={s.tagText}>{t("Save {n}%", { n: percent })}</Text></View>;
}

/** The product picture on its soft blue tile; the first letter when the seller has not added a photo. */
export function Picture({ product, style, letterSize = 36 }: { product: Pick<Product, "name" | "images">; style?: ViewStyle; letterSize?: number }) {
  const src = imageUrl(product.images?.[0]);
  return (
    <View style={[s.tile, style]}>
      {src ? <Image source={src} style={StyleSheet.absoluteFill} contentFit="cover" /> : <Text style={{ fontSize: letterSize, fontWeight: "800", color: color.blue }}>{product.name.slice(0, 1)}</Text>}
    </View>
  );
}

/** A product as a row in a list (results): picture, name, stars, price, delivery and seller. */
export function ProductRow({ product, slug }: { product: Product; slug: string }) {
  const save = savingPercent(product.from_price, product.compare_at_price);
  return (
    <Pressable onPress={() => router.push({ pathname: "/product/[slug]/[id]", params: { slug, id: String(product.id) } })} style={s.row} accessibilityRole="button" accessibilityLabel={product.name}>
      <View>
        <Picture product={product} style={{ width: 112, height: 128 }} />
        <SaveTag percent={save} style={{ position: "absolute", top: 8, start: 8 }} />
      </View>
      <View style={{ flex: 1, gap: 4 }}>
        <Text style={s.rowName} numberOfLines={3}>{product.name}</Text>
        <Stars average={product.rating?.average} count={product.rating?.count} />
        <Price amount={product.from_price} was={product.compare_at_price} currency={product.currency} size={20} />
        {product.requires_shipping ? <Text style={s.ok}>{t("Free delivery")}</Text> : <Text style={s.chip}>{t("Installers nearby")}</Text>}
        <Text style={s.seller} numberOfLines={1}>{[product.seller, product.warranty_years ? `${product.warranty_years} ${t("year warranty")}` : null].filter(Boolean).join(" · ")}</Text>
      </View>
    </Pressable>
  );
}

/** A product as a card in a horizontal strip (home). */
export function ProductTile({ product, slug }: { product: Product; slug: string }) {
  const save = savingPercent(product.from_price, product.compare_at_price);
  return (
    <Pressable onPress={() => router.push({ pathname: "/product/[slug]/[id]", params: { slug, id: String(product.id) } })} style={s.tileCard} accessibilityRole="button" accessibilityLabel={product.name}>
      <View>
        <Picture product={product} style={{ height: 118 }} />
        <SaveTag percent={save} style={{ position: "absolute", top: 8, start: 8 }} />
      </View>
      <Text style={s.tileName} numberOfLines={2}>{product.name}</Text>
      <View style={{ paddingHorizontal: 4 }}><Price amount={product.from_price} was={product.compare_at_price} currency={product.currency} /></View>
    </Pressable>
  );
}

export function SectionTitle({ title, action, onAction }: { title: string; action?: string; onAction?: () => void }) {
  return (
    <View style={s.sectionTitle}>
      <Text style={s.h2}>{title}</Text>
      {action ? <Pressable onPress={onAction} accessibilityRole="button"><Text style={s.link}>{action}</Text></Pressable> : null}
    </View>
  );
}

const s = StyleSheet.create({
  search: { flexDirection: "row", alignItems: "center", height: 48, borderRadius: radius.pill, borderWidth: 1.5, borderColor: color.fog, backgroundColor: color.bone, paddingStart: 16, paddingEnd: 6 },
  searchInput: { flex: 1, fontSize: 15, color: color.ink, height: 46, outlineStyle: "none" } as never,
  searchBtn: { width: 36, height: 36, borderRadius: 18, backgroundColor: color.blue, alignItems: "center", justifyContent: "center" },
  tag: { backgroundColor: color.navy, borderRadius: radius.pill, paddingHorizontal: 8, paddingVertical: 3 },
  tagText: { color: "#fff", fontSize: 10.5, fontWeight: "700" },
  tile: { backgroundColor: color.wash, borderRadius: 16, alignItems: "center", justifyContent: "center", overflow: "hidden" },
  row: { flexDirection: "row", gap: 12, backgroundColor: "#fff", borderWidth: 1, borderColor: color.fog, borderRadius: radius.big - 2, padding: 10, marginHorizontal: 14, marginTop: 10 },
  rowName: { fontSize: 15, fontWeight: "700", color: color.navy, lineHeight: 19 },
  ok: { color: color.green, fontWeight: "700", fontSize: 12.5 },
  chip: { alignSelf: "flex-start", backgroundColor: color.wash, color: color.blueInk, fontWeight: "700", fontSize: 11.5, borderRadius: radius.pill, paddingHorizontal: 9, paddingVertical: 3, overflow: "hidden" },
  seller: { color: color.slate, fontSize: 12 },
  tileCard: { width: 146, backgroundColor: "#fff", borderWidth: 1, borderColor: color.fog, borderRadius: radius.card, padding: 8, paddingBottom: 12, gap: 4 },
  tileName: { fontSize: 13, fontWeight: "600", color: color.navy, marginHorizontal: 4, marginTop: 4, minHeight: 34, lineHeight: 17 },
  sectionTitle: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginHorizontal: 14, marginTop: 18, marginBottom: 10 },
  h2: { fontSize: 18, fontWeight: "800", color: color.navy, letterSpacing: -0.3 },
  link: { fontSize: 13, fontWeight: "600", color: color.blue },
});
