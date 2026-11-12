import { tr } from "@/lib/i18n";

/** A pill search that works without JavaScript (a plain GET to the catalogue), with the store's accent on the submit button. */
export function ShopSearch({ action, q = "", category = "" }: { action: string; q?: string; category?: string }) {
  return (
    <form role="search" action={action} method="get" className="mx-auto flex max-w-xl items-center rounded-full border border-black/10 bg-white py-1 ps-5 pe-1">
      <input name="q" defaultValue={q} type="search" aria-label={tr("Search products")} placeholder={tr("What are you looking for today?")}
        className="h-11 min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-[#5f5c5b]" />
      {category && <input type="hidden" name="category" value={category} />}
      <button type="submit" aria-label={tr("Search")} className="btn-brand grid size-12 shrink-0 place-items-center rounded-full" style={{ boxShadow: "0 4px 24px color-mix(in srgb, var(--brand) 34%, transparent)" }}>
        <svg viewBox="0 0 24 24" className="size-5 rtl:-scale-x-100" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </button>
    </form>
  );
}
