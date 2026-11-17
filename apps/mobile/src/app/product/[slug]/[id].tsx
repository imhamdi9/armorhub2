import { Image } from "expo-image";
import * as Linking from "expo-linking";
import { router, useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { Dimensions, ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Loading, Problem } from "@/components/ui";
import { WEB_URL, imageUrl } from "@/config";
import { money, t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import type { ProductPage } from "@/types";

const WIDTH = Dimensions.get("window").width;

/** A product: pictures, price, what it offers and what the seller wrote. Buying needs an account (the same one as on the website). */
export default function ProductScreen() {
  const { slug, id } = useLocalSearchParams<{ slug: string; id: string }>();
  const { user } = useSession();
  const [data, setData] = useState<ProductPage | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [picked, setPicked] = useState<number | null>(null);

  const load = useCallback(() => {
    setError(null);
    api<ProductPage>(`/public/storefronts/${encodeURIComponent(slug)}/products/${id}`).then(setData).catch(setError);
  }, [slug, id]);
  useEffect(load, [load]);

  if (error) return <Problem error={error} retry={load} />;
  if (!data) return <Loading />;

  const p = data.product;
  const options = p.prices.filter((r) => r.target_id !== undefined);
  const shown = picked !== null ? (options[picked]?.price ?? p.from_price) : p.from_price;
  const goods = !!p.requires_shipping;
  const page = p.page ?? {};
  // Buying together with an installation offer is chosen on the website in this version.
  const onWebOnly = goods && p.installation === "required";
  const fee = data.customer_fee_percentage ?? 0;

  function buy() {
    if (!user) return router.push("/login");
    router.push({ pathname: "/checkout/[slug]/[id]", params: { slug, id: String(p.id), option: picked !== null ? String(picked) : "" } });
  }

  return (
    <View style={{ flex: 1 }}>
      <ScrollView contentContainerStyle={{ paddingBottom: 120 }}>
        <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
          {(p.images?.length ? p.images : [null]).map((src, i) => (
            imageUrl(src) ? <Image key={i} source={imageUrl(src)} style={{ width: WIDTH, height: WIDTH }} contentFit="cover" /> : <View key={i} style={[{ width: WIDTH, height: WIDTH }, s.noImg]}><Text style={s.letter}>{p.name.slice(0, 1)}</Text></View>
          ))}
        </ScrollView>

        <View style={{ padding: 16, gap: 14 }}>
          <Text style={s.name}>{p.name}</Text>
          {(page.headline || p.brand) ? <Text style={s.muted}>{page.headline ?? p.brand}</Text> : null}
          <Text style={s.price}>{!goods && options.length > 0 && picked === null ? `${t("From")} ` : ""}{money(shown, p.currency)}</Text>
          {fee > 0 && <Text style={s.muted}>{t("A platform fee of {n}% is added at payment.", { n: fee })}</Text>}

          {options.length > 1 && (
            <View style={{ gap: 8 }}>
              <Text style={s.section}>{t("Choose an option")}</Text>
              <View style={s.pills}>
                {options.map((o, i) => (
                  <Button key={o.target} title={o.target} kind={picked === i ? "primary" : "outline"} onPress={() => setPicked(picked === i ? null : i)} style={{ minHeight: 42, paddingHorizontal: 16 }} />
                ))}
              </View>
            </View>
          )}

          {p.highlights && p.highlights.length > 0 && (
            <Card style={{ gap: 6 }}>
              <Text style={s.section}>{t("Highlights")}</Text>
              {p.highlights.map((h) => <Text key={h} style={s.body}>✓ {h}</Text>)}
              {p.warranty_years ? <Text style={s.body}>🛡 {p.warranty_years} {t("year warranty")}</Text> : null}
            </Card>
          )}

          {p.description ? <View style={{ gap: 6 }}><Text style={s.section}>{t("Description")}</Text><Text style={s.body}>{p.description}</Text></View> : null}

          {page.specs && page.specs.length > 0 && (
            <View style={{ gap: 6 }}>
              <Text style={s.section}>{t("Specifications")}</Text>
              {page.specs.map((r) => <View key={r.label} style={s.row}><Text style={s.rowKey}>{r.label}</Text><Text style={s.rowVal}>{r.value}</Text></View>)}
            </View>
          )}

          {page.faqs && page.faqs.length > 0 && (
            <View style={{ gap: 10 }}>
              <Text style={s.section}>{t("Questions")}</Text>
              {page.faqs.map((f) => <View key={f.q} style={{ gap: 2 }}><Text style={s.q}>{f.q}</Text><Text style={s.body}>{f.a}</Text></View>)}
            </View>
          )}
        </View>
      </ScrollView>

      <View style={s.bar}>
        <Text style={s.barPrice}>{money(shown, p.currency)}</Text>
        {onWebOnly
          ? <Button title={t("Open the website")} kind="outline" onPress={() => Linking.openURL(`${WEB_URL}/s/${slug}/products/${p.id}`)} style={{ flex: 1 }} />
          : <Button title={goods ? t("Buy") : t("Request a quote")} onPress={buy} disabled={p.in_stock === false} style={{ flex: 1 }} />}
      </View>
    </View>
  );
}

const s = StyleSheet.create({
  noImg: { backgroundColor: color.wash, alignItems: "center", justifyContent: "center" },
  letter: { fontSize: 72, color: color.blue, fontWeight: "800" },
  name: { fontSize: 24, fontWeight: "800", color: color.navy },
  muted: { fontSize: 14, color: color.slate },
  price: { fontSize: 26, fontWeight: "800", color: color.blue },
  section: { fontSize: 16, fontWeight: "800", color: color.navy },
  body: { fontSize: 15, color: color.ink, lineHeight: 22 },
  q: { fontSize: 15, fontWeight: "700", color: color.navy },
  pills: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  row: { flexDirection: "row", justifyContent: "space-between", borderBottomWidth: 1, borderColor: color.fog, paddingVertical: 8 },
  rowKey: { color: color.slate, fontSize: 14, flex: 1 },
  rowVal: { color: color.ink, fontSize: 14, fontWeight: "600", flex: 1, textAlign: "right" },
  bar: { position: "absolute", bottom: 0, left: 0, right: 0, flexDirection: "row", alignItems: "center", gap: 14, padding: 16, backgroundColor: "#fff", borderTopWidth: 1, borderColor: color.fog },
  barPrice: { fontSize: 18, fontWeight: "800", color: color.navy },
});
