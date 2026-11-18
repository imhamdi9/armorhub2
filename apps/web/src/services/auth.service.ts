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
  /** A friend's invite code: their reward comes when this customer's first order arrives. */
  referral?: string;
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
  const { data } = await api.get<{ store: { name: string; slug: string; logo_url?: string | null; primary_color?: string }; market: { code: string; phone: { dial: string; example: string } } | null }>(
    `/public/storefronts/${encodeURIComponent(slug)}`,
  );

  return { ...data.store, market: data.market };
}

export type BusinessType = "partner" | "seller";

/** A business application is sent as a form with files (the verification documents). */
export async function applyAsBusiness(form: FormData) {
  // Let the browser set the multipart boundary itself (the default JSON header would break the upload).
  return (await api.post<{ message: string; plan_payment?: PlanPayment }>("/partner-applications", form, { headers: { "Content-Type": undefined } })).data;
}

/** What a business pays to start a paid plan right after it applied. */
export interface PlanPayment { application_id: number; token: string; plan: string; name: string; amount: number; currency: string }

export const payApplicationPlan = async (p: PlanPayment) => (await api.post<{ paid: boolean }>(`/partner-applications/${p.application_id}/pay-plan`, { token: p.token })).data;

/** The open stores a business can apply to (public). */
export async function getOpenStores() {
  const { data } = await api.get<{ stores: { name: string; slug: string; market: string | null }[] }>("/public/storefronts");

  return data.stores;
}

/** Marketplaces (a country's platform, e.g. "ArmorHub Egypt") that new sellers can join. */
export async function getMarketplaces() {
  const { data } = await api.get<{ marketplaces: { key: string; name: string; industry: string | null; industries?: string[]; market: string | null; country?: string | null; currency?: string | null }[] }>("/public/marketplaces");

  return data.marketplaces;
}

/** The live terms a partner sees before joining: the commission, how it is shared, and how many requests and partners there are right now. */
export interface PublicTerms {
  commission_percentage: number;
  split: { platform: number; company: number; partner: number };
  open_requests: number;
  verified_partners: number;
  customer_fee_percentage?: number;
  plans?: { key: string; name: string; price_monthly: number; currency: string; customer_limit: number | null; commission_percentage: number; team_seats: number }[];
}

export const getPublicTerms = async () => (await api.get<PublicTerms>("/public/terms")).data;

export type SocialProvider = "google" | "facebook" | "apple";

/** Which "Continue with …" buttons are set up, with the public id each needs. */
export const getSocialProviders = async () => (await api.get<{ providers: Partial<Record<SocialProvider, { client_id: string }>> }>("/auth/social/providers")).data.providers;

/** Sends what the provider returned; the server checks it and signs the person in (or creates a customer when a store is given). */
export const socialLogin = async (body: { provider: SocialProvider; credential: string; store?: string; name?: string }) => (await api.post<LoginResponse>("/auth/social", body)).data;
