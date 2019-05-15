<?php

namespace Drupal\eloqua_api_redux\Service;

/**
 * Interface for migrations.
 */
class EloquaAuthDefaultFallback implements EloquaAuthFallbackInterface {

  /**
   * Default generateTokensByResourceOwner implementation.
   *
   * @inheritDoc
   */
  public function generateTokensByResourceOwner() {
    return FALSE;
  }

}
