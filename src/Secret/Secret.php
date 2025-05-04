<?php

namespace  AuthToken\Secret;

use Random\RandomException;

class Secret {

    /**
     * Generate the Secret Key.
     * @return string
     * @throws RandomException
     */
    public function generateSecret(): string
    {

        $numbers = range(0, 9);
        $lowerCase = range('a', 'z');
        $capitalCase = range('A', 'Z');
        $specialChars = ['!', '@', '#', '$', '%', '^', '&', '*', '-', '_', '=', '+'];
        $mergedChars = array_merge($numbers, $lowerCase, $capitalCase, $specialChars);
        
        $secret = '';

        for ($i = 0; $i < 64; $i++) {
            $randomIndex = random_int(0, count($mergedChars) - 1);
            $secret .= $mergedChars[$randomIndex];
        }

        $key = fopen('src/Secret/secret.txt', 'w');
        fwrite($key, $secret);
        fclose($key);
        return $secret;

    }

}
