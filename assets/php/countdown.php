<?php
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Configurações
$frame_rate = 1; // FPS (frames por segundo)
//$duration = 30 * 60; // 30 minutos em segundos
$tolerance = 10; // Tolerância de 10 segundos para cache

// Obtém o timestamp da URL
if (!isset($_GET['ts']) || !is_numeric($_GET['ts'])) {
    die('Invalid timestamp');
}
$timestamp = (int) $_GET['ts'];
$current_time = time();
$remaining_time = $timestamp - $current_time;

// Se já expirou, mostra GIF "Expirado"
if ($remaining_time <= 0) {
    $remaining_time = 0;
    $expired = true;
} else {
    $expired = false;
}

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
$frames = !$expired ? $remaining_time : 1;
$delay = 100; // 1 segundo por frame (100 = 1s no GIF)

$imagick = new Imagick();
$imagick->setFormat('gif');

for ($i = 0; $i <= (($expired) ? $frames : ($frames + 1)); $i++) {
    $frame = new Imagick();
    $frame->newImage($width, $height, new ImagickPixel('white'));

    if(!$expired) {
        $frame->setImageIterations(1);
    }

    $frame->setImageFormat('gif');

    $draw = new ImagickDraw();

    if(!$expired && $i < ($frames + 1)) {
        $text = gmdate('H:i:s', $remaining_time - $i);

        $draw->setFont('../fonts/AtkinsonHyperlegible-Bold.ttf');
        $draw->setFontSize(20);
        $draw->setFontWeight(700);
        $draw->annotation(80, 150, "HORAS");
        $draw->annotation(255, 150, "MINUTOS");
        $draw->annotation(440, 150, "SEGUNDOS");
    } else {
        if($expired) {
            $text = ($i == 0) ? 'Expirado' : '';
        } else {
            $text = 'Expirado';
        }
        
    }
    
    $draw->setFillColor('black');
    $draw->setFont('../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf');
    $draw->setFontSize(100);
    $draw->setFontWeight(300);
    $draw->annotation(50, 120, $text);
    
    
    $frame->drawImage($draw);
    $frame->setImageDelay($delay);
    $imagick->addImage($frame);
}

// Otimiza o GIF e salva em cache
$imagick->optimizeImageLayers();
$imagick->writeImages($cache_file, true);

// Exibe o GIF gerado
echo file_get_contents($cache_file);
exit;