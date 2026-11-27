ESTRUCTURA DE MODULOS

Cada modulo reside en Modulos\<nombre exacto de modulos.nombreModulo>.
La coincidencia distingue mayusculas y minusculas incluso en Windows.
Cada carpeta contiene menuLateral.php, Nucleo y Configuracion.
GESTALO conserva estas carpetas preparadas, sin funcionalidad aun.

El inicio de sesion no depende de la licencia de la empresa. vistaAnfitrion
muestra los modulos habilitados de las empresas habilitadas vinculadas a la
cuenta. Para cuentas multempresa, cada modulo agrupa sus opciones por empresa;
si la licencia del modulo ha vencido, el grupo muestra el aviso de vencimiento
sin exponer sus opciones. La licencia dura hasta el final de la fecha en
empresas_modulos.licenciaExpira, en la zona de empresas.
Cada menuLateral.php devuelve una funcion que recibe
el registro del modulo (incluido idEmpresa) y el nombre de la web. Debe
construir sus opciones para la empresa recibida, no para la empresa de sesion.
La funcion devuelve un arreglo con:

- principal: [titulo, icono SVG (path), hijos, pagina opcional, clave opcional]
- configuracion: [titulo, hijos, pagina opcional, clave, icono SVG (path)]
- prioridad opcional: numero menor aparece antes; el valor por defecto es 1.
- script opcional: URL del JavaScript del modulo, relativa a vistaAnfitrion.
- guia opcional: ruta absoluta a un fragmento PHP para la pagina de inicio.

Cada hijo es [titulo, hijos, pagina, clave, icono SVG, mensaje de lista
vacia, ID de lista]; los ultimos campos son opcionales. Una pagina puede
apuntar a ../Modulos/Nombre/Nucleo/pagina.php o a Configuracion. Se muestra
en un iframe y se le agrega embed=1. Las claves de navegacion deben ser
unicas. Veanse OpenDoors\menuLateral.php y GESTALO\menuLateral.php.

Un modulo habilitado sin carpeta o menu se muestra como no disponible y
se registra el problema. Los archivos del modulo deben comprobar la sesion,
los permisos de empresa y la habilitacion del modulo cuando corresponda:
ocultar el menu no protege por si solo sus URL.
Las paginas privadas deben comprobar tambien la empresa vinculada, la
habilitacion y la licencia con empresaModulo(nombreModulo), despues de
requerirSesion(). Deben conservar el parametro empresa en enlaces, formularios,
redirecciones y consultas posteriores, sin confiar en el valor sin validarlo.
En OpenDoors el vencimiento limita el panel, no las API de dispositivos ni
los enlaces publicos de apertura.

Las rutas web anteriores de OpenDoors siguen funcionando por las reglas de
.htaccess; las API y el acceso publico mantienen sus rutas mediante archivos
de entrada. El servidor debe permitir mod_rewrite y AllowOverride. Las
aperturas se resuelven al recibir peticiones, sin proceso permanente; ver
migraciones\seguimiento_aperturas.txt para el despliegue y sus limitaciones.

UTILIDADES GENERALES

Las implementaciones comunes estan agrupadas en util\BaseDatos, Correo,
Configuracion, Cuentas, Seguridad y Soporte. La configuracion de
la URL publica se edita en util\Configuracion\DatosWeb.php. El certificado CA
opcional mantiene su ubicacion anterior en util\ca.pem.

Las referencias PHP y los comandos externos deben usar las rutas de las
subcarpetas; las rutas anteriores en la raiz de util ya no existen.
Docker inicia Apache directamente.
