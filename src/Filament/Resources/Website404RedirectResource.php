<?php

namespace Apogee\Website404Redirects\Filament\Resources;

use Apogee\Website404Redirects\Contracts\RedirectAdminAuthorizer;
use Apogee\Website404Redirects\Filament\Resources\Website404RedirectResource\Pages;
use Apogee\Website404Redirects\Models\Website404Redirect;
use Apogee\Website404Redirects\Services\Website404Redirect\PathNormalizer;
use Apogee\Website404Redirects\Services\Website404Redirect\RedirectTargetValidator;
use BackedEnum;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section as SchemaSection;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnitEnum;

class Website404RedirectResource extends Resource
{
    protected static ?string $model = Website404Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = '404 & redirects';

    protected static ?string $modelLabel = '404 redirect';

    protected static ?string $pluralModelLabel = '404 & redirects';

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return static::authorizer()->canManageRedirects(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::authorizer()->canManageRedirects(auth()->user());
    }

    public static function canView(Model $record): bool
    {
        return static::authorizer()->canManageRedirects(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return static::authorizer()->canManageRedirects(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return static::authorizer()->canManageRedirects(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SchemaSection::make('Path & redirect')
                ->schema([
                    Forms\Components\TextInput::make('path')
                        ->label('Path')
                        ->required()
                        ->maxLength(512)
                        ->unique(ignoreRecord: true)
                        ->placeholder('/blog/old-slug')
                        ->helperText('Normalized on save (leading slash, no trailing slash, lowercase).'),

                    Forms\Components\TextInput::make('redirect_to')
                        ->label('Redirect to')
                        ->maxLength(2048)
                        ->placeholder('/blog/new-slug')
                        ->helperText('Relative path (preferred) or allowed external URL. Leave empty to log hits only.'),

                    Forms\Components\TextInput::make('redirect_status')
                        ->label('HTTP status')
                        ->numeric()
                        ->default(301)
                        ->minValue(301)
                        ->maxValue(301)
                        ->disabled()
                        ->dehydrated(true),

                    Forms\Components\Toggle::make('is_ignored')
                        ->label('Ignored')
                        ->helperText('Ignored paths are not logged and not redirected.'),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferFilters(false)
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Path copied')
                    ->url(fn (Website404Redirect $record): string => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('hit_count')
                    ->label('Hits')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 10 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('redirect_to')
                    ->label('Redirect to')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Website404Redirect $record): string => self::statusLabel($record))
                    ->color(fn (Website404Redirect $record): string => self::statusColor($record)),
            ])
            ->filters([
                Filter::make('status')
                    ->label('Status')
                    ->schema([
                        Forms\Components\Radio::make('value')
                            ->hiddenLabel()
                            ->options([
                                'pending' => 'Pending',
                                'ignored' => 'Ignored',
                                'has_redirect' => 'Has redirect',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => $query
                                ->whereNull('redirect_to')
                                ->where('is_ignored', false),
                            'ignored' => $query->where('is_ignored', true),
                            'has_redirect' => $query
                                ->where('is_ignored', false)
                                ->whereNotNull('redirect_to'),
                            default => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['value'] ?? null) {
                            'pending' => 'Pending',
                            'ignored' => 'Ignored',
                            'has_redirect' => 'Has redirect',
                            default => null,
                        };
                    }),

                Filter::make('high_traffic')
                    ->label('High traffic (10+ hits)')
                    ->query(fn (Builder $query): Builder => $query->where('hit_count', '>=', 10)),
            ])
            ->recordActionsColumnLabel('Actions')
            ->recordActions([
                Actions\ViewAction::make(),

                Actions\EditAction::make(),

                Actions\Action::make('setRedirect')
                    ->label('Set redirect')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn (Website404Redirect $record): bool => ! $record->is_ignored)
                    ->form([
                        Forms\Components\TextInput::make('redirect_to')
                            ->label('Redirect to')
                            ->required()
                            ->maxLength(2048)
                            ->default(fn (Website404Redirect $record): ?string => $record->redirect_to),

                        Forms\Components\TextInput::make('redirect_status')
                            ->label('HTTP status')
                            ->numeric()
                            ->default(301)
                            ->disabled()
                            ->dehydrated(true),

                        Forms\Components\Textarea::make('notes')
                            ->rows(2)
                            ->default(fn (Website404Redirect $record): ?string => $record->notes),
                    ])
                    ->action(function (Website404Redirect $record, array $data): void {
                        $validator = app(RedirectTargetValidator::class);
                        $record->redirect_to = $validator->validate($data['redirect_to'] ?? null, required: true);
                        $record->redirect_status = 301;
                        $record->notes = $data['notes'] ?? $record->notes;
                        $record->updated_by = auth()->id();
                        $record->save();

                        Notification::make()
                            ->title('Redirect saved')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('clearRedirect')
                    ->label('Clear redirect')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (Website404Redirect $record): bool => filled($record->redirect_to))
                    ->requiresConfirmation()
                    ->modalHeading('Clear redirect')
                    ->modalDescription('The path will remain for hit history; future requests will 404 until a new redirect is set.')
                    ->action(function (Website404Redirect $record): void {
                        $record->redirect_to = null;
                        $record->updated_by = auth()->id();
                        $record->save();

                        Notification::make()
                            ->title('Redirect cleared')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('toggleIgnored')
                    ->label(fn (Website404Redirect $record): string => $record->is_ignored ? 'Unignore' : 'Ignore')
                    ->icon(fn (Website404Redirect $record): string => $record->is_ignored ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->color(fn (Website404Redirect $record): string => $record->is_ignored ? 'success' : 'warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Website404Redirect $record): string => $record->is_ignored ? 'Unignore path' : 'Ignore path')
                    ->modalDescription(fn (Website404Redirect $record): string => $record->is_ignored
                        ? 'Resume logging and allow redirects for this path.'
                        : 'Stop logging hits and disable redirects for this path.')
                    ->action(function (Website404Redirect $record): void {
                        $record->is_ignored = ! $record->is_ignored;
                        $record->updated_by = auth()->id();
                        $record->save();

                        Notification::make()
                            ->title($record->is_ignored ? 'Path ignored' : 'Path unignored')
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Actions\BulkAction::make('ignore')
                        ->label('Ignore')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Ignore selected paths')
                        ->modalDescription('Stop logging hits and disable redirects for the selected paths.')
                        ->action(function (Collection $records): void {
                            $count = Website404Redirect::query()
                                ->whereIn('id', $records->modelKeys())
                                ->where('is_ignored', false)
                                ->update([
                                    'is_ignored' => true,
                                    'updated_by' => auth()->id(),
                                ]);

                            Notification::make()
                                ->title('Paths ignored')
                                ->body($count > 0
                                    ? "{$count} path(s) ignored."
                                    : 'Selected paths were already ignored.')
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('hit_count', 'desc')
            ->searchable()
            ->striped();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            SchemaSection::make('Path & redirect')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('path')
                                ->copyable()
                                ->url(
                                    fn (Website404Redirect $record): string => rtrim((string) config('app.url'), '/').$record->path,
                                    shouldOpenInNewTab: true,
                                ),
                            Infolists\Components\TextEntry::make('status')
                                ->label('Status')
                                ->state(fn (Website404Redirect $record): string => self::statusLabel($record))
                                ->badge()
                                ->color(fn (Website404Redirect $record): string => self::statusColor($record)),
                            Infolists\Components\TextEntry::make('redirect_to')
                                ->label('Redirect to')
                                ->placeholder('—')
                                ->copyable()
                                ->url(
                                    fn (Website404Redirect $record): ?string => self::publicUrlForRedirectTarget($record->redirect_to),
                                    shouldOpenInNewTab: true,
                                ),
                            Infolists\Components\TextEntry::make('redirect_status')
                                ->label('HTTP status'),
                            Infolists\Components\IconEntry::make('is_ignored')
                                ->label('Ignored')
                                ->boolean(),
                            Infolists\Components\TextEntry::make('notes')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),

            SchemaSection::make('Traffic')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('hit_count')
                                ->label('Hits'),
                            Infolists\Components\TextEntry::make('first_seen_at')
                                ->label('First seen')
                                ->dateTime()
                                ->placeholder('—'),
                            Infolists\Components\TextEntry::make('last_seen_at')
                                ->label('Last seen')
                                ->since()
                                ->placeholder('—'),
                            Infolists\Components\TextEntry::make('last_referer')
                                ->label('Last referer')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
                ]),

            SchemaSection::make('Audit')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('createdBy.name')
                                ->label('Created by')
                                ->placeholder('—'),
                            Infolists\Components\TextEntry::make('updatedBy.name')
                                ->label('Updated by')
                                ->placeholder('—'),
                            Infolists\Components\TextEntry::make('created_at')
                                ->dateTime(),
                            Infolists\Components\TextEntry::make('updated_at')
                                ->dateTime(),
                        ]),
                ])
                ->collapsed(),
        ]);
    }

    public static function publicUrlForRedirectTarget(?string $redirectTo): ?string
    {
        if (! filled($redirectTo)) {
            return null;
        }

        if (preg_match('#^https?://#i', $redirectTo)) {
            return $redirectTo;
        }

        return rtrim((string) config('app.url'), '/').$redirectTo;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['createdBy', 'updatedBy']);
    }

    public static function statusLabel(Website404Redirect $record): string
    {
        if ($record->is_ignored) {
            return 'Ignored';
        }

        if (filled($record->redirect_to)) {
            return 'Active redirect';
        }

        return 'Pending';
    }

    public static function statusColor(Website404Redirect $record): string
    {
        if ($record->is_ignored) {
            return 'gray';
        }

        if (filled($record->redirect_to)) {
            return 'success';
        }

        return 'warning';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data, bool $requireRedirectTarget = false): array
    {
        $normalizer = app(PathNormalizer::class);
        $validator = app(RedirectTargetValidator::class);

        try {
            $data['path'] = $normalizer->normalize($data['path'] ?? '');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'path' => $exception->getMessage(),
            ]);
        }

        $data['redirect_to'] = $validator->validate(
            $data['redirect_to'] ?? null,
            required: $requireRedirectTarget
        );

        $data['redirect_status'] = (int) ($data['redirect_status'] ?? config('website-404-redirects.default_redirect_status', 301));

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsite404Redirects::route('/'),
            'create' => Pages\CreateWebsite404Redirect::route('/create'),
            'view' => Pages\ViewWebsite404Redirect::route('/{record}'),
            'edit' => Pages\EditWebsite404Redirect::route('/{record}/edit'),
        ];
    }

    protected static function authorizer(): RedirectAdminAuthorizer
    {
        return app(RedirectAdminAuthorizer::class);
    }
}
