import * as AppleAuthentication from "expo-apple-authentication";
import * as Google from "expo-auth-session/providers/google";
import * as Linking from "expo-linking";
import { router } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { useEffect, useState } from "react";
import { Platform, ScrollView, StyleSheet, Text, View } from "react-native";

import { api } from "@/api";
import { Logo } from "@/components/brand";
import { Button, Field, messageOf } from "@/components/ui";
import { GOOGLE, WEB_URL } from "@/config";
import { t } from "@/i18n";
import { useSession } from "@/session";
import { color } from "@/theme";

WebBrowser.maybeCompleteAuthSession();

/** "Continue with Google": the token comes back from Google and the platform checks it. */
function GoogleButton({ onToken, onError }: { onToken: (idToken: string) => Promise<void>; onError: (e: unknown) => void }) {
  const [request, response, prompt] = Google.useIdTokenAuthRequest({ iosClientId: GOOGLE.ios || undefined, androidClientId: GOOGLE.android || undefined });

  useEffect(() => {
    if (response?.type === "success") {
      const token = response.params.id_token;
      if (token) onToken(token).catch(onError);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only a new response should trigger this
  }, [response]);

  return <Button title={t("Continue with Google")} kind="outline" disabled={!request} onPress={() => void prompt()} />;
}

export default function LoginScreen() {
  const { signIn, signInWith } = useSession();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [providers, setProviders] = useState<string[]>([]);
  const [appleOk, setAppleOk] = useState(false);

  useEffect(() => {
    api<{ providers: Record<string, unknown> }>("/auth/social/providers").then((r) => setProviders(Object.keys(r.providers))).catch(() => setProviders([]));
    if (Platform.OS === "ios") AppleAuthentication.isAvailableAsync().then(setAppleOk).catch(() => setAppleOk(false));
  }, []);

  const done = () => router.back();
  const fail = (e: unknown) => { setError(messageOf(e)); setBusy(false); };

  async function submit() {
    setBusy(true);
    setError(null);
    try {
      await signIn(email.trim(), password);
      done();
    } catch (e) {
      fail(e);
    }
  }

  async function apple() {
    setError(null);
    try {
      const cred = await AppleAuthentication.signInAsync({ requestedScopes: [AppleAuthentication.AppleAuthenticationScope.FULL_NAME, AppleAuthentication.AppleAuthenticationScope.EMAIL] });
      if (!cred.identityToken) return;
      const name = [cred.fullName?.givenName, cred.fullName?.familyName].filter(Boolean).join(" ");
      await signInWith("apple", cred.identityToken, name ? { name } : undefined);
      done();
    } catch (e) {
      // closing the Apple sheet is not an error
      if ((e as { code?: string }).code !== "ERR_REQUEST_CANCELED") fail(e);
    }
  }

  const showGoogle = providers.includes("google") && (GOOGLE.ios !== "" || GOOGLE.android !== "");
  const showApple = providers.includes("apple") && appleOk;

  return (
    <ScrollView contentContainerStyle={s.wrap} keyboardShouldPersistTaps="handled">
      <View style={{ flexDirection: "row", alignItems: "center", gap: 10 }}><Logo size={34} /><Text style={s.brand}>ArmorHub</Text></View>
      <Text style={s.title}>{t("Welcome back")}</Text>
      <Field label={t("Email")} value={email} onChangeText={setEmail} autoCapitalize="none" autoComplete="email" keyboardType="email-address" textContentType="emailAddress" />
      <Field label={t("Password")} value={password} onChangeText={setPassword} secureTextEntry autoComplete="current-password" textContentType="password" error={error ?? undefined} />
      <Button title={t("Sign in")} busy={busy} disabled={!email || !password} onPress={submit} />

      {(showGoogle || showApple) && (
        <View style={{ gap: 12 }}>
          <Text style={s.or}>{t("Or")}</Text>
          {showApple && <AppleAuthentication.AppleAuthenticationButton buttonType={AppleAuthentication.AppleAuthenticationButtonType.CONTINUE} buttonStyle={AppleAuthentication.AppleAuthenticationButtonStyle.BLACK} cornerRadius={26} style={{ height: 52 }} onPress={apple} />}
          {showGoogle && <GoogleButton onToken={async (tok) => { await signInWith("google", tok); done(); }} onError={fail} />}
        </View>
      )}

      <Button title={t("Forgot your password?")} kind="outline" onPress={() => Linking.openURL(`${WEB_URL}/forgot-password`)} />
      <Button title={t("Create an account")} kind="outline" onPress={() => Linking.openURL(`${WEB_URL}/signup`)} />
    </ScrollView>
  );
}

const s = StyleSheet.create({
  wrap: { padding: 20, gap: 16 },
  brand: { fontSize: 20, fontWeight: "800", color: color.navy },
  title: { fontSize: 30, fontWeight: "800", color: color.navy, letterSpacing: -0.8 },
  or: { textAlign: "center", color: color.slate },
});
