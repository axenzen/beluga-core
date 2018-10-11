<?php

return [
    'extends' => 'bootstrap3',
    'css' => [
        'jquery.qtip.min.css',
        'uikit.min.css',
        'compiled.css',
        'belugino.css',
        'belugax.css',
    ],
    'js' => [
        'jquery.qtip.min.js',
        'uikit.min.js',
        'belugax.js',
        'check_item_statuses.js',
    ],
    'mixins' => [
        'belugaconfig',
        'delivery',
        'libraries',
        'searchkeys',
        'dependentworks',
//        'daia',
        'recorddriver',
        'beluga-core-base',
    ],
    "less" => [
        "active" => false,
        "compiled.less"
    ],
];
