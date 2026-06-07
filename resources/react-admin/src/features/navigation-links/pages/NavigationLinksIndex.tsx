import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, Check, ExternalLink, Pencil, Plus, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
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
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';

import { useNavigationLinks, useDeleteNavigationLink, useReorderNavigationLinks } from '../hooks';
import { NavigationLinkForm } from '../components/NavigationLinkForm';
import type { NavLocation, NavigationLink } from '../types';

export function NavigationLinksIndex() {
  const { t } = useTranslation();
  const { can } = useAuth();
  const [location, setLocation] = useState<NavLocation>('header');
  const [editing, setEditing] = useState<NavigationLink | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<NavigationLink | null>(null);

  const queryParams = useMemo(() => ({ per_page: 200, filter: { location } }), [location]);
  const { data, isLoading } = useNavigationLinks(queryParams);
  const del = useDeleteNavigationLink();
  const reorder = useReorderNavigationLinks();

  const rows = data?.data ?? [];

  const move = async (idx: number, direction: -1 | 1) => {
    const list = [...rows];
    const target = idx + direction;
    if (target < 0 || target >= list.length) return;
    [list[idx], list[target]] = [list[target], list[idx]];
    const payload = list.map((l, i) => ({ id: l.id, sort_order: i + 1 }));
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
      toast.success(t('navigation_links.deleted'));
      setDeleting(null);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('navigation_links.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('navigation_links.subtitle')}</p>
        </div>
        {can('navigation_links.create') && (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('navigation_links.create')}
          </Button>
        )}
      </div>

      {/* Location segmented control */}
      <div className="inline-flex rounded-lg border border-[var(--color-border)] p-1">
        {(['header', 'footer'] as NavLocation[]).map((loc) => (
          <button
            key={loc}
            type="button"
            onClick={() => setLocation(loc)}
            className={
              'rounded-md px-4 py-1.5 text-sm font-medium transition-colors ' +
              (location === loc
                ? 'bg-[var(--color-primary)] text-white'
                : 'text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]')
            }
          >
            {loc === 'header' ? t('navigation_links.location_header') : t('navigation_links.location_footer')}
          </button>
        ))}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-20">{t('navigation_links.order_actions')}</TableHead>
            <TableHead>{t('navigation_links.label')}</TableHead>
            {location === 'footer' && <TableHead>{t('navigation_links.column')}</TableHead>}
            <TableHead>{t('navigation_links.target')}</TableHead>
            <TableHead>{t('navigation_links.is_active')}</TableHead>
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
          ) : rows.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            rows.map((row, idx) => (
              <TableRow key={row.id}>
                <TableCell>
                  <div className="flex gap-1">
                    <Button variant="ghost" size="icon" disabled={idx === 0 || reorder.isPending} onClick={() => move(idx, -1)} aria-label="up">
                      <ArrowUp className="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" disabled={idx === rows.length - 1 || reorder.isPending} onClick={() => move(idx, 1)} aria-label="down">
                      <ArrowDown className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
                <TableCell className="font-medium">
                  <span className="inline-flex items-center gap-1.5">
                    {row.label_ar}
                    {row.open_new_tab && <ExternalLink className="h-3.5 w-3.5 text-[var(--color-muted-foreground)]" />}
                  </span>
                </TableCell>
                {location === 'footer' && (
                  <TableCell><Badge variant="muted">{row.footer_column ?? '—'}</Badge></TableCell>
                )}
                <TableCell className="text-[var(--color-muted-foreground)] text-xs" dir="ltr">
                  <a href={row.resolved_url} target="_blank" rel="noopener" className="hover:underline">{row.resolved_url}</a>
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
                    {can('navigation_links.update') && (
                      <Button variant="ghost" size="icon" onClick={() => setEditing(row)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                    )}
                    {can('navigation_links.delete') && (
                      <Button variant="ghost" size="icon" onClick={() => setDeleting(row)} aria-label={t('common.delete')} className="text-[var(--color-destructive)]">
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

      <Dialog
        open={creating || editing !== null}
        onOpenChange={(open) => {
          if (!open) {
            setCreating(false);
            setEditing(null);
          }
        }}
      >
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{editing ? t('navigation_links.edit') : t('navigation_links.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('navigation_links.subtitle')}</DialogDescription>
          </DialogHeader>
          <NavigationLinkForm
            link={editing}
            defaultLocation={location}
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
            <AlertDialogTitle>{t('navigation_links.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('navigation_links.delete_confirm_body')}</AlertDialogDescription>
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
