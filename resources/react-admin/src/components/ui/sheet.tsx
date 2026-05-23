import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { forwardRef, type HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export const Sheet = DialogPrimitive.Root;
export const SheetTrigger = DialogPrimitive.Trigger;
export const SheetClose = DialogPrimitive.Close;
export const SheetPortal = DialogPrimitive.Portal;

export const SheetOverlay = forwardRef<
  React.ElementRef<typeof DialogPrimitive.Overlay>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Overlay
    ref={ref}
    className={cn('fixed inset-0 z-50 bg-black/50', className)}
    {...props}
  />
));
SheetOverlay.displayName = DialogPrimitive.Overlay.displayName;

type SheetSide = 'start' | 'end';

interface SheetContentProps extends React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> {
  side?: SheetSide;
}

export const SheetContent = forwardRef<
  React.ElementRef<typeof DialogPrimitive.Content>,
  SheetContentProps
>(({ className, children, side = 'start', ...props }, ref) => (
  <SheetPortal>
    <SheetOverlay />
    <DialogPrimitive.Content
      ref={ref}
      className={cn(
        'fixed inset-y-0 z-50 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl',
        side === 'start' ? 'start-0 border-e border-[var(--color-border)]' : 'end-0 border-s border-[var(--color-border)]',
        className,
      )}
      {...props}
    >
      {children}
      <DialogPrimitive.Close className="absolute end-3 top-3 rounded-sm p-1 opacity-70 hover:bg-[var(--color-muted)] hover:opacity-100">
        <X className="h-4 w-4" />
        <span className="sr-only">Close</span>
      </DialogPrimitive.Close>
    </DialogPrimitive.Content>
  </SheetPortal>
));
SheetContent.displayName = DialogPrimitive.Content.displayName;

export const SheetHeader = ({ className, ...props }: HTMLAttributes<HTMLDivElement>) => (
  <div className={cn('flex flex-col gap-1 border-b border-[var(--color-border)] px-5 py-4', className)} {...props} />
);

export const SheetTitle = forwardRef<
  React.ElementRef<typeof DialogPrimitive.Title>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Title>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Title ref={ref} className={cn('text-lg font-semibold leading-none', className)} {...props} />
));
SheetTitle.displayName = DialogPrimitive.Title.displayName;

export const SheetDescription = forwardRef<
  React.ElementRef<typeof DialogPrimitive.Description>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Description>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Description ref={ref} className={cn('text-sm text-[var(--color-muted-foreground)]', className)} {...props} />
));
SheetDescription.displayName = DialogPrimitive.Description.displayName;
