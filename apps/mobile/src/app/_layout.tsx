import * as Notifications from "expo-notifications";
import { Stack, router } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { useEffect } from "react";

import { screenFor } from "@/push";
import { SessionProvider } from "@/session";
import { color } from "@/theme";

/** Opens the right screen when a notification is tapped, also when the app was closed. */
function useNotificationTaps() {
  const last = Notifications.useLastNotificationResponse();
  useEffect(() => {
    const target = screenFor(last?.notification.request.content.data?.path);
    if (target) router.push(target as never);
  }, [last]);
}

export default function RootLayout() {
  useNotificationTaps();

  return (
    <SessionProvider>
      <StatusBar style="dark" />
      <Stack screenOptions={{ headerTintColor: color.navy, headerShadowVisible: false, contentStyle: { backgroundColor: color.bg } }}>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="login" options={{ presentation: "modal", title: "" }} />
        <Stack.Screen name="store/[slug]" options={{ title: "" }} />
        <Stack.Screen name="product/[slug]/[id]" options={{ title: "" }} />
        <Stack.Screen name="checkout/[slug]/[id]" options={{ title: "" }} />
        <Stack.Screen name="order/[id]" options={{ title: "" }} />
        <Stack.Screen name="track/[token]" options={{ title: "" }} />
      </Stack>
    </SessionProvider>
  );
}
