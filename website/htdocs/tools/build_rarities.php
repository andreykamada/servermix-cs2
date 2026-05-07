<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$apiUrl = "https://raw.githubusercontent.com/ByMykel/CSGO-API/main/public/api/en/skins.json";

$outputFile = __DIR__ . '/../src/data/skin_rarities.json';

$json = file_get_contents($apiUrl);

if (!$json) {
    die("Erro ao baixar skins.json");
}

$skins = json_decode($json, true);

if (!$skins) {
    die("Erro ao decodificar skins.json");
}

$orderMap = [
    "Consumer Grade" => 1,
    "Industrial Grade" => 2,
    "Mil-Spec Grade" => 3,
    "Restricted" => 4,
    "Classified" => 5,
    "Covert" => 6,
    "Contraband" => 7
];

$colorMap = [
    "Consumer Grade" => "#b0c3d9",
    "Industrial Grade" => "#5e98d9",
    "Mil-Spec Grade" => "#4b69ff",
    "Restricted" => "#8847ff",
    "Classified" => "#d32ce6",
    "Covert" => "#eb4b4b",
    "Contraband" => "#e4ae39"
];

$rarities = [];

foreach ($skins as $skin) {
    if (!isset($skin["paint_index"])) continue;

    $paint = (string)$skin["paint_index"];

    $rarityName = $skin["rarity"]["name"] ?? "Unknown";

    $rarities[$paint] = [
        "name" => $rarityName,
        "color" => $skin["rarity"]["color"] ?? $colorMap[$rarityName] ?? "#2a2a2a",
        "order" => $orderMap[$rarityName] ?? 0,
        "skin" => $skin["name"] ?? "",
        "paint" => $paint
    ];
}

file_put_contents(
    $outputFile,
    json_encode($rarities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

echo "<h1>RARIDADES GERADAS COM SUCESSO!</h1>";
echo "<p>Arquivo salvo em:</p>";
echo "<pre>{$outputFile}</pre>";
echo "<p>Total de paints: " . count($rarities) . "</p>";