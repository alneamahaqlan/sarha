import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Check, Pencil, Plus, Search, X } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useUsers } from '../hooks';
import { UserForm } from '../components/UserForm';
import type { User } from '../types';
import { UserAiInterestsSection } from '@/features/ai-center/phase2/components/UserAiInterestsSection';

const STATUS_FILTERS = [
  { value: undefined, key: 'common.all' as const },
  { value: true, key: 'users.filter_active' as const },
  { value: false, key: 'users.filter_inactive' as const },
];

export function UsersIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<boolean | undefined>(undefined);
  const [editing, setEditing] = useState<User | null>(null);
  const [creating, setCreating] = useState(false);

  const queryParams = useMemo(
    () => ({
      page,
      per_page: 15,
      search: debouncedSearch.trim() || undefined,
      sort: '-created_at',
      filter: { is_active: statusFilter },
    }),
    [page, debouncedSearch, statusFilter],
  );
  const { data, isLoading, isFetching } = useUsers(queryParams);

  return (
    <div className="space-y-3 sm:space-y-4">
      {/* Title row — title takes the line on mobile so the long subtitle
          doesn't get squeezed against the Create button. */}
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          <h1 className="text-xl sm:text-2xl font-semibold">{t('users.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('users.subtitle')}</p>
        </div>
        {can('users.create') && (
          <Button onClick={() => setCreating(true)} className="self-start sm:self-auto">
            <Plus className="h-4 w-4" />
            {t('users.create')}
          </Button>
        )}
      </div>

      {/* Search + status filters stack on xs, line up on md+. The search
          takes the full row width on mobile so users see what they typed
          instead of fighting a 240px squeeze. */}
      <div className="flex flex-col gap-2 md:flex-row md:items-center">
        <div className="relative w-full md:max-w-sm md:flex-1">
          <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </div>
        <div className="flex gap-1 overflow-x-auto">
          {STATUS_FILTERS.map((f) => (
            <Button
              key={String(f.value)}
              variant={statusFilter === f.value ? 'default' : 'outline'}
              size="sm"
              onClick={() => {
                setStatusFilter(f.value);
                setPage(1);
              }}
            >
              {t(f.key)}
            </Button>
          ))}
        </div>
      </div>

      {/* Column priority on small screens — drops the least-critical
          columns first so users always see identity (name + phone) and
          the actions cell. Progression:
            xs   : name + phone + actions
            sm+  : + is_active
            md+  : + email
            lg+  : + bookings_count + created_at
      */}
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('users.name')}</TableHead>
            <TableHead>{t('users.phone')}</TableHead>
            <TableHead className="hidden md:table-cell">{t('users.email')}</TableHead>
            <TableHead className="hidden lg:table-cell">{t('users.bookings_count')}</TableHead>
            <TableHead className="hidden sm:table-cell">{t('users.is_active')}</TableHead>
            <TableHead className="hidden lg:table-cell">{t('users.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={7} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((user) => (
              <TableRow key={user.id}>
                <TableCell className="font-medium">
                  <Link to={`/admin/users/${user.id}`} className="text-[var(--color-primary)] hover:underline">
                    {user.name}
                  </Link>
                </TableCell>
                <TableCell dir="ltr" className="text-start">{user.phone}</TableCell>
                <TableCell className="hidden md:table-cell text-[var(--color-muted-foreground)]" dir="ltr">{user.email ?? '—'}</TableCell>
                <TableCell className="hidden lg:table-cell">
                  <Badge variant="muted">{user.bookings_count ?? 0}</Badge>
                </TableCell>
                <TableCell className="hidden sm:table-cell">
                  {user.is_active ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                  )}
                </TableCell>
                <TableCell className="hidden lg:table-cell text-xs text-[var(--color-muted-foreground)]">
                  {user.created_at ? new Date(user.created_at).toLocaleDateString(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US') : '—'}
                </TableCell>
                <TableCell className="text-end">
                  {can('users.update') && (
                    <Button variant="ghost" size="icon" onClick={() => setEditing(user)} aria-label={t('common.edit')}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-[var(--color-muted-foreground)]">
            {data.meta.from}–{data.meta.to} / {data.meta.total}
          </span>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page === 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              {t('common.back')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= data.meta.last_page || isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              ›
            </Button>
          </div>
        </div>
      )}

      <Dialog
        open={creating || editing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false);
            setEditing(null);
          }
        }}
      >
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing ? t('users.edit') : t('users.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('users.subtitle')}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <UserForm
              user={editing}
              onSuccess={() => {
                setCreating(false);
                setEditing(null);
              }}
              onCancel={() => {
                setCreating(false);
                setEditing(null);
              }}
            />
            {/* Embedded AI Interests panel — only shown when editing an
                existing user, and only opens its API call on expand. */}
            {editing && <UserAiInterestsSection userId={editing.id} />}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}
