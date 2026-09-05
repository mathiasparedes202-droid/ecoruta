interface LogoProps {
  variant?: 'full' | 'mark' | 'collapsed';
  theme?: 'dark' | 'light';
  size?: 'sm' | 'md' | 'lg' | 'xl';
}

const sizeMap = {
  sm: { mark: 28, name: 'text-sm', tag: 'text-[10px]' },
  md: { mark: 36, name: 'text-base', tag: 'text-[11px]' },
  lg: { mark: 44, name: 'text-xl', tag: 'text-xs' },
  xl: { mark: 56, name: 'text-2xl', tag: 'text-sm' },
};

function EcoMark({ size = 36 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 40 40" fill="none" aria-hidden>
      {/* Dark green square base */}
      <rect width="40" height="40" rx="11" fill="#166534" />
      {/* Lighter green gradient layer */}
      <rect width="40" height="40" rx="11" fill="url(#logo-grad)" opacity="0.5" />
      {/* Leaf body */}
      <path
        d="M9 30C9 30 11 14 22 8C27.5 5.5 32 9 31 15C29.5 23 20 29 9 30Z"
        fill="white"
        opacity="0.95"
      />
      {/* Route / vein dashed */}
      <path
        d="M12 27C15 22 18.5 17 22.5 11.5"
        stroke="#166534"
        strokeWidth="1.8"
        strokeLinecap="round"
        strokeDasharray="2 2.5"
      />
      {/* Origin dot */}
      <circle cx="12" cy="27" r="2.5" fill="#dcfce7" />
      {/* Destination pin */}
      <circle cx="29" cy="11" r="4" fill="#4ade80" opacity="0.9" />
      <circle cx="29" cy="11" r="2.2" fill="#166534" />
      <defs>
        <linearGradient id="logo-grad" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
          <stop stopColor="#22c55e" />
          <stop offset="1" stopColor="#15803d" />
        </linearGradient>
      </defs>
    </svg>
  );
}

export function Logo({ variant = 'full', theme = 'dark', size = 'md' }: LogoProps) {
  const { mark, name, tag } = sizeMap[size];
  const nameColor = theme === 'light' ? '#ffffff' : '#0d1710';
  const tagColor = theme === 'light' ? 'rgba(255,255,255,0.55)' : '#647b70';

  if (variant === 'mark') {
    return <EcoMark size={mark} />;
  }

  return (
    <div className="flex items-center gap-3 select-none">
      <EcoMark size={mark} />
      {variant !== 'collapsed' && (
        <div className="flex flex-col gap-0 leading-none">
          <span
            className={`${name} font-bold tracking-tight`}
            style={{ fontFamily: 'Plus Jakarta Sans', color: nameColor, letterSpacing: '-0.02em' }}
          >
            EcoRuta
          </span>
          {size !== 'sm' && (
            <span
              className={`${tag} font-medium`}
              style={{ fontFamily: 'Inter', color: tagColor, marginTop: '1px' }}
            >
              Gestión Logística
            </span>
          )}
        </div>
      )}
    </div>
  );
}