import { Tabs } from "expo-router";

import { TabIcon } from "@/components/brand";
import { t } from "@/i18n";
import { color } from "@/theme";

export default function TabsLayout() {
  return (
    <Tabs screenOptions={{
      headerShown: false, tabBarActiveTintColor: color.blue, tabBarInactiveTintColor: color.slate,
      tabBarLabelStyle: { fontSize: 11, fontWeight: "600" }, tabBarStyle: { height: 64, paddingTop: 6, borderTopColor: color.fog, backgroundColor: "#fff" },
    }}>
      <Tabs.Screen name="index" options={{ title: t("Home"), tabBarIcon: ({ focused }) => <TabIcon name="home" active={focused} /> }} />
      <Tabs.Screen name="orders" options={{ title: t("Orders"), tabBarIcon: ({ focused }) => <TabIcon name="orders" active={focused} /> }} />
      <Tabs.Screen name="cart" options={{ title: t("Cart"), tabBarIcon: ({ focused }) => <TabIcon name="cart" active={focused} /> }} />
      <Tabs.Screen name="account" options={{ title: t("Account"), tabBarIcon: ({ focused }) => <TabIcon name="account" active={focused} /> }} />
    </Tabs>
  );
}
