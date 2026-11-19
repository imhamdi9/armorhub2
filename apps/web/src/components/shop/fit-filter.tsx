"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { tr } from "@/lib/i18n";
import { getGarage, type Vehicle } from "@/services/ops.service";
import { me } from "@/services/auth.service";

/** For a signed-in customer with a car in the garage: show only the parts that fit it. The choice lives in the address, so the list stays server-rendered. */
export function FitFilter({
  base, q, category, sort, industry, fit,
}: {
  base: string; q: string; category: string; sort: string; industry: string; fit?: { vehicle_model_id: string; year: string };
}) {
  const router = useRouter();
  const [cars, setCars] = useState<Vehicle[]>([]);
  useEffect(() => {
    me().then(() => getGarage().then(setCars)).catch(() => setCars([]));
  }, []);

  if (cars.length === 0) return null;
  const current = cars.find((c) => fit && String(c.vehicle_model_id) === fit.vehicle_model_id && String(c.year) === fit.year);

  function go(id: string) {
    const params = new URLSearchParams();
    if (q) params.set("q", q);
    if (category) params.set("category", category);
    if (sort) params.set("sort", sort);
    if (industry) params.set("industry", industry);
    const car = cars.find((c) => String(c.id) === id);
    if (car) { params.set("vehicle_model_id", String(car.vehicle_model_id)); params.set("year", String(car.year)); params.set("compatible", "1"); }
    router.push(params.toString() ? `${base}?${params}` : base);
  }

  return (
    <label className="mb-4 flex flex-wrap items-center gap-2 text-sm" data-testid="fit-filter">
      <span className="text-slate">{tr("Show only what fits")}:</span>
      <select value={current ? String(current.id) : ""} onChange={(e) => go(e.target.value)} className="h-9 rounded-full border border-border bg-white px-3">
        <option value="">{tr("All cars")}</option>
        {cars.map((c) => <option key={c.id} value={c.id}>{c.nickname || c.label}</option>)}
      </select>
    </label>
  );
}
