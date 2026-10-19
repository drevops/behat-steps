// Line icons on a 24px grid, stroked in the color of the surrounding text.
const ICONS = {
  arrowRight: <path d="M5 12h14M13 6l6 6-6 6" />,
  arrowUpRight: <path d="M7 17 17 7M9 7h8v8" />,
  book: (
    <>
      <path d="M12 6.5c-2-1.6-5.3-1.6-7.5 0v12c2.2-1.6 5.5-1.6 7.5 0 2-1.6 5.3-1.6 7.5 0v-12c-2.2-1.6-5.5-1.6-7.5 0z" />
      <path d="M12 6.5v12" />
    </>
  ),
  browser: (
    <>
      <rect x="3" y="4" width="18" height="16" rx="2" />
      <path d="M3 9h18M6.5 6.5h.01M9.5 6.5h.01" />
    </>
  ),
  check: <path d="M20 6 9 17l-5-5" />,
  code: <path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 6l-3 12" />,
  copy: (
    <>
      <rect x="9" y="9" width="11" height="11" rx="2" />
      <path d="M5 15V5a2 2 0 0 1 2-2h10" />
    </>
  ),
  download: (
    <>
      <path d="M12 3v11m0 0-4-4m4 4 4-4" />
      <path d="M4 16v2.5A2.5 2.5 0 0 0 6.5 21h11a2.5 2.5 0 0 0 2.5-2.5V16" />
    </>
  ),
  drop: (
    <>
      <path d="M12 3s6.5 6 6.5 10.5a6.5 6.5 0 0 1-13 0C5.5 9 12 3 12 3z" />
      <path d="M9.5 13.5a2.6 2.6 0 0 0 2.5 2.5" />
    </>
  ),
  file: (
    <>
      <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
      <path d="M14 3v5h5M9 13h6M9 17h6" />
    </>
  ),
  flask: (
    <>
      <path d="M9.5 3h5M10.5 3v6.2L5 18.6A1.6 1.6 0 0 0 6.4 21h11.2a1.6 1.6 0 0 0 1.4-2.4L13.5 9.2V3" />
      <path d="M7.4 15h9.2" />
    </>
  ),
  github: <path fill="currentColor" stroke="none" d="M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49l-.01-1.9c-2.78.62-3.37-1.2-3.37-1.2-.46-1.18-1.11-1.49-1.11-1.49-.91-.64.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.89 1.56 2.34 1.11 2.91.85.09-.66.35-1.11.63-1.37-2.22-.26-4.55-1.14-4.55-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.71 0 0 .84-.28 2.75 1.05a9.4 9.4 0 0 1 5 0c1.91-1.33 2.75-1.05 2.75-1.05.55 1.41.2 2.45.1 2.71.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.81-4.57 5.06.36.32.68.94.68 1.9l-.01 2.82c0 .27.18.59.69.49A10.26 10.26 0 0 0 22 12.25C22 6.58 17.52 2 12 2z" />,
  globe: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9s1.3-6.4 3.8-9z" />
    </>
  ),
  info: (
    <>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 11v5M12 8h.01" />
    </>
  ),
  layers: (
    <>
      <path d="M12 3 21 8l-9 5-9-5 9-5z" />
      <path d="m3 13 9 5 9-5" />
      <path d="m3 17.5 9 5 9-5" />
    </>
  ),
  package: (
    <>
      <path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7L12 2.5z" />
      <path d="M3.7 7.2 12 12l8.3-4.8M12 12v9.3" />
    </>
  ),
  refresh: (
    <>
      <path d="M3.5 12a8.5 8.5 0 0 1 14.6-5.9l2.4 2.4" />
      <path d="M20.5 3.5v5h-5" />
      <path d="M20.5 12a8.5 8.5 0 0 1-14.6 5.9l-2.4-2.4" />
      <path d="M3.5 20.5v-5h5" />
    </>
  ),
  shield: (
    <>
      <path d="M12 3 5 6v5.5c0 4.2 3 7 7 9 4-2 7-4.8 7-9V6l-7-3z" />
      <path d="m9 12 2 2 4-4.2" />
    </>
  ),
  sliders: (
    <>
      <path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1" />
      <circle cx="15" cy="6" r="2" />
      <circle cx="9" cy="12" r="2" />
      <circle cx="17" cy="18" r="2" />
    </>
  ),
  sparkles: (
    <>
      <path d="m12 3 1.9 4.7L18.5 9l-4.6 1.3L12 15l-1.9-4.7L5.5 9l4.6-1.3L12 3z" />
      <path d="M19 14.5l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7.7-1.8z" />
    </>
  ),
  steps: <path d="M3.5 20.5h4v-4h4v-4h4v-4h5" />,
  text: <path d="M4 6h16M4 10h16M4 14h10M4 18h7" />,
  trash: (
    <>
      <path d="M4 7h16M9.5 7V4.5h5V7" />
      <path d="M6 7l.9 12.2A2 2 0 0 0 8.9 21h6.2a2 2 0 0 0 2-1.8L18 7" />
      <path d="M10 11v6M14 11v6" />
    </>
  ),
  zap: <path d="M13 2 4.5 13.2H11l-1 8.8 9.5-11.2H13V2z" />,
};

const Icon = ({ name, className }) => {
  if (!Object.prototype.hasOwnProperty.call(ICONS, name)) {
    throw new Error(`Icon "${name}" doesn't exist.`);
  }

  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.85" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      {ICONS[name]}
    </svg>
  );
};

export const ICON_NAMES = Object.keys(ICONS);

export default Icon;
