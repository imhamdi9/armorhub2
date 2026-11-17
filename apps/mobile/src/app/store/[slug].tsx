import { Image } from "expo-image";
import { Link, Stack, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, TextInput, View } from "react-native";

import { api } from "@/api";
import { Loading, Problem } from "@/components/ui";
import { imageUrl } from "@/config";
import { money, t } from "@/i18n";
import { color, radius } from "@/theme";
import type { Paginated, Product } from "@/types";

/** A store's products, searchable, a page at a time. */
export default function StoreScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const [name, setName] = useState("");
  const [q, setQ] = useState("");
  const [items, setItems] = useState<Product[] | null>(null);
  const [page, setPage] = useState(1);
  const [last, setLast] = useState(1);
  const [error, setError] = useState<unknown>(null);

  useEffect(() => {
    api<{ store: { name: string } }>(`/public/storefronts/${encodeURIComponent(slug)}`).then((r) => setName(r.store.name)).catch(() => undefined);
  }, [slug]);

  const load = useCallback((p: number, query: string) => {
    setError(null);
    api<Paginated<Product>>(`/public/storefronts/${encodeURIComponent(slug)}/products`, { params: { page: p, q: query, per_page: 12 } })
      .then((r) => { setItems((cur) => (p === 1 ? r.data : [...(cur ?? []), ...r.data])); setPage(r.current_page); setLast(r.last_page); })
      .catch(setError);
  }, [slug]);

  // Searching waits for a short pause in typing, then starts again from the first page.
  useEffect(() => {
    const id = setTimeout(() => load(1, q), 300);
    return () => clearTimeout(id);
  }, [q, load]);

  if (error && !items) return <Problem error={error} retry={() => load(1, q)} />;

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: name }} />
      <TextInput value={q} onChangeText={setQ} placeholder={t("Search")} placeholderTextColor="#9aa5c0" style={s.search} accessibilityLabel={t("Search")} returnKeyType="search" />
      {!items ? <Loading /> : items.length === 0 ? <View style={s.center}><Text style={s.muted}>{t("No products found.")}</Text></View> : (
        <FlatList data={items} keyExtractor={(p) => String(p.id)} numColumns={2} columnWrapperStyle={{ gap: 12 }} contentContainerStyle={{ padding: 16, gap: 12 }}
          onEndReachedThreshold={0.4} onEndReached={() => { if (page < last) load(page + 1, q); }}
          renderItem={({ item }) => (
            <Link href={{ pathname: "/product/[slug]/[id]", params: { slug, id: String(item.id) } }} asChild>
              <Pressable style={s.card} accessibilityRole="button">
                {imageUrl(item.images?.[0]) ? <Image source={imageUrl(item.images?.[0])} style={s.img} contentFit="cover" /> : <View style={[s.img, s.noImg]}><Text style={s.letter}>{item.name.slice(0, 1)}</Text></View>}
                <Text style={s.name} numberOfLines={2}>{item.name}</Text>
                <Text style={s.price}>{item.requires_shipping === false ? `${t("From")} ` : ""}{money(item.from_price, item.currency)}</Text>
              </Pressable>
            </Link>
          )} />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  search: { margin: 16, marginBottom: 0, minHeight: 48, borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 18, fontSize: 16, color: color.ink },
  center: { flex: 1, alignItems: "center", justifyContent: "center" },
  muted: { color: color.slate, fontSize: 16 },
  card: { flex: 1, backgroundColor: "#fff", borderWidth: 1, borderColor: color.fog, borderRadius: radius.card, overflow: "hidden", paddingBottom: 12 },
  img: { width: "100%", aspectRatio: 1 },
  noImg: { backgroundColor: color.wash, alignItems: "center", justifyContent: "center" },
  letter: { fontSize: 36, color: color.blue, fontWeight: "800" },
  name: { fontSize: 14, fontWeight: "700", color: color.navy, paddingHorizontal: 12, paddingTop: 10 },
  price: { fontSize: 14, color: color.blue, fontWeight: "800", paddingHorizontal: 12, paddingTop: 4 },
});
