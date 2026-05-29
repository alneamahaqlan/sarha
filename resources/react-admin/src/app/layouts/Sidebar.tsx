import { useEffect, useState } from 'react';
import { NavLink } from 'react-router-dom';
import { ChevronsLeft, ChevronsRight } from 'lucide-react';

import { useTranslation } from '@/app/providers/LocaleProvider';
import { useBreakpoint } from '@/lib/use-breakpoint';
import { cn } from '@/lib/utils';
import { Logo } from '@/components/ui/Logo';

import type { NavEntry, NavItem } from './MobileNav';

const PERSIST_KEY = 'admin.sidebar.collapsed';
const TOUCHED_KEY = 'admin.sidebar.touched';

interface SidebarProps {
  items: NavEntry[];
  badges?: Record<string, number>;
  /** Optional footer block (user info, role badge). Rendered only in
   *  full mode — collapsed icon rail has no room for it. */
  footer?: React.ReactNode;
}

function isGroup(entry: NavEntry): entry is { group: string; items: NavItem[] } {
  return 'group' in entry;
}

/**
 * Dual-mode sidebar shared by AdminLayout + ClinicLayout.
 *
 *   < md  : hidden — MobileNav (Sheet) takes over. We render `null`.
 *   md..lg: defaults to icon-rail (64px) so tablet content has room,
 *           but the user can expand to full and that preference sticks.
 *   ≥ lg  : defaults to full (256px). User can collapse to icon-rail.
 *
 * Preference model: once the user clicks the toggle ANYWHERE, their
 * choice is honoured across breakpoints. Before any interaction, each
 * breakpoint uses its own sensible default (collapsed on tablet,
 * expanded on desktop). Two localStorage keys keep this honest —
 * `touched` records whether the user has ever spoken; `collapsed`
 * stores what they said.
 *
 * Tooltips: icon-mode nav rows expose the label via the native `title`
 * attribute — zero-dependency, screen-reader friendly, and pointer-only
 * users get the hover tip browsers render for free.
 */
export function Sidebar({ items, badges, footer }: SidebarProps) {
  const { t } = useTranslation();
  const { isMobile, isTablet } = useBreakpoint();

  const [touched, setTouched] = useState<boolean>(() => {
    try { return localStorage.getItem(TOUCHED_KEY) === '1'; } catch { return false; }
  });
  const [userCollapsed, setUserCollapsed] = useState<boolean>(() => {
    try { return localStorage.getItem(PERSIST_KEY) === '1'; } catch { return false; }
  });

  useEffect(() => {
    try {
      localStorage.setItem(PERSIST_KEY, userCollapsed ? '1' : '0');
      if (touched) localStorage.setItem(TOUCHED_KEY, '1');
    } catch { /* swallow */ }
  }, [userCollapsed, touched]);

  // Mobile path: render nothing. AdminLayout shows MobileNav instead.
  if (isMobile) return null;

  // Effective state: respect the user once they've spoken, else fall
  // back to per-breakpoint default (tablet → collapsed, desktop → full).
  const collapsed = touched ? userCollapsed : isTablet;

  const onToggle = () => {
    setTouched(true);
    setUserCollapsed(!collapsed);
  };

  return (
    <aside
      className={cn(
        'hidden md:flex flex-col border-e border-[var(--color-border)] bg-white transition-[width] duration-200',
        collapsed ? 'w-sidebar-icon' : 'w-sidebar',
      )}
      aria-label={t('common.menu')}
    >
      {/* Brand + collapse toggle. Toggle is hidden on tablet where the
          mode is forced — leaving the button visible there would imply
          a control the user actually has, which they don't. */}
      <div className={cn(
        'flex items-center border-b border-[var(--color-border)] py-4',
        collapsed ? 'justify-center px-2' : 'justify-between px-5',
      )}>
        <Logo size={collapsed ? 28 : 36} />
        {/* Toggle works on both tablet and desktop — the user keeps
            control wherever the sidebar is visible. Mobile is excluded
            by the `isMobile` early return above. */}
        <button
          type="button"
          onClick={onToggle}
          className="rounded-md p-1.5 text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)] hover:text-[var(--color-foreground)] transition-colors"
          aria-label={t(collapsed ? 'common.expand_sidebar' : 'common.collapse_sidebar')}
          title={t(collapsed ? 'common.expand_sidebar' : 'common.collapse_sidebar')}
        >
          {collapsed ? <ChevronsRight className="h-4 w-4 rtl:rotate-180" /> : <ChevronsLeft className="h-4 w-4 rtl:rotate-180" />}
        </button>
      </div>

      <nav className={cn('flex-1 overflow-y-auto', collapsed ? 'p-2 space-y-1' : 'p-2 space-y-2')}>
        {items.map((entry, idx) =>
          isGroup(entry) ? (
            <SidebarGroup
              key={`g-${idx}`}
              group={entry}
              badges={badges}
              collapsed={collapsed}
            />
          ) : (
            <SidebarRow
              key={entry.to}
              item={entry}
              badges={badges}
              collapsed={collapsed}
            />
          ),
        )}
      </nav>

      {footer && !collapsed && (
        <div className="border-t border-[var(--color-border)] p-3 text-xs text-[var(--color-muted-foreground)]">
          {footer}
        </div>
      )}
    </aside>
  );
}

function SidebarGroup({
  group, badges, collapsed,
}: {
  group: { group: string; items: NavItem[] };
  badges?: Record<string, number>;
  collapsed: boolean;
}) {
  const { t } = useTranslation();
  return (
    <div className="space-y-1">
      {/* Group label hidden in icon mode — a divider line keeps the
          visual grouping cue without claiming horizontal room. */}
      {collapsed ? (
        <div className="my-2 border-t border-[var(--color-border)]" role="separator" />
      ) : (
        <div className="px-3 pt-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-muted-foreground)]">
          {t(group.group)}
        </div>
      )}
      {group.items.map((item) => (
        <SidebarRow key={item.to} item={item} badges={badges} collapsed={collapsed} />
      ))}
    </div>
  );
}

function SidebarRow({
  item, badges, collapsed,
}: {
  item: NavItem;
  badges?: Record<string, number>;
  collapsed: boolean;
}) {
  const { t } = useTranslation();
  const Icon = item.icon;
  const count = item.badge ? badges?.[item.badge] ?? 0 : 0;
  const label = t(item.label);

  return (
    <NavLink
      to={item.to}
      // Native title gives icon-mode users a label tip on hover and
      // is read by assistive tech without any extra ARIA wiring.
      title={collapsed ? label : undefined}
      className={({ isActive }) =>
        cn(
          'relative flex items-center rounded-md text-sm transition-colors',
          collapsed ? 'justify-center px-2 py-2.5 mx-1' : 'gap-3 px-3 py-2',
          isActive
            ? 'bg-[var(--color-primary)] text-white'
            : 'text-[var(--color-foreground)] hover:bg-[var(--color-muted)]',
        )
      }
    >
      <Icon className="h-4 w-4 shrink-0" />
      {!collapsed && <span className="flex-1 truncate">{label}</span>}
      {count > 0 && (
        <span
          className={cn(
            'inline-flex items-center justify-center rounded-full bg-[var(--color-destructive)] font-medium text-white',
            collapsed
              ? 'absolute -top-0.5 end-0.5 h-4 min-w-[1rem] px-1 text-[10px]'
              : 'h-5 min-w-[1.25rem] px-1.5 text-xs',
          )}
        >
          {count > 99 ? '99+' : count}
        </span>
      )}
    </NavLink>
  );
}
