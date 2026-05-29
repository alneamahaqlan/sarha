import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { Slot } from '@radix-ui/react-slot';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-[var(--color-primary)] text-white hover:bg-[color-mix(in_oklab,var(--color-primary),black_10%)]',
        destructive: 'bg-[var(--color-destructive)] text-white hover:opacity-90',
        outline: 'border border-[var(--color-border)] bg-white hover:bg-[var(--color-muted)]',
        secondary: 'bg-[var(--color-muted)] text-[var(--color-foreground)] hover:bg-[color-mix(in_oklab,var(--color-muted),black_5%)]',
        ghost: 'hover:bg-[var(--color-muted)]',
        link: 'text-[var(--color-primary)] underline-offset-4 hover:underline',
      },
      // Touch-target policy: every size enforces the 44px WCAG floor on
      // mobile (< md) via min-h-touch / min-w-touch, then snaps down to
      // the desktop-friendly compact height on md+. Same gestalt across
      // sizes — `sm` still LOOKS smaller on desktop, but mobile users
      // never get a sub-44px tap area.
      size: {
        default: 'min-h-touch md:min-h-9 md:h-9 px-4 py-2',
        sm: 'min-h-touch md:min-h-8 md:h-8 px-3',
        lg: 'min-h-touch md:min-h-10 md:h-10 px-6',
        icon: 'min-h-touch min-w-touch md:min-h-9 md:min-w-9 md:h-9 md:w-9',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
);

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return <Comp ref={ref} className={cn(buttonVariants({ variant, size, className }))} {...props} />;
  },
);
Button.displayName = 'Button';

export { buttonVariants };
