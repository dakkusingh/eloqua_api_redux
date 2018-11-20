<?php

namespace Drupal\eloqua_api_redux\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
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
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   */
  public function __construct(ConfigFactory $config,
                              LoggerChannelFactoryInterface $loggerFactory,
                              CacheBackendInterface $cacheBackend) {
    $this->config = $config->get('eloqua_api_redux.settings');
    $this->configEditable = $config->getEditable('eloqua_api_redux.settings');
    $this->loggerFactory = $loggerFactory;
    $this->cacheBackend = $cacheBackend;
  }

  /**
   * Fetch Eloqua API Access Token by Auth Code.
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
  public function getAccessTokenByAuthCode($code) {
    if ($accessToken = $this->getTokenCache('access_token')) {
      return $accessToken;
    }

    $params = [
      'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
      'grant_type' => 'authorization_code',
      'code' => $code,
    ];

    $token = $this->doTokenRequest($params);

    if (!empty($token)) {
      return $token['accessToken'];
    }
    return FALSE;

  }

  /**
   * Fetch Eloqua API Access Token by Refresh Token.
   *
   * If the access token has expired, you should send your stored Refresh Token
   * to login.eloqua.com/auth/oauth2/token to obtain new tokens.
   *
   * @return bool|mixed
   *   If the request is successful, the response is a JSON body containing
   *   a new access token, token type, access token expiration time, and
   *   new refresh token
   */
  public function getAccessTokenByRefreshToken() {
    if ($accessToken = $this->getTokenCache('access_token')) {
      return $accessToken;
    }
    
    // TODO Add better handling for expired refresh tokens.
    $params = [
      'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
      'grant_type' => 'refresh_token',
      'refresh_token' => $this->config->get('refresh_token'),
    ];

    $token = $this->doTokenRequest($params);

    if (!empty($token)) {
      return $token['access_token'];
    }
    return FALSE;
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
        $contents = $response->getBody()->getContents();
        // TODO Add debugging options.
        // ksm(Json::decode($contents));
        $contentsDecoded = Json::decode($contents);

        // TODO Tokens are saved in config as a form of persistent storage.
        $this->saveTokens($contentsDecoded);

        // Set tokens in Cache for better expiry control.
        $this->setTokenCache('access_token', $contentsDecoded['access_token']);
        $this->setTokenCache('refresh_token', $contentsDecoded['refresh_token']);

        return $contentsDecoded;
      }
    }
    catch (GuzzleException $e) {
      // TODO Add debugging options.
      // TODO Add better handling for expired refresh & access tokens.
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

  /**
   * Get Access or Request Token from Cache.
   *
   * @return string|false
   */
  public function getTokenCache($tokenType) {
    $cid = 'eloqua_api_redux:' . $tokenType;
    // Check cache.
    if ($cache = $this->cacheBackend->get($cid)) {
      $response = $cache->data;
      // Return result from cache if found.
      return $response;
    }

    return FALSE;
  }

  /**
   * Set Token cache.
   *
   * Authorization Codes expire in 60 seconds (intended for immediate use)
   * Access Tokens expire in 8 hours
   * Refresh Tokens expire in 1 year
   * Refresh Tokens will expire immediately after being used to obtain new
   * tokens, or after 1 year if they are not used to obtain new tokens.
   *
   * @param $tokenType
   *   What type of token is it?
   */
  public function setTokenCache($tokenType, $token) {
    $cacheAge = 0;

    if ($tokenType == 'access_token') {
      // Cache for 8 hours.
      $cacheAge = 28800;
    }
    if ($tokenType == 'refresh_token') {
      // Cache for 1 year.
      $cacheAge = 31557600;
    }

    $cid = 'eloqua_api_redux:' . $tokenType;
    // TODO Meh this will get cleared when the site caches are being cleared.
    $this->cacheBackend->set($cid, $token, time() + $cacheAge);
  }

}
