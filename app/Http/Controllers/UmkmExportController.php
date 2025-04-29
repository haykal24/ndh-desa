<?php

namespace App\Http\Controllers;

use App\Models\Umkm;
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

class UmkmExportController extends Controller
{
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $kategori = $request->input('kategori');
        $isVerified = $request->input('is_verified');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $format = $request->input('format', 'pdf');

        // Query data UMKM
        $query = Umkm::query()->with(['penduduk', 'desa']);

        // Filter berdasarkan kategori jika ada
        if ($kategori) {
            $query->where('kategori', $kategori);
        }

        // Filter berdasarkan status verifikasi jika ada
        if ($isVerified !== null && $isVerified !== '') {
            $query->where('is_verified', $isVerified);
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
        $umkmList = $query->orderBy('created_at', 'desc')->get();

        // Ekspor sesuai format
        if ($format === 'excel') {
            return Excel::download(
                new UmkmListExport($umkmList),
                'umkm-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF
            $pdf = Pdf::loadView('exports.umkm-list', [
                'umkmList' => $umkmList,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => $kategori ?: 'Semua Kategori',
                    'is_verified' => $isVerified === '1' ? 'Terverifikasi' : ($isVerified === '0' ? 'Belum Terverifikasi' : 'Semua Status'),
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download('umkm-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    public function exportSelected(Request $request)
    {
        // Ambil ID UMKM yang dipilih
        $selectedIds = explode(',', $request->get('ids'));

        // Ambil parameter filter tanggal
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada UMKM yang dipilih');
        }

        // Query dasar
        $query = Umkm::with(['penduduk', 'desa'])
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

        // Ambil data UMKM
        $umkmList = $query->orderBy('created_at', 'desc')->get();

        // Format pilihan ekspor
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new UmkmListExport($umkmList),
                'umkm-terpilih-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF
            $pdf = Pdf::loadView('exports.umkm-list', [
                'umkmList' => $umkmList,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => 'UMKM Terpilih',
                    'is_verified' => null,
                    'dari_tanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampai_tanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ],
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download('umkm-terpilih-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }
}

// Class untuk ekspor list UMKM ke Excel
class UmkmListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $umkmList;

    public function __construct($umkmList)
    {
        $this->umkmList = $umkmList;
    }

    public function collection()
    {
        return $this->umkmList;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Usaha',
            'Pemilik',
            'Kategori',
            'Produk/Layanan',
            'Lokasi Usaha',
            'Kontak WhatsApp',
            'Status Verifikasi',
            'Desa',
            'Terdaftar Pada',
        ];
    }

    public function map($umkm): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $umkm->nama_usaha,
            $umkm->penduduk->nama ?? 'N/A',
            $umkm->kategori ?? 'N/A',
            $umkm->produk,
            $umkm->lokasi ?? 'N/A',
            $umkm->kontak_whatsapp,
            $umkm->is_verified ? 'Terverifikasi' : 'Belum Terverifikasi',
            $umkm->desa->nama_desa ?? 'N/A',
            $umkm->created_at->format('d/m/Y H:i'),
        ];
    }

    public function title(): string
    {
        return 'Daftar UMKM';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}