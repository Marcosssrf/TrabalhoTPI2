<?php

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '' && stripos($search, 'produto') !== false) {

    echo json_encode([
        'data' => [
            [
                'cod'       => 1,
                'nome'      => 'Fulano',
                'sobrenome' => 'Beltrano de Ciclano',
                'documento' => '123.456.789-00',
                'categoria' => 'Ouro',
                'email'     => 'fulano@email.com'
            ],
            [
                'cod'       => 2,
                'nome'      => 'Aulano',
                'sobrenome' => 'Beltrano de Ciclano',
                'documento' => '987.654.321-00',
                'categoria' => 'Prata',
                'email'     => 'aulano@email.com'
            ],
            [
                'cod'       => 3,
                'nome'      => 'Betalano',
                'sobrenome' => 'Altrano de Clano',
                'documento' => '111.222.333-44',
                'categoria' => 'Bronze',
                'email'     => 'betalano@email.com'
            ]
        ]
    ]);

} else {

    echo json_encode([
        'data' => [
            [
                'cod'       => 10,
                'nome'      => 'Fulano 10',
                'sobrenome' => 'Beltrano de Ciclano',
                'documento' => '12.345.678/0001-90',
                'categoria' => 'Platinum',
                'email'     => 'fulano10@empresa.com'
            ],
            [
                'cod'       => 12,
                'nome'      => 'Aulano 12',
                'sobrenome' => 'Beltrano de Ciclano',
                'documento' => '98.765.432/0001-10',
                'categoria' => 'Ouro',
                'email'     => 'aulano12@empresa.com'
            ]
        ]
    ]);

}
?>
