import Constants from "expo-constants";
import * as Device from "expo-device";
import * as Notifications from "expo-notifications";
import { Platform } from "react-native";

import { api } from "@/api";

// While the app is open a notification still shows as a banner.
if (Platform.OS !== "web") {
  Notifications.setNotificationHandler({
    handleNotification: async () => ({ shouldPlaySound: false, shouldSetBadge: false, shouldShowBanner: true, shouldShowList: true }),
  });
}

let registered: string | null = null;

/**
 * Asks permission and tells the platform where to send this phone's notifications. Quiet by design: a refusal, a simulator or a missing
 * project id simply means no push, and nothing else in the app depends on it.
 */
export async function registerForPush(): Promise<void> {
  try {
    if (Platform.OS === "web" || !Device.isDevice) return;
    const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
    if (!projectId) return;

    if (Platform.OS === "android") {
      await Notifications.setNotificationChannelAsync("default", { name: "ArmorHub", importance: Notifications.AndroidImportance.DEFAULT });
    }

    let { status } = await Notifications.getPermissionsAsync();
    if (status !== "granted") status = (await Notifications.requestPermissionsAsync()).status;
    if (status !== "granted") return;

    const { data } = await Notifications.getExpoPushTokenAsync({ projectId });
    await api("/me/devices", { method: "POST", body: { platform: Platform.OS === "ios" ? "ios" : "android", token: data } });
    registered = data;
  } catch {
    // never block the person
  }
}

/** On sign-out the phone stops receiving this person's notifications. */
export async function unregisterPush(): Promise<void> {
  if (!registered) return;
  const token = registered;
  registered = null;
  try {
    await api("/me/devices", { method: "DELETE", body: { token } });
  } catch {
    // the token is forgotten by the platform the next time a notification to it fails
  }
}

/** The web path a notification carries, as the screen of the app that shows the same thing. */
export function screenFor(path: unknown): string | null {
  if (typeof path !== "string") return null;
  const order = path.match(/^\/dashboard\/orders\/(\d+)/);
  if (order) return `/order/${order[1]}`;
  if (path.startsWith("/dashboard/orders")) return "/orders";
  return null;
}
