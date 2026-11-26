"use client";

import { toast, ToastViewport } from "@/components/layout/toast";

const btn = "inline-flex min-h-11 items-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export default function ToastPreview() {
  return (
    <div className="min-h-screen bg-neutral-50 p-10">
      <h1 className="text-2xl font-semibold text-neutral-900">Toasts</h1>
      <p className="mt-2 text-sm text-neutral-500">Each one counts down for five seconds. Hover to pause, or close it.</p>
      <div className="mt-6 flex flex-wrap gap-3">
        <button type="button" className={btn} onClick={() => toast("Changes saved", "Your workspace settings were updated.", "success")}>Success toast</button>
        <button type="button" className={btn} onClick={() => toast("Couldn’t send invite", "That email address bounced. Check it and try again.", "error")}>Error toast</button>
        <button type="button" className={btn} onClick={() => { toast("First", "Sits at the top of the stack.", "success"); toast("Second", "Below the first.", "info"); toast("Third", "Newest, at the bottom.", "error"); }}>Stack three</button>
      </div>
      <ToastViewport />
    </div>
  );
}
