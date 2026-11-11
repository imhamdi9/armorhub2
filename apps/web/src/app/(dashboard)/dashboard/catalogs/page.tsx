"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { aliasLabel } from "@/lib/navigation";
import { getIndustries } from "@/services/platform.service";
import type { Industry } from "@/types/auth";

/** Every industry's shared lists in one place (platform admins). */
export default function CatalogsPage() {
  const [industries, setIndustries] = useState<Industry[] | null>(null);

  useEffect(() => {
    getIndustries().then(setIndustries).catch(() => setIndustries([]));
  }, []);

  return (
    <div>
      <PageHeader title="Catalogs" />

      {industries === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : industries.length === 0 ? (
        <EmptyState>No industries are set up.</EmptyState>
      ) : (
        <div className="space-y-6">
          {industries.map((industry) => (
            <section key={industry.id}>
              <h2 className="mb-2 text-xl font-semibold">{industry.name}</h2>
              {industry.catalogs && industry.catalogs.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                  {industry.catalogs.map((alias) => (
                    <Link
                      key={alias}
                      href={`/dashboard/catalog/${alias}`}
                      className="rounded-lg border px-4 py-2 hover:bg-muted"
                    >
                      {aliasLabel(alias)}
                    </Link>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No shared lists.</p>
              )}
            </section>
          ))}
        </div>
      )}
    </div>
  );
}
