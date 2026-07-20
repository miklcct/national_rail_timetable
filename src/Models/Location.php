<?php
declare(strict_types=1);

namespace Miklcct\NationalRailTimetable\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use function Safe\preg_replace;

abstract class Location extends Model {
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public abstract function getName() : ?string;
    public function getCrsOrTiplocCode() : string {
        return $this->crs_code ?? $this->tiploc_code;
    }

    public function getShortName() : string {
        $name = $this->getName();
        if (str_contains($name, 'MAESTEG')) {
            return $name;
        }
        return preg_replace('/ \(.*\)$/', '', $name);
    }

    public static function getCoordinates(string $tiploc) : ?array {
        static $coords_cache = [];
        if (array_key_exists($tiploc, $coords_cache)) {
            return $coords_cache[$tiploc];
        }
        $tiplocData = self::getTiplocData();
        $result = $tiplocData[$tiploc] ?? null;
        if (isset($result['easting'], $result['northing'])) {
            return $coords_cache[$tiploc] = [$result['easting'], $result['northing']];
        }
        return $coords_cache[$tiploc] = null;
    }

    protected function coordinates() : Attribute {
        return Attribute::make(
            get: fn() => self::getCoordinates($this->tiploc_code)
        );
    }

    private static function getTiplocData() : array {
        static $tiplocData;
        if ($tiplocData === null) {
            $csv_path = __DIR__ . '/../../resource/tiplocs-merged.csv';
            $cache_path = __DIR__ . '/../../var/cache/tiplocs-merged.php';
            if (file_exists($cache_path) && filemtime($cache_path) >= filemtime($csv_path)) {
                $tiplocData = require $cache_path;
            } else {
                $handle = fopen($csv_path, 'r');
                $keys = fgetcsv($handle, 0, ',', '"', "\\");
                $tiplocData = [];
                while (($row = fgetcsv($handle, 0, ',', '"', "\\")) !== false) {
                    $combined = array_combine($keys, $row);
                    foreach (
                        ["stop_lon" => "float", "stop_lat" => "float", "easting" => "int", "northing" => "int"] as $key
                    => $type
                    ) {
                        if (($combined[$key] ?? "") === "") {
                            $combined[$key] = null;
                        } else {
                            settype($combined[$key], $type);
                        }
                    }
                    $tiplocData[$combined["stop_id"]] = $combined;
                }
                fclose($handle);
                if (!is_dir(dirname($cache_path))) {
                    mkdir(dirname($cache_path), 0775, true);
                }
                file_put_contents($cache_path, "<?php return " . var_export($tiplocData, true) . ";");
            }
        }
        return $tiplocData;
    }
}

