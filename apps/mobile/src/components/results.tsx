import { useState } from "react";
import { FlatList, Modal, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

import { ProductRow } from "@/components/brand";
import { t } from "@/i18n";
import { color, radius } from "@/theme";
import type { Product } from "@/types";

export type Hit = { product: Product; slug: string };
export type SortKey = "featured" | "price_asc" | "price_desc" | "name";

const SORTS: { key: SortKey; label: string }[] = [
  { key: "featured", label: "Featured" }, { key: "price_asc", label: "Price: low to high" }, { key: "price_desc", label: "Price: high to low" }, { key: "name", label: "Name" },
];

export function sortHits(hits: Hit[], sort: SortKey): Hit[] {
  const list = [...hits];
  if (sort === "price_asc") list.sort((a, b) => a.product.from_price - b.product.from_price);
  if (sort === "price_desc") list.sort((a, b) => b.product.from_price - a.product.from_price);
  if (sort === "name") list.sort((a, b) => a.product.name.localeCompare(b.product.name));
  return list;
}

/**
 * Results as a list of rich rows with a sort menu and category filter chips. Used by the search screen and by a store's page,
 * so a product looks and behaves the same wherever it is found.
 */
export function Results({ hits, header, onEnd }: { hits: Hit[]; header?: React.ReactNode; onEnd?: () => void }) {
  const [sort, setSort] = useState<SortKey>("featured");
  const [category, setCategory] = useState<string | null>(null);
  const [menu, setMenu] = useState(false);

  const categories = [...new Set(hits.map((h) => h.product.category).filter((c): c is string => !!c))];
  const filtered = sortHits(category ? hits.filter((h) => h.product.category === category) : hits, sort);
  const label = (c: string) => c.replace(/-/g, " ").replace(/^./, (x) => x.toUpperCase());

  return (
    <View style={{ flex: 1, backgroundColor: color.bone }}>
      {header}
      <View style={s.bar}>
        <Text style={s.count}><Text style={s.countNum}>{filtered.length}</Text> {t("results")}</Text>
        <Pressable onPress={() => setMenu(true)} style={s.pill} accessibilityRole="button"><Text style={s.pillText}>{t("Sort")}: {t(SORTS.find((x) => x.key === sort)?.label ?? "Featured")} ▾</Text></Pressable>
      </View>
      {categories.length > 1 && (
        <View style={{ backgroundColor: "#fff" }}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={s.chips}>
            <Pressable onPress={() => setCategory(null)} style={[s.chip, category === null && s.chipOn]}><Text style={[s.chipText, category === null && { color: "#fff" }]}>{t("All")}</Text></Pressable>
            {categories.map((c) => (
              <Pressable key={c} onPress={() => setCategory(category === c ? null : c)} style={[s.chip, category === c && s.chipOn]}><Text style={[s.chipText, category === c && { color: "#fff" }]}>{label(c)}</Text></Pressable>
            ))}
          </ScrollView>
        </View>
      )}

      {filtered.length === 0
        ? <View style={s.empty}><Text style={s.emptyText}>{t("No results.")}</Text></View>
        : <FlatList data={filtered} keyExtractor={(h) => `${h.slug}-${h.product.id}`} renderItem={({ item }) => <ProductRow product={item.product} slug={item.slug} />}
            contentContainerStyle={{ paddingBottom: 24 }} onEndReachedThreshold={0.4} onEndReached={onEnd} />}

      <Modal transparent visible={menu} animationType="fade" onRequestClose={() => setMenu(false)}>
        <Pressable style={s.scrim} onPress={() => setMenu(false)}>
          <View style={s.sheet}>
            <Text style={s.sheetTitle}>{t("Sort")}</Text>
            {SORTS.map((x) => (
              <Pressable key={x.key} onPress={() => { setSort(x.key); setMenu(false); }} style={s.option} accessibilityRole="radio" accessibilityState={{ selected: sort === x.key }}>
                <Text style={[s.optionText, sort === x.key && { color: color.blue, fontWeight: "800" }]}>{t(x.label)}</Text>
                {sort === x.key ? <Text style={{ color: color.blue, fontSize: 16 }}>✓</Text> : null}
              </Pressable>
            ))}
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

const s = StyleSheet.create({
  bar: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", paddingHorizontal: 14, paddingBottom: 10, backgroundColor: "#fff", borderBottomWidth: 1, borderBottomColor: color.fog },
  count: { color: color.slate, fontSize: 13 },
  countNum: { color: color.navy, fontWeight: "800" },
  pill: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 12, paddingVertical: 6, backgroundColor: "#fff" },
  pillText: { fontSize: 12.5, fontWeight: "600", color: color.navy },
  chips: { paddingHorizontal: 14, paddingVertical: 10, gap: 8 },
  chip: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 14, paddingVertical: 6, backgroundColor: "#fff" },
  chipOn: { backgroundColor: color.navy, borderColor: color.navy },
  chipText: { fontSize: 13, fontWeight: "600", color: color.navy },
  empty: { flex: 1, alignItems: "center", justifyContent: "center" },
  emptyText: { color: color.slate, fontSize: 16 },
  scrim: { flex: 1, backgroundColor: "rgba(11,27,58,.45)", justifyContent: "flex-end" },
  sheet: { backgroundColor: "#fff", borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 20, paddingBottom: 34, gap: 4 },
  sheetTitle: { fontSize: 18, fontWeight: "800", color: color.navy, marginBottom: 8 },
  option: { flexDirection: "row", justifyContent: "space-between", paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: color.bone },
  optionText: { fontSize: 16, color: color.navy },
});
