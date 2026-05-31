import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowUpFromLine, Sparkles, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { MultiCategorySelect } from '@/features/clinic/services/components/MultiCategorySelect';
import { useClinicServices } from '@/features/clinic/services/hooks';
import { useCategoryLookup } from '@/features/lookups/hooks';

import { importServicesApi, type ColumnAnalysis, type DraftService, type DraftOffer } from '../api';

const ALLOWED_FIELDS: Array<string | null> = [null, 'name', 'price', 'old_price', 'description'];

/** Review-table rows carry a local `_sel` (ticked?) flag; offers also carry an
 *  editable end date the AI can't know from text. Neither is sent verbatim. */
type ServiceRow = DraftService & { _sel: boolean };
type OfferRow = DraftOffer & { _sel: boolean; ends_at: string };

/** Default offer window end — 30 days out, as YYYY-MM-DD for a date input. */
function defaultEndsAt(): string {
  const d = new Date();
  d.setDate(d.getDate() + 30);
  return d.toISOString().slice(0, 10);
}

export function ImportServicesPage() {
  const { t } = useTranslation();
  const qc = useQueryClient();

  // --- CSV path (unchanged) -------------------------------------------------
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
    setFile(e.target.files?.[0] ?? null);
  };
  const onAnalyze = () => {
    if (!file) { toast.error(t('import_services.no_file')); return; }
    analyze.mutate(file);
  };
  const hasMapping = Object.values(mapping).some((v) => v !== null && v !== '');

  // --- Free-text → AI extraction path ---------------------------------------
  const [text, setText] = useState('');
  const [svcRows, setSvcRows] = useState<ServiceRow[]>([]);
  const [offerRows, setOfferRows] = useState<OfferRow[]>([]);
  const extracted = svcRows.length > 0 || offerRows.length > 0;

  // The offer "linked service" dropdown lists the clinic's existing services;
  // the service category picker needs the active specialty lookup.
  const { data: clinicServices } = useClinicServices({ per_page: 200 });
  const { data: categories } = useCategoryLookup();

  const extract = useMutation({
    mutationFn: (input: string) => importServicesApi.extractText(input),
    onSuccess: (catalog) => {
      setSvcRows(catalog.services.map((s) => ({ ...s, _sel: true })));
      setOfferRows(catalog.offers.map((o) => ({ ...o, _sel: true, ends_at: defaultEndsAt() })));
      toast.success(
        t('import_services.extracted', {
          services: catalog.services.length,
          offers: catalog.offers.length,
        }),
      );
    },
    onError: (err) => toast.error(extractMessage(err, t('errors.generic'))),
  });

  const confirm = useMutation({
    mutationFn: () =>
      importServicesApi.confirm({
        services: svcRows
          .filter((r) => r._sel && r.name.trim())
          .map(({ _sel, ...s }) => s),
        offers: offerRows
          .filter((r) => r._sel && r.title.trim())
          .map(({ _sel, ...o }) => o),
      }),
    onSuccess: (res) => {
      toast.success(res.message);
      setText('');
      setSvcRows([]);
      setOfferRows([]);
      qc.invalidateQueries({ queryKey: ['clinic', 'services'] });
      qc.invalidateQueries({ queryKey: ['clinic', 'offers'] });
    },
    onError: (err) => toast.error(extractMessage(err, t('errors.generic'))),
  });

  const patchSvc = (idx: number, patch: Partial<ServiceRow>) =>
    setSvcRows((prev) => prev.map((r, i) => (i === idx ? { ...r, ...patch } : r)));
  const patchOffer = (idx: number, patch: Partial<OfferRow>) =>
    setOfferRows((prev) => prev.map((r, i) => (i === idx ? { ...r, ...patch } : r)));

  const selectedCount =
    svcRows.filter((r) => r._sel).length + offerRows.filter((r) => r._sel).length;

  return (
    <div className="max-w-5xl space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('import_services.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('import_services.subtitle')}</p>
      </div>

      {/* ---- Free-text → AI extraction (services + offers) ------------------ */}
      <div className="rounded-lg border border-[var(--color-border)] bg-white p-6 space-y-4">
        <div>
          <h2 className="text-lg font-semibold">{t('import_services.text_title')}</h2>
          <p className="text-xs text-[var(--color-muted-foreground)]">{t('import_services.text_hint')}</p>
        </div>

        <Textarea
          rows={6}
          value={text}
          onChange={(e) => setText(e.target.value)}
          placeholder={t('import_services.text_placeholder')}
        />

        <Button
          onClick={() => (text.trim() ? extract.mutate(text) : toast.error(t('import_services.text_empty')))}
          disabled={extract.isPending}
        >
          <Sparkles className="h-4 w-4" />
          {extract.isPending ? t('common.loading') : t('import_services.extract')}
        </Button>

        {extracted && (
          <div className="space-y-6">
            {/* Services review table */}
            {svcRows.length > 0 && (
              <section className="space-y-2">
                <div className="flex items-center justify-between">
                  <h3 className="font-semibold">{t('import_services.review_services')}</h3>
                  <label className="flex items-center gap-2 text-xs text-[var(--color-muted-foreground)]">
                    <input
                      type="checkbox"
                      checked={svcRows.every((r) => r._sel)}
                      onChange={(e) => setSvcRows((prev) => prev.map((r) => ({ ...r, _sel: e.target.checked })))}
                    />
                    {t('import_services.select_all')}
                  </label>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-10" />
                      <TableHead>{t('import_services.fields.name')}</TableHead>
                      <TableHead>{t('import_services.fields.description')}</TableHead>
                      <TableHead className="w-28">{t('import_services.fields.price')}</TableHead>
                      <TableHead className="w-56">{t('import_services.category')}</TableHead>
                      <TableHead className="w-10" />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {svcRows.map((r, idx) => (
                      <TableRow key={idx} className={r._sel ? '' : 'opacity-50'}>
                        <TableCell>
                          <input
                            type="checkbox"
                            checked={r._sel}
                            onChange={(e) => patchSvc(idx, { _sel: e.target.checked })}
                          />
                        </TableCell>
                        <TableCell>
                          <Input value={r.name} onChange={(e) => patchSvc(idx, { name: e.target.value })} />
                        </TableCell>
                        <TableCell>
                          <Input
                            value={r.description ?? ''}
                            onChange={(e) => patchSvc(idx, { description: e.target.value || null })}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            value={r.price ?? ''}
                            onChange={(e) =>
                              patchSvc(idx, { price: e.target.value === '' ? null : Number(e.target.value) })
                            }
                          />
                        </TableCell>
                        <TableCell>
                          <MultiCategorySelect
                            value={r.category_ids}
                            onChange={(ids) => patchSvc(idx, { category_ids: ids })}
                            categories={categories}
                          />
                        </TableCell>
                        <TableCell>
                          <button
                            type="button"
                            className="text-[var(--color-muted-foreground)] hover:text-red-600"
                            onClick={() => setSvcRows((prev) => prev.filter((_, i) => i !== idx))}
                          >
                            <X className="h-4 w-4" />
                          </button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </section>
            )}

            {/* Offers review table */}
            {offerRows.length > 0 && (
              <section className="space-y-2">
                <div className="flex items-center justify-between">
                  <h3 className="font-semibold">{t('import_services.review_offers')}</h3>
                  <label className="flex items-center gap-2 text-xs text-[var(--color-muted-foreground)]">
                    <input
                      type="checkbox"
                      checked={offerRows.every((r) => r._sel)}
                      onChange={(e) => setOfferRows((prev) => prev.map((r) => ({ ...r, _sel: e.target.checked })))}
                    />
                    {t('import_services.select_all')}
                  </label>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-10" />
                      <TableHead>{t('import_services.offer_title')}</TableHead>
                      <TableHead className="w-24">{t('import_services.fields.old_price')}</TableHead>
                      <TableHead className="w-24">{t('import_services.fields.price')}</TableHead>
                      <TableHead className="w-48">{t('import_services.linked_service')}</TableHead>
                      <TableHead className="w-40">{t('import_services.ends_at')}</TableHead>
                      <TableHead className="w-10" />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {offerRows.map((r, idx) => (
                      <TableRow key={idx} className={r._sel ? '' : 'opacity-50'}>
                        <TableCell>
                          <input
                            type="checkbox"
                            checked={r._sel}
                            onChange={(e) => patchOffer(idx, { _sel: e.target.checked })}
                          />
                        </TableCell>
                        <TableCell>
                          <Input value={r.title} onChange={(e) => patchOffer(idx, { title: e.target.value })} />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            value={r.old_price ?? ''}
                            onChange={(e) =>
                              patchOffer(idx, { old_price: e.target.value === '' ? null : Number(e.target.value) })
                            }
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            value={r.price ?? ''}
                            onChange={(e) =>
                              patchOffer(idx, { price: e.target.value === '' ? null : Number(e.target.value) })
                            }
                          />
                        </TableCell>
                        <TableCell>
                          <Select
                            value={r.service_id ?? ''}
                            onChange={(e) => {
                              const id = e.target.value ? Number(e.target.value) : null;
                              patchOffer(idx, { service_id: id, type: id ? 'service' : 'general' });
                            }}
                          >
                            <option value="">{t('import_services.offer_general')}</option>
                            {clinicServices?.data.map((s) => (
                              <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                          </Select>
                        </TableCell>
                        <TableCell>
                          <Input
                            type="date"
                            value={r.ends_at}
                            onChange={(e) => patchOffer(idx, { ends_at: e.target.value })}
                          />
                        </TableCell>
                        <TableCell>
                          <button
                            type="button"
                            className="text-[var(--color-muted-foreground)] hover:text-red-600"
                            onClick={() => setOfferRows((prev) => prev.filter((_, i) => i !== idx))}
                          >
                            <X className="h-4 w-4" />
                          </button>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </section>
            )}

            <Button onClick={() => confirm.mutate()} disabled={confirm.isPending || selectedCount === 0}>
              <ArrowUpFromLine className="h-4 w-4" />
              {confirm.isPending
                ? t('common.loading')
                : t('import_services.confirm_selected', { count: selectedCount })}
            </Button>
          </div>
        )}
      </div>

      {/* ---- CSV upload + column mapping (unchanged) ------------------------ */}
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

          <Button onClick={() => execute.mutate()} disabled={!hasMapping || execute.isPending}>
            <ArrowUpFromLine className="h-4 w-4" />
            {execute.isPending ? t('common.loading') : t('import_services.execute')}
          </Button>
        </div>
      )}
    </div>
  );
}
