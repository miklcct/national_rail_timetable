<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TocInterchange extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'toc_interchange';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = ['time' => 'integer'];

    public function station() : BelongsTo {
        return $this->belongsTo(PhysicalStation::class, 'crs', 'crs_code')->primary();
    }
}
