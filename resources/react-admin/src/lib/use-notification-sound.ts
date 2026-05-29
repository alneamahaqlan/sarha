import { useCallback, useEffect, useRef } from 'react';

/**
 * Cross-tab-aware notification sound via the Web Audio API.
 *
 * Why synthesised (and not MP3s)?
 *   - Zero bytes shipped, zero CORS/cache headaches.
 *   - Precise control over duration so a notification can't keep
 *     ringing if the JS layer drops a beat.
 *   - Different priorities map to different two-note motifs without
 *     touching disk.
 *
 * Cross-tab lock:
 *   When the same user has 3 tabs open, all three receive the broadcast
 *   from the same Reverb subscription. Playing sound in all three is
 *   obnoxious. We use a BroadcastChannel to elect one tab as the
 *   "winner" — first claim wins, others stay silent for 2 seconds.
 *
 * Autoplay policy:
 *   Browsers block AudioContext.start() until a user gesture has
 *   happened on the page. The hook lazily creates the context inside
 *   `play()`, which is always called from a real notification event
 *   AFTER the user has been on the page for a while — usually fine,
 *   but we silently swallow errors so a blocked autoplay never breaks
 *   the bell render.
 */

type Priority = 'info' | 'normal' | 'high' | 'urgent';

const LOCK_CHANNEL = 'sarha.notif-sound-lock';
const LOCK_WINDOW_MS = 2_000;

interface SoundLockMessage {
  type: 'claim';
  notificationId: number;
  tabId: number;
  at: number;
}

/** Pick a stable id for this tab so we can disambiguate claims. */
const TAB_ID = Math.floor(Math.random() * 2 ** 31);

export function useNotificationSound() {
  const ctxRef = useRef<AudioContext | null>(null);
  const recentClaimsRef = useRef<Map<number, number>>(new Map());
  const bcRef = useRef<BroadcastChannel | null>(null);

  useEffect(() => {
    // BroadcastChannel is supported in all evergreen browsers we care
    // about; the fallback path (no channel) just means each tab plays
    // its own sound — annoying but not broken.
    if (typeof BroadcastChannel === 'undefined') return;
    const bc = new BroadcastChannel(LOCK_CHANNEL);
    bcRef.current = bc;
    bc.onmessage = (e: MessageEvent<SoundLockMessage>) => {
      if (e.data?.type === 'claim') {
        recentClaimsRef.current.set(e.data.notificationId, e.data.at);
      }
    };
    return () => { bc.close(); bcRef.current = null; };
  }, []);

  /**
   * Play the sound matching the priority. Pass a notification id so
   * the cross-tab lock can dedupe by it — same notification arriving
   * in 3 tabs gets one sound total.
   */
  const play = useCallback((priority: Priority, notificationId: number) => {
    // INFO never makes a sound (in-app only, no audible alert).
    if (priority === 'info' || priority === 'normal') return;

    // Cross-tab lock — if another tab already claimed in the past 2s, stay silent.
    const claimed = recentClaimsRef.current.get(notificationId);
    if (claimed && Date.now() - claimed < LOCK_WINDOW_MS) return;
    // Optimistically claim and announce.
    const now = Date.now();
    recentClaimsRef.current.set(notificationId, now);
    bcRef.current?.postMessage({ type: 'claim', notificationId, tabId: TAB_ID, at: now } satisfies SoundLockMessage);

    try {
      if (!ctxRef.current) {
        const AC = (window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext);
        ctxRef.current = new AC();
      }
      const ctx = ctxRef.current;

      // High = warm two-note chime (E5 → G5).
      // Urgent = sharper triad (C5 → E5 → G5) — distinct enough to
      // grab attention even when the user has muted other tabs.
      const notes = priority === 'urgent'
        ? [{ f: 523.25, dur: 0.10 }, { f: 659.25, dur: 0.10 }, { f: 783.99, dur: 0.16 }]
        : [{ f: 659.25, dur: 0.10 }, { f: 783.99, dur: 0.14 }];

      let t = ctx.currentTime;
      for (const n of notes) {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(n.f, t);
        // ADSR-ish envelope so we don't hear clicks at the boundaries.
        gain.gain.setValueAtTime(0, t);
        gain.gain.linearRampToValueAtTime(0.18, t + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, t + n.dur);
        osc.connect(gain).connect(ctx.destination);
        osc.start(t);
        osc.stop(t + n.dur + 0.02);
        t += n.dur;
      }
    } catch {
      // Autoplay blocked / context creation failed — silent fallback.
    }
  }, []);

  return { play };
}
