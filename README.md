
# Panduan Instalasi Laravel / Filament

## Prasyarat

-   PHP >= 8.1
-   Composer
-   Node.js dan npm
-   MySQL atau database lain yang didukung Laravel

## Langkah-langkah Instalasi

1. **Clone Repositori**

    ```bash
    git clone <URL_REPOSITORI>
    cd <NAMA_DIRECTORY>
    ```

2. **Instalasi Dependensi PHP**

    ```bash
    composer install
    ```

3. **Konfigurasi Environment**

    - Salin file `.env.example` menjadi `.env`
    - Sesuaikan konfigurasi database dan pengaturan lainnya di file `.env`

    ```bash
    cp .env.example .env
    ```

4. **Generate Application Key**

    ```bash
    php artisan key:generate
    ```

5. **Jalankan Migrasi Database**

    ```bash
    php artisan migrate --seed
    ```

6. **Instalasi Dependensi JavaScript**

    ```bash
    npm install
    ```

7. **Build Assets**

    ```bash
    npm run dev
    ```

8. **Jalankan Server Development**

    ```bash
    php artisan serve
    ```

9. **Akses Aplikasi**
    - Buka browser dan akses `http://localhost:8000`

## Catatan Tambahan

-   Pastikan server database Anda berjalan sebelum menjalankan migrasi.
-   Jika Anda menggunakan Filament, pastikan untuk mengikuti panduan instalasi tambahan yang mungkin diperlukan dari dokumentasi Filament.

## Kontribusi

Silakan buka issue atau pull request jika Anda ingin berkontribusi pada proyek ini.

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](https://opensource.org/licenses/MIT).
