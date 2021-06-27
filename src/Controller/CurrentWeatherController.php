<?php

namespace Drupal\current_weather\Controller;

use Cmfcmf\OpenWeatherMap\Exception;
use Cmfcmf\OpenWeatherMap\NotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Messenger\Messenger;
use Drupal\current_weather\Services\CurrentWeatherService;
use Drupal\custom_weather\Exception\CustomWeatherException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class CurrentWeatherController provide controller for current weather pages.
 *
 * @package Drupal\current_weather\Controller
 */
class CurrentWeatherController extends ControllerBase {

  /**
   * Current weather service.
   *
   * @var \Drupal\current_weather\Services\CurrentWeatherService
   */
  protected $currentWeatherService;

  /**
   * CurrentWeatherController constructor.
   *
   * @param \Drupal\current_weather\Services\CurrentWeatherService $currentWeatherService
   *   Current weather service.
   */
  public function __construct(CurrentWeatherService $currentWeatherService) {
    $this->currentWeatherService = $currentWeatherService;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_weather.service')
    );
  }

  /**
   * Weather page controller.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string|int $city
   *   City.
   * @param string $country
   *   Country code.
   *
   * @return array
   *   Weather render array.
   */
  public function page(Request $request, $city = '', string $country = '') {
    try {
      $weather = $this->getWeather($city, $country);

      return [
        '#theme' => 'current_weather',
        '#icon_url' => $weather->weather->getIconUrl(),
        '#city' => $weather->city->name . ', ' . $weather->city->country,
        '#description' => $weather->weather->description,
        '#temperature' => $weather->temperature->getFormatted(),
        '#humidity' => $weather->humidity->getFormatted(),
        '#wind_speed' => $weather->wind->speed->getFormatted(),
        '#wind_speed_description' => $weather->wind->speed->getDescription(),
        '#wind_direction' => $weather->wind->direction->getDescription(),
        '#pressure' => $weather->pressure->getFormatted(),
        '#clouds' => $weather->clouds->getFormatted(),
      ];
    }
    catch (\Exception $exception) {
      // Api Key validation.
      if ($exception instanceof Exception || $exception instanceof CustomWeatherException) {
        if ($this->currentUser()->hasPermission('access to configure current_weather module') && empty($country) && empty($city)) {
          $this->messenger()->addMessage($this->t('Please check out module settings @link', [
            '@link' => Link::createFromRoute($this->t('page'), 'current_weather.settings')->toString(),
          ]), Messenger::TYPE_ERROR);
        }

        throw new NotFoundHttpException();
      }

      // City validation.
      if ($exception instanceof NotFoundException) {
        throw new NotFoundHttpException();
      }
    }

    return [];
  }

  /**
   * Get page title.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string|int $city
   *   City.
   * @param string $country
   *   Country code.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Page title.
   */
  public function pageTitle(Request $request, $city = '', string $country = '') {
    try {
      $weather = $this->getWeather($city, $country);

      return $this->t('Current weather for: @city, @country', [
        '@city' => $weather->city->name,
        '@country' => $weather->city->country,
      ]);
    }
    catch (\Exception $exception) {
      return $this->t('Current Weather');
    }
  }

  /**
   * Get weather based on incoming controller parameters.
   *
   * @param string|int $city
   *   City.
   * @param string $country
   *   Country code.
   *
   * @return \Cmfcmf\OpenWeatherMap\CurrentWeather|\Exception|false
   *   Result of requesting weather.
   *
   * @throws \Cmfcmf\OpenWeatherMap\Exception
   * @throws \Drupal\custom_weather\Exception\CustomWeatherException
   */
  private function getWeather($city = '', string $country = '') {
    if ((!empty($city) && empty($country)) || (!empty($city) && !empty($country))) {
      // Get weather by city.
      $weather = $this->currentWeatherService->getWeather(trim($city));
    }
    elseif (!empty($city) && is_string($city) && empty($country)) {
      // Get weather by city and country.
      $weather = $this->currentWeatherService->getWeather(trim($city) . ', ' . trim($country));
    }
    else {
      // Get weather for default city.
      $weather = $this->currentWeatherService->getWeather();
    }

    return $weather;
  }

}
