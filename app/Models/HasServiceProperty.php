<?php
declare(strict_types=1);

namespace App\Models;

use App\DomainModels\ServiceProperty;

trait HasServiceProperty {
    public function getServiceProperty() : ServiceProperty {
        return new ServiceProperty(
            $this->train_category,
            $this->train_identity,
            $this->headcode,
            $this->business_sector,
            $this->power_type,
            $this->timing_load,
            $this->speed,
            str_contains($this->operating_chars ?? '', 'D'),
            $this->train_class,
            $this->sleepers,
            $this->reservations,
            $this->catering_code,
            $this->retail_train_id
        );
    }
}
