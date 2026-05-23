import { useMemo, useState } from 'react';
import { Pencil, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useDebouncedValue } from '@/lib/use-debounced-value';

import { useSystemSettings } from '../hooks';
import { SettingEditDialog } from '../components/SettingEditDialog';
import type { SystemSetting } from '../types';

export function SystemSettingsIndex() {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebouncedValue(search, 300);
  const [editing, setEditing] = useState<SystemSetting | null>(null);
  const { data, isLoading } = useSystemSettings(debouncedSearch.trim() || undefined);

  // Group rows by group, mirroring Filament's defaultGroup('group').
  const grouped = useMemo(() => {
    const map = new Map<string, SystemSetting[]>();
    (data ?? []).forEach((s) => {
      const key = s.group ?? '—';
      if (!map.has(key)) map.set(key, []);
      map.get(key)!.push(s);
    });
    return Array.from(map.entries());
  }, [data]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('system_settings.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('system_settings.subtitle')}</p>
      </div>

      <div className="relative max-w-sm">
        <Search className="absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-muted-foreground)]" />
        <Input
          className="ps-9"
          placeholder={t('common.search')}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
      </div>

      {isLoading ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : grouped.length === 0 ? (
        <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
      ) : (
        grouped.map(([group, rows]) => (
          <div key={group} className="space-y-2">
            <h2 className="text-sm font-semibold text-[var(--color-muted-foreground)]">{group}</h2>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('system_settings.key')}</TableHead>
                  <TableHead>{t('system_settings.label')}</TableHead>
                  <TableHead>{t('system_settings.value')}</TableHead>
                  <TableHead>{t('system_settings.type')}</TableHead>
                  <TableHead className="text-end">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell dir="ltr" className="font-mono text-xs">{s.key}</TableCell>
                    <TableCell className="font-medium">{s.label}</TableCell>
                    <TableCell className="max-w-xs truncate text-[var(--color-muted-foreground)]">
                      {s.value ?? '—'}
                    </TableCell>
                    <TableCell>
                      <Badge variant="muted">{s.type}</Badge>
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
          </div>
        ))
      )}

      {editing && <SettingEditDialog setting={editing} onClose={() => setEditing(null)} />}
    </div>
  );
}
