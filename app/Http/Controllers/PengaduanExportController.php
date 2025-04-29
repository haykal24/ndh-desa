<?php

namespace App\Http\Controllers;

use App\Models\Pengaduan;
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

class PengaduanExportController extends Controller
{
    /**
     * Export single pengaduan
     */
    public function export(Pengaduan $pengaduan, Request $request)
    {
        // Load relasi yang valid
        $pengaduan->load(['penduduk', 'desa', 'petugas']);

        $format = $request->input('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new PengaduanSingleExport($pengaduan),
                'pengaduan-' . $pengaduan->id . '.xlsx'
            );
        } else {
            $pdf = PDF::loadView('exports.pengaduan', [
                'pengaduan' => $pengaduan
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download('pengaduan-' . $pengaduan->id . '.pdf');
        }
    }

    /**
     * Export semua pengaduan dengan filter periode
     */
    public function exportAll(Request $request)
    {
        // Debug untuk memastikan parameter sampai
        // dd($request->all());

        $query = Pengaduan::query()
            ->with(['penduduk', 'desa', 'petugas'])
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan status jika ada
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan tanggal
        $periodLabel = 'Semua Waktu';

        if ($request->filled('dari_tanggal')) {
            $dariTanggal = Carbon::parse($request->dari_tanggal)->startOfDay();
            $query->where('created_at', '>=', $dariTanggal);

            if ($request->filled('sampai_tanggal')) {
                $sampaiTanggal = Carbon::parse($request->sampai_tanggal)->endOfDay();
                $query->where('created_at', '<=', $sampaiTanggal);

                $periodLabel = 'Periode ' . $dariTanggal->format('d/m/Y') . ' - ' . $sampaiTanggal->format('d/m/Y');

                // Cek apakah periode khusus
                if ($dariTanggal->isSameDay($sampaiTanggal)) {
                    $periodLabel = 'Tanggal ' . $dariTanggal->format('d/m/Y');
                } elseif ($dariTanggal->format('Y-m') === $sampaiTanggal->format('Y-m') &&
                          $dariTanggal->day === 1 &&
                          $sampaiTanggal->day === $sampaiTanggal->copy()->endOfMonth()->day) {
                    $periodLabel = 'Bulan ' . $dariTanggal->format('F Y');
                } elseif ($dariTanggal->year === $sampaiTanggal->year &&
                          $dariTanggal->month === 1 && $dariTanggal->day === 1 &&
                          $sampaiTanggal->month === 12 && $sampaiTanggal->day === 31) {
                    $periodLabel = 'Tahun ' . $dariTanggal->format('Y');
                }
            } else {
                $periodLabel = 'Dari ' . $dariTanggal->format('d/m/Y');
            }
        } elseif ($request->filled('sampai_tanggal')) {
            $sampaiTanggal = Carbon::parse($request->sampai_tanggal)->endOfDay();
            $query->where('created_at', '<=', $sampaiTanggal);
            $periodLabel = 'Sampai ' . $sampaiTanggal->format('d/m/Y');
        }

        // Ambil data
        $pengaduans = $query->get();

        // Format yang diminta
        $format = $request->input('format', 'pdf');

        if ($format === 'excel') {
            return Excel::download(
                new PengaduanListExport($pengaduans),
                'pengaduan-' . now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            $pdf = PDF::loadView('exports.pengaduan-list', [
                'pengaduans' => $pengaduans,
                'filter' => [
                    'status' => $request->status,
                    'periode' => $periodLabel,
                    'count' => $pengaduans->count(),
                ],
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download('pengaduan-' . now()->format('Y-m-d') . '.pdf');
        }
    }

    /**
     * Export pengaduan yang dipilih
     */
    public function exportSelected(Request $request)
    {
        // Ambil ID pengaduan yang dipilih
        $selectedIds = $request->input('ids', []);

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada pengaduan yang dipilih');
        }

        // Query dasar
        $query = Pengaduan::with(['penduduk', 'desa', 'petugas'])
            ->whereIn('id', $selectedIds);

        // Filter berdasarkan tanggal
        $periodLabel = 'Semua Waktu';

        if ($request->filled('dari_tanggal')) {
            $dariTanggal = Carbon::parse($request->dari_tanggal)->startOfDay();
            $query->where('created_at', '>=', $dariTanggal);

            if ($request->filled('sampai_tanggal')) {
                $sampaiTanggal = Carbon::parse($request->sampai_tanggal)->endOfDay();
                $query->where('created_at', '<=', $sampaiTanggal);

                $periodLabel = 'Periode ' . $dariTanggal->format('d/m/Y') . ' - ' . $sampaiTanggal->format('d/m/Y');
            } else {
                $periodLabel = 'Dari ' . $dariTanggal->format('d/m/Y');
            }
        } elseif ($request->filled('sampai_tanggal')) {
            $sampaiTanggal = Carbon::parse($request->sampai_tanggal)->endOfDay();
            $query->where('created_at', '<=', $sampaiTanggal);
            $periodLabel = 'Sampai ' . $sampaiTanggal->format('d/m/Y');
        }

        // Ambil data
        $pengaduans = $query->orderBy('created_at', 'desc')->get();

        // Format pilihan export
        $format = $request->get('format', 'pdf');

        // Filename
        $filename = 'pengaduan-terpilih-' . Carbon::now()->format('Y-m-d');

        // Ekspor sesuai format
        if ($format === 'excel') {
            return Excel::download(
                new PengaduanListExport($pengaduans),
                $filename . '.xlsx'
            );
        } else {
            // Default PDF
            $pdf = Pdf::loadView('exports.pengaduan-list', [
                'pengaduans' => $pengaduans,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'jenis' => 'Pengaduan Terpilih',
                    'periode' => $periodLabel,
                    'count' => $pengaduans->count(),
                ],
            ])
            ->setPaper('a4', 'landscape'); // Set ke orientasi landscape

            return $pdf->download($filename . '.pdf');
        }
    }
}

/**
 * Class untuk export single pengaduan ke Excel
 */
class PengaduanSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $pengaduan;

    public function __construct(Pengaduan $pengaduan)
    {
        $this->pengaduan = $pengaduan;
    }

    public function collection()
    {
        return collect([$this->pengaduan]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Judul',
            'Pelapor',
            'Desa',
            'Kategori',
            'Prioritas',
            'Status',
            'Isi Laporan',
            'Tanggal Dibuat',
            'Tanggapan',
            'Ditanggapi Oleh',
            'Tanggal Tanggapan',
        ];
    }

    public function map($pengaduan): array
    {
        return [
            $pengaduan->id,
            $pengaduan->judul,
            $pengaduan->penduduk ? $pengaduan->penduduk->nama : 'Anonim',
            $pengaduan->desa ? $pengaduan->desa->nama_desa : '-',
            $pengaduan->kategori ?: 'Tidak Terkategori',
            $pengaduan->prioritas,
            $pengaduan->status,
            $pengaduan->deskripsi,
            $pengaduan->created_at->format('d/m/Y H:i'),
            $pengaduan->tanggapan ?: '-',
            $pengaduan->petugas ? $pengaduan->petugas->name : '-',
            $pengaduan->tanggal_tanggapan ? $pengaduan->tanggal_tanggapan->format('d/m/Y H:i') : '-',
        ];
    }

    public function title(): string
    {
        return 'Detail Pengaduan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 8],
            'B' => ['width' => 25],
            'C' => ['width' => 20],
            'D' => ['width' => 20],
            'E' => ['width' => 15],
            'F' => ['width' => 15],
            'G' => ['width' => 15],
            'H' => ['width' => 40],
            'I' => ['width' => 15],
            'J' => ['width' => 40],
            'K' => ['width' => 20],
            'L' => ['width' => 20],
        ];
    }
}

/**
 * Class untuk export list pengaduan ke Excel
 */
class PengaduanListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $pengaduans;

    public function __construct($pengaduans)
    {
        $this->pengaduans = $pengaduans;
    }

    public function collection()
    {
        return $this->pengaduans;
    }

    public function headings(): array
    {
        return [
            'No',
            'ID',
            'Judul',
            'Pelapor',
            'Desa',
            'Kategori',
            'Prioritas',
            'Status',
            'Tanggal Dibuat',
        ];
    }

    public function map($pengaduan): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $pengaduan->id,
            $pengaduan->judul,
            $pengaduan->penduduk ? $pengaduan->penduduk->nama : 'Anonim',
            $pengaduan->desa ? $pengaduan->desa->nama_desa : '-',
            $pengaduan->kategori ?: 'Tidak Terkategori',
            $pengaduan->prioritas,
            $pengaduan->status,
            $pengaduan->created_at->format('d/m/Y H:i'),
        ];
    }

    public function title(): string
    {
        return 'Daftar Pengaduan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 5],
            'B' => ['width' => 8],
            'C' => ['width' => 35],
            'D' => ['width' => 20],
            'E' => ['width' => 20],
            'F' => ['width' => 15],
            'G' => ['width' => 15],
            'H' => ['width' => 15],
            'I' => ['width' => 15],
        ];
    }
}