<?php
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Configurações
$frame_rate = 1; // FPS (frames por segundo)
$tolerance = 10; // Tolerância de 10 segundos para cache
$max_time_in_seconds = 1200; // 20 minutos

// Obtém o timestamp da URL
if (!isset($_GET['order_due_time']) || !is_numeric($_GET['order_due_time'])) {
    die('Invalid timestamp');
}
$timestamp = (int) $_GET['order_due_time'];
$current_time = time();
$remaining_time = $timestamp - $current_time;

// Se já expirou, ou se está nos últimos segundos, ativa o loop de "Expirado"
$expired = ($remaining_time <= 0);
$remaining_time = max($remaining_time, 0);

// --- Verificação de Ferramentas ---
$use_gifsicle = function_exists('shell_exec') && !empty(shell_exec('which gifsicle'));

// --- Lógica de Tempo & Loop ---
$is_expired = ($seconds_to_generate <= 0);
$is_longer_than_max = ($remaining_time > $max_time_in_seconds);

// Ajusta para o múltiplo mais próximo da tolerância
$remaining_time = floor($remaining_time / $tolerance) * $tolerance;
$suffix = '';
if ($is_longer_than_max) {
    $suffix = '_plus';
} elseif ($expired) {
    $suffix = '_expired';
}
$cache_file = "cache/countdown_{$remaining_time}{$suffix}.gif";

// Serve o GIF do cache se existir
if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
    exit;
}

// Criar o GIF dinamicamente
$alternating_frames_at_end = 5; // Número de frames alternados no final

$width = 600;
$height = 200;
$frames = !$expired ? $remaining_time + $alternating_frames_at_end : 1;
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

    if (!$expired && $i < $frames - ($alternating_frames_at_end)) {
        // Contagem regressiva normal
        $text = gmdate('H:i:s', max(0, $remaining_time - $i));

        // Labels
        $draw->setFont('../fonts/AtkinsonHyperlegible-Bold.ttf');
        $draw->setFontSize(20);
        $draw->setFontWeight(700);
        $draw->annotation(80, 150, "HORAS");
        $draw->annotation(255, 150, "MINUTOS");
        $draw->annotation(440, 150, "SEGUNDOS");

        // Texto centralizado
        $draw->setFillColor('black');
        $draw->setFont('../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf');
        $draw->setFontSize(100);
        $draw->setFontWeight(300);
        $draw->annotation(50, 120, $text);

        $frame->drawImage($draw);
        $frame->setImageDelay($delay);
        
        $imagick->addImage($frame);

    } else {
        // Alternância entre "Expirado" e frame vazio nos últimos dois frames
        
        if($expired) {
            $frame->setImageIterations(0);
        }
        // Alternância entre "Expirado" e frame vazio nos últimos dois frames
        $text = ($i % 2 == 0) ? '' : 'Expirado';

        // Cronometro vazio
        if($i % 2 == 0) {

            $text = gmdate('H:i:s', max(0, $remaining_time - $i));

            // Labels
            $draw->setFont('../fonts/AtkinsonHyperlegible-Bold.ttf');
            $draw->setFontSize(20);
            $draw->setFontWeight(700);
            $draw->annotation(80, 150, "HORAS");
            $draw->annotation(255, 150, "MINUTOS");
            $draw->annotation(440, 150, "SEGUNDOS");

            // Texto centralizado
            $draw->setFillColor('black');
            $draw->setFont('../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf');
            $draw->setFontSize(100);
            $draw->setFontWeight(300);
            $draw->annotation(50, 120, $text);

            $frame->drawImage($draw);
            $frame->setImageDelay($delay);
            
            $imagick->addImage($frame);

        // Texto "Expirado"
        } else {

            // Texto centralizado
            $draw->setFillColor('black');
            $draw->setFont('../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf');
            $draw->setFontSize(80);
            $draw->setFontWeight(600);
            $draw->setTextKerning(-5);
            $draw->annotation(120, 125, 'Expirado');

            $frame->drawImage($draw);
            $frame->setImageDelay($delay);
            
            $imagick->addImage($frame);

        }
    }
}

    if ($use_gifsicle) {
        $temp_file = $cache_file . '.tmp';
        $imagick->writeImages($temp_file, true);
        shell_exec("gifsicle -O3 --lossy=80 -o \"$cache_file\" \"$temp_file\" --no-conserve-memory");
        unlink($temp_file);
        $imagick->destroy();

    } else {
        $imagick->optimizeImageLayers();
        $imagick->quantizeImage(16, Imagick::COLORSPACE_RGB, 0, false, false);
        $imagick->setImageCompressionQuality(60);
        $imagick->writeImages($cache_file, true);
    }



// Serve o GIF gerado se existir
if (file_exists($cache_file)) {
    echo file_get_contents($cache_file);
    exit;
} else {
    error_log("Erro ao gerar o GIF de contagem regressiva. URL: " . $_SERVER['REQUEST_URI']);
}

exit;