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

        // Determine project root and .env path
        $projectRoot = dirname(__DIR__, 2);
        $envPath = $projectRoot . '/.env';

        // Prepare the line to write (wrap in double quotes)
        $envLine = 'AUTHTOKEN_APP_SECRET="' . $secret . '"';

        // If .env exists, update AUTHTOKEN_APP_SECRET line if present, otherwise append
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $found = false;
            foreach ($lines as $i => $line) {
                // ignore comments and blank lines
                $trimmed = ltrim($line);
                if (stripos($trimmed, 'AUTHTOKEN_APP_SECRET=') === 0) {
                    $lines[$i] = $envLine;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $lines[] = $envLine;
            }
            // Preserve existing line endings
            file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
        } else {
            // Create .env and write APP_SECRET
            file_put_contents($envPath, $envLine . PHP_EOL);
        }

        // Update runtime environment as well
        $_ENV['AUTHTOKEN_APP_SECRET'] = $secret;
        putenv('AUTHTOKEN_APP_SECRET=' . $secret);

        return $secret;

    }

}
