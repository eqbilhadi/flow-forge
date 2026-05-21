<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Workflow extends Model
{
    protected $fillable = ['tenant_id', 'name', 'description', 'nodes', 'edges', 'status'];

    protected $casts = [
        'nodes' => 'array',
        'edges' => 'array',
    ];

    // Hubungan ke Tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Mengaktifkan fitur auto-filter berdasarkan tenant_id user yang sedang login
    protected static function booted()
    {
        static::creating(function ($model) {
            if (Auth::check() && ! $model->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where('tenant_id', Auth::user()->tenant_id);
            }
        });
    }
}
