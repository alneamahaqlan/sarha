import { useState } from 'react';
import { Check, Plus, Trash2, X, Pencil, Send, ShieldAlert } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';

import { useWhatsAppSenders, useDeleteWhatsAppSender, useTestWhatsAppSender } from '../hooks';
import { WhatsAppSenderForm } from '../components/WhatsAppSenderForm';
import type { WhatsAppSender } from '../types';

const MAX_SENDERS = 5;
const FAILURE_THRESHOLD = 5;

export function WhatsAppSendersIndex() {
  const { t } = useTranslation();
  const { data, isLoading } = useWhatsAppSenders();
  const del = useDeleteWhatsAppSender();
  const test = useTestWhatsAppSender();

  const [editing, setEditing] = useState<WhatsAppSender | null>(null);
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState<WhatsAppSender | null>(null);
  const [testing, setTesting] = useState<WhatsAppSender | null>(null);
  const [testPhone, setTestPhone] = useState('');

  const senders = data ?? [];
  const atCap = senders.length >= MAX_SENDERS;

  const handleDelete = async () => {
    if (!deleting) return;
    try {
      await del.mutateAsync(deleting.id);
      toast.success(t('whatsapp_senders.deleted'));
      setDeleting(null);
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  const handleTest = async () => {
    if (!testing) return;
    try {
      const res = await test.mutateAsync({ id: testing.id, phone: testPhone });
      res.sent ? toast.success(res.message) : toast.error(res.message);
      if (res.sent) {
        setTesting(null);
        setTestPhone('');
      }
    } catch (e) {
      toast.error(extractMessage(e, t('errors.generic')));
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div>
          <h1 className="text-2xl font-semibold">{t('whatsapp_senders.title')}</h1>
          <p className="text-sm text-[var(--color-muted-foreground)]">{t('whatsapp_senders.subtitle')}</p>
        </div>
        <Button onClick={() => setCreating(true)} disabled={atCap} title={atCap ? t('whatsapp_senders.max_reached') : undefined}>
          <Plus className="h-4 w-4" />
          {t('whatsapp_senders.create')}
        </Button>
      </div>

      <div className="rounded-md border border-[var(--color-border)] bg-[var(--color-muted)] p-3 text-xs text-[var(--color-muted-foreground)]">
        {t('whatsapp_senders.help')} <span className="font-medium">{senders.length}/{MAX_SENDERS}</span>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('whatsapp_senders.label')}</TableHead>
            <TableHead>{t('whatsapp_senders.phone')}</TableHead>
            <TableHead>{t('whatsapp_senders.credentials')}</TableHead>
            <TableHead>{t('whatsapp_senders.priority')}</TableHead>
            <TableHead>{t('whatsapp_senders.status')}</TableHead>
            <TableHead className="text-end">{t('common.actions')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.loading')}
              </TableCell>
            </TableRow>
          ) : senders.length === 0 ? (
            <TableRow>
              <TableCell colSpan={6} className="py-8 text-center text-[var(--color-muted-foreground)]">
                {t('common.no_data')}
              </TableCell>
            </TableRow>
          ) : (
            senders.map((s) => {
              const benched = s.failure_count >= FAILURE_THRESHOLD;
              return (
                <TableRow key={s.id}>
                  <TableCell className="font-medium">{s.label ?? '—'}</TableCell>
                  <TableCell dir="ltr" className="font-mono text-xs">+{s.phone}</TableCell>
                  <TableCell>
                    {s.token_set && s.profile_id ? (
                      <Badge variant="muted">{t('whatsapp_senders.creds_set')}</Badge>
                    ) : (
                      <Badge variant="muted" className="text-amber-700">{t('whatsapp_senders.creds_missing')}</Badge>
                    )}
                  </TableCell>
                  <TableCell>{s.priority}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      {s.is_active ? (
                        <Check className="h-4 w-4 text-emerald-600" />
                      ) : (
                        <X className="h-4 w-4 text-[var(--color-muted-foreground)]" />
                      )}
                      {benched && (
                        <span className="inline-flex items-center gap-1 text-xs text-amber-700" title={t('whatsapp_senders.benched_help')}>
                          <ShieldAlert className="h-3.5 w-3.5" />
                          {t('whatsapp_senders.benched')}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="text-end">
                    <div className="flex justify-end gap-1">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => { setTesting(s); setTestPhone(''); }}
                        aria-label={t('whatsapp_senders.test')}
                        title={t('whatsapp_senders.test')}
                      >
                        <Send className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => setEditing(s)} aria-label={t('common.edit')}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setDeleting(s)}
                        aria-label={t('common.delete')}
                        className="text-[var(--color-destructive)]"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              );
            })
          )}
        </TableBody>
      </Table>

      {/* Create / edit */}
      <Dialog open={creating || editing !== null} onOpenChange={(open) => { if (!open) { setCreating(false); setEditing(null); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? t('whatsapp_senders.edit') : t('whatsapp_senders.create')}</DialogTitle>
            <DialogDescription className="sr-only">{t('whatsapp_senders.subtitle')}</DialogDescription>
          </DialogHeader>
          <WhatsAppSenderForm
            sender={editing}
            onSuccess={() => { setCreating(false); setEditing(null); }}
            onCancel={() => { setCreating(false); setEditing(null); }}
          />
        </DialogContent>
      </Dialog>

      {/* Test send */}
      <Dialog open={testing !== null} onOpenChange={(open) => { if (!open) { setTesting(null); setTestPhone(''); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('whatsapp_senders.test_title')}</DialogTitle>
            <DialogDescription>{t('whatsapp_senders.test_desc')}</DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="test_phone">{t('whatsapp_senders.test_phone')}</Label>
            <Input
              id="test_phone"
              dir="ltr"
              value={testPhone}
              onChange={(e) => setTestPhone(e.target.value)}
              placeholder="05XXXXXXXX"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setTesting(null); setTestPhone(''); }} disabled={test.isPending}>
              {t('common.cancel')}
            </Button>
            <Button onClick={handleTest} disabled={test.isPending || !/^0?5\d{8}$/.test(testPhone)}>
              {test.isPending ? t('common.loading') : t('whatsapp_senders.send_test')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete confirm */}
      <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('whatsapp_senders.delete_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>{t('whatsapp_senders.delete_confirm_body')}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} disabled={del.isPending}>
              {t('common.delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
