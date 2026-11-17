import { router } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { api } from "@/api";
import { BigShield, Logo, ProductTile, SearchBar, SectionTitle, savingPercent } from "@/components/brand";
import { MarketPill } from "@/components/market-ui";
import { Loading, Problem } from "@/components/ui";
import { useMarket } from "@/market";
import { t } from "@/i18n";
import { color, radius } from "@/theme";
import type { Product, Showcase } from "@/types";

type Entry = { product: Product; slug: string };

/** Home: search first, then a banner, categories, deals and the stores, as on the website but made for the thumb. */
export default function HomeScreen() {
  const [data, setData] = useState<Showcase | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [category, setCategory] = useState<string | null>(null);
  const { market } = useMarket();

  const load = useCallback(() => {
    setError(null);
    api<Showcase>("/public/showcase").then(setData).catch(setError);
  }, []);
  useEffect(load, [load]);

  const { stores, entries, categories } = useMemo(() => {
    // Only the stores of the visitor's market: their products and prices are in that market's currency.
    const stores = (data?.industries ?? []).flatMap((i) => i.stores).filter((st) => st.market === market?.code);
    const entries: Entry[] = stores.flatMap((st) => st.products.map((product) => ({ product, slug: st.slug })));
    const categories = [...new Set(entries.map((e) => e.product.category).filter((c): c is string => !!c))];
    return { stores, entries, categories };
  }, [data, market]);

  if (error && !data) return <Problem error={error} retry={load} />;
  if (!data) return <Loading />;

  const shown = category ? entries.filter((e) => e.product.category === category) : entries;
  const deals = shown.filter((e) => savingPercent(e.product.from_price, e.product.compare_at_price) > 0);
  const label = (c: string) => c.replace(/-/g, " ").replace(/^./, (x) => x.toUpperCase());
  const search = (q: string) => router.push({ pathname: "/search", params: q ? { q } : {} });

  return (
    <SafeAreaView edges={["top"]} style={{ flex: 1, backgroundColor: "#fff" }}>
      <View style={s.header}>
        <View style={s.brandRow}><View style={s.brand}><Logo /><Text style={s.brandName}>ArmorHub</Text></View><MarketPill /></View>
        <SearchBar onSubmit={search} />
      </View>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.chips} style={{ flexGrow: 0 }}>
        <Chip title={t("All")} on={category === null} onPress={() => setCategory(null)} />
        {categories.map((c) => <Chip key={c} title={label(c)} on={category === c} onPress={() => setCategory(category === c ? null : c)} />)}
      </ScrollView>

      <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ paddingBottom: 28 }}>
        <View style={s.hero}>
          <View style={{ flex: 1, zIndex: 1 }}>
            <Text style={s.heroTitle}>{t("Protect your ride this season")}</Text>
            <Text style={s.heroSub}>{t("Verified installers near you")}</Text>
            <Pressable style={s.heroBtn} onPress={() => search("")} accessibilityRole="button"><Text style={s.heroBtnText}>{t("Shop now")}</Text></Pressable>
          </View>
          <View style={s.heroShield}><BigShield size={124} /></View>
        </View>

        {deals.length > 0 && (
          <>
            <SectionTitle title={t("Deals for you")} />
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.strip}>
              {deals.map((e) => <ProductTile key={`${e.slug}-${e.product.id}`} product={e.product} slug={e.slug} />)}
            </ScrollView>
          </>
        )}

        <SectionTitle title={category ? label(category) : t("Popular products")} />
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.strip}>
          {shown.map((e) => <ProductTile key={`all-${e.slug}-${e.product.id}`} product={e.product} slug={e.slug} />)}
        </ScrollView>

        <SectionTitle title={t("Top rated stores")} />
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.strip}>
          {stores.map((st) => (
            <Pressable key={st.slug} style={s.store} accessibilityRole="button" onPress={() => router.push({ pathname: "/store/[slug]", params: { slug: st.slug } })}>
              <View style={[s.storeLogo, { backgroundColor: st.primary_color || color.blue }]}><Text style={s.storeLetter}>{st.name.slice(0, 1)}</Text></View>
              <Text style={s.storeName} numberOfLines={1}>{st.name}</Text>
            </Pressable>
          ))}
        </ScrollView>
      </ScrollView>
    </SafeAreaView>
  );
}

function Chip({ title, on, onPress }: { title: string; on: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[s.chip, on && s.chipOn]} accessibilityRole="button" accessibilityState={{ selected: on }}>
      <Text style={[s.chipText, on && { color: "#fff" }]}>{title}</Text>
    </Pressable>
  );
}

const s = StyleSheet.create({
  header: { paddingHorizontal: 14, paddingTop: 6, paddingBottom: 10, gap: 10, backgroundColor: "#fff" },
  brandRow: { flexDirection: "row", alignItems: "center", justifyContent: "space-between" },
  brand: { flexDirection: "row", alignItems: "center", gap: 8 },
  brandName: { fontSize: 19, fontWeight: "800", color: color.navy, letterSpacing: -0.3 },
  chips: { paddingHorizontal: 14, paddingBottom: 12, gap: 8, alignItems: "center" },
  chip: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 15, paddingVertical: 7, backgroundColor: "#fff" },
  chipOn: { backgroundColor: color.navy, borderColor: color.navy },
  chipText: { fontSize: 13, fontWeight: "600", color: color.navy },
  hero: { flexDirection: "row", marginHorizontal: 14, marginTop: 8, minHeight: 168, borderRadius: radius.big, backgroundColor: color.navy, padding: 20, overflow: "hidden" },
  heroTitle: { color: "#fff", fontSize: 24, fontWeight: "800", letterSpacing: -0.6, lineHeight: 27, maxWidth: 200 },
  heroSub: { color: color.sky, fontSize: 13, marginTop: 8 },
  heroBtn: { alignSelf: "flex-start", backgroundColor: color.blue, borderRadius: radius.pill, paddingHorizontal: 18, paddingVertical: 9, marginTop: 16 },
  heroBtnText: { color: "#fff", fontWeight: "700", fontSize: 13 },
  heroShield: { position: "absolute", end: -8, top: 22 },
  strip: { paddingHorizontal: 14, gap: 12 },
  store: { flexDirection: "row", alignItems: "center", gap: 10, backgroundColor: "#fff", borderWidth: 1, borderColor: color.fog, borderRadius: radius.card, padding: 10, paddingEnd: 18, minWidth: 190 },
  storeLogo: { width: 46, height: 46, borderRadius: 14, alignItems: "center", justifyContent: "center" },
  storeLetter: { color: "#fff", fontWeight: "800", fontSize: 20 },
  storeName: { fontWeight: "800", color: color.navy, fontSize: 15, flexShrink: 1 },
});
