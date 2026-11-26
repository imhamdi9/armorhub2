import { useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Switch, Text, View } from "react-native";

import { api } from "@/api";
import { Card, Loading, Problem, messageOf } from "@/components/ui";
import { t } from "@/i18n";
import { color } from "@/theme";

type Prefs = { notify_email: boolean; notify_push: boolean };

/** How ArmorHub reaches you. The notices inside the app are always kept. */
export default function PreferencesScreen() {
  const [prefs, setPrefs] = useState<Prefs | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [message, setMessage] = useState<string | null>(null);
  const load = useCallback(() => { setError(null); api<Prefs>("/me/notification-preferences").then(setPrefs).catch(setError); }, []);
  useFocusEffect(load);

  if (error && !prefs) return <Problem error={error} retry={load} />;
  if (!prefs) return <Loading />;

  async function set(key: keyof Prefs, value: boolean) {
    setMessage(null);
    setPrefs((p) => (p ? { ...p, [key]: value } : p));
    try { setPrefs(await api<Prefs>("/me/notification-preferences", { method: "PUT", body: { [key]: value } })); } catch (e) { setMessage(messageOf(e)); load(); }
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
      <Card style={{ gap: 14 }}>
        <Text style={s.h}>{t("How we tell you")}</Text>
        <View style={s.row}><Text style={s.label}>{t("By e-mail")}</Text><Switch accessibilityLabel={t("By e-mail")} value={prefs.notify_email} onValueChange={(v) => void set("notify_email", v)} /></View>
        <View style={s.row}><Text style={s.label}>{t("On my phone (notifications)")}</Text><Switch accessibilityLabel={t("On my phone (notifications)")} value={prefs.notify_push} onValueChange={(v) => void set("notify_push", v)} /></View>
        <Text style={s.muted}>{t("The notices inside the app are always kept.")}</Text>
        {message ? <Text style={{ color: color.red }} accessibilityRole="alert">{message}</Text> : null}
      </Card>
    </ScrollView>
  );
}

const s = StyleSheet.create({
  h: { fontSize: 17, fontWeight: "800", color: color.navy },
  label: { fontSize: 16, color: color.ink, flex: 1 },
  muted: { color: color.slate, fontSize: 14 },
  row: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", gap: 12 },
});
