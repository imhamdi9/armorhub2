"use client";

import Link from "next/link";
import { tr } from "@/lib/i18n";

export default function HomePage() {
  return (
    <main className="mx-auto max-w-2xl p-10">
      <h1 className="text-3xl font-bold">{tr("ArmorHub")}</h1>
      <p className="mt-2 text-muted-foreground">
        {tr("A marketplace platform connecting customers with trusted service providers, across automotive, home services, beauty, devices and more.")}
      </p>
      <Link
        href="/login"
        className="mt-6 inline-block rounded-lg bg-black px-4 py-2 text-white"
      >
        {tr("Sign in")}
      </Link>
    </main>
  );
}
