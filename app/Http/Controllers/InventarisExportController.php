<?php

namespace App\Http\Controllers;

use App\Models\Inventaris;
use App\Models\ProfilDesa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class InventarisExportController extends Controller
{
    // Export Single Item Inventaris
    public function export(Inventaris $inventaris, Request $request)
    {
        // Ambil parameter format dari request
        $format = $request->input('format', 'pdf');

        // Load relasi
        $inventaris->load(['desa', 'creator']);

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new InventarisSingleExport($inventaris),
                'inventaris-' . $inventaris->id . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi portrait (tidak perlu setPaper)
            $pdf = Pdf::loadView('exports.inventaris-single', [
                'inventaris' => $inventaris,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ]);

            return $pdf->download('inventaris-' . $inventaris->id . '.pdf');
        }
    }

    // Export All Inventaris
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $kategori = $request->input('kategori');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $format = $request->input('format', 'pdf');

        // Debugging untuk melihat nilai parameter
        // dd(['dari' => $dariTanggal, 'sampai' => $sampaiTanggal]);

        // Query data inventaris
        $query = Inventaris::query()->with(['desa', 'creator']);

        // Filter berdasarkan kategori jika ada
        if ($kategori && $kategori !== 'Semua Kategori') {
            $query->where('kategori', $kategori);
        }

        // Filter berdasarkan tanggal jika ada
        if ($dariTanggal) {
            $dariTanggal = Carbon::parse($dariTanggal)->startOfDay();
            $query->where('tanggal_perolehan', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $sampaiTanggal = Carbon::parse($sampaiTanggal)->endOfDay();
            $query->where('tanggal_perolehan', '<=', $sampaiTanggal);
        }

        // Ambil data
        $inventaris = $query->get();

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new InventarisListExport($inventaris),
                'inventaris-list-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.inventaris-list', [
                'inventaris' => $inventaris,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => $kategori,
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
            ])->setPaper('a4', 'landscape');

            return $pdf->download('inventaris-list-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Export inventaris yang dipilih
    public function exportSelected(Request $request)
    {
        // Ambil ID inventaris yang dipilih
        $selectedIds = explode(',', $request->get('ids'));

        // Ambil parameter filter tanggal
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada inventaris yang dipilih');
        }

        // Query dasar
        $query = Inventaris::with(['desa', 'creator'])
            ->whereIn('id', $selectedIds);

        // Terapkan filter tanggal jika ada
        if ($dariTanggal) {
            $dariTanggal = Carbon::parse($dariTanggal)->startOfDay();
            $query->where('tanggal_perolehan', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $sampaiTanggal = Carbon::parse($sampaiTanggal)->endOfDay();
            $query->where('tanggal_perolehan', '<=', $sampaiTanggal);
        }

        // Ambil data inventaris
        $inventarisList = $query->orderBy('tanggal_perolehan', 'desc')->get();

        // Hitung total nilai dan jumlah unit
        $totalNilai = $inventarisList->sum('nominal_harga');
        $totalUnit = $inventarisList->sum('jumlah');

        // Format pilihan export
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new InventarisListExport($inventarisList),
                'inventaris-terpilih-' . now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.inventaris-list', [
                'inventaris' => $inventarisList,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => null,
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
                'totalNilai' => $totalNilai,
                'totalUnit' => $totalUnit,
            ])->setPaper('a4', 'landscape');

            return $pdf->download('inventaris-terpilih-' . now()->format('Y-m-d') . '.pdf');
        }
    }

    // Helper untuk export ke CSV
    private function exportToCsv($records)
    {
        $csvData = [];
        $csvData[] = [
            'Desa',
            'Kode Barang',
            'Nama Barang',
            'Kategori',
            'Jumlah',
            'Kondisi',
            'Tanggal Perolehan',
            'Nominal Harga',
            'Sumber Dana',
            'Lokasi',
            'Status',
            'Keterangan',
            'Dibuat Oleh',
            'Tanggal Dibuat'
        ];

        foreach ($records as $record) {
            $csvData[] = [
                $record->desa->nama_desa ?? '-',
                $record->kode_barang,
                $record->nama_barang,
                $record->kategori,
                $record->jumlah,
                $record->kondisi,
                $record->tanggal_perolehan->format('d/m/Y'),
                $record->nominal_harga,
                $record->sumber_dana,
                $record->lokasi,
                $record->status,
                $record->keterangan,
                $record->creator->name ?? 'Sistem',
                $record->created_at->format('d/m/Y H:i:s'),
            ];
        }

        $filename = 'inventaris-desa-export-' . now()->format('Y-m-d-His') . '.csv';

        $callback = function() use ($csvData) {
            $file = fopen('php://output', 'w');
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}

// Class untuk export single inventaris ke Excel
class InventarisSingleExport implements WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $inventaris;

    public function __construct(Inventaris $inventaris)
    {
        $this->inventaris = $inventaris;
    }

    public function collection()
    {
        return collect([$this->inventaris]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama Desa',
            'Nama Barang',
            'Kategori',
            'Jumlah',
            'Tanggal Perolehan',
            'Kondisi',
            'Keterangan',
        ];
    }

    public function map($inventaris): array
    {
        // Get desa name
        $namaDesa = 'N/A';
        if ($inventaris->desa) {
            $namaDesa = $inventaris->desa->nama_desa;
        } elseif (isset($inventaris->desa_id)) {
            $desa = ProfilDesa::find($inventaris->desa_id);
            $namaDesa = $desa ? $desa->nama_desa : 'N/A';
        }

        // Format tanggal
        $tanggalPerolehan = $inventaris->tanggal_perolehan ?
            Carbon::parse($inventaris->tanggal_perolehan)->format('d/m/Y') : 'N/A';

        return [
            $inventaris->id,
            $namaDesa,
            $inventaris->nama_barang,
            $inventaris->kategori,
            $inventaris->jumlah,
            $tanggalPerolehan,
            $inventaris->kondisi,
            $inventaris->keterangan ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Detail Inventaris';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Class untuk export list inventaris ke Excel
class InventarisListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $inventaris;

    public function __construct($inventaris)
    {
        $this->inventaris = $inventaris;
    }

    public function collection()
    {
        return $this->inventaris;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Desa',
            'Nama Barang',
            'Kategori',
            'Jumlah',
            'Tanggal Perolehan',
            'Kondisi',
            'Keterangan',
        ];
    }

    public function map($inventaris): array
    {
        static $counter = 0;
        $counter++;

        // Get desa name
        $namaDesa = 'N/A';
        if ($inventaris->desa) {
            $namaDesa = $inventaris->desa->nama_desa;
        } elseif (isset($inventaris->desa_id)) {
            $desa = ProfilDesa::find($inventaris->desa_id);
            $namaDesa = $desa ? $desa->nama_desa : 'N/A';
        }

        // Format tanggal
        $tanggalPerolehan = $inventaris->tanggal_perolehan ?
            Carbon::parse($inventaris->tanggal_perolehan)->format('d/m/Y') : 'N/A';

        return [
            $counter,
            $namaDesa,
            $inventaris->nama_barang,
            $inventaris->kategori,
            $inventaris->jumlah,
            $tanggalPerolehan,
            $inventaris->kondisi,
            $inventaris->keterangan ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Daftar Inventaris';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
