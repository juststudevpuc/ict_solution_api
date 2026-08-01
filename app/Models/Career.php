<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\SoftDeletes; // 🔥 ADDED THIS

class Career extends Model
{
    use SoftDeletes; // 🔥 ADDED THIS

    protected $connection = "mongodb";
    protected $table = "careers";

    protected $fillable = [
        "title",
        "slug",
        "department",
        "job_type",
        "location",
        "job_level",
        "vacancies",          // 🔥 NEW: Number of open positions
        "salary_range",       // 🔥 NEW: e.g., "$500 - $800"
        "job_description",
        "job_requirement",
        "job_responsibility",
        "closing_date",
        "status"
    ];

    public function applications()
    {
        return $this->hasMany(JobApplication::class, 'career_id', '_id');
    }
}
