import { useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, Share, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Loading, Problem } from "@/components/ui";
import { t } from "@/i18n";
import { color } from "@/theme";

type Referral = { code: string; link: string; invited: number; rewarded: number; points_each: number };
type Points = { points: number };

/** Your invite code and your points. A friend's first delivered order rewards you both. */
export default function InviteScreen() {
  const [info, setInfo] = useState<Referral | null>(null);
  const [points, setPoints] = useState<Points | null>(null);
  const [error, setError] = useState<unknown>(null);
  const load = useCallback(() => {
    setError(null);
    api<Referral>("/referral").then(setInfo).catch(setError);
    api<Points>("/loyalty").then(setPoints).catch(() => setPoints(null));
  }, []);
  useFocusEffect(load);

  if (error && !info) return <Problem error={error} retry={load} />;
  if (!info) return <Loading />;

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
      <Card style={{ gap: 10 }}>
        <Text style={s.muted}>{t("Share your link. When a friend receives their first order, you both get {n} points.", { n: info.points_each })}</Text>
        <Text style={s.code} testID="referral-code">{info.code}</Text>
        <Button title={t("Share my invite")} onPress={() => void Share.share({ message: info.link })} />
        <Text style={s.muted}>{t("{n} invited", { n: info.invited })} · {t("{n} rewarded", { n: info.rewarded })}</Text>
      </Card>
      {points ? <Card><Text style={s.muted}>{t("Your points")}</Text><View><Text style={s.points}>{points.points}</Text></View></Card> : null}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  muted: { color: color.slate, fontSize: 14 },
  code: { fontSize: 30, fontWeight: "800", letterSpacing: 6, color: color.navy, textAlign: "center" },
  points: { fontSize: 32, fontWeight: "800", color: color.navy },
});
