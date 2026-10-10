<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentPeriodResource\Pages;
use App\Models\AssessmentPeriod;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class AssessmentPeriodResource extends Resource
{
    protected static ?string $model = AssessmentPeriod::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Assessment Period Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('period_name')
                            ->required()
                            ->label('Period Name')
                            ->maxLength(255),
                        TextInput::make('fiscal_month')
                            ->required()
                            ->label('Fiscal Month')
                            ->numeric(),
                        TextInput::make('fiscal_year')
                            ->required()
                            ->label('Fiscal Year')
                            ->numeric(),
                        DateTimePicker::make('assessment_date')
                            ->required()
                            ->label('Assessment Date'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_name')->searchable(),
                TextColumn::make('fiscal_month')->label('Month'),
                TextColumn::make('fiscal_year')->label('Year'),
                TextColumn::make('assessment_date')->dateTime(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentPeriods::route('/'),
            'create' => Pages\CreateAssessmentPeriod::route('/create'),
            'edit' => Pages\EditAssessmentPeriod::route('/{record}/edit'),
        ];
    }
}
