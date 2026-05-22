import { useMemo, useState } from 'react';
import { Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';

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
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useAdmins, useDeleteAdmin } from '../hooks';
import { AdminForm } from '../components/AdminForm';
import type { AdminRole, AdminUser } from '../types';

const ROLE_VARIANT: Record<AdminRole, 'danger' | 'default' | 'warning'> = {
  super_admin: 'danger',
  admin: 'default',
  sales: 'warning',
};

export function AdminsIndex() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const { can, user } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<AdminUser | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<AdminUser | null>(null);

  const queryParams = useMemo(
    () => ({ page, per_page: 15, search: debouncedSearch.trim() || undefined, sort: '-created_at' }),
    [page, debouncedSearch],
  );
  const { data, isLoading, isFetching } = useAdmins(queryParams);
  const del = useDeleteAdmin();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('admins.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.unauthorized')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('admins.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('admins.subtitle')}</p>
        </div>
        {can('admins.create') !== false && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('admins.create')}
          </Button>
        )}
      </div>

      <div className="relative max-w-sm">
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

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('admins.name')}</TableHead>
            <TableHead>{t('admins.email')}</TableHead>
            <TableHead>{t('admins.role')}</TableHead>
            <TableHead>{t('admins.is_active')}</TableHead>
            <TableHead>{t('admins.created_at')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((admin) => (
              <TableRow key={admin.id}>
                <TableCell className="font-medium">{admin.name}</TableCell>
                <TableCell dir="ltr">{admin.email}</TableCell>
                <TableCell>
                  <Badge variant={ROLE_VARIANT[admin.role]}>{t(`admins.role_${admin.role === 'sales' ? 'support' : admin.role}`)}</Badge>
                </TableCell>
                <TableCell>
                  {admin.is_active ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                  )}
                </TableCell>
                <TableCell className="text-xs text-[var(--color-muted-foreground)]">
                  {admin.created_at
                    ? new Date(admin.created_at).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US')
                    : '—'}
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(admin)} aria-label={t('common.edit')}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    {admin.id !== user?.user.id && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleting(admin)}
                        aria-label={t('common.delete')}
                        className="text-[var(--color-destructive)]"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
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
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('admins.edit') : t('admins.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('admins.subtitle')}</DialogDescription>
          </DialogHeader>
          <AdminForm
            admin={editing}
            onSuccess={() => {
              setCreating(false);
              setEditing(null);
            }}
            onCancel={() => {
              setCreating(false);
              setEditing(null);
            }}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('admins.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('admins.delete_confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
