<?php

return [
    'model_label' => 'venta',
    'plural_model_label' => 'ventas',

    'sections' => [
        'lines' => 'Productos',
        'payments' => 'Pago',
        'cancellation' => 'Cancelación',
    ],

    'overview' => [
        'today' => 'Hoy',
        'this_month' => 'Este mes',
        'total' => 'Ventas',
        'count' => 'Tickets',
        'average' => 'Ticket promedio',
        'cancelled' => 'Canceladas',
        'month_total' => 'Ventas del mes',
    ],

    'cancel' => [
        'label' => 'Cancelar venta',
        'heading' => '¿Cancelar esta venta?',
        'description' => 'La existencia de sus productos se repondrá. Esta acción no se puede deshacer.',
        'confirm' => 'Sí, cancelar venta',
        'done' => 'Venta cancelada',
    ],

    'statuses' => [
        'paid' => 'Pagada',
        'cancelled' => 'Cancelada',
    ],

    'types' => [
        'point_of_sale' => 'Punto de venta',
    ],

    'payment_methods' => [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
    ],

    'fields' => [
        'folio' => 'Folio',
        'date' => 'Fecha',
        'branch_id' => 'Sucursal',
        'user_id' => 'Cajero',
        'user' => 'Cajero',
        'status' => 'Estado',
        'type' => 'Tipo',
        'total' => 'Total',
        'payment_method' => 'Forma de pago',
        'amount_received' => 'Recibido',
        'change' => 'Cambio',
        'reference' => 'Referencia',
        'cancelled_at' => 'Cancelada el',
        'cancelled_by' => 'Cancelada por',
        'cancellation_reason' => 'Motivo de cancelación',
        'product' => 'Producto',
        'quantity' => 'Cantidad',
        'unit_price' => 'Precio',
        'line_total' => 'Importe',
        'from' => 'Desde',
        'until' => 'Hasta',
    ],

    'errors' => [
        'empty' => 'Agrega al menos un producto.',
        'insufficient_payment' => 'El monto recibido no cubre el total.',
        'unavailable_product' => 'Uno de los productos ya no está disponible en esta sucursal.',
        'already_cancelled' => 'Esta venta ya fue cancelada.',
    ],
];
