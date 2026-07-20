<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZStopTime extends BaseStopTime {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'z_stop_time';

    public function scheduleModel() : BelongsTo {
        return $this->belongsTo(ZSchedule::class, 'z_schedule');
    }

    public function physicalStation() : BelongsTo {
        return $this->belongsTo(PhysicalStation::class, 'location', 'crs_code')->primary();
    }
}
