import { useState } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/app/providers/LocaleProvider';
import { extractMessage, extractValidationErrors } from '@/lib/api-client';

import { useCreateAiRestriction } from '../hooks';
import type { AiRestrictionType } from '../types';

interface Props {
  defaultType: AiRestrictionType;
  onClose: () => void;
}

export function AiRestrictionDialog({ defaultType, onClose }: Props) {
  const { t } = useTranslation();
  const create = useCreateAiRestriction();

  const [type, setType] = useState<AiRestrictionType>(defaultType);
  const [value, setValue] = useState('');
  const [responseOverride, setResponseOverride] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const isBlocklist = type === 'clinic_blocklist' || type === 'category_blocklist';
  const supportsOverride = type === 'banned_topic';

  const onSubmit = async () => {
    setErrors({});
    try {
      await create.mutateAsync({
        type,
        value: value.trim(),
        response_override: supportsOverride && responseOverride.trim() ? responseOverride : null,
      });
      toast.success(t('ai_center.restriction_created', 'تم إنشاء المنع'));
      onClose();
    } catch (err) {
      const ve = extractValidationErrors(err);
      if (ve) {
        setErrors(Object.fromEntries(Object.entries(ve).map(([k, v]) => [k, (v as string[])[0]])));
      } else {
        toast.error(extractMessage(err, t('errors.generic')));
      }
    }
  };

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{t('ai_center.add_restriction', 'إضافة منع')}</DialogTitle>
          <DialogDescription>
            {t('ai_center.add_restriction_hint', 'تَفعل المنوعات على رد المساعد قبل إرساله للمستخدم. التغيير يَسري خلال دقيقة.')}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="r-type">{t('ai_center.restriction_type', 'النوع')}</Label>
            <select
              id="r-type"
              value={type}
              onChange={(e) => setType(e.target.value as AiRestrictionType)}
              className="flex h-9 w-full rounded-md border border-[var(--color-border)] bg-transparent px-3 py-1 text-sm shadow-sm"
            >
              <option value="banned_topic">{t('ai_center.type_banned_topic', 'موضوع ممنوع')}</option>
              <option value="emergency_keyword">{t('ai_center.type_emergency_keyword', 'كلمة طوارئ')}</option>
              <option value="clinic_blocklist">{t('ai_center.type_clinic_blocklist', 'مجمع ممنوع توصيته')}</option>
              <option value="category_blocklist">{t('ai_center.type_category_blocklist', 'تخصص ممنوع توصيته')}</option>
            </select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="r-value">
              {isBlocklist
                ? t('ai_center.value_id', 'المعرّف (id)')
                : t('ai_center.value_phrase', 'العبارة أو الكلمة')}
            </Label>
            <Input
              id="r-value"
              value={value}
              onChange={(e) => setValue(e.target.value)}
              maxLength={255}
              dir={isBlocklist ? 'ltr' : 'auto'}
              placeholder={isBlocklist ? '123' : t('ai_center.value_placeholder', 'مثال: تشخيص طبي')}
            />
            {errors.value && (
              <p className="text-xs text-[var(--color-destructive)]">{errors.value}</p>
            )}
            {isBlocklist && (
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {t('ai_center.value_id_hint', 'اكتب رقم المعرّف من شاشة المجمعات/التخصصات.')}
              </p>
            )}
          </div>

          {supportsOverride && (
            <div className="space-y-1.5">
              <Label htmlFor="r-override">{t('ai_center.response_override', 'رد بديل (اختياري)')}</Label>
              <Textarea
                id="r-override"
                value={responseOverride}
                onChange={(e) => setResponseOverride(e.target.value)}
                rows={3}
                maxLength={2000}
                placeholder={t('ai_center.response_override_placeholder', 'النص الذي سيعرضه المساعد بدلاً من الرد الافتراضي.')}
              />
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose} disabled={create.isPending}>
            {t('common.cancel')}
          </Button>
          <Button onClick={onSubmit} disabled={create.isPending || !value.trim()}>
            {create.isPending ? t('common.saving', 'يحفظ…') : t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
