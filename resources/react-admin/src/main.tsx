import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { Toaster } from 'sonner';

import { queryClient } from '@/lib/query-client';
import { LocaleProvider } from '@/app/providers/LocaleProvider';
import { AuthProvider } from '@/app/providers/AuthProvider';
import { AppRoutes } from '@/app/routes';

import '@/styles/index.css';

ReactDOM.createRoot(document.getElementById('react-admin-root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <LocaleProvider>
        <BrowserRouter basename="/app">
          <AuthProvider>
            <AppRoutes />
            <Toaster richColors closeButton position="top-center" />
          </AuthProvider>
        </BrowserRouter>
      </LocaleProvider>
    </QueryClientProvider>
  </React.StrictMode>,
);
