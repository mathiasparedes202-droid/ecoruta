import { type ReactNode } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'danger' | 'ghost' | 'link';
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg';

interface ButtonProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  disabled?: boolean;
  loading?: boolean;
  icon?: ReactNode;
  iconRight?: ReactNode;
  children?: ReactNode;
  onClick?: () => void;
  type?: 'button' | 'submit' | 'reset';
  fullWidth?: boolean;
  className?: string;
}

const variantStyles: Record<ButtonVariant, string> = {
  primary:
    'bg-er-600 text-white hover:bg-er-700 active:bg-er-800 shadow-sm hover:shadow-md focus-visible:ring-2 focus-visible:ring-er-500/40',
  secondary:
    'bg-er-50 text-er-700 hover:bg-er-100 active:bg-er-200 border border-er-200 focus-visible:ring-2 focus-visible:ring-er-500/30',
  outline:
    'bg-transparent text-er-700 border border-er-600 hover:bg-er-50 active:bg-er-100 focus-visible:ring-2 focus-visible:ring-er-500/30',
  danger:
    'bg-red-600 text-white hover:bg-red-700 active:bg-red-800 shadow-sm focus-visible:ring-2 focus-visible:ring-red-500/40',
  ghost:
    'bg-transparent text-text-secondary hover:bg-er-50 hover:text-er-700 active:bg-er-100 focus-visible:ring-2 focus-visible:ring-er-500/30',
  link:
    'bg-transparent text-er-600 hover:text-er-700 underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-er-500/30',
};

const sizeStyles: Record<ButtonSize, string> = {
  xs: 'h-7 px-2.5 text-xs gap-1.5 rounded-md',
  sm: 'h-8 px-3.5 text-sm gap-2 rounded-lg',
  md: 'h-10 px-4.5 text-sm gap-2 rounded-lg',
  lg: 'h-12 px-6 text-base gap-2.5 rounded-xl',
};

const iconSizeStyles: Record<ButtonSize, string> = {
  xs: 'h-7 w-7 rounded-md',
  sm: 'h-8 w-8 rounded-lg',
  md: 'h-10 w-10 rounded-lg',
  lg: 'h-12 w-12 rounded-xl',
};

function Spinner() {
  return (
    <svg
      className="animate-spin"
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      aria-hidden
    >
      <circle cx="8" cy="8" r="6" stroke="currentColor" strokeOpacity="0.3" strokeWidth="2" />
      <path
        d="M14 8a6 6 0 0 0-6-6"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function Button({
  variant = 'primary',
  size = 'md',
  disabled,
  loading,
  icon,
  iconRight,
  children,
  onClick,
  type = 'button',
  fullWidth,
  className = '',
}: ButtonProps) {
  const isIconOnly = !children;
  const base =
    'inline-flex items-center justify-center font-medium font-sans transition-all duration-150 outline-none focus-visible:outline-none select-none cursor-pointer';
  const disabledStyle = 'disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none';
  const sizeClass = isIconOnly ? iconSizeStyles[size] : sizeStyles[size];

  return (
    <button
      type={type}
      disabled={disabled || loading}
      onClick={onClick}
      className={`
        ${base}
        ${variantStyles[variant]}
        ${sizeClass}
        ${disabledStyle}
        ${fullWidth ? 'w-full' : ''}
        ${className}
      `.trim()}
      style={{ fontFamily: 'Inter' }}
    >
      {loading ? <Spinner /> : icon}
      {children && <span>{children}</span>}
      {!loading && iconRight}
    </button>
  );
}

export function IconButton({
  variant = 'ghost',
  size = 'md',
  disabled,
  loading,
  icon,
  onClick,
  className = '',
  label,
}: {
  variant?: ButtonVariant;
  size?: ButtonSize;
  disabled?: boolean;
  loading?: boolean;
  icon: ReactNode;
  onClick?: () => void;
  className?: string;
  label?: string;
}) {
  return (
    <Button
      variant={variant}
      size={size}
      disabled={disabled}
      loading={loading}
      icon={loading ? undefined : icon}
      onClick={onClick}
      className={className}
      aria-label={label}
    />
  );
}