<?php
namespace App\Services;
class IosReceiptVerifier {
  public function verify(string $receipt): bool {
    // Stub: In production, call Apple verifyReceipt endpoint and validate bundle/product/expiration.
    return strlen($receipt) > 20;
  }
}
