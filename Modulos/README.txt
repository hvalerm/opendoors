ESTRUCTURA DE MODULOS

Cada modulo reside en Modulos\<nombre exacto de modulos.nombreModulo>.
La coincidencia distingue mayusculas y minusculas incluso en Windows.
Cada carpeta contiene menuLateral.php, Nucleo y Configuracion.
GESTALO conserva estas carpetas preparadas, sin funcionalidad aun.

vistaAnfitrion carga unicamente modulos habilitados para la empresa en
empresas_modulos. Cada menuLateral.php devuelve una funcion que recibe
el registro del modulo (idModulo y nombreModulo) y el nombre de la web.
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

Las rutas web anteriores de OpenDoors siguen funcionando por las reglas de
.htaccess; las API y el acceso publico mantienen sus rutas mediante archivos
de entrada. El servidor debe permitir mod_rewrite y AllowOverride. El proceso
de aperturas ahora se encuentra en OpenDoors\Nucleo\util\procesar_aperturas.php;
actualizar cualquier servicio externo que siga usando la ruta anterior.
