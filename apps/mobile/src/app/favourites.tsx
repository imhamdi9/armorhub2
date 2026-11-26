import { Image } from "expo-image";
import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Picture } from "@/components/brand";
import { Button, Card, Loading, Problem } from "@/components/ui";
import { imageUrl } from "@/config";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import type { Product } from "@/types";

/** Products the person saved with the heart. */
export default function FavouritesScreen() {
  const { user } = useSession();
  const [rows, setRows] = useState<Product[] | null>(null);
  const [error, setError] = useState<unknown>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<Product[]>("/favourites").then(setRows).catch(setError);
  }, [user]);
  useFocusEffect(load);

  if (!user) return <View style={s.center}><Text style={s.muted}>{t("Sign in to see your favourites")}</Text><Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} /></View>;
  if (error && !rows) return <Problem error={error} retry={load} />;
  if (!rows) return <Loading />;
  if (rows.length === 0) return <View style={s.center}><Text style={s.muted}>{t("Nothing saved yet. Tap the heart on a product to keep it here.")}</Text></View>;

  return (
    <FlatList
      data={rows}
      keyExtractor={(p) => String(p.id)}
      contentContainerStyle={{ padding: 14, gap: 12 }}
      renderItem={({ item: p }) => (
        <Card style={{ gap: 8 }}>
          <Pressable onPress={() => router.push({ pathname: "/product/[slug]/[id]", params: { slug: user.store_slug ?? "", id: String(p.id) } })} style={s.row}>
            {imageUrl(p.images?.[0]) ? <Image source={imageUrl(p.images?.[0])} style={s.img} contentFit="cover" /> : <Picture product={p} style={s.img} letterSize={36} />}
            <View style={{ flex: 1 }}>
              <Text style={s.name}>{p.name}</Text>
              <Text style={s.muted}>{money(p.from_price, p.currency)}</Text>
            </View>
          </Pressable>
          <Button title={t("Remove")} kind="outline" onPress={() => api(`/favourites/${p.id}`, { method: "DELETE" }).then(load).catch(() => {})} style={{ minHeight: 40 }} />
        </Card>
      )}
    />
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  muted: { color: color.slate, fontSize: 14, textAlign: "center" },
  row: { flexDirection: "row", gap: 12, alignItems: "center" },
  img: { width: 72, height: 72, borderRadius: 14 },
  name: { fontSize: 16, fontWeight: "700", color: color.navy },
});
