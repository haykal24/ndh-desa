<?php

namespace App\Http\Controllers;

use App\Models\Penduduk;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendudukExportController extends Controller
{
    // Export 1 data penduduk
    public function export(Penduduk $penduduk, Request $request)
    {
        // Format pilihan export
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new PendudukSingleExport($penduduk),
                'data-penduduk-' . Str::slug($penduduk->nama) . '.xlsx'
            );
        } else {
            $pdf = Pdf::loadView('exports.penduduk', [
                'penduduk' => $penduduk,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ]);

            // Buat nama file yang sesuai dengan nama penduduk
            $filename = 'data-penduduk-' . Str::slug($penduduk->nama) . '.pdf';

            return $pdf->download($filename);
        }
    }

    // Export semua penduduk (dengan filter opsional)
    public function exportAll(Request $request)
    {
        // Ambil parameter filter
        $jenis_kelamin = $request->get('jenis_kelamin');
        $status_perkawinan = $request->get('status_perkawinan');
        $pekerjaan = $request->get('pekerjaan');
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        // Query dasar
        $query = Penduduk::query();

        // Terapkan filter jika ada
        if ($jenis_kelamin) {
            $query->where('jenis_kelamin', $jenis_kelamin);
        }

        if ($status_perkawinan) {
            $query->where('status_perkawinan', $status_perkawinan);
        }

        if ($pekerjaan) {
            $query->where('pekerjaan', $pekerjaan);
        }

        if ($dariTanggal) {
            $query->whereDate('created_at', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $query->whereDate('created_at', '<=', $sampaiTanggal);
        }

        // Ambil data
        $pendudukList = $query->orderBy('created_at', 'desc')->get();

        // Format pilihan export
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new PendudukListExport($pendudukList),
                'laporan-penduduk-desa-' . now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.penduduk-list', [
                'pendudukList' => $pendudukList,
                'total' => $pendudukList->count(),
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filters' => [
                    'jenis_kelamin' => $jenis_kelamin,
                    'status_perkawinan' => $status_perkawinan,
                    'pekerjaan' => $pekerjaan,
                    'dariTanggal' => $dariTanggal ? Carbon::parse($dariTanggal)->translatedFormat('d F Y') : null,
                    'sampaiTanggal' => $sampaiTanggal ? Carbon::parse($sampaiTanggal)->translatedFormat('d F Y') : null,
                ]
            ])->setPaper('a4', 'landscape'); // Set landscape orientation

            return $pdf->download('laporan-penduduk-desa-' . now()->format('Y-m-d') . '.pdf');
        }
    }

    // Export penduduk yang dipilih
    public function exportSelected(Request $request)
    {
        // Ambil ID penduduk yang dipilih
        $selectedIds = explode(',', $request->get('ids'));

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada penduduk yang dipilih');
        }

        // Ambil data penduduk
        $pendudukList = Penduduk::whereIn('id', $selectedIds)
            ->orderBy('created_at', 'desc')
            ->get();

        // Format pilihan export
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new PendudukListExport($pendudukList),
                'laporan-penduduk-terpilih-' . now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.penduduk-list', [
                'pendudukList' => $pendudukList,
                'total' => $pendudukList->count(),
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filters' => [
                    'jenis_kelamin' => null,
                    'status_perkawinan' => null,
                    'pekerjaan' => null,
                    'dariTanggal' => null,
                    'sampaiTanggal' => null,
                ]
            ])->setPaper('a4', 'landscape'); // Set landscape orientation

            return $pdf->download('laporan-penduduk-terpilih-' . now()->format('Y-m-d') . '.pdf');
        }
    }
}

// Class untuk export satu penduduk ke Excel
class PendudukSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $penduduk;

    public function __construct($penduduk)
    {
        $this->penduduk = $penduduk;
    }

    public function collection()
    {
        return collect([$this->penduduk]);
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Usia',
            'Alamat',
            'RT/RW',
            'Desa/Kelurahan',
            'Kecamatan',
            'Agama',
            'Status Perkawinan',
            'Pekerjaan',
            'Pendidikan',
            'Tanggal Terdaftar'
        ];
    }

    public function map($penduduk): array
    {
        return [
            $penduduk->nik,
            $penduduk->nama,
            $penduduk->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan',
            $penduduk->tempat_lahir,
            $penduduk->tanggal_lahir ? Carbon::parse($penduduk->tanggal_lahir)->format('d/m/Y') : '-',
            $penduduk->tanggal_lahir ? Carbon::parse($penduduk->tanggal_lahir)->age . ' tahun' : '-',
            $penduduk->alamat,
            $penduduk->rt_rw,
            $penduduk->desa_kelurahan,
            $penduduk->kecamatan,
            $penduduk->agama,
            $penduduk->status_perkawinan,
            $penduduk->pekerjaan,
            $penduduk->pendidikan,
            Carbon::parse($penduduk->created_at)->format('d/m/Y H:i')
        ];
    }

    public function title(): string
    {
        return 'Data Penduduk';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Class untuk export banyak penduduk ke Excel
class PendudukListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $pendudukList;

    public function __construct($pendudukList)
    {
        $this->pendudukList = $pendudukList;
    }

    public function collection()
    {
        return $this->pendudukList;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIK',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Usia',
            'Alamat',
            'RT/RW',
            'Desa/Kelurahan',
            'Kecamatan',
            'Agama',
            'Status Perkawinan',
            'Pekerjaan',
            'Pendidikan',
            'Tanggal Terdaftar'
        ];
    }

    public function map($penduduk): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $penduduk->nik,
            $penduduk->nama,
            $penduduk->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan',
            $penduduk->tempat_lahir,
            $penduduk->tanggal_lahir ? Carbon::parse($penduduk->tanggal_lahir)->format('d/m/Y') : '-',
            $penduduk->tanggal_lahir ? Carbon::parse($penduduk->tanggal_lahir)->age . ' tahun' : '-',
            $penduduk->alamat,
            $penduduk->rt_rw,
            $penduduk->desa_kelurahan,
            $penduduk->kecamatan,
            $penduduk->agama,
            $penduduk->status_perkawinan,
            $penduduk->pekerjaan,
            $penduduk->pendidikan,
            Carbon::parse($penduduk->created_at)->format('d/m/Y H:i')
        ];
    }

    public function title(): string
    {
        return 'Daftar Penduduk';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}