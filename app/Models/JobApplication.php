<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'career_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'experience_years',
        'expected_salary',
        'cv_url',
        'cv_public_id',
        'cover_letter',
        'portfolio_url',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'experience_years' => 'integer',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }
}
