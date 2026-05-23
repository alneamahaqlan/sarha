import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface CopyBadgeProps {
  value: string;
  className?: string;
}

export function CopyBadge({ value, className }: CopyBadgeProps) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // clipboard API blocked — silently ignore.
    }
  };

  return (
    <button type="button" onClick={copy} className="group inline-flex items-center" aria-label={value}>
      <Badge variant="muted" className={cn('font-mono cursor-pointer hover:bg-[var(--color-muted)]', className)}>
        {value}
        {copied ? <Check className="ms-1 h-3 w-3 text-emerald-600" /> : <Copy className="ms-1 h-3 w-3 opacity-50 group-hover:opacity-100" />}
      </Badge>
    </button>
  );
}
