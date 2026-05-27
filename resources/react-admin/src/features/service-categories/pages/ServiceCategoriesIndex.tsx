import { useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, Check, Pencil, Plus, Search, Trash2, X } from 'lucide-react';
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
import { extractMessage, isApiError } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import {
  useDeleteServiceCategory,
  useReorderServiceCategories,
  useServiceCategories,
} from '../hooks';
import { ServiceCategoryForm } from '../components/ServiceCategoryForm';
import type { ServiceCategory } from '../types';

export function ServiceCategoriesIndex() {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<ServiceCategory | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<ServiceCategory | null>(null);

  const queryParams = useMemo(
    () => ({ page, per_page: 30, search: debouncedSearch.trim() || undefined, sort: 'sort_order' }),
    [page, debouncedSearch],
  );
  const { data, isLoading, isFetching } = useServiceCategories(queryParams);
  const del = useDeleteServiceCategory();
  const reorder = useReorderServiceCategories();

  const move = async (idx: number, direction: -1 | 1) => {
    if (!data) return;
    const list = [...data.data];
    const target = idx + direction;
    if (target < 0 || target >= list.length) return;
    [list[idx], list[target]] = [list[target], list[idx]];
    const payload = list.map((c, i) => ({ id: c.id, sort_order: i + 1 }));
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
      toast.success(t('service_categories.deleted', 'تم الحذف'));
      setDeleting(null);
    } catch (err) {
      if (isApiError(err) && err.response?.status === 403) {
        toast.error(t('service_categories.cannot_delete_has_services', 'لا يمكن حذف فئة لها خدمات حالية'));
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('service_categories.title', 'فئات الخدمات')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t(
              'service_categories.subtitle',
              'الفئات التي تُصنَّف بها كل خدمة يضيفها المجمع — يديرها الأدمن فقط.',
            )}
          </p>
        </div>
        <Button onClick={() => setCreating(true)}>
          <Plus className="h-4 w-4" />
          {t('service_categories.create', 'إضافة فئة')}
        </Button>
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
            <TableHead className="w-20">{t('service_categories.order_actions', 'الترتيب')}</TableHead>
            <TableHead className="w-10">{t('service_categories.emoji', 'إيموجي')}</TableHead>
            <TableHead>{t('service_categories.name', 'الاسم')}</TableHead>
            <TableHead>{t('service_categories.slug', 'الـ slug')}</TableHead>
            <TableHead>{t('service_categories.services_count', 'عدد الخدمات')}</TableHead>
            <TableHead>{t('service_categories.is_active', 'مفعّلة')}</TableHead>
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
            data.data.map((category, idx) => (
              <TableRow key={category.id}>
                <TableCell>
                  <div className="flex gap-1">
                    <Button
                      variant="ghost"
                      size="icon"
                      disabled={idx === 0 || reorder.isPending}
                      onClick={() => move(idx, -1)}
                      aria-label="up"
                    >
                      <ArrowUp className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      disabled={idx === data.data.length - 1 || reorder.isPending}
                      onClick={() => move(idx, 1)}
                      aria-label="down"
                    >
                      <ArrowDown className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
                <TableCell className="text-xl">{category.emoji ?? '—'}</TableCell>
                <TableCell className="font-medium">
                  {category.name}
                  {category.name_en && (
                    <span className="ms-2 text-xs text-[var(--color-muted-foreground)]" dir="ltr">
                      {category.name_en}
                    </span>
                  )}
                </TableCell>
                <TableCell className="text-[var(--color-muted-foreground)]" dir="ltr">{category.slug}</TableCell>
                <TableCell>
                  <Badge variant="muted">{category.services_count ?? 0}</Badge>
                </TableCell>
                <TableCell>
                  {category.is_active ? (
                    <Check className="h-4 w-4 text-emerald-600" />
                  ) : (
                    <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                  )}
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" onClick={() => setEditing(category)} aria-label={t('common.edit')}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => setDeleting(category)}
                      aria-label={t('common.delete')}
                      className="text-[var(--color-destructive)]"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
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
            <DialogTitle>
              {editing
                ? t('service_categories.edit', 'تعديل فئة الخدمة')
                : t('service_categories.create', 'إضافة فئة')}
            </DialogTitle>
            <DialogDescription className="sr-only">
              {t('service_categories.subtitle', 'فئات الخدمات')}
            </DialogDescription>
          </DialogHeader>
          <ServiceCategoryForm
            category={editing}
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
            <AlertDialogTitle>
              {t('service_categories.delete_confirm_title', 'حذف فئة الخدمة')}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {t('service_categories.delete_confirm_body', 'هل أنت متأكد من حذف هذه الفئة؟')}
              {deleting && deleting.services_count && deleting.services_count > 0 ? (
                <span className="mt-2 block text-[var(--color-destructive)]">
                  {t(
                    'service_categories.cannot_delete_has_services',
                    'لا يمكن حذف فئة لها خدمات حالية — انقل الخدمات أولاً.',
                  )}
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
