<?php

namespace App\Http\Controllers;

use App\Models\Bansos;
use App\Models\ProfilDesa;
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
use Illuminate\Support\Str;

class BansosExportController extends Controller
{
    // Export Single Bansos
    public function export(Bansos $bansos, Request $request)
    {
        // Ambil parameter format dari request
        $format = $request->input('format', 'pdf');

        // Load relasi untuk ekspor
        $bansos->load(['penduduk', 'jenisBansos', 'desa', 'editor']);

        // Filename base
        $filename = 'bantuan-sosial-' . Str::slug($bansos->penduduk->nama ?? 'detail');

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new BansosSingleExport($bansos),
                $filename . '.xlsx'
            );
        } else {
            // PDF dengan orientasi portrait
            $pdf = Pdf::loadView('exports.bansos', [
                'bansos' => $bansos,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ]);

            return $pdf->download($filename . '.pdf');
        }
    }

    // Export All Bansos
    public function exportAll(Request $request)
    {
        // Ambil parameter filter
        $jenis_bansos_id = $request->input('jenis_bansos_id');
        $status = $request->input('status');
        $id_desa = $request->input('id_desa');
        $prioritas = $request->input('prioritas');
        $sumber_pengajuan = $request->input('sumber_pengajuan');
        $dariTanggal = $request->input('dari_tanggal');
        $sampaiTanggal = $request->input('sampai_tanggal');
        $format = $request->input('format', 'pdf');

        // Query dasar
        $query = Bansos::with(['penduduk', 'jenisBansos', 'desa']);

        // Terapkan filter jika ada
        if ($jenis_bansos_id) {
            $query->where('jenis_bansos_id', $jenis_bansos_id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($id_desa) {
            $query->where('id_desa', $id_desa);
        }

        if ($prioritas) {
            $query->where('prioritas', $prioritas);
        }

        if ($sumber_pengajuan) {
            $query->where('sumber_pengajuan', $sumber_pengajuan);
        }

        if ($dariTanggal) {
            $query->whereDate('tanggal_pengajuan', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $query->whereDate('tanggal_pengajuan', '<=', $sampaiTanggal);
        }

        // Ambil data
        $bansosList = $query->orderBy('tanggal_pengajuan', 'desc')->get();

        // Hitung statistik
        $stats = [
            'total_bansos' => $bansosList->count(),
            'total_by_status' => $bansosList->groupBy('status')->map->count(),
            'total_by_kategori' => $bansosList->groupBy('jenisBansos.kategori')->map->count(),
            'total_by_prioritas' => $bansosList->groupBy('prioritas')->map->count(),
        ];

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new BansosListExport($bansosList),
                'daftar-bantuan-sosial-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.bansos-list', [
                'bansosList' => $bansosList,
                'stats' => $stats,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'jenis_bansos_id' => $jenis_bansos_id,
                    'status' => $status,
                    'id_desa' => $id_desa ? ProfilDesa::find($id_desa)->nama_desa : null,
                    'prioritas' => $prioritas,
                    'sumber_pengajuan' => $sumber_pengajuan,
                    'dariTanggal' => $dariTanggal ? Carbon::parse($dariTanggal)->translatedFormat('d F Y') : null,
                    'sampaiTanggal' => $sampaiTanggal ? Carbon::parse($sampaiTanggal)->translatedFormat('d F Y') : null,
                ],
            ])->setPaper('a4', 'landscape');

            return $pdf->download('daftar-bantuan-sosial-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Export bansos yang dipilih
    public function exportSelected(Request $request)
    {
        // Ambil ID bansos yang dipilih
        $selectedIds = explode(',', $request->input('ids'));

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada data bantuan sosial yang dipilih');
        }

        // Format pilihan export
        $format = $request->input('format', 'pdf');

        // Ambil data bansos
        $bansosList = Bansos::whereIn('id', $selectedIds)
            ->with(['penduduk', 'jenisBansos', 'desa'])
            ->orderBy('tanggal_pengajuan', 'desc')
            ->get();

        // Hitung statistik
        $stats = [
            'total_bansos' => $bansosList->count(),
            'total_by_status' => $bansosList->groupBy('status')->map->count(),
            'total_by_kategori' => $bansosList->groupBy('jenisBansos.kategori')->map->count(),
            'total_by_prioritas' => $bansosList->groupBy('prioritas')->map->count(),
        ];

        if ($format === 'excel') {
            return Excel::download(
                new BansosListExport($bansosList),
                'bantuan-sosial-terpilih-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default ke PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.bansos-list', [
                'bansosList' => $bansosList,
                'stats' => $stats,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'jenis_bansos_id' => null,
                    'status' => null,
                    'id_desa' => null,
                    'prioritas' => null,
                ],
            ])->setPaper('a4', 'landscape');

            return $pdf->download('bantuan-sosial-terpilih-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }
}

// Class untuk export single bansos ke Excel
class BansosSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $bansos;

    public function __construct(Bansos $bansos)
    {
        $this->bansos = $bansos;
    }

    public function collection()
    {
        return collect([$this->bansos]);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Penerima',
            'NIK',
            'Desa',
            'Jenis Bantuan',
            'Tanggal Pengajuan',
            'Status',
            'Prioritas',
            'Sumber Pengajuan',
            'Alasan Pengajuan',
        ];
    }

    public function map($bansos): array
    {
        return [
            $bansos->id,
            $bansos->penduduk->nama ?? 'N/A',
            $bansos->penduduk->nik ?? 'N/A',
            $bansos->desa->nama_desa ?? 'N/A',
            $bansos->jenisBansos->nama_bansos ?? 'N/A',
            $bansos->tanggal_pengajuan ? $bansos->tanggal_pengajuan->format('d/m/Y') : 'N/A',
            $bansos->status ?? 'N/A',
            $bansos->prioritas ?? 'N/A',
            $bansos->sumber_pengajuan ?? 'N/A',
            $bansos->alasan_pengajuan ?? 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Detail Bantuan Sosial';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Class untuk export list bansos ke Excel
class BansosListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $bansosList;

    public function __construct($bansosList)
    {
        $this->bansosList = $bansosList;
    }

    public function collection()
    {
        return $this->bansosList;
    }

    public function headings(): array
    {
        return [
            'No',
            'Penerima',
            'NIK',
            'Desa',
            'Jenis Bantuan',
            'Kategori',
            'Tanggal Pengajuan',
            'Status',
            'Prioritas',
            'Sumber Pengajuan',
            'Alasan Pengajuan',
        ];
    }

    public function map($bansos): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $bansos->penduduk->nama ?? 'N/A',
            $bansos->penduduk->nik ?? 'N/A',
            $bansos->desa->nama_desa ?? 'N/A',
            $bansos->jenisBansos->nama_bansos ?? 'N/A',
            $bansos->jenisBansos->kategori ?? 'N/A',
            $bansos->tanggal_pengajuan ? $bansos->tanggal_pengajuan->format('d/m/Y') : 'N/A',
            $bansos->status ?? 'N/A',
            $bansos->prioritas ?? 'N/A',
            $bansos->sumber_pengajuan === 'admin' ? 'Admin/Petugas Desa' : 'Pengajuan Warga',
            $bansos->alasan_pengajuan ?? 'N/A',
        ];
    }

    public function title(): string
    {
        return 'Daftar Bantuan Sosial';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}