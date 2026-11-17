import { Stack, router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { View } from "react-native";

import { api } from "@/api";
import { SearchBar } from "@/components/brand";
import { Results, type Hit } from "@/components/results";
import { Loading, Problem } from "@/components/ui";
import type { Paginated, Product } from "@/types";

/** One store's products, a page at a time, in the same rows as the search results. */
export default function StoreScreen() {
  const { slug, q = "" } = useLocalSearchParams<{ slug: string; q?: string }>();
  const [name, setName] = useState("");
  const [items, setItems] = useState<Product[] | null>(null);
  const [page, setPage] = useState(1);
  const [last, setLast] = useState(1);
  const [error, setError] = useState<unknown>(null);

  useEffect(() => {
    api<{ store: { name: string } }>(`/public/storefronts/${encodeURIComponent(slug)}`).then((r) => setName(r.store.name)).catch(() => undefined);
  }, [slug]);

  const load = useCallback((p: number) => {
    setError(null);
    api<Paginated<Product>>(`/public/storefronts/${encodeURIComponent(slug)}/products`, { params: { page: p, q, per_page: 12 } })
      .then((r) => { setItems((cur) => (p === 1 ? r.data : [...(cur ?? []), ...r.data])); setPage(r.current_page); setLast(r.last_page); })
      .catch(setError);
  }, [slug, q]);
  useEffect(() => { setItems(null); load(1); }, [load]);

  const hits: Hit[] = (items ?? []).map((product) => ({ product, slug }));
  const header = (
    <View style={{ paddingHorizontal: 14, paddingBottom: 10, backgroundColor: "#fff" }}>
      <SearchBar initial={q} onSubmit={(next) => router.setParams({ q: next })} />
    </View>
  );

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: name }} />
      {error && !items ? <Problem error={error} retry={() => load(1)} /> : items ? <Results hits={hits} header={header} onEnd={() => { if (page < last) load(page + 1); }} /> : <Loading />}
    </View>
  );
}
