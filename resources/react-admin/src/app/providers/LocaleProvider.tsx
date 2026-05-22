import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import i18n from 'i18next';
import { initReactI18next, useTranslation } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

import arAdmin from '@/locales/ar/admin.json';
import enAdmin from '@/locales/en/admin.json';

type Locale = 'ar' | 'en';

interface LocaleContextValue {
  locale: Locale;
  direction: 'rtl' | 'ltr';
  setLocale: (l: Locale) => void;
}

const LocaleContext = createContext<LocaleContextValue | null>(null);

if (!i18n.isInitialized) {
  i18n
    .use(LanguageDetector)
    .use(initReactI18next)
    .init({
      resources: {
        ar: { admin: arAdmin },
        en: { admin: enAdmin },
      },
      fallbackLng: 'ar',
      supportedLngs: ['ar', 'en'],
      ns: ['admin'],
      defaultNS: 'admin',
      detection: {
        order: ['cookie', 'localStorage', 'navigator'],
        lookupCookie: 'app_locale',
        caches: ['cookie', 'localStorage'],
      },
      interpolation: { escapeValue: false },
    });
}

export function LocaleProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>((i18n.language as Locale) || 'ar');

  const setLocale = useCallback((l: Locale) => {
    i18n.changeLanguage(l);
    document.cookie = `app_locale=${l}; path=/; max-age=${60 * 60 * 24 * 365}`;
    setLocaleState(l);
  }, []);

  useEffect(() => {
    const dir = locale === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.setAttribute('dir', dir);
    document.documentElement.setAttribute('lang', locale);
  }, [locale]);

  const value = useMemo<LocaleContextValue>(
    () => ({ locale, direction: locale === 'ar' ? 'rtl' : 'ltr', setLocale }),
    [locale, setLocale],
  );

  return <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>;
}

export function useLocale() {
  const ctx = useContext(LocaleContext);
  if (!ctx) throw new Error('useLocale must be used within LocaleProvider');
  return ctx;
}

export { useTranslation };
