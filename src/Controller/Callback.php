<?php

namespace Drupal\eloqua_api_redux\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Eloqua API Callback Controller.
 *
 * @package Drupal\eloqua_api_redux\Controller
 */
class Callback extends ControllerBase {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Editable Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $configEditable;

  /**
   * Uneditable Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * Callback Controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config
   *   An instance of ConfigFactory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   LoggerChannelFactoryInterface.
   */
  public function __construct(ConfigFactory $config,
                              LoggerChannelFactoryInterface $loggerFactory) {
    $this->config = $config->get('eloqua_api_redux.settings');
    $this->configEditable = $config->getEditable('eloqua_api_redux.settings');
    $this->loggerFactory = $loggerFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * Callback URL for Eloqua API Auth.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array
   */
  public function callbackUrl(Request $request) {
    $code = $request->get('code');

    // Try to get the token.
    $token = $this->getToken($code);

    // If token is not empty.
    if (!empty($token)) {
      // Save the token.
      $this->configEditable
        ->set('access_token', $token['access_token'])
        ->set('refresh_token', $token['refresh_token'])
        ->save();
      $markup = $this->t("Access token saved");
    }
    else {
      $markup = $this->t("Failed to get access token. Check log messages.");
    }

    return ['#markup' => $markup];
  }

  /**
   * Fetch Eloqua API Token.
   *
   * Use the Grant Token to obtain an Access Token and Refresh
   * Token using a POST request to the login.eloqua.com/auth/oauth2/token
   * endpoint.
   *
   * @param $code
   * Grant Token (which is in this case an Authorization Code).
   *
   * @return string|bool
   * The authorization server validates the authorization code and if valid
   * responds with a JSON body containing the Access Token, Refresh Token,
   * access token expiration time, and token type
   */
  public function getToken($code) {
    // Guzzle Client.
    $guzzleClient = new GuzzleClient([
      'base_uri' => $this->config->get('api_uri'),
    ]);

    try {
      $response = $guzzleClient->request(
        'POST',
        'token',
        [
          'form_params' => [
            'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
            'grant_type' => 'authorization_code',
            'code' => $code,
          ],
          'auth' => [
            $this->config->get('client_id'),
            $this->config->get('client_secret')
          ],
        ]
      );

      if ($response->getStatusCode() == 200) {
        // TODO Add debugging options.
        $contents = $response->getBody()->getContents();
        // ksm(Json::decode($contents));
        return Json::decode($contents);
      }
    }
    catch (GuzzleException $e) {
      // TODO Add debugging options.
      // kint($e);
      $this->loggerFactory->get('eloqua_api_redux')->error("@message", ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

}