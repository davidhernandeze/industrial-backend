<?php

return [
    'model_label' => 'movimiento',
    'plural_model_label' => 'movimientos',
    'navigation_label' => 'Inventario',
    'oversold' => 'Venta sin existencia',
    'adjustment_help' => 'Usa un valor negativo para restar existencia.',

    'overview' => [
        'period' => 'Últimos :days días',
        'per_unit' => 'Por :unit',
        'oversold' => 'Ventas sin existencia',
        'sold' => 'Vendido',
        'purchased' => 'Entradas',
        'last_movement' => 'Último movimiento',
        'negative_stock' => 'Existencia negativa',
        'value' => 'Valor a precio de venta: :value',
    ],

    'fields' => [
        'date' => 'Fecha',
        'product' => 'Producto',
        'type' => 'Tipo',
        'quantity' => 'Cantidad',
        'balance_after' => 'Existencia',
        'flag' => 'Alerta',
        'user' => 'Usuario',
        'note' => 'Nota',
    ],

    'types' => [
        'purchase' => 'Entrada',
        'sale' => 'Venta',
        'adjustment' => 'Ajuste',
        'sale_cancellation' => 'Cancelación de venta',
    ],

    'errors' => [
        'zero' => 'La cantidad no puede ser cero.',
        'negative' => 'La cantidad debe ser positiva. Usa un ajuste para restar.',
        'whole' => 'Este producto se vende por pieza: la cantidad debe ser entera.',
    ],
];
