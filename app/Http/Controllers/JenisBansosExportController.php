<?php

namespace App\Http\Controllers;

use App\Models\JenisBansos;
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

class JenisBansosExportController extends Controller
{
    // Export Single Jenis Bansos
    public function export(JenisBansos $jenisBansos, Request $request)
    {
        // Ambil parameter format dari request
        $format = $request->input('format', 'pdf');

        // Filename base
        $filename = 'jenis-bansos-' . Str::slug($jenisBansos->nama_bansos);

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new JenisBansosSingleExport($jenisBansos),
                $filename . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi portrait (hapus landscape)
            $pdf = Pdf::loadView('exports.jenis-bansos', [
                'jenisBansos' => $jenisBansos,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ]);

            return $pdf->download($filename . '.pdf');
        }
    }

    // Export All Jenis Bansos
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $kategori = $request->input('kategori');
        $bentuk = $request->input('bentuk_bantuan');
        $periode = $request->input('periode');
        $status = $request->input('is_active');
        $format = $request->input('format', 'pdf');

        // Query data jenis bansos
        $query = JenisBansos::query();

        // Filter berdasarkan kategori jika ada
        if ($kategori) {
            $query->whereIn('kategori', explode(',', $kategori));
        }

        // Filter berdasarkan bentuk bantuan jika ada
        if ($bentuk) {
            $query->whereIn('bentuk_bantuan', explode(',', $bentuk));
        }

        // Filter berdasarkan periode jika ada
        if ($periode) {
            $query->whereIn('periode', explode(',', $periode));
        }

        // Filter berdasarkan status jika ada
        if ($status !== null && $status !== '') {
            $query->where('is_active', $status === 'true');
        }

        // Ambil data
        $jenisBansos = $query->get();

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new JenisBansosListExport($jenisBansos),
                'jenis-bansos-list-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.jenis-bansos-list', [
                'jenisBansos' => $jenisBansos,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'kategori' => $kategori,
                    'bentuk_bantuan' => $bentuk,
                    'periode' => $periode,
                    'status' => $status,
                ],
            ])->setPaper('a4', 'landscape'); // Set landscape orientation

            return $pdf->download('jenis-bansos-list-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Export jenis bansos yang dipilih
    public function exportSelected(Request $request)
    {
        // Perbaikan: Konversi string ids menjadi array
        $ids = explode(',', $request->input('ids'));

        $format = $request->input('format', 'pdf');
        $jenisBansos = JenisBansos::whereIn('id', $ids)->get();

        if ($format === 'excel') {
            return Excel::download(new JenisBansosListExport($jenisBansos), 'jenis-bansos.xlsx');
        }

        // Default ke PDF dengan orientasi landscape
        $pdf = Pdf::loadView('exports.jenis-bansos-list', [
            'jenisBansos' => $jenisBansos,
            'tanggal' => Carbon::now()->translatedFormat('d F Y'),
        ])->setPaper('a4', 'landscape'); // Set landscape orientation

        return $pdf->download('jenis-bansos.pdf');
    }
}

// Class untuk ekspor Excel Single Jenis Bansos
class JenisBansosSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $jenisBansos;

    public function __construct(JenisBansos $jenisBansos)
    {
        $this->jenisBansos = $jenisBansos;
    }

    public function collection()
    {
        return collect([$this->jenisBansos]);
    }

    public function headings(): array
    {
        return [
            'Nama Program',
            'Kategori',
            'Bentuk Bantuan',
            'Nilai Bantuan',
            'Instansi Pemberi',
            'Periode',
            'Deskripsi',
            'Status',
        ];
    }

    public function map($jenisBansos): array
    {
        return [
            $jenisBansos->nama_bansos,
            $jenisBansos->kategori,
            JenisBansos::getBentukBantuanOptions()[$jenisBansos->bentuk_bantuan] ?? '-',
            $jenisBansos->getNilaiBantuanFormatted(),
            $jenisBansos->instansi_pemberi,
            JenisBansos::getPeriodeOptions()[$jenisBansos->periode] ?? '-',
            $jenisBansos->deskripsi,
            $jenisBansos->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function title(): string
    {
        return 'Detail Jenis Bantuan Sosial';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

// Class untuk ekspor Excel List Jenis Bansos
class JenisBansosListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $jenisBansos;

    public function __construct($jenisBansos)
    {
        $this->jenisBansos = $jenisBansos;
    }

    public function collection()
    {
        return $this->jenisBansos;
    }

    public function headings(): array
    {
        return [
            'Nama Program',
            'Kategori',
            'Bentuk Bantuan',
            'Nilai Bantuan',
            'Instansi Pemberi',
            'Periode',
            'Deskripsi',
            'Status',
        ];
    }

    public function map($jenisBansos): array
    {
        return [
            $jenisBansos->nama_bansos,
            $jenisBansos->kategori,
            JenisBansos::getBentukBantuanOptions()[$jenisBansos->bentuk_bantuan] ?? '-',
            $jenisBansos->getNilaiBantuanFormatted(),
            $jenisBansos->instansi_pemberi,
            JenisBansos::getPeriodeOptions()[$jenisBansos->periode] ?? '-',
            $jenisBansos->deskripsi,
            $jenisBansos->is_active ? 'Aktif' : 'Tidak Aktif',
        ];
    }

    public function title(): string
    {
        return 'Daftar Jenis Bantuan Sosial';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}