"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { me } from "@/services/auth.service";

export default function DashboardPage() {
  const router = useRouter();

  const [user, setUser] = useState<any>(null);

  useEffect(() => {
    async function checkUser() {
      try {
        const data = await me();
        setUser(data);
      } catch {
        localStorage.removeItem("token");
        router.replace("/login");
      }
    }

    checkUser();
  }, [router]);

  if (!user) {
    return <div>Loading...</div>;
  }

  return (
    <div>
      <h2 className="text-3xl font-bold">
        Welcome, {user.name}
      </h2>

      <p className="mt-2 text-muted-foreground">
        {user.email}
      </p>
    </div>
  );
}