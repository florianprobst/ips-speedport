<?php

/**
 * Generate a PBKDF2 key derivation of a supplied password
 *
 * This is a hash_pbkdf2() implementation for PHP versions 5.3 and 5.4.
 * @link http://www.php.net/manual/en/function.hash-pbkdf2.php
 *
 * @param string $algo
 * @param string $password
 * @param string $salt
 * @param int $iterations
 * @param int $length
 * @param bool $rawOutput
 *
 * @return string
 */
function compat_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $rawOutput = false)
{
    $result = '';
    $loops = 1;
    if ($length > 0) {
        $loops = (int)ceil($length / strlen(hash($algo, '', $rawOutput)));
    }

    for ($i = 1; $i <= $loops; $i++) {
        $digest = hash_hmac($algo, $salt . pack('N', $i), $password, true);
        $temp = $digest;
        for ($j = 1; $j < $iterations; $j++) {
            $digest = hash_hmac($algo, $digest, $password, true);
            $temp ^= $digest;
        }
        $result .= $temp;
    }

    if (!$rawOutput) {
        $result = bin2hex($result);
    }

    if ($length > 0) {
        return substr($result, 0, $length);
    }

    return $result;
}

// test
if (realpath($_SERVER['argv'][0]) === __FILE__ && function_exists('hash_pbkdf2')) {
    $password = 'password';
    $savedPassword = 'sha256,salt,65536,4156f668bb31db3a17f4d1b91424ef0d417ad1f35d055aceaebd8da0f6a44b7e';
    list($algo, $salt, $iterations, $hash) = explode(',', $savedPassword);

    var_dump(
        $hash,
        hash_pbkdf2($algo, $password, $salt, $iterations, strlen($hash)),
        compat_pbkdf2($algo, $password, $salt, $iterations, strlen($hash))
    );
}
