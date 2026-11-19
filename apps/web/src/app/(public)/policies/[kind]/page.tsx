import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { PolicyView } from "@/components/public/policy-view";
import { PublicShell } from "@/components/public/shell";
import { POLICIES, POLICY_KINDS, type PolicyKind } from "@/lib/policies";

type Props = { params: Promise<{ kind: string }> };

export const dynamicParams = false;
export const generateStaticParams = () => POLICY_KINDS.map((kind) => ({ kind }));

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { kind } = await params;
  const policy = POLICIES[kind as PolicyKind];

  return policy ? { title: `${policy.title.en} — ArmorHub`, description: policy.summary.en, alternates: { canonical: `/policies/${kind}` } } : { title: "Not found" };
}

export default async function PolicyPage({ params }: Props) {
  const { kind } = await params;
  if (!POLICY_KINDS.includes(kind as PolicyKind)) notFound();

  return (
    <PublicShell>
      <PolicyView kind={kind as PolicyKind} />
    </PublicShell>
  );
}
