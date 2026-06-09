import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, ArrowLeft, ArrowRight, CheckCircle2, Wand2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { clinicServicesApi } from '@/features/clinic/services/api';
import { useClinicSubClinics } from '@/features/clinic/sub-clinics/hooks';

import { useAssignees } from '../hooks';
import { ACQUISITION_SOURCES } from '../types';
import {
  useImportCommit,
  useImportPreview,
  useImportSources,
  type ImportColumnMap,
  type ImportDefaults,
  type ImportPreviewResult,
  type ServiceResolution,
} from '../importApi';

interface Props {
  onClose: () => void;
}

type Step = 'source' | 'mapping' | 'preview';

const EMPTY_MAP: ImportColumnMap = { customer_name: 'B', customer_phone: 'C', service: '', appointment_at: '', notes: '' };

export function ImportSheetDialog({ onClose }: Props) {
  const { t } = useTranslation();
  const qc = useQueryClient();

  const [step, setStep] = useState<Step>('source');
  const [sheetUrl, setSheetUrl] = useState('');
  const [rowFrom, setRowFrom] = useState(2);
  const [rowTo, setRowTo] = useState(100);
  const [sourceId, setSourceId] = useState<number | null>(null);
  const [map, setMap] = useState<ImportColumnMap>(EMPTY_MAP);
  const [defaults, setDefaults] = useState<ImportDefaults>({ status: 'new', acquisition_source: null });
  const [preview, setPreview] = useState<ImportPreviewResult | null>(null);
  const [resolutions, setResolutions] = useState<Record<string, ServiceResolution>>({});
  const [saveSource, setSaveSource] = useState(false);
  const [sourceName, setSourceName] = useState('');

  const { data: savedSources } = useImportSources();
  const { data: assignees } = useAssignees();
  const { data: subClinics } = useClinicSubClinics();
  const { data: services } = useQuery({
    queryKey: ['clinic', 'services', 'list', { per_page: 100 }],
    queryFn: () => clinicServicesApi.list({ per_page: 100 }),
    staleTime: 60_000,
  });

  const previewMut = useImportPreview();
  const commitMut = useImportCommit();

  function loadSaved(id: number) {
    const s = savedSources?.find((x) => x.id === id);
    if (!s) return;
    setSourceId(id);
    setSheetUrl(s.sheet_url);
    setMap({ ...EMPTY_MAP, ...s.column_map });
    if (s.defaults) setDefaults(s.defaults);
  }

  async function runPreview() {
    if (!sheetUrl.trim() || !map.customer_name.trim() || !map.customer_phone.trim()) {
      toast.error(t('clinic_bookings_kanban.create.missing_fields'));
      return;
    }
    try {
      const result = await previewMut.mutateAsync({
        sheet_url: sheetUrl.trim(),
        column_map: map,
        row_from: rowFrom,
        row_to: rowTo,
        import_source_id: sourceId,
      });
      setPreview(result);
      setResolutions({});
      setStep('preview');
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  const unmatched = useMemo(
    () => (preview?.services ?? []).filter((s) => s.status === 'unmatched'),
    [preview],
  );
  const matchedCount = (preview?.services ?? []).length - unmatched.length;

  // Refs to each unmatched card so we can jump the clinic straight to the
  // next one still missing a decision (instead of hunting visually).
  const cardRefs = useRef<Record<string, HTMLDivElement | null>>({});

  function isResolved(name: string): boolean {
    const r = resolutions[name];
    if (!r) return false;
    if (r.action === 'map') return !!r.service_id;
    if (r.action === 'create') return !!r.sub_clinic_id;
    return false;
  }

  const remaining = unmatched.filter((s) => !isResolved(s.name));
  const allResolved = remaining.length === 0;

  function jumpToNextUnresolved() {
    const next = remaining[0];
    if (!next) return;
    const el = cardRefs.current[next.name];
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el?.classList.add('ring-2', 'ring-rose-400');
    window.setTimeout(() => el?.classList.remove('ring-2', 'ring-rose-400'), 1200);
  }

  function setResolution(name: string, r: ServiceResolution | null) {
    setResolutions((prev) => {
      const next = { ...prev };
      if (r) next[name] = r;
      else delete next[name];
      return next;
    });
  }

  function applyAllSuggestions() {
    setResolutions((prev) => {
      const next = { ...prev };
      for (const s of unmatched) {
        if (s.suggestions[0]) {
          next[s.name] = { name: s.name, action: 'map', service_id: s.suggestions[0].service_id };
        }
      }
      return next;
    });
  }

  async function runCommit() {
    if (!allResolved) {
      toast.error(t('clinic_bookings_kanban.import.unresolved_block'));
      return;
    }
    try {
      const result = await commitMut.mutateAsync({
        sheet_url: sheetUrl.trim(),
        column_map: map,
        row_from: rowFrom,
        row_to: rowTo,
        defaults,
        service_resolutions: Object.values(resolutions),
        save_source: saveSource,
        source_name: saveSource ? sourceName.trim() : undefined,
        import_source_id: sourceId,
      });
      toast.success(
        t('clinic_bookings_kanban.import.success', { imported: result.rows_imported, skipped: result.rows_skipped }),
      );
      qc.invalidateQueries({ queryKey: ['clinic', 'bookings'] });
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  const k = (s: string) => `clinic_bookings_kanban.import.${s}`;

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t(k('title'))}</DialogTitle>
          <DialogDescription>{t(k('subtitle'))}</DialogDescription>
        </DialogHeader>

        {/* ── Step: source ─────────────────────────────────────────── */}
        {step === 'source' && (
          <div className="space-y-4">
            {(savedSources?.length ?? 0) > 0 && (
              <div className="space-y-1.5">
                <Label>{t(k('pick_saved'))}</Label>
                <Select value={sourceId ? String(sourceId) : ''} onChange={(e) => (e.target.value ? loadSaved(Number(e.target.value)) : setSourceId(null))}>
                  <option value="">—</option>
                  {savedSources!.map((s) => (
                    <option key={s.id} value={s.id}>{s.name}</option>
                  ))}
                </Select>
              </div>
            )}
            <div className="space-y-1.5">
              <Label htmlFor="imp-url">{t(k('sheet_url'))}</Label>
              <Input id="imp-url" dir="ltr" value={sheetUrl} onChange={(e) => setSheetUrl(e.target.value)} placeholder="https://docs.google.com/spreadsheets/d/…" />
              <p className="text-xs text-[var(--color-muted-foreground)]">{t(k('sheet_url_hint'))}</p>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="imp-from">{t(k('row_from'))}</Label>
                <Input id="imp-from" type="number" min={1} value={rowFrom} onChange={(e) => setRowFrom(Math.max(1, Number(e.target.value)))} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="imp-to">{t(k('row_to'))}</Label>
                <Input id="imp-to" type="number" min={rowFrom} value={rowTo} onChange={(e) => setRowTo(Number(e.target.value))} />
              </div>
            </div>
          </div>
        )}

        {/* ── Step: mapping ────────────────────────────────────────── */}
        {step === 'mapping' && (
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>{t(k('columns_title'))}</Label>
              {(['customer_name', 'customer_phone', 'service', 'appointment_at', 'notes'] as const).map((field) => (
                <div key={field} className="flex items-center gap-3">
                  <span className="w-32 shrink-0 text-sm">
                    {t(k(`col_${field}`))}
                    {(field === 'customer_name' || field === 'customer_phone') && <span className="text-rose-500"> *</span>}
                  </span>
                  <Input
                    dir="ltr"
                    className="w-24"
                    value={map[field] ?? ''}
                    placeholder={t(k('col_placeholder'))}
                    onChange={(e) => setMap((m) => ({ ...m, [field]: e.target.value.toUpperCase() }))}
                  />
                </div>
              ))}
            </div>

            <div className="grid grid-cols-1 gap-3 border-t border-[var(--color-border)] pt-3 sm:grid-cols-3">
              <div className="space-y-1.5">
                <Label>{t(k('status'))}</Label>
                <Select value={defaults.status ?? 'new'} onChange={(e) => setDefaults((d) => ({ ...d, status: e.target.value as ImportDefaults['status'] }))}>
                  <option value="new">{t('bookings.status.new')}</option>
                  <option value="contacted">{t('bookings.status.contacted')}</option>
                  <option value="appointment_set">{t('bookings.status.appointment_set')}</option>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>{t(k('source'))}</Label>
                <Select value={defaults.acquisition_source ?? ''} onChange={(e) => setDefaults((d) => ({ ...d, acquisition_source: (e.target.value || null) as AcqOrNull }))}>
                  <option value="">—</option>
                  {ACQUISITION_SOURCES.filter((s) => s !== 'other').map((s) => (
                    <option key={s} value={s}>{t(`clinic_bookings_kanban.source.opt.${s}`)}</option>
                  ))}
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>{t(k('assignee'))}</Label>
                <Select
                  value={defaults.assignee_id ? `${defaults.assignee_type}:${defaults.assignee_id}` : ''}
                  onChange={(e) => {
                    if (!e.target.value) { setDefaults((d) => ({ ...d, assignee_type: null, assignee_id: null })); return; }
                    const [type, id] = e.target.value.split(':');
                    setDefaults((d) => ({ ...d, assignee_type: type as ImportDefaults['assignee_type'], assignee_id: Number(id) }));
                  }}
                >
                  <option value="">—</option>
                  {(assignees ?? []).map((a) => (
                    <option key={`${a.type}:${a.id}`} value={`${a.type}:${a.id}`}>{a.name}</option>
                  ))}
                </Select>
              </div>
            </div>
          </div>
        )}

        {/* ── Step: preview + service matching ─────────────────────── */}
        {step === 'preview' && preview && (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3 text-sm">
              <span>{t(k('preview_count'), { count: preview.total })}</span>
              <span className="inline-flex items-center gap-1 text-emerald-600">
                <CheckCircle2 className="h-4 w-4" /> {matchedCount} {t(k('matched'))}
              </span>
            </div>

            {preview.overlap.length > 0 && (
              <div className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                <span>{t(k('overlap_warn'))}</span>
              </div>
            )}

            {unmatched.length > 0 && (
              <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <Label className="text-rose-600">{t(k('unmatched_warn'))} ({unmatched.length})</Label>
                  {unmatched.some((s) => s.suggestions.length > 0) && (
                    <Button variant="outline" size="sm" className="gap-1" onClick={applyAllSuggestions}>
                      <Wand2 className="h-3.5 w-3.5" /> {t(k('apply_all_suggestions'))}
                    </Button>
                  )}
                </div>

                {/* Remaining counter + jump-to-next so the clinic never has to
                    hunt for the service it forgot to resolve. */}
                {remaining.length > 0 ? (
                  <button
                    type="button"
                    onClick={jumpToNextUnresolved}
                    className="flex w-full items-center justify-between gap-2 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-700 transition hover:bg-rose-100"
                  >
                    <span className="inline-flex items-center gap-1.5">
                      <AlertTriangle className="h-4 w-4" /> {t(k('remaining'), { count: remaining.length })}
                    </span>
                    <span className="inline-flex items-center gap-1 font-medium">
                      {t(k('jump_unresolved'))} <ArrowLeft className="h-4 w-4" />
                    </span>
                  </button>
                ) : (
                  <div className="flex items-center gap-1.5 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                    <CheckCircle2 className="h-4 w-4" /> {t(k('all_resolved'))}
                  </div>
                )}

                {unmatched.map((s) => {
                  const r = resolutions[s.name];
                  const action = r?.action ?? '';
                  const resolved = isResolved(s.name);
                  return (
                    <div
                      key={s.name}
                      ref={(el) => { cardRefs.current[s.name] = el; }}
                      className={
                        'space-y-2 rounded-md border p-3 transition ' +
                        (resolved ? 'border-emerald-300 bg-emerald-50/40' : 'border-rose-300 bg-rose-50/30')
                      }
                    >
                      <div className="flex items-center justify-between gap-2">
                        <span className="inline-flex items-center gap-1.5 font-medium">
                          {resolved
                            ? <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                            : <AlertTriangle className="h-4 w-4 text-rose-500" />}
                          «{s.name}»
                        </span>
                        {s.suggestions[0] && (
                          <span className="text-xs text-[var(--color-muted-foreground)]">
                            {t(k('suggestion_label'))} {s.suggestions[0].name} ({s.suggestions[0].score}%)
                          </span>
                        )}
                      </div>

                      <Select
                        value={action}
                        onChange={(e) => {
                          const a = e.target.value;
                          if (a === 'map') setResolution(s.name, { name: s.name, action: 'map', service_id: s.suggestions[0]?.service_id ?? 0 });
                          else if (a === 'create') setResolution(s.name, { name: s.name, action: 'create', sub_clinic_id: 0 });
                          else setResolution(s.name, null);
                        }}
                      >
                        <option value="">—</option>
                        <option value="map">{t(k('action_map'))}</option>
                        <option value="create">{t(k('action_create'))}</option>
                      </Select>

                      {action === 'map' && (
                        <div className="space-y-1">
                          <Select
                            value={r && r.action === 'map' && r.service_id ? String(r.service_id) : ''}
                            onChange={(e) => setResolution(s.name, { name: s.name, action: 'map', service_id: Number(e.target.value) })}
                          >
                            <option value="">{t(k('pick_service'))}</option>
                            {(services?.data ?? []).map((sv) => (
                              <option key={sv.id} value={sv.id}>{sv.name}</option>
                            ))}
                          </Select>
                          <p className="text-xs text-[var(--color-muted-foreground)]">{t(k('map_help'))}</p>
                        </div>
                      )}

                      {action === 'create' && (
                        <div className="space-y-1">
                          <Select
                            value={r && r.action === 'create' && r.sub_clinic_id ? String(r.sub_clinic_id) : ''}
                            onChange={(e) => setResolution(s.name, { name: s.name, action: 'create', sub_clinic_id: Number(e.target.value) })}
                          >
                            <option value="">{t(k('pick_sub_clinic'))}</option>
                            {(subClinics?.data ?? []).map((sc) => (
                              <option key={sc.id} value={sc.id}>{sc.name}</option>
                            ))}
                          </Select>
                          <p className="text-xs text-[var(--color-muted-foreground)]">{t(k('create_help'))}</p>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}

            <div className="space-y-2 border-t border-[var(--color-border)] pt-3">
              <div className="flex items-center gap-2">
                <Switch checked={saveSource} onCheckedChange={setSaveSource} id="imp-save" />
                <Label htmlFor="imp-save" className="cursor-pointer">{t(k('save_source'))}</Label>
              </div>
              {saveSource && (
                <Input value={sourceName} onChange={(e) => setSourceName(e.target.value)} placeholder={t(k('source_name'))} />
              )}
            </div>

            <p className="text-xs text-[var(--color-muted-foreground)]">{t(k('audit_hint'))}</p>
          </div>
        )}

        <DialogFooter className="flex-row justify-between gap-2">
          {step === 'source' && (
            <>
              <Button variant="outline" onClick={onClose}>{t('common.cancel')}</Button>
              <Button className="gap-1" onClick={() => setStep('mapping')} disabled={!sheetUrl.trim()}>
                {t(k('next'))} <ArrowLeft className="h-4 w-4" />
              </Button>
            </>
          )}
          {step === 'mapping' && (
            <>
              <Button variant="outline" className="gap-1" onClick={() => setStep('source')}>
                <ArrowRight className="h-4 w-4" /> {t(k('back'))}
              </Button>
              <Button className="gap-1" onClick={runPreview} disabled={previewMut.isPending}>
                {previewMut.isPending ? t(k('analyzing')) : t(k('analyze'))}
              </Button>
            </>
          )}
          {step === 'preview' && (
            <>
              <Button variant="outline" className="gap-1" onClick={() => setStep('mapping')}>
                <ArrowRight className="h-4 w-4" /> {t(k('back'))}
              </Button>
              <Button onClick={runCommit} disabled={!allResolved || commitMut.isPending}>
                {commitMut.isPending ? t(k('committing')) : t(k('commit'))}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

type AcqOrNull = (typeof ACQUISITION_SOURCES)[number] | null;
