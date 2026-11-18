import { useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";
import { ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Button, Card, Field, Loading, Problem, messageOf } from "@/components/ui";
import { t } from "@/i18n";
import { color } from "@/theme";

type Ticket = { id: number; subject: string; body: string; status: "open" | "answered" | "closed"; reply: string | null };

/** Ask ArmorHub for help and read the answer. */
export default function HelpScreen() {
  const [tickets, setTickets] = useState<Ticket[] | null>(null);
  const [error, setError] = useState<unknown>(null);
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [message, setMessage] = useState<string | null>(null);

  const load = useCallback(() => { setError(null); api<Ticket[]>("/support").then(setTickets).catch(setError); }, []);
  useFocusEffect(load);

  if (error && !tickets) return <Problem error={error} retry={load} />;
  if (!tickets) return <Loading />;

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
      <Card style={{ gap: 10 }}>
        <Text style={s.h}>{t("Ask for help")}</Text>
        <Field label={t("Subject")} value={subject} onChangeText={setSubject} />
        <Field label={t("Tell us what happened")} value={body} onChangeText={setBody} multiline />
        {message ? <Text style={{ color: color.red }} accessibilityRole="alert">{message}</Text> : null}
        <Button title={t("Send")} disabled={subject.trim().length < 3 || body.trim().length < 5} onPress={async () => {
          setMessage(null);
          try { await api("/support", { method: "POST", body: { subject, body } }); setSubject(""); setBody(""); load(); } catch (e) { setMessage(messageOf(e)); }
        }} />
      </Card>
      {tickets.length === 0 ? <Text style={s.muted}>{t("No help requests yet.")}</Text> : tickets.map((x) => (
        <Card key={x.id} style={{ gap: 6 }}>
          <View style={s.row}><Text style={s.name}>{x.subject}</Text><Text style={s.badge}>{t(x.status === "open" ? "Waiting for an answer" : x.status === "answered" ? "Answered" : "Closed")}</Text></View>
          <Text style={s.muted}>{x.body}</Text>
          {x.reply ? <View style={{ backgroundColor: color.wash, borderRadius: 12, padding: 10 }}><Text style={{ color: color.ink }}>{x.reply}</Text></View> : null}
          {x.status !== "closed" ? <Button title={t("Close")} kind="outline" onPress={async () => { try { await api(`/support/${x.id}/close`, { method: "POST" }); load(); } catch (e) { setMessage(messageOf(e)); } }} style={{ minHeight: 40 }} /> : null}
        </Card>
      ))}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  h: { fontSize: 17, fontWeight: "800", color: color.navy },
  name: { fontSize: 16, fontWeight: "700", color: color.navy, flex: 1 },
  muted: { color: color.slate, fontSize: 14 },
  row: { flexDirection: "row", justifyContent: "space-between", gap: 8, alignItems: "center" },
  badge: { color: color.blue, fontWeight: "700", fontSize: 13 },
});
