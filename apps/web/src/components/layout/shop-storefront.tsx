import { ArrowRight, ChevronRight, Home, LayoutGrid, Search, ShoppingBag, Shield, Car, Wrench, Truck, Star, User, Package } from "lucide-react";

// Preview of a "Shop"-style storefront theme: white-canvas discovery, big rounded image cards, pill controls, one accent colour.
// Product photos are stand-in warm tiles; real photography is what makes this look work.
export const ACCENT = "#5433eb";
export const SHADOW = "0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1)";

type Item = { name: string; seller: string; price: string; was?: string; rating: string; tone: string; icon: React.ComponentType<{ className?: string }> };

export const ITEMS: Item[] = [
  { name: "Level IIIA vest", seller: "Nile Armor Co.", price: "EGP 18,500", was: "EGP 21,000", rating: "4.8 (212)", tone: "#d9c7ad", icon: Shield },
  { name: "Armoured SUV, whole car", seller: "Cairo Motors", price: "EGP 4.2M", rating: "4.9 (38)", tone: "#b98a6e", icon: Car },
  { name: "Ceramic plate set", seller: "Delta Defence", price: "EGP 9,900", rating: "4.7 (96)", tone: "#a9b39b", icon: Shield },
  { name: "Door panel upgrade", seller: "Giza Garage", price: "EGP 62,000", rating: "4.6 (54)", tone: "#e6dccb", icon: Wrench },
  { name: "Fleet transport", seller: "Suez Logistics", price: "From EGP 3,200", rating: "4.5 (71)", tone: "#8f8378", icon: Truck },
  { name: "Armour inspection", seller: "Alex Certify", price: "EGP 2,400", rating: "5.0 (19)", tone: "#c9b79a", icon: Package },
  { name: "Run-flat tyre kit", seller: "Giza Garage", price: "EGP 14,800", rating: "4.7 (63)", tone: "#b5aea4", icon: Car },
  { name: "Helmet, ballistic", seller: "Nile Armor Co.", price: "EGP 7,600", rating: "4.8 (140)", tone: "#cdbba0", icon: Shield },
];

export const PILLS = [{ l: "Armour", i: Shield }, { l: "Vehicles", i: Car }, { l: "Services", i: Wrench }, { l: "Transport", i: Truck }, { l: "Parts", i: Package }];

function Stars({ value }: { value: string }) {
  return (
    <span className="flex items-center gap-1 text-[11px] text-[#5f5c5b]">
      <Star className="size-3 fill-black text-black" aria-hidden /> {value}
    </span>
  );
}

export function Tile({ item, big }: { item: Item; big?: boolean }) {
  const Icon = item.icon;
  return (
    <a href="#" className="block rounded-[28px] bg-white p-2 transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#5433eb]" style={{ boxShadow: SHADOW }}>
      <div className={`grid place-items-center rounded-[20px] ${big ? "aspect-[4/5]" : "aspect-square"}`} style={{ background: item.tone }}>
        <Icon className="size-1/3 text-white/80" aria-hidden />
      </div>
      <div className="px-3 pb-3 pt-3">
        <p className="text-[14px] tracking-[-0.014em] text-black">{item.name}</p>
        <p className="mt-0.5 text-[12px] text-[#5f5c5b]">{item.seller}</p>
        <p className="mt-2 flex items-baseline gap-2 text-[14px] tracking-[-0.014em] text-black">
          {item.price}
          {item.was && <s className="text-[12px] text-[#5f5c5b]">{item.was}</s>}
        </p>
        <div className="mt-1"><Stars value={item.rating} /></div>
      </div>
    </a>
  );
}

export function Band({ title, items }: { title: string; items: Item[] }) {
  return (
    <section className="mt-16">
      <h2 className="mb-6 flex items-center text-[20px] tracking-[-0.05em] text-black">
        {title} <ChevronRight className="size-4" aria-hidden />
      </h2>
      <div className="relative">
        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          {items.map((i) => <Tile key={i.name} item={i} />)}
        </div>
        <button type="button" aria-label="Next" className="absolute -right-4 top-1/2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white lg:grid" style={{ boxShadow: "0 4px 24px rgba(0,0,0,.12)" }}>
          <ChevronRight className="size-4" aria-hidden />
        </button>
      </div>
    </section>
  );
}

export function ShopHero() {
  return (
    <>
        {/* hero */}
        <header className="overflow-x-clip pt-16 text-center">
          <div className="relative mx-auto h-48 max-w-xl" aria-hidden>
            {[
              { x: "6%", y: 20, r: -6, it: ITEMS[0] }, { x: "30%", y: 0, r: 3, it: ITEMS[1] }, { x: "54%", y: 26, r: -3, it: ITEMS[2] }, { x: "76%", y: 6, r: 6, it: ITEMS[7] },
            ].map(({ x, y, r, it }) => (
              <div key={it.name} className="absolute w-[18%] min-w-24 rounded-[28px] bg-white p-1.5" style={{ left: x, top: y, rotate: `${r}deg`, boxShadow: SHADOW }}>
                <div className="grid aspect-square place-items-center rounded-[20px]" style={{ background: it.tone }}><it.icon className="size-7 text-white/80" /></div>
                <p className="px-1.5 pb-1.5 pt-1.5 text-left text-[12px] text-black">{it.seller}</p>
              </div>
            ))}
          </div>
          <h1 className="mt-6 text-[64px] leading-none tracking-[-0.06em]" style={{ color: ACCENT }}>
            armorhub<span className="text-black">.</span>
          </h1>
          <form role="search" className="mx-auto mt-8 flex max-w-xl items-center rounded-full border border-black/10 bg-white py-1 pl-5 pr-1">
            <input type="search" aria-label="Search the market" placeholder="What are you looking for today?" className="h-11 min-w-0 flex-1 bg-transparent text-[16px] outline-none placeholder:text-[#5f5c5b]" />
            <button type="submit" aria-label="Search" className="grid size-12 shrink-0 place-items-center rounded-full text-white" style={{ background: ACCENT, boxShadow: "0 4px 24px rgba(69,36,219,.34)" }}>
              <ArrowRight className="size-5" aria-hidden />
            </button>
          </form>
          <div className="mt-6 flex flex-wrap justify-center gap-2">
            {PILLS.map(({ l, i: Icon }) => (
              <a key={l} href="#" className="flex items-center gap-2 rounded-full border border-[#ebebeb] bg-white py-1.5 pl-1.5 pr-4 text-[16px]" style={{ boxShadow: "0 2px 8px rgba(0,0,0,.06)" }}>
                <span className="grid size-6 place-items-center rounded-full bg-[#f2f4f5]"><Icon className="size-3.5" aria-hidden /></span>{l}
              </a>
            ))}
          </div>
        </header>

    </>
  );
}

export function ShopShell({ children }: { children: React.ReactNode }) {
  const nav = [{ l: "Home", i: Home, on: true }, { l: "Markets", i: LayoutGrid }, { l: "Search", i: Search }, { l: "Cart", i: ShoppingBag }];
  return (
    <div className="min-h-screen bg-[#f2f4f5] tracking-[-0.031em] text-black">
      {/* left rail (bottom bar on small screens) */}
      <nav aria-label="Primary" className="fixed inset-x-0 bottom-0 z-20 flex justify-around bg-white py-1 md:inset-y-0 md:right-auto md:w-16 md:flex-col md:items-center md:justify-start md:gap-1 md:py-4">
        {nav.map(({ l, i: Icon, on }) => (
          <a key={l} href="#" aria-label={l} aria-current={on ? "page" : undefined} className={`grid size-12 place-items-center rounded-[20px] ${on ? "bg-[#f2f4f5]" : ""}`}>
            <Icon className="size-6" aria-hidden />
          </a>
        ))}
        <a href="#" aria-label="Account" className="grid size-12 place-items-center md:mt-auto"><span className="grid size-8 place-items-center rounded-full bg-white ring-1 ring-[#ebebeb]"><User className="size-4" aria-hidden /></span></a>
      </nav>

      <main className="mx-auto max-w-[1200px] px-4 pb-28 md:pl-24 md:pr-6">
        {children}
      </main>

      <footer className="bg-black py-12 text-[14px] text-white md:pl-16">
        <div className="mx-auto grid max-w-[1200px] grid-cols-2 gap-8 px-6 md:grid-cols-4">
          {["Shop", "Sell", "Partners", "Help"].map((h) => (
            <div key={h}><p className="mb-3">{h}</p><ul className="space-y-2 text-white/70"><li>Link one</li><li>Link two</li><li>Link three</li></ul></div>
          ))}
        </div>
      </footer>
    </div>
  );
}

export function ShopStorefront() {
  return (
    <ShopShell>
      <ShopHero />
        <Band title="Armour" items={ITEMS.slice(0, 4)} />

        {/* hero-and-grid band */}
        <section className="mt-16">
          <h2 className="mb-6 flex items-center text-[20px] tracking-[-0.05em]">Vehicles <ChevronRight className="size-4" aria-hidden /></h2>
          <div className="grid gap-3 lg:grid-cols-[1.2fr_1fr]">
            <a href="#" className="relative grid min-h-80 place-items-center overflow-hidden rounded-[28px] bg-[#332f2d] p-6 text-white">
              <Car className="size-24 text-white/20" aria-hidden />
              <div className="absolute left-6 top-6">
                <p className="text-[32px] leading-none tracking-[-0.05em]">Cairo Motors</p>
                <p className="mt-2 flex items-center gap-1 text-[14px]"><Star className="size-3.5 fill-white" aria-hidden /> 4.9 · 38 reviews</p>
              </div>
            </a>
            <div className="grid grid-cols-2 gap-3">{[ITEMS[1], ITEMS[6], ITEMS[3], ITEMS[4]].map((i) => <Tile key={i.name} item={i} />)}</div>
          </div>
        </section>

        <Band title="Services" items={[ITEMS[5], ITEMS[4], ITEMS[3], ITEMS[7]]} />
    </ShopShell>
  );
}
