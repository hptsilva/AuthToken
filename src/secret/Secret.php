<?php

namespace  AuthToken\secret;

class Secret {

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

        $file = fopen('src/secret/secret.txt', 'w');
        fwrite($file, $secret);
        fclose($file);

        return $secret;

    }

}
