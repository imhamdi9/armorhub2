/** The page an e-mail link asked for, kept only when it is a page of this site (never another address a crafted link could send someone to). */
export function guestAccessTarget(to: string | null): string {
  return to && /^\/(?![/\\])[^\\\s]*$/.test(to) ? to : "/dashboard/orders";
}
