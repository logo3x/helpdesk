<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('asset_tag'),
                TextInput::make('hostname'),
                TextInput::make('serial_number'),
                TextInput::make('type')
                    ->required()
                    ->default('desktop'),
                TextInput::make('manufacturer'),
                TextInput::make('model'),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Select::make('department_id')
                    ->relationship('department', 'name'),
                TextInput::make('os_name'),
                TextInput::make('os_version'),
                TextInput::make('os_architecture'),
                TextInput::make('cpu_cores')
                    ->numeric(),
                TextInput::make('cpu_model'),
                TextInput::make('ram_mb')
                    ->numeric(),
                TextInput::make('disk_total_gb')
                    ->numeric(),
                TextInput::make('gpu_info'),
                TextInput::make('ip_address'),
                TextInput::make('mac_address'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                DateTimePicker::make('last_scan_at'),
            ]);
    }
}
