<?php
include 'config.php'; // qui carichi la OPENAI_API_KEY dal .env

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$input = $body['input'] ?? '';

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Input mancante']);
    exit;
}

$payload = [
'model' => 'gpt-4',
'messages' => [
    [
    'role' => 'system',
    'content' => 'Sei un assistente esperto nella redazione di testi argomentativi, didattici o informativi.'
    ],
    [
    'role' => 'user',
    'content' => <<<EOT
A partire dai seguenti paragrafi:

$input

1. Genera un testo coerente e completo che **sintetizzi**, **ampli** e **arricchisca** i contenuti.  
2. Se necessario, aggiungi informazioni aggiuntive rilevanti per migliorare la comprensione.  
3. Utilizza il **grassetto** con i tag HTML `<b>` per evidenziare concetti chiave.  
4. Utilizza il **corsivo** con i tag HTML `<i>` per termini tecnici, definizioni o esempi.  
5. Separa i paragrafi andando **due volte a capo** (`\n\n`) per una buona leggibilità.  
6. Scrivi in italiano corretto e con tono chiaro e ordinato.

Restituisci solo il testo generato, **senza spiegazioni** o note aggiuntive.
EOT
    ]
],
'temperature' => 0.7
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $openai_api_key"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
curl_close($ch);

$json = json_decode($response, true);
$text = $json['choices'][0]['message']['content'] ?? '';

file_put_contents('debug_openai_response.json', $response);

echo json_encode(['text' => $text]);
?>