import { describe, expect, it } from "vitest";

import { marketFromCoords } from "@/lib/market-detect";

const SA: [string, number, number][] = [
  ["Riyadh", 24.71, 46.67], ["Jeddah", 21.54, 39.17], ["Dammam", 26.43, 50.1], ["Khobar", 26.28, 50.2], ["Makkah", 21.42, 39.83], ["Medina", 24.47, 39.61],
  ["Tabuk", 28.38, 36.57], ["Abha", 18.22, 42.5], ["Jizan", 16.89, 42.55], ["Najran", 17.5, 44.13], ["Hail", 27.52, 41.7], ["Yanbu", 24.09, 38.06],
];
const EG: [string, number, number][] = [
  ["Cairo", 30.04, 31.24], ["Alexandria", 31.2, 29.92], ["Aswan", 24.09, 32.9], ["Luxor", 25.7, 32.64], ["Sharm el-Sheikh", 27.91, 34.33],
  ["Taba", 29.49, 34.89], ["Nuweiba", 29.03, 34.67], ["Hurghada", 27.26, 33.81], ["Marsa Alam", 25.07, 34.89], ["Port Said", 31.26, 32.3],
  ["Siwa", 29.2, 25.52], ["Suez", 29.97, 32.55],
];
const NEITHER: [string, number, number][] = [
  ["Paris", 48.85, 2.35], ["Dubai", 25.2, 55.27], ["Abu Dhabi", 24.45, 54.38], ["Doha", 25.28, 51.53], ["Kuwait City", 29.38, 47.98],
  ["Amman", 31.95, 35.93], ["Eilat", 29.56, 34.95], ["Beirut", 33.89, 35.5], ["Khartoum", 15.5, 32.56],
];

describe("marketFromCoords", () => {
  it.each(SA)("%s is in the Saudi market", (_n, lat, lng) => expect(marketFromCoords(lat, lng)).toBe("SA"));
  it.each(EG)("%s is in the Egyptian market", (_n, lat, lng) => expect(marketFromCoords(lat, lng)).toBe("EG"));
  it.each(NEITHER)("%s is in neither", (_n, lat, lng) => expect(marketFromCoords(lat, lng)).toBeNull());
});
