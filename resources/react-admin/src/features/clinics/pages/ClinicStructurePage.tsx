import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { ArrowLeft, ChevronDown, ChevronRight, Stethoscope, Tag } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { useClinicStructure } from '../hooks';
import type { StructureService, StructureSubClinic } from '../api/clinics.api';

function ServiceRow({ service }: { service: StructureService }) {
  const { t } = useTranslation();
  return (
    <div className="flex items-center justify-between gap-3 py-2 px-3 rounded-md hover:bg-[var(--color-muted)]/30">
      <div className="flex items-center gap-2 min-w-0">
        <span className={`h-1.5 w-1.5 rounded-full ${service.is_active ? 'bg-emerald-500' : 'bg-gray-300'}`} />
        <span className={`truncate ${service.is_active ? '' : 'text-[var(--color-muted-foreground)] line-through'}`}>
          {service.name}
        </span>
      </div>
      <div className="text-xs text-[var(--color-muted-foreground)] whitespace-nowrap">
        {service.price != null ? (
          <>
            {service.old_price != null && (
              <span className="line-through me-1 text-[10px] opacity-60">{service.old_price.toLocaleString()}</span>
            )}
            <span className="text-[var(--color-foreground)] font-medium">{service.price.toLocaleString()}</span>{' '}
            <span className="text-[10px]">{t('common.sar')}</span>
          </>
        ) : (
          <span className="italic">{t('clinics.structure.no_price')}</span>
        )}
      </div>
    </div>
  );
}

function SubClinicCard({ sub }: { sub: StructureSubClinic }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(true);
  const Chevron = open ? ChevronDown : ChevronRight;

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-card)]">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full flex items-center justify-between gap-3 px-4 py-3 text-start hover:bg-[var(--color-muted)]/30 rounded-t-lg"
      >
        <div className="flex items-center gap-3 min-w-0">
          <Chevron className="h-4 w-4 text-[var(--color-muted-foreground)] flex-shrink-0 rtl:rotate-180 rtl:data-[open=true]:rotate-0" />
          <Stethoscope className="h-4 w-4 text-[var(--color-primary)] flex-shrink-0" />
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-medium truncate">{sub.name}</span>
              {sub.name_en && (
                <span className="text-xs text-[var(--color-muted-foreground)]" dir="ltr">({sub.name_en})</span>
              )}
              {!sub.is_active && (
                <Badge variant="muted" className="text-[10px]">{t('common.inactive')}</Badge>
              )}
            </div>
            {sub.category && (
              <p className="text-xs text-[var(--color-muted-foreground)] mt-0.5 flex items-center gap-1">
                <Tag className="h-3 w-3" />
                {sub.category.emoji && <span>{sub.category.emoji}</span>}
                {sub.category.name}
              </p>
            )}
          </div>
        </div>
        <Badge variant="muted" className="flex-shrink-0">
          {t('clinics.structure.services_count', { count: sub.services_count })}
        </Badge>
      </button>

      {open && (
        <div className="border-t border-[var(--color-border)] p-2">
          {sub.services.length === 0 ? (
            <p className="text-sm text-[var(--color-muted-foreground)] py-4 text-center">
              {t('clinics.structure.no_services_in_sub_clinic')}
            </p>
          ) : (
            <div className="divide-y divide-[var(--color-border)]/40">
              {sub.services.map((s) => <ServiceRow key={s.id} service={s} />)}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export function ClinicStructurePage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const clinicId = id ? Number(id) : undefined;
  const { data, isLoading } = useClinicStructure(clinicId);

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3">
        <Link
          to="/admin/clinics"
          className="inline-flex h-8 w-8 items-center justify-center rounded-md border border-[var(--color-border)] hover:bg-[var(--color-muted)]"
          aria-label={t('common.back')}
        >
          <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
        </Link>
        <div className="min-w-0">
          <h1 className="text-2xl font-semibold truncate">{data?.clinic.name ?? t('clinics.structure.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinics.structure.subtitle')}</p>
        </div>
      </div>

      {isLoading || !data ? (
        <div className="py-12 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>
      ) : (
        <>
          {/* Summary chips */}
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="muted">
              {t('clinics.structure.sub_clinics_count', { count: data.totals.sub_clinics })}
            </Badge>
            <Badge variant="muted">
              {t('clinics.structure.total_services', { count: data.totals.services })}
            </Badge>
          </div>

          {/* Sub-clinics */}
          {data.sub_clinics.length === 0 && data.general_services.length === 0 ? (
            <div className="rounded-lg border border-dashed border-[var(--color-border)] py-12 text-center text-sm text-[var(--color-muted-foreground)]">
              {t('clinics.structure.empty')}
            </div>
          ) : (
            <div className="space-y-3">
              {data.sub_clinics.map((sub) => <SubClinicCard key={sub.id} sub={sub} />)}

              {data.general_services.length > 0 && (
                <div className="rounded-lg border border-[var(--color-border)] bg-[var(--color-card)]">
                  <div className="flex items-center justify-between gap-3 px-4 py-3 border-b border-[var(--color-border)]">
                    <div>
                      <p className="font-medium">{t('clinics.structure.general_services')}</p>
                      <p className="text-xs text-[var(--color-muted-foreground)] mt-0.5">
                        {t('clinics.structure.general_services_hint')}
                      </p>
                    </div>
                    <Badge variant="muted" className="flex-shrink-0">
                      {t('clinics.structure.services_count', { count: data.general_services.length })}
                    </Badge>
                  </div>
                  <div className="p-2 divide-y divide-[var(--color-border)]/40">
                    {data.general_services.map((s) => <ServiceRow key={s.id} service={s} />)}
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
