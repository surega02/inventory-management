<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Every create/update/delete lands in activity_log with old/new values.
 * Fields never worth logging (secrets, timestamps, counters) are excluded
 * once in config/activitylog.php `default_except_attributes`.
 */
trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
