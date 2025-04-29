<?php

namespace App\Http\Controllers;

use App\Models\Penduduk;
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

class KartuKeluargaExportController extends Controller
{
    // Ekspor semua KK
    public function exportAll(Request $request)
    {
        // Ambil parameter dari request
        $idDesa = $request->input('id_desa');
        $format = $request->input('format', 'pdf');

        // Query data KK (penduduk yang kepala keluarga)
        $query = Penduduk::query()
            ->where('kepala_keluarga', true)
            ->with(['desa']);

        // Filter berdasarkan desa jika ada
        if ($idDesa) {
            $query->where('id_desa', $idDesa);
        }

        // Ambil data
        $kepalaKeluarga = $query->get();

        // Siapkan data anggota keluarga untuk setiap KK
        $kartuKeluarga = [];
        foreach ($kepalaKeluarga as $kepala) {
            $anggota = Penduduk::where('kk', $kepala->kk)
                ->where('kepala_keluarga', false)
                ->orderBy('tanggal_lahir')
                ->get();

            $kartuKeluarga[] = [
                'kepala' => $kepala,
                'anggota' => $anggota,
                'jumlah_anggota' => $anggota->count()
            ];
        }

        // Sesuaikan format ekspor
        if ($format === 'excel') {
            return Excel::download(
                new KartuKeluargaListExport($kartuKeluarga),
                'kartu-keluarga-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.kartu-keluarga-list', [
                'kartuKeluarga' => $kartuKeluarga,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'desa' => $idDesa ? ProfilDesa::find($idDesa)->nama_desa : 'Semua Desa',
                ],
            ])->setPaper('a4', 'landscape'); // Tambahkan orientasi landscape

            return $pdf->download('kartu-keluarga-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Ekspor KK yang dipilih
    public function exportSelected(Request $request)
    {
        // Ubah parameter dari 'kk' menjadi 'ids' sesuai dengan yang dikirim dari resource
        $selectedKK = explode(',', $request->get('ids', ''));

        if (empty($selectedKK)) {
            return redirect()->back()->with('error', 'Tidak ada Kartu Keluarga yang dipilih');
        }

        // Format pilihan export
        $format = $request->get('format', 'pdf');

        // Filter tanggal jika ada
        $dariTanggal = $request->get('dari_tanggal');
        $sampaiTanggal = $request->get('sampai_tanggal');

        // Siapkan data
        $kartuKeluarga = [];
        foreach ($selectedKK as $kk) {
            $kepala = Penduduk::where('kk', $kk)
                ->where('kepala_keluarga', true)
                ->with(['desa'])
                ->first();

            if ($kepala) {
                // Tambahkan filter tanggal jika ada
                $anggotaQuery = Penduduk::where('kk', $kepala->kk)
                    ->where('kepala_keluarga', false);

                // Filter berdasarkan tanggal pembuatan jika diperlukan
                if ($dariTanggal) {
                    $anggotaQuery->whereDate('created_at', '>=', $dariTanggal);
                }

                if ($sampaiTanggal) {
                    $anggotaQuery->whereDate('created_at', '<=', $sampaiTanggal);
                }

                $anggota = $anggotaQuery->orderBy('tanggal_lahir')->get();

                $kartuKeluarga[] = [
                    'kepala' => $kepala,
                    'anggota' => $anggota,
                    'jumlah_anggota' => $anggota->count()
                ];
            }
        }

        // Ekspor sesuai format
        if ($format === 'excel') {
            return Excel::download(
                new KartuKeluargaListExport($kartuKeluarga),
                'kartu-keluarga-terpilih-' . Carbon::now()->format('Y-m-d') . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.kartu-keluarga-list', [
                'kartuKeluarga' => $kartuKeluarga,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
                'filter' => [
                    'desa' => 'KK Terpilih',
                    'periode' => $dariTanggal && $sampaiTanggal ? "Periode: $dariTanggal s/d $sampaiTanggal" : "Semua Periode",
                ],
            ])->setPaper('a4', 'landscape'); // Tambahkan orientasi landscape

            return $pdf->download('kartu-keluarga-terpilih-' . Carbon::now()->format('Y-m-d') . '.pdf');
        }
    }

    // Ekspor satu KK
    public function exportSingle(Request $request, $kk)
    {
        // Format ekspor
        $format = $request->input('format', 'pdf');

        // Cari kepala keluarga
        $kepala = Penduduk::where('kk', $kk)
            ->where('kepala_keluarga', true)
            ->with(['desa'])
            ->first();

        if (!$kepala) {
            return redirect()->back()->with('error', 'Kartu Keluarga tidak ditemukan');
        }

        // Ambil anggota keluarga
        $anggota = Penduduk::where('kk', $kepala->kk)
            ->where('kepala_keluarga', false)
            ->orderBy('tanggal_lahir')
            ->get();

        $kartuKeluarga = [
            'kepala' => $kepala,
            'anggota' => $anggota,
            'jumlah_anggota' => $anggota->count()
        ];

        // Ekspor sesuai format
        if ($format === 'excel') {
            return Excel::download(
                new KartuKeluargaSingleExport($kartuKeluarga),
                'kartu-keluarga-' . $kk . '.xlsx'
            );
        } else {
            // Default PDF dengan orientasi landscape
            $pdf = Pdf::loadView('exports.kartu-keluarga-single', [
                'kk' => $kartuKeluarga,
                'tanggal' => Carbon::now()->translatedFormat('d F Y'),
            ])->setPaper('a4', 'landscape'); // Tambahkan orientasi landscape

            return $pdf->download('kartu-keluarga-' . $kk . '.pdf');
        }
    }
}

// Class untuk export single KK ke Excel
class KartuKeluargaSingleExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $kartuKeluarga;

    public function __construct($kartuKeluarga)
    {
        $this->kartuKeluarga = $kartuKeluarga;
    }

    public function collection()
    {
        // Gabungkan kepala keluarga dan anggota untuk ekspor
        $allMembers = collect([$this->kartuKeluarga['kepala']])->merge($this->kartuKeluarga['anggota']);
        return $allMembers;
    }

    public function headings(): array
    {
        return [
            'Nama',
            'NIK',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Pendidikan',
            'Pekerjaan',
            'Status Kawin',
            'Status dalam Keluarga',
        ];
    }

    public function map($record): array
    {
        return [
            $record->nama,
            $record->nik,
            $record->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
            $record->tempat_lahir,
            $record->tanggal_lahir ? Carbon::parse($record->tanggal_lahir)->format('d-m-Y') : '-',
            $record->agama,
            $record->pendidikan,
            $record->pekerjaan,
            $record->status_perkawinan,
            $record->kepala_keluarga ? 'Kepala Keluarga' : ($record->status_hubungan_dalam_keluarga ?? 'Anggota Keluarga'),
        ];
    }

    public function title(): string
    {
        return 'Kartu Keluarga ' . $this->kartuKeluarga['kepala']->kk;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 25],
            'B' => ['width' => 20],
            'C' => ['width' => 15],
            'D' => ['width' => 20],
            'E' => ['width' => 15],
            'F' => ['width' => 15],
            'G' => ['width' => 20],
            'H' => ['width' => 20],
            'I' => ['width' => 15],
            'J' => ['width' => 20],
        ];
    }
}

// Class untuk export list KK ke Excel
class KartuKeluargaListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $kartuKeluarga;

    public function __construct($kartuKeluarga)
    {
        $this->kartuKeluarga = $kartuKeluarga;
    }

    public function collection()
    {
        // Hanya ambil kepala keluarga untuk list
        return collect($this->kartuKeluarga)->map(function ($kk) {
            return $kk['kepala'];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor KK',
            'Kepala Keluarga',
            'NIK',
            'Alamat',
            'RT/RW',
            'Desa/Kelurahan',
            'Kecamatan',
            'Kabupaten/Kota',
            'Jumlah Anggota',
            'Desa',
        ];
    }

    public function map($record): array
    {
        static $counter = 0;
        $counter++;

        // Cari jumlah anggota keluarga dari data yang sudah disiapkan
        $jumlahAnggota = 0;
        foreach ($this->kartuKeluarga as $kk) {
            if ($kk['kepala']->id === $record->id) {
                $jumlahAnggota = $kk['jumlah_anggota'];
                break;
            }
        }

        return [
            $counter,
            $record->kk,
            $record->nama,
            $record->nik,
            $record->alamat,
            $record->rt_rw,
            $record->desa_kelurahan,
            $record->kecamatan,
            $record->kabupaten,
            $jumlahAnggota,
            $record->desa->nama_desa ?? '-',
        ];
    }

    public function title(): string
    {
        return 'Daftar Kartu Keluarga';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            'A' => ['width' => 5],
            'B' => ['width' => 20],
            'C' => ['width' => 25],
            'D' => ['width' => 20],
            'E' => ['width' => 30],
            'F' => ['width' => 10],
            'G' => ['width' => 20],
            'H' => ['width' => 20],
            'I' => ['width' => 20],
            'J' => ['width' => 15],
            'K' => ['width' => 20],
        ];
    }
}