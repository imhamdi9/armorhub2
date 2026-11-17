import { Image } from "expo-image";
import { Link } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { imageUrl } from "@/config";
import { t } from "@/i18n";
import { color, radius } from "@/theme";
import type { StoreCard } from "@/types";
import { Loading, Problem } from "@/components/ui";

/** The markets (stores) a customer can shop in. */
export default function StoresScreen() {
  const [stores, setStores] = useState<StoreCard[] | null>(null);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(() => {
    setError(null);
    api<{ stores: StoreCard[] }>("/public/storefronts").then((r) => setStores(r.stores)).catch(setError);
  }, []);
  useEffect(load, [load]);

  if (error) return <Problem error={error} retry={load} />;
  if (!stores) return <Loading />;

  return (
    <FlatList data={stores} keyExtractor={(s) => s.slug} contentContainerStyle={{ padding: 16, gap: 12 }}
      renderItem={({ item }) => (
        <Link href={{ pathname: "/store/[slug]", params: { slug: item.slug } }} asChild>
          <Pressable style={s.card} accessibilityRole="button">
            <View style={[s.logo, { backgroundColor: item.primary_color || color.blue }]}>
              {imageUrl(item.logo_url) ? <Image source={imageUrl(item.logo_url)} style={s.logoImg} contentFit="contain" /> : <Text style={s.logoLetter}>{item.name.slice(0, 1)}</Text>}
            </View>
            <View style={{ flex: 1 }}>
              <Text style={s.name}>{item.name}</Text>
              {item.headline ? <Text style={s.headline} numberOfLines={2}>{item.headline}</Text> : null}
              {item.industry ? <Text style={s.tag}>{item.industry.name}</Text> : null}
            </View>
          </Pressable>
        </Link>
      )} />
  );
}

const s = StyleSheet.create({
  card: { flexDirection: "row", gap: 14, alignItems: "center", backgroundColor: "#fff", borderWidth: 1, borderColor: color.fog, borderRadius: radius.card, padding: 14 },
  logo: { width: 56, height: 56, borderRadius: 16, alignItems: "center", justifyContent: "center", overflow: "hidden" },
  logoImg: { width: 44, height: 44 },
  logoLetter: { color: "#fff", fontSize: 24, fontWeight: "800" },
  name: { fontSize: 17, fontWeight: "800", color: color.navy },
  headline: { fontSize: 13, color: color.slate, marginTop: 2 },
  tag: { fontSize: 12, color: color.blue, fontWeight: "700", marginTop: 4 },
});
