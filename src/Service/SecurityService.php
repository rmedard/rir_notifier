<?php


namespace Drupal\rir_notifier\Service;

use Drupal;
use Exception;

/**
 * Class SecurityService
 * @package Drupal\rir_notifier\Service
 */
class SecurityService
{
    function encrypt($value, $nonce): ?string
    {
        $encrypted = null;
        $config_factory = Drupal::configFactory();
        $key = $config_factory->get('rir_notifier.settings')->get('crypto_secret_key');
        if (!isset($key)) {
            $key = sodium_crypto_secretbox_keygen();
            $config_factory->getEditable('rir_notifier.settings')->set('crypto_secret_key', $key)->save();
        }
        try {
//            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($value, $nonce, $key);
        } catch (Exception $e) {
            Drupal::logger('rir_notifier')->error('Error while encrypting value: ' . $value);
        }
        return $encrypted;
    }

    function decrypt($value)
    {
        $decrypted = null;
        $config_factory = Drupal::configFactory();
        $key = $config_factory->get('rir_notifier.settings')->get('crypto_secret_key');
        if (!isset($key)) {
            Drupal::logger('rir_notifier')->error('No encryption key found!');
            return null;
        }

    }
}