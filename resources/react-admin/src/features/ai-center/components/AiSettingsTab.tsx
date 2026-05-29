import { useMemo, useState } from 'react';
import { Bot, Pencil, Search, Shield, BellRing, Zap } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useSystemSettings } from '@/features/system-settings/hooks';
import { SettingEditDialog } from '@/features/system-settings/components/SettingEditDialog';
import type { SystemSetting } from '@/features/system-settings/types';

/**
 * Settings tab — reuses the existing /admin/system-settings endpoint
 * but filters by group prefix `ai*`. Adds search + grouping so 30+
 * rows stay browsable.
 */
const GROUPS: { key: string; icon: typeof Bot; titleKey: string; titleFallback: string }[] = [
  { key: 'ai',          icon: Bot,      titleKey: 'ai_center.group_main',     titleFallback: 'الإعدادات الأساسية' },
  { key: 'ai_alerts',   icon: BellRing, titleKey: 'ai_center.group_alerts',   titleFallback: 'الإنذارات' },
  { key: 'ai_cache',    icon: Zap,      titleKey: 'ai_center.group_cache',    titleFallback: 'الأداء (Cache)' },
  { key: 'ai_advanced', icon: Shield,   titleKey: 'ai_center.group_advanced', titleFallback: 'إعدادات متقدمة' },
];

export function AiSettingsTab() {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const [editing, setEditing] = useState<SystemSetting | null>(null);

  // Pull all four AI groups at once — one query per group, dedup'd by
  // the hook's cache key. We could merge in a single fetch with
  // `?filter[group]=ai*` if the backend supported wildcards; for now
  // a parallel fan-out works.
  const { data: dataMain,     isLoading: l1 } = useSystemSettings(undefined, 'ai');
  const { data: dataAlerts,   isLoading: l2 } = useSystemSettings(undefined, 'ai_alerts');
  const { data: dataCache,    isLoading: l3 } = useSystemSettings(undefined, 'ai_cache');
  const { data: dataAdvanced, isLoading: l4 } = useSystemSettings(undefined, 'ai_advanced');
  const isLoading = l1 || l2 || l3 || l4;

  const byGroup: Record<string, SystemSetting[]> = useMemo(() => ({
    ai:          (dataMain     ?? []).filter((s) => s.group === 'ai'),
    ai_alerts:   dataAlerts    ?? [],
    ai_cache:    dataCache     ?? [],
    ai_advanced: dataAdvanced  ?? [],
  }), [dataMain, dataAlerts, dataCache, dataAdvanced]);

  const filterRows = (rows: SystemSetting[]) => {
    if (!search.trim()) return rows;
    const q = search.toLowerCase();
    return rows.filter((s) =>
      s.key.toLowerCase().includes(q) ||
      (s.label ?? '').toLowerCase().includes(q) ||
      (s.description ?? '').toLowerCase().includes(q),
    );
  };

  const totalShown = GROUPS.reduce((sum, g) => sum + filterRows(byGroup[g.key] ?? []).length, 0);

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="max-w-3xl text-sm text-[var(--color-muted-foreground)]">
          {t('ai_center.settings_hint', 'إعدادات المساعد المركزية. مفاتيح API تَظهر مخفية ولا يمكن نسخها — للتعديل، اكتب القيمة الجديدة كاملة.')}
        </p>
        <div className="relative w-72 max-w-full">
          <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
          <Input
            className="ps-9"
            placeholder={t('common.search')}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {search && (
        <p className="text-xs text-[var(--color-muted-foreground)]">
          {t('ai_center.search_matches', '{{n}} نتيجة', { n: totalShown })}
        </p>
      )}

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : (
        GROUPS.map((g) => {
          const rows = filterRows(byGroup[g.key] ?? []);
          if (rows.length === 0) return null;
          const Icon = g.icon;
          return (
            <section key={g.key} className="rounded-lg border border-[var(--color-border)] bg-white">
              <div className="flex items-center gap-2 border-b border-[var(--color-border)] px-4 py-3">
                <Icon className="h-4 w-4 text-[var(--color-primary)]" />
                <h2 className="text-sm font-semibold">{t(g.titleKey, g.titleFallback)}</h2>
                <Badge variant="muted" className="ms-auto text-[10px]">{rows.length}</Badge>
              </div>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('system_settings.label')}</TableHead>
                    <TableHead>{t('system_settings.value')}</TableHead>
                    <TableHead className="w-24">{t('system_settings.type')}</TableHead>
                    <TableHead className="w-16 text-end">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((s) => (
                    <TableRow key={s.id}>
                      <TableCell>
                        <div className="font-medium">{s.label}</div>
                        <div dir="ltr" className="font-mono text-[11px] text-[var(--color-muted-foreground)]">{s.key}</div>
                        {s.description && (
                          <div className="mt-1 max-w-md text-[11px] text-[var(--color-muted-foreground)]">
                            {s.description}
                          </div>
                        )}
                      </TableCell>
                      <TableCell className="max-w-md text-[var(--color-muted-foreground)]">
                        {s.type === 'encrypted' ? (
                          s.value_set ? (
                            <span className="inline-flex items-center gap-1">
                              <span dir="ltr" className="font-mono">••••••••</span>
                              <Badge variant="muted">{t('ai_center.value_set', 'مضبوط')}</Badge>
                            </span>
                          ) : (
                            <Badge variant="muted">{t('ai_center.value_not_set', 'غير مضبوط')}</Badge>
                          )
                        ) : s.type === 'boolean' ? (
                          <Badge variant="muted">{s.value === '1' || s.value === 'true' ? t('common.yes', 'نعم') : t('common.no', 'لا')}</Badge>
                        ) : (
                          <span className="line-clamp-2">{s.value ?? '—'}</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant="muted" className="text-[10px]">{s.type}</Badge>
                      </TableCell>
                      <TableCell className="text-end">
                        <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}>
                          <Pencil className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </section>
          );
        })
      )}

      {!isLoading && totalShown === 0 && (
        <div className="rounded-md border border-dashed border-[var(--color-border)] py-8 text-center text-sm text-[var(--color-muted-foreground)]">
          {search ? t('common.no_results', 'لا نتائج') : t('common.no_data')}
        </div>
      )}

      {editing && <SettingEditDialog setting={editing} onClose={() => setEditing(null)} />}
    </div>
  );
}
