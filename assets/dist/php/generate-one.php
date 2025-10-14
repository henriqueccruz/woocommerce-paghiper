<?php
// Uso: php generate-one.php [segundos]
if ($argc < 2) {
    die("Uso: php generate-one.php [segundos]\n");
}

set_time_limit(0);
ini_set('memory_limit', '768M');

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
$generation_time = $is_expired ? 0 : min($seconds_to_generate, $max_time_in_seconds);
$loop_behavior = $is_expired ? 0 : 1; // 0 para loop, 1 para tocar uma vez

// --- Lógica de Cache ---
$suffix = '';
if ($is_longer_than_max) {
    $suffix = '_plus';
} elseif ($is_expired) {
    $suffix = '_expired';
}
// O nome do arquivo para o caso expirado é sempre countdown_0_expired.gif
$file_time = $is_expired ? 0 : $generation_time;
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
$font_mono = __DIR__ . '/../fonts/AtkinsonHyperlegibleMono-VariableFont_wght.ttf';

// --- Funções para desenhar frames ---
function create_countdown_frame($time, $config) {
    extract($config);
    $frame = new Imagick();
    $frame->newImage($width, $height, new ImagickPixel('white'));
    $frame->setImageFormat('gif');
    $frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $frame->setImageIterations($loop_behavior); // Definido a nível de frame
    $draw = new ImagickDraw();
    $text = gmdate('H:i:s', $time);
    $draw->setFont($font_bold);
    $draw->setFontSize(20);
    $draw->setFontWeight(700);
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
    $frame->setImageIterations($loop_behavior); // Definido a nível de frame
    $draw = new ImagickDraw();
    $draw->setFont($font_bold);
    $draw->setFontSize(60);
    $draw->setFontWeight(700);
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

// 1. Adiciona frames de contagem regressiva (se aplicável)
    // CASO 2: Cronômetro Longo (> 20 min)
    $start_time = $seconds_to_generate;
    $end_time = $start_time - $max_time_in_seconds + 1;
    for ($i = $start_time; $i >= $end_time; $i--) {
        $imagick->addImage(create_countdown_frame($i, $draw_config));
    }
// 2. Adiciona frames piscantes no final
$zero_frame = create_countdown_frame(0, $draw_config);

if ($is_longer_than_max) {
    $update_text_frame = new Imagick();
    $update_text_frame->newImage($width, $height, new ImagickPixel('white'));
    $update_text_frame->setImageFormat('gif');
    $update_text_frame->setImageDispose(Imagick::DISPOSE_BACKGROUND);
    $update_text_frame->setImageIterations($loop_behavior); // Definido a nível de frame
    $draw = new ImagickDraw();
    $draw->setFont($font_bold);
    $draw->setFillColor('black');
    $draw->setFontSize(28);
    $line1 = "O cronômetro precisa ser atualizado.";
    $metrics1 = $imagick->queryFontMetrics($draw, $line1);
    $x1 = ($width - $metrics1['textWidth']) / 2;
    $draw->annotation($x1, 90, $line1);
    $draw->setFontSize(18);
    $draw->setFontWeight(300);
    $line2 = "Abra essa mensagem novamente para continuar vendo o cronômetro";
    $metrics2 = $imagick->queryFontMetrics($draw, $line2);
    $x2 = ($width - $metrics2['textWidth']) / 2;
    $draw->annotation($x2, 140, $line2);
    $update_text_frame->drawImage($draw);
    $update_text_frame->setImageDelay($delay);

    for ($k = 0; $k < 10; $k++) {
        $imagick->addImage($k % 2 == 0 ? clone $zero_frame : clone $update_text_frame);
    }
} else {
    $expired_text_frame = create_text_frame('Expirado', $draw_config);
    for ($k = 0; $k < 10; $k++) {
        $imagick->addImage($k % 2 == 0 ? clone $zero_frame : clone $expired_text_frame);
    }
}

// --- Otimização e Salvamento ---
$use_gifsicle = function_exists('shell_exec') && !empty(shell_exec('which gifsicle'));
if ($use_gifsicle) {
    $temp_file = $cache_file . '.tmp';
    $imagick->writeImages($temp_file, true);
    shell_exec("gifsicle -O3 --lossy=80 -o \"$cache_file\" \"$temp_file\" --no-conserve-memory");
    unlink($temp_file);
} else {
    $imagick->optimizeImageLayers();
    $imagick->quantizeImage(16, Imagick::COLORSPACE_RGB, 0, false, false);
    $imagick->setImageCompressionQuality(60);
    $imagick->writeImages($cache_file, true);
}

$imagick->destroy();
echo "Processo concluído.\n";
