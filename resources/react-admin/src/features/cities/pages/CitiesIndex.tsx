import { useState, useMemo } from 'react';
import { Check, Plus, Search, Trash2, X, Pencil } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
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
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage, isApiError } from '@/lib/api-client';

import { useCities, useDeleteCity } from '../hooks';
import { CityForm } from '../components/CityForm';
import type { City } from '../types';

export function CitiesIndex() {
  const { t } = useTranslation();
  const { can } = useAuth();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<City | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<City | null>(null);

  const queryParams = useMemo(
    () => ({ page, per_page: 15, search: search.trim() || undefined, sort: 'sort_order' }),
    [page, search],
  );
  const { data, isLoading, isFetching } = useCities(queryParams);
  const del = useDeleteCity();

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('cities.deleted'));
      setDeleting(null);
    } catch (err) {
      if (isApiError(err) && err.response?.status === 403) {
        toast.error(t('cities.cannot_delete_has_clinics'));
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('cities.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('cities.subtitle')}</p>
        </div>
        {can('cities.create') && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('cities.create')}
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
            <TableHead>{t('cities.name')}</TableHead>
            <TableHead>{t('cities.name_en')}</TableHead>
            <TableHead>{t('cities.clinics_count')}</TableHead>
            <TableHead>{t('cities.is_active')}</TableHead>
            <TableHead>{t('cities.sort_order')}</TableHead>
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
            data.data.map((city) => (
              <TableRow key={city.id}>
                <TableCell className="font-medium">{city.name}</TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]">{city.name_en ?? '—'}</TableCell>
                <TableCell>
                  <Badge variant="muted">{city.clinics_count ?? 0}</Badge>
                </TableCell>
                <TableCell>
                  {city.is_active ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                  )}
                </TableCell>
                <TableCell>{city.sort_order}</TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {can('cities.update') && (
                      <Button variant="ghost" size="icon" onClick={() => setEditing(city)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {can('cities.delete') && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleting(city)}
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

      <Dialog open={creating || editing !== null} onOpenChange={(open) => { if (!open) { setCreating(false); setEditing(null); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('cities.edit') : t('cities.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('cities.subtitle')}</DialogDescription>
          </DialogHeader>
          <CityForm
            city={editing}
            onSuccess={() => { setCreating(false); setEditing(null); }}
            onCancel={() => { setCreating(false); setEditing(null); }}
          />
        </DialogContent>
      </Dialog>

      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('cities.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {t('cities.delete_confirm_body')}
              {deleting && deleting.clinics_count && deleting.clinics_count > 0 ? (
                <span className="mt-2 block text-[var(--color-destructive)]">
                  {t('cities.cannot_delete_has_clinics')}
                </span>
              ) : null}
            </AlertDialogDescription>
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
