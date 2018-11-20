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
   * Editable Tokens Config.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  private $configTokens;

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
    $this->configTokens = $config->getEditable('eloqua_api_redux.tokens');
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
      return $token['access_token'];
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

    // Only do a request if we have a valid refresh token.
    if ($refreshToken = $this->getTokenCache('refresh_token')) {
      // TODO Add better handling for expired refresh tokens.
      $params = [
        'redirect_uri' => Url::fromUri('internal:/eloqua_api_redux/callback', ['absolute' => TRUE])->toString(),
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken,
      ];

      $token = $this->doTokenRequest($params);

      if (!empty($token)) {
        return $token['access_token'];
      }
    }
    else {
      $this->loggerFactory->get('eloqua_api_redux')->error("Refresh Token is expired, Update tokens by visiting Eloqua API settings page.");
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
        $this->setTokenCache($contentsDecoded);

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
  private function setTokenCache(array $token) {
    if (!empty($token)) {
      // Save the token.
      $accessToken = [
        'value' => $token['access_token'],
        'expire' => REQUEST_TIME + $this->tokenAge('access_token'),
      ];

      $refreshToken = [
        'value' => $token['refresh_token'],
        'expire' => REQUEST_TIME + $this->tokenAge('refresh_token'),
      ];

      $this->configTokens
        ->set('access_token', serialize($accessToken))
        ->set('refresh_token', serialize($refreshToken))
        ->save();

      // TODO Maybe add some logging?
      return TRUE;
    }

    // TODO Maybe add some logging?
    return FALSE;
  }

  /**
   * Get Access or Request Token from "config cache".
   *
   * @return string|false
   */
  public function getTokenCache($tokenType) {
    // Check config "cache".
    if ($cache = $this->configTokens->get($tokenType)) {
      $response = unserialize($cache);

      $now = REQUEST_TIME;
      $expire = $response['expire'];

      // Manually validate if the token is still fresh
      if ($expire > $now) {
        // Return result from cache if found.
        return $response['value'];
      }
    }

    return FALSE;
  }

  /**
   * Get Cache Age.
   *
   * @param $tokenType
   *   What type of token is it?
   *
   * @return int
   * Token age.
   */
  private function tokenAge($tokenType) {
    $cacheAge = 0;
    $offset = 3600;

    if ($tokenType == 'access_token') {
      // Cache for 8 hours.
      // Offset a little so we can refresh before time.
      $cacheAge = 28800 - $offset;
    }
    if ($tokenType == 'refresh_token') {
      // Cache for 1 year.
      $cacheAge = 31557600 - $offset;
    }

    return $cacheAge;
  }

}
