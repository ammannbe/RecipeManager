<?php

return [

    // The name in quotes, e.g. Add to "Ingredients".
    'builder' => [

        'actions' => [

            'add' => [

                'label' => 'Add to ":label"',

                'modal' => [

                    'heading' => 'Add to ":label"',

                ],

            ],

            'add_between' => [

                'modal' => [

                    'heading' => 'Add to ":label"',

                ],

            ],

        ],

    ],

    'repeater' => [

        'actions' => [

            'add' => [
                'label' => 'Add to ":label"',
            ],

        ],

    ],

    'select' => [

        'placeholder' => 'Please select',

    ],

];
