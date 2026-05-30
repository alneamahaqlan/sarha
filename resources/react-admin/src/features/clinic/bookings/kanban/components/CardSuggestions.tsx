import { Clock, PhoneCall, Repeat, AlertTriangle, Sparkles } from 'lucide-react';
import { useTranslation } from '@/app/providers/LocaleProvider';
import type { SuggestionKey } from '../types';

const ICONS: Record<SuggestionKey, any> = {
  confirm_urgent: Clock,
  first_contact: Sparkles,
  retry_call: Repeat,
  reminder_soon: PhoneCall,
  cancel_risk: AlertTriangle,
};

const TONES: Record<SuggestionKey, string> = {
  confirm_urgent: 'border-red-300 bg-red-50 text-red-700',
  first_contact: 'border-blue-300 bg-blue-50 text-blue-700',
  retry_call: 'border-amber-300 bg-amber-50 text-amber-700',
  reminder_soon: 'border-amber-300 bg-amber-50 text-amber-700',
  cancel_risk: 'border-rose-300 bg-rose-50 text-rose-700',
};

export function CardSuggestions({ suggestions }: { suggestions: SuggestionKey[] }) {
  const { t } = useTranslation();
  if (!suggestions || suggestions.length === 0) return null;
  return (
    <div className="flex flex-wrap gap-1">
      {suggestions.map((k) => {
        const Icon = ICONS[k];
        return (
          <span
            key={k}
            className={`inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-[10px] font-medium ${TONES[k]}`}
          >
            <Icon className="h-3 w-3" />
            {t(`clinic_bookings_kanban.suggestion.${k}`)}
          </span>
        );
      })}
    </div>
  );
}
