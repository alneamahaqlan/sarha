import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, ExternalLink, Lock, Pencil } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useAuth } from '@/app/providers/AuthProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useClinicPageSections,
  useReorderClinicPageSections,
  useUpdateClinicPageSection,
} from '../hooks';
import type { ClinicPageSection } from '../types';
import { ClinicPageSectionEditDialog } from '../components/ClinicPageSectionEditDialog';

export function ClinicPageBuilderIndex() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { data, isLoading } = useClinicPageSections();
  const reorder = useReorderClinicPageSections();

  const [editing, setEditing] = useState<ClinicPageSection | null>(null);

  /**
   * "Open my public page" link — uses the authenticated clinic's slug so
   * the admin can flip to a new tab and preview their changes immediately.
   * Falls back to /search if the slug is somehow missing (defensive).
   */
  const publicUrl =
    user?.user && 'slug' in user.user && user.user.slug
      ? `/clinic/${user.user.slug}`
      : '/search';

  const move = async (idx: number, direction: -1 | 1) => {
    if (!data) return;
    const list = [...data];
    const target = idx + direction;
    if (target < 0 || target >= list.length) return;
    [list[idx], list[target]] = [list[target], list[idx]];
    const payload = list.map((s, i) => ({ id: s.id, sort_order: (i + 1) * 10 }));
    try {
      await reorder.mutateAsync({ order: payload });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const moveTo = async (fromIdx: number, targetPosition: number) => {
    if (!data) return;
    const total = data.length;
    const toIdx = Math.max(0, Math.min(total - 1, targetPosition - 1));
    if (toIdx === fromIdx) return;
    const list = [...data];
    const [moved] = list.splice(fromIdx, 1);
    list.splice(toIdx, 0, moved);
    const payload = list.map((s, i) => ({ id: s.id, sort_order: (i + 1) * 10 }));
    try {
      await reorder.mutateAsync({ order: payload });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">
            {t('clinic_page_builder.title', 'تخصيص صفحتي العامة')}
          </h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t(
              'clinic_page_builder.subtitle',
              'تحكّم في ترتيب وعرض أقسام صفحة مجمعك. التغييرات تظهر للزوار خلال ثوانٍ.',
            )}
          </p>
        </div>
        <Button asChild variant="outline">
          <a href={publicUrl} target="_blank" rel="noopener noreferrer">
            <ExternalLink className="h-4 w-4" />
            {t('clinic_page_builder.view_public_page', 'فتح صفحتي العامة')}
          </a>
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-32">{t('clinic_page_builder.order', 'الترتيب')}</TableHead>
            <TableHead>{t('clinic_page_builder.section', 'القسم')}</TableHead>
            <TableHead>{t('clinic_page_builder.title_col', 'العنوان المخصص')}</TableHead>
            <TableHead className="w-24">{t('clinic_page_builder.limit_col', 'الحد')}</TableHead>
            <TableHead className="w-32">{t('clinic_page_builder.is_active', 'مفعّل')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : !data || data.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            data.map((s, idx) => (
              <SectionRow
                key={s.id}
                section={s}
                position={idx + 1}
                total={data.length}
                isFirst={idx === 0}
                isLast={idx === data.length - 1}
                onMoveUp={() => move(idx, -1)}
                onMoveDown={() => move(idx, 1)}
                onMoveTo={(pos) => moveTo(idx, pos)}
                onEdit={() => setEditing(s)}
                friendlyName={t(`clinic_page_builder.section_name.${s.key}`, s.key)}
              />
            ))
          )}
        </TableBody>
      </Table>

      {editing && (
        <ClinicPageSectionEditDialog
          section={editing}
          friendlyName={t(`clinic_page_builder.section_name.${editing.key}`, editing.key)}
          onClose={() => setEditing(null)}
        />
      )}
    </div>
  );
}

interface RowProps {
  section: ClinicPageSection;
  position: number;
  total: number;
  isFirst: boolean;
  isLast: boolean;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onMoveTo: (position: number) => void;
  onEdit: () => void;
  friendlyName: string;
}

function SectionRow({ section, position, total, isFirst, isLast, onMoveUp, onMoveDown, onMoveTo, onEdit, friendlyName }: RowProps) {
  const { t } = useTranslation();
  const update = useUpdateClinicPageSection(section.id);
  const [draftPos, setDraftPos] = useState<string>(String(position));

  useEffect(() => {
    if (document.activeElement?.id !== `pos-${section.id}`) {
      setDraftPos(String(position));
    }
  }, [position, section.id]);

  const commitPos = () => {
    const n = Number(draftPos);
    if (!Number.isFinite(n) || n < 1) {
      setDraftPos(String(position));
      return;
    }
    if (n === position) return;
    onMoveTo(n);
  };

  const onToggle = async (c: boolean) => {
    try {
      await update.mutateAsync({ is_active: c });
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  const displayTitle = section.title_ar || section.title_en;

  return (
    <TableRow>
      <TableCell>
        <div className="flex items-center gap-1">
          <Input
            id={`pos-${section.id}`}
            type="number"
            min={1}
            max={total}
            value={draftPos}
            onChange={(e) => setDraftPos(e.target.value)}
            onBlur={commitPos}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                commitPos();
                (e.target as HTMLInputElement).blur();
              }
              if (e.key === 'Escape') {
                setDraftPos(String(position));
                (e.target as HTMLInputElement).blur();
              }
            }}
            className="h-8 w-14 text-center font-mono text-sm"
            title={t('clinic_page_builder.move_to_hint', 'اكتب رقم الصف واضغط Enter')}
          />
          <div className="flex flex-col">
            <Button variant="ghost" size="icon" onClick={onMoveUp} disabled={isFirst} className="h-4 w-5">
              <ArrowUp className="h-3 w-3" />
            </Button>
            <Button variant="ghost" size="icon" onClick={onMoveDown} disabled={isLast} className="h-4 w-5">
              <ArrowDown className="h-3 w-3" />
            </Button>
          </div>
        </div>
      </TableCell>
      <TableCell>
        <div className="flex items-center gap-2">
          <div className="font-medium text-[var(--color-foreground)]">{friendlyName}</div>
          {section.is_protected && (
            <Badge variant="muted" className="gap-1">
              <Lock className="h-3 w-3" />
              {t('clinic_page_builder.protected', 'محمي')}
            </Badge>
          )}
        </div>
        <div className="font-mono text-[11px] text-[var(--color-muted-foreground)] mt-0.5">{section.key}</div>
      </TableCell>
      <TableCell className="text-sm">
        {displayTitle ? (
          <span className="line-clamp-1">{displayTitle}</span>
        ) : (
          <span className="text-[var(--color-muted-foreground)] italic text-xs">
            {t('clinic_page_builder.default_title', 'النص الافتراضي')}
          </span>
        )}
      </TableCell>
      <TableCell className="text-sm">
        {section.item_limit !== null ? (
          <span className="font-mono">{section.item_limit}</span>
        ) : (
          <span className="text-[var(--color-muted-foreground)] italic text-xs">—</span>
        )}
      </TableCell>
      <TableCell>
        <Switch
          checked={section.is_active}
          onCheckedChange={onToggle}
          disabled={update.isPending || section.is_protected}
          title={
            section.is_protected
              ? t('clinic_page_builder.cannot_hide_hint', 'هذا القسم محمي ولا يمكن إخفاؤه.')
              : undefined
          }
        />
      </TableCell>
      <TableCell className="text-end">
        <Button variant="ghost" size="icon" onClick={onEdit} title={t('common.edit')}>
          <Pencil className="h-4 w-4" />
        </Button>
      </TableCell>
    </TableRow>
  );
}
