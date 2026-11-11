import type { CSSProperties } from "react";

/** Brand mark: a shield with a check, drawn in the page's brand colour. */
export function LogoMark({ className = "h-8 w-8" }: { className?: string }) {
  return (
    <svg viewBox="0 0 32 32" className={className} aria-hidden="true">
      <path d="M16 2 4 6.5v8.2c0 7.2 4.9 12.6 12 14.8 7.1-2.2 12-7.6 12-14.8V6.5L16 2Z" fill="var(--brand, #111827)" />
      <path d="m10.5 16.2 4 4 7.2-8" fill="none" stroke="var(--on-brand, #fff)" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

const ICONS: Record<string, string> = {
  shield: "M12 2 4 5v6c0 5 3.4 9.3 8 11 4.6-1.7 8-6 8-11V5l-8-3Zm-1.2 13.2-3-3 1.4-1.4 1.6 1.6 4-4 1.4 1.4-5.4 5.4Z",
  tag: "M3 12.6V4a1 1 0 0 1 1-1h8.6a1 1 0 0 1 .7.3l7.4 7.4a1 1 0 0 1 0 1.4l-8.6 8.6a1 1 0 0 1-1.4 0L3.3 13.3a1 1 0 0 1-.3-.7ZM7.5 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z",
  users: "M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20c0-3.3 3.1-6 7-6s7 2.7 7 6v1H2v-1Zm14.5-5.8c3 .3 5.5 2.4 5.5 5.3V21h-4v-1c0-2-.6-3.8-1.5-5.2Z",
  lock: "M7 10V7a5 5 0 0 1 10 0v3h1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V11a1 1 0 0 1 1-1h1Zm2 0h6V7a3 3 0 0 0-6 0v3Z",
  pin: "M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.500a2.5 2.5 0 0 1 0 5Z",
  globe: "M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.9 9h-3a15.6 15.6 0 0 0-1.2-5.5A8 8 0 0 1 18.900 11ZM12 4c.8 1 1.8 3.200 2.100 7H9.900C10.200 7.200 11.200 5 12 4ZM9.300 5.500A15.600 15.600 0 0 0 8.100 11h-3a8 8 0 0 1 4.200-5.500ZM5.100 13h3a15.600 15.600 0 0 0 1.200 5.500A8 8 0 0 1 5.100 13Zm4.800 0h4.200c-.3 3.800-1.300 6-2.100 7-.8-1-1.800-3.200-2.100-7Zm4.800 5.500c.6-1.200 1.100-3.100 1.200-5.500h3a8 8 0 0 1-4.200 5.500Z",
  check: "M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1.200 14.500-4-4 1.400-1.400 2.600 2.600 5.600-5.600 1.400 1.400-7 7Z",
  chat: "M4 4h16a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H9l-5 4V5a1 1 0 0 1 1-1Z",
  star: "m12 2 2.900 6.200 6.800.8-5 4.700 1.300 6.700L12 17l-6 3.400 1.300-6.700-5-4.700 6.800-.8L12 2Z",
};

export function Icon({ name, className = "h-6 w-6", style }: { name: keyof typeof ICONS | string; className?: string; style?: CSSProperties }) {
  return (
    <svg viewBox="0 0 24 24" className={className} style={style} fill="currentColor" aria-hidden="true">
      <path d={ICONS[name] ?? ICONS.check} />
    </svg>
  );
}

/** A soft dotted pattern + glow used behind hero art. */
function Backdrop() {
  return (
    <>
      <defs>
        <pattern id="dots" width="14" height="14" patternUnits="userSpaceOnUse">
          <circle cx="2" cy="2" r="1.2" fill="var(--brand, #111827)" opacity="0.14" />
        </pattern>
        <radialGradient id="glow" cx="50%" cy="45%" r="55%">
          <stop offset="0%" stopColor="var(--brand, #111827)" stopOpacity="0.22" />
          <stop offset="100%" stopColor="var(--brand, #111827)" stopOpacity="0" />
        </radialGradient>
      </defs>
      <rect width="480" height="360" rx="28" fill="url(#dots)" />
      <circle cx="240" cy="170" r="190" fill="url(#glow)" />
    </>
  );
}

const B = "var(--brand, #111827)";

/** Illustrations for each kind of business. Pure SVG: no downloads, always in the store's colours. */
export function HeroArt({ kind, className = "w-full" }: { kind?: string | null; className?: string }) {
  return (
    <svg viewBox="0 0 480 360" className={className} role="img" aria-label="" aria-hidden="true">
      <Backdrop />
      {kind === "home-services" ? (
        <g>
          <path d="M80 190 240 70l160 120v110a10 10 0 0 1-10 10H90a10 10 0 0 1-10-10V190Z" fill="#fff" stroke={B} strokeWidth="6" strokeLinejoin="round" />
          <path d="M60 195 240 55l180 140" fill="none" stroke={B} strokeWidth="14" strokeLinecap="round" strokeLinejoin="round" />
          <rect x="205" y="215" width="70" height="95" rx="8" fill={B} />
          <rect x="120" y="205" width="54" height="54" rx="6" fill={B} opacity="0.2" />
          <rect x="306" y="205" width="54" height="54" rx="6" fill={B} opacity="0.2" />
          <path d="M392 120v-40h26v62" fill="none" stroke={B} strokeWidth="6" />
          <g transform="translate(330 40)"><circle cx="20" cy="20" r="20" fill={B} /><path d="m11 21 6 6 12-13" fill="none" stroke="#fff" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" /></g>
        </g>
      ) : kind === "beauty" ? (
        <g>
          <ellipse cx="240" cy="290" rx="130" ry="16" fill={B} opacity="0.12" />
          <path d="M200 280v-90c0-18 12-30 40-30s40 12 40 30v90Z" fill="#fff" stroke={B} strokeWidth="6" strokeLinejoin="round" />
          <rect x="222" y="90" width="36" height="70" rx="8" fill={B} />
          <rect x="228" y="62" width="24" height="34" rx="6" fill={B} opacity="0.55" />
          <circle cx="240" cy="228" r="22" fill={B} opacity="0.18" />
          <g fill={B}><path d="m370 120 8 18 18 8-18 8-8 18-8-18-18-8 18-8 8-18Z" /><path d="m110 170 5 11 11 5-11 5-5 11-5-11-11-5 11-5 5-11Z" opacity="0.6" /><path d="m335 230 4 9 9 4-9 4-4 9-4-9-9-4 9-4 4-9Z" opacity="0.4" /></g>
        </g>
      ) : kind === "devices" ? (
        <g>
          <rect x="150" y="50" width="150" height="260" rx="26" fill="#fff" stroke={B} strokeWidth="6" />
          <rect x="166" y="82" width="118" height="190" rx="10" fill={B} opacity="0.12" />
          <rect x="205" y="62" width="40" height="8" rx="4" fill={B} />
          <path d="M225 66 330 70" stroke="none" />
          <g transform="translate(262 150)"><path d="M60 8 14 28v38c0 28 20 52 46 62 26-10 46-34 46-62V28L60 8Z" fill={B} /><path d="m38 70 16 16 30-34" fill="none" stroke="#fff" strokeWidth="9" strokeLinecap="round" strokeLinejoin="round" /></g>
          <rect x="176" y="106" width="98" height="12" rx="6" fill={B} opacity="0.3" /><rect x="176" y="130" width="70" height="12" rx="6" fill={B} opacity="0.2" />
        </g>
      ) : (
        <g>
          <ellipse cx="240" cy="290" rx="170" ry="18" fill={B} opacity="0.14" />
          <path d="M70 250c0-26 14-44 40-52l40-52c8-10 20-16 34-16h72c14 0 26 5 36 15l46 53c28 5 52 8 52 40v12a12 12 0 0 1-12 12H82a12 12 0 0 1-12-12v-0Z" fill="#fff" stroke={B} strokeWidth="6" strokeLinejoin="round" />
          <path d="m168 158 22-28c3-4 8-6 13-6h64c5 0 10 2 13 6l22 28H168Z" fill={B} opacity="0.2" />
          <circle cx="150" cy="268" r="34" fill={B} /><circle cx="150" cy="268" r="14" fill="#fff" />
          <circle cx="346" cy="268" r="34" fill={B} /><circle cx="346" cy="268" r="14" fill="#fff" />
          <rect x="396" y="208" width="30" height="14" rx="7" fill={B} />
          <g transform="translate(300 60)"><path d="M30 0 4 11v20c0 16 11 28 26 34 15-6 26-18 26-34V11L30 0Z" fill={B} /><path d="m17 33 9 9 18-20" fill="none" stroke="#fff" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round" /></g>
        </g>
      )}
    </svg>
  );
}
