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
    $query_string = '';
    if (!empty($parameters)) {
      $query_string .= '?' . http_build_query($parameters);
    }

    try {
      $response = $this->client->request('GET', 'https://dawa.aws.dk'.$endpoint.$query_string);
    } catch (GuzzleException $e) {
      $message = $this->t('Request failed due to GuzzleException (line @line in @file) @exception', ['@line' => $e->getLine(), '@file' => $e->getFile(), '@exception' => $e->getMessage()]);
      \Drupal::logger('DAWA Adresses')->error($message);
      return FALSE;
    }

    if ($response->getStatusCode() === 200) {
      $result = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($result)) {
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
   * @see https://dawa.aws.dk/dok/api/adgangsadresse#s%C3%B8gning
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
      'q' => $query
    ];
    $params += $parameters;
    return $this->request('/adresser', $params);
  }

  /**
   * Look up an address based on the DAWA Id for the address.
   *
   * @see https://dawa.aws.dk/dok/api/adgangsadresse#opslag
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
    $parameters = [];
    if (!empty($structure)) {
      $parameters['struktur'] = $structure;
    }
    if (!empty($geometry)) {
      $parameters['geometri'] = $geometry;
    }
    return $this->request('/adresser/'.$id, $parameters);
  }

  /**
   * Autocomplete of address..
   *
   * @see https://dawa.aws.dk/dok/api/adgangsadresse#autocomplete
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
      'q' => $query
    ];
    $params += $parameters;
    return $this->request('/adresser/autocomplete', $params);
  }

  /**
   * Find the address closes to the coordinates.
   * Available coordinate systems are:
   *   ETRS89/UTM32 with srid=25832
   *   WGS84/geographical with srdi=4326 (Default).
   *
   * @see https://dawa.aws.dk/dok/api/adgangsadresse#reverse
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
    $parameters = [
      'x' => $x,
      'y' => $y
    ];

    if (!is_null($srid)) {
      $parameters['srid'] = $srid;
    }

    if (!is_null($callback)) {
      $parameters['callback'] = $callback;
    }

    if (!is_null($format)) {
      $parameters['format'] = $format;
    }

    if (!is_null($noformat)) {
      $parameters['noformat'] = $noformat;
    }

    if (!is_null($structure)) {
      $parameters['struktur'] = $structure;
    }

    if (!is_null($geometry)) {
      $parameters['geometri'] = $geometry;
    }

    return $this->request('/adresser/reverse', $parameters);
  }
}
