import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, Check, Lock, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
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
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage, isApiError } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useStaticPages, useDeleteStaticPage, useReorderStaticPages } from '../hooks';
import { StaticPageForm } from '../components/StaticPageForm';
import type { StaticPage } from '../types';

export function StaticPagesIndex() {
  const { t } = useTranslation();
  const { can } = useAuth();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<StaticPage | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<StaticPage | null>(null);

  const queryParams = useMemo(
    () => ({ page, per_page: 30, search: debouncedSearch.trim() || undefined, sort: 'sort_order' }),
    [page, debouncedSearch],
  );
  const { data, isLoading, isFetching } = useStaticPages(queryParams);
  const del = useDeleteStaticPage();
  const reorder = useReorderStaticPages();

  const move = async (idx: number, direction: -1 | 1) => {
    if (!data) return;
    const list = [...data.data];
    const target = idx + direction;
    if (target < 0 || target >= list.length) return;
    [list[idx], list[target]] = [list[target], list[idx]];
    const payload = list.map((p, i) => ({ id: p.id, sort_order: i + 1 }));
    try {
      await reorder.mutateAsync({ order: payload });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('static_pages.deleted'));
      setDeleting(null);
    } catch (err) {
      if (isApiError(err) && err.response?.status === 403) {
        toast.error(t('static_pages.cannot_delete_system'));
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('static_pages.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('static_pages.subtitle')}</p>
        </div>
        {can('static_pages.create') && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('static_pages.create')}
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
            <TableHead className="w-20">{t('static_pages.order_actions')}</TableHead>
            <TableHead>{t('static_pages.page_title')}</TableHead>
            <TableHead>{t('static_pages.slug')}</TableHead>
            <TableHead>{t('static_pages.is_active')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.data.map((row, idx) => (
              <TableRow key={row.id}>
                <TableCell>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon" disabled={idx === 0 || reorder.isPending} onClick={() => move(idx, -1)} aria-label="up">
                      <ArrowUp className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" disabled={idx === data.data.length - 1 || reorder.isPending} onClick={() => move(idx, 1)} aria-label="down">
                      <ArrowDown className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
                <TableCell className="font-medium">
                  <span className="inline-flex items-center gap-1.5">
                    {row.is_system && <Lock className="h-3.5 w-3.5 text-[var(--color-muted-foreground)]" aria-label={t('static_pages.system_badge')} />}
                    {row.title_ar}
                  </span>
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]" dir="ltr">
                  <a href={`/${row.slug}`} target="_blank" rel="noopener" className="hover:underline">/{row.slug}</a>
                </TableCell>
                <TableCell>
                  {row.is_active ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                  )}
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    {can('static_pages.update') && (
                      <Button variant="ghost" size="icon" onClick={() => setEditing(row)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {can('static_pages.delete') && !row.is_system && (
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleting(row)}
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
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
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
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing ? t('static_pages.edit') : t('static_pages.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('static_pages.subtitle')}</DialogDescription>
          </DialogHeader>
          <StaticPageForm
            page={editing}
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
            <AlertDialogTitle>{t('static_pages.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('static_pages.delete_confirm_body')}</AlertDialogDescription>
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
