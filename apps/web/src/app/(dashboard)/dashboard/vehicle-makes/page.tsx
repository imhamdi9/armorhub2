"use client";

import { useEffect, useState } from "react";
import { getVehicleMakes } from "@/services/vehicle-make.service";

export default function VehicleMakesPage() {
  const [makes, setMakes] = useState<{ id: number; name: string; slug: string }[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");

  useEffect(() => {
    async function loadMakes() {
      const data = await getVehicleMakes();
      setMakes(data);
    }

    loadMakes();
  }, []);

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-3xl font-bold">Vehicle Makes</h1>

        <button
          onClick={() => setShowForm(true)}
          className="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
        >
          Add Vehicle Make
        </button>
      </div>

      {showForm && (
        <div className="mb-6 rounded-lg border p-4">
          <div className="mb-4">
            <label className="mb-1 block font-medium">Name</label>

            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-lg border px-3 py-2"
            />
          </div>

          <div className="mb-4">
            <label className="mb-1 block font-medium">Slug</label>

            <input
              value={slug}
              onChange={(e) => setSlug(e.target.value)}
              className="w-full rounded-lg border px-3 py-2"
            />
          </div>

          <button className="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700">
            Save
          </button>
        </div>
      )}

      <div className="space-y-3">
        {makes.map((make) => (
          <div key={make.id} className="rounded-lg border p-4">
            <h2 className="font-semibold">{make.name}</h2>
            <p className="text-sm text-gray-500">{make.slug}</p>
          </div>
        ))}
      </div>
    </div>
  );
}