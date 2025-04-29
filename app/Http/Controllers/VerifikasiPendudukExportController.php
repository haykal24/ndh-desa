<?php

namespace App\Http\Controllers;

use App\Models\VerifikasiPenduduk;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class VerifikasiPendudukExportController extends Controller
{
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $status = $request->input('status');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $format = $request->input('format', 'pdf');

        // Query data verifikasi penduduk
        $query = VerifikasiPenduduk::query()->with(['user']);

        // Filter berdasarkan status jika ada
        if ($status) {
            $query->where('status', $status);
        }

        // Filter berdasarkan tanggal jika ada
        if ($dariTanggal) {
            $dariTanggal = Carbon::parse($dariTanggal)->startOfDay();
            $query->where('created_at', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $sampaiTanggal = Carbon::parse($sampaiTanggal)->endOfDay();
            $query->where('created_at', '<=', $sampaiTanggal);
        }

        // Ambil data
        $verifikasiList = $query->orderBy('created_at', 'desc')->get();

        // Ekspor sesuai format
        if ($format === 'excel') {
            return Excel::download(
                new VerifikasiPendudukListExport($verifikasiList),
                'verifikasi-penduduk-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF
            $pdf = Pdf::loadView('exports.verifikasi-penduduk-list', [
                'verifikasiList' => $verifikasiList,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'status' => $status ? ucfirst($status) : 'Semua Status',
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
            ]);

            return $pdf->download('verifikasi-penduduk-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    public function exportSelected(Request $request)
    {
        // Ambil ID verifikasi yang dipilih
        $selectedIds = explode(',', $request->get('ids'));

        // Ambil parameter filter tanggal
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada verifikasi penduduk yang dipilih');
        }

        // Query dasar
        $query = VerifikasiPenduduk::with(['user'])
            ->whereIn('id', $selectedIds);

        // Terapkan filter tanggal jika ada
        if ($dariTanggal) {
            $dariTanggal = Carbon::parse($dariTanggal)->startOfDay();
            $query->where('created_at', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $sampaiTanggal = Carbon::parse($sampaiTanggal)->endOfDay();
            $query->where('created_at', '<=', $sampaiTanggal);
        }

        // Ambil data verifikasi
        $verifikasiList = $query->orderBy('created_at', 'desc')->get();

        // Format pilihan ekspor
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new VerifikasiPendudukListExport($verifikasiList),
                'verifikasi-penduduk-terpilih-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF
            $pdf = Pdf::loadView('exports.verifikasi-penduduk-list', [
                'verifikasiList' => $verifikasiList,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'status' => 'Verifikasi Terpilih',
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
            ]);

            return $pdf->download('verifikasi-penduduk-terpilih-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }
}

// Class untuk ekspor list verifikasi penduduk ke Excel
class VerifikasiPendudukListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $verifikasiList;

    public function __construct($verifikasiList)
    {
        $this->verifikasiList = $verifikasiList;
    }

    public function collection()
    {
        return $this->verifikasiList;
    }

    public function headings(): array
    {
        return [
            'No',
            'Pengaju',
            'NIK',
            'Nomor KK',
            'Nama',
            'Jenis Kelamin',
            'Alamat',
            'Tanggal Pengajuan',
            'Status',
            'Catatan',
        ];
    }

    public function map($verifikasi): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $verifikasi->user ? $verifikasi->user->name : 'Pengajuan Mandiri',
            $verifikasi->nik,
            $verifikasi->kk,
            $verifikasi->nama,
            $verifikasi->jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan',
            $verifikasi->alamat,
            $verifikasi->created_at ? Carbon::parse($verifikasi->created_at)->format('d/m/Y H:i') : '-',
            ucfirst($verifikasi->status),
            $verifikasi->catatan ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Daftar Verifikasi Penduduk';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}