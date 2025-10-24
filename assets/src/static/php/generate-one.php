<?php
// Uso: php generate-one.php [segundos]
if ($argc < 2) {
    die("Uso: php generate-one.php [segundos]\n");
}

set_time_limit(0);
ini_set('memory_limit', '768M');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/error.log');

// --- Configurações ---
$seconds_to_generate = (int) $argv[1];
$max_time_in_seconds = 1200;
$cache_dir = __DIR__ . '/cache';
$width = 600;
$height = 200;
$delay = 100;

// --- Lógica de Tempo & Loop ---
$is_expired = ($seconds_to_generate <= 0);
$is_longer_than_max = ($seconds_to_generate > $max_time_in_seconds);
$loop_behavior = $is_expired ? 0 : 1;

// --- Lógica de Cache (CORRIGIDO) ---
$suffix = '';
if ($is_longer_than_max) {
    $suffix = '_plus';
} elseif ($is_expired) {
    $suffix = '_expired';
}
$file_time = $is_expired ? 0 : $seconds_to_generate;
$cache_file = "{$cache_dir}/countdown_{$file_time}{$suffix}.gif";

if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

if (file_exists($cache_file)) {
    echo "Arquivo de cache já existe: " . basename($cache_file) . "\n";
    exit;
}

// --- Geração do GIF ---
$imagick = new Imagick();
$imagick->setFormat('gif');

$font_bold = __DIR__ . '/../fonts/AtkinsonHyperlegible-Bold.ttf';
$font_normal = __DIR__ . '/../fonts/AtkinsonHyperlegible-Regular.ttf';
$font_mono = __DIR__ . '/../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf';

// --- Funções para desenhar frames ---
function create_countdown_frame($time, $config) {
    extract($config);
    $frame = new Imagick();
    $frame->newImage($width, $height, new ImagickPixel('white'));
    $frame->setImageFormat('gif');
    $frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $frame->setImageIterations($loop_behavior);
    $draw = new ImagickDraw();
    $text = gmdate('H:i:s', $time);
    $draw->setFont($font_bold);
    $draw->setFontSize(20);
    $draw->annotation(80, 150, "HORAS");
    $draw->annotation(255, 150, "MINUTOS");
    $draw->annotation(440, 150, "SEGUNDOS");
    $draw->setFillColor('black');
    $draw->setFont($font_mono);
    $draw->setFontSize(100);
    $draw->setFontWeight(300);
    $draw->annotation(50, 120, $text);
    $frame->drawImage($draw);
    $frame->setImageDelay(100);
    return $frame;
}

function create_text_frame($text, $config) {
    extract($config);
    $frame = new Imagick();
    $frame->newImage($width, $height, new ImagickPixel('white'));
    $frame->setImageFormat('gif');
    $frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $frame->setImageIterations($loop_behavior);
    $draw = new ImagickDraw();
    $draw->setFont($font_bold);
    $draw->setFontSize(60);
    $metrics = $imagick->queryFontMetrics($draw, $text);
    $x = ($width - $metrics['textWidth']) / 2;
    $y = ($height + $metrics['textHeight']) / 2 - 20;
    $draw->annotation($x, $y, $text);
    $frame->drawImage($draw);
    $frame->setImageDelay(100);
    return $frame;
}

$draw_config = compact('width', 'height', 'font_bold', 'font_mono', 'imagick', 'loop_behavior');

echo "Gerando: " . basename($cache_file) . "\n";

// --- Lógica Principal de Geração ---

if ($is_longer_than_max) {
    // CASO: Cronômetro Longo (> 20 min)
    $start_time = $seconds_to_generate;
    $end_time = $start_time - $max_time_in_seconds + 1;
    for ($i = $start_time; $i >= $end_time; $i--) {
        $imagick->addImage(create_countdown_frame($i, $draw_config));
    }

    $last_countdown_frame = create_countdown_frame($end_time - 1, $draw_config); // CORRIGIDO

    $update_text_frame = new Imagick();
    $update_text_frame->newImage($width, $height, new ImagickPixel('white'));
    $update_text_frame->setImageFormat('gif');
    $update_text_frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $update_text_frame->setImageIterations($loop_behavior);
    $draw = new ImagickDraw();
    $draw->setFont($font_bold);
    $draw->setFillColor('black');
    $draw->setFontSize(28);
    $line1 = "O cronômetro precisa ser atualizado.";
    $metrics1 = $imagick->queryFontMetrics($draw, $line1);
    $x1 = ($width - $metrics1['textWidth']) / 2;
    $draw->annotation($x1, 90, $line1);
    $draw->setFont($font_normal);
    $draw->setFontSize(20);
    $line2 = "Abra essa mensagem novamente";
    $metrics2 = $imagick->queryFontMetrics($draw, $line2);
    $x2 = ($width - $metrics2['textWidth']) / 2;
    $draw->annotation($x2, 140, $line2);
    $line3 = "para continuar acompanhando a contagem.";
    $metrics3 = $imagick->queryFontMetrics($draw, $line3);
    $x3 = ($width - $metrics3['textWidth']) / 2;
    $draw->annotation($x3, 165, $line3);
    $update_text_frame->drawImage($draw);
    $update_text_frame->setImageDelay($delay);

    // Loop number lower than countdown frames to create blinking effect and to account for the last countdown frame we added manually

    for ($k = 0; $k < 10; $k++) {
        $imagick->addImage($k % 2 == 0 ? clone $last_countdown_frame : clone $update_text_frame);
    }

} else { // CASO: Normal ou Expirado
    if (!$is_expired) {
        $generation_time = $seconds_to_generate;
        for ($i = $generation_time; $i >= 1; $i--) {
            $imagick->addImage(create_countdown_frame($i, $draw_config));
        }
    }
    $zero_frame = create_countdown_frame(0, $draw_config);
    $expired_text_frame = create_text_frame('Expirado', $draw_config);
    for ($k = 0; $k < 11; $k++) {
        $imagick->addImage($k % 2 == 0 ? clone $zero_frame : clone $expired_text_frame);
    }
}

// Define o comportamento de loop FINAL
if ($is_expired) {
    $imagick->setImageIterations(0);
} else {
    $imagick->setImageIterations(1);
}

// --- Otimização e Salvamento ---
$use_gifsicle = function_exists('shell_exec') && !empty(shell_exec('which gifsicle'));
if ($use_gifsicle) {
    $temp_file = $cache_file . '.tmp';
    $imagick->writeImages($temp_file, true);
    shell_exec("gifsicle -O3 --lossy=80 -o \"$cache_file\" \"$temp_file\" --no-conserve-memory");
    unlink($temp_file);
    //$imagick->writeImages($cache_file, true);
} else {
    $imagick->optimizeImageLayers();
    $imagick->quantizeImage(16, Imagick::COLORSPACE_RGB, 0, false, false);
    $imagick->setImageCompressionQuality(60);
    $imagick->writeImages($cache_file, true);
}

$imagick->destroy();
echo "Processo concluído.\n";