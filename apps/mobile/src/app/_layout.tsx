import * as Notifications from "expo-notifications";
import { Stack, router } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useEffect } from "react";
import { Platform } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";

import { screenFor } from "@/push";
import { MarketSetup } from "@/components/market-ui";
import { MarketProvider, useMarket } from "@/market";
import { SessionProvider } from "@/session";
import { color } from "@/theme";

/** Opens the right screen when a notification is tapped, also when the app was closed. (Phones only: the web preview has no notifications.) */
function NotificationTaps() {
  const last = Notifications.useLastNotificationResponse();
  useEffect(() => {
    const target = screenFor(last?.notification.request.content.data?.path);
    if (target) router.push(target as never);
  }, [last]);
  return null;
}

/** The app opens once the visitor's market is known (found from their location, or chosen). */
function Shell() {
  const { market, ready } = useMarket();
  if (!ready) return null;
  if (!market) return <MarketSetup />;

  return (
        <Stack screenOptions={{ headerTintColor: color.navy, headerShadowVisible: false, headerTitleStyle: { fontWeight: "800", color: color.navy }, contentStyle: { backgroundColor: color.bg } }}>
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="login" options={{ presentation: "modal", title: "" }} />
          <Stack.Screen name="search" options={{ title: "" }} />
          <Stack.Screen name="store/[slug]" options={{ title: "" }} />
          <Stack.Screen name="product/[slug]/[id]" options={{ title: "", headerStyle: { backgroundColor: color.bone } }} />
          <Stack.Screen name="checkout/[slug]/[id]" options={{ title: "Checkout" }} />
          <Stack.Screen name="order/[id]" options={{ title: "" }} />
          <Stack.Screen name="track/[token]" options={{ title: "" }} />
          <Stack.Screen name="favourites" options={{ title: "Favourites" }} />
          <Stack.Screen name="garage" options={{ title: "Garage" }} />
          <Stack.Screen name="quotes" options={{ title: "Price requests" }} />
          <Stack.Screen name="addresses" options={{ title: "Addresses" }} />
          <Stack.Screen name="help" options={{ title: "Help" }} />
          <Stack.Screen name="preferences" options={{ title: "Preferences" }} />
          <Stack.Screen name="invite" options={{ title: "Invite friends" }} />
          <Stack.Screen name="warranties" options={{ title: "Warranties" }} />
        </Stack>
  );
}

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <SessionProvider>
        <MarketProvider>
          {Platform.OS !== "web" && <NotificationTaps />}
          <StatusBar style="dark" />
          <Shell />
        </MarketProvider>
      </SessionProvider>
    </SafeAreaProvider>
  );
}
