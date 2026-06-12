import { useLayoutEffect, useState, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';

interface Props {
  /** The element the panel is anchored to (its trigger/input wrapper). */
  anchorRef: RefObject<HTMLElement | null>;
  open: boolean;
  children: ReactNode;
  className?: string;
  /** Gap in px between the anchor and the panel. */
  offset?: number;
}

/**
 * Renders a dropdown panel in a `document.body` portal, positioned (fixed)
 * under — or above, when there isn't room — its anchor.
 *
 * Why a portal: dialogs cap their height with `overflow-y-auto` AND use a
 * CSS `transform` to center, which clips BOTH `position:absolute` and
 * `position:fixed` descendants. A body portal escapes that clip so the list
 * is always fully visible no matter how far down the form the field sits.
 *
 * Pointer events inside the panel are stopped from propagating so the Radix
 * Dialog's "pointer-down outside" dismissal doesn't fire — clicking an option
 * never closes the surrounding dialog.
 */
export function PortalDropdown({ anchorRef, open, children, className, offset = 4 }: Props) {
  const [pos, setPos] = useState<
    { left: number; width: number; maxHeight: number; top?: number; bottom?: number } | null
  >(null);

  useLayoutEffect(() => {
    if (!open) return;

    const update = () => {
      const el = anchorRef.current;
      if (!el) return;
      const r = el.getBoundingClientRect();
      const spaceBelow = window.innerHeight - r.bottom;
      const spaceAbove = r.top;
      // Flip up only when there's clearly more room above.
      const openUp = spaceBelow < 260 && spaceAbove > spaceBelow;
      if (openUp) {
        setPos({ left: r.left, width: r.width, bottom: window.innerHeight - r.top + offset, maxHeight: spaceAbove - 8 });
      } else {
        setPos({ left: r.left, width: r.width, top: r.bottom + offset, maxHeight: spaceBelow - 8 });
      }
    };

    update();
    // capture:true so we also catch scrolling inside the dialog's scroll box.
    window.addEventListener('scroll', update, true);
    window.addEventListener('resize', update);
    return () => {
      window.removeEventListener('scroll', update, true);
      window.removeEventListener('resize', update);
    };
  }, [open, anchorRef, offset]);

  if (!open || !pos) return null;

  return createPortal(
    <div
      data-portal-dropdown=""
      onPointerDown={(e) => e.stopPropagation()}
      onMouseDown={(e) => e.stopPropagation()}
      // Stop focusin from reaching document so a Radix Dialog focus-trap
      // doesn't yank focus back when a search box inside the panel is focused.
      onFocus={(e) => e.stopPropagation()}
      style={{
        position: 'fixed',
        left: pos.left,
        width: pos.width,
        top: pos.top,
        bottom: pos.bottom,
        maxHeight: pos.maxHeight,
        zIndex: 60,
      }}
      className={className}
    >
      {children}
    </div>,
    document.body,
  );
}
