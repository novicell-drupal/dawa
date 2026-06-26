<?php

namespace Drupal\dawa\Controller;


use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Addresses {

  use StringTranslationTrait;

  /**
   * @var Client $client
   */
  protected $client;

  public function __construct() {
    $this->client = new Client();
  }

  /**
   * Get the configured Adressevaelger API URL.
   */
  protected function getApiUrl(): string {
    return rtrim(\Drupal::config('dawa.settings')->get('api_url') ?: 'https://adressevaelger.dk', '/');
  }

  /**
   * Get the configured Adressevaelger token.
   */
  protected function getToken(): string {
    return trim((string) \Drupal::config('dawa.settings')->get('token'));
  }

  /**
   *  Make an API call to the specified DAWA endpoint.
   *
   * @param string $endpoint
   *   The DAWA API endpoint, must start with /
   * @param array $parameters
   *   An optional array of query parameters to pass to the endpoint
   *
   * @return bool|mixed
   */
  protected function request($endpoint, $parameters = []) {
    $token = $this->getToken();
    if ($token === '') {
      \Drupal::logger('DAWA Adresses')->error($this->t('Adressevaelger token is not configured.'));
      return FALSE;
    }

    $parameters += ['token' => $token];

    try {
      $response = $this->client->request('GET', $this->getApiUrl() . $endpoint, [
        'query' => $parameters,
      ]);
    } catch (GuzzleException $e) {
      $message = $this->t('Request failed due to GuzzleException (line @line in @file) @exception', ['@line' => $e->getLine(), '@file' => $e->getFile(), '@exception' => $e->getMessage()]);
      \Drupal::logger('DAWA Adresses')->error($message);
      return FALSE;
    }

    if ($response->getStatusCode() === 200) {
      $result = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($result) && (($result['status'] ?? NULL) !== 'fejl')) {
        return $result;
      } else {
        return FALSE;
      }
    } else {
      if (!empty($response->getReasonPhrase())) {
        $message = $this->t('Request failed with statuscode: @code, reason: %reason', ['@code' => $response->getStatusCode(), '%reason' => $response->getReasonPhrase()]);
      } else {
        $message = $this->t('Request failed with statuscode @code', ['@code' => $response->getStatusCode()]);
      }
      \Drupal::logger('DAWA Adresses')->error($message);
      return FALSE;
    }
  }

  /**
   * Search for addresses.
   *
   * @see https://confluence.sdfi.dk/pages/viewpage.action?pageId=244318431
   *
   * @param string $query
   *   The search query.
   *   This will get added to the parameters.
   * @param array $parameters
   *   An array of parameters.
   *
   * @return bool|array
   *   Returns an array if successful, otherwise FALSE.
   */
  public function addressSearch($query, array $parameters) {
    $params = [
      'tekst' => $query
    ];
    $params += $parameters;
    $result = $this->request('/adresser/soeg', $params);

    return $result['fund'] ?? FALSE;
  }

  /**
   * Look up an address based on the DAWA Id for the address.
   *
   * @see https://confluence.sdfi.dk/pages/viewpage.action?pageId=246743156
   *
   * @param string      $id
   *   The DAWA address Id.
   * @param string|null $structure
   *   How to structure the result. Valid options are:
   *     - 'nestet' for a fully nestet address result.
   *     - 'mini' for a simplified address result.
   *     - 'flat' for a flat address result.
   *     - NULL to ignore this setting.
   * @param string|null $geometry
   *   If using the GeoJSON format, this defined if it is an accesspoint or roadpoint.
   *   Valid options are:
   *     - 'adgangspunkt' for a accesspoint.
   *     - 'vejpunkt' for a roadpoint.
   *     - NULL to ignore this setting
   *
   * @return bool|array
   *   Returns an array if successful, otherwise FALSE.
   */
  public function addressLookup($id, $structure = NULL, $geometry = NULL) {
    $result = $this->request('/adresser/' . $id);
    if (empty($result['adresse'])) {
      return FALSE;
    }

    return $this->normalizeAddressLookup($result);
  }

  /**
   * Autocomplete of address..
   *
   * @see https://confluence.sdfi.dk/pages/viewpage.action?pageId=244318431
   *
   * @param string $query
   *   The autocomplete query.
   *   This will get added to the parameters.
   * @param array $parameters
   *   An array of parameters.
   *
   * @return bool|array
   *   Returns an array if successful, otherwise FALSE.
   */
  public function autocomplete($query, array $parameters) {
    $params = [
      'tekst' => $query
    ];
    $params += $parameters;
    $result = $this->request('/adresser/soeg', $params);

    return $result['fund'] ?? FALSE;
  }

  /**
   * Find the address closes to the coordinates.
   * Available coordinate systems are:
   *   ETRS89/UTM32 with srid=25832
   *   WGS84/geographical with srdi=4326 (Default).
   *
   * Adressevaelger does not currently provide an equivalent endpoint.
   *
   * @param double $x
   * @param double $y
   * @param string|null $srid
   * @param string|null $callback
   * @param string|null $format
   * @param string|null $noformat
   * @param string|null $structure
   * @param string|null $geometry
   *
   * @return bool|array
   *   Returns an array if successful, otherwise FALSE.
   */
  public function reverseGeocoding($x, $y, $srid = NULL, $callback = NULL, $format = NULL, $noformat = NULL, $structure = NULL, $geometry = NULL) {
    \Drupal::logger('DAWA Adresses')->warning($this->t('Reverse geocoding is not supported by Adressevaelger.'));
    return FALSE;
  }

  /**
   * Normalize Adressevaelger address lookup output to the existing DAWA shape.
   */
  public function normalizeAddressLookup(array $lookup): array {
    $address = $lookup['adresse'] ?? [];
    $house_number = $address['husnummer'] ?? [];
    $postal_code = $house_number['postnummer'] ?? [];
    $road = $house_number['navngivenvej'] ?? [];
    $road_municipality = $house_number['navngivenvejkommunedel'] ?? [];
    $supplementary_city = $house_number['supplerendebynavn'] ?? [];
    $access_point = $house_number['adgangspunkt']['koordinater'] ?? [];
    $coordinates = $this->convertEpsg25832ToWgs84(
      $access_point['x'] ?? NULL,
      $access_point['y'] ?? NULL
    );

    return [
      'id' => $address['id_lokalid'] ?? '',
      'status' => NULL,
      'darstatus' => $address['status'] ?? $house_number['status'] ?? NULL,
      'vejkode' => $road_municipality['vejkode'] ?? NULL,
      'vejnavn' => $house_number['vejnavn'] ?? $road['vejnavn'] ?? NULL,
      'adresseringsvejnavn' => $house_number['vejnavn'] ?? $road['vejnavn'] ?? NULL,
      'husnr' => $house_number['husnummertekst'] ?? NULL,
      'etage' => $address['etagebetegnelse'] ?? NULL,
      'dør' => $address['doerbetegnelse'] ?? NULL,
      'supplerendebynavn' => $supplementary_city['navn'] ?? NULL,
      'postnr' => $postal_code['postnr'] ?? NULL,
      'postnrnavn' => $postal_code['navn'] ?? NULL,
      'stormodtagerpostnr' => NULL,
      'stormodtagerpostnrnavn' => NULL,
      'kommunekode' => $road_municipality['kommune'] ?? NULL,
      'adgangsadresseid' => $house_number['id_lokalid'] ?? NULL,
      'x' => $coordinates['x'],
      'y' => $coordinates['y'],
      'href' => $this->getApiUrl() . '/adresser/' . ($address['id_lokalid'] ?? ''),
      'betegnelse' => $address['adressebetegnelse'] ?? '',
      'adressebetegnelse' => $address['adressebetegnelse'] ?? '',
    ];
  }

  /**
   * Convert ETRS89 / UTM zone 32N coordinates to WGS84 longitude/latitude.
   */
  protected function convertEpsg25832ToWgs84($easting, $northing): array {
    if (!is_numeric($easting) || !is_numeric($northing)) {
      return ['x' => NULL, 'y' => NULL];
    }

    $a = 6378137.0;
    $ecc_squared = 0.00669438;
    $k0 = 0.9996;
    $zone_number = 32;

    $x = (float) $easting - 500000.0;
    $y = (float) $northing;
    $long_origin = ($zone_number - 1) * 6 - 180 + 3;
    $ecc_prime_squared = $ecc_squared / (1 - $ecc_squared);

    $m = $y / $k0;
    $mu = $m / ($a * (1 - $ecc_squared / 4 - 3 * $ecc_squared * $ecc_squared / 64 - 5 * $ecc_squared * $ecc_squared * $ecc_squared / 256));

    $e1 = (1 - sqrt(1 - $ecc_squared)) / (1 + sqrt(1 - $ecc_squared));

    $j1 = 3 * $e1 / 2 - 27 * pow($e1, 3) / 32;
    $j2 = 21 * pow($e1, 2) / 16 - 55 * pow($e1, 4) / 32;
    $j3 = 151 * pow($e1, 3) / 96;
    $j4 = 1097 * pow($e1, 4) / 512;

    $fp = $mu + $j1 * sin(2 * $mu) + $j2 * sin(4 * $mu) + $j3 * sin(6 * $mu) + $j4 * sin(8 * $mu);

    $sin_fp = sin($fp);
    $cos_fp = cos($fp);
    $tan_fp = tan($fp);

    $c1 = $ecc_prime_squared * $cos_fp * $cos_fp;
    $t1 = $tan_fp * $tan_fp;
    $n1 = $a / sqrt(1 - $ecc_squared * $sin_fp * $sin_fp);
    $r1 = $a * (1 - $ecc_squared) / pow(1 - $ecc_squared * $sin_fp * $sin_fp, 1.5);
    $d = $x / ($n1 * $k0);

    $lat = $fp - ($n1 * $tan_fp / $r1) * (
      $d * $d / 2 -
      (5 + 3 * $t1 + 10 * $c1 - 4 * $c1 * $c1 - 9 * $ecc_prime_squared) * pow($d, 4) / 24 +
      (61 + 90 * $t1 + 298 * $c1 + 45 * $t1 * $t1 - 252 * $ecc_prime_squared - 3 * $c1 * $c1) * pow($d, 6) / 720
    );

    $lon = deg2rad($long_origin) + (
      $d -
      (1 + 2 * $t1 + $c1) * pow($d, 3) / 6 +
      (5 - 2 * $c1 + 28 * $t1 - 3 * $c1 * $c1 + 8 * $ecc_prime_squared + 24 * $t1 * $t1) * pow($d, 5) / 120
    ) / $cos_fp;

    return [
      'x' => rad2deg($lon),
      'y' => rad2deg($lat),
    ];
  }
}
