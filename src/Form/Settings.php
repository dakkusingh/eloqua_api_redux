<?php

namespace Drupal\eloqua_api_redux\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Eloqua API Settings.
 */
class Settings extends ConfigFormBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Settings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date Formatter.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              DateFormatterInterface $date_formatter) {
    parent::__construct($config_factory);
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eloqua_api_redux_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['eloqua_api_redux.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('eloqua_api_redux.settings');

    $form['client'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Client Settings'),
    ];

    $form['client']['help'] = [
      '#type' => '#markup',
      '#markup' => $this->t('To get your Client ID, you need to register your application. See details on @link.',
        [
          '@link' => Link::fromTextAndUrl('https://docs.oracle.com/cloud/latest/marketingcs_gs/OMCAC/Authentication_Auth.html',
            Url::fromUri('https://docs.oracle.com/cloud/latest/marketingcs_gs/OMCAC/Authentication_Auth.html'))->toString(),
        ]),
    ];

    $form['client']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
    ];

    $form['client']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('client_secret'),
    ];

    if ($config->get('client_id') != '' && $config->get('client_secret') != '') {
      $options = ['attributes' => ['target' => '_blank']];

      $form['client']['access_token'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Access Token'),
        '#default_value' => $config->get('access_token'),
        '#disabled' => TRUE,
        '#description' => $this->t('Access Tokens expire in 8 hours. To get your Access Token, @link.',
          [
            '@link' => Link::fromTextAndUrl('click here', Url::fromUri($this->accessUrl(), $options))->toString(),
          ]),
      ];

      $form['client']['refresh_token'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Refresh Token'),
        '#disabled' => TRUE,
        '#description' => $this->t('Refresh Tokens expire after 1 year if they are not used to obtain new tokens. To get your Refresh Token, @link.',
          [
            '@link' => Link::fromTextAndUrl('click here', Url::fromUri($this->accessUrl(), $options))->toString(),
          ]),
        '#default_value' => $config->get('refresh_token'),
      ];
    }
    
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('eloqua_api_redux.settings')
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('refresh_token', $form_state->getValue('refresh_token'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Generate the Access Url.
   *
   * See details at
   * https://docs.oracle.com/cloud/latest/marketingcs_gs/OMCAC/Authentication_Auth.html.
   *
   * @return string
   *   URL.
   */
  private function accessUrl() {
    $config = $this->config('eloqua_api_redux.settings');
    $redirectUrl = Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString();
    $urlBase = $config->get('api_uri') . 'authorize';

    $query = [
      'response_type' => 'code',
      'client_id' => $config->get('client_id'),
      'redirect_uri' => $redirectUrl,
    ];

    $url = Url::fromUri($urlBase, [
      'query' => $query,
      'absolute' => TRUE,
    ])->toString();

    return $url;
  }

}
