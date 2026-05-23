import { forwardRef, type InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export interface SwitchProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  onCheckedChange?: (checked: boolean) => void;
}

export const Switch = forwardRef<HTMLInputElement, SwitchProps>(
  ({ className, checked, onCheckedChange, onChange, ...props }, ref) => (
    <label className={cn('relative inline-flex h-6 w-11 cursor-pointer items-center', className)}>
      <input
        type="checkbox"
        ref={ref}
        className="peer sr-only"
        checked={checked}
        onChange={(e) => {
          onChange?.(e);
          onCheckedChange?.(e.target.checked);
        }}
        {...props}
      />
      <span className="absolute inset-0 rounded-full bg-gray-300 transition peer-checked:bg-[var(--color-primary)]" />
      <span className="absolute start-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:start-[1.375rem]" />
    </label>
  ),
);
Switch.displayName = 'Switch';
