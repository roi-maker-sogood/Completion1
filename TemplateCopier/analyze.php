<?php

require_once __DIR__ . '/vendor/autoload.php';
use Smalot\PdfParser\Parser;

function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function failRequest($message, $status = 400)
{
    http_response_code($status);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Template analysis failed</title>
        <style>
            body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f1f5f4;color:#182b28;font:16px/1.5 system-ui,sans-serif}
            main{width:min(100%,620px);padding:28px;border:1px solid #d8e2de;background:#fff}
            h1{margin-top:0;font-size:24px}a{color:#087f68}
        </style>
    </head>
    <body><main><h1>Template analysis failed</h1><p><?= escapeHtml($message) ?></p><a href="index.php">Return to upload</a></main></body>
    </html>
    <?php
    exit;
}

function outputTextFromGeminiResponse(array $response)
{
    $textParts = [];
    foreach (($response['candidates'][0]['content']['parts'] ?? []) as $part) {
        if (isset($part['text']) && is_string($part['text'])) {
            $textParts[] = $part['text'];
        }
    }

    return implode("\n", $textParts);
}

function requestVisionAnalysis($pdfPath, $extractedText, $apiKey, &$model, $fallbackModel = '')
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL is required. Enable the curl extension in XAMPP php.ini and restart Apache.');
    }

    $pdfData = file_get_contents($pdfPath);
    if ($pdfData === false) {
        throw new RuntimeException('The uploaded PDF could not be read.');
    }

    $textContext = mb_substr($extractedText, 0, 24000);
    $prompt = <<<PROMPT
Analyze the attached PDF visually and rebuild each page as editable design elements. Use the accompanying extracted PDF text only to help transcribe exact wording; the PDF page visuals are authoritative for layout and styling.

Return one JSON object with this shape:
{"pages":[{"page":1,"width":612,"height":792,"elements":[{"type":"text","role":"title","text":"Example","x":40,"y":40,"width":500,"height":50,"fontSize":32,"fontFamily":"Arial","fontWeight":"bold","color":"#111111","alignment":"left"}]}]}

Requirements:
- Include every PDF page, in page order. Coordinates are points/pixels from the page's top-left corner; x/y describe the top-left of each element.
- Identify visible text, rectangles, ellipses, lines, images, and meaningful page backgrounds. Use type text, rectangle, ellipse, line, or image. For image elements use description for a short description.
- For each element provide a concise role and approximate x, y, width, and height. For text include exact readable text, fontSize, fontFamily when recognizable, fontWeight, color, and alignment. For shapes include fill, stroke, and strokeWidth when visible.
- Use numeric page and geometry values. Use hex colors. Do not invent content that is not visible. Do not wrap the JSON in Markdown.
- Keep the element list faithful to the visual page; do not include invisible PDF internals.

Extracted PDF text (may be incomplete or have reading-order errors):
{$textContext}
PROMPT;

    $requestBody = [
        'contents' => [[
            'parts' => [
                [
                    'inlineData' => [
                        'mimeType' => 'application/pdf',
                        'data' => base64_encode($pdfData),
                    ],
                ],
                ['text' => $prompt],
            ],
        ]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature' => 0.1,
        ],
    ];

    $retryableStatuses = [429, 500, 502, 503, 504];
    $maxAttempts = 3;
    $responseBody = false;
    $status = 0;
    $models = [$model];
    if ($fallbackModel !== '' && $fallbackModel !== $model) {
        $models[] = $fallbackModel;
    }

    foreach ($models as $modelIndex => $candidateModel) {
        $model = $candidateModel;
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($candidateModel)
            . ':generateContent';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $curl = curl_init($endpoint);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'x-goog-api-key: ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode($requestBody),
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 240,
            ]);

            $responseBody = curl_exec($curl);
            $curlError = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($responseBody === false) {
                throw new RuntimeException('Could not reach the vision API: ' . $curlError);
            }

            if (!in_array($status, $retryableStatuses, true) || $attempt === $maxAttempts) {
                break;
            }

            sleep($attempt);
        }

        if ($status >= 200 && $status < 300) {
            break;
        }
        if ($status !== 503 || $modelIndex === count($models) - 1) {
            break;
        }
    }

    $response = json_decode($responseBody, true);
    if ($status < 200 || $status >= 300) {
        if (in_array($status, $retryableStatuses, true)) {
            throw new RuntimeException('Gemini is temporarily unavailable after retrying its available models (' . $status . '). Please try again later.');
        }

        $apiMessage = is_array($response)
            ? ($response['error']['message'] ?? 'Gemini returned an error.')
            : 'Gemini returned an invalid error response.';
        throw new RuntimeException('Gemini API error (' . $status . '): ' . $apiMessage);
    }

    if (!is_array($response)) {
        throw new RuntimeException('Gemini returned an invalid response.');
    }

    $analysis = json_decode(outputTextFromGeminiResponse($response), true);
    if (!is_array($analysis)) {
        throw new RuntimeException('Gemini did not return valid design JSON. Please retry with this PDF.');
    }

    return $analysis;
}

function normalizePages(array $analysis)
{
    $rawPages = $analysis['pages'] ?? [];
    if (!$rawPages && isset($analysis['elements'])) {
        $rawPages = [[
            'page' => 1,
            'width' => $analysis['page']['width'] ?? 612,
            'height' => $analysis['page']['height'] ?? 792,
            'elements' => $analysis['elements'],
        ]];
    }

    $pages = [];
    foreach ($rawPages as $pageIndex => $rawPage) {
        if (!is_array($rawPage)) {
            continue;
        }

        $elements = [];
        foreach (($rawPage['elements'] ?? []) as $elementIndex => $rawElement) {
            if (!is_array($rawElement)) {
                continue;
            }

            $type = strtolower((string) ($rawElement['type'] ?? 'text'));
            if ($type === 'rect' || $type === 'shape') {
                $type = 'rectangle';
            } elseif ($type === 'circle') {
                $type = 'ellipse';
            }
            if (!in_array($type, ['text', 'rectangle', 'ellipse', 'line', 'image'], true)) {
                $type = isset($rawElement['text']) || isset($rawElement['content']) ? 'text' : 'rectangle';
            }

            $text = (string) ($rawElement['text'] ?? $rawElement['content'] ?? $rawElement['description'] ?? '');
            $elements[] = [
                'id' => 'element_' . ($pageIndex + 1) . '_' . ($elementIndex + 1),
                'type' => $type,
                'role' => (string) ($rawElement['role'] ?? ''),
                'text' => $text,
                'description' => (string) ($rawElement['description'] ?? ''),
                'x' => (float) ($rawElement['x'] ?? 0),
                'y' => (float) ($rawElement['y'] ?? 0),
                'width' => max(1, (float) ($rawElement['width'] ?? 120)),
                'height' => max(1, (float) ($rawElement['height'] ?? 32)),
                'fontSize' => max(1, (float) ($rawElement['fontSize'] ?? 16)),
                'fontFamily' => (string) ($rawElement['fontFamily'] ?? 'Arial'),
                'fontWeight' => (string) ($rawElement['fontWeight'] ?? 'normal'),
                'fontStyle' => (string) ($rawElement['fontStyle'] ?? 'normal'),
                'color' => (string) ($rawElement['color'] ?? '#111111'),
                'alignment' => (string) ($rawElement['alignment'] ?? 'left'),
                'fill' => (string) ($rawElement['fill'] ?? 'none'),
                'stroke' => (string) ($rawElement['stroke'] ?? 'none'),
                'strokeWidth' => max(0, (float) ($rawElement['strokeWidth'] ?? 1)),
                'rotation' => (float) ($rawElement['rotation'] ?? 0),
                'editable' => true,
            ];
        }

        $minX = 0.0;
        $minY = 0.0;
        foreach ($elements as $element) {
            $minX = min($minX, (float) ($element['x'] ?? 0));
            $minY = min($minY, (float) ($element['y'] ?? 0));
        }

        $offsetX = $minX < 0 ? abs($minX) + 8 : 0;
        $offsetY = $minY < 0 ? abs($minY) + 8 : 0;
        if ($offsetX > 0 || $offsetY > 0) {
            foreach ($elements as &$element) {
                $element['x'] = (float) $element['x'] + $offsetX;
                $element['y'] = (float) $element['y'] + $offsetY;
            }
            unset($element);
        }

        $pages[] = [
            'page' => (int) ($rawPage['page'] ?? $pageIndex + 1),
            'width' => max(1, (float) ($rawPage['width'] ?? 612)),
            'height' => max(1, (float) ($rawPage['height'] ?? 792)),
            'background' => (string) ($rawPage['background'] ?? '#ffffff'),
            'elements' => $elements,
        ];
    }

    if (!$pages) {
        throw new RuntimeException('The vision model did not identify any pages.');
    }

    return $pages;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    failRequest('Upload a PDF from the template screen.');
}

if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
    failRequest('The PDF upload failed. Check the file size and try again.');
}

$file = $_FILES['template'];
$maxSize = 10 * 1024 * 1024;
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension !== 'pdf') {
    failRequest('Please upload a PDF file.');
}
if ((int) $file['size'] > $maxSize) {
    failRequest('The PDF is too large. Maximum size is 10 MB.');
}

$apiKey = trim((string) getenv('GEMINI_API_KEY'));
$model = trim((string) (getenv('GEMINI_MODEL') ?: 'gemini-3.8-flash'));
$configuredFallbackModel = getenv('GEMINI_FALLBACK_MODEL');
$fallbackModel = $configuredFallbackModel === false
    ? 'gemini-3.5-flash-lite'
    : trim((string) $configuredFallbackModel);
if ($apiKey === '') {
    failRequest('Set the GEMINI_API_KEY environment variable for Apache, then restart Apache and retry.', 503);
}

$uploadDir = __DIR__ . '/uploads/';
$blueprintDir = $uploadDir . 'blueprints/';
foreach ([$uploadDir, $blueprintDir] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        failRequest('The upload folder could not be created.', 500);
    }
}

$fileId = uniqid('template_', true);
$savedFile = $uploadDir . $fileId . '.pdf';
if (!move_uploaded_file($file['tmp_name'], $savedFile)) {
    failRequest('The uploaded PDF could not be saved.', 500);
}

try {
    $pdf = (new Parser())->parseFile($savedFile);
    $pdfDetails = $pdf->getDetails();
    $pdfText = $pdf->getText();
    $analysis = requestVisionAnalysis($savedFile, $pdfText, $apiKey, $model, $fallbackModel);
    $pages = normalizePages($analysis);
} catch (Throwable $exception) {
    failRequest($exception->getMessage(), 502);
}

$blueprint = [
    'version' => '2.0',
    'created_at' => date(DATE_ATOM),
    'source' => [
        'original_name' => basename($file['name']),
        'saved_file' => basename($savedFile),
        'extension' => 'pdf',
        'size' => (int) $file['size'],
    ],
    'pdf' => [
        'pages' => count($pages),
        'details' => $pdfDetails,
    ],
    'analysis' => [
        'provider' => 'Google Gemini',
        'model' => $model,
        'method' => 'vision',
    ],
    'statistics' => [
        'pages' => count($pages),
        'elements' => array_sum(array_map(static function ($page) {
            return count($page['elements']);
        }, $pages)),
    ],
    'pages' => $pages,
];

$blueprintFile = $blueprintDir . $fileId . '.json';
$encodedBlueprint = json_encode(
    $blueprint,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($encodedBlueprint === false || file_put_contents($blueprintFile, $encodedBlueprint, LOCK_EX) === false) {
    failRequest('The editable design JSON could not be saved.', 500);
}

header('Location: editor.php?id=' . rawurlencode($fileId), true, 303);
exit;