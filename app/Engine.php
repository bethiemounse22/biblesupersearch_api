<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Passage;
use App\Search;

class Engine {
    use Traits\Error;
    use Traits\Input;

    protected $Bibles = array(); // Array of Bible objects
    protected $Bible_Primary = NULL; // Primary Bible version
    protected $languages = array();
    protected $default_data_format = 'passage';
    protected $default_page_all = FALSE;
    public $debug = FALSE;

    public function __construct() {
        // Set the default Bible
        $default_bible = config('bss.defaults.bible');
        $this->addBible($default_bible);
        $this->setPrimaryBible($default_bible);
    }

    public function setBibles($modules) {
        $this->Bibles = array();
        $this->languages = array();

        if(is_string($modules)) {
            $decoded = json_decode($modules);
            $modules = (json_last_error() == JSON_ERROR_NONE) ? $decoded : $modules;
        }

        $modules = (is_array($modules)) ? $modules : array($modules);
        $Bibles = Bible::whereIn('module', $modules)->get();
        $primary = NULL;

        foreach($modules as $module) {
            $added = $this->addBible($module);
            $primary = ($added && !$primary) ? $module : $primary;
        }

        $this->setPrimaryBible($primary);
    }

    public function setPrimaryBible($module) {
        if(!$module) {
            return FALSE;
        }

        if(!isset($this->Bibles[$module]) && !$this->addBible($module)) {
            return FALSE;
        }

        $this->Bible_Primary = $this->Bibles[$module];
        return TRUE;
    }

    public function addBible($module) {
        $Bible = Bible::findByModule($module);

        if($Bible) {
            $this->Bibles[$module] = $Bible;

            if(!in_array($Bible->lang_short, $this->languages)) {
                $this->languages[] = $Bible->lang_short;
            }
        }
        else {
            $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
            return FALSE;
        }

        return TRUE;
    }

    public function getBibles() {
        return $this->Bibles;
    }

    /**
     * 'Query' API Action
     * Primary Bible Look Up And Search
     * Implements the bulk of the
     *
     * @param array $input request data
     * @return array $results search / look up results.
     */
    public function actionQuery($input) {
        // To do - add labels
        $parsing = array(
            'reference' => array(
                'type'  => 'string',
            ),
            'search' => array(
                'type'  => 'string',
            ),
            'bible' => array(
                'type'  => 'array_string',
            ),
            'whole_words' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'exact_case' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'data_format' => array(
                'type'  => 'string',
                //'default' => 'passage', // breaking!
            ),
            'highlight' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'page' => array(
                'type'  => 'int'
            ),
            'page_all' => array(
                'type'  => 'bool',
                'default' => $this->default_page_all,
            ),
            'highlight_tag' => array(
                'type'  => 'string',
            ),
            'search_type' => array(
                'type'  => 'string',
            ),
            'proximity_limit' => array(
                'type'  => 'int',
            ),
            'callback' => array(
                'type'  => 'string',
            ),
            'search_all' => array(
                'type'  => 'string',
            ),
            'search_any' => array(
                'type'  => 'string',
            ),
            'search_one' => array(
                'type'  => 'string',
            ),
            'search_none' => array(
                'type'  => 'string',
            ),
            'search_phrase' => array(
                'type'  => 'string',
            ),
        );

        $this->resetErrors();
        $results = $bible_no_results = array();
        !empty($input['bible']) && $this->setBibles($input['bible']);
        $input = $this->_sanitizeInput($input, $parsing);
        $input['bible'] = array_keys($this->Bibles);
        $input['multi_bibles'] = (count($input['bible']) > 1) ? TRUE : FALSE;

        // Secondary search elements are detected automatically by Search class
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search'])    ? NULL : $input['search'];
        $Search     = Search::parseSearch($keywords, $input);
        $is_search  = ($Search) ? TRUE : FALSE;

        if(!$is_search && empty($references)) {
            $this->addError(trans('errors.no_query'), 4);
            return FALSE;
        }

        // Passage parsing and validation
        $Passages = Passage::parseReferences($references, $this->languages, $is_search, $this->Bibles);

        if(is_array($Passages)) {
            foreach($Passages as $key => $Passage) {
                if($Passage->hasErrors()) {
                    $this->addErrors($Passage->getErrors(), $Passage->getErrorLevel());
                    unset($Passages[$key]);
                }
            }

            if(empty($Passages)) {
                $this->setErrorLevel(4);
                return FALSE; // If all of the passages are invalid, return
            }
        }

        // Search validation
        if($Search) {
            $search_valid = $Search->validate();

            if(!$search_valid) {
                $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
            }

            if($this->error_level == 4) {
                return FALSE;
            }
        }

        if(!$Search || $Search && $search_valid) {
            foreach($this->Bibles as $Bible) {
                $BibleResults = $Bible->getSearch($Passages, $Search, $input); // Laravel Collection

                if(!empty($BibleResults) && !$BibleResults->isEmpty()) {
                    $results[$Bible->module] = $BibleResults->all();

                    if($BibleResults->count() == config('bss.global_maximum_results')) {
                        $this->addError( trans('errors.result_limit_reached', ['maximum' => config('bss.global_maximum_results')]), 3);
                    }
                }
                else {
                    $bible_no_results[] = trans('errors.bible_no_results', ['module' => $Bible->module]);
                }
            }

            if(empty($results)) {
                if($Search) {
                    if($Search->hasErrors()) {
                        $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
                    }
                    else {
                        $this->addError( trans('errors.no_results'), 4);
                    }
                }
                else {
                    $this->setErrorLevel(4);
                }
            }
            elseif(!empty($bible_no_results)) {
                $this->addErrors($bible_no_results, 3);
            }
        }

        if(is_array($Passages) && !$Search) {
            foreach($Passages as $Passage) {
                if($this->debug) {
                    print_r($Passage->chapter_verse_parsed);
                }

                if(!$Passage->claimVerses($results, TRUE)) {
                    $this->addError( trans('errors.passage_not_found', ['passage' => $Passage->raw_book . ' ' . $Passage->raw_chapter_verse]), 3);
                }
            }
        }

        $results = $this->_formatDataStructure($results, $input, $Passages, $Search);

        if(config('app.debug')) {
            //$this->addError( '<pre>' . print_r($_SESSION['debug'], TRUE) . '</pre>', 1);
            //$results['debug'] = $_SESSION['debug'];
        }

        return $results;
    }

    /**
     * API action query for getting a list of Bibles available to the user
     * @param array $input
     */
    public function actionBibles($input) {
        $include_desc = FALSE;
        $Bibles = Bible::select('name','shortname','module','year','lang','lang_short','copyright','italics','strongs','rank','research');
        $bibles = array(); // Array of associative arrays

        if($include_desc) {
            $Bibles -> addSelect('description');
        }

        if(array_key_exists('order_by_lang_name', $input) && !empty($input['order_by_lang_name'])) {
            $Bibles -> orderBy('lang', 'ASC') -> orderBy('name', 'ASC');
        }
        else {
            $Bibles -> orderBy('rank', 'ASC');
        }

        $Bibles = $Bibles -> where('enabled', 1) -> get() -> all();

        if(empty($Bibles)) {
            $this->addError(trans('errors.no_bible_enabled'));
            return FALSE;
        }

        foreach($Bibles as $Bible) {
            $bibles[$Bible->module] = $Bible->getAttributes();
        }

        return $bibles;
    }

    /**
     * API Action query for getting the list of books for the specified language.
     * @param array $input
     */
    public function actionBooks($input) {
        $language = (!empty($input['language'])) ? $input['language'] : env('DEFAULT_LANGUAGE_SHORT', 'en');
        $namespaced_class = 'App\Models\Books\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Books\\' . env('DEFAULT_LANGUAGE_SHORT', 'en');
        }

        $Books = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
        return $Books;
    }

    public function actionStatics($input) {
        $response = new \stdClass;
        $response->bibles       = $this->actionBibles($input);
        $response->books        = $this->actionBooks($input);
        $response->name         = config('app.name');
        $response->version      = config('app.version');
        $response->environment  = config('app.env');
        return $response;
    }

    public function actionVersion($input) {
        $response = new \stdClass;
        $response->name         = config('app.name');
        $response->version      = config('app.version');
        $response->environment  = config('app.env');
        return $response;
    }

    protected function _formatDataStructure($results, $input, $Passages, $Search) {
        $format_type = (!empty($input['data_format'])) ? $input['data_format'] : $this->default_data_format;

        // Defines avaliable data formats and their aliases
        $format_map = array(
            'raw'       => 'minimal',
            'minimal'   => 'minimal',
            'passage'   => 'passage',
        );

        $format_type  = (array_key_exists($format_type, $format_map)) ? $format_map[$format_type] : 'passage';
        $format_class = '\App\Formatters\\' . ucfirst($format_type);

        if($this->isTruthy('highlight', $input)) {
            $results = $this->_highlightResults($results, $Search);
        }

        $Formatter = new $format_class($results, $Passages, $Search);
        return $Formatter->format();
    }

    protected function _highlightResults($results, $Search) {
        if(!$Search) {
            return $results;
        }

        return $Search->highlightResults($results);
    }

    protected function _sanitizeInput($input, $parsing) {
        $clean = array();

        foreach($parsing as $index => $s) {
            $value = NULL;

            if(array_key_exists($index, $input) && !empty($input[$index])) {
                switch($s['type']) {
                    case 'boola':
                        $value = ($input[$index] && $input[$index] != 'false') ? TRUE : FALSE;
                        break;
                    case 'array_string':
                    case 'string_array':
                        // This needs to be parsed here - just passing through now
                        $value = $input[$index];
                        break;
                    case 'int':
                        $value = intval($input[$index]);
                        break;
                    case 'string':
                        $value = strval($input[$index]);
                        break;
                    default:
                        $value = $input[$index];
                }
            }

            if(!$value && array_key_exists('default', $s)) {
                $clean[$index] = $s['default'];
            }
            elseif($value) {
                $clean[$index] = $value;
            }
        }

        return $clean;
    }

    public function setDefaultDataType($type) {
        $this->default_data_format = $type;
    }

    public function setDefaultPageAll($value) {
        $this->default_page_all = ($value) ? TRUE : FALSE;
    }
}

