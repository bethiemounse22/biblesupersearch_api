<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Passage;
use App\Search;

class Engine {
    use Traits\Error;
    
    protected $Bibles = array(); // Array of Bible objects
    protected $Bible_Primary = NULL; // Primary Bible version
    protected $languages = array();
    protected $default_data_format = 'passage';
    
    public function __construct() {
        // Set the default Bible
        $default_bible = config('bss.defaults.bible');
        $this->addBible($default_bible);
        $this->setPrimaryBible($default_bible);
    }
    
    public function setBibles($modules) {
        $this->Bibles = array();
        
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
        $this->languages = array();
        
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
        $this->resetErrors();
        $results = $bible_no_results = array();
        !empty($input['bible']) && $this->setBibles($input['bible']);
                
        // Todo - Routing and merging of multiple elements here
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search'])    ? NULL : $input['search'];
        $Search     = Search::parseSearch($keywords, $input);
        $is_search  = ($Search) ? TRUE : FALSE;
        
        if(!$is_search && empty($references)) {
            $this->addError(trans('errors.no_query'), 4);
            return FALSE;
        }
        
        // Passage validation
        $Passages  = Passage::parseReferences($references, $this->languages, $is_search);
        
        if(is_array($Passages)) {            
            $passage_error_count = 0;
            
            foreach($Passages as $Passage) {
                if($Passage->hasErrors()) {
                    $this->addErrors($Passage->getErrors(), $Passage->getErrorLevel());
                    $passage_error_count ++;
                }
            }
            
            if(count($Passages) == $passage_error_count) {
                return FALSE; // If all of the passages are invalid, return
            }
        }
        
        // Search validation
        if($Search) {
            $search_valid = $Search->validate();
            
            if(!$search_valid) {
                $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
            }
        }
        
        if(!$Search || $Search && $search_valid) {
            foreach($this->Bibles as $Bible) {
                $bible_results = $Bible->getSearch($Passages, $Search, $input);

                if($bible_results) {
                    $results[$Bible->module] = $bible_results;
                }
                else {
                    $bible_no_results[] = trans('errors.bible_no_results', ['module' => $Bible->module]);
                }
            }
            
            if(empty($results)) {
                $this->addError( trans('errors.no_results'), 4);
            }
            elseif(!empty($bible_no_results)) {
                $this->addErrors($bible_no_results, 3);
            }
        }
        
        if(is_array($Passages)) {  
            foreach($Passages as $Passage) {
                if(!$Passage->claimVerses($results, TRUE)) {
                    $this->addError( trans('errors.passage_not_found', ['passage' => $Passage->raw_book . ' ' . $Passage->raw_chapter_verse]), 3);
                }
            }
        }
        
        $results = $this->_formatDataStructure($results, $input, $Passages, $Search);
        return $results;
    }
    
    /**
     * API action query for getting a list of Bibles available to the user
     */
    public function actionBibles() {
        $include_desc = FALSE;
        $Bibles = Bible::select('name','shortname','module','year','lang','lang_short','copyright','italics','strongs','rank');
        $bibles = array(); // Array of associative arrays
        
        if($include_desc) {
            $Bibles -> addSelect('description');
        }
        
        $Bibles = $Bibles -> where('enabled', 1) -> orderBy('rank', 'ASC') -> get() -> all();
        
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
     * @param type $input
     */
    public function actionBooks($input) {
        $language = (!empty($input['language'])) ? $input['language'] : env('DEFAULT_LANGUAGE_SHORT', 'en');
        $namespaced_class = 'App\Models\Books\\' . ucfirst($language);
        
        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Books\\' . env('DEFAULT_LANGUAGE_SHORT', 'en');
        }
        
        $Books = $namespaced_class::orderBy('id', 'ASC') -> get() -> all();
        return $Books;
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
        
        $Formatter = new $format_class($results, $Passages, $Search);
        return $Formatter->format();
    }
    
    public function setDefaultDataType($type) {
        $this->default_data_format = $type;
    }
}

