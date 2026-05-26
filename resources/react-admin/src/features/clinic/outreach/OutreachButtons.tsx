import { MessageCircle, Phone } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { apiClient } from '@/lib/api-client';

function whatsappLink(phone: string): string {
  let p = phone.replace(/\D/g, '');
  if (p.startsWith('05')) p = `966${p.slice(1)}`;
  else if (p.startsWith('5') && p.length === 9) p = `966${p}`;
  return `https://wa.me/${p}`;
}

/**
 * WhatsApp + Call buttons for reaching a customer from a booking or quote.
 * Each click records outreach (counts in stats + as a visibility) before
 * opening the channel.
 */
export function OutreachButtons({
  phone, context, refId,
}: {
  phone: string;
  context: 'booking' | 'quote';
  refId: number;
}) {
  const { t } = useTranslation();

  const record = (type: 'whatsapp' | 'call') => {
    // Fire-and-forget — never block the outreach on the analytics write.
    apiClient.post('/clinic/outreach', { type, context, ref_id: refId }).catch(() => {});
  };

  return (
    <>
      <Button
        variant="ghost"
        size="icon"
        className="text-emerald-600"
        title={t('outreach.whatsapp')}
        aria-label={t('outreach.whatsapp')}
        onClick={() => { record('whatsapp'); window.open(whatsappLink(phone), '_blank', 'noopener'); }}
      >
        <MessageCircle className="h-4 w-4" />
      </Button>
      <Button
        variant="ghost"
        size="icon"
        className="text-blue-600"
        title={t('outreach.call')}
        aria-label={t('outreach.call')}
        onClick={() => { record('call'); window.location.href = `tel:${phone}`; }}
      >
        <Phone className="h-4 w-4" />
      </Button>
    </>
  );
}
