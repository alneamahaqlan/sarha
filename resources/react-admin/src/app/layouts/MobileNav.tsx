import { useEffect, useState } from 'react';
import { useLocation, NavLink } from 'react-router-dom';
import { Menu } from 'lucide-react';
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

function NavRow({ item, badges }: { item: NavItem; badges?: Record<string, number> }) {
  const { t } = useTranslation();
  const Icon = item.icon;
  const count = item.badge ? badges?.[item.badge] ?? 0 : 0;
  return (
    <NavLink
      to={item.to}
      className={({ isActive }) =>
        cn(
          'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
          isActive
            ? 'bg-[var(--color-primary)] text-white'
            : 'text-[var(--color-foreground)] hover:bg-[var(--color-muted)]',
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
              <div key={`g-${idx}`} className="space-y-1">
                <div className="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-muted-foreground)]">
                  {t(entry.group)}
                </div>
                {entry.items.map((it) => <NavRow key={it.to} item={it} badges={badges} />)}
              </div>
            ) : (
              <NavRow key={entry.to} item={entry} badges={badges} />
            ),
          )}
        </nav>
      </SheetContent>
    </Sheet>
  );
}
