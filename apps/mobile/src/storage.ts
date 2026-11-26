import * as SecureStore from "expo-secure-store";
import { Platform } from "react-native";

// On a phone values live in secure storage (Keychain / Keystore). The web preview (for development) has no such storage and uses the browser's.
const web = Platform.OS === "web";

export async function storeGet(key: string): Promise<string | null> {
  return web ? globalThis.localStorage?.getItem(key) ?? null : SecureStore.getItemAsync(key);
}

export async function storeSet(key: string, value: string): Promise<void> {
  if (web) globalThis.localStorage?.setItem(key, value);
  else await SecureStore.setItemAsync(key, value);
}

export async function storeRemove(key: string): Promise<void> {
  if (web) globalThis.localStorage?.removeItem(key);
  else await SecureStore.deleteItemAsync(key);
}
