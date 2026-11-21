<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Career extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'department',
        'job_type',
        'location',
        'job_level',
        'vacancies',
        'salary_range',
        'job_description',
        'job_requirement',
        'job_responsibility',
        'closing_date',
        'status',
    ];

    protected $casts = [
        'vacancies' => 'integer',
        'job_requirement' => 'array',
        'job_responsibility' => 'array',
        'closing_date' => 'datetime',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
