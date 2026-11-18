import { useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { t } from "@/i18n";
import { color } from "@/theme";

type Claim = { id: number; status: "open" | "approved" | "rejected" | "resolved"; resolution: string | null; note: string | null; description: string };
type Warranty = { id: number; ends_at: string; active: boolean; can_claim: boolean; product?: { name: string }; claims: Claim[] };

const CLAIM_TEXT = { open: "Claim sent", approved: "Claim approved", rejected: "Claim declined", resolved: "Claim resolved" } as const;

/** Your warranties, and a claim when goods fail inside one. */
export default function WarrantiesScreen() {
  const [list, setList] = useState<Warranty[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [text, setText] = useState<Record<number, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const load = useCallback(() => { setError(null); api<Warranty[]>("/warranties").then(setList).catch(setError); }, []);
  useFocusEffect(load);

  if (error && !list) return <Problem error={error} retry={load} />;
  if (!list) return <Loading />;

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
      {message ? <Text style={{ color: color.red }} accessibilityRole="alert">{message}</Text> : null}
      {list.length === 0 ? <Text style={s.muted}>{t("No warranties yet.")}</Text> : list.map((w) => (
        <Card key={w.id} style={{ gap: 8 }}>
          <View style={s.row}><Text style={s.name}>{w.product?.name}</Text><Text style={s.badge}>{w.active ? t("Active") : t("Expired")}</Text></View>
          <Text style={s.muted}>{t("Until")} {w.ends_at.slice(0, 10)}</Text>
          {w.claims.map((c) => <View key={c.id} style={{ backgroundColor: color.bone, borderRadius: 10, padding: 8 }}><Text style={{ color: color.ink }}>{t(CLAIM_TEXT[c.status])}{c.resolution ? ` · ${t(c.resolution === "repair" ? "Repair" : c.resolution === "replace" ? "Replacement" : "Refund")}` : ""}</Text><Text style={s.muted}>{c.note ?? c.description}</Text></View>)}
          {w.can_claim ? (
            <View style={{ gap: 8 }}>
              <Field label={t("What went wrong? Describe the problem.")} value={text[w.id] ?? ""} onChangeText={(v) => setText((x) => ({ ...x, [w.id]: v }))} multiline />
              <Button title={t("Make a warranty claim")} kind="outline" disabled={(text[w.id] ?? "").trim().length < 10} onPress={async () => {
                setMessage(null);
                try { await api(`/warranties/${w.id}/claims`, { method: "POST", body: { description: text[w.id] } }); setText((x) => ({ ...x, [w.id]: "" })); load(); } catch (e) { setMessage(messageOf(e)); }
              }} />
            </View>
          ) : null}
        </Card>
      ))}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  name: { fontSize: 16, fontWeight: "700", color: color.navy, flex: 1 },
  muted: { color: color.slate, fontSize: 14 },
  row: { flexDirection: "row", justifyContent: "space-between", gap: 8, alignItems: "center" },
  badge: { color: color.blue, fontWeight: "700", fontSize: 13 },
});
