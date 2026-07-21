<?php
// copy this file to config.php and adjust the values if needed

declare(strict_types = 1);

use Miklcct\NationalRailTimetable\Config\MongodbConfig;

return new MongodbConfig(
    null
    , null
    , 'national_rail'
    , 'national_rail_new'
    , 'GBTT.uk'
);