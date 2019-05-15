<?php

namespace Drupal\eloqua_api_redux\Service;

/**
 * Interface for migrations.
 */
interface EloquaAuthFallbackInterface {

  /**
   * Calls eloqua authentication API service to generate tokens.
   *
   * Access and refresh tokens are generated using resource owner password
   * credentials grant method.
   *
   * @return bool
   *   TRUE if tokens are generated/renewed from eloqua API.
   */
  public function generateTokensByResourceOwner();

}
