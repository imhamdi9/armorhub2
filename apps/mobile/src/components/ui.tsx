import { ActivityIndicator, Pressable, StyleSheet, Text, TextInput, View, type TextInputProps, type ViewStyle } from "react-native";

import { ApiError } from "@/api";
import { t } from "@/i18n";
import { color, radius } from "@/theme";

export function Button({ title, onPress, kind = "primary", busy, disabled, style }: { title: string; onPress: () => void; kind?: "primary" | "outline" | "danger"; busy?: boolean; disabled?: boolean; style?: ViewStyle }) {
  const off = busy || disabled;
  return (
    <Pressable accessibilityRole="button" onPress={onPress} disabled={off}
      style={({ pressed }) => [s.button, kind === "primary" && s.primary, kind === "outline" && s.outline, kind === "danger" && s.danger, off && { opacity: 0.55 }, pressed && { transform: [{ scale: 0.98 }] }, style]}>
      {busy ? <ActivityIndicator color={kind === "outline" ? color.blue : "#fff"} /> : <Text style={[s.buttonText, kind === "outline" && { color: color.blue }]}>{title}</Text>}
    </Pressable>
  );
}

export function Field({ label, error, ...props }: TextInputProps & { label: string; error?: string }) {
  return (
    <View style={{ gap: 6 }}>
      <Text style={s.label}>{label}</Text>
      <TextInput placeholderTextColor="#9aa5c0" {...props} style={[s.input, error ? { borderColor: color.red } : null, props.style]} accessibilityLabel={label} />
      {error ? <Text style={s.error} accessibilityRole="alert">{error}</Text> : null}
    </View>
  );
}

export function Card({ children, style }: { children: React.ReactNode; style?: ViewStyle }) {
  return <View style={[s.card, style]}>{children}</View>;
}

export function Loading() {
  return <View style={s.center}><ActivityIndicator color={color.blue} size="large" /></View>;
}

/** What went wrong, in words the person can act on, with a way to try again. */
export function Problem({ error, retry }: { error: unknown; retry?: () => void }) {
  const offline = error instanceof ApiError && error.status === 0;
  return (
    <View style={s.center}>
      <Text style={s.problem}>{offline ? t("You are offline. Check your connection.") : t("Something went wrong.")}</Text>
      {retry ? <Button title={t("Try again")} kind="outline" onPress={retry} style={{ marginTop: 12 }} /> : null}
    </View>
  );
}

/** The first message of a failed request: a field's own error if the server named one, else its message. */
export function messageOf(error: unknown): string {
  if (error instanceof ApiError) {
    if (error.status === 0) return t("You are offline. Check your connection.");
    const first = Object.values(error.errors)[0]?.[0];
    return first ?? error.message;
  }
  return t("Something went wrong.");
}

const s = StyleSheet.create({
  button: { minHeight: 52, borderRadius: radius.pill, alignItems: "center", justifyContent: "center", paddingHorizontal: 22 },
  primary: { backgroundColor: color.blue },
  outline: { borderWidth: 2, borderColor: color.blue, backgroundColor: "#fff" },
  danger: { backgroundColor: color.red },
  buttonText: { color: "#fff", fontSize: 16, fontWeight: "700" },
  label: { fontSize: 13, fontWeight: "600", color: color.slate },
  input: { minHeight: 50, borderWidth: 1.5, borderColor: color.fog, borderRadius: radius.field, paddingHorizontal: 14, fontSize: 16, color: color.ink, backgroundColor: "#fff" },
  error: { color: color.red, fontSize: 12 },
  card: { backgroundColor: "#fff", borderRadius: radius.card, borderWidth: 1, borderColor: color.fog, padding: 16 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", padding: 24 },
  problem: { fontSize: 16, color: color.slate, textAlign: "center" },
});
