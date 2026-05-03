<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'audit_log';
    protected static ?string $model = AuditLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'إعدادات النظام';
    protected static ?string $navigationLabel = 'سجل المراجعة';
    protected static ?string $modelLabel = 'سجل';
    protected static ?string $pluralModelLabel = 'سجل المراجعة';
    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('admin_name')->label('المسؤول')->searchable(),
                Tables\Columns\TextColumn::make('action')->label('الإجراء')->searchable(),
                Tables\Columns\TextColumn::make('model_type')->label('النوع')
                    ->formatStateUsing(fn($state) => class_basename($state ?? '')),
                Tables\Columns\TextColumn::make('model_id')->label('المعرف'),
                Tables\Columns\TextColumn::make('ip_address')->label('عنوان IP'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('admin_id')->label('المسؤول')->relationship('admin', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
