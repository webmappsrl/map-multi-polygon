<?php

namespace Wm\MapMultiPolygon;

use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

class MapMultiPolygon extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'map-multi-polygon';
    public $zone = [];

    /**
     * Resolve the field's value.
     */
    public function resolve($resource, ?string $attribute = null): void
    {
        parent::resolve($resource, $attribute = null);
        $this->zone = $this->geometryToGeojson($this->value);
        if (!is_null($this->zone)) {
            $this->withMeta(['geojson' => $this->zone['geojson']]);
            $this->withMeta(['center' => $this->zone['center']]);
        }
    }
    public function fillModelWithData(object $model, mixed $value, string $attribute): void
    {
        $newValue = $this->geojsonToGeometry($value);
        $oldAttribute = $this->geometryToGeojson($model->{$attribute});
        $oldValue = $this->geojsonToGeometry($oldAttribute['geojson']);
        if ($newValue != $oldValue) {
            parent::fillModelWithData($model, $newValue, $attribute);
        }
    }

    public function geometryToGeojson($geometry)
    {
        $coords = null;
        if (!is_null($geometry)) {
            $g = DB::select("SELECT st_asgeojson('$geometry') as g")[0]->g;
            $c = json_decode(DB::select("SELECT st_asgeojson(ST_Centroid('$geometry')) as g")[0]->g);
            $coords['geojson'] = $g;
            $coords['center'] = [$c->coordinates[1], $c->coordinates[0]];
        }
        return $coords;
    }

    public function geojsonToGeometry($geojson)
    {
        if (!is_null($geojson) && $geojson != "null") {
            $query = "SELECT ST_AsText(ST_GeomFromGeoJSON('$geojson')) As wkt";
            return DB::select($query)[0]->wkt;
        } else {
            return null;
        }
    }
}
