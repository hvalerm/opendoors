ENVIO DE CORREOS POR SMTP

El sistema usa PHPMailer instalado localmente y SMTP autenticado. Gmail
entrega los mensajes: no se usa la API de Resend. El flujo de recuperacion
de contrasena conserva su respuesta generica para no revelar cuentas.

Instalacion:
- En XAMPP u otro servidor PHP, ejecutar `composer install --no-dev` desde
  la raiz. Se requiere PHP 8.2 o superior y OpenSSL. El archivo composer.lock
  fija la version. Composer puede requerir ZIP o unzip para instalarla.
- Docker instala las dependencias durante la construccion de la imagen.
- Configurar las siguientes variables de entorno en el proceso que ejecuta
  PHP/Apache o en el gestor de secretos del despliegue. No guardar la
  contrasena en el codigo ni en el repositorio:

  SMTP_USER       Direccion completa de Gmail; tambien es el remitente.
  SMTP_PASSWORD   Contrasena de aplicacion de Google, sin espacios.
  SMTP_HOST       Opcional; por defecto smtp.gmail.com.
  SMTP_PORT       Opcional; por defecto 465.
  SMTP_SECURITY   Opcional; ssl (TLS implicito, por defecto) o tls (STARTTLS).

Para Gmail tambien puede usarse SMTP_PORT=587 y SMTP_SECURITY=tls. El
certificado del servidor se verifica; el host debe confiar en las CA
necesarias. No se permite enviar la contrasena sin cifrado.
Google requiere verificacion en dos pasos para crear una contrasena de
aplicacion; al cambiar la contrasena de Google puede ser necesario crear
otra. La disponibilidad depende de la configuracion de la cuenta.
https://support.google.com/accounts/answer/185833?hl=es
https://developers.google.com/workspace/gmail/imap/imap-smtp

El servidor necesita conexion saliente al puerto SMTP elegido. Render
bloquea los puertos 25, 465 y 587 en sus servicios web gratuitos:
https://render.com/changelog/free-web-services-will-no-longer-allow-outbound-traffic-to-smtp-ports
Sin una conexion permitida no se podran enviar correos mediante Gmail.
Los errores SMTP se registran sin imprimir credenciales ni el codigo.

Prueba local sin Gmail ni destinatarios reales: `php tests\correo.php`.
Para una prueba de entrega real, configura las variables en el servidor y
solicita un codigo de recuperacion para una cuenta de prueba propia.
