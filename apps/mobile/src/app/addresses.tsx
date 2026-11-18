import { router, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";
import type { Address } from "@/types";

/** Saved delivery addresses: the default one is filled in at checkout. */
export default function AddressesScreen() {
  const { user } = useSession();
  const [rows, setRows] = useState<Address[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [adding, setAdding] = useState(false);
  const [form, setForm] = useState({ label: "", name: "", phone: "", line1: "", city: "" });
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(() => {
    if (!user) return;
    setError(null);
    api<Address[]>("/addresses").then(setRows).catch(setError);
  }, [user]);
  useFocusEffect(load);

  if (!user) return <View style={s.center}><Text style={s.muted}>{t("Sign in to see your addresses")}</Text><Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} /></View>;
  if (error && !rows) return <Problem error={error} retry={load} />;
  if (!rows) return <Loading />;

  async function save() {
    setMessage(null);
    try {
      await api("/addresses", { method: "POST", body: { ...form, label: form.label || null } });
      setAdding(false); setForm({ label: "", name: "", phone: "", line1: "", city: "" });
      load();
    } catch (e) { setMessage(messageOf(e)); }
  }

  return (
    <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ padding: 14, gap: 12 }}>
      {rows.length === 0 && !adding ? <Text style={s.muted}>{t("No saved addresses yet.")}</Text> : null}
      {rows.map((a) => (
        <Card key={a.id} style={{ gap: 6 }}>
          <Text style={s.name}>{a.label || a.name}{a.is_default ? ` · ${t("Default")}` : ""}</Text>
          <Text style={s.muted}>{a.line1}, {a.city} · {a.phone}</Text>
          <View style={{ flexDirection: "row", gap: 8 }}>
            {!a.is_default ? <Button title={t("Make default")} kind="outline" onPress={() => api(`/addresses/${a.id}`, { method: "PUT", body: { is_default: true } }).then(load).catch(() => {})} style={{ minHeight: 40, flex: 1 }} /> : null}
            <Button title={t("Remove")} kind="outline" onPress={() => api(`/addresses/${a.id}`, { method: "DELETE" }).then(load).catch(() => {})} style={{ minHeight: 40, flex: 1 }} />
          </View>
        </Card>
      ))}
      {adding ? (
        <Card style={{ gap: 10 }}>
          <Field label={t("Label, e.g. Home")} value={form.label} onChangeText={(v) => setForm({ ...form, label: v })} />
          <Field label={t("Full name")} value={form.name} onChangeText={(v) => setForm({ ...form, name: v })} />
          <Field label={t("Phone")} value={form.phone} onChangeText={(v) => setForm({ ...form, phone: v })} keyboardType="phone-pad" />
          <Field label={t("Address")} value={form.line1} onChangeText={(v) => setForm({ ...form, line1: v })} />
          <Field label={t("City")} value={form.city} onChangeText={(v) => setForm({ ...form, city: v })} />
          {message ? <Text style={s.error}>{message}</Text> : null}
          <Button title={t("Save address")} disabled={!form.name || !form.phone || !form.line1 || !form.city} onPress={save} />
          <Button title={t("Cancel")} kind="outline" onPress={() => setAdding(false)} />
        </Card>
      ) : <Button title={t("Add an address")} onPress={() => setAdding(true)} />}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  muted: { color: color.slate, fontSize: 13 },
  name: { fontSize: 15, fontWeight: "700", color: color.navy },
  error: { color: color.red, fontSize: 13 },
});
