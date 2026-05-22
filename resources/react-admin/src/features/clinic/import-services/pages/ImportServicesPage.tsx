import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowUpFromLine, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { importServicesApi, type ColumnAnalysis } from '../api';

const ALLOWED_FIELDS: Array<string | null> = [null, 'name', 'price', 'old_price', 'description'];

export function ImportServicesPage() {
  const { t } = useTranslation();
  const qc = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [headers, setHeaders] = useState<string[]>([]);
  const [rows, setRows] = useState<string[][]>([]);
  const [analysis, setAnalysis] = useState<ColumnAnalysis[]>([]);
  const [mapping, setMapping] = useState<Record<number, string | null>>({});

  const analyze = useMutation({
    mutationFn: (f: File) => importServicesApi.analyze(f),
    onSuccess: (data) => {
      setHeaders(data.headers);
      setRows(data.rows);
      setAnalysis(data.analysis);
      // Pre-populate mapping from analysis where confidence >= 50 (matches Filament rule).
      const initial: Record<number, string | null> = {};
      data.analysis.forEach((a, idx) => {
        initial[idx] = a.mapped_to && a.confidence >= 50 ? a.mapped_to : null;
      });
      setMapping(initial);
      toast.success(t('import_services.analyzed'));
    },
    onError: (err) => toast.error(extractMessage(err, t('errors.generic'))),
  });

  const execute = useMutation({
    mutationFn: () => importServicesApi.execute({ headers, rows, mapping }),
    onSuccess: (res) => {
      toast.success(res.message);
      setFile(null);
      setHeaders([]);
      setRows([]);
      setAnalysis([]);
      setMapping({});
      qc.invalidateQueries({ queryKey: ['clinic', 'services'] });
    },
    onError: (err) => toast.error(extractMessage(err, t('errors.generic'))),
  });

  const onUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0] ?? null;
    setFile(f);
  };

  const onAnalyze = () => {
    if (!file) { toast.error(t('import_services.no_file')); return; }
    analyze.mutate(file);
  };

  const hasMapping = Object.values(mapping).some((v) => v !== null && v !== '');

  return (
    <div className="max-w-4xl space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('import_services.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('import_services.subtitle')}</p>
      </div>

      <div className="rounded-lg border border-[var(--color-border)] bg-white p-6">
        <div className="space-y-1.5">
          <Label htmlFor="file">{t('import_services.file')}</Label>
          <Input id="file" type="file" accept=".csv,text/csv" onChange={onUpload} />
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('import_services.file_hint')}</p>
        </div>
        <div className="mt-4">
          <Button onClick={onAnalyze} disabled={!file || analyze.isPending}>
            <Sparkles className="h-4 w-4" />
            {analyze.isPending ? t('common.loading') : t('import_services.analyze')}
          </Button>
        </div>
      </div>

      {analysis.length > 0 && (
        <div className="rounded-lg border border-[var(--color-border)] bg-white p-6 space-y-4">
          <div>
            <h2 className="text-lg font-semibold">{t('import_services.mapping')}</h2>
            <p className="text-xs text-[var(--color-muted-foreground)]">{t('import_services.mapping_hint')}</p>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('import_services.column')}</TableHead>
                <TableHead>{t('import_services.suggestion')}</TableHead>
                <TableHead>{t('import_services.confidence')}</TableHead>
                <TableHead>{t('import_services.map_to')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {analysis.map((a, idx) => (
                <TableRow key={idx}>
                  <TableCell className="font-medium">{a.column}</TableCell>
                  <TableCell className="text-[var(--color-muted-foreground)]">{a.mapped_to ?? '—'}</TableCell>
                  <TableCell>
                    <Badge variant={a.confidence >= 70 ? 'success' : a.confidence >= 50 ? 'warning' : 'muted'}>
                      {a.confidence}%
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <Select
                      value={mapping[idx] ?? ''}
                      onChange={(e) => setMapping({ ...mapping, [idx]: e.target.value || null })}
                      className="w-44"
                    >
                      {ALLOWED_FIELDS.map((f) => (
                        <option key={f ?? 'none'} value={f ?? ''}>
                          {f ? t(`import_services.fields.${f}`) : t('import_services.fields.none')}
                        </option>
                      ))}
                    </Select>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className="text-xs text-[var(--color-muted-foreground)]">
            {t('import_services.rows_count', { count: rows.length })}
          </div>

          <Button onClick={() => execute.mutate()} disabled={!hasMapping || execute.isPending}>
            <ArrowUpFromLine className="h-4 w-4" />
            {execute.isPending ? t('common.loading') : t('import_services.execute')}
          </Button>
        </div>
      )}
    </div>
  );
}
