import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Bot, ChevronDown, ChevronUp, Calendar } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useUserAiInterests } from '../hooks';
import type { Seriousness } from '../types';

interface Props {
  userId: number;
}

/**
 * Collapsible "AI Interests" panel — embedded inside the user profile
 * dialog/page. Admin-only. Default state: collapsed (it's auxiliary
 * context, not primary info).
 */
export function UserAiInterestsSection({ userId }: Props) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const { data, isLoading } = useUserAiInterests(open ? userId : null);

  return (
    <section className="rounded-lg border border-[var(--color-border)] bg-white">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between gap-2 px-4 py-3 text-start hover:bg-[var(--color-muted)]"
      >
        <span className="inline-flex items-center gap-2 text-sm font-semibold">
          <Bot className="h-4 w-4 text-sky-600" />
          {t('ai_center.user_interests_title', 'اهتمامات هذا المستخدم (AI)')}
        </span>
        {open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>

      {open && (
        <div className="border-t border-[var(--color-border)] p-4">
          {isLoading ? (
            <div className="py-6 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
          ) : !data || !data.has_history ? (
            <div className="rounded-md border border-dashed border-[var(--color-border)] py-6 text-center text-sm text-[var(--color-muted-foreground)]">
              {t('ai_center.user_no_history', 'لم يتحدث هذا المستخدم مع المساعد بعد.')}
            </div>
          ) : (
            <Body data={data} />
          )}
        </div>
      )}
    </section>
  );
}

function Body({ data }: { data: NonNullable<ReturnType<typeof useUserAiInterests>['data']> }) {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA' : 'en-US');
  const dateFmt = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-US', { dateStyle: 'medium' });

  return (
    <div className="space-y-4">
      {/* Rollup row */}
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        <Rollup label={t('ai_center.user_rollup_total', 'إجمالي المحادثات')} value={fmt.format(data.conversation_count)} />
        <Rollup
          label={t('ai_center.user_rollup_specialty', 'التخصص الأكثر')}
          value={data.top_specialty?.name ?? '—'}
        />
        <Rollup
          label={t('ai_center.user_rollup_last_seen', 'آخر تفاعل')}
          value={data.last_interaction_at ? dateFmt.format(new Date(data.last_interaction_at)) : '—'}
        />
        <Rollup
          label={t('ai_center.user_rollup_top_clinics', 'مجمعات بحث')}
          value={
            data.top_clinics.length > 0
              ? data.top_clinics.map((c) => c.name).slice(0, 3).join(' · ')
              : '—'
          }
          small
        />
      </div>

      {/* Timeline */}
      <div className="space-y-3">
        <h3 className="text-sm font-semibold">{t('ai_center.user_timeline', 'آخر 10 محادثات')}</h3>
        {data.timeline.length === 0 ? (
          <div className="text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
        ) : (
          <ol className="space-y-2">
            {data.timeline.map((row) => (
              <li key={row.id} className="rounded-md border border-[var(--color-border)] p-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0 flex-1">
                    <p className="text-sm">{row.topic}</p>
                    <div className="mt-1 inline-flex items-center gap-1 text-[11px] text-[var(--color-muted-foreground)]">
                      <Calendar className="h-3 w-3" />
                      {row.generated_at ? dateFmt.format(new Date(row.generated_at)) : '—'}
                    </div>
                  </div>
                  <SeriousnessBadge level={row.seriousness} />
                </div>

                {(row.categories.length > 0 || row.clinics.length > 0) && (
                  <div className="mt-2 flex flex-wrap gap-1">
                    {row.categories.map((c) => (
                      <Badge key={'cat' + c.id} variant="muted" className="text-[10px]">
                        {c.emoji && <span className="me-1">{c.emoji}</span>}{c.name}
                      </Badge>
                    ))}
                    {row.clinics.map((c) => (
                      <Link key={'cl' + c.id} to={`/admin/clinics/${c.id}/stats`}>
                        <Badge variant="muted" className="cursor-pointer text-[10px] hover:bg-[var(--color-primary)]/10">
                          🏥 {c.name}
                        </Badge>
                      </Link>
                    ))}
                  </div>
                )}
              </li>
            ))}
          </ol>
        )}
      </div>
    </div>
  );
}

function Rollup({ label, value, small }: { label: string; value: string; small?: boolean }) {
  return (
    <div className="rounded-md border border-[var(--color-border)] p-2">
      <div className="text-[10px] uppercase text-[var(--color-muted-foreground)]">{label}</div>
      <div className={small ? 'text-xs font-medium' : 'mt-1 text-base font-semibold'}>{value}</div>
    </div>
  );
}

function SeriousnessBadge({ level }: { level: Seriousness }) {
  const { t } = useTranslation();
  const cls = level === 'near_decision' ? 'bg-red-50 text-red-700'
    : level === 'comparing'             ? 'bg-amber-50 text-amber-700'
    : 'bg-gray-100 text-gray-700';
  return (
    <Badge className={`shrink-0 text-[10px] ${cls}`}>
      {t(`ai_center.seriousness_${level}`, level)}
    </Badge>
  );
}
