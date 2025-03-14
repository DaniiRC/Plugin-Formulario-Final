<?php
// Salir si WordPress no lo está llamando directamente
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Cargar la base de datos global de WordPress
global $wpdb;

// Nombre de la tabla (considerando el prefijo dinámico de WordPress)
$table_name = $wpdb->prefix . 'fcp_mensajes';

// Eliminar la tabla
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Opcional: Eliminar opciones del plugin si existen
delete_option('nombre_de_tu_opcion');
delete_site_option('nombre_de_tu_opcion');