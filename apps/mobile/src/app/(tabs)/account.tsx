import * as Linking from "expo-linking";
import { router } from "expo-router";
import { useState } from "react";
import { Alert, ScrollView, StyleSheet, Text, View } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { api } from "@/api";
import { Button, Card, Field, messageOf } from "@/components/ui";
import { WEB_URL } from "@/config";
import { t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";

export default function AccountScreen() {
  const { user, signOut, deleteAccount, refresh } = useSession();
  const [name, setName] = useState(user?.name ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [saved, setSaved] = useState<string | null>(null);
  const [confirming, setConfirming] = useState(false);
  const [proof, setProof] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!user) {
    return (
      <SafeAreaView edges={["top"]} style={{ flex: 1, backgroundColor: "#fff" }}>
        <Text style={s.title}>{t("Account")}</Text>
        <View style={s.center}>
          <Text style={s.muted}>{t("Not signed in")}</Text>
          <Button title={t("Sign in")} onPress={() => router.push("/login")} style={{ marginTop: 16, alignSelf: "stretch" }} />
        </View>
      </SafeAreaView>
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
    <SafeAreaView edges={["top"]} style={{ flex: 1, backgroundColor: "#fff" }}>
    <Text style={s.title}>{t("Account")}</Text>
    <ScrollView style={{ backgroundColor: color.bone }} contentContainerStyle={{ padding: 14, gap: 14 }}>
      <Card style={{ gap: 4 }}>
        <Text style={s.name}>{user.name}</Text>
        <Text style={s.muted}>{user.email}</Text>
      </Card>

      <Card style={{ gap: 10 }}>
        <Field label={t("Full name")} value={name} onChangeText={setName} />
        <Field label={t("Phone")} value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
        {saved ? <Text style={s.muted}>{saved}</Text> : null}
        <Button title={t("Save")} kind="outline" onPress={async () => {
          setSaved(null);
          try { await api("/me/profile", { method: "PUT", body: { name, phone: phone || null } }); await refresh(); setSaved(t("Saved")); } catch (e) { setSaved(messageOf(e)); }
        }} />
      </Card>
      <Button title={t("Favourites")} kind="outline" onPress={() => router.push("/favourites")} />
      <Button title={t("Garage")} kind="outline" onPress={() => router.push("/garage")} />
      <Button title={t("Price requests")} kind="outline" onPress={() => router.push("/quotes")} />
      <Button title={t("Addresses")} kind="outline" onPress={() => router.push("/addresses")} />
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
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  title: { fontSize: 26, fontWeight: "800", color: color.navy, letterSpacing: -0.6, paddingHorizontal: 14, paddingTop: 8, paddingBottom: 12 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  name: { fontSize: 20, fontWeight: "800", color: color.navy },
  muted: { fontSize: 14, color: color.slate },
});
