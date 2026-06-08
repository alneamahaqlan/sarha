import { useEffect, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { ChevronsLeft, ChevronsRight, ChevronDown } from 'lucide-react';

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

const GROUP_PREFIX = 'admin.sidebar.group.';

/** True when `pathname` is on `to` or any nested route below it. */
function isPathActive(pathname: string, to: string) {
  return pathname === to || pathname.startsWith(to + '/');
}

/**
 * Collapsible nav section.
 *
 * Full mode: the group label is a toggle button that expands/collapses
 * its items with a smooth height transition (CSS grid-rows trick — no
 * JS height measuring). Sections default to OPEN; the user can collapse
 * any of them and the choice persists per-group in localStorage.
 * Navigating into a collapsed group auto-opens it, and a closed group
 * surfaces the badge total of its hidden items so notifications are
 * never buried.
 *
 * Items stay mounted while collapsed (so the height animation has
 * something to animate) but are marked `inert` so they're skipped by
 * keyboard tabbing and assistive tech — matching what's visible.
 *
 * Icon-rail (collapsed) mode: there's no header to click, so we keep
 * the original always-visible behaviour with a divider cue.
 */
function SidebarGroup({
  group, badges, collapsed,
}: {
  group: { group: string; items: NavItem[] };
  badges?: Record<string, number>;
  collapsed: boolean;
}) {
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

  // Navigating into a collapsed group reveals where you are. Runs only
  // when the active flag flips, so a manual collapse while staying on
  // the page is respected.
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

  // Icon-rail mode: divider + all items, no accordion (nowhere to click).
  if (collapsed) {
    return (
      <div className="space-y-1">
        <div className="my-2 border-t border-[var(--color-border)]" role="separator" />
        {group.items.map((item) => (
          <SidebarRow key={item.to} item={item} badges={badges} collapsed={collapsed} />
        ))}
      </div>
    );
  }

  return (
    <div>
      <button
        type="button"
        onClick={toggle}
        aria-expanded={open}
        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-[11px] font-semibold uppercase tracking-wider text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)] hover:text-[var(--color-foreground)] transition-colors"
      >
        <span className="flex-1 text-start truncate">{t(group.group)}</span>
        {/* Aggregate badge surfaces hidden notifications on a closed group. */}
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

      {/* Smooth height animation: the grid track goes 1fr→0fr while the
          inner wrapper clips the overflow. */}
      <div
        className={cn(
          'grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none',
          open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
        )}
      >
        <div className="overflow-hidden">
          <div className="space-y-1 pt-1" inert={!open}>
            {group.items.map((item) => (
              <SidebarRow key={item.to} item={item} badges={badges} collapsed={collapsed} />
            ))}
          </div>
        </div>
      </div>
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
          'relative flex items-center rounded-lg text-sm transition-all duration-150',
          collapsed ? 'justify-center px-2 py-2.5 mx-1' : 'gap-3 px-3 py-2.5',
          isActive
            // Soft, luminous active state: a light primary tint with
            // primary-coloured text + a glowing leading bar — far easier
            // on the eye than a heavy solid fill, and reads as "modern".
            ? cn(
                'bg-[color-mix(in_oklab,var(--color-primary),white_88%)] text-[var(--color-primary)] font-medium',
                'shadow-[0_1px_10px_-3px_color-mix(in_oklab,var(--color-primary),transparent_45%)]',
                !collapsed &&
                  'before:absolute before:inset-y-1.5 before:start-0 before:w-1 before:rounded-full before:bg-[var(--color-primary)] before:shadow-[0_0_8px_0_var(--color-primary)]',
              )
            : 'text-[var(--color-foreground)] hover:bg-[color-mix(in_oklab,var(--color-primary),white_94%)] hover:text-[var(--color-primary)]',
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
