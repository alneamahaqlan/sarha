import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, ExternalLink, Image, Pencil } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import {
  useHomepageSections,
  useReorderHomepageSections,
  useUpdateHomepageSection,
} from '../hooks';
import type { HomepageSection } from '../types';
import { HomepageSectionEditDialog } from '../components/HomepageSectionEditDialog';
import { BannerSlidesDialog } from '../components/BannerSlidesDialog';

const TYPE_LABEL_KEY: Record<string, string> = {
  hero: 'homepage_sections.type_hero',
  stats: 'homepage_sections.type_stats',
  banner: 'homepage_sections.type_banner',
  offers: 'homepage_sections.type_offers',
  articles: 'homepage_sections.type_articles',
  categories: 'homepage_sections.type_categories',
  category_offers: 'homepage_sections.type_category_offers',
  ai_highlight: 'homepage_sections.type_ai_highlight',
  how_it_works: 'homepage_sections.type_how_it_works',
  clinic_list: 'homepage_sections.type_clinic_list',
  map: 'homepage_sections.type_map',
  cta: 'homepage_sections.type_cta',
  price_quote: 'homepage_sections.type_price_quote',
  faqs: 'homepage_sections.type_faqs',
  followed_offers: 'homepage_sections.type_followed_offers',
  followed_clinics: 'homepage_sections.type_followed_clinics',
};

export function HomepageSectionsIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useHomepageSections();
  const reorder = useReorderHomepageSections();

  const [editing, setEditing] = useState<HomepageSection | null>(null);
  const [slidesFor, setSlidesFor] = useState<HomepageSection | null>(null);

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

  /** Jump a section to an explicit 1-indexed position by splice-reordering. */
  const moveTo = async (fromIdx: number, targetPosition: number) => {
    if (!data) return;
    const total = data.length;
    // Clamp to [1, total] — the user can't move outside the list.
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
          <h1 className="text-2xl font-semibold">{t('homepage_sections.title', 'سكشنات الصفحة الرئيسية')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {t(
              'homepage_sections.subtitle',
              'تحكّم في ترتيب وعرض كل سكشن من سكشنات الصفحة الرئيسية. التغييرات تظهر للزوار خلال دقائق.',
            )}
          </p>
        </div>
        <Button asChild variant="outline">
          <a href="/" target="_blank" rel="noopener noreferrer">
            <ExternalLink className="h-4 w-4" />
            {t('homepage_sections.view_homepage', 'فتح الصفحة الرئيسية')}
          </a>
        </Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-32">{t('homepage_sections.order', 'الترتيب')}</TableHead>
            <TableHead>{t('homepage_sections.section_key', 'المعرّف')}</TableHead>
            <TableHead>{t('homepage_sections.type_col', 'النوع')}</TableHead>
            <TableHead>{t('homepage_sections.title_col', 'العنوان المخصص')}</TableHead>
            <TableHead className="w-32">{t('homepage_sections.is_active', 'مفعّل')}</TableHead>
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
                onManageSlides={() => setSlidesFor(s)}
                typeLabel={t(TYPE_LABEL_KEY[s.type] ?? '', s.type)}
                friendlyName={t(`homepage_sections.section_name.${s.key}`, s.key)}
              />
            ))
          )}
        </TableBody>
      </Table>

      {editing && (
        <HomepageSectionEditDialog
          section={editing}
          onClose={() => setEditing(null)}
          onOpenSlides={() => {
            const section = editing;
            setEditing(null);
            setSlidesFor(section);
          }}
        />
      )}

      {slidesFor && <BannerSlidesDialog section={slidesFor} onClose={() => setSlidesFor(null)} />}
    </div>
  );
}

interface RowProps {
  section: HomepageSection;
  position: number;
  total: number;
  isFirst: boolean;
  isLast: boolean;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onMoveTo: (position: number) => void;
  onEdit: () => void;
  onManageSlides: () => void;
  typeLabel: string;
  friendlyName: string;
}

function SectionRow({ section, position, total, isFirst, isLast, onMoveUp, onMoveDown, onMoveTo, onEdit, onManageSlides, typeLabel, friendlyName }: RowProps) {
  const { t } = useTranslation();
  const update = useUpdateHomepageSection(section.id);
  // Local input value so the user can type freely; commits on blur / Enter.
  const [draftPos, setDraftPos] = useState<string>(String(position));

  // Sync from props when the reorder API resolves and `position` changes,
  // but never overwrite a value the user is actively typing.
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
            title={t('homepage_sections.move_to_hint', 'اكتب رقم الصف واضغط Enter للنقل المباشر')}
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
        <div className="font-medium text-[var(--color-foreground)]">{friendlyName}</div>
        <div className="font-mono text-[11px] text-[var(--color-muted-foreground)] mt-0.5">{section.key}</div>
      </TableCell>
      <TableCell>
        <Badge variant="muted">{typeLabel}</Badge>
        {section.type === 'banner' && section.banner_slides_count !== undefined && (
          <span className="ms-2 text-xs text-[var(--color-muted-foreground)]">
            ({section.banner_slides_count} {t('homepage_sections.slides_count', 'صورة')})
          </span>
        )}
      </TableCell>
      <TableCell className="text-sm">
        {displayTitle ? (
          <span className="line-clamp-1">{displayTitle}</span>
        ) : (
          <span className="text-[var(--color-muted-foreground)] italic text-xs">
            {t('homepage_sections.default_title', 'النص الافتراضي')}
          </span>
        )}
      </TableCell>
      <TableCell>
        <Switch checked={section.is_active} onCheckedChange={onToggle} disabled={update.isPending} />
      </TableCell>
      <TableCell className="text-end">
        <div className="flex justify-end gap-1">
          {section.type === 'banner' && (
            <Button variant="ghost" size="icon" onClick={onManageSlides} title={t('homepage_sections.manage_slides', 'إدارة الصور')}>
              <Image className="h-4 w-4" />
            </Button>
          )}
          <Button variant="ghost" size="icon" onClick={onEdit} title={t('common.edit')}>
            <Pencil className="h-4 w-4" />
          </Button>
        </div>
      </TableCell>
    </TableRow>
  );
}
