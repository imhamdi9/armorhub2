import { router, useFocusEffect } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { t } from "@/i18n";
import { useSession } from "@/session";
import { color, radius } from "@/theme";
import type { Vehicle } from "@/types";

type Named = { id: number; name: string; vehicle_make_id?: number };

/** The person's cars: saved once, so parts can be checked against them. */
export default function GarageScreen() {
  const { user } = useSession();
  const [cars, setCars] = useState<Vehicle[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [makes, setMakes] = useState<Named[]>([]);
  const [models, setModels] = useState<Named[]>([]);
  const [adding, setAdding] = useState(false);
  const [make, setMake] = useState<number | null>(null);
  const [model, setModel] = useState<number | null>(null);
  const [year, setYear] = useState("");
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<Vehicle[]>("/garage").then(setCars).catch(setError);
  }, [user]);
  useFocusEffect(load);
  useEffect(() => {
    if (!adding || makes.length) return;
    api<Named[]>("/catalog/vehicle_make").then(setMakes).catch(() => {});
    api<Named[]>("/catalog/vehicle_model").then(setModels).catch(() => {});
  }, [adding, makes.length]);

  if (!user) return <View style={s.center}><Text style={s.muted}>{t("Sign in to see your garage")}</Text><Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} /></View>;
  if (error && !cars) return <Problem error={error} retry={load} />;
  if (!cars) return <Loading />;

  async function save() {
    setMessage(null);
    try {
      await api("/garage", { method: "POST", body: { vehicle_model_id: model, year: Number(year) } });
      setAdding(false); setMake(null); setModel(null); setYear("");
      load();
    } catch (e) { setMessage(messageOf(e)); }
  }

  return (
    <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ padding: 14, gap: 12 }}>
      {cars.length === 0 && !adding ? <Text style={s.muted}>{t("Add your car to see only the parts that fit it.")}</Text> : null}
      {cars.map((c) => (
        <Card key={c.id} style={{ gap: 8 }}>
          <Text style={s.name}>{c.nickname || c.label}{c.is_default ? ` · ${t("Default")}` : ""}</Text>
          <Text style={s.muted}>{c.label}</Text>
          <Button title={t("Remove")} kind="outline" onPress={() => api(`/garage/${c.id}`, { method: "DELETE" }).then(load).catch(() => {})} style={{ minHeight: 40 }} />
        </Card>
      ))}

      {adding ? (
        <Card style={{ gap: 10 }}>
          <Text style={s.h}>{t("Make")}</Text>
          <View style={s.chips}>{makes.map((m) => <Chip key={m.id} text={m.name} on={make === m.id} onPress={() => { setMake(m.id); setModel(null); }} />)}</View>
          {make ? <><Text style={s.h}>{t("Model")}</Text><View style={s.chips}>{models.filter((m) => m.vehicle_make_id === make).map((m) => <Chip key={m.id} text={m.name} on={model === m.id} onPress={() => setModel(m.id)} />)}</View></> : null}
          <Field label={t("Year")} value={year} onChangeText={setYear} keyboardType="number-pad" maxLength={4} />
          {message ? <Text style={s.error}>{message}</Text> : null}
          <Button title={t("Save vehicle")} disabled={!model || year.length !== 4} onPress={save} />
          <Button title={t("Cancel")} kind="outline" onPress={() => setAdding(false)} />
        </Card>
      ) : <Button title={t("Add a vehicle")} onPress={() => setAdding(true)} />}
    </ScrollView>
  );
}

function Chip({ text, on, onPress }: { text: string; on: boolean; onPress: () => void }) {
  return <Pressable onPress={onPress} style={[s.chip, on && s.chipOn]}><Text style={s.chipText}>{text}</Text></Pressable>;
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  muted: { color: color.slate, fontSize: 14 },
  name: { fontSize: 16, fontWeight: "700", color: color.navy },
  h: { fontSize: 14, fontWeight: "800", color: color.navy },
  error: { color: color.red, fontSize: 13 },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chip: { borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.pill, paddingHorizontal: 14, paddingVertical: 8, backgroundColor: "#fff" },
  chipOn: { borderColor: color.blue, backgroundColor: color.wash },
  chipText: { color: color.navy, fontWeight: "600", fontSize: 13 },
});
