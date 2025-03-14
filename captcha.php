<?php
session_start();

// Mostrar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: image/png");

// Crear la imagen
$width = 150;
$height = 50;
$image = imagecreatetruecolor($width, $height);

// Validar si la imagen se creó correctamente
if (!$image) {
    die("Error: No se pudo crear la imagen");
}

// Generar un tono base aleatorio (RGB)
$baseRed = rand(50, 200);   // Valores medios para evitar tonos extremos
$baseGreen = rand(50, 200);
$baseBlue = rand(50, 200);

// Generar color de fondo (un poco más claro)
$bgColor = imagecolorallocate($image, $baseRed + 30, $baseGreen + 30, $baseBlue + 30);

// Generar color de texto (un poco más oscuro)
$textColor = imagecolorallocate($image, $baseRed - 30, $baseGreen - 30, $baseBlue - 30);

// Rellenar el fondo
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// Generar código aleatorio de entre 4 y 6 caracteres (números y letras)
$caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
$longitud = rand(4, 6);  // Longitud aleatoria entre 4 y 6
$captcha_code = '';
for ($i = 0; $i < $longitud; $i++) {
    $captcha_code .= $caracteres[rand(0, strlen($caracteres) - 1)];
}

// Guardar el código CAPTCHA en la sesión
$_SESSION['captcha_code'] = $captcha_code;

// Escribir el texto en la imagen
imagestring($image, 5, 50, 15, $captcha_code, $textColor);

// Enviar la imagen
imagepng($image);
imagedestroy($image);
?>
