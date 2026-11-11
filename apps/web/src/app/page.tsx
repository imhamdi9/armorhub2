import Link from "next/link";

export default function HomePage() {
  return (
    <main className="mx-auto max-w-2xl p-10">
      <h1 className="text-3xl font-bold">ArmorHub</h1>
      <p className="mt-2 text-muted-foreground">
        A marketplace platform connecting customers with trusted service
        providers, across automotive, home services, beauty, devices and more.
      </p>
      <Link
        href="/login"
        className="mt-6 inline-block rounded-lg bg-black px-4 py-2 text-white"
      >
        Sign in
      </Link>
    </main>
  );
}
