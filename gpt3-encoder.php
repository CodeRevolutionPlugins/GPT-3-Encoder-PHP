<?php

require_once __DIR__.'/vendor/autoload.php';

use CodeRevolutionPlugins\GPT3Encoder\Encoder;

$prompt = "Many words map to one token, but some don't: indivisible. Unicode characters like emojis may be split into many tokens containing the underlying bytes: 🤚🏾 Sequences of characters commonly found next to each other may be grouped together: 1234567890";
$token_array = Encoder::instance()->encode($prompt);
error_log('Token array: ' . print_r($token_array, true));
error_log('Count: ' . count($token_array));

?>