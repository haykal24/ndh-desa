<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BansosHistory extends Model
{
    use HasFactory;

    protected $table = 'bansos_history';

    protected $fillable = [
        'bansos_id',
        'status_lama',
        'status_baru',
        'keterangan',
        'diubah_oleh',
        'waktu_perubahan',
    ];

    protected $casts = [
        'waktu_perubahan' => 'datetime',
    ];

    public function bansos(): BelongsTo
    {
        return $this->belongsTo(Bansos::class, 'bansos_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    public function diubah_oleh_user()
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}