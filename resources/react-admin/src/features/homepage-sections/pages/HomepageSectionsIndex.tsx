import { useState } from 'react';
import { toast } from 'sonner';
import { ArrowDown, ArrowUp, ExternalLink, Image, Pencil, Settings } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
            <TableHead className="w-20">{t('homepage_sections.order', 'الترتيب')}</TableHead>
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
                isFirst={idx === 0}
                isLast={idx === data.length - 1}
                onMoveUp={() => move(idx, -1)}
                onMoveDown={() => move(idx, 1)}
                onEdit={() => setEditing(s)}
                onManageSlides={() => setSlidesFor(s)}
                typeLabel={t(TYPE_LABEL_KEY[s.type] ?? '', s.type)}
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
  isFirst: boolean;
  isLast: boolean;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onEdit: () => void;
  onManageSlides: () => void;
  typeLabel: string;
}

function SectionRow({ section, isFirst, isLast, onMoveUp, onMoveDown, onEdit, onManageSlides, typeLabel }: RowProps) {
  const { t } = useTranslation();
  const update = useUpdateHomepageSection(section.id);

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
        <div className="flex items-center gap-0.5">
          <Button variant="ghost" size="icon" onClick={onMoveUp} disabled={isFirst}>
            <ArrowUp className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon" onClick={onMoveDown} disabled={isLast}>
            <ArrowDown className="h-4 w-4" />
          </Button>
        </div>
      </TableCell>
      <TableCell className="font-mono text-xs">{section.key}</TableCell>
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
