import { api } from "@/services/api";
import type { LoginRequest, LoginResponse } from "@/types/auth";

export async function login(data: LoginRequest) {
  const response = await api.post<LoginResponse>("/login", data);

  return response.data;
}

export async function me() {
  const response = await api.get("/me");

  return response.data;
}
export async function register(data: {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  phone?: string;
  /** Either the store the visitor came from (its address) or a company id. */
  store?: string;
  company_id?: number;
  signup_token: string;
  website?: string;
  captcha_token?: string;
}) {
  const response = await api.post<LoginResponse>("/register", data);

  return response.data;
}

export async function forgotPassword(data: { email: string; signup_token: string; website?: string; captcha_token?: string }) {
  return (await api.post<{ message: string }>("/forgot-password", data)).data;
}

export async function resetPassword(data: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}) {
  return (await api.post<{ message: string }>("/reset-password", data)).data;
}

export interface SignupChallenge {
  token: string;
  min_seconds: number;
  captcha: { provider: "turnstile" | "hcaptcha" | "recaptcha"; site_key: string } | null;
}

/** Human-check data the signup and reset forms need before they can be submitted. */
export async function getChallenge() {
  return (await api.get<SignupChallenge>("/signup-challenge")).data;
}

export async function logout() {
  return (await api.post("/logout")).data;
}

export async function resendVerification() {
  return (await api.post<{ message: string }>("/email/resend")).data;
}

export async function getPublicStoreName(slug: string) {
  const { data } = await api.get<{ store: { name: string; slug: string }; market: { code: string; phone: { dial: string; example: string } } | null }>(
    `/public/storefronts/${encodeURIComponent(slug)}`,
  );

  return { ...data.store, market: data.market };
}

export interface PartnerApplicationForm {
  store: string;
  business_name: string;
  contact_name: string;
  email: string;
  phone: string;
  city: string;
  country: string;
  description?: string;
  latitude?: number;
  longitude?: number;
  password: string;
  password_confirmation: string;
  signup_token: string;
  website?: string;
  captcha_token?: string;
}

export async function applyAsPartner(data: PartnerApplicationForm) {
  return (await api.post<{ message: string }>("/partner-applications", data)).data;
}

/** The open stores a business can apply to (public). */
export async function getOpenStores() {
  const { data } = await api.get<{ stores: { name: string; slug: string; market: string | null }[] }>("/public/storefronts");

  return data.stores;
}
