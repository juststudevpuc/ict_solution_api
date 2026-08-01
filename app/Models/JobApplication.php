<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes; // 🔥 ADDED THIS

class JobApplication extends Model
{
    use SoftDeletes; // 🔥 ADDED THIS

    protected $connection = "mongodb";
    protected $table = "job_applications";

    protected $fillable = [
        "career_id",
        "first_name",
        "last_name",
        "email",
        "phone",
        "experience_years",   // 🔥 NEW: e.g., 2
        "expected_salary",    // 🔥 NEW: e.g., "$600"
        "cv_url",
        "cv_public_id",
        "cover_letter",
        "portfolio_url",
        "status",
        "admin_notes"
    ];

    public function career()
    {
        return $this->belongsTo(Career::class, 'career_id', '_id');
    }
}
