import { useState } from 'react';
import { Eye, EyeOff, MessageCircle, ShieldAlert, AlertTriangle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation, useLocale } from '@/app/providers/LocaleProvider';

import { useAiConversation, useAiConversations } from '../hooks';
import type { AiConversationRow } from '../types';

type StatusFilter = '' | 'normal' | 'blocked' | 'emergency';

export function AiConversationsTab() {
  const { t } = useTranslation();
  const { locale } = useLocale();
  const fmt = new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US');
  const dateFmt = new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', {
    dateStyle: 'short', timeStyle: 'short',
  });

  const [page, setPage] = useState(1);
  const [reveal, setReveal] = useState(false);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [minTurns, setMinTurns] = useState<string>('');
  const [openConv, setOpenConv] = useState<string | null>(null);

  const { data, isLoading } = useAiConversations({
    page,
    per_page: 25,
    status: (statusFilter || undefined) as 'normal' | 'blocked' | 'emergency' | undefined,
    min_turns: minTurns ? Number(minTurns) : undefined,
    reveal,
  });

  const rows: AiConversationRow[] = data?.data ?? [];

  return (
    <div className="space-y-3">
      {/* Toolbar */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex flex-wrap gap-2">
          {(['', 'normal', 'blocked', 'emergency'] as StatusFilter[]).map((s) => (
            <button
              key={s || 'all'}
              type="button"
              onClick={() => { setStatusFilter(s); setPage(1); }}
              className={`inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
                statusFilter === s
                  ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                  : 'border-[var(--color-border)] hover:bg-[var(--color-muted)]'
              }`}
            >
              {s ? t(`ai_center.kind_${s}`, s) : t('ai_center.filter_all', 'الكل')}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2">
          <Input
            type="number"
            min={1}
            max={50}
            placeholder={t('ai_center.min_turns', 'حد أدنى رسائل')}
            value={minTurns}
            onChange={(e) => { setMinTurns(e.target.value); setPage(1); }}
            className="h-8 w-32 text-sm"
          />
        </div>

        <div className="ms-auto flex items-center gap-2">
          <Switch checked={reveal} onCheckedChange={setReveal} />
          <span className="text-xs text-[var(--color-muted-foreground)]">
            {reveal ? <Eye className="me-1 inline h-3 w-3" /> : <EyeOff className="me-1 inline h-3 w-3" />}
            {t('ai_center.reveal_pii', 'كشف البيانات الشخصية')}
          </span>
        </div>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('ai_center.col_when', 'التاريخ')}</TableHead>
            <TableHead>{t('ai_center.col_user', 'المستخدم')}</TableHead>
            <TableHead>{t('ai_center.col_summary', 'ملخّص')}</TableHead>
            <TableHead className="hidden md:table-cell">{t('ai_center.col_clinics', 'مجمعات')}</TableHead>
            <TableHead className="hidden md:table-cell">{t('ai_center.col_categories', 'تخصصات')}</TableHead>
            <TableHead className="w-20 text-center">{t('ai_center.col_turns', 'رسائل')}</TableHead>
            <TableHead className="w-24">{t('ai_center.col_status', 'الحالة')}</TableHead>
            <TableHead />
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : rows.length === 0 ? (
            <TableRow><TableCell colSpan={8} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : rows.map((c) => (
            <TableRow key={c.conversation_id}>
              <TableCell className="whitespace-nowrap text-xs text-[var(--color-muted-foreground)]">
                {dateFmt.format(new Date(c.started_at))}
              </TableCell>
              <TableCell className="whitespace-nowrap text-sm">
                {c.user ? c.user.name : <span className="text-[var(--color-muted-foreground)]">{t('ai_center.visitor', 'زائر')}</span>}
              </TableCell>
              <TableCell className="max-w-md">
                <span className="line-clamp-1 text-sm">{c.summary}</span>
              </TableCell>
              <TableCell className="hidden max-w-[10rem] md:table-cell">
                <div className="flex flex-wrap gap-1">
                  {c.clinics.slice(0, 2).map((cl) => (
                    <Badge key={cl.id} variant="muted" className="text-[10px]">{cl.name}</Badge>
                  ))}
                  {c.clinics.length > 2 && <span className="text-[10px] text-[var(--color-muted-foreground)]">+{c.clinics.length - 2}</span>}
                </div>
              </TableCell>
              <TableCell className="hidden max-w-[10rem] md:table-cell">
                <div className="flex flex-wrap gap-1">
                  {c.categories.slice(0, 2).map((cat) => (
                    <Badge key={cat.id} variant="muted" className="text-[10px]">{cat.name}</Badge>
                  ))}
                  {c.categories.length > 2 && <span className="text-[10px] text-[var(--color-muted-foreground)]">+{c.categories.length - 2}</span>}
                </div>
              </TableCell>
              <TableCell className="text-center">{fmt.format(c.turn_count)}</TableCell>
              <TableCell>
                <StatusBadge status={c.status} />
              </TableCell>
              <TableCell className="text-end">
                <Button variant="ghost" size="sm" onClick={() => setOpenConv(c.conversation_id)}>
                  {t('ai_center.view_thread', 'افتح')}
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {/* Pagination */}
      {data && data.meta.last_page > 1 && (
        <div className="flex items-center justify-between text-xs text-[var(--color-muted-foreground)]">
          <div>
            {t('common.showing_x_of_y', 'عرض {{from}}–{{to}} من {{total}}', {
              from: (data.meta.current_page - 1) * data.meta.per_page + 1,
              to: Math.min(data.meta.current_page * data.meta.per_page, data.meta.total),
              total: data.meta.total,
            })}
          </div>
          <div className="flex gap-1">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
              {t('common.previous', 'السابق')}
            </Button>
            <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage((p) => p + 1)}>
              {t('common.next', 'التالي')}
            </Button>
          </div>
        </div>
      )}

      {openConv && (
        <ConversationThreadDialog conversationId={openConv} reveal={reveal} onClose={() => setOpenConv(null)} />
      )}
    </div>
  );
}

function StatusBadge({ status }: { status: 'normal' | 'blocked' | 'emergency' }) {
  const { t } = useTranslation();
  if (status === 'emergency') {
    return <Badge className="bg-red-50 text-red-700"><AlertTriangle className="me-1 inline h-3 w-3" />{t('ai_center.kind_emergency', 'طوارئ')}</Badge>;
  }
  if (status === 'blocked') {
    return <Badge className="bg-amber-50 text-amber-700"><ShieldAlert className="me-1 inline h-3 w-3" />{t('ai_center.kind_blocked', 'مرفوض')}</Badge>;
  }
  return <Badge className="bg-emerald-50 text-emerald-700"><MessageCircle className="me-1 inline h-3 w-3" />{t('ai_center.kind_normal', 'عادي')}</Badge>;
}

function ConversationThreadDialog({ conversationId, reveal, onClose }: { conversationId: string; reveal: boolean; onClose: () => void }) {
  const { t } = useTranslation();
  const { data: turns, isLoading } = useAiConversation(conversationId, reveal);

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-h-[85vh] max-w-3xl overflow-hidden">
        <DialogHeader>
          <DialogTitle>{t('ai_center.thread_title', 'المحادثة كاملة')}</DialogTitle>
          <DialogDescription>
            <span className="font-mono text-[11px]">{conversationId}</span>
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] space-y-4 overflow-auto pe-2">
          {isLoading && <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.loading')}</div>}
          {!isLoading && (!turns || turns.length === 0) && (
            <div className="py-8 text-center text-sm text-[var(--color-muted-foreground)]">{t('common.no_data')}</div>
          )}
          {turns?.map((turn) => (
            <div key={turn.id} className="rounded-md border border-[var(--color-border)] p-3">
              <div className="mb-2 flex items-center justify-between text-[11px] text-[var(--color-muted-foreground)]">
                <span>{new Date(turn.created_at).toLocaleString()}</span>
                <div className="flex items-center gap-2">
                  {turn.was_emergency && <Badge className="bg-red-50 text-red-700 text-[10px]">{t('ai_center.kind_emergency', 'طوارئ')}</Badge>}
                  {turn.was_blocked && <Badge className="bg-amber-50 text-amber-700 text-[10px]">{t('ai_center.kind_blocked', 'مرفوض')}</Badge>}
                  <span>{turn.provider} · {turn.response_ms ?? '?'} ms</span>
                </div>
              </div>
              <div className="space-y-2">
                <div>
                  <div className="text-[11px] font-semibold uppercase text-[var(--color-muted-foreground)]">
                    {turn.user ? turn.user.name : t('ai_center.visitor', 'زائر')}
                  </div>
                  <div className="text-sm">{turn.query}</div>
                </div>
                <div>
                  <div className="text-[11px] font-semibold uppercase text-sky-700">{t('ai_center.assistant', 'المساعد')}</div>
                  <div className="text-sm">{turn.reply}</div>
                </div>
                {(turn.clinics.length > 0 || turn.categories.length > 0) && (
                  <div className="flex flex-wrap gap-1 pt-1">
                    {turn.clinics.map((c) => (
                      <Badge key={'c' + c.id} variant="muted" className="text-[10px]">🏥 {c.name}</Badge>
                    ))}
                    {turn.categories.map((c) => (
                      <Badge key={'cat' + c.id} variant="muted" className="text-[10px]">🏷️ {c.name}</Badge>
                    ))}
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </DialogContent>
    </Dialog>
  );
}
