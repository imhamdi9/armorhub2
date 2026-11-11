"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ShippingPanel } from "@/components/orders/shipping-panel";
import { errorMessage } from "@/lib/errors";
import { statusLabel } from "@/lib/orders";
import { completeJob, getJobs, scheduleJob, startJob } from "@/services/platform.service";
import type { Order } from "@/types/platform";
import { formatDateTime, tr } from "@/lib/i18n";

/** Paid work assigned to the signed-in user's partner: schedule, start, complete. */
export default function JobsPage() {
  const [jobs, setJobs] = useState<Order[] | null>(null);
  const [when, setWhen] = useState<Record<number, string>>({});
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getJobs()
      .then(setJobs)
      .catch((e) => {
        setError(errorMessage(e));
        setJobs([]);
      });
  }, [tick]);

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  return (
    <div>
      <PageHeader title={tr("Jobs")} />
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {jobs === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : jobs.length === 0 ? (
        <EmptyState>{tr("No jobs yet. Jobs appear here once a customer accepts your quote and pays.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {jobs.map((job) => (
            <div key={job.id} className="rounded-lg border p-4">
              <div className="flex items-start justify-between">
                <div>
                  <p className="font-semibold">
                    {job.product?.name} · {tr("Order #{id}", { id: job.id })}
                  </p>
                  <p className="text-sm text-muted-foreground">
                    {job.customer?.name}
                    {job.customer?.city ? ` · ${job.customer.city}` : ""}
                    {job.subject && "name" in job.subject ? ` · ${String(job.subject.name)}` : ""}
                  </p>
                  {job.scheduled_at && (
                    <p className="mt-1 text-sm">{tr("Appointment")}: {formatDateTime(job.scheduled_at)}</p>
                  )}
                </div>
                <Badge variant="secondary">{statusLabel(job.status)}</Badge>
              </div>

              <div className="mt-3"><ShippingPanel orderId={job.id} paid /></div>

              {["paid", "appointment_scheduled"].includes(job.status) && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                  <Input
                    className="w-60"
                    type="datetime-local"
                    value={when[job.id] ?? ""}
                    onChange={(e) => setWhen((w) => ({ ...w, [job.id]: e.target.value }))}
                  />
                  <Button
                    size="sm"
                    disabled={!when[job.id]}
                    onClick={() => run(() => scheduleJob(job.id, new Date(when[job.id]).toISOString()))}
                  >
                    {job.status === "paid" ? "Schedule" : "Reschedule"}
                  </Button>
                  {job.status === "appointment_scheduled" && (
                    <Button size="sm" variant="outline" onClick={() => run(() => startJob(job.id))}>
                      {tr("Start work")}
                    </Button>
                  )}
                </div>
              )}

              {job.status === "service_in_progress" && (
                <Button className="mt-3" size="sm" onClick={() => run(() => completeJob(job.id))}>
                  {tr("Mark completed")}
                </Button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
