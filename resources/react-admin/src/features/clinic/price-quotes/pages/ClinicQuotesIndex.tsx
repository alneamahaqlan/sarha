import { useState } from 'react';
import { toast } from 'sonner';
import { MessageSquareReply, Search } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { useDebouncedValue } from '@/lib/use-debounced-value';
import { OutreachButtons } from '@/features/clinic/outreach/OutreachButtons';

import { useClinicQuotes, useReplyClinicQuote } from '../hooks';
import type { BroadcastQuote, ClinicQuoteFilter } from '../api';

function ReplyDialog({ quote, onClose }: { quote: BroadcastQuote; onClose: () => void }) {
  const { t } = useTranslation();
  const reply = useReplyClinicQuote(quote.id);
  const [body, setBody] = useState(quote.my_reply?.body ?? '');
  const [price, setPrice] = useState<string>(quote.my_reply?.price != null ? String(quote.my_reply.price) : '');
  const [isPublic, setIsPublic] = useState(quote.my_reply?.is_public ?? false);

  const submit = async () => {
    if (!body.trim()) return;
    try {
      await reply.mutateAsync({ body: body.trim(), price: price === '' ? null : Number(price), is_public: isPublic });
      toast.success(t('clinic_quotes.reply_saved'));
      onClose();
    } catch (err) {
      toast.error(extractMessage(err, t('errors.generic')));
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('clinic_quotes.reply_title')}</DialogTitle>
          <DialogDescription className="sr-only">{quote.service_name}</DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          <div className="rounded-lg bg-[var(--color-muted)]/40 p-3 text-sm">
            <p className="font-semibold">{quote.service_name}</p>
            <p className="mt-1 text-[var(--color-muted-foreground)]">{quote.description}</p>
            <div className="mt-2 flex flex-wrap gap-1">
              {quote.cities.map((c) => <Badge key={c.id} variant="muted" className="text-[10px]">{c.name}</Badge>)}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="body">{t('clinic_quotes.reply_body')}</Label>
            <Textarea id="body" rows={4} maxLength={2000} value={body} onChange={(e) => setBody(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="price">{t('clinic_quotes.reply_price')}</Label>
            <Input id="price" type="number" min={0} step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} />
          </div>
          <div className="flex items-center gap-3">
            <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            <div>
              <Label>{t('clinic_quotes.reply_public')}</Label>
              <p className="text-xs text-[var(--color-muted-foreground)]">{t('clinic_quotes.reply_public_hint')}</p>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose} disabled={reply.isPending}>{t('common.cancel')}</Button>
          <Button type="button" onClick={submit} disabled={reply.isPending || !body.trim()}>
            {reply.isPending ? t('common.loading') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function ClinicQuotesIndex() {
  const { t } = useTranslation();
  const [search, setSearch] = useState('');
  const debounced = useDebouncedValue(search, 300);
  const [filter, setFilter] = useState<ClinicQuoteFilter | ''>('');
  const { data, isLoading } = useClinicQuotes({ search: debounced || undefined, filter: filter ? { status: filter } : undefined });
  const [replying, setReplying] = useState<BroadcastQuote | null>(null);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-2xl font-semibold">{t('clinic_quotes.title')}</h1>
        <p className="text-sm text-[var(--color-muted-foreground)]">{t('clinic_quotes.broadcast_subtitle')}</p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative">
          <Search className="absolute start-2.5 top-2.5 h-4 w-4 text-[var(--color-muted-foreground)]" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('common.search')} className="ps-8 w-64" />
        </div>
        {(['', 'pending', 'replied'] as const).map((f) => (
          <button key={f || 'all'} type="button" onClick={() => setFilter(f)}
            className={`rounded-md px-3 py-1.5 text-sm ${filter === f ? 'bg-[var(--color-primary)] text-white' : 'hover:bg-[var(--color-muted)]'}`}>
            {f ? t(`clinic_quotes.filter_${f}`) : t('common.all')}
          </button>
        ))}
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('clinic_quotes.service')}</TableHead>
            <TableHead>{t('clinic_quotes.customer')}</TableHead>
            <TableHead>{t('clinic_quotes.cities')}</TableHead>
            <TableHead>{t('clinic_quotes.my_reply')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.loading')}</TableCell></TableRow>
          ) : !data || data.data.length === 0 ? (
            <TableRow><TableCell colSpan={5} className="py-8 text-center text-[var(--color-muted-foreground)]">{t('common.no_data')}</TableCell></TableRow>
          ) : (
            data.data.map((q) => (
              <TableRow key={q.id}>
                <TableCell className="font-medium">
                  {q.service_name}
                  <p className="text-xs text-[var(--color-muted-foreground)] max-w-xs truncate">{q.description}</p>
                </TableCell>
                <TableCell className="text-sm">
                  {q.customer_name}
                  <span className="block text-xs text-[var(--color-muted-foreground)]" dir="ltr">{q.customer_phone}</span>
                </TableCell>
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    {q.cities.map((c) => <Badge key={c.id} variant="muted" className="text-[10px]">{c.name}</Badge>)}
                  </div>
                </TableCell>
                <TableCell>
                  {q.my_reply ? (
                    <Badge variant={q.my_reply.is_public ? 'success' : 'muted'}>
                      {q.my_reply.is_public ? t('clinic_quotes.replied_public') : t('clinic_quotes.replied_private')}
                    </Badge>
                  ) : (
                    <Badge variant="warning">{t('clinic_quotes.not_replied')}</Badge>
                  )}
                </TableCell>
                <TableCell className="text-end">
                  <div className="flex items-center justify-end gap-1">
                    <OutreachButtons phone={q.customer_phone} context="quote" refId={q.id} />
                    <Button variant="ghost" size="sm" onClick={() => setReplying(q)}>
                      <MessageSquareReply className="h-4 w-4" />
                      {q.my_reply ? t('clinic_quotes.edit_reply') : t('clinic_quotes.reply')}
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {replying && <ReplyDialog quote={replying} onClose={() => setReplying(null)} />}
    </div>
  );
}
