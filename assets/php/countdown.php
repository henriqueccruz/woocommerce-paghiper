<?php
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Configurações
$frame_rate = 1; // FPS (frames por segundo)
$tolerance = 10; // Tolerância de 10 segundos para cache

// Obtém o timestamp da URL
if (!isset($_GET['ts']) || !is_numeric($_GET['ts'])) {
    die('Invalid timestamp');
}
$timestamp = (int) $_GET['ts'];
$current_time = time();
$remaining_time = $timestamp - $current_time;

// Se já expirou, ou se está nos últimos segundos, ativa o loop de "Expirado"
$expired = ($remaining_time <= 0);
$remaining_time = max($remaining_time, 0);

// Ajusta para o múltiplo mais próximo da tolerância
$remaining_time = floor($remaining_time / $tolerance) * $tolerance;
$cache_file = "cache/countdown_{$remaining_time}.gif";

// Serve o GIF do cache se existir
if (file_exists($cache_file)) {
    readfile($cache_file);
    exit;
}

// Criar o GIF dinamicamente
$width = 600;
$height = 200;
$frames = !$expired ? $remaining_time + 1 : 1;
$delay = 100; // 1 segundo por frame (100 = 1s no GIF)

$imagick = new Imagick();
$imagick->setFormat('gif');

for ($i = 0; $i <= $frames; $i++) {
    $frame = new Imagick();

    $frame->newImage($width, $height, new ImagickPixel('white'));
    $frame->setImageFormat('gif');
    $frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $draw = new ImagickDraw();
    $frame->setImageIterations(1);

    if (!$expired && $i < $frames) {
        // Contagem regressiva normal
        $text = gmdate('H:i:s', max(0, $remaining_time - $i));

        // Labels
        $draw->setFont('../fonts/AtkinsonHyperlegible-Bold.ttf');
        $draw->setFontSize(20);
        $draw->setFontWeight(700);
        $draw->annotation(80, 150, "HORAS");
        $draw->annotation(255, 150, "MINUTOS");
        $draw->annotation(440, 150, "SEGUNDOS");

    } else {
        if($expired) {
            $frame->setImageIterations(0);
        }
        // Alternância entre "Expirado" e frame vazio nos últimos dois frames
        $text = ($i % 2 == 0) ? '' : 'Expirado';
    }

    // Texto centralizado
    $draw->setFillColor('black');
    $draw->setFont('../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf');
    $draw->setFontSize(100);
    $draw->setFontWeight(300);
    $draw->annotation(50, 120, $text);

    $frame->drawImage($draw);
    $frame->setImageDelay($delay);
    $imagick->addImage($frame);
}

// Loop infinito **somente nos dois últimos frames** (Expirado + Frame vazio)

// Otimiza o GIF e salva em cache
$imagick->optimizeImageLayers();
$imagick->writeImages($cache_file, true);

// Exibe o GIF gerado
echo file_get_contents($cache_file);
exit;
