<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasTranslatableLabels;
use App\Filament\Resources\ComplaintResource\Pages;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ComplaintResource extends Resource
{
    use HasTranslatableLabels;

    protected static ?string $translationKey = 'complaint';
    protected static ?string $model = Complaint::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static string|\UnitEnum|null $navigationGroup = 'إدارة المجمعات';
    protected static ?int $navigationSort = 4;

    private static function typeOptions(): array
    {
        return collect(Complaint::TYPES)
            ->mapWithKeys(fn($t) => [$t => __('admin.complaint_type.' . $t)])
            ->toArray();
    }

    private static function statusOptions(): array
    {
        return collect(Complaint::STATUSES)
            ->mapWithKeys(fn($s) => [$s => __('admin.complaint_status.' . $s)])
            ->toArray();
    }

    private static function priorityOptions(): array
    {
        return collect(Complaint::PRIORITIES)
            ->mapWithKeys(fn($p) => [$p => __('admin.complaint_priority.' . $p)])
            ->toArray();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Complaint::whereIn('status', ['new', 'in_review'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('reference_code')
                ->label(__('admin.fields.reference_code'))
                ->disabled()->dehydrated(false),

            Forms\Components\Select::make('clinic_id')
                ->label(__('admin.fields.name_clinic'))
                ->relationship('clinic', 'name')->searchable(),

            Forms\Components\TextInput::make('customer_name')->label(__('admin.fields.customer_name'))->required(),
            Forms\Components\TextInput::make('customer_phone')->label(__('admin.fields.phone'))->required()->tel(),
            Forms\Components\TextInput::make('customer_email')->label(__('admin.fields.email'))->email(),

            Forms\Components\Select::make('type')->label(__('admin.fields.complaint_type'))
                ->options(self::typeOptions())->required(),
            Forms\Components\Select::make('priority')->label(__('admin.fields.priority'))
                ->options(self::priorityOptions())->required()->default('medium'),
            Forms\Components\Select::make('status')->label(__('admin.fields.status'))
                ->options(self::statusOptions())->required()->default('new'),

            Forms\Components\Select::make('assigned_admin_id')
                ->label(__('admin.fields.assigned_admin'))
                ->relationship('assignedAdmin', 'name')->searchable(),

            Forms\Components\TextInput::make('subject')->label(__('admin.fields.subject'))->required()->columnSpanFull(),
            Forms\Components\Textarea::make('description')->label(__('admin.fields.description'))->rows(4)->required()->columnSpanFull(),
            Forms\Components\Textarea::make('admin_notes')->label(__('admin.fields.admin_notes'))->rows(3)->columnSpanFull(),
            Forms\Components\Textarea::make('resolution')->label(__('admin.fields.resolution'))->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_code')
                    ->label(__('admin.fields.reference_code'))
                    ->searchable()->copyable()->badge()->color('gray'),
                Tables\Columns\TextColumn::make('customer_name')->label(__('admin.fields.customer_name'))->searchable(),
                Tables\Columns\TextColumn::make('clinic.name')->label(__('admin.fields.name_clinic'))->searchable()->default(__('admin.fields.default_dash')),
                Tables\Columns\TextColumn::make('type')->label(__('admin.fields.complaint_type'))->badge()
                    ->formatStateUsing(fn($state) => __('admin.complaint_type.' . $state)),
                Tables\Columns\TextColumn::make('priority')->label(__('admin.fields.priority'))->badge()
                    ->color(fn($state) => ['low' => 'gray', 'medium' => 'warning', 'high' => 'danger'][$state] ?? 'gray')
                    ->formatStateUsing(fn($state) => __('admin.complaint_priority.' . $state)),
                Tables\Columns\TextColumn::make('status')->label(__('admin.fields.status'))->badge()
                    ->color(fn($state) => [
                        'new' => 'info', 'in_review' => 'warning',
                        'resolved' => 'success', 'rejected' => 'danger',
                    ][$state] ?? 'gray')
                    ->formatStateUsing(fn($state) => __('admin.complaint_status.' . $state)),
                Tables\Columns\IconColumn::make('clinic_notified')->label(__('admin.fields.clinic_notified'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.fields.created_at'))->dateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label(__('admin.fields.status'))->options(self::statusOptions()),
                Tables\Filters\SelectFilter::make('type')->label(__('admin.fields.complaint_type'))->options(self::typeOptions()),
                Tables\Filters\SelectFilter::make('priority')->label(__('admin.fields.priority'))->options(self::priorityOptions()),
                Tables\Filters\SelectFilter::make('clinic_id')->label(__('admin.fields.name_clinic'))->relationship('clinic', 'name'),
            ])
            ->actions([
                \Filament\Actions\Action::make('mark_in_review')
                    ->label(__('admin.actions.mark_in_review'))
                    ->icon('heroicon-o-eye')->color('warning')
                    ->visible(fn(Complaint $r) => $r->status === 'new')
                    ->requiresConfirmation()
                    ->action(fn(Complaint $r) => app(ComplaintService::class)->markInReview($r)),

                \Filament\Actions\Action::make('resolve')
                    ->label(__('admin.actions.resolve'))
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn(Complaint $r) => in_array($r->status, ['new', 'in_review']))
                    ->form([
                        Forms\Components\Textarea::make('resolution')->label(__('admin.fields.resolution'))->required()->rows(4),
                    ])
                    ->action(fn(Complaint $r, array $data) => app(ComplaintService::class)->resolve($r, $data['resolution'])),

                \Filament\Actions\Action::make('reject')
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn(Complaint $r) => in_array($r->status, ['new', 'in_review']))
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')->label(__('admin.fields.rejection_reason'))->required()->rows(3),
                    ])
                    ->action(fn(Complaint $r, array $data) => app(ComplaintService::class)->reject($r, $data['admin_notes'])),

                \Filament\Actions\Action::make('notify_clinic')
                    ->label(__('admin.actions.notify_clinic'))
                    ->icon('heroicon-o-megaphone')->color('primary')
                    ->visible(fn(Complaint $r) => $r->clinic_id && ! $r->clinic_notified)
                    ->requiresConfirmation()
                    ->action(fn(Complaint $r) => app(ComplaintService::class)->notifyClinic($r)),

                \Filament\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplaints::route('/'),
            'create' => Pages\CreateComplaint::route('/create'),
            'edit'  => Pages\EditComplaint::route('/{record}/edit'),
        ];
    }
}
