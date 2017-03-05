<?php

return array(
    'name' => 'Query',
    'description' => 'Used for all queries against the Bibles in our database, including keyword searchs and passage retrieval. <br />'
    . 'When running a query, the only requirement is that either \'reference\' or \'search\' are required.',
    'advanced' => 'These advanced search fields can be used in addition to the standard ones above to build a powerful search interface',
    'params' => array(
        'reference' => array(
            'type' => 'String',
            'name' => 'Reference',
            'default' => '(none)',
            'description' => 'Passage reference, such as Romans 1:1-20; Acts 2:3; 1 Cor 5:20-6:7',
        ),
        'search' => array(
            'type' => 'String',
            'name' => 'Search',
            'default' => '(none)',
            'description' => 'Keyword search',
        ),
        'search_type' => array(
            'type' => 'String Select',
            'name' => 'Search Type',
            'default' => 'and',
            'description' => 'Type of search when using keyword search. <br />'
            . 'Options: <ul>'
            . '<li>"and" or "all_words" - Searches for verses containing all words given</li>'
            . '<li>"or" or "any_words" - Searches for verses containing any words given</li>'
            . '<li>"xor" or "one_words" - Searches for verses containing only one word given</li>'
            . '<li>"phrase" - Searches for verses containing the exact phrase given</li>'
            . '<li>"boolean" - Searches for verses matching the boolean expression</li>'
            . '<li>"regexp" - Searches for verses matching the regular expression</li>'
            . '<li>"proximity" - Searches for words within 5 verses but not nessessarily in the same verse or chapter.<br />'
            . 'This limit can be set via proximity_limit</li>'
            . '<li>"chapter" - Searches for words within the same chapter but not nessessarily in the same verse.</li>'
            . '<li>"book" - Searches for words within the same book but not nessessarily in the same chapter or verse.</li>'
            . '</ul>',
        ),
        'bible' => array(
            'type' => 'String, Array or JSON encoded array',
            'name' => 'Bibles',
            'default' => env('DEFAULT_BIBLE', 'kjv'),
            'description' => 'Bible(s) to query against.  Use the Bibles action to get a list of available Bibles.',
        ),
        'whole_words' => array(
            'type' => 'Boolean',
            'name' => 'Whole Words',
            'default' => 'false',
            'description' => 'Whether to look for exact words.  Otherwise, keywords will be found within words.',
        ),
        'exact_case' => array(
            'type' => 'Boolean',
            'name' => 'Exact Case',
            'default' => 'false',
            'description' => 'Whether to look for the exact case.  Searches are case-insensitive by default.',
        ),
        'highlight' => array(
            'type' => 'Boolean',
            'name' => 'Highlight',
            'default' => 'false',
            'description' => 'Whether to highlight keywords in retrieved verses.  Setting this to true will cause highlight_tag to be wrapped around'
            . 'each matched keyword.',
        ),
        'highlight_tag' => array(
            'type' => 'Boolean',
            'name' => 'Highlight Tag',
            'default' => 'b',
            'description' => 'HTML tag to use for wrapping highlighted keywords. Just set to the name of the tag.'
        ),
        'data_format' => array(
            'type' => 'passage or raw',
            'name' => 'Data Format',
            'default' => 'passage',
            'description' => 'Format of the outputed data structure. <br />'
            . '\'minimal\' or \'raw\' format simply groups all verses by the Bible.<br />'
            . '\'passage\' format groups verses by Bible and groups them into passages. <br />'
            . 'See examples below.',
            'raw' => array(
                'description' => 'Verses are grouped simply by Bible and ordered in the way they appear in the Bible.  No book information is included.',
            ),
            'passage' => array(
                'description' => 'Verses are grouped into passages, and passages are ordered in the order they were requested by the user.',
                'id_bible' => 'Indexed by Bible',
                'id_chapter' => 'Indexed by chapter',
                'id_verse' => 'Indexed by verse',
                'single' => 'Indicates if the passage contains exactly one verse.  Note: Searches will result in all single verse passages.',
            ),
        ),
        'proximity_limit' => array(
            'type' => 'Int',
            'name' => 'Proximity Limit',
            'default' => '5',
            'description' => 'Proximity limit.  For Proximity search types, sets the range of allowable verses between keywords.',
        ),
        'search_all' => array(
            'type' => 'String',
            'name' => 'Search All',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for all words.',
        ),
        'search_any' => array(
            'type' => 'String',
            'name' => 'Search Any',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for any words.',
        ),
        'search_one' => array(
            'type' => 'String',
            'name' => 'Search One',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for one of the words.',
        ),
        'search_none' => array(
            'type' => 'String',
            'name' => 'Search None',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for none of the words.',
        ),
        'search_phrase' => array(
            'type' => 'String',
            'name' => 'Search Phrase',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for an exact phrase.',
        ),
        'search_regexp' => array(
            'type' => 'String',
            'name' => 'Search REGEXP',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for a given regular expression.',
        ),
        'search_boolean' => array(
            'type' => 'String',
            'name' => 'Search Boolean',
            'default' => '(none)',
            'description' => 'For an advanced search form, this is the input for searching for a given boolean expression.',
        ),
    )
);
