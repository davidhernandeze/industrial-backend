<?php

return [
    'model_label' => 'producto',
    'plural_model_label' => 'productos',

    'keeps_unit' => 'Este producto ya estuvo en la sucursal y conserva su unidad.',

    'fields' => [
        'branch_id' => 'Sucursal',
        'product' => 'Producto',
        'product_id' => 'Producto',
        'unit' => 'Unidad',
        'price' => 'Precio',
        'stock' => 'Existencia',
    ],

    'unit_abbreviations' => [
        'kg' => 'kg',
        'piece' => 'pza',
    ],

    'units' => [
        'kg' => 'Kilogramo',
        'piece' => 'Pieza',
    ],
];
