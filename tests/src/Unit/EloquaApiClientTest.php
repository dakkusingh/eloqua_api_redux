<?php

namespace Drupal\Tests\eloqua_api_redux\Unit;

use Drupal\Component\Serialization\Json;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\Response;

use Drupal\Core\DependencyInjection\ContainerBuilder;


/**
 * Implements Eloqua api client service tests.
 *
 * @ingroup eloqua_api_redux
 *
 * @group eloqua_api_redux
 *
 * @coversDefaultClass \Drupal\eloqua_api_redux\Service\EloquaApiClient
 */
class EloquaApiClientTest extends UnitTestCase {

  /**
   * Sample json response from eloqua auth api.
   */
  const ACCESS_TOKEN_RESPONSE = 'accessTokenResponse.json';

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Config instance.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Token Config instance.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $configTokens;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Guzzle http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The module handler to load the module path.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->httpClientFactory = $this->getMockBuilder('Drupal\Core\Http\ClientFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $this->config = $this->getMockBuilder('Drupal\Core\Config\Config\Drupal\Core\Config\ImmutableConfig')
      ->disableOriginalConstructor()
      ->setMethods(['get'])
      ->getMock();

    $this->configFactory = $this->getMockBuilder('Drupal\Core\Config\ConfigFactory')
      ->disableOriginalConstructor()
      ->getMock();

    $this->configFactory->expects($this->any())
      ->method('get')
      ->with('eloqua_api_redux.settings')
      ->will($this->returnValue($this->config));

    $this->configFactory->expects($this->any())
      ->method('getEditable')
      ->with('eloqua_api_redux.tokens')
      ->will($this->returnValue($this->configTokens));

    $this->loggerFactory = $this->getMockBuilder('Drupal\Core\Logger\LoggerChannelFactoryInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->cacheBackend = $this->getMockBuilder('Drupal\Core\Cache\CacheBackendInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $this->moduleHandler = $this->getMockBuilder('Drupal\Core\Extension\ModuleHandlerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    // Set container and required dependencies.
    $container = new ContainerBuilder();
    $pathValidator = $this->getMockBuilder('Drupal\Core\Path\PathValidatorInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $unroutedUrlAssembler = $this->getMockBuilder('Drupal\Core\Utility\UnroutedUrlAssemblerInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $authFallbackService = $this->getMockBuilder('Drupal\eloqua_api_redux\Service\EloquaAuthFallbackInterface')
      ->disableOriginalConstructor()
      ->getMock();
    $container->set('path.validator', $pathValidator);
    $container->set('unrouted_url_assembler', $unroutedUrlAssembler);
    $container->set('eloqua_api_redux.auth_fallback_default', $authFallbackService);

    \Drupal::setContainer($container);

  }

  /**
   * Test the getAccessTokenByRefreshToken() method.
   *
   * @covers ::getAccessTokenByRefreshToken
   * @covers ::__construct
   */
  public function testGetAccessTokenByRefreshToken() {
    $tokenResponse = Json::decode($this->getMockResponseContents(self::ACCESS_TOKEN_RESPONSE));
    $refreshToken = 'tGzv3JOkF0XG5Qx2TlKW';

    $eloquaApiClient = $this->getMockBuilder('Drupal\eloqua_api_redux\Service\EloquaApiClient')
      ->setConstructorArgs([
        $this->configFactory,
        $this->loggerFactory,
        $this->cacheBackend,
        $this->httpClientFactory,
      ])
      ->setMethods(['getEloquaApiCache', 'doTokenRequest'])
      ->getMock();

    $eloquaApiClient->expects($this->at(0))
      ->method('getEloquaApiCache')
      ->with('access_token')
      ->will($this->returnValue(NULL));

    $eloquaApiClient->expects($this->at(1))
      ->method('getEloquaApiCache')
      ->with('refresh_token')
      ->will($this->returnValue($refreshToken));

    $eloquaApiClient->expects($this->any())
      ->method('doTokenRequest')
      ->will($this->returnValue($tokenResponse));

    $response = $eloquaApiClient->getAccessTokenByRefreshToken();
    $this->assertNotNull($response);
  }

  /**
   * Test the getAccessTokenByAuthCode() method.
   *
   * @covers ::getAccessTokenByAuthCode
   * @covers ::__construct
   */
  public function testGetAccessTokenByAuthCode() {
    $tokenResponse = Json::decode($this->getMockResponseContents(self::ACCESS_TOKEN_RESPONSE));
    $authCode = 'SplxlOBeZQQYbYS6WxSbIA';

    $eloquaApiClient = $this->getMockBuilder('Drupal\eloqua_api_redux\Service\EloquaApiClient')
      ->setConstructorArgs([
        $this->configFactory,
        $this->loggerFactory,
        $this->cacheBackend,
        $this->httpClientFactory,
      ])
      ->setMethods(['getEloquaApiCache', 'doTokenRequest'])
      ->getMock();

    $eloquaApiClient->expects($this->at(0))
      ->method('getEloquaApiCache')
      ->with('access_token')
      ->will($this->returnValue(NULL));

    $eloquaApiClient->expects($this->any())
      ->method('doTokenRequest')
      ->will($this->returnValue($tokenResponse));

    $response = $eloquaApiClient->getAccessTokenByAuthCode($authCode);
    $this->assertNotNull($response);
  }

  /**
   * Test the doTokenRequest() method.
   *
   * @covers ::doTokenRequest
   * @covers ::__construct
   */
  public function testDoTokenRequest() {
    $params = [
      'redirect_uri' => 'http://localhost/eloqua_api_redux/callback',
      'grant_type' => 'refresh_token',
      'refresh_token' => 'SplxlOBeZQQYbYS6WxSbIA',
    ];
    $mockedResponse = new Response(200,
      ['Content-Type' => 'application/json'],
      $this->getMockResponseContents(self::ACCESS_TOKEN_RESPONSE)
    );

    $httpClientFactory = $this->getMockBuilder('Drupal\Core\Http\ClientFactory')
      ->disableOriginalConstructor()
      ->setMethods(['fromOptions'])
      ->getMock();

    $guzzleClient = $this->getMockBuilder('GuzzleHttp\Client')
      ->disableOriginalConstructor()
      ->setMethods(['request'])
      ->getMock();

    $guzzleClient->expects($this->any())
      ->method('request')
      ->will($this->returnValue($mockedResponse));

    $httpClientFactory->expects($this->any())
      ->method('fromOptions')
      ->will($this->returnValue($guzzleClient));

    $eloquaApiClient = $this->getMockBuilder('Drupal\eloqua_api_redux\Service\EloquaApiClient')
      ->setConstructorArgs([
        $this->configFactory,
        $this->loggerFactory,
        $this->cacheBackend,
        $httpClientFactory,
      ])
      ->setMethods(['setEloquaApiCache', 'doBaseUrlRequest'])
      ->getMock();

    $eloquaApiClient->expects($this->at(0))
      ->method('setEloquaApiCache')
      ->with('access_token');
    $eloquaApiClient->expects($this->at(1))
      ->method('setEloquaApiCache')
      ->with('refresh_token');

    $eloquaApiClient->expects($this->any())
      ->method('setEloquaApiCache');

    $response = $eloquaApiClient->doTokenRequest($params);
    $this->assertNotEmpty($response);
  }

  /**
   * Gets mock response from the given file.
   *
   * @param string $fileName
   *   File name for mock response.
   *
   * @return string
   *   Mock response contents.
   */
  protected function getMockResponseContents($fileName) {
    return file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'Mocks' . DIRECTORY_SEPARATOR . $fileName);
  }

}
