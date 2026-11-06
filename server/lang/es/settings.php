<?php

return [
    'model_label' => 'configuración',
    'plural_model_label' => 'configuraciones',

    'fields' => [
        'name' => 'Nombre',
        'key' => 'Clave',
        'type' => 'Tipo',
        'value' => 'Valor',
        'entity_type' => 'Modelo',
        'entity_id' => 'Valor',
        'updated_at' => 'Última actualización',
    ],

    'types' => [
        'string' => 'Texto',
        'number' => 'Número',
        'price' => 'Precio',
        'entity' => 'Registro',
    ],

    'keys' => [
        'company_name' => 'Nombre de la empresa',
        'main_branch' => 'Sucursal principal',
    ],
];
