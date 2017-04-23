<?php

/* Bible SuperSearch configs */
return array(
    'defaults' => array(
        'language'       => env('DEFAULT_LANGUAGE', 'English'),
        'language_short' => env('DEFAULT_LANGUAGE_SHORT', 'en'),
        'bible'          => env('DEFAULT_BIBLE', 'kjv'),
    ),
    'import_from_v2' => env('IMPORT_FROM_V2', FALSE),
    'daily_access_limit' => env('DAILY_ACCESS_LIMIT', 2000),

    'pagination' => array(
        'limit' => 30,
    )
);
