
<?php

$keyFile = "C:/xampp/private/openrouter_key.txt";

$apiKey = "";

if (is_readable($keyFile)) {
    $apiKey = trim(file_get_contents($keyFile));
}

$openRouterModel = "openrouter/free";

$openRouterApiUrl = "https://openrouter.ai/api/v1/chat/completions";
