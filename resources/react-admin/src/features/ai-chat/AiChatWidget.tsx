import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Send, Sparkles, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage } from '@/lib/api-client';
import { cn } from '@/lib/utils';

import { aiChatApi, type AiChatResponse } from './api';

interface ChatMessage {
  role: 'user' | 'assistant';
  text: string;
  clinics?: AiChatResponse['clinics'];
  kind?: AiChatResponse['kind'];
}

/**
 * Floating AI assistant button + panel. Delegates to the SAME
 * AiAssistantService::ask the Filament Livewire AiChat widget uses.
 */
export function AiChatWidget() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [input, setInput] = useState('');
  const [messages, setMessages] = useState<ChatMessage[]>([]);

  const ask = useMutation({
    mutationFn: (q: string) => aiChatApi.ask(q),
    onSuccess: (res, q) => {
      setMessages((m) => [
        ...m,
        { role: 'user', text: q },
        { role: 'assistant', text: res.reply, clinics: res.clinics, kind: res.kind },
      ]);
      setInput('');
    },
    onError: (err, q) => {
      setMessages((m) => [
        ...m,
        { role: 'user', text: q },
        { role: 'assistant', text: extractMessage(err, t('errors.generic')) },
      ]);
    },
  });

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const q = input.trim();
    if (!q || ask.isPending) return;
    ask.mutate(q);
  };

  return (
    <>
      {!open && (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="fixed bottom-5 end-5 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-plum-primary text-white shadow-lg hover:opacity-90"
          aria-label={t('ai_chat.open')}
        >
          <Sparkles className="h-5 w-5" />
        </button>
      )}

      {open && (
        <div className="fixed bottom-5 end-5 z-40 flex h-[28rem] w-80 flex-col rounded-lg border border-[var(--color-border)] bg-white shadow-2xl">
          <div className="flex items-center justify-between border-b border-[var(--color-border)] px-3 py-2">
            <div className="flex items-center gap-2 text-sm font-semibold">
              <Sparkles className="h-4 w-4 text-plum-primary" />
              {t('ai_chat.title')}
            </div>
            <button type="button" onClick={() => setOpen(false)} aria-label={t('common.cancel')} className="text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]">
              <X className="h-4 w-4" />
            </button>
          </div>

          <div className="flex-1 space-y-3 overflow-y-auto p-3 text-sm">
            {messages.length === 0 ? (
              <div className="text-center text-xs text-[var(--color-muted-foreground)]">{t('ai_chat.placeholder')}</div>
            ) : (
              messages.map((m, i) => (
                <div key={i} className={cn('flex', m.role === 'user' ? 'justify-end' : 'justify-start')}>
                  <div
                    className={cn(
                      'max-w-[85%] rounded-lg px-3 py-2',
                      m.role === 'user'
                        ? 'bg-[var(--color-primary)] text-white'
                        : 'bg-[var(--color-muted)] text-[var(--color-foreground)]',
                    )}
                  >
                    <div className="whitespace-pre-wrap break-words">{m.text}</div>
                    {m.clinics && m.clinics.length > 0 && (
                      <ul className="mt-2 space-y-1 border-t border-black/10 pt-2 text-xs">
                        {m.clinics.slice(0, 5).map((c) => (
                          <li key={c.id}>
                            • {c.name}
                            {c.city?.name ? ` — ${c.city.name}` : ''}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                </div>
              ))
            )}
            {ask.isPending && (
              <div className="text-xs text-[var(--color-muted-foreground)]">{t('ai_chat.thinking')}</div>
            )}
          </div>

          <form noValidate onSubmit={onSubmit} className="flex items-center gap-2 border-t border-[var(--color-border)] p-2">
            <Input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder={t('ai_chat.input_placeholder')}
              disabled={ask.isPending}
              maxLength={500}
            />
            <Button type="submit" size="icon" disabled={!input.trim() || ask.isPending} aria-label={t('ai_chat.send')}>
              <Send className="h-4 w-4" />
            </Button>
          </form>
        </div>
      )}
    </>
  );
}
