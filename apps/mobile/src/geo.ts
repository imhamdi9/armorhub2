import * as Location from "expo-location";
import { Platform } from "react-native";

export type Detection =
  | { ok: true; country: string; latitude: number; longitude: number; accuracy: number | null }
  | { ok: false; reason: "denied" | "unavailable" };

/** Rough outlines of the two countries ArmorHub serves, as [longitude, latitude] corners. Close enough to tell which side of a border a person is on. */
const OUTLINES: Record<"SA" | "EG", [number, number][]> = {
  SA: [
    [34.95, 29.35], [36.0, 29.2], [37.5, 29.9], [38.0, 30.5], [37.0, 31.5], [39.2, 32.15], [40.0, 31.9], [42.0, 31.1], [44.7, 29.2], [46.5, 29.1],
    [47.6, 28.6], [48.4, 28.5], [48.5, 27.9], [49.6, 27.0], [50.3, 26.5], [50.4, 25.6], [50.8, 24.75], [51.6, 24.25], [51.6, 24.1], [55.2, 22.9],
    [55.7, 22.0], [55.0, 20.0], [52.0, 19.0], [49.0, 18.6], [46.0, 17.5], [43.8, 17.3], [42.8, 16.5], [42.6, 16.7], [41.0, 19.0], [38.9, 21.6],
    [38.5, 22.8], [37.9, 24.0], [37.1, 25.0], [35.6, 27.3], [34.9, 29.3],
  ],
  EG: [
    [25.0, 31.6], [29.0, 31.2], [32.3, 31.3], [34.25, 31.3], [34.9, 29.5], [34.75, 29.0], [34.6, 28.5], [34.5, 28.0], [34.3, 27.7], [32.55, 29.85],
    [34.0, 27.25], [35.0, 25.05], [36.9, 22.0], [25.0, 22.0],
  ],
};

function inside(lat: number, lng: number, outline: [number, number][]): boolean {
  let hit = false;
  for (let i = 0, j = outline.length - 1; i < outline.length; j = i++) {
    const [xi, yi] = outline[i];
    const [xj, yj] = outline[j];
    if (yi > lat !== yj > lat && lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) hit = !hit;
  }
  return hit;
}

/**
 * A country from coordinates alone, for when the phone cannot name it (the web preview, or no geocoder). The same outlines the website uses
 * (apps/web/src/lib/market-detect.ts, tested there against twelve cities of each country and nine places outside).
 */
export function countryFromCoords(lat: number, lng: number): "SA" | "EG" | null {
  if (inside(lat, lng, OUTLINES.SA)) return "SA";
  if (inside(lat, lng, OUTLINES.EG)) return "EG";
  return null;
}

/** Whether location was already allowed, without asking (so a quiet re-check on launch never shows a prompt). */
export async function locationAllowed(): Promise<boolean> {
  try {
    return (await Location.getForegroundPermissionsAsync()).status === "granted";
  } catch {
    return false;
  }
}

/**
 * Finds where the person is: asks for permission (once), takes a high-accuracy fix and asks the phone's own geocoder which country that is.
 * The coordinates stay on the phone; only the market that results from them is ever used.
 */
export async function detectCountry(): Promise<Detection> {
  try {
    const asked = await Location.requestForegroundPermissionsAsync();
    if (asked.status !== "granted") return { ok: false, reason: "denied" };

    const fix = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.High });
    const { latitude, longitude, accuracy } = fix.coords;

    let country: string | null = null;
    if (Platform.OS !== "web") {
      try {
        country = (await Location.reverseGeocodeAsync({ latitude, longitude }))[0]?.isoCountryCode?.toUpperCase() ?? null;
      } catch {
        country = null;
      }
    }
    country ??= countryFromCoords(latitude, longitude);

    return { ok: true, country: country ?? "", latitude, longitude, accuracy: accuracy ?? null };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}
