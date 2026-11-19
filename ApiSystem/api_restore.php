<?php
require_once __DIR__ . '/../util/api_dispositivo.php';

// El firmware envía cifrado en el cuerpo del POST después de accionar la puerta.
atenderDispositivo(OperacionDispositivo::Restablecer);
