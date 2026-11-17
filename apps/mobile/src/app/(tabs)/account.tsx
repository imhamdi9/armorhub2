import * as Linking from "expo-linking";
import { router } from "expo-router";
import { useState } from "react";
import { Alert, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button, Card, Field, messageOf } from "@/components/ui";
import { WEB_URL } from "@/config";
import { t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";

export default function AccountScreen() {
  const { user, signOut, deleteAccount } = useSession();
  const [confirming, setConfirming] = useState(false);
  const [proof, setProof] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!user) {
    return (
      <View style={s.center}>
        <Text style={s.muted}>{t("Not signed in")}</Text>
        <Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} />
      </View>
    );
  }

  async function remove() {
    setBusy(true);
    setError(null);
    try {
      // People who signed up with Google or Apple have no password: they type DELETE. Everyone else types the password.
      await deleteAccount(proof === "DELETE" ? { confirm: proof } : { password: proof });
      router.replace("/");
    } catch (e) {
      setError(messageOf(e));
      setBusy(false);
    }
  }

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 16 }}>
      <Card style={{ gap: 4 }}>
        <Text style={s.name}>{user.name}</Text>
        <Text style={s.muted}>{user.email}</Text>
      </Card>

      <Button title={t("Open the website")} kind="outline" onPress={() => Linking.openURL(WEB_URL)} />
      <Button title={t("Sign out")} kind="outline" onPress={() => void signOut()} />

      {!confirming ? (
        <Button title={t("Delete my account")} kind="danger" onPress={() => setConfirming(true)} />
      ) : (
        <Card style={{ gap: 12, borderColor: color.red }}>
          <Text style={s.muted}>{t("This removes your personal details for good. Your past orders stay as records without your name.")}</Text>
          <Field label={`${t("Password")} / ${t("Type DELETE to confirm")}`} secureTextEntry={proof !== "DELETE"} autoCapitalize="none" value={proof} onChangeText={setProof} error={error ?? undefined} />
          <Button title={t("Delete")} kind="danger" busy={busy} disabled={!proof} onPress={() => Alert.alert(t("Delete my account"), t("This removes your personal details for good. Your past orders stay as records without your name."), [{ text: t("Cancel"), style: "cancel" }, { text: t("Delete"), style: "destructive", onPress: () => void remove() }])} />
          <Button title={t("Cancel")} kind="outline" onPress={() => { setConfirming(false); setProof(""); setError(null); }} />
        </Card>
      )}
    </ScrollView>
  );
}

const s = StyleSheet.create({
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  name: { fontSize: 20, fontWeight: "800", color: color.navy },
  muted: { fontSize: 14, color: color.slate },
});
