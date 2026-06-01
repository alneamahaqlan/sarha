/**
 * Resolves a stored media path (e.g. "logos/abc.jpg") to a fully
 * qualified URL.
 *
 * The backend persists relative paths and serves the bytes from the default
 * filesystem disk (S3 in this project). VITE_ASSET_BASE_URL mirrors the
 * backend's AWS_URL so previews resolve to the exact same bucket URL, e.g.
 * https://daleel-sarha.s3.eu-north-1.amazonaws.com/logos/abc.jpg
 *
 * Falls back to "/storage" when the env var is absent so a purely local
 * (non-S3) setup still works.
 */
const ASSET_BASE = (import.meta.env.VITE_ASSET_BASE_URL ?? '/storage').replace(/\/+$/, '');

export function assetUrl(path: string | null | undefined): string | null {
  if (!path) return null;
  // Already absolute (the backend sometimes returns a full *_url field).
  if (/^https?:\/\//i.test(path)) return path;
  return `${ASSET_BASE}/${path.replace(/^\/+/, '')}`;
}
