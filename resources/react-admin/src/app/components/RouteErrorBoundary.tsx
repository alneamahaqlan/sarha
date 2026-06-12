import { Component, type ErrorInfo, type ReactNode } from 'react';
import { RefreshCw } from 'lucide-react';

/**
 * Catches render-time + lazy-chunk-load failures inside the routed content
 * area so a single broken page (or a stale code-split chunk after a deploy)
 * shows a recoverable error card instead of blanking the whole SPA.
 *
 * Two distinct failure modes:
 *  1. **Stale chunk** — after a new deploy the running bundle tries to
 *     `import()` a hashed chunk that no longer exists on the server. The
 *     dynamic import rejects with a "Failed to fetch dynamically imported
 *     module" / ChunkLoadError. The fix is simply to reload so the browser
 *     picks up the fresh index + chunk map. We auto-reload ONCE (guarded by
 *     a sessionStorage flag) to avoid a reload loop if the real cause is
 *     something else.
 *  2. **Genuine render error** — show a retry card with a reload button;
 *     never leave the user on a blank screen.
 */
const RELOAD_FLAG = 'route_chunk_reloaded_at';

function isChunkLoadError(error: unknown): boolean {
  const msg = error instanceof Error ? `${error.name} ${error.message}` : String(error);
  return /ChunkLoadError|Loading chunk|Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed/i.test(msg);
}

interface Props {
  children: ReactNode;
  /** Resets the boundary when the routed location changes (so navigating away clears a stuck error). */
  resetKey?: string;
}

interface State {
  error: Error | null;
}

export class RouteErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Stale-chunk recovery: reload once. The flag carries a timestamp so a
    // genuinely persistent chunk failure (e.g. offline) reloads at most once
    // per minute instead of hammering in a loop.
    if (isChunkLoadError(error)) {
      const last = Number(sessionStorage.getItem(RELOAD_FLAG) ?? 0);
      if (Date.now() - last > 60_000) {
        sessionStorage.setItem(RELOAD_FLAG, String(Date.now()));
        window.location.reload();
        return;
      }
    }
    // Surface non-chunk errors for debugging without crashing the shell.
    console.error('[RouteErrorBoundary]', error, info.componentStack);
  }

  componentDidUpdate(prev: Props) {
    // Clear the error when the route changes so a different page renders fresh.
    if (this.state.error && prev.resetKey !== this.props.resetKey) {
      this.setState({ error: null });
    }
  }

  private handleReload = () => {
    this.setState({ error: null });
    window.location.reload();
  };

  render() {
    const { error } = this.state;
    if (!error) return this.props.children;

    const chunk = isChunkLoadError(error);

    return (
      <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-6 text-center">
        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600">
          <RefreshCw className="h-6 w-6" />
        </div>
        <div className="max-w-md space-y-1">
          <h2 className="text-lg font-semibold text-[var(--color-foreground)]">
            {chunk ? 'تعذّر تحميل هذه الصفحة' : 'حدث خطأ غير متوقّع'}
          </h2>
          <p className="text-sm text-[var(--color-muted-foreground)]">
            {chunk
              ? 'قد يكون التطبيق حُدّث للتو. أعد التحميل لتحميل أحدث نسخة.'
              : 'حدث خطأ أثناء عرض هذه الصفحة. يمكنك إعادة المحاولة أو إعادة تحميل الصفحة.'}
          </p>
        </div>
        <button
          type="button"
          onClick={this.handleReload}
          className="inline-flex items-center gap-2 rounded-md bg-[var(--color-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:opacity-90"
        >
          <RefreshCw className="h-4 w-4" />
          إعادة التحميل
        </button>
      </div>
    );
  }
}
