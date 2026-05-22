import { useEffect, useState } from 'react';
import { useLocation, NavLink } from 'react-router-dom';
import { Menu } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { cn } from '@/lib/utils';

interface NavItem {
  to: string;
  label: string;
  icon: LucideIcon;
  badge?: string;
}

interface MobileNavProps {
  items: NavItem[];
  title: string;
  badges?: Record<string, number>;
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
        <nav className="flex-1 space-y-1 overflow-y-auto p-2">
          {items.map(({ to, label, icon: Icon, badge }) => {
            const count = badge ? badges?.[badge] ?? 0 : 0;
            return (
              <NavLink
                key={to}
                to={to}
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
                <span className="flex-1">{t(label)}</span>
                {count > 0 && (
                  <span className="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[var(--color-destructive)] px-1.5 text-xs font-medium text-white">
                    {count > 99 ? '99+' : count}
                  </span>
                )}
              </NavLink>
            );
          })}
        </nav>
      </SheetContent>
    </Sheet>
  );
}
