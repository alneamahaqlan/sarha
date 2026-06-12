import { useEffect, useState } from 'react';
import { useLocation, NavLink } from 'react-router-dom';
import { Menu, ChevronDown } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';

export interface NavItem {
  to: string;
  label: string;
  icon: LucideIcon;
  badge?: string;
}

export interface NavGroup {
  group: string;
  items: NavItem[];
}

export type NavEntry = NavItem | NavGroup;

function isGroup(entry: NavEntry): entry is NavGroup {
  return 'group' in entry;
}

interface MobileNavProps {
  items: NavEntry[];
  title: string;
  badges?: Record<string, number>;
}

const GROUP_PREFIX = 'admin.mobilenav.group.';

/** True when `pathname` is on `to` or any nested route below it. */
function isPathActive(pathname: string, to: string) {
  return pathname === to || pathname.startsWith(to + '/');
}

/**
 * Collapsible section in the mobile sheet — mirrors the desktop
 * Sidebar accordion so both surfaces behave the same. Defaults open;
 * the user can collapse a section and the choice persists. Smooth
 * height transition + `inert` on collapsed items (kept mounted for the
 * animation, hidden from tab order / assistive tech).
 */
function MobileGroup({ group, badges }: { group: NavGroup; badges?: Record<string, number> }) {
  const { t } = useTranslation();
  const location = useLocation();
  const storeKey = `${GROUP_PREFIX}${group.group}`;

  const hasActive = group.items.some((i) => isPathActive(location.pathname, i.to));
  const groupBadgeCount = group.items.reduce(
    (sum, i) => sum + (i.badge ? badges?.[i.badge] ?? 0 : 0),
    0,
  );

  const [open, setOpen] = useState<boolean>(() => {
    try {
      const raw = localStorage.getItem(storeKey);
      if (raw === '1') return true;
      if (raw === '0') return false;
    } catch { /* swallow */ }
    return true; // default: expanded
  });

  useEffect(() => {
    if (hasActive) setOpen(true);
  }, [hasActive]);

  const toggle = () => {
    setOpen((prev) => {
      const next = !prev;
      try { localStorage.setItem(storeKey, next ? '1' : '0'); } catch { /* swallow */ }
      return next;
    });
  };

  return (
    <div>
      <button
        type="button"
        onClick={toggle}
        aria-expanded={open}
        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)] hover:text-[var(--color-foreground)] transition-colors"
      >
        <span className="flex-1 text-start truncate">{t(group.group)}</span>
        {!open && groupBadgeCount > 0 && (
          <span className="inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[var(--color-destructive)] px-1 text-[10px] font-medium text-white">
            {groupBadgeCount > 99 ? '99+' : groupBadgeCount}
          </span>
        )}
        <ChevronDown
          className={cn(
            'h-3.5 w-3.5 shrink-0 text-[var(--color-muted-foreground)] transition-transform duration-200 ease-out',
            open ? '' : '-rotate-90 rtl:rotate-90',
          )}
        />
      </button>

      <div
        className={cn(
          'grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none',
          open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
        )}
      >
        <div className="overflow-hidden">
          <div className="space-y-1 pt-1" inert={!open}>
            {group.items.map((it) => <NavRow key={it.to} item={it} badges={badges} />)}
          </div>
        </div>
      </div>
    </div>
  );
}

function NavRow({ item, badges }: { item: NavItem; badges?: Record<string, number> }) {
  const { t } = useTranslation();
  const Icon = item.icon;
  const count = item.badge ? badges?.[item.badge] ?? 0 : 0;
  return (
    <NavLink
      to={item.to}
      className={({ isActive }) =>
        cn(
          'relative flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-all duration-150',
          isActive
            ? cn(
                'bg-[color-mix(in_oklab,var(--color-primary),white_88%)] text-[var(--color-primary)] font-medium',
                'shadow-[0_1px_10px_-3px_color-mix(in_oklab,var(--color-primary),transparent_45%)]',
                'before:absolute before:inset-y-1.5 before:start-0 before:w-1 before:rounded-full before:bg-[var(--color-primary)] before:shadow-[0_0_8px_0_var(--color-primary)]',
              )
            : 'text-[var(--color-foreground)] hover:bg-[color-mix(in_oklab,var(--color-primary),white_94%)] hover:text-[var(--color-primary)]',
        )
      }
    >
      <Icon className="h-4 w-4" />
      <span className="flex-1">{t(item.label)}</span>
      {count > 0 && (
        <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[var(--color-destructive)] px-1.5 text-xs font-medium text-white">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </NavLink>
  );
}

export function MobileNav({ items, title, badges }: MobileNavProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <button
          type="button"
          aria-label={t('common.menu')}
          className="inline-flex h-9 w-9 items-center justify-center rounded-md hover:bg-[var(--color-muted)] md:hidden"
        >
          <Menu className="h-5 w-5" />
        </button>
      </SheetTrigger>
      <SheetContent side="start" className="p-0">
        <SheetHeader>
          <SheetTitle>{title}</SheetTitle>
        </SheetHeader>
        <nav className="flex-1 space-y-2 overflow-y-auto p-2">
          {items.map((entry, idx) =>
            isGroup(entry) ? (
              <MobileGroup key={`g-${idx}`} group={entry} badges={badges} />
            ) : (
              <NavRow key={entry.to} item={entry} badges={badges} />
            ),
          )}
        </nav>
      </SheetContent>
    </Sheet>
  );
}
