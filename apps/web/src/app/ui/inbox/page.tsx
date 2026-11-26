import { InboxLayout, type Threads } from "@/components/layout/inbox-layout";

// Sample conversations for previewing the layout only.
const THREADS: Threads = {
  t1: { sender: "Priya Raman", time: "10:42", subject: "Q2 pricing proposal", participants: "Priya Raman, Marcus Lee, you", unread: true, messages: [
    { from: "Priya Raman", time: "Mon 09:10", text: "Hi all, attached is the draft pricing for Q2. The main change is moving the mid tier from $29 to $34, with a longer free trial to offset it." },
    { from: "You", time: "Mon 11:32", text: "Thanks Priya. Do we have data on how many mid-tier customers are on monthly billing? That is where I would expect the most pushback.", outgoing: true },
    { from: "Marcus Lee", time: "Mon 14:05", text: "About 62% monthly. I can pull the churn history by cohort if that helps." },
    { from: "Priya Raman", time: "Today 10:42", text: "Yes please. If the cohorts hold up I would like to take it to the leadership review on Thursday." },
  ] },
  t2: { sender: "Studioform", time: "09:15", subject: "Your invitation to join the workspace", participants: "Jules Carter, you", unread: true, messages: [
    { from: "Jules Carter", time: "Today 09:15", text: "Welcome aboard! I added you as an Editor. Start with the Getting started board, then ping me with any questions." },
  ] },
  t3: { sender: "Lena Fischer", time: "Yesterday", subject: "Brand guidelines v3", participants: "Lena Fischer, you", unread: true, messages: [
    { from: "Lena Fischer", time: "Yesterday 16:20", text: "The v3 guidelines are in the shared drive. The biggest update is the new colour palette and the accessibility notes for contrast.\n\nCould you review section 4 before Friday?" },
    { from: "You", time: "Yesterday 17:02", text: "Will do. Is the contrast table final or still changing?", outgoing: true },
    { from: "Lena Fischer", time: "Yesterday 17:20", text: "Final. Only the examples might change." },
  ] },
  t4: { sender: "Billing", time: "Mon", subject: "Receipt for March", participants: "Billing, you", messages: [
    { from: "Billing", time: "Mon 08:00", text: "Thanks for your payment. Your receipt for March is attached. Nothing else is needed from you." },
  ] },
  t5: { sender: "Tomás Herrera", time: "Sun", subject: "Offsite planning", participants: "Tomás Herrera, Aisha Bello, you", messages: [
    { from: "Tomás Herrera", time: "Sun 12:10", text: "Three options for the offsite: the lake house, the city hotel, or the mountain lodge. Votes by Wednesday?" },
    { from: "You", time: "Sun 13:45", text: "Lake house for me. It is the easiest to get to.", outgoing: true },
    { from: "Aisha Bello", time: "Sun 15:00", text: "Lodge, but I can live with the lake house." },
  ] },
  t6: { sender: "Noor Haddad", time: "Feb 28", subject: "Board meeting recap", participants: "Noor Haddad, you", messages: [
    { from: "Noor Haddad", time: "Feb 28 18:30", text: "Short recap: reporting was approved, hiring plan is on hold until April, and we revisit pricing next month." },
  ] },
};

export default function InboxPreview() {
  return (
    <div className="h-screen bg-neutral-50 p-3 sm:p-6">
      <InboxLayout initial={THREADS} initiallyOpen="t1" />
    </div>
  );
}
