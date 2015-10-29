<?php
/**
 * This file contains class::Object
 * @package Runalyze\Model\Route
 */

namespace Runalyze\Model\Route;

use Runalyze\Model;
use League\Geotools\Geotools;
use \League\Geotools\Coordinate\Coordinate;

/**
 * Route object
 * 
 * @author Hannes Christiansen
 * @package Runalyze\Model\Route
 */
class Object extends Model\ObjectWithID implements Model\Loopable {
	/**
	 * Cities separator
	 * @var string
	 */
	const CITIES_SEPARATOR = ' - ';

	/**
	 * Key: name
	 * @var string
	 */
	const NAME = 'name';

	/**
	 * Key: cities
	 * @var string
	 */
	const CITIES = 'cities';

	/**
	 * Key: distance
	 * @var string
	 */
	const DISTANCE = 'distance';

	/**
	 * Key: elevation
	 * @var string
	 */
	const ELEVATION = 'elevation';

	/**
	 * Key: elevation up
	 * @var string
	 */
	const ELEVATION_UP = 'elevation_up';

	/**
	 * Key: elevation down
	 * @var string
	 */
	const ELEVATION_DOWN = 'elevation_down';

	/**
	 * Key: latitudes
	 * @var string
	 */
	const LATITUDES = 'lats';

	/**
	 * Key: longitudes
	 * @var string
	 */
	const LONGITUDES = 'lngs';
	
	/**
	 * Key: geohash
	 * @var string
	 */
	const GEOHASHES = 'geohash';

	/**
	 * Key: elevations original
	 * @var string
	 */
	const ELEVATIONS_ORIGINAL = 'elevations_original';

	/**
	 * Key: elevations corrected
	 * @var string
	 */
	const ELEVATIONS_CORRECTED = 'elevations_corrected';

	/**
	 * Key: elevations source
	 * @var string
	 */
	const ELEVATIONS_SOURCE = 'elevations_source';

	/**
	 * Key: startpoint latitude
	 * @var string
	 */
	const STARTPOINT_LATITUDE = 'startpoint_lat';

	/**
	 * Key: startpoint longitude
	 * @var string
	 */
	const STARTPOINT_LONGITUDE = 'startpoint_lng';

	/**
	 * Key: startpoint in geohash
	 * @var string
	 */
	const STARTPOINT = 'startpoint';
	
	/**
	 * Key: endpoint latitude
	 * @var string
	 */
	const ENDPOINT_LATITUDE = 'endpoint_lat';

	/**
	 * Key: endpoint longitude
	 * @var string
	 */
	const ENDPOINT_LONGITUDE = 'endpoint_lng';
	
	/**
	 * Key: endpoint in geohash
	 * @var string
	 */
	const ENDPOINT = 'endpoint';

	/**
	 * Key: minimal latitude
	 * @var string
	 */
	const MIN_LATITUDE = 'min_lat';

	/**
	 * Key: minimal longitude
	 * @var string
	 */
	const MIN_LONGITUDE = 'min_lng';
	
	/**
	 * Key: minimal longitude & latitude in geohash
	 * @var string
	 */
	const MIN = 'min';

	/**
	 * Key: maximal latitude
	 * @var string
	 */
	const MAX_LATITUDE = 'max_lat';

	/**
	 * Key: maximal longitude
	 * @var string
	 */
	const MAX_LONGITUDE = 'max_lng';
	
	/**
	 * Key: maximal longitude & latitude in geohash
	 * @var string
	 */
	const MAX = 'max';	

	/**
	 * Key: in routenet
	 * @var string
	 */
	const IN_ROUTENET = 'in_routenet';

	/**
	 * Flag: ensure arrays to be equally sized
	 * @var bool
	 */
	protected $checkArraySizes = true;

	/**
	 * Check array sizes
	 * @throws \RuntimeException
	 */
	protected function checkArraySizes() {
		foreach ($this->properties() as $key) {
			if ($this->isArray($key)) {
				try {
					$count = count($this->Data[$key]);

					if ($key == self::ELEVATIONS_CORRECTED && $this->numberOfPoints > 0 && $count > $this->numberOfPoints) {
						$this->Data[$key] = array_slice($this->Data[$key], 0, $this->numberOfPoints);
					} else {
						$this->checkArraySize( $count );
					}
				} catch(\RuntimeException $E) {
					throw new \RuntimeException($E->getMessage().' (for '.$key.')');
				}
			}
		}
	}

	/**
	 * All properties
	 * @return array
	 */
	public static function allDatabaseProperties() {
		return array(
			self::NAME,
			self::CITIES,
			self::DISTANCE,
			self::ELEVATION,
			self::ELEVATION_UP,
			self::ELEVATION_DOWN,
			self::LATITUDES,
			self::LONGITUDES,
			self::GEOHASHES,
			self::ELEVATIONS_ORIGINAL,
			self::ELEVATIONS_CORRECTED,
			self::ELEVATIONS_SOURCE,
			self::STARTPOINT,
			self::ENDPOINT,
			self::MIN,
			self::MAX,
			self::IN_ROUTENET
		);
	}

	/**
	 * Properties
	 * @return array
	 */
	public function properties() {
		return static::allDatabaseProperties();
	}

	/**
	 * Is the property an array?
	 * @param string $key
	 * @return bool
	 */
	public function isArray($key) {
		switch ($key) {
			case self::ELEVATIONS_ORIGINAL:
			case self::ELEVATIONS_CORRECTED:
			case self::LATITUDES:
			case self::LONGITUDES:
			case self::GEOHASHES:
				return true;
		}

		return false;
	}

	/**
	 * Can be null?
	 * @param string $key
	 * @return boolean
	 */
	protected function canBeNull($key) {
		switch ($key) {
			case self::ELEVATIONS_ORIGINAL:
			case self::ELEVATIONS_CORRECTED:
			case self::LATITUDES:
			case self::LONGITUDES:
			case self::GEOHASHES:
			case self::STARTPOINT:
			case self::ENDPOINT:
			case self::MIN:
			case self::MAX:
				return true;
		}
		return false;
	}

	/**
	 * Synchronize internal models
	 */
	public function synchronize() {
		parent::synchronize();

		$this->ensureAllNumericValues();
		$this->synchronizeStartAndEndpoint();
		$this->synchronizeBoundaries();

		if (!$this->hasCorrectedElevations()) {
			$this->set(self::ELEVATIONS_SOURCE, '');
		}
	}

	/**
	 * Ensure that numeric fields get numeric values
	 */
	protected function ensureAllNumericValues() {
		$this->ensureNumericValue(array(
			self::DISTANCE,
			self::ELEVATION,
			self::ELEVATION_UP,
			self::ELEVATION_DOWN,
			self::IN_ROUTENET
		));
	}

	/**
	 * Synchronize start- and endpoint
	 */
	protected function synchronizeStartAndEndpoint() {
		if (!$this->hasPositionData()) {
			$this->Data[self::STARTPOINT] = null;
			$this->Data[self::ENDPOINT] = null;
		} else {
			$Latitudes = array_filter($this->Data[self::LATITUDES]);
			$Longitudes = array_filter($this->Data[self::LONGITUDES]);

			if (!empty($Latitudes) && !empty($Longitudes)) {
			    $StartpointCoordinate = new Coordinate(reset($Latitudes).','.reset($Longitudes));
			    $this->Data[self::STARTPOINT] = (new Geotools)->geohash()->encode($StartpointCoordinate, 10);
				    
			    $EndpointCoordinate = new Coordinate(end($Latitudes).','.end($Longitudes));
			    $this->Data[self::ENDPOINT] = (new Geotools)->geohash()->encode($EndpointCoordinate, 10);
			}
		}
	}

	/**
	 * Synchronize boundaries
	 */
	protected function synchronizeBoundaries() {
		if (!$this->hasPositionData()) {
			$this->Data[self::MIN] = null;
			$this->Data[self::MAX] = null;
		} else {
			$Latitudes = array_filter($this->Data[self::LATITUDES]);
			$Longitudes = array_filter($this->Data[self::LONGITUDES]);

			if (!empty($Latitudes) && !empty($Longitudes)) {
				$MinCoordinate = new Coordinate(min($Latitudes).','.min($Longitudes));
				$this->Data[self::MIN] = (new Geotools)->geohash()->encode($MinCoordinate, 10);
				    
				$MaxCoordinate = new Coordinate(max($Latitudes).','.max($Longitudes));
				$this->Data[self::MAX] = (new Geotools)->geohash()->encode($MaxCoordinate, 10);
			}

		}
	}

	/**
	 * Number of points
	 * @return int
	 */
	public function num() {
		return $this->numberOfPoints;
	}

	/**
	 * Value at
	 * 
	 * Remark: This method may throw index offsets.
	 * @param int $index
	 * @param enum $key
	 * @return mixed
	 */
	public function at($index, $key) {
		return $this->Data[$key][$index];
	}

	/**
	 * Name
	 * @return string
	 */
	public function name() {
		return $this->Data[self::NAME];
	}

	/**
	 * Cities as array
	 * @return array
	 */
	public function citiesAsArray() {
		return explode(self::CITIES_SEPARATOR, $this->Data[self::CITIES]);
	}

	/**
	 * Distance
	 * @return float
	 */
	public function distance() {
		return $this->Data[self::DISTANCE];
	}

	/**
	 * Elevation
	 * @return int
	 */
	public function elevation() {
		return $this->Data[self::ELEVATION];
	}

	/**
	 * Elevation up
	 * @return int
	 */
	public function elevationUp() {
		return $this->Data[self::ELEVATION_UP];
	}

	/**
	 * Elevation down
	 * @return int
	 */
	public function elevationDown() {
		return $this->Data[self::ELEVATION_DOWN];
	}

	/**
	 * Latitudes
	 * @return array
	 */
	public function latitudes() {
		return $this->Data[self::LATITUDES];
	}

	/**
	 * Longitudes
	 * @return array
	 */
	public function longitudes() {
		return $this->Data[self::LONGITUDES];
	}

	/**
	 * Has position data?
	 * @return boolean
	 */
	public function hasPositionData() {
		return $this->has(self::LATITUDES);
	}

	/**
	 * Original elevations
	 * @return array
	 */
	public function elevationsOriginal() {
		return $this->Data[self::ELEVATIONS_ORIGINAL];
	}

	/**
	 * Corrected elevations
	 * @return array
	 */
	public function elevationsCorrected() {
		return $this->Data[self::ELEVATIONS_CORRECTED];
	}

	/**
	 * Strategy name of elevation correction
	 * @return string
	 */
	public function elevationsSource() {
		return $this->Data[self::ELEVATIONS_SOURCE];
	}

	/**
	 * Elevations array (corrected or original)
	 * @return array
	 */
	public function elevations() {
		if ($this->hasCorrectedElevations()) {
			return $this->elevationsCorrected();
		} elseif ($this->hasOriginalElevations()) {
			return $this->elevationsOriginal();
		}

		return array();
	}

	/**
	 * @return boolean
	 */
	public function hasElevations() {
		return $this->hasOriginalElevations() || $this->hasCorrectedElevations();
	}

	/**
	 * @return boolean
	 */
	public function hasOriginalElevations() {
		return isset($this->Data[self::ELEVATIONS_ORIGINAL]) && !empty($this->Data[self::ELEVATIONS_ORIGINAL]);
	}

	/**
	 * @return boolean
	 */
	public function hasCorrectedElevations() {
		return isset($this->Data[self::ELEVATIONS_CORRECTED]) && !empty($this->Data[self::ELEVATIONS_CORRECTED]);
	}

	/**
	 * Is in routenet?
	 * @return boolean
	 */
	public function inRoutenet() {
		return ($this->Data[self::IN_ROUTENET] == 1);
	}

	/**
	 * Calculate distance between two coordinates
	 * @param double $lat1
	 * @param double $lon1
	 * @param double $lat2
	 * @param double $lon2
	 * @return double
	 */
	public static function gpsDistance($lat1, $lon1, $lat2, $lon2) {
		$rad1 = deg2rad($lat1);
		$rad2 = deg2rad($lat2);
		$dist = sin($rad1) * sin($rad2) +  cos($rad1) * cos($rad2) * cos(deg2rad($lon1 - $lon2)); 
		$dist = acos($dist); 
		$dist = rad2deg($dist); 
		$miles = $dist * 60 * 1.1515;

		if (is_nan($miles))
			return 0;
	
		return ($miles * 1.609344);
	}
}