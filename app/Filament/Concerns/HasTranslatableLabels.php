<?php

namespace App\Filament\Concerns;

/**
 * @property-read ?string $translationKey
 */
trait HasTranslatableLabels
{
    public static function getNavigationLabel(): string
    {
        return static::$translationKey
            ? __('admin.res_' . static::$translationKey . 's')
            : (static::$navigationLabel ?? '');
    }

    public static function getModelLabel(): string
    {
        return static::$translationKey
            ? __('admin.res_' . static::$translationKey)
            : (static::$modelLabel ?? '');
    }

    public static function getPluralModelLabel(): string
    {
        return static::$translationKey
            ? __('admin.res_' . static::$translationKey . 's')
            : (static::$pluralModelLabel ?? '');
    }
}
