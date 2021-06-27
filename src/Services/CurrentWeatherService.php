<?php

namespace Drupal\current_weather\Services;

use Cmfcmf\OpenWeatherMap;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_weather\Exception\CustomWeatherException;
use Http\Factory\Guzzle\RequestFactory;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;

/**
 * Class for resolve retrieving data from OpenWeatherMap API.
 *
 * @package Drupal\current_weather\Services
 */
class CurrentWeatherService {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Config name.
   *
   * @var string
   */
  const CONFIG_NAME = 'current_weather.settings';

  /**
   * Module settings configuration.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * CurrentWeatherService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user.
   */
  public function __construct(ConfigFactoryInterface $configFactory, AccountInterface $currentUser) {
    $this->config = $configFactory->get(self::CONFIG_NAME);
    $this->currentUser = $currentUser;
  }

  /**
   * Get weather from OpenWeatherMap.
   *
   * @param string|int $city
   *   City for the weather.
   * @param string|null $apiKey
   *   OpenWeatherMap API key.
   *
   * @return \Cmfcmf\OpenWeatherMap\CurrentWeather
   *   Weather.
   *
   * @throws \Cmfcmf\OpenWeatherMap\Exception
   * @throws \Drupal\custom_weather\Exception\CustomWeatherException
   */
  public function getWeather($city = '', string $apiKey = NULL) {
    // Checking is $apiKey is not null required for
    // test connection on the module settings form.
    if (!$this->getModuleStatus() && is_null($apiKey) && empty($city)) {
      throw new CustomWeatherException('Module has been disabled.', 503);
    }

    if (empty($city)) {
      $city = $this->getDefaultCity();
    }

    $owm = $this->openWeatherMapGetClient($apiKey);

    $langCode = $this->currentUser->getPreferredLangcode();

    return $owm->getWeather($city, $this->getUnits(), $langCode);
  }

  /**
   * Get module status.
   *
   * @return bool
   *   Module status
   */
  private function getModuleStatus() {
    return $this->config->get('status');
  }

  /**
   * Get OpenWeatherMap client.
   *
   * @param string|null $apiKey
   *   OpenWeatherMap API key.
   *
   * @return \Cmfcmf\OpenWeatherMap|false
   *   Client.
   */
  public function openWeatherMapGetClient(string $apiKey = NULL) {
    if (is_null($apiKey)) {
      $apiKey = $this->getApiKey();
    }

    if (empty($apiKey)) {
      return FALSE;
    }

    $httpRequestFactory = new RequestFactory();
    $httpClient = GuzzleAdapter::createWithConfig([]);

    return new OpenWeatherMap($apiKey, $httpClient, $httpRequestFactory);
  }

  /**
   * Get Api Key.
   *
   * @return string
   *   OpenWeatherMap API Key from settings.
   */
  private function getApiKey() {
    return $this->config->get('key');
  }

  /**
   * Get Default city.
   *
   * @return int
   *   Selected default city id.
   */
  private function getDefaultCity() {
    $city_id = $this->config->get('city_id');

    if (!empty($city_id)) {
      return $city_id;
    }

    return FALSE;
  }

  /**
   * Get units settings.
   *
   * @return string
   *   Type of units.
   */
  private function getUnits() {
    $units = $this->config->get('units');

    return empty($units) ? 'metric' : $units;
  }

}
