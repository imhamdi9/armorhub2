import { useState } from "react";
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { Logo } from "@/components/brand";
import { Button } from "@/components/ui";
import { t } from "@/i18n";
import { marketName, useMarket, type Market } from "@/market";
import { color, radius } from "@/theme";

const FLAG: Record<string, string> = { SA: "🇸🇦", EG: "🇪🇬" };
const flag = (code: string) => FLAG[code] ?? "🌍";

function MarketCard({ m, on, onPress }: { m: Market; on?: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[s.card, on && s.cardOn]} accessibilityRole="button" accessibilityState={{ selected: !!on }}>
      <Text style={s.flag}>{flag(m.code)}</Text>
      <View style={{ flex: 1 }}>
        <Text style={s.cardName}>{marketName(m)}</Text>
        <Text style={s.cardSub}>{m.currency}</Text>
      </View>
      {on ? <Text style={{ color: color.blue, fontSize: 18 }}>✓</Text> : null}
    </Pressable>
  );
}

function why(problem: ReturnType<typeof useMarket>["problem"]): string | null {
  if (problem === "denied") return t("Location is off. Choose your market, or allow location in Settings.");
  if (problem === "unsupported") return t("ArmorHub is not in your country yet. Choose a market to browse.");
  if (problem === "unavailable") return t("We could not find your location. Choose your market.");
  return null;
}

/** First launch: find the visitor's market from where they are, or let them choose. Shown instead of the app until a market is known. */
export function MarketSetup() {
  const { markets, locating, problem, choose, locate } = useMarket();
  const note = why(problem);

  return (
    <SafeAreaView style={s.screen}>
      <View style={s.brand}><Logo size={34} /><Text style={s.brandName}>ArmorHub</Text></View>
      {locating ? (
        <View style={s.center}>
          <ActivityIndicator color={color.blue} size="large" />
          <Text style={s.title}>{t("Finding your market...")}</Text>
          <Text style={s.sub}>{t("We use your location to show the stores and prices of your country.")}</Text>
        </View>
      ) : (
        <View style={s.body}>
          <Text style={s.title}>{t("Choose your market")}</Text>
          <Text style={s.sub}>{note ?? t("We use your location to show the stores and prices of your country.")}</Text>
          <View style={{ gap: 10, marginTop: 18 }}>
            {markets.map((m) => <MarketCard key={m.code} m={m} onPress={() => void choose(m.code)} />)}
          </View>
          <Button title={t("Use my location")} onPress={() => void locate()} style={{ marginTop: 18 }} />
        </View>
      )}
    </SafeAreaView>
  );
}

/** The country chip at the top of the screens, and the sheet to change it. */
export function MarketPill() {
  const { market, markets, choose, locate, locating, problem } = useMarket();
  const [open, setOpen] = useState(false);
  if (!market) return null;
  const note = why(problem);

  return (
    <>
      <Pressable onPress={() => setOpen(true)} style={s.pill} accessibilityRole="button" accessibilityLabel={t("Market")}>
        <Text style={{ fontSize: 14 }}>{flag(market.code)}</Text>
        <Text style={s.pillText}>{market.currency}</Text>
        <Text style={s.pillText}>▾</Text>
      </Pressable>
      <Modal transparent visible={open} animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={s.scrim} onPress={() => setOpen(false)}>
          <Pressable style={s.sheet} onPress={() => undefined}>
            <Text style={s.sheetTitle}>{t("Market")}</Text>
            {markets.map((m) => <MarketCard key={m.code} m={m} on={m.code === market.code} onPress={() => { void choose(m.code); setOpen(false); }} />)}
            {note ? <Text style={s.sub}>{note}</Text> : null}
            <Button title={locating ? t("Finding your market...") : t("Use my location")} kind="outline" busy={locating} onPress={async () => { await locate(); setOpen(false); }} style={{ marginTop: 6 }} />
          </Pressable>
        </Pressable>
      </Modal>
    </>
  );
}

const s = StyleSheet.create({
  screen: { flex: 1, backgroundColor: "#fff", padding: 20 },
  brand: { flexDirection: "row", alignItems: "center", gap: 10 },
  brandName: { fontSize: 20, fontWeight: "800", color: color.navy },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 14, padding: 10 },
  body: { flex: 1, justifyContent: "center" },
  title: { fontSize: 28, fontWeight: "800", color: color.navy, letterSpacing: -0.8, textAlign: "center" },
  sub: { fontSize: 14, color: color.slate, textAlign: "center", lineHeight: 20, marginTop: 6 },
  card: { flexDirection: "row", alignItems: "center", gap: 14, backgroundColor: "#fff", borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.big - 2, padding: 16 },
  cardOn: { borderColor: color.blue, backgroundColor: color.wash },
  flag: { fontSize: 32 },
  cardName: { fontSize: 17, fontWeight: "800", color: color.navy },
  cardSub: { fontSize: 13, color: color.slate, marginTop: 2 },
  pill: { flexDirection: "row", alignItems: "center", gap: 5, borderWidth: 1, borderColor: color.fog, backgroundColor: color.bone, borderRadius: radius.pill, paddingHorizontal: 10, paddingVertical: 6 },
  pillText: { fontSize: 12, fontWeight: "700", color: color.navy },
  scrim: { flex: 1, backgroundColor: "rgba(11,27,58,.45)", justifyContent: "flex-end" },
  sheet: { backgroundColor: "#fff", borderTopLeftRadius: 28, borderTopRightRadius: 28, padding: 20, paddingBottom: 34, gap: 10 },
  sheetTitle: { fontSize: 18, fontWeight: "800", color: color.navy },
});
