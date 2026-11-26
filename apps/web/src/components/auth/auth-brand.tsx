"use client";

import { platformLogo } from "@/lib/platform-brand";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

import { LogoMark } from "@/components/public/art";
import { tr } from "@/lib/i18n";
import { getPublicStoreName } from "@/services/auth.service";

const KEY = "last-store";

type Brand = { name: string; slug: string; logo_url?: string | null };

/**
 * Logo of the market (store) the visitor is signing in to or joining, linking to that market's home page.
 * The store comes from `?store=`, else from the last store the visitor opened; with none, the platform mark links to `/`.
 * `panel` sits on the dark side panel; `bar` is the compact version for small screens.
 */
export function AuthBrand({ variant = "bar" }: { variant?: "panel" | "bar" }) {
  const fromUrl = useSearchParams().get("store");
  const [brand, setBrand] = useState<Brand | null>(null);

  useEffect(() => {
    let live = true;
    let slug = fromUrl;
    try {
      if (slug) localStorage.setItem(KEY, slug);
      else slug = localStorage.getItem(KEY);
    } catch { /* storage unavailable */ }
    if (!slug) return;

    getPublicStoreName(slug)
      .then((s) => { if (live) setBrand({ name: s.name, slug: s.slug, logo_url: s.logo_url }); })
      .catch(() => { if (live) setBrand(null); });
    return () => { live = false; };
  }, [fromUrl]);

  const href = brand ? `/s/${encodeURIComponent(brand.slug)}` : "/";
  const name = brand?.name ?? tr("ArmorHub");
  const logo = (brand?.logo_url ?? platformLogo())
    // eslint-disable-next-line @next/next/no-img-element
    ? <img src={brand?.logo_url ?? platformLogo() ?? ""} alt="" className="h-10 w-10 rounded-xl object-cover" />
    : <LogoMark className="h-10 w-10" />;

  if (variant === "panel") {
    return (
      <Link href={href} className="flex w-max items-center gap-3 rounded-full border border-white/15 bg-white/10 py-2 pe-5 ps-2 text-white transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
        <span className="grid h-10 w-10 place-items-center overflow-hidden rounded-xl bg-lime">{brand?.logo_url || platformLogo() ? logo : <LogoMark className="h-10 w-10" />}</span>
        <span className="leading-tight">
          <span className="font-display block text-[19px] font-semibold tracking-tight">{name}</span>
          <span className="block text-xs text-white/70">{brand ? tr("Back to the store") : tr("Back to the home page")}</span>
        </span>
      </Link>
    );
  }

  return (
    <div className="flex justify-center pt-6 lg:hidden">
      <Link href={href} aria-label={name} className="inline-flex items-center gap-3 rounded-xl px-3 py-2 transition hover:bg-white/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black">
        {logo}
        <span className="font-display text-xl font-bold tracking-tight">{name}</span>
      </Link>
    </div>
  );
}
