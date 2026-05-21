<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowRun extends Model
{
    protected $fillable = ['workflow_id', 'status', 'current_state', 'started_at', 'completed_at'];

    protected $casts = [
        'current_state' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Hubungan balik ke Workflow definisi-nya
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
