# Documentación del Plugin de WordPress

Este repositorio contiene un plugin de WordPress que implementa un formulario de contacto con validación CAPTCHA y un sistema de desinstalación seguro.

## Archivos incluidos

### 1. `uninstall.php`

Este archivo maneja la desinstalación del plugin, asegurando que los datos creados sean eliminados completamente de la base de datos para evitar acumulaciones innecesarias.

#### Estructura y funcionamiento

- **Verificación del contexto de ejecución:**

  - Se comprueba si el archivo se está ejecutando dentro del contexto de WordPress.
  - Se usa `defined('WP_UNINSTALL_PLUGIN')` para evitar accesos directos no deseados.

- **Eliminación de opciones y datos:**

  - Se eliminan las opciones almacenadas en la base de datos con `delete_option('nombre_opcion')`.
  - Si el plugin utiliza una tabla personalizada en la base de datos, se ejecuta una consulta SQL con `DROP TABLE IF EXISTS` para eliminarla.

- **Eliminación de metadatos asociados a los usuarios:**

  - Se recorren los metadatos creados por el plugin y se eliminan con `delete_metadata()`.

### 2. `captcha.php`

Este archivo implementa la generación y validación de un CAPTCHA para formularios, ayudando a prevenir envíos automáticos y ataques de spam.

#### Estructura y funcionamiento

- **Generación del CAPTCHA:**

  - Se generan caracteres aleatorios utilizando `rand()` o `mt_rand()`.
  - Se crea una imagen utilizando la librería GD (`imagecreate`, `imagestring`, `imagepng`).
  - Se almacenan los caracteres generados en una variable de sesión (`$_SESSION['captcha']`).

- **Presentación del CAPTCHA en el formulario:**

  - Se genera una etiqueta `<img src="captcha.php">` en el formulario para mostrar la imagen generada.

- **Validación del CAPTCHA:**

  - Se compara la entrada del usuario con el valor almacenado en `$_SESSION['captcha']`.
  - Si el valor no coincide, se muestra un mensaje de error y se bloquea el envío del formulario.

### 3. `formulario-contacto-plugin.php`

Este archivo es el núcleo del plugin y se encarga de la creación, gestión y procesamiento del formulario de contacto.

#### Estructura y funcionamiento

- **Definición del shortcode:**

  - Se usa `add_shortcode('nombre_shortcode', 'funcion_que_renderiza_formulario')` para permitir que el formulario se incruste en entradas y páginas.

- **Generación del formulario:**

  - Se crea un formulario HTML con campos de nombre, correo electrónico, mensaje y CAPTCHA.
  - Se utiliza `wp_nonce_field()` para incluir un token de seguridad y prevenir ataques CSRF.

- **Procesamiento del formulario:**

  - Se verifica si el formulario ha sido enviado con `$_POST`.
  - Se sanitizan y validan los datos utilizando `sanitize_text_field()` y `is_email()`.
  - Se valida el CAPTCHA para prevenir spam.

- **Envío del correo:**

  - Se utiliza `wp_mail()` para enviar la información ingresada en el formulario al administrador del sitio o destinatario especificado.
  - Se construye el cuerpo del mensaje con `wp_mail()` y se agregan encabezados personalizados si es necesario.

- **Mensaje de confirmación o error:**

  - Si el formulario se envía correctamente, se muestra un mensaje de éxito.
  - Si hay errores, se notifican al usuario con mensajes descriptivos.

## Instalación

1. Clona este repositorio en tu servidor:
   ```bash
   git clone https://github.com/DaniiRC/Plugin-Formulario-Final.git
   ```
2. Sube los archivos a tu directorio de plugins de WordPress (`/wp-content/plugins/`).
3. Activa el plugin desde el panel de administración de WordPress.
4. Usa el shortcode en una página o entrada para insertar el formulario.

## Desinstalación

Si deseas eliminar completamente el plugin y sus datos:

1. Desactívalo desde el panel de administración de WordPress.
2. Borra el plugin.
3. El script `uninstall.php` se ejecutará automáticamente eliminando los datos del plugin.

## Requisitos

- WordPress 5.0 o superior.
- PHP 7.4 o superior.
- Librería GD habilitada para la generación del CAPTCHA.

## Contribución

Si deseas contribuir, por favor abre un issue o envía un pull request.

## Licencia

Este proyecto está bajo la licencia MIT. Puedes usarlo y modificarlo libremente.
