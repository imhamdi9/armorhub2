"use client";

import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { tr } from "@/lib/i18n";

export interface CartonValues { sku: string; units_per_carton: string; carton_length_cm: string; carton_width_cm: string; carton_height_cm: string; carton_weight_kg: string; stock_cartons: string }

export const emptyCarton: CartonValues = { sku: "", units_per_carton: "", carton_length_cm: "", carton_width_cm: "", carton_height_cm: "", carton_weight_kg: "", stock_cartons: "" };

/** The numbers sent to the API: blanks mean "not set". */
export const cartonPayload = (v: CartonValues) => ({
  sku: v.sku.trim() || null,
  units_per_carton: v.units_per_carton === "" ? null : Number(v.units_per_carton),
  carton_length_cm: v.carton_length_cm === "" ? null : Number(v.carton_length_cm),
  carton_width_cm: v.carton_width_cm === "" ? null : Number(v.carton_width_cm),
  carton_height_cm: v.carton_height_cm === "" ? null : Number(v.carton_height_cm),
  carton_weight_kg: v.carton_weight_kg === "" ? null : Number(v.carton_weight_kg),
  stock_cartons: v.stock_cartons === "" ? 0 : Number(v.stock_cartons),
});

/** SKU and how the product is packed and counted: the data a warehouse and a carrier need. */
export function CartonFields({ value, onChange }: { value: CartonValues; onChange: (v: CartonValues) => void }) {
  const set = (k: keyof CartonValues) => (e: React.ChangeEvent<HTMLInputElement>) => onChange({ ...value, [k]: e.target.value });
  const units = Number(value.units_per_carton || 0) * Number(value.stock_cartons || 0);

  return (
    <fieldset className="col-span-2 space-y-3 rounded-lg border p-3">
      <legend className="px-1 text-sm font-medium">{tr("SKU and cartons")}</legend>
      <div>
        <Label htmlFor="sku">{tr("SKU (product code)")}</Label>
        <Input id="sku" dir="ltr" value={value.sku} onChange={set("sku")} placeholder={tr("Leave empty to get one automatically")} />
      </div>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div><Label htmlFor="upc">{tr("Units per carton")}</Label><Input id="upc" type="number" min="1" value={value.units_per_carton} onChange={set("units_per_carton")} /></div>
        <div><Label htmlFor="stock_cartons">{tr("Cartons in stock")}</Label><Input id="stock_cartons" type="number" min="0" value={value.stock_cartons} onChange={set("stock_cartons")} /></div>
        <div><Label htmlFor="cl">{tr("Carton length (cm)")}</Label><Input id="cl" type="number" min="0" step="0.1" value={value.carton_length_cm} onChange={set("carton_length_cm")} /></div>
        <div><Label htmlFor="cw">{tr("Carton width (cm)")}</Label><Input id="cw" type="number" min="0" step="0.1" value={value.carton_width_cm} onChange={set("carton_width_cm")} /></div>
        <div><Label htmlFor="ch">{tr("Carton height (cm)")}</Label><Input id="ch" type="number" min="0" step="0.1" value={value.carton_height_cm} onChange={set("carton_height_cm")} /></div>
        <div><Label htmlFor="cwt">{tr("Carton weight (kg)")}</Label><Input id="cwt" type="number" min="0" step="0.001" value={value.carton_weight_kg} onChange={set("carton_weight_kg")} /></div>
      </div>
      {units > 0 && <p className="text-xs text-muted-foreground">{tr("{n} units in stock in total.", { n: units })}</p>}
    </fieldset>
  );
}
