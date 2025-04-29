<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StrukturPemerintahanResource\Pages;
use App\Models\StrukturPemerintahan;
use App\Models\ProfilDesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\View;
use Filament\Forms\Components\CheckboxList;

class StrukturPemerintahanResource extends Resource
{
    protected static ?string $model = StrukturPemerintahan::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationParentItem = 'Profil Desa';

    protected static ?string $navigationGroup = 'Desa';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Struktur Pemerintahan';
    }

    public static function getPluralLabel(): string
    {
        return 'Struktur Pemerintahan';
    }

    public static function form(Form $form): Form
    {
        // Coba dapatkan desa default (pertama)
        $defaultDesa = ProfilDesa::first()?->id;
        
        return $form
            ->schema([
                Forms\Components\Select::make('profil_desa_id')
                    ->label('Desa')
                    ->options(ProfilDesa::pluck('nama_desa', 'id'))
                    ->searchable()
                    ->required()
                    ->default($defaultDesa), // Set default desa

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => Auth::id()),

                Forms\Components\Tabs::make('Tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Kepala Desa')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Card::make()
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('nama_kepala_desa')
                                            ->label('Nama Kepala Desa')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('periode_jabatan')
                                            ->label('Periode Jabatan')
                                            ->maxLength(100)
                                            ->placeholder('Contoh: 2020-2025'),
                                        Forms\Components\FileUpload::make('foto_kepala_desa')
                                            ->label('Foto Kepala Desa')
                                            ->image()
                                            ->imageEditor()
                                            ->imageCropAspectRatio('3:4')
                                            ->imageResizeTargetWidth('300')
                                            ->imageResizeTargetHeight('400')
                                            ->directory('uploads/desa/kepala-desa')
                                            ->helperText('Format yang disarankan: 3:4 (portrait)')
                                            ->columnSpan(2),
                                        Forms\Components\RichEditor::make('sambutan_kepala_desa')
                                            ->label('Sambutan Kepala Desa')
                                            ->fileAttachmentsDisk('public')
                                            ->fileAttachmentsDirectory('uploads/desa')
                                            ->columnSpan(2),
                                    ]),
                                    
                                // Card untuk program kerja dengan rich editor
                                Card::make()
                                    ->schema([
                                        Forms\Components\RichEditor::make('program_kerja')
                                            ->label('Program Kerja Kepala Desa')
                                            ->helperText('Program kerja utama selama masa jabatan')
                                            ->placeholder('Masukkan program kerja utama kepala desa...')
                                            ->fileAttachmentsDisk('public')
                                            ->fileAttachmentsDirectory('uploads/desa')
                                            ->toolbarButtons([
                                                'blockquote',
                                                'bold',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'italic',
                                                'link',
                                                'orderedList',
                                                'redo',
                                                'strike',
                                                'underline',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                            
                                        Forms\Components\RichEditor::make('prioritas_program')
                                            ->label('Prioritas Program Kepala Desa')
                                            ->helperText('Jabarkan prioritas program yang akan dilaksanakan')
                                            ->placeholder('Masukkan prioritas program yang akan dilaksanakan...')
                                            ->fileAttachmentsDisk('public')
                                            ->fileAttachmentsDirectory('uploads/desa')
                                            ->toolbarButtons([
                                                'blockquote',
                                                'bold',
                                                'bulletList',
                                                'h2',
                                                'h3',
                                                'italic',
                                                'link',
                                                'orderedList',
                                                'redo',
                                                'strike',
                                                'underline',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Bagan Struktur')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                Forms\Components\FileUpload::make('bagan_struktur')
                                    ->label('Bagan Struktur Pemerintahan')
                                    ->image()
                                    ->imageResizeTargetWidth('1200')
                                    ->directory('uploads/desa/struktur')
                                    ->helperText('Upload gambar bagan struktur organisasi desa'),
                            ]),
                            
                        Forms\Components\Tabs\Tab::make('Aparat Desa')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Forms\Components\CheckboxList::make('aparat_desa')
                                    ->label('Pilih Aparat Desa')
                                    ->options(function ($record) {
                                        // Get all aparat desa entries
                                        if (!$record) return [];
                                        
                                        return \App\Models\AparatDesa::orderBy('urutan')
                                            ->get()
                                            ->mapWithKeys(function ($aparat) {
                                                return [$aparat->id => $aparat->nama . ' - ' . $aparat->jabatan];
                                            });
                                    })
                                    ->columnSpanFull(),
                                
                                Forms\Components\Section::make('Daftar Aparat Desa')
                                    ->schema([
                                        Forms\Components\Placeholder::make('aparat_desa_list')
                                            ->label('')
                                            ->content(function ($record) {
                                                if (!$record || !$record->aparatDesa || $record->aparatDesa->isEmpty()) {
                                                    return 'Belum ada aparat desa yang dipilih.';
                                                }
                                                
                                                $html = '<div class="space-y-4">';
                                                foreach ($record->aparatDesa()->orderBy('urutan', 'asc')->get() as $aparat) {
                                                    $foto = $aparat->foto 
                                                        ? '<img src="'.asset('storage/'.$aparat->foto).'" class="w-12 h-12 rounded-full mr-4">' 
                                                        : '<div class="w-12 h-12 rounded-full bg-gray-200 mr-4 flex items-center justify-center"><span>No Img</span></div>';
                                                        
                                                    $html .= '
                                                    <div class="flex items-center p-4 bg-white rounded-lg shadow">
                                                        '.$foto.'
                                                        <div>
                                                            <h3 class="font-medium">'.$aparat->nama.'</h3>
                                                            <p class="text-sm text-gray-500">'.$aparat->jabatan.'</p>
                                                        </div>
                                                    </div>';
                                                }
                                                $html .= '</div>';
                                                
                                                return new \Illuminate\Support\HtmlString($html);
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => $record !== null),
                                
                                Forms\Components\Placeholder::make('aparat_desa_link')
                                    ->label('Kelola Aparat Desa')
                                    ->content(new \Illuminate\Support\HtmlString(
                                        'Untuk mengelola data lengkap aparat desa, silakan menuju ke 
                                        <a href="'.url('/admin/aparat-desas').'" target="_blank" class="text-primary-500 hover:text-primary-700 font-medium">
                                            Halaman Aparat Desa <span class="text-xs">↗</span>
                                        </a>'
                                    ))
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('profilDesa.nama_desa')
                    ->label('Desa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ImageColumn::make('foto_kepala_desa')
                    ->label('Foto Kades')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                Tables\Columns\TextColumn::make('nama_kepala_desa')
                    ->label('Nama Kepala Desa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('periode_jabatan')
                    ->label('Periode Jabatan'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Dibuat Oleh'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make()
                    ->visible(fn ($record) => $record->trashed()),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn ($record) => $record->trashed()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStrukturPemerintahans::route('/'),
            'create' => Pages\CreateStrukturPemerintahan::route('/create'),
            'view' => Pages\ViewStrukturPemerintahan::route('/{record}'),
            'edit' => Pages\EditStrukturPemerintahan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
} 