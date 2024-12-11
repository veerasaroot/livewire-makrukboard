<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Board Theme
    |--------------------------------------------------------------------------
    |
    | The theme settings for the makruk board including colors and styles
    |
    */
    'theme' => [
        'light_square' => 'bg-amber-200',
        'dark_square' => 'bg-amber-800',
        'selected' => 'ring-2 ring-blue-500',
        'valid_move' => 'ring-2 ring-green-500',
        'last_move' => 'ring-2 ring-yellow-500',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Position
    |--------------------------------------------------------------------------
    |
    | The default starting position in FEN notation
    |
    */
    'default_position' => 'rnsmksnr/8/pppppppp/8/8/PPPPPPPP/8/RNSKMSNR w - - 0 1',

    /*
    |--------------------------------------------------------------------------
    | Move Rules
    |--------------------------------------------------------------------------
    |
    | Configuration for piece movement rules
    |
    */
    'rules' => [
        'enable_countmoves' => true,  // นับจำนวนครั้งการเดิน
        'promotion_rank' => [  // แถวที่เบี้ยจะเลื่อนขั้น
            'white' => 3,  // แถวที่ 3 จากบน
            'black' => 6,  // แถวที่ 6 จากบน
        ],
        'piece_values' => [
            'k' => 0,    // ขุน
            'm' => 9,    // เม็ด
            'n' => 3,    // ม้า
            'r' => 4,    // เรือ
            's' => 3,    // โคน
            'p' => 1,    // เบี้ย
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for board events and callbacks
    |
    */
    'events' => [
        'enable_move_sound' => true,
        'enable_capture_sound' => true,
        'enable_check_sound' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface Options
    |--------------------------------------------------------------------------
    |
    | Configuration for board interface
    |
    */
    'interface' => [
        'show_coordinates' => true,
        'show_move_hints' => true,
        'show_piece_values' => false,
        'animation_speed' => 200, // milliseconds
        'enable_drag_drop' => true,
        'enable_premove' => false,
    ],
];