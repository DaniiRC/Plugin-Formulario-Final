<?php
/*
Plugin Name: Formulario
Plugin URI: https://formulario
Description: Plugin de formulario de contacto con gestión de mensajes en el panel de administración.
Version: 2.3
Author: Danii y Yerpes
License: GPL2
*/
// Hook para manejar el envío del formulario en el hook 'init'
add_action('init', 'procesar_formulario_contacto');


// Función que procesa el formulario de contacto al enviar datos por POST
function procesar_formulario_contacto()
{
    // Se comprueba que la petición sea de tipo POST y que se haya enviado el botón 'submit'
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
        session_start(); // Inicia la sesión para gestionar variables de sesión


        // Verificar nonce para evitar ataques CSRF
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'formulario_contacto_nonce')) {
            wp_die('Error de seguridad.');
        }


        // Verificar si el CAPTCHA es correcto
        if (!isset($_POST['captcha']) || $_POST['captcha'] !== $_SESSION['captcha_code']) {
            $_SESSION['form_data'] = $_POST; // Guardar datos ingresados en la sesión para reusarlos
            $_SESSION['error_captcha'] = "El código CAPTCHA es incorrecto. Inténtalo de nuevo.";
            return;
        }


        // Asegurar que WordPress ha cargado sus funciones necesarias (como wp_safe_redirect)
        if (!function_exists('wp_safe_redirect')) {
            require_once(ABSPATH . 'wp-includes/pluggable.php');
        }


        // Acceso global a la base de datos de WordPress
        global $wpdb;
        // Definir el nombre de la tabla donde se almacenarán los mensajes
        $tabla = $wpdb->prefix . 'fcp_mensajes';


        // Sanitizar los datos del formulario para evitar inyecciones y errores
        $titulo = sanitize_text_field($_POST['titulo']);
        $mensaje = sanitize_textarea_field($_POST['mensaje']);
        $email = sanitize_email($_POST['email']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $apellido = sanitize_text_field($_POST['apellido']);

        // Insertar los datos del formulario en la base de datos
        $wpdb->insert(
            $tabla,
            [
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'email' => $email,
                'nombre' => $nombre,
                'apellidos' => $apellido

            ],
            ['%s', '%s', '%s', '%s', '%s']
        );


        // Guardar mensaje de éxito en la sesión y redirigir a la misma URL para limpiar POST
        $_SESSION['success_message'] = "Formulario enviado correctamente.";
        wp_safe_redirect($_SERVER['REQUEST_URI']);
        exit;
    }
}




// Crear un shortcode para mostrar el formulario de contacto en páginas o entradas
function formulario_contacto_shortcode()
{
    session_start(); // Iniciar sesión para acceder a variables guardadas
    // Recuperar datos de la sesión (en caso de error o éxito previo)
    $form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [];
    $error_captcha = isset($_SESSION['error_captcha']) ? $_SESSION['error_captcha'] : '';
    $success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';


    // Limpiar las variables de sesión para evitar mostrar mensajes antiguos
    unset($_SESSION['form_data'], $_SESSION['error_captcha'], $_SESSION['success_message']);


    // Iniciar el buffer de salida para capturar el HTML del formulario
    ob_start(); ?>
    <?php if ($error_captcha): ?>
        <!-- Mostrar mensaje de error si el CAPTCHA es incorrecto -->
        <p style='color:red;'><?php echo $error_captcha; ?></p>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <!-- Mostrar mensaje de éxito si el formulario se envió correctamente -->
        <p style='color:green;'><?php echo $success_message; ?></p>
    <?php endif; ?>
    <form class="contact-form" method="POST" action="">
        <?php wp_nonce_field('formulario_contacto_nonce'); // Campo de seguridad
        ?>
        <label class="contact-label" for="titulo">Titulo:</label>
        <input class="contact-input" type="text" name="titulo" value="<?php echo esc_attr($form_data['titulo'] ?? ''); ?>" required>

        <label class="contact-label" for="mensaje">Mensaje:</label>
        <textarea class="contact-textarea" name="mensaje" required><?php echo esc_textarea($form_data['mensaje'] ?? ''); ?></textarea>

        <label class="contact-label" for="nombre">Nombre:</label>
        <input class="contact-input" type="text" name="nombre" value="<?php echo esc_attr($form_data['nombre'] ?? ''); ?>" required>

        <label class="contact-label" for="apellido">Apellido:</label>
        <input class="contact-input" type="text" name="apellido" value="<?php echo esc_attr($form_data['apellido'] ?? ''); ?>" required>

        <label class="contact-label" for="email">Email:</label>
        <input class="contact-input" type="email" name="email" value="<?php echo esc_attr($form_data['email'] ?? ''); ?>" required>

        <label class="contact-label" for="captcha">Introduce el código CAPTCHA:</label>
        <!-- Mostrar la imagen CAPTCHA generada dinámicamente -->
        <img class="contact-captcha" src="<?php echo plugins_url('captcha.php', __FILE__); ?>" alt="CAPTCHA">
        <input class="contact-input" type="text" name="captcha" required>

        <button class="contact-button" type="submit" name="submit">Enviar</button>
    </form>
<?php
    // Devolver el contenido capturado y finalizar el buffer
    return ob_get_clean();
}
add_shortcode('formulario_contacto', 'formulario_contacto_shortcode');


// Iniciar sesión y buffer de salida globalmente en el hook 'init'
add_action('init', function () {
    if (!session_id()) {
        session_start();
    }
    ob_start(); // Inicia un buffer global para evitar problemas de salida
});


// CREAR LA TABLA EN LA ACTIVACIÓN DEL PLUGIN
register_activation_hook(__FILE__, 'fcp_crear_tabla');
function fcp_crear_tabla()
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';
    $charset_collate = $wpdb->get_charset_collate();


    // SQL para crear la tabla, si no existe, con las columnas necesarias
    $sql = "CREATE TABLE IF NOT EXISTS $tabla (
        id INT NOT NULL AUTO_INCREMENT,
        titulo VARCHAR(50) NOT NULL,
        mensaje TEXT NOT NULL,
        email VARCHAR(100) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_respondido TIMESTAMP NULL DEFAULT NULL,
        respuesta TEXT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";


    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql); // Ejecuta la creación/actualización de la tabla
}


// PROCESAR FORMULARIO Y GUARDAR MENSAJE (Función alternativa para otro formulario)
add_action('init', 'fcp_guardar_mensaje');
function fcp_guardar_mensaje()
{
    if (isset($_POST['fcp_enviar_mensaje'])) { // Comprueba si se envió este formulario específico
        global $wpdb;
        $tabla = $wpdb->prefix . 'fcp_mensajes';


        // Sanitizar datos del formulario
        $titulo = sanitize_text_field($_POST['titulo']);
        $mensaje = sanitize_textarea_field($_POST['mensaje']);
        $email = sanitize_email($_POST['email']);
        $nombre = sanitize_text_field($_POST['nombre']);
        $apellido = sanitize_text_field($_POST['apellido']);


        // Insertar el mensaje en la base de datos
        $wpdb->insert(
            $tabla,
            [
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'email' => $email,
                'nombre' => $nombre,
                'apellidos' => $apellido

            ],
            ['%s', '%s', '%s', '%s', '%s']
        );


        // Redirigir a la página anterior con un parámetro que indica que se envió el mensaje
        wp_redirect(add_query_arg('mensaje_enviado', '1', wp_get_referer()));
        exit;
    }
}


// AGREGAR MENÚ EN EL ADMINISTRADOR de WordPress
add_action('admin_menu', 'fcp_menu_administrador');
function fcp_menu_administrador()
{
    // Página principal del menú de mensajes
    add_menu_page('Mensajes', 'Mensajes', 'manage_options', 'fcp_mensajes', 'fcp_mensajes_recibidos', 'dashicons-email', 20);
    // Submenú para mensajes recibidos
    add_submenu_page('fcp_mensajes', 'Mensajes Recibidos', 'Recibidos', 'manage_options', 'fcp_mensajes', 'fcp_mensajes_recibidos');
    // Submenú para mensajes respondidos
    add_submenu_page('fcp_mensajes', 'Mensajes Respondidos', 'Respondidos', 'manage_options', 'fcp_mensajes_respondidos', 'fcp_mensajes_respondidos');
    add_submenu_page(
        'fcp_mensajes',                 // Slug del menú padre (por ejemplo, "Mensajes")
        'Exportar CSV',                 // Título de la página (aunque no se mostrará, ya que se descarga el archivo)
        'Exportar CSV',                 // Título del submenú
        'manage_options',               // Permisos necesarios
        'exportar-csv-respondidos',     // Slug del submenú
        'fcp_exportar_mensajes_csv'     // Callback que ejecuta la exportación
    );
    // Página oculta para responder un mensaje, accesible solo con el URL adecuado
    add_submenu_page(null, 'Responder Mensaje', 'Responder', 'manage_options', 'fcp_responder_mensaje', 'fcp_responder_mensaje');
}


/*-------------------------------------------------
   Funcionalidad para Importar CSV
-------------------------------------------------*/
// Función para procesar e importar el CSV
function fcp_importar_csv()
{
    // Si se envió el formulario de importación
    if (isset($_POST['fcp_importar_csv_submit'])) {


        // Verificar el nonce para seguridad
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'fcp_importar_csv_nonce')) {
            wp_die('Error de seguridad en la importación.');
        }


        // Verificar que se haya subido un archivo sin errores
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            echo "<div class='error'><p>No se ha subido ningún archivo o se produjo un error.</p></div>";
        } else {
            $file_tmp = $_FILES['csv_file']['tmp_name'];
            $file_ext = pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION);
            if (strtolower($file_ext) !== 'csv') {
                echo "<div class='error'><p>Por favor, sube un archivo CSV válido.</p></div>";
            } else {
                global $wpdb;
                $tabla = $wpdb->prefix . 'fcp_mensajes';
                $inserted = 0;
                if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                    // Leer la primera línea (cabecera) y descartarla
                    $header = fgetcsv($handle, 1000, ",");
                    /*
                       Se asume que el CSV exportado tiene las columnas en el siguiente orden:
                       id, nombre, apellidos, email, mensaje, fecha, fecha_respondido, respuesta
                       Ignoramos el campo "id" ya que la tabla lo maneja como AUTO_INCREMENT.
                    */
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        // Asegurarse de que existan al menos 8 columnas
                        if (count($data) < 8) continue;
                        $titulo          = sanitize_text_field($data[1]);
                        $mensaje         = sanitize_textarea_field($data[2]);
                        $email           = sanitize_email($data[3]);
                        $nombre          = sanitize_text_field($data[4]);
                        $apellidos       = sanitize_text_field($data[5]);
                        $fecha           = sanitize_text_field($data[6]); // opcional
                        $fecha_respondido = sanitize_text_field($data[7]); // opcional
                        $respuesta       = sanitize_textarea_field($data[8]);


                        $insert_data = array(
                            'titulo'    => $titulo,
                            'mensaje'   => $mensaje,
                            'email'     => $email,
                            'nombre'    => $nombre,
                            'apellidos' => $apellidos,
                            'fecha'     => ($fecha !== '' ? $fecha : current_time('mysql')),
                            'fecha_respondido' => ($fecha_respondido !== '' ? $fecha_respondido : null),
                            'respuesta' => ($respuesta !== '' ? $respuesta : null),
                        );
                        // Los formatos se especifican como cadenas
                        $insert_format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
                        $result = $wpdb->insert($tabla, $insert_data, $insert_format);
                        if ($result !== false) {
                            $inserted++;
                        }
                    }
                    fclose($handle);
                }
                echo "<div class='updated'><p>Importación completada. Se insertaron {$inserted} mensajes.</p></div>";
            }
        }
    }
?>
    <div class="wrap">
        <h1>Importar CSV</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('fcp_importar_csv_nonce'); ?>
            <p>
                <label for="csv_file">Selecciona un archivo CSV:</label><br>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </p>
            <p>
                <input type="submit" name="fcp_importar_csv_submit" class="button button-primary" value="Importar CSV">
            </p>
        </form>
    </div>
<?php
}


// Registrar el submenu "Importar CSV" en el menú "Mensajes"
add_action('admin_menu', 'fcp_registrar_importar_csv_menu');
function fcp_registrar_importar_csv_menu()
{
    add_submenu_page(
        'fcp_mensajes',           // Parent slug (menú "Mensajes")
        'Importar CSV',           // Título de la página
        'Importar CSV',           // Título del submenu
        'manage_options',         // Capacidad necesaria
        'importar-csv-mensajes',  // Slug del menú
        'fcp_importar_csv'        // Función callback
    );
}


// **MOSTRAR MENSAJES RECIBIDOS**
function fcp_mensajes_recibidos()
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';


    // Obtener los valores de los filtros (si existen)
    $orden = isset($_GET['orden']) ? $_GET['orden'] : 'ASC';  // 'ASC' por defecto
    $campo = isset($_GET['campo']) ? $_GET['campo'] : 'fecha';  // 'fecha' por defecto
    $filtro_valor = isset($_GET['filtro_valor']) ? $_GET['filtro_valor'] : '';  // Valor del filtro (Nombre o Email)


    // Iniciar el buffer de salida
    ob_start();
?>
    <form action="<?php echo admin_url('admin-post.php'); ?>" method="get" class="fcp-filtro-form">
        <h1 class="titulo">Mensajes Recibidos</h1>
        <input type="hidden" name="page" value="fcp_mensajes" />


        <label for="campo" class="estilo-filtro">Filtrar por:</label>
        <select name="campo" id="campo" onchange="mostrarFiltro()">
            <option value="nombre" <?php selected($campo, 'nombre'); ?>>Nombre</option>
            <option value="email" <?php selected($campo, 'email'); ?>>Email</option>
            <option value="fecha" <?php selected($campo, 'fecha'); ?>>Fecha Recibido</option>
        </select>


        <div id="filtro_texto" style="display: <?php echo ($campo === 'nombre' || $campo === 'email') ? 'block' : 'none'; ?>;">
            <label for="filtro_valor">Valor a Filtrar:</label>
            <input type="text" name="filtro_valor" id="filtro_valor" value="<?php echo esc_attr($filtro_valor); ?>" />
        </div>


        <label for="orden">Ordenar:</label>
        <select name="orden" id="orden">
            <option value="ASC" <?php selected($orden, 'ASC'); ?>>Ascendente</option>
            <option value="DESC" <?php selected($orden, 'DESC'); ?>>Descendente</option>
        </select>


        <input type="submit" value="Aplicar Filtro" class="button">
    </form>


    <?php
    // Recuperar los mensajes filtrados solo para los mensajes recibidos (respuesta IS NULL)
    $sql = "SELECT * FROM $tabla WHERE respuesta IS NULL";


    // Aplicar el filtro si se selecciona "nombre" o "email"
    if ($campo === 'nombre' && !empty($filtro_valor)) {
        $sql .= $wpdb->prepare(" AND nombre LIKE %s", '%' . $wpdb->esc_like($filtro_valor) . '%');
    } elseif ($campo === 'email' && !empty($filtro_valor)) {
        $sql .= $wpdb->prepare(" AND email LIKE %s", '%' . $wpdb->esc_like($filtro_valor) . '%');
    }


    // Agregar el orden a la consulta
    $sql .= " ORDER BY $campo $orden";


    // Ejecutar la consulta
    $mensajes = $wpdb->get_results($sql);
    ?>


    <div class="table-container">
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Mensaje</th>
                    <th>Email</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Fecha Recibido</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mensajes as $mensaje) : ?>
                    <tr>
                        <td><?php echo esc_html($mensaje->titulo); ?></td>
                        <td><?php echo esc_html($mensaje->mensaje); ?></td>
                        <td><?php echo esc_html($mensaje->email); ?></td>
                        <td><?php echo esc_html($mensaje->nombre); ?></td>
                        <td><?php echo esc_html($mensaje->apellidos); ?></td>
                        <td><?php echo esc_html($mensaje->fecha); ?></td>
                        <td>
                            <a href="admin.php?page=ver_mensaje&mensaje_id=<?php echo $mensaje->id; ?>" class="button button-primary">Ver Detalles</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <script type="text/javascript">
        function mostrarFiltro() {
            var campoSeleccionado = document.getElementById('campo').value;
            var filtroTexto = document.getElementById('filtro_texto');
            if (campoSeleccionado === 'nombre' || campoSeleccionado === 'email') {
                filtroTexto.style.display = 'block';
            } else {
                filtroTexto.style.display = 'none';
            }
        }
        window.onload = mostrarFiltro;
    </script>


<?php
    // Capturar el contenido del buffer y limpiarlo
    $output = ob_get_clean();
    echo $output;
}

function fcp_mostrar_mensaje_admin() {
    if (!isset($_GET['mensaje_id'])) {
        echo "<div class='error'><p>No se ha encontrado el mensaje.</p></div>";
        return;
    }

    global $wpdb;
    $id_mensaje = intval($_GET['mensaje_id']);
    $tabla = $wpdb->prefix . 'fcp_mensajes';
    $mensaje = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $id_mensaje));

    if (!$mensaje) {
        echo "<div class='error'><p>El mensaje no existe.</p></div>";
        return;
    }

    $mensaje_enviado = false; // Flag para saber si se envió la respuesta correctamente

    // Procesar respuesta si se envía
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respuesta'])) {
        check_admin_referer('fcp_responder_mensaje', 'fcp_nonce');
    
        $respuesta = sanitize_textarea_field($_POST['respuesta']);
    
        $resultado = $wpdb->update(
            $tabla,
            [
                'respuesta' => $respuesta,
                'fecha_respondido' => current_time('mysql')
            ],
            ['id' => $id_mensaje],
            ['%s', '%s'],
            ['%d']
        );
    
        if (false === $resultado) {
            $error = $wpdb->last_error;
            echo '<div class="notice notice-error"><p>❌ Error al guardar la respuesta: ' . esc_html($error) . '</p></div>';
        } else {
            // Si la respuesta se guardó correctamente, mostrar mensaje de éxito
            $mensaje_enviado = true;
            $mensaje->respuesta = $respuesta; // Guardar la respuesta para mostrarla si es necesario
        }
    }

    ?>
    <div class="wrap">
        <div class="mensaje-container">
            <!-- 📝 CUADRO IZQUIERDO: Mensaje y Fecha -->
            <div class="mensaje-contenido">
                <h2><?php echo esc_html($mensaje->titulo); ?></h2>
                <p><?php echo nl2br(esc_html($mensaje->mensaje)); ?></p>
                <p class="mensaje-fecha"><strong>Recibido el:</strong> <?php echo date('d/m/Y H:i', strtotime($mensaje->fecha)); ?></p>

                <?php if (empty($mensaje->respuesta)) : ?>
                    <!-- Botón para mostrar el formulario si no hay respuesta -->
                    <button id="mostrar-formulario" class="button">Responder</button>
                <?php elseif ($mensaje_enviado) : ?>
                    <!-- Mensaje de éxito si se ha enviado la respuesta correctamente -->
                    <div class="notice notice-success"><p>✅ Respuesta enviada correctamente.</p></div>
                <?php endif; ?>

                <!-- 📨 Formulario oculto -->
                <div id="formulario-respuesta" style="display:none; margin-top:20px;">
                    <form method="post">
                        <?php wp_nonce_field('fcp_responder_mensaje', 'fcp_nonce'); ?>
                        <textarea name="respuesta" rows="6" style="width:100%;" placeholder="Escribe tu respuesta aquí..." required></textarea>
                        <br><br>
                        <input type="submit" class="button button-primary" value="Enviar Respuesta">
                    </form>
                </div>
            </div>

            <!-- 📌 CUADRO DERECHO: Datos del remitente -->
            <div class="mensaje-remitente">
                <h3>Datos del remitente</h3>
                <p><strong>Nombre:</strong> <?php echo esc_html($mensaje->nombre . ' ' . $mensaje->apellidos); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($mensaje->email); ?></p>
            </div>
        </div>

        <a href="<?php echo admin_url('admin.php?page=fcp_mensajes'); ?>" class="button" style="margin-top:20px; display:inline-block;">Volver</a>
    </div>

    <style>
        
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var boton = document.getElementById('mostrar-formulario');
            if (boton) {
                boton.addEventListener('click', function() {
                    document.getElementById('formulario-respuesta').style.display = 'block';
                    this.style.display = 'none';
                });
            }
        });
    </script>

    <?php
} // Aquí cierra la función


// Registrar la página en el menú de administración
function fcp_agregar_pagina_mensaje() {
    add_submenu_page(
        null, // No aparece en el menú
        'Ver Mensaje', 
        'Ver Mensaje', 
        'manage_options', 
        'ver_mensaje', 
        'fcp_mostrar_mensaje_admin'
    );
}
add_action('admin_menu', 'fcp_agregar_pagina_mensaje');


// Registrar la página en el menú de administración
if (!function_exists('fcp_agregar_pagina_mensaje')) {
    function fcp_agregar_pagina_mensaje() {
        add_submenu_page(
            null, // No aparece en el menú
            'Ver Mensaje', 
            'Ver Mensaje', 
            'manage_options', 
            'ver_mensaje', 
            'fcp_mostrar_mensaje_admin'
        );
    }
    add_action('admin_menu', 'fcp_agregar_pagina_mensaje');
}

function fcp_exportar_mensajes_csv()
{
    if (headers_sent($file, $line)) {
        wp_die("Headers already sent in $file on line $line");
    }


    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';
    $mensajes = $wpdb->get_results("SELECT * FROM $tabla", ARRAY_A);


    if (!$mensajes) {
        wp_die('No hay mensajes para exportar.');
    }


    // Configura las cabeceras para la descarga del archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=mensajes.csv');


    if (ob_get_length()) {
        ob_clean();
    }
    flush();


    // Abre la salida en modo escritura
    $output = fopen('php://output', 'w');


    // Escribe la primera fila con los nombres de las columnas
    fputcsv($output, array_keys($mensajes[0]));


    // Escribe los datos de la tabla en el CSV
    foreach ($mensajes as $mensaje) {
        fputcsv($output, $mensaje);
    }


    fclose($output);
    exit; // Termina la ejecución después de la descarga
}


// Hook para que la función se ejecute en admin-post.php
add_action('admin_post_fcp_exportar_mensajes', 'fcp_exportar_mensajes_csv');
add_action('admin_post_nopriv_fcp_exportar_mensajes', 'fcp_exportar_mensajes_csv'); // Para usuarios no logueados


// **NUEVA FUNCIÓN PARA RESPONDER MENSAJES**
function fcp_responder_mensaje()
{
    ob_start();
    if (!current_user_can('manage_options')) {
        wp_die(__('Permisos insuficientes.'));
    }
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        wp_die(__('ID inválido.'));
    }
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';
    $mensaje_id = intval($_GET['id']);
    $mensaje = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $mensaje_id));
    if (!$mensaje) {
        wp_die(__('Mensaje no existe.'));
    }
    if (isset($_POST['fcp_responder'])) {
        $respuesta = sanitize_textarea_field($_POST['respuesta']);
        if (empty($respuesta)) {
            echo "<p style='color:red;'>Debe ingresar una respuesta.</p>";
        } else {
            $resultado = $wpdb->update(
                $tabla,
                [
                    'respuesta' => $respuesta,
                    'fecha_respondido' => current_time('mysql')
                ],
                ['id' => $mensaje_id],
                ['%s', '%s'],
                ['%d']
            );
            if (false === $resultado) {
                $error = $wpdb->last_error;
                error_log("Error al actualizar mensaje: $error");
                echo "<p style='color:red;'>Error al actualizar mensaje: $error</p>";
            } else {
                echo "<p style='color:green;'>Mensaje actualizado correctamente</p>";
            }
            ob_end_clean();
            wp_safe_redirect(admin_url('admin.php?page=fcp_mensajes_respondidos'));
            exit;
        }
    }
    // Muestra el formulario de respuesta
?>
    <div class="wrap fcp-responder">
        <h1>Responder Mensaje</h1>
        <p><strong>De:</strong> <?php echo esc_html($mensaje->nombre . ' ' . $mensaje->apellidos); ?></p>
        <p><strong>Correo:</strong> <?php echo esc_html($mensaje->email); ?></p>
        <p><strong>Mensaje:</strong><br><?php echo esc_textarea($mensaje->mensaje); ?></p>
        <form method="post">
            <label for="respuesta"><strong>Escribir respuesta:</strong></label><br><br>
            <textarea name="respuesta" rows="5" required></textarea><br><br>
            <?php wp_nonce_field('fcp_responder_mensaje', 'fcp_nonce'); ?>
            <input type="submit" name="fcp_responder" class="button button-primary" value="Enviar Respuesta">
        </form>
    </div>
<?php
    ob_end_flush();
}


// **MOSTRAR MENSAJES RESPONDIDOS**
function fcp_mensajes_respondidos()
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';


    // Obtener los valores de los filtros (si existen)
    $orden = isset($_GET['orden']) ? $_GET['orden'] : 'ASC';  // 'ASC' por defecto
    $campo = isset($_GET['campo']) ? $_GET['campo'] : 'fecha';  // 'fecha' por defecto
    $filtro_valor = isset($_GET['filtro_valor']) ? $_GET['filtro_valor'] : '';  // Valor del filtro (Nombre o Email)


    // Iniciar el buffer de salida
    ob_start();
?>
    <form method="get" class="fcp-filtro-form">
        <h1 class="titulo">Mensajes Respondidos</h1>
        <input type="hidden" name="page" value="fcp_mensajes_respondidos" /> <!-- Asegúrate de que la página sea correcta -->


        <label for="campo" class="estilo-filtro">Filtrar por:</label>
        <select name="campo" id="campo" onchange="mostrarFiltro()">
            <option value="nombre" <?php selected($campo, 'nombre'); ?>>Nombre</option>
            <option value="email" <?php selected($campo, 'email'); ?>>Email</option>
            <option value="fecha" <?php selected($campo, 'fecha'); ?>>Fecha Respondido</option>
        </select>


        <div id="filtro_texto" style="display: <?php echo ($campo === 'nombre' || $campo === 'email') ? 'block' : 'none'; ?>;">
            <label for="filtro_valor">Valor a Filtrar:</label>
            <input type="text" name="filtro_valor" id="filtro_valor" value="<?php echo esc_attr($filtro_valor); ?>" />
        </div>


        <label for="orden">Ordenar:</label>
        <select name="orden" id="orden">
            <option value="ASC" <?php selected($orden, 'ASC'); ?>>Ascendente</option>
            <option value="DESC" <?php selected($orden, 'DESC'); ?>>Descendente</option>
        </select>


        <input type="submit" value="Aplicar Filtro" class="button">
    </form>


    <?php
    // Recuperar los mensajes filtrados solo para los mensajes respondidos (respuesta IS NOT NULL)
    $sql = "SELECT * FROM $tabla WHERE respuesta IS NOT NULL";


    // Aplicar el filtro si se selecciona "nombre" o "email"
    if ($campo === 'nombre' && !empty($filtro_valor)) {
        $sql .= $wpdb->prepare(" AND nombre LIKE %s", '%' . $wpdb->esc_like($filtro_valor) . '%');
    } elseif ($campo === 'email' && !empty($filtro_valor)) {
        $sql .= $wpdb->prepare(" AND email LIKE %s", '%' . $wpdb->esc_like($filtro_valor) . '%');
    }


    // Agregar el orden a la consulta
    $sql .= " ORDER BY $campo $orden";


    // Ejecutar la consulta
    $mensajes = $wpdb->get_results($sql);
    ?>


    <div class="table-container">
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Titulo</th>
                    <th>Mensaje</th>
                    <th>Email</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Respuesta</th>
                    <th>Fecha Recibido</th>
                    <th>Fecha Respondido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mensajes as $mensaje) : ?>
                    <tr>
                        <td><?php echo esc_html($mensaje->titulo); ?></td>
                        <td><?php echo esc_html($mensaje->mensaje); ?></td>
                        <td><?php echo esc_html($mensaje->email); ?></td>
                        <td><?php echo esc_html($mensaje->nombre); ?></td>
                        <td><?php echo esc_html($mensaje->apellidos); ?></td>
                        <td><?php echo esc_html($mensaje->respuesta); ?></td>
                        <td><?php echo esc_html($mensaje->fecha); ?></td>
                        <td><?php echo esc_html($mensaje->fecha_respondido); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <script type="text/javascript">
        function mostrarFiltro() {
            var campoSeleccionado = document.getElementById('campo').value;
            var filtroTexto = document.getElementById('filtro_texto');
            if (campoSeleccionado === 'nombre' || campoSeleccionado === 'email') {
                filtroTexto.style.display = 'block';
            } else {
                filtroTexto.style.display = 'none';
            }
        }
        window.onload = mostrarFiltro;
    </script>


<?php
    // Capturar el contenido del buffer y limpiarlo
    $output = ob_get_clean();
    echo $output;
}


// Cargar estilos en el panel de administración (tanto para recibidos como respondidos)
function fcp_cargar_estilos_admin($hook)
{
    wp_enqueue_style('estilo-pestañas-admin', plugin_dir_url(__FILE__) . 'estilo-pestañas.css', [], '1.0.0', 'all');
    wp_enqueue_style('estilo-formulario-admin', plugin_dir_url(__FILE__) . 'estilo-formulario.css', [], '1.0.0', 'all');
}
add_action('admin_enqueue_scripts', 'fcp_cargar_estilos_admin');


// Cargar estilos en el frontend cuando se usa el shortcode
function fcp_cargar_estilos_frontend()
{
    wp_enqueue_style('estilo-pestañas-frontend', plugin_dir_url(__FILE__) . 'estilo-pestañas.css', [], '1.0.0', 'all');
    wp_enqueue_style('estilo-formulario-frontend', plugin_dir_url(__FILE__) . 'estilo-formulario.css', [], '1.0.0', 'all');
}
add_action('wp_enqueue_scripts', 'fcp_cargar_estilos_frontend');


function api_obtener_mensajes(WP_REST_Request $request)
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';


    // Obtener todos los mensajes ordenados por fecha de forma descendente
    $resultados = $wpdb->get_results("SELECT id, titulo, mensaje, email, nombre, apellidos, fecha, respuesta, fecha_respondido FROM $tabla ORDER BY fecha DESC");


    // Devolver la respuesta en formato JSON
    return rest_ensure_response($resultados);
}


add_action('rest_api_init', function () {
    register_rest_route('fcp/v1', '/mensajes/', array(
        'methods' => 'GET',
        'callback' => 'api_obtener_mensajes',
        'permission_callback' => '__return_true'
    ));
});


function api_obtener_mensajes_con_respuesta(WP_REST_Request $request)
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';


    // Obtener todos los mensajes con respuesta (donde el campo 'respuesta' no es NULL ni vacío)
    $resultados = $wpdb->get_results("
        SELECT id, titulo, mensaje, email, nombre, apellidos, fecha, fecha_respondido, respuesta
        FROM $tabla
        WHERE respuesta IS NOT NULL AND respuesta != ''
        ORDER BY fecha DESC
    ");


    // Devolver la respuesta en formato JSON
    return rest_ensure_response($resultados);
}


// Registrar la nueva ruta de la API
add_action('rest_api_init', function () {
    register_rest_route('fcp/v1', '/mensajes/respuestas/', array(
        'methods' => 'GET',
        'callback' => 'api_obtener_mensajes_con_respuesta',
        'permission_callback' => '__return_true'  // Deberías definir un control de acceso adecuado
    ));
});


function api_responder_mensaje(WP_REST_Request $request)
{
    global $wpdb;
    $tabla = $wpdb->prefix . 'fcp_mensajes';


    // Obtener el ID del mensaje y la respuesta desde los parámetros de la solicitud
    $id_mensaje = $request->get_param('id'); // ID del mensaje
    $respuesta = $request->get_param('respuesta'); // Respuesta que se va a enviar


    // Comprobar si el mensaje existe
    $mensaje = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabla WHERE id = %d", $id_mensaje));


    if (!$mensaje) {
        return new WP_REST_Response('Mensaje no encontrado', 404);
    }


    // Actualizar la respuesta del mensaje en la base de datos
    $wpdb->update(
        $tabla,
        array(
            'respuesta' => $respuesta, // Agregar la respuesta
            'fecha_respondido' => current_time('mysql'), // Fecha y hora de la respuesta
        ),
        array('id' => $id_mensaje),
        array('%s', '%s'),
        array('%d')
    );


    // Devolver una respuesta exitosa
    return new WP_REST_Response('Respuesta enviada correctamente', 200);
}


// Registrar la nueva ruta de la API para responder
add_action('rest_api_init', function () {
    register_rest_route('fcp/v1', '/mensajes/(?P<id>\d+)/responder/', array(
        'methods' => 'POST',
        'callback' => 'api_responder_mensaje',
        'permission_callback' => '__return_true', // Deberías controlar los permisos aquí
    ));
});


// Al final del archivo:
add_action('shutdown', function () {
    ob_end_flush();
}, 0);


// Evitar acceso directo al archivo
if (!defined('ABSPATH')) exit;


// Función para exportar los mensajes como CSV


if (!defined('ABSPATH')) exit;
