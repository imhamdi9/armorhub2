/**
 * Where the app talks to. The versioned address (/api/v1) never changes under an installed app, so an old version keeps working
 * after the platform is updated. Set EXPO_PUBLIC_API_URL for a build; the default reaches a platform running on the developer's computer
 * from the Android emulator (a real phone needs the computer's network address).
 */
export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? "http://10.0.2.2:8000/api/v1").replace(/\/$/, "");

/** The website, for the few things that stay there (creating an account, resetting a password). */
export const WEB_URL = (process.env.EXPO_PUBLIC_WEB_URL ?? "https://armorhub.com").replace(/\/$/, "");

/** Client ids for "Continue with Google" (separate from the website's). Empty hides the button. */
export const GOOGLE = {
  ios: process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID ?? "",
  android: process.env.EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID ?? "",
};

/** An uploaded picture is kept as /media/<name>; this is the address that loads it (https addresses pass through). */
export function imageUrl(value: string | null | undefined): string | null {
  if (!value) return null;
  return value.startsWith("/media/") ? `${API_URL}/public${value}` : value;
}
