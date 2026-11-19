import type { ReactNode } from "react";

export type Platform = "chat" | "photo" | "network";

export interface SocialPost {
  name: string;
  handle: string;
  platform: Platform;
  /** Plain text. @mentions and #hashtags are picked out automatically. */
  body: string;
  /** Already formatted for display: "2h", "Mar 4". */
  time: string;
  /** An ISO date for the <time> element. */
  datetime: string;
  replies: number;
  likes: number;
  /** A favourite gets a darker border. */
  favourite?: boolean;
}

const AVATAR_TONES = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-emerald-200 text-emerald-900", "bg-violet-200 text-violet-900", "bg-orange-200 text-orange-900", "bg-teal-200 text-teal-900"];
const compact = new Intl.NumberFormat("en", { notation: "compact", maximumFractionDigits: 1 });
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

const PLATFORMS: Record<Platform, { label: string; glyph: ReactNode }> = {
  chat: { label: "Short post", glyph: <svg {...svg} className="size-4"><path d="M5 5h14a1.500 1.500 0 0 1 1.500 1.500v8A1.500 1.500 0 0 1 19 16h-7l-4.500 3.500V16H5a1.500 1.500 0 0 1-1.500-1.500v-8A1.500 1.500 0 0 1 5 5Z" /></svg> },
  photo: { label: "Photo post", glyph: <svg {...svg} className="size-4"><rect x="3.500" y="3.500" width="17" height="17" rx="5" /><circle cx="12" cy="12" r="3.800" /><circle cx="17" cy="7" r="0.600" fill="currentColor" /></svg> },
  network: { label: "Professional network post", glyph: <svg {...svg} className="size-4"><rect x="3.500" y="3.500" width="17" height="17" rx="3" /><path d="M8 10.500V16M8 7.800v.1M12 16v-3.200a2.300 2.300 0 0 1 4.600 0V16M12 10.500V16" /></svg> },
};

/** Wraps @mentions and #hashtags in blue-toned spans; everything else stays plain text. */
function renderBody(text: string): ReactNode[] {
  return text.split(/([@#][\p{L}\p{N}_]+)/u).map((part, i) =>
    /^[@#][\p{L}\p{N}_]+$/u.test(part) ? <span key={i} className="font-medium text-sky-700">{part}</span> : part,
  );
}

function Post({ post, tone }: { post: SocialPost; tone: string }) {
  const p = PLATFORMS[post.platform];

  return (
    <article className={`mb-4 break-inside-avoid rounded-xl border bg-white p-5 ${post.favourite ? "border-neutral-900" : "border-neutral-200"}`}>
      <header className="flex items-center gap-3">
        <span aria-hidden="true" className={`grid size-10 shrink-0 place-items-center rounded-full text-sm font-semibold ${tone}`}>{post.name[0]}</span>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium text-neutral-900">{post.name}</p>
          <p className="truncate text-sm text-neutral-500" dir="ltr">{post.handle}</p>
        </div>
        <span className="text-neutral-400" role="img" aria-label={p.label}>{p.glyph}</span>
      </header>

      <p className="mt-4 whitespace-pre-line text-[15px] leading-relaxed text-neutral-800">{renderBody(post.body)}</p>

      <footer className="mt-4 flex items-center gap-4 text-xs text-neutral-500">
        <time dateTime={post.datetime} className="me-auto">{post.time}</time>
        <span className="inline-flex items-center gap-1.5" aria-label={`${post.replies} replies`}>
          <svg {...svg} className="size-4"><path d="M5 5h14a1.500 1.500 0 0 1 1.500 1.500v8A1.500 1.500 0 0 1 19 16h-7l-4.500 3.500V16H5a1.500 1.500 0 0 1-1.500-1.500v-8A1.500 1.500 0 0 1 5 5Z" /></svg>
          {compact.format(post.replies)}
        </span>
        <span className="inline-flex items-center gap-1.5" aria-label={`${post.likes} likes`}>
          <svg {...svg} className="size-4"><path d="M12 20s-7.500-4.600-7.500-10.200A4.300 4.300 0 0 1 12 7.300a4.300 4.300 0 0 1 7.500 2.500C19.500 15.400 12 20 12 20Z" /></svg>
          {compact.format(post.likes)}
        </span>
      </footer>
    </article>
  );
}

/** A wall of posts: three CSS columns on wide screens, each post kept whole so the columns stagger by post length. */
export function SocialWall({ title, invite, posts }: { title: string; invite: string; posts: SocialPost[] }) {
  return (
    <section aria-labelledby="wall-title" className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6">
      <div className="mx-auto max-w-xl text-center">
        <h2 id="wall-title" className="text-2xl font-semibold tracking-tight text-neutral-900 sm:text-3xl">{title}</h2>
        <p className="mt-2 text-sm leading-relaxed text-neutral-500">{invite}</p>
      </div>

      <div className="mt-10 columns-1 gap-4 md:columns-2 lg:columns-3">
        {posts.map((post, i) => <Post key={post.handle + post.datetime} post={post} tone={AVATAR_TONES[i % AVATAR_TONES.length]} />)}
      </div>
    </section>
  );
}
