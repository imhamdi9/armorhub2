"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { getFavourites, removeFavourite, type FavouriteProduct } from "@/services/ops.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** Products the customer kept to come back to. */
export default function FavouritesPage() {
  const user = useCurrentUser();
  const [rows, setRows] = useState<FavouriteProduct[] | null>(null);
  const load = () => getFavourites().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);
  const store = user?.store_slug;

  return (
    <div>
      <PageHeader title={tr("Favourites")} />
      {rows === null ? <LoadingBlock /> : rows.length === 0 ? <EmptyState>{tr("Nothing saved yet. Tap the heart on a product to keep it here.")}</EmptyState> : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {rows.map((p) => (
            <div key={p.id} className="overflow-hidden rounded-xl border" data-testid="favourite-item">
              {p.images?.[0] && (
                // eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo, served by the API
                <img src={imageSrc(p.images[0]) ?? undefined} alt="" className="aspect-[4/3] w-full object-cover" />
              )}
              <div className="p-3">
                <p className="font-semibold">{p.name}</p>
                <p className="text-sm text-muted-foreground">{p.from_price !== null ? formatMoney(p.from_price, p.currency) : ""}</p>
                <div className="mt-3 flex gap-2">
                  {store && <Link href={`/s/${store}/products/${p.id}`} className="inline-flex h-8 items-center rounded-full bg-primary px-4 text-[0.8rem] font-semibold text-primary-foreground">{tr("View product")}</Link>}
                  <Button size="sm" variant="outline" onClick={() => removeFavourite(p.id).then(load)}>{tr("Remove")}</Button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
