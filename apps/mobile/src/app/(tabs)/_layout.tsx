import { Tabs } from "expo-router";
import { Text } from "react-native";

import { t } from "@/i18n";
import { color } from "@/theme";

const icon = (glyph: string) => ({ focused }: { focused: boolean }) => <Text style={{ fontSize: 20, opacity: focused ? 1 : 0.55 }}>{glyph}</Text>;

export default function TabsLayout() {
  return (
    <Tabs screenOptions={{ tabBarActiveTintColor: color.blue, tabBarInactiveTintColor: color.slate, headerTitleStyle: { color: color.navy, fontWeight: "800" }, headerShadowVisible: false }}>
      <Tabs.Screen name="index" options={{ title: t("Stores"), tabBarIcon: icon("🛍️") }} />
      <Tabs.Screen name="orders" options={{ title: t("Orders"), tabBarIcon: icon("📦") }} />
      <Tabs.Screen name="account" options={{ title: t("Account"), tabBarIcon: icon("👤") }} />
    </Tabs>
  );
}
