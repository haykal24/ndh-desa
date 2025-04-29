<?php

namespace App\Http\Controllers;

use App\Models\LayananDesa;
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
use Illuminate\Support\Str;

class LayananExportController extends Controller
{
    // Export Single Layanan Desa
    public function export(LayananDesa $layanan, Request $request)
    {
        // Ambil parameter format dari request
        $format = $request->input('format', 'pdf');

        // Load relasi
        $layanan->load(['desa', 'creator']);

        // Filename base
        $filename = 'layanan-' . Str::slug($layanan->nama_layanan);

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new LayananSingleExport($layanan),
                $filename . '.xlsx'
            );
        } else {
            // Default PDF
            $pdf = Pdf::loadView('exports.layanan', [
                'layanan' => $layanan,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download($filename . '.pdf');
        }
    }

    // Export All Layanan
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $kategori = $request->input('kategori');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $format = $request->input('format', 'pdf');

        // Query data layanan
        $query = LayananDesa::query()->with(['desa', 'creator']);

        // Filter berdasarkan kategori jika ada
        if ($kategori && $kategori !== 'Semua Kategori') {
            $query->where('kategori', $kategori);
        }

        // Filter berdasarkan tanggal jika ada
        if ($dariTanggal) {
            $query->whereDate('created_at', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $query->whereDate('created_at', '<=', $sampaiTanggal);
        }

        // Ambil data
        $layanans = $query->get();

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new LayananListExport($layanans),
                'layanan-desa-list-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF
            $pdf = Pdf::loadView('exports.layanan-list', [
                'layanans' => $layanans,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => $kategori,
                    'dari_tanggal' => $dariTanggal ? Carbon::parse($dariTanggal)->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? Carbon::parse($sampaiTanggal)->translatedFormat('d F Y') : null,
                ],
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download('layanan-desa-list-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Export layanan yang dipilih
    public function exportSelected(Request $request)
    {
        // Perbaikan: Konversi string ids menjadi array
        $ids = explode(',', $request->input('ids'));

        $format = $request->input('format', 'pdf');
        $layanans = LayananDesa::whereIn('id', $ids)->get();

        if ($format === 'excel') {
            return Excel::download(new LayananListExport($layanans), 'layanan-desa.xlsx');
        }

        // Default ke PDF jika format tidak dispesifikasi atau selain excel
        $pdf = Pdf::loadView('exports.layanan-list', [
            'layanans' => $layanans,
            'tanggal' => Carbon::now()->translatedFormat('d F Y'),
        ])
        ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

        return $pdf->download('layanan-desa.pdf');
    }

    // Helper untuk export ke CSV
    private function exportToCsv($records)
    {
        $csvData = [];
        $csvData[] = [
            'Nama Layanan',
            'Kategori',
            'Desa',
            'Deskripsi',
            'Biaya',
            'Lokasi Layanan',
            'Jadwal Pelayanan',
            'Kontak Layanan',
            'Persyaratan',
            'Prosedur',
            'Dibuat Oleh',
            'Tanggal Dibuat'
        ];

        foreach ($records as $record) {
            // Format persyaratan sebagai string
            $persyaratan = collect($record->persyaratan ?? [])
                ->map(function ($item) {
                    return "- {$item['dokumen']}" . (isset($item['keterangan']) && !empty($item['keterangan']) ? ": {$item['keterangan']}" : "");
                })
                ->join(" | ");

            // Format prosedur sebagai string
            $prosedur = collect($record->prosedur ?? [])
                ->map(function ($item, $index) {
                    return ($index + 1) . ". {$item['langkah']}" . (isset($item['keterangan']) && !empty($item['keterangan']) ? ": {$item['keterangan']}" : "");
                })
                ->join(" | ");

            // Format biaya
            $biaya = $record->biaya == 0 ? 'Gratis' : 'Rp ' . number_format($record->biaya, 0, ',', '.');

            $csvData[] = [
                $record->nama_layanan,
                $record->kategori,
                $record->desa->nama_desa ?? '-',
                strip_tags($record->deskripsi),
                $biaya,
                $record->lokasi_layanan ?? '-',
                $record->jadwal_pelayanan ?? '-',
                $record->kontak_layanan ?? '-',
                $persyaratan,
                $prosedur,
                $record->creator->name ?? 'Sistem',
                $record->created_at->format('d/m/Y H:i:s'),
            ];
        }

        $filename = 'layanan-desa-export-' . now()->format('Y-m-d-His') . '.csv';

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

// Class untuk export single layanan ke Excel
class LayananSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $layanan;

    public function __construct(LayananDesa $layanan)
    {
        $this->layanan = $layanan;
    }

    public function collection()
    {
        return collect([$this->layanan]);
    }

    public function headings(): array
    {
        return [
            'Nama Layanan',
            'Kategori',
            'Desa',
            'Deskripsi',
            'Biaya',
            'Lokasi Layanan',
            'Jadwal Pelayanan',
            'Kontak Layanan',
            'Dibuat Oleh',
            'Tanggal Dibuat'
        ];
    }

    public function map($layanan): array
    {
        // Format biaya
        $biaya = $layanan->biaya == 0 ? 'Gratis' : 'Rp ' . number_format($layanan->biaya, 0, ',', '.');

        return [
            $layanan->nama_layanan,
            $layanan->kategori,
            $layanan->desa->nama_desa ?? 'N/A',
            strip_tags($layanan->deskripsi),
            $biaya,
            $layanan->lokasi_layanan ?? '-',
            $layanan->jadwal_pelayanan ?? '-',
            $layanan->kontak_layanan ?? '-',
            $layanan->creator->name ?? 'Sistem',
            $layanan->created_at->format('d/m/Y H:i:s'),
        ];
    }

    public function title(): string
    {
        return 'Detail Layanan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 25],
            'B' => ['width' => 15],
            'C' => ['width' => 20],
            'D' => ['width' => 40],
            'E' => ['width' => 15],
            'F' => ['width' => 20],
            'G' => ['width' => 20],
            'H' => ['width' => 20],
            'I' => ['width' => 20],
            'J' => ['width' => 20],
        ];
    }
}

// Class untuk export list layanan ke Excel
class LayananListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $layanan;

    public function __construct($layanan)
    {
        $this->layanan = $layanan;
    }

    public function collection()
    {
        return $this->layanan;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Layanan',
            'Kategori',
            'Biaya',
            'Lokasi Layanan',
            'Jadwal Pelayanan',
            'Kontak Layanan',
            'Persyaratan',
            'Prosedur',
            'Desa',
            'Dibuat Oleh',
            'Tanggal Dibuat'
        ];
    }

    public function map($layanan): array
    {
        static $counter = 0;
        $counter++;

        // Format persyaratan sebagai string
        $persyaratan = collect($layanan->persyaratan ?? [])
            ->map(function ($item) {
                return "{$item['dokumen']}";
            })
            ->join(", ");

        // Format prosedur sebagai string
        $prosedur = collect($layanan->prosedur ?? [])
            ->map(function ($item, $index) {
                return "{$item['langkah']}";
            })
            ->join(", ");

        // Format biaya
        $biaya = $layanan->biaya == 0 ? 'Gratis' : 'Rp ' . number_format($layanan->biaya, 0, ',', '.');

        return [
            $counter,
            $layanan->nama_layanan,
            $layanan->kategori,
            $biaya,
            $layanan->lokasi_layanan ?? '-',
            $layanan->jadwal_pelayanan ?? '-',
            $layanan->kontak_layanan ?? '-',
            $persyaratan ?: '-',
            $prosedur ?: '-',
            $layanan->desa->nama_desa ?? 'N/A',
            $layanan->creator->name ?? 'Sistem',
            $layanan->created_at->format('d/m/Y H:i'),
        ];
    }

    public function title(): string
    {
        return 'Daftar Layanan Desa';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 5],
            'B' => ['width' => 25],
            'C' => ['width' => 15],
            'D' => ['width' => 15],
            'E' => ['width' => 20],
            'F' => ['width' => 20],
            'G' => ['width' => 20],
            'H' => ['width' => 35],
            'I' => ['width' => 35],
            'J' => ['width' => 20],
            'K' => ['width' => 20],
            'L' => ['width' => 20],
        ];
    }
}