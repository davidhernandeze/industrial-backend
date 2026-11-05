<?php

return [
    'title' => 'Manual de usuario',
    'contents' => 'Contenido',
    'note' => 'Importante',

    'sections' => [
        'start' => [
            'title' => 'Antes de empezar',
            'intro' => 'Este tutorial lleva el sistema desde una instalación nueva hasta la primera venta. Sigue los pasos en orden: cada uno depende del anterior. La etiqueta de cada paso indica qué rol lo realiza.',
            'items' => [
                '**Administrador:** configura el sistema y puede usar todos los módulos, excepto el punto de venta. Trabaja en todas las sucursales.',
                '**Gerente de sucursal:** administra los productos, el inventario y las ventas de sus sucursales, y los clientes de la empresa.',
                '**Cajero:** solo usa el punto de venta de sus sucursales. Al cobrar, también puede elegir o registrar un cliente.',
                '**Sucursal activa:** Productos, Inventario y Ventas aparecen debajo de la línea divisoria del menú y siempre muestran una sola sucursal. Si tienes más de una, elígela en el selector que está sobre esos módulos.',
            ],
            'note' => 'La primera cuenta de administrador se crea durante la instalación. Pide los datos de acceso a quien instaló el sistema.',
        ],

        'company' => [
            'title' => 'Paso 1: Revisa el nombre de la empresa',
            'roles' => ['admin'],
            'intro' => 'Este nombre aparece en la parte superior de todas las pantallas.',
            'steps' => [
                'Inicia sesión con la cuenta de administrador.',
                'En el menú, abre **Configuraciones**.',
                'En la fila **Nombre de la empresa**, abre el menú de opciones de la derecha y elige **Editar**.',
                'Escribe el nombre y guarda los cambios.',
            ],
        ],

        'branch' => [
            'title' => 'Paso 2: Crea la sucursal',
            'roles' => ['admin'],
            'steps' => [
                'En el menú, abre **Sucursales** y pulsa **Crear sucursal**.',
                'Escribe el **Nombre**. Es el único dato obligatorio.',
                'Si quieres, agrega la **Dirección**, la **URL de Google Maps** y la **Fecha de apertura**.',
                'Pulsa **Crear**. Repite estos pasos por cada sucursal.',
            ],
            'note' => 'Los administradores trabajan automáticamente en todas las sucursales. No necesitan asignarse a ninguna.',
        ],

        'catalog' => [
            'title' => 'Paso 3: Registra el catálogo de productos',
            'roles' => ['admin'],
            'intro' => 'El catálogo se comparte entre todas las sucursales y describe cada producto. El precio y la existencia se definen después, en cada sucursal.',
            'steps' => [
                'En el menú, abre **Catálogo de productos** y pulsa **Crear producto**.',
                'Escribe el **Nombre**. Es el único dato obligatorio.',
                'Para registrar el **Código de barras**, haz clic en el campo y escanea el producto. También puedes escribirlo a mano.',
                'Si quieres, agrega **SKU**, **Marca**, **Descripción** e **Imagen** (JPG, PNG o WEBP de hasta 4 MB).',
                'Pulsa **Crear**. Repite estos pasos por cada producto.',
            ],
            'note' => 'Sin código de barras ni SKU, el producto solo se podrá vender buscándolo por nombre. Dos productos no pueden compartir el mismo código de barras o SKU.',
        ],

        'users' => [
            'title' => 'Paso 4: Crea los usuarios',
            'roles' => ['admin'],
            'intro' => 'Necesitas al menos un cajero para poder vender, porque el punto de venta es exclusivo de ese rol.',
            'steps' => [
                'En el menú, abre **Usuarios** y pulsa **Crear usuario**.',
                'Escribe el **Nombre**, el **Correo electrónico** y la **Contraseña** (mínimo 8 caracteres).',
                'Elige el **Rol**.',
                'En **Sucursales**, pasa de **Disponibles** a **Asignadas** las sucursales donde trabajará esta persona. Los administradores no necesitan este dato.',
                'Pulsa **Crear** y entrega a la persona su correo y contraseña.',
            ],
            'note' => 'El rol no se puede cambiar después. Si te equivocas, borra el usuario y crea otro con un correo distinto.',
        ],

        'branch_products' => [
            'title' => 'Paso 5: Agrega productos a la sucursal',
            'roles' => ['admin', 'branch_manager'],
            'intro' => 'Un producto del catálogo solo se puede vender en una sucursal después de agregarlo aquí.',
            'steps' => [
                'Elige la sucursal en el selector del menú, si tienes más de una.',
                'Abre **Productos** y pulsa **Crear producto**.',
                'En **Producto**, busca por nombre, SKU o código de barras. La lista solo muestra los productos que aún no están en la sucursal.',
                'Elige la **Unidad**: **Pieza** para cantidades enteras o **Kilogramo** para vender por peso.',
                'Escribe el **Precio** y pulsa **Crear**.',
            ],
            'note' => 'La unidad no se puede cambiar después. El precio sí, y cada sucursal puede tener el suyo. Un cambio de precio no modifica las ventas ya registradas.',
        ],

        'stock' => [
            'title' => 'Paso 6: Captura la existencia inicial',
            'roles' => ['admin', 'branch_manager'],
            'steps' => [
                'Abre **Inventario** y pulsa **Crear movimiento**.',
                'En **Producto**, elige el producto.',
                'En **Tipo**, elige **Entrada**.',
                'Escribe la **Cantidad**. Por pieza debe ser entera; por kilogramo admite hasta tres decimales.',
                'Si quieres, agrega una **Nota**, como el número de remisión, y pulsa **Crear**.',
            ],
            'items' => [
                'Usa **Entrada** también cada vez que recibas mercancía.',
                'Para corregir diferencias, crea un movimiento de tipo **Ajuste**. Una cantidad negativa resta existencia.',
            ],
            'note' => 'Los movimientos no se editan ni se borran. Si capturaste un dato incorrecto, corrígelo con un Ajuste y explica el motivo en la Nota.',
        ],

        'sale' => [
            'title' => 'Paso 7: Realiza una venta',
            'roles' => ['cashier'],
            'intro' => 'El cajero inicia sesión con su propia cuenta y entra directamente al punto de venta. Si administras el sistema, cierra tu sesión antes de hacer la primera venta de prueba.',
            'steps' => [
                'Si el cajero tiene más de una sucursal, elige la sucursal en la columna derecha.',
                'Agrega productos desde la columna izquierda: escanea el código de barras, escribe el código y pulsa Enter, o busca por nombre con el teclado físico o el de la pantalla y selecciona el resultado.',
                'Si no hay nada escrito, la columna muestra los cinco productos **Más vendidos** para agregarlos con un toque.',
                'Ajusta la cantidad en la columna central con **−** y **+**, o escribe el número. En productos por kilogramo, escribe el peso o elígelo en la lista **Peso**.',
                'Para quitar un producto, pulsa la **X** de su fila. Para descartar toda la venta, pulsa **Vaciar venta**.',
                'Cuando la venta esté completa, pulsa **Cobrar** o la tecla **F2**.',
                'Elige la **Forma de pago**: **Efectivo**, **Tarjeta** o **Transferencia**. En tarjeta y transferencia puedes escribir una **Referencia**.',
                'Si la venta es para un cliente, búscalo en **Cliente** por nombre, teléfono, correo o RFC. Si no existe, pulsa **+** y registra su nombre y teléfono. Este dato es opcional.',
                'Pulsa **Confirmar venta**. El sistema muestra el folio y deja la pantalla lista para el siguiente cliente.',
            ],
            'note' => 'En efectivo, el campo Recibido siempre muestra el total y está bloqueado. El sistema no calcula el cambio, así que el cajero debe calcularlo aparte.',
        ],

        'sales' => [
            'title' => 'Paso 8: Consulta y cancela ventas',
            'roles' => ['admin', 'branch_manager'],
            'steps' => [
                'Abre **Ventas** para ver los indicadores y el historial de la sucursal.',
                'Usa los filtros para buscar por cliente, estado, forma de pago o fechas.',
                'Para ver el detalle, abre el menú de opciones de la venta y elige **Ver**.',
                'Para cancelar, elige **Cancelar venta**, escribe el motivo y pulsa **Sí, cancelar venta**.',
            ],
            'note' => 'Cancelar repone la existencia de los productos y no se puede deshacer. Si cobraste de más o de menos, cancela la venta y captúrala de nuevo.',
        ],

        'clients' => [
            'title' => 'Paso 9: Administra los clientes',
            'roles' => ['admin', 'branch_manager'],
            'intro' => 'Los clientes son de la empresa, así que están disponibles en todas las sucursales. Registrarlos es opcional: se puede vender sin cliente.',
            'steps' => [
                'En el menú, abre **Clientes** y pulsa **Crear cliente**.',
                'Escribe el **Nombre**. Es el único dato obligatorio.',
                'Si quieres, agrega **Teléfono**, **Correo electrónico**, **RFC**, **Dirección** y **Notas**.',
                'Pulsa **Crear**.',
                'Para ver las compras de un cliente en la sucursal activa, abre **Ventas** y usa el filtro **Cliente**.',
            ],
            'note' => 'Borrar un cliente no modifica sus ventas: su nombre sigue apareciendo en ellas, pero ya no se puede elegir en nuevas ventas.',
        ],

        'daily' => [
            'title' => 'Paso 10: Da seguimiento a la operación',
            'roles' => ['admin', 'branch_manager'],
            'items' => [
                '**Escritorio:** muestra las ventas del día y del mes, los tickets, el valor del inventario, las gráficas y las notificaciones de la sucursal.',
                '**Venta sin existencia:** el sistema nunca detiene una venta. Si un producto queda en negativo, avisa a los administradores y a los gerentes de esa sucursal. Revisa el producto y corrige con una Entrada o un Ajuste.',
                '**Detalle de un producto:** en **Productos** o **Inventario**, haz clic en su nombre para ver sus movimientos y un resumen.',
                '**Historial:** en la mayoría de los módulos, el menú de opciones de cada fila incluye **Historial**, que muestra quién cambió qué y cuándo.',
            ],
        ],

        'help' => [
            'title' => 'Problemas frecuentes',
            'items' => [
                '**No veo Punto de venta en el menú.** Solo aparece para los cajeros. Los administradores y gerentes no pueden vender con su cuenta.',
                '**No veo Sucursales, Catálogo de productos, Usuarios ni Configuraciones.** Esos módulos son solo para administradores.',
                '**Aparece “Sin sucursales asignadas”.** Aún no existe ninguna sucursal. Crea la primera (paso 2).',
                '**Al iniciar sesión aparece un error 403.** Tu usuario no tiene sucursales asignadas. Pide a un administrador que te asigne a una.',
                '**Al escanear aparece “No se encontró”.** El producto no está agregado a esta sucursal (paso 5), o su código no está registrado en el catálogo (paso 3).',
                '**Aparece “Los precios cambiaron”.** Un precio se modificó mientras se cobraba. Revisa el total y cobra de nuevo.',
                '**Olvidé mi contraseña.** Un administrador puede escribir una nueva al editar tu usuario. Por seguridad, no la compartas por mensajes.',
            ],
        ],
    ],
];
