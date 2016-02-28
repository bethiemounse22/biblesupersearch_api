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
    
    public function __construct() {
        // Set the default Bible
        $default_bible = config('bss.defaults.bible');
        $this->addBible($default_bible);
        $this->setPrimaryBible($default_bible);
    }
    
    public function setBibles($modules) {
        $this->Bibles = array();
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
        $results = $bible_no_results = array();
        $input['bible'] && $this->setBibles($input['bible']);
                
        // Todo - Routing and merging of multiple elements here
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search']) ? NULL : $input['search'];
        
        $Search    = Search::parseSearch($keywords, $input);
        $is_search = ($Search) ? TRUE : FALSE;
        $Passages  = Passage::parseReferences($references, $this->languages, $is_search);
        
        foreach($this->Bibles as $Bible) {
            $bible_results = $Bible->getSearch($Passages, $Search, $input);
            
            if($bible_results) {
                $results[$Bible->module] = $bible_results;
            }
            else {
                $bible_no_results[] = trans('errors.bible_no_results', ['module' => $Bible->module]);
            }
        }
        
        // Todo: Error handling
        if(empty($results)) {
            $this->addError( trans('errors.no_results') );
        }
        elseif(!empty($bible_no_results)) {
            $this->addErrors($bible_no_results);
        }

        // Todo: Format data structure
        
        
        return $results;
    }

}
