import { useState } from 'react';
import { Phone, MessageCircle, Pencil, Save, X, Crown, Repeat, Sparkles, AlertOctagon } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/app/providers/AuthProvider';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useUpdateCustomer } from '../hooks';
import type { CustomerProfile } from '../types';

interface Props {
  customer: CustomerProfile;
}

function waLink(phone: string) {
  const digits = phone.replace(/[^\d]/g, '');
  return `https://wa.me/${digits.startsWith('0') ? '966' + digits.slice(1) : digits}`;
}

export function CustomerProfileHeader({ customer }: Props) {
  const { t } = useTranslation();
  const canManage = useCan('customers.manage');
  const mut = useUpdateCustomer(customer.id);

  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(customer.name);
  const [email, setEmail] = useState(customer.email ?? '');

  async function save() {
    try {
      await mut.mutateAsync({ name: name.trim() || undefined, email: email.trim() || null });
      toast.success(t('clinic_customers.profile.saved'));
      setEditing(false);
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  }

  function cancel() {
    setName(customer.name);
    setEmail(customer.email ?? '');
    setEditing(false);
  }

  const badges: any[] = [];
  if (customer.auto_tags.is_vip)              badges.push({ Icon: Crown,        label: t('clinic_bookings_kanban.tag.vip'),        variant: 'gold' });
  if (customer.auto_tags.is_repeat)           badges.push({ Icon: Repeat,       label: t('clinic_bookings_kanban.tag.repeat'),     variant: 'info' });
  if (customer.auto_tags.is_new)              badges.push({ Icon: Sparkles,     label: t('clinic_bookings_kanban.tag.new'),        variant: 'default' });
  if (customer.auto_tags.has_prior_complaint) badges.push({ Icon: AlertOctagon, label: t('clinic_bookings_kanban.tag.complaint'),  variant: 'danger' });

  return (
    <div className="rounded-lg border border-[var(--color-border)] bg-white p-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-2">
          {editing ? (
            <div className="flex flex-col gap-2">
              <Input value={name} onChange={(e) => setName(e.target.value)} className="text-lg font-semibold" />
              <Input value={email} onChange={(e) => setEmail(e.target.value)} placeholder="email@example.com" />
              <div className="flex gap-1">
                <Button size="sm" onClick={save} disabled={mut.isPending} className="h-7 gap-1 text-xs">
                  <Save className="h-3 w-3" />{t('common.save')}
                </Button>
                <Button size="sm" variant="ghost" onClick={cancel} className="h-7 gap-1 text-xs">
                  <X className="h-3 w-3" />{t('common.cancel')}
                </Button>
              </div>
            </div>
          ) : (
            <>
              <div className="flex items-center gap-2">
                <h1 className="text-2xl font-semibold">{customer.name}</h1>
                {canManage && (
                  <button type="button" onClick={() => setEditing(true)} className="rounded p-0.5 text-[var(--color-muted-foreground)] hover:bg-[var(--color-muted)]">
                    <Pencil className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-2 text-sm">
                <span dir="ltr" className="font-mono text-[var(--color-muted-foreground)]">{customer.phone}</span>
                {customer.email && <span className="text-[var(--color-muted-foreground)]">· {customer.email}</span>}
              </div>
              <div className="flex flex-wrap gap-1.5">
                {badges.map(({ Icon, label, variant }, i) => (
                  <Badge key={i} variant={variant as any} className="gap-1">
                    <Icon className="h-3 w-3" />
                    {label}
                  </Badge>
                ))}
                {customer.tags.map((tag) => (
                  <Badge key={tag.id} variant="muted" className="bg-slate-100 text-slate-700">{tag.label}</Badge>
                ))}
              </div>
            </>
          )}
        </div>

        {!editing && (
          <div className="flex shrink-0 items-center gap-2">
            <a href={`tel:${customer.phone}`} className="inline-flex items-center gap-1.5 rounded-md border border-[var(--color-border)] px-3 py-2 text-sm hover:bg-[var(--color-muted)]">
              <Phone className="h-4 w-4" />{t('outreach.call')}
            </a>
            <a href={waLink(customer.phone)} target="_blank" rel="noopener" className="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50">
              <MessageCircle className="h-4 w-4" />{t('outreach.whatsapp')}
            </a>
          </div>
        )}
      </div>
    </div>
  );
}
