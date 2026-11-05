<?php

return [
    'model_label' => 'usuario',
    'plural_model_label' => 'usuarios',

    'fields' => [
        'name' => 'Nombre',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'role' => 'Rol',
        'branches' => 'Sucursales',
    ],

    'roles' => [
        'admin' => 'Administrador',
        'branch_manager' => 'Gerente de sucursal',
        'cashier' => 'Cajero',
        'driver' => 'Repartidor',
    ],

    'assignment' => [
        'available' => 'Disponibles',
        'assigned' => 'Asignadas',
        'add' => 'Agregar',
        'remove' => 'Quitar',
        'add_all' => 'Agregar todas',
        'remove_all' => 'Quitar todas',
        'empty' => 'Ninguna',
    ],
];
