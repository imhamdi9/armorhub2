import { AnswerLink, HelpCentreFaq, type FaqCategory } from "@/components/layout/help-centre-faq";

// Sample questions for previewing the layout only.
const CATEGORIES: FaqCategory[] = [
  { id: "billing", title: "Billing", items: [
    { question: "When will I be charged?", answer: <>You are charged on the same day each month that you first subscribed. A receipt is emailed straight away and is always available under <AnswerLink href="#accounts">your account</AnswerLink>.</> },
    { question: "Can I get a refund?", answer: "Yes. If you cancel within 14 days of a new subscription we refund the full amount, no questions asked. After that, we refund the unused part of an annual plan on request." },
    { question: "How do I change my payment method?", answer: <>Open Billing in your settings, choose &ldquo;Payment method&rdquo; and add a new card. The old one is removed once the new one is confirmed. Having trouble? <AnswerLink href="#contact">Talk to us</AnswerLink>.</> },
  ] },
  { id: "accounts", title: "Accounts", items: [
    { question: "How do I add a teammate?", answer: "Go to Members, press Invite and enter their work email. They get a link that lasts seven days, and you choose their role before they join." },
    { question: "Can I change the email address on my account?", answer: <>Yes, from Profile settings. We send a confirmation link to the new address, and your sign-in details stay the same. See <AnswerLink href="#security">Security</AnswerLink> for what happens to active sessions.</> },
  ] },
  { id: "security", title: "Security", items: [
    { question: "Do you support two-factor sign-in?", answer: "Yes. You can use an authenticator app, a hardware key or a passkey. We recommend adding a passkey and keeping one backup method." },
    { question: "What should I do if I think my account was accessed by someone else?", answer: <>Sign out everywhere from Security settings, change your password, then <AnswerLink href="#contact">contact support</AnswerLink> so we can review recent activity with you.</> },
    { question: "Where is my data protected?", answer: "Data is encrypted in transit and at rest, and access inside our team is limited to the people who need it and is logged." },
  ] },
  { id: "data", title: "Data", items: [
    { question: "Can I export everything I have stored?", answer: <>Yes. Request an export from Settings and we email a download link, usually within an hour. Exports are in open formats so you can <AnswerLink href="#billing">keep your records</AnswerLink> if you leave.</> },
    { question: "What happens to my data if I close my account?", answer: "It is kept for 30 days in case you change your mind, then permanently deleted from our systems and backups within 90 days." },
  ] },
];

export default function HelpCentrePreview() {
  return (
    <div className="min-h-screen bg-white">
      <div className="mx-auto max-w-6xl px-4 pt-14 sm:px-6">
        <h1 className="text-3xl font-semibold tracking-tight text-neutral-900">Help centre</h1>
        <p className="mt-2 text-sm text-neutral-500">Answers to the questions we hear most.</p>
      </div>
      <HelpCentreFaq categories={CATEGORIES} />
      <div id="contact" className="h-40" />
    </div>
  );
}
