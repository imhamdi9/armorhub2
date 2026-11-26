import { Stack, router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { View } from "react-native";

import { api } from "@/api";
import { SearchBar } from "@/components/brand";
import { Results, type Hit } from "@/components/results";
import { MarketPill } from "@/components/market-ui";
import { Loading, Problem } from "@/components/ui";
import { useMarket } from "@/market";
import type { Paginated, Product, StoreCard } from "@/types";

/** Search across the stores: each store is asked and the answers are put together in one list. */
export default function SearchScreen() {
  const { q = "" } = useLocalSearchParams<{ q?: string }>();
  const [hits, setHits] = useState<Hit[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const { market } = useMarket();

  const load = useCallback(() => {
    setError(null);
    setHits(null);
    api<{ stores: StoreCard[] }>("/public/storefronts")
      .then(async ({ stores }) => {
        const lists = await Promise.all(stores.filter((st) => st.market === market?.code).slice(0, 8).map((st) =>
          api<Paginated<Product>>(`/public/storefronts/${encodeURIComponent(st.slug)}/products`, { params: { q, per_page: 24 } }).then((r) => r.data.map((product) => ({ product, slug: st.slug }))),
        ));
        setHits(lists.flat());
      })
      .catch(setError);
  }, [q, market]);
  useEffect(load, [load]);

  const header = (
    <View style={{ paddingHorizontal: 14, paddingBottom: 10, backgroundColor: "#fff", flexDirection: "row", alignItems: "center", gap: 10 }}>
      <View style={{ flex: 1 }}><SearchBar initial={q} onSubmit={(next) => router.setParams({ q: next })} /></View>
      <MarketPill />
    </View>
  );

  return (
    <View style={{ flex: 1 }}>
      <Stack.Screen options={{ title: "" }} />
      {error && !hits ? <Problem error={error} retry={load} /> : hits ? <Results hits={hits} header={header} /> : <Loading />}
    </View>
  );
}
