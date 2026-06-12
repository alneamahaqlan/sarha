<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Seeder-Center reseed operation. See SeederCenterService::reseed
 * and ReseedBatchJob.
 */
class SeederRun extends Model
{
    protected $fillable = [
        'batch', 'status', 'message', 'rows_created',
        'started_at', 'finished_at', 'created_by', 'created_by_name',
    ];

    protected $casts = [
        'rows_created' => 'integer',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    public const STATUS_QUEUED  = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';
}
