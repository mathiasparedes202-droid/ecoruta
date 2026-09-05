import { useState, type InputHTMLAttributes, type TextareaHTMLAttributes, type ReactNode } from 'react';

interface InputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'size'> {
  label?: string;
  helper?: string;
  error?: string;
  size?: 'sm' | 'md' | 'lg';
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  variant?: 'default' | 'filled';
}

const sizeMap = {
  sm: 'h-8 text-sm px-3',
  md: 'h-10 text-sm px-3.5',
  lg: 'h-12 text-base px-4',
};

export function Input({
  label,
  helper,
  error,
  size = 'md',
  leftIcon,
  rightIcon,
  variant = 'default',
  className = '',
  disabled,
  ...props
}: InputProps) {
  const baseInput = `
    w-full font-sans rounded-lg border outline-none transition-all duration-150
    ${sizeMap[size]}
    ${leftIcon ? 'pl-9' : ''}
    ${rightIcon ? 'pr-9' : ''}
  `;

  const stateStyle = error
    ? 'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 text-text-base placeholder:text-red-300'
    : variant === 'filled'
    ? 'border-transparent bg-surface focus:border-er-500 focus:ring-2 focus:ring-er-500/20 focus:bg-white text-text-base placeholder:text-text-faint'
    : 'border-border bg-card focus:border-er-500 focus:ring-2 focus:ring-er-500/20 text-text-base placeholder:text-text-faint hover:border-border-strong';

  const disabledStyle = disabled ? 'opacity-50 cursor-not-allowed bg-surface' : '';

  return (
    <div className="flex flex-col gap-1.5 w-full">
      {label && (
        <label
          className="text-sm font-medium text-text-secondary"
          style={{ fontFamily: 'Inter' }}
        >
          {label}
          {props.required && <span className="text-red-500 ml-0.5">*</span>}
        </label>
      )}
      <div className="relative flex items-center">
        {leftIcon && (
          <span className="absolute left-3 text-text-faint pointer-events-none flex items-center">
            {leftIcon}
          </span>
        )}
        <input
          {...props}
          disabled={disabled}
          className={`${baseInput} ${stateStyle} ${disabledStyle} ${className}`}
          style={{ fontFamily: 'Inter' }}
        />
        {rightIcon && (
          <span className="absolute right-3 text-text-muted flex items-center">
            {rightIcon}
          </span>
        )}
      </div>
      {error ? (
        <span className="text-xs text-red-600 flex items-center gap-1" style={{ fontFamily: 'Inter' }}>
          <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor" aria-hidden>
            <path d="M6 1a5 5 0 1 0 0 10A5 5 0 0 0 6 1zm0 2.5a.75.75 0 0 1 .75.75v2a.75.75 0 0 1-1.5 0v-2A.75.75 0 0 1 6 3.5zm0 5a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5z" />
          </svg>
          {error}
        </span>
      ) : helper ? (
        <span className="text-xs text-text-muted" style={{ fontFamily: 'Inter' }}>
          {helper}
        </span>
      ) : null}
    </div>
  );
}

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  helper?: string;
  error?: string;
}

export function Textarea({ label, helper, error, disabled, className = '', ...props }: TextareaProps) {
  return (
    <div className="flex flex-col gap-1.5 w-full">
      {label && (
        <label className="text-sm font-medium text-text-secondary" style={{ fontFamily: 'Inter' }}>
          {label}
        </label>
      )}
      <textarea
        {...props}
        disabled={disabled}
        className={`
          w-full font-sans rounded-lg border outline-none transition-all duration-150 resize-y
          px-3.5 py-2.5 text-sm min-h-[90px]
          ${error
            ? 'border-red-400 bg-red-50/30 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
            : 'border-border bg-card focus:border-er-500 focus:ring-2 focus:ring-er-500/20 hover:border-border-strong'}
          ${disabled ? 'opacity-50 cursor-not-allowed bg-surface' : ''}
          text-text-base placeholder:text-text-faint
          ${className}
        `}
        style={{ fontFamily: 'Inter' }}
      />
      {error ? (
        <span className="text-xs text-red-600" style={{ fontFamily: 'Inter' }}>{error}</span>
      ) : helper ? (
        <span className="text-xs text-text-muted" style={{ fontFamily: 'Inter' }}>{helper}</span>
      ) : null}
    </div>
  );
}

interface SelectProps extends Omit<InputHTMLAttributes<HTMLSelectElement>, 'size'> {
  label?: string;
  helper?: string;
  error?: string;
  size?: 'sm' | 'md' | 'lg';
  options: { value: string; label: string; disabled?: boolean }[];
  placeholder?: string;
}

export function Select({ label, helper, error, size = 'md', options, placeholder, disabled, className = '', value, onChange }: SelectProps) {
  return (
    <div className="flex flex-col gap-1.5 w-full">
      {label && (
        <label className="text-sm font-medium text-text-secondary" style={{ fontFamily: 'Inter' }}>
          {label}
        </label>
      )}
      <div className="relative">
        <select
          disabled={disabled}
          value={value as string}
          onChange={onChange as any}
          className={`
            w-full appearance-none font-sans rounded-lg border outline-none transition-all duration-150 pr-9
            ${sizeMap[size]}
            ${error
              ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
              : 'border-border bg-card focus:border-er-500 focus:ring-2 focus:ring-er-500/20 hover:border-border-strong'}
            ${disabled ? 'opacity-50 cursor-not-allowed bg-surface' : 'bg-card cursor-pointer'}
            text-text-base
            ${className}
          `}
          style={{ fontFamily: 'Inter' }}
        >
          {placeholder && <option value="">{placeholder}</option>}
          {options.map((o) => (
            <option key={o.value} value={o.value} disabled={o.disabled}>
              {o.label}
            </option>
          ))}
        </select>
        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-text-faint pointer-events-none">
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden>
            <path d="M3 5l4 4 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </div>
      {error ? (
        <span className="text-xs text-red-600" style={{ fontFamily: 'Inter' }}>{error}</span>
      ) : helper ? (
        <span className="text-xs text-text-muted" style={{ fontFamily: 'Inter' }}>{helper}</span>
      ) : null}
    </div>
  );
}

interface ToggleProps {
  checked: boolean;
  onChange: (v: boolean) => void;
  label?: string;
  helper?: string;
  disabled?: boolean;
  size?: 'sm' | 'md';
}

export function Toggle({ checked, onChange, label, helper, disabled, size = 'md' }: ToggleProps) {
  return (
    <label className={`flex items-start gap-3 ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}>
      <div
        onClick={() => !disabled && onChange(!checked)}
        className={`
          relative flex-shrink-0 rounded-full transition-all duration-200
          ${size === 'sm' ? 'w-8 h-4.5' : 'w-10 h-6'}
          ${checked ? 'bg-er-600' : 'bg-gray-200'}
        `}
        style={{ minWidth: size === 'sm' ? 32 : 40, height: size === 'sm' ? 18 : 24 }}
      >
        <span
          className={`
            absolute top-0.5 rounded-full bg-white shadow-sm transition-all duration-200
            ${size === 'sm' ? 'w-3.5 h-3.5' : 'w-5 h-5'}
            ${checked
              ? size === 'sm' ? 'left-[14px]' : 'left-[18px]'
              : 'left-0.5'}
          `}
        />
      </div>
      {(label || helper) && (
        <div className="flex flex-col gap-0.5 pt-0.5">
          {label && (
            <span className="text-sm font-medium text-text-base" style={{ fontFamily: 'Inter' }}>
              {label}
            </span>
          )}
          {helper && (
            <span className="text-xs text-text-muted" style={{ fontFamily: 'Inter' }}>
              {helper}
            </span>
          )}
        </div>
      )}
    </label>
  );
}

interface CheckboxProps {
  checked: boolean;
  onChange: (v: boolean) => void;
  label?: string;
  disabled?: boolean;
  indeterminate?: boolean;
}

export function Checkbox({ checked, onChange, label, disabled, indeterminate }: CheckboxProps) {
  return (
    <label className={`flex items-center gap-2.5 ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'} group`}>
      <div
        onClick={() => !disabled && onChange(!checked)}
        className={`
          w-4 h-4 rounded flex items-center justify-center border transition-all duration-150 flex-shrink-0
          ${checked || indeterminate
            ? 'bg-er-600 border-er-600'
            : 'bg-white border-border group-hover:border-er-500'}
        `}
      >
        {indeterminate ? (
          <svg width="8" height="2" viewBox="0 0 8 2" fill="white" aria-hidden>
            <rect width="8" height="2" rx="1" />
          </svg>
        ) : checked ? (
          <svg width="9" height="7" viewBox="0 0 9 7" fill="none" aria-hidden>
            <path d="M1 3.5l2.5 2.5 5-5" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        ) : null}
      </div>
      {label && (
        <span className="text-sm text-text-base" style={{ fontFamily: 'Inter' }}>
          {label}
        </span>
      )}
    </label>
  );
}

interface RadioProps {
  checked: boolean;
  onChange: () => void;
  label?: string;
  disabled?: boolean;
}

export function Radio({ checked, onChange, label, disabled }: RadioProps) {
  return (
    <label className={`flex items-center gap-2.5 ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'} group`}>
      <div
        onClick={() => !disabled && onChange()}
        className={`
          w-4 h-4 rounded-full flex items-center justify-center border-2 transition-all duration-150 flex-shrink-0
          ${checked ? 'border-er-600' : 'border-border group-hover:border-er-500'}
        `}
      >
        {checked && <span className="w-2 h-2 rounded-full bg-er-600" />}
      </div>
      {label && (
        <span className="text-sm text-text-base" style={{ fontFamily: 'Inter' }}>
          {label}
        </span>
      )}
    </label>
  );
}

export function SearchInput({
  placeholder = 'Buscar...',
  value,
  onChange,
  className = '',
}: {
  placeholder?: string;
  value?: string;
  onChange?: (v: string) => void;
  className?: string;
}) {
  return (
    <Input
      type="search"
      placeholder={placeholder}
      value={value}
      onChange={(e) => onChange?.(e.target.value)}
      className={className}
      leftIcon={
        <svg width="15" height="15" viewBox="0 0 15 15" fill="none" aria-hidden>
          <circle cx="6.5" cy="6.5" r="4.5" stroke="currentColor" strokeWidth="1.5" />
          <path d="M10 10l3 3" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
        </svg>
      }
    />
  );
}

export function PasswordInput({ label, error, helper, ...props }: Omit<InputProps, 'type'>) {
  const [show, setShow] = useState(false);

  return (
    <Input
      {...props}
      label={label}
      error={error}
      helper={helper}
      type={show ? 'text' : 'password'}
      rightIcon={
        <button
          type="button"
          onClick={() => setShow(!show)}
          className="text-text-muted hover:text-text-secondary transition-colors"
          aria-label={show ? 'Ocultar contraseña' : 'Mostrar contraseña'}
        >
          {show ? (
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M2 8s2.5-4.5 6-4.5S14 8 14 8s-2.5 4.5-6 4.5S2 8 2 8z" stroke="currentColor" strokeWidth="1.3" />
              <circle cx="8" cy="8" r="2" stroke="currentColor" strokeWidth="1.3" />
              <path d="M2 2l12 12" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
            </svg>
          ) : (
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path d="M2 8s2.5-4.5 6-4.5S14 8 14 8s-2.5 4.5-6 4.5S2 8 2 8z" stroke="currentColor" strokeWidth="1.3" />
              <circle cx="8" cy="8" r="2" stroke="currentColor" strokeWidth="1.3" />
            </svg>
          )}
        </button>
      }
    />
  );
}