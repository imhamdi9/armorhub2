"use client";

import Link from "next/link";

import { LanguageSwitcher, tr } from "@/lib/i18n";

const TYPES = [
  { href: "/register", icon: "🛒", title: "I want to buy", text: "Order products and services, compare quotes from professionals near you and pay securely.", cta: "Create a customer account", note: "Instant: no review needed." },
  { href: "/partner-signup", icon: "🛠️", title: "I provide services", text: "Installers, repair shops, salons and technicians: receive quote requests from nearby customers and do the work.", cta: "Become a partner", note: "We verify your documents first." },
  { href: "/seller-signup", icon: "📦", title: "I sell products", text: "Brands and companies: list your own products in a marketplace and ship them to its customers.", cta: "Sell on ArmorHub", note: "We verify your business first." },
] as const;

/** Where everyone starts: the three kinds of account. */
export default function SignupChooserPage() {
  return (
    <div className="relative flex min-h-screen flex-col items-center justify-center bg-muted px-4 py-12">
      <div className="absolute end-4 top-4"><LanguageSwitcher /></div>
      <h1 className="display display-md text-center">{tr("How will you use ArmorHub?")}</h1>
      <p className="mt-2 text-center text-muted-foreground">{tr("Choose the account that fits. You can sign in any time with the email you register.")}</p>

      <div className="mt-8 grid w-full max-w-5xl gap-4 md:grid-cols-3">
        {TYPES.map((t) => (
          <Link key={t.href} href={t.href} className="hover-lift flex flex-col rounded-[28px] border border-fog bg-white p-7">
            <span className="text-4xl" aria-hidden="true">{t.icon}</span>
            <h2 className="mt-4 text-xl font-black tracking-tight text-obsidian">{tr(t.title)}</h2>
            <p className="mt-2 flex-1 text-sm text-slate">{tr(t.text)}</p>
            <p className="mt-4 text-xs font-semibold text-spruce">{tr(t.note)}</p>
            <span className="btn-brand mt-3 block px-5 py-3 text-center">{tr(t.cta)}</span>
          </Link>
        ))}
      </div>
      <p className="mt-8 text-sm">{tr("Already have an account?")} <Link href="/login" className="underline">{tr("Sign in")}</Link></p>
    </div>
  );
}
