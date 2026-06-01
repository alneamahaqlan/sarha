import { Link } from 'react-router-dom';
import { ArrowUpRight, Bot, AlertTriangle, ShieldOff, MessageCircle } from 'lucide-react';

import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';
import { useAiDashboardWidget } from '../hooks';

/**
 * Compact AI summary card for the admin DashboardPage. Sized to match
 * the existing kpi widgets in that page so the layout doesn't shift.
 */
export function AiDashboardWidget() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const { data, isLoading } = useAiDashboardWidget();

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex items-center gap-2 text-xs uppercase text-[var(--color-muted-foreground)]">
            <Bot className="h-3.5 w-3.5" />
            {t('ai_center.widget_title', 'المساعد الذكي اليوم')}
          </div>

          {isLoading ? (
            <div className="mt-2 h-8 w-24 animate-pulse rounded bg-[var(--color-muted)]" />
          ) : (
            <>
              <div className="mt-1 text-2xl font-semibold">
                {data ? fmt.format(data.today_count) : '—'}
                <span className="ms-1 text-xs font-normal text-[var(--color-muted-foreground)]">
                  {t('ai_center.conversations_unit', 'محادثة')}
                </span>
              </div>
              {data && (
                <div className="text-[11px] text-[var(--color-muted-foreground)]">
                  {t('ai_center.yesterday_was', 'الأمس: {{n}}', { n: fmt.format(data.yesterday_count) })}
                </div>
              )}
            </>
          )}
        </div>

        <Link
          to="/admin/ai-center"
          className="inline-flex items-center gap-1 rounded-md border border-[var(--color-border)] px-2 py-1 text-xs font-medium hover:bg-[var(--color-muted)]"
        >
          {t('ai_center.open_center', 'افتح المركز')}
          <ArrowUpRight className="h-3 w-3" />
        </Link>
      </div>

      {data?.top_topic && (
        <div className="mt-3 flex items-start gap-2 rounded-md bg-[var(--color-muted)] p-2 text-xs">
          <MessageCircle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-[var(--color-muted-foreground)]" />
          <div className="min-w-0">
            <div className="text-[10px] uppercase text-[var(--color-muted-foreground)]">
              {t('ai_center.widget_top_topic', 'أعلى موضوع')}
            </div>
            <div className="line-clamp-1 font-medium">{data.top_topic.text}</div>
          </div>
        </div>
      )}

      {data?.alert && (
        <div className={`mt-3 flex items-start gap-2 rounded-md p-2 text-xs ${
          data.alert.kind === 'emergency'
            ? 'bg-red-50 text-red-700'
            : 'bg-amber-50 text-amber-700'
        }`}>
          {data.alert.kind === 'emergency' ? (
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          ) : (
            <ShieldOff className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          )}
          <div>
            {data.alert.kind === 'emergency'
              ? t('ai_center.alert_emergency', 'محادثة طوارئ خلال آخر 24 ساعة')
              : t('ai_center.alert_block_spike', 'ارتفاع غير طبيعي في الرفض ({{x}}× الطبيعي)', { x: data.alert.ratio ?? '?' })}
          </div>
        </div>
      )}
    </div>
  );
}
