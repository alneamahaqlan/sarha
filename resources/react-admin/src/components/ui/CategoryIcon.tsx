// Display-only: render a category's resolved visual (uploaded image, built-in
// vector, or emoji fallback). Thin wrapper over the shared icon library so the
// public site and the admin panel show identical category icons.
//
// Canonical source: src/lib/category-icons.tsx (mirrors the PHP
// App\Support\CategoryIcons used by the Blade <x-category-icon> component).
export { CategoryIcon } from '@/lib/category-icons';
