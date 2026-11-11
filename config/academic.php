<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Escala de calificaciones
    |--------------------------------------------------------------------------
    |
    | Escala vigesimal (0 a 20). La nota mínima aprobatoria es la única fuente
    | de verdad para determinar el estado de una matrícula. La escala máxima
    | también está protegida por una restricción CHECK en PostgreSQL.
    |
    */

    'min_grade' => 0,

    'max_grade' => 20,

    'passing_grade' => (float) env('ACADEMIC_PASSING_GRADE', 14),

    /*
    |--------------------------------------------------------------------------
    | Código de registro
    |--------------------------------------------------------------------------
    |
    | Formato: {prefijo}-{CODIGO_CURSO}-{NN}-{AAA}
    | Ejemplo: HGC-PA-01-024
    |
    */

    'registration_prefix' => 'HGC',

    'registration_number_padding' => 2,

    'registration_year_digits' => 3,

];
