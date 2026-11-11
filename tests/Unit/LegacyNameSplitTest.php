<?php

use App\Services\Import\LegacyWorkbookReader;

it('splits "APELLIDOS Y NOMBRES" into surnames and names', function (string $full, string $last, string $first) {
    expect(LegacyWorkbookReader::splitName($full))->toBe([$last, $first]);
})->with([
    ['Ambrosio Díaz Lorena Maricela', 'Ambrosio Díaz', 'Lorena Maricela'],
    ['Celi Soto Cinthia', 'Celi Soto', 'Cinthia'],
    ['Chavez Rodriguez Madely de Fatima', 'Chavez Rodriguez', 'Madely de Fatima'],
    ['De la Cruz Pérez Juan', 'De la Cruz Pérez', 'Juan'],
    ['Quinto Paitan Q\'ori Q\'oyllor', 'Quinto Paitan', 'Q\'ori Q\'oyllor'],
    ['Polo Luis', 'Polo', 'Luis'],
    ['  Rojas   Cotrina  Nelly ', 'Rojas Cotrina', 'Nelly'],
    ['Solo', '', 'Solo'],
]);
