import { api } from "@/services/api";

export async function getVehicleMakes() {
  const response = await api.get("/vehicle-makes");

  return response.data;
}