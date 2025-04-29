<?php

namespace App\Http\Controllers;

use App\Models\KeuanganDesa;
use App\Models\ProfilDesa;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use App\Exports\KeuanganExport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Carbon\Carbon;

class KeuanganExportController extends Controller
{
    // Ekspor 1 transaksi
    public function export(KeuanganDesa $keuangan, Request $request)
    {
        // Ambil parameter format dari request
        $format = $request->input('format', 'pdf');

        // Ambil data desa secara langsung
        $desa = ProfilDesa::find($keuangan->id_desa);

        // Tentukan metode export berdasarkan format
        if ($format === 'excel') {
            return $this->exportToExcel($keuangan, $desa);
        } else {
            return $this->exportToPdf($keuangan, $desa);
        }
    }

    protected function exportToPdf(KeuanganDesa $keuangan, $desa)
    {
        $pdf = Pdf::loadView('exports.keuangan', [
            'keuangan' => $keuangan,
            'desa' => $desa,
        ]);

        return $pdf->download('keuangan-' . $keuangan->id . '.pdf');
    }

    protected function exportToExcel(KeuanganDesa $keuangan, $desa)
    {
        return Excel::download(new class($keuangan, $desa) implements FromCollection, WithHeadings, WithMapping, WithTitle {
            protected $keuangan;
            protected $desa;

            public function __construct(KeuanganDesa $keuangan, $desa)
            {
                $this->keuangan = $keuangan;
                $this->desa = $desa;
            }

            public function collection()
            {
                return collect([$this->keuangan]);
            }

            public function headings(): array
            {
                return [
                    'ID',
                    'Desa',
                    'Jenis',
                    'Tanggal',
                    'Jumlah',
                    'Deskripsi',
                    'Dibuat Oleh',
                    'Tanggal Dibuat',
                ];
            }

            public function map($keuangan): array
            {
                // Gunakan nama_desa sesuai dengan yang berhasil di blade
                $namaDesa = $this->desa ? $this->desa->nama_desa : 'N/A';

                return [
                    $keuangan->id,
                    $namaDesa,
                    $keuangan->jenis,
                    $keuangan->tanggal ? date('d/m/Y', strtotime($keuangan->tanggal)) : 'N/A',
                    'Rp ' . number_format($keuangan->jumlah, 0, ',', '.'),
                    $keuangan->deskripsi,
                    $keuangan->creator ? $keuangan->creator->name : 'N/A',
                    $keuangan->created_at ? $keuangan->created_at->format('d/m/Y H:i') : 'N/A',
                ];
            }

            public function title(): string
            {
                return 'Data Keuangan';
            }
        }, 'keuangan-' . $keuangan->id . '.xlsx');
    }

    // Ekspor semua transaksi (dengan filter opsional)
    public function exportAll(Request $request)
    {
        // Ambil parameter filter
        $jenis = $request->get('jenis');
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');
        $format = $request->get('format', 'pdf');

        // Query dasar dengan eager loading desa
        $query = KeuanganDesa::with('desa');

        // Terapkan filter jika ada
        if ($jenis) {
            $query->where('jenis', $jenis);
        }

        if ($dariTanggal) {
            $query->whereDate('tanggal', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $query->whereDate('tanggal', '<=', $sampaiTanggal);
        }

        // Ambil data dan hitung total
        $keuanganList = $query->get();

        // Hitung total pemasukan dan pengeluaran
        $totalPemasukan = $keuanganList->where('jenis', 'Pemasukan')->sum('jumlah');
        $totalPengeluaran = $keuanganList->where('jenis', 'Pengeluaran')->sum('jumlah');
        $saldo = $totalPemasukan - $totalPengeluaran;

        // Filter untuk tampilan
        $filters = [
            'jenis' => $jenis,
            'dariTanggal' => $dariTanggal,
            'sampaiTanggal' => $sampaiTanggal,
        ];

        // Tentukan export berdasarkan format
        if ($format === 'excel') {
            return $this->exportAllToExcel($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo, $filters);
        } else {
            return $this->exportAllToPdf($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo, $filters);
        }
    }

    protected function exportAllToPdf($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo, $filters)
    {
        $pdf = PDF::loadView('exports.keuangan-list', [
            'keuanganList' => $keuanganList,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'saldo' => $saldo,
            'filters' => $filters,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('laporan-keuangan-desa.pdf');
    }

    protected function exportAllToExcel($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo, $filters)
    {
        return Excel::download(new class($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo) implements FromCollection, WithHeadings, WithMapping, WithTitle {
            protected $keuanganList;
            protected $totalPemasukan;
            protected $totalPengeluaran;
            protected $saldo;

            public function __construct($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo)
            {
                $this->keuanganList = $keuanganList;
                $this->totalPemasukan = $totalPemasukan;
                $this->totalPengeluaran = $totalPengeluaran;
                $this->saldo = $saldo;
            }

            public function collection()
            {
                // Menambahkan baris summary di akhir
                $data = $this->keuanganList->toArray();

                // Tambahkan baris kosong dan ringkasan
                $data[] = ['', '', '', '', '', ''];  // Baris kosong
                $data[] = ['RINGKASAN', '', '', '', '', ''];
                $data[] = ['Total Pemasukan', '', '', '', '', 'Rp ' . number_format($this->totalPemasukan, 0, ',', '.')];
                $data[] = ['Total Pengeluaran', '', '', '', '', 'Rp ' . number_format($this->totalPengeluaran, 0, ',', '.')];
                $data[] = ['Saldo', '', '', '', '', 'Rp ' . number_format($this->saldo, 0, ',', '.')];

                return collect($data);
            }

            public function headings(): array
            {
                return [
                    'No',
                    'Desa',
                    'Tanggal',
                    'Deskripsi',
                    'Jenis',
                    'Jumlah',
                ];
            }

            public function map($row): array
            {
                // Cek jika ini adalah array yang sudah diformat (ringkasan)
                if (!isset($row['id'])) {
                    return $row; // Langsung kembalikan array ringkasan
                }

                static $counter = 0;
                $counter++;

                // Extract desa name properly
                $namaDesa = '';
                if (isset($row['desa']) && is_array($row['desa']) && isset($row['desa']['nama_desa'])) {
                    $namaDesa = $row['desa']['nama_desa'];
                } else {
                    // Coba ambil desa dari id_desa jika ada
                    if (isset($row['id_desa'])) {
                        $desa = ProfilDesa::find($row['id_desa']);
                        $namaDesa = $desa ? $desa->nama_desa : 'N/A';
                    } else {
                        $namaDesa = 'N/A';
                    }
                }

                return [
                    $counter,
                    $namaDesa,
                    isset($row['tanggal']) ? date('d/m/Y', strtotime($row['tanggal'])) : 'N/A',
                    $row['deskripsi'] ?? 'N/A',
                    $row['jenis'] ?? 'N/A',
                    isset($row['jumlah']) ? 'Rp ' . number_format($row['jumlah'], 0, ',', '.') : 'N/A',
                ];
            }

            public function title(): string
            {
                return 'Laporan Keuangan Desa';
            }
        }, 'laporan-keuangan-desa.xlsx');
    }

    // Ekspor transaksi yang dipilih
    public function exportSelected(Request $request)
    {
        // Ambil ID transaksi yang dipilih
        $selectedIds = explode(',', $request->get('ids'));

        // Ambil parameter filter tanggal
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        if (empty($selectedIds)) {
            return redirect()->back()->with('error', 'Tidak ada transaksi yang dipilih');
        }

        // Query dasar
        $query = KeuanganDesa::with(['desa', 'creator'])
            ->whereIn('id', $selectedIds);

        // Terapkan filter tanggal jika ada
        if ($dariTanggal) {
            $dariTanggal = Carbon::parse($dariTanggal)->startOfDay();
            $query->whereDate('tanggal', '>=', $dariTanggal);
        }

        if ($sampaiTanggal) {
            $sampaiTanggal = Carbon::parse($sampaiTanggal)->endOfDay();
            $query->whereDate('tanggal', '<=', $sampaiTanggal);
        }

        // Ambil data keuangan
        $keuanganList = $query->orderBy('tanggal', 'desc')->get();

        // Hitung total pemasukan dan pengeluaran
        $totalPemasukan = $keuanganList->where('jenis', 'Pemasukan')->sum('jumlah');
        $totalPengeluaran = $keuanganList->where('jenis', 'Pengeluaran')->sum('jumlah');
        $saldo = $totalPemasukan - $totalPengeluaran;

        // Format pilihan ekspor
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            return $this->exportAllToExcel($keuanganList, $totalPemasukan, $totalPengeluaran, $saldo, [
                'jenis' => null,
                'dariTanggal' => $dariTanggal ? $dariTanggal->format('Y-m-d') : null,
                'sampaiTanggal' => $sampaiTanggal ? $sampaiTanggal->format('Y-m-d') : null,
            ]);
        } else {
            // Default ke PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.keuangan-list', [
                'keuanganList' => $keuanganList,
                'totalPemasukan' => $totalPemasukan,
                'totalPengeluaran' => $totalPengeluaran,
                'saldo' => $saldo,
                'filters' => [
                    'jenis' => null,
                    'dariTanggal' => $dariTanggal ? $dariTanggal->translatedFormat('d F Y') : null,
                    'sampaiTanggal' => $sampaiTanggal ? $sampaiTanggal->translatedFormat('d F Y') : null,
                ]
            ])->setPaper('a4', 'landscape');

            return $pdf->download('laporan-keuangan-desa-terpilih-' . now()->format('Y-m-d') . '.pdf');
        }
    }
}