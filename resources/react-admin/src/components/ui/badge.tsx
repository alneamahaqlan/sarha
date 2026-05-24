import { type HTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
  {
    variants: {
      variant: {
        default: 'border-transparent bg-[var(--color-primary)] text-white',
        success: 'border-transparent bg-sage-mist text-sage-deep',
        warning: 'border-transparent bg-amber-100 text-amber-700',
        danger: 'border-transparent bg-[color-mix(in_srgb,var(--color-danger)_15%,white)] text-[#9C4D3A]',
        info: 'border-transparent bg-blue-100 text-blue-700',
        gold: 'border-gold-soft bg-gold-whisper text-gold-deep',
        premium: 'border-transparent bg-gradient-to-l from-gold-primary to-gold-deep text-white',
        verified: 'border-transparent bg-sage-mist text-sage-deep',
        ai: 'border-plum-soft bg-plum-whisper text-plum-deep',
        muted: 'border-transparent bg-[var(--color-muted)] text-[var(--color-muted-foreground)]',
        outline: 'border-[var(--color-border)] text-[var(--color-foreground)]',
      },
    },
    defaultVariants: { variant: 'default' },
  },
);

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
