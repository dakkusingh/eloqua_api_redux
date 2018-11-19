<?php

namespace Drupal\eloqua_api_redux\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class Eloqua API Client Service.
 *
 * @package Drupal\eloqua_api_redux\Service
 */
class EloquaApiClient {

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
   * Fetch Eloqua API Token by Auth Code.
   *
   * Use the Grant Token to obtain an Access Token and Refresh
   * Token using a POST request to the login.eloqua.com/auth/oauth2/token
   * endpoint.
   *
   * @param string $code
   *   Grant Token (which is in this case an Authorization Code).
   *
   * @return string|bool
   *   The authorization server validates the authorization code and if valid
   *   responds with a JSON body containing the Access Token, Refresh Token,
   *   access token expiration time, and token type
   */
  public function getTokenByAuthCode($code) {
    $params = [
      'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
      'grant_type' => 'authorization_code',
      'code' => $code,
    ];

    $token = $this->doTokenRequest($params);
    return $token;
  }

  /**
   * Fetch Eloqua API Token by Refresh Token.
   *
   * If the access token has expired, you should send your stored Refresh Token
   * to login.eloqua.com/auth/oauth2/token to obtain new tokens.
   *
   * @return bool|mixed
   *   If the request is successful, the response is a JSON body containing
   *   a new access token, token type, access token expiration time, and
   *   new refresh token
   */
  public function getTokenByRefreshToken() {
    $params = [
      'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
      'grant_type' => 'refresh_token',
      'refresh_token' => $this->config->get('refresh_token'),
    ];

    $token = $this->doTokenRequest($params);
    return $token;
  }

  /**
   * Do the Request.
   *
   * @param array $params
   *   Options to pass for Guzzle Request to Eloqua.
   * @param string $res
   *   Resource to call.
   *
   * @return bool|mixed
   *   If the request is successful, the response is a JSON body containing
   *   a new access token, token type, access token expiration time, and
   *   new refresh token
   */
  private function doTokenRequest(array $params, $res = 'token') {
    // Guzzle Client.
    $guzzleClient = new GuzzleClient([
      'base_uri' => $this->config->get('api_uri'),
    ]);

    try {
      $response = $guzzleClient->request(
        'POST',
        $res,
        [
          'form_params' => $params,
          'auth' => [
            $this->config->get('client_id'),
            $this->config->get('client_secret'),
          ],
        ]
      );

      if ($response->getStatusCode() == 200) {
        // TODO Add debugging options.
        $contents = $response->getBody()->getContents();
        // ksm(Json::decode($contents));
        $contentsDecoded = Json::decode($contents);
        $this->saveTokens($contentsDecoded);

        return $contentsDecoded;
      }
    }
    catch (GuzzleException $e) {
      // TODO Add debugging options.
      // ksm($e);
      $this->loggerFactory->get('eloqua_api_redux')->error("@message", ['@message' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Save newly refreshed tokens.
   *
   * @param array $token
   *   New tokens.
   *
   * @return true|false
   *   Did we save the tokens or not?
   */
  private function saveTokens(array $token) {
    if (!empty($token)) {
      // Save the token.
      $this->configEditable
        ->set('access_token', $token['access_token'])
        ->set('refresh_token', $token['refresh_token'])
        ->save();

      // TODO Maybe add some logging?
      return TRUE;
    }

    // TODO Maybe add some logging?
    return FALSE;
  }

}
