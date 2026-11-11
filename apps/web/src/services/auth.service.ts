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
  company_id: number;
}) {
  const response = await api.post<LoginResponse>("/register", data);

  return response.data;
}
