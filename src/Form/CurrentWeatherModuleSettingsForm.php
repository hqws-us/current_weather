<?php

namespace Drupal\current_weather\Form;

use Cmfcmf\OpenWeatherMap\Exception;
use Cmfcmf\OpenWeatherMap\NotFoundException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Url;
use Drupal\current_weather\Services\CurrentWeatherService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CurrentWeatherModuleSettingsForm provide setting form for the module.
 *
 * @package Drupal\current_weather\Form
 */
class CurrentWeatherModuleSettingsForm extends ConfigFormBase {

  /**
   * Config name.
   *
   * @var string
   */
  const CONFIG_NAME = CurrentWeatherService::CONFIG_NAME;

  /**
   * Current Weather service.
   *
   * @var \Drupal\current_weather\Services\CurrentWeatherService
   */
  protected $currentWeatherService;

  /**
   * CurrentWeatherModuleSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\current_weather\Services\CurrentWeatherService $currentWeatherService
   *   Current Weather service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CurrentWeatherService $currentWeatherService) {
    parent::__construct($config_factory);
    $this->currentWeatherService = $currentWeatherService;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_weather.service')
    );
  }

  /**
   * {@inheritDoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'current_weather__module__settings__form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Current Weather pages'),
      '#default_value' => $config->get('status'),
    ];

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('OpenWeatherMap key'),
      '#default_value' => $config->get('key'),
      '#description' => $this->t('All requests require a free API key (sometimes called "APPID") from OpenWeatherMap. To retrieve your API key, @link.', [
        '@link' => Link::fromTextAndUrl($this->t('sign up for an OpenWeatherMap account'), Url::fromUri('https://home.openweathermap.org/users/sign_up'))->toString(),
      ]),
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['units'] = [
      '#type' => 'select',
      '#title' => $this->t('Units'),
      '#default_value' => $config->get('units'),
      '#options' => [
        'imperial' => $this->t('Imperial'),
        'metric' => $this->t('Metric'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $weather_page_url = Url::fromRoute('current_weather.page');

    $form['default_weather'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings for default Weather page (@link)', [
        '@link' => Link::fromTextAndUrl($weather_page_url->toString(), $weather_page_url)->toString(),
      ]),
      '#tree' => FALSE,
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['default_weather']['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#default_value' => $config->get('country'),
      '#options' => CountryManager::getStandardList(),
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => FALSE],
        ],
        'required' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['default_weather']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#default_value' => $config->get('city'),
      '#description' => $this->t('For more correct city\'s search add region code after comma and space. Example: "Portland, or". It\'s relevant if in the selected country more than one city with the same name.'),
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => FALSE],
        ],
        'required' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['default_weather']['direct_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I know city id'),
      '#default_value' => $config->get('direct_id'),
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['default_weather']['city_id'] = [
      '#type' => 'number',
      '#title' => $this->t('City id'),
      '#default_value' => $config->get('city_id'),
      '#min' => 0,
      '#states' => [
        'visible' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="status"]' => ['checked' => TRUE],
          ':input[name="direct_id"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    try {
      $weather = $this->getWeather($form_state);

      if (!$form_state->getValue('direct_id')) {
        if (strtolower($weather->city->name) !== strtolower($form_state->getValue('city'))) {
          $form_state->setError($form['default_weather']['city'], $this->t('Wrong city name, Weather for this city has not been found.'));
        }

        if (strtolower($weather->city->country) !== strtolower($form_state->getValue('country'))) {
          $form_state->setError($form['default_weather']['city'], $this->t('Wrong city name, Weather for this city has not been found.'));
        }
      }
    }
    catch (\Exception $exception) {
      // Api Key validation.
      if ($exception instanceof Exception && !$exception instanceof NotFoundException) {
        $form_state->setError($form['key'], $exception->getMessage());
      }

      // City validation.
      if ($exception instanceof NotFoundException) {
        if ($form_state->getValue('direct_id')) {
          $form_state->setError($form['default_weather']['city_id'], $this->t('Wrong city ID, Weather for this city has not been found.'));
        }
        else {
          $form_state->setError($form['default_weather']['city'], $this->t('Wrong city name, Weather for this city has not been found.'));
        }
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $values = $form_state->getValues();
    $clearKeys = $form_state->getCleanValueKeys();
    $clearKeys[] = 'submit';

    try {
      /** @var \Cmfcmf\OpenWeatherMap\CurrentWeather $weather */
      $weather = $this->getWeather($form_state);

      if (!$values['direct_id']) {
        $values['city_id'] = $weather->city->id;
      }
      else {
        $values['city'] = $weather->city->name;
        $values['country'] = $weather->city->country;
      }

      foreach ($clearKeys as $key) {
        if (array_key_exists($key, $values)) {
          unset($values[$key]);
        }
      }

      foreach ($values as $key => $value) {
        $config->set($key, trim($value));
      }

      $config->save();

      $this->messenger()->addStatus($this->t('Configuration saved! You may check weather on this @link', [
        '@link' => Link::createFromRoute($this->t('page'), 'current_weather.page')->toString(),
      ]));
    }
    catch (\Exception $exception) {
      $this->messenger()->addStatus($this->t('Something went wrong. Please try again later.'));
      $this->logger('current_weather')->error($exception->getMessage());
    }
  }

  /**
   * Get weather.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Cmfcmf\OpenWeatherMap\CurrentWeather|\Exception|false
   *   Result of requesting weather.
   *
   * @throws \Cmfcmf\OpenWeatherMap\Exception
   * @throws \Drupal\custom_weather\Exception\CustomWeatherException
   */
  private function getWeather(FormStateInterface $form_state) {
    $city = $form_state->getValue('direct_id') ? trim($form_state->getValue('city_id')) : trim($form_state->getValue('city')) . ', ' . trim($form_state->getValue('country'));

    return $this->currentWeatherService->getWeather($city, trim($form_state->getValue('key')));
  }

}
