<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Formatters;

use App\Passage;
use App\Search;
/**
 * The formatters format the retured data structure before it is sent to the client
 *
 * @author Computer
 */
abstract class FormatterAbstract {
    
    protected $results;
    protected $Passages;
    protected $Search;
    protected $is_search;
    
    public function __construct($results, $Passages, $Search) {
        $this->results      = $results;
        $this->Passages     = $Passages;
        $this->Search       = $Search;
        $this->is_search    = ($Search) ? TRUE : FALSE;
    }
    
    abstract public function format();
    
    protected function _mapResultsToPassages($results) {
        if(!is_array($this->Passages) || !count($this->Passages)) {
            if(!$this->is_search) {
                return FALSE;
            }
            
            $Passages = array();
            
            // We loop through every verse returned for every Bible requested,
            // so none are omitted
            foreach($results as $bible => $verses) {
                foreach($verses as $verse) {
                    $bcv = $verse->book * 1000000 + $verse->chapter * 1000 + $verse->verse;
                    
                    if(empty($Passages[$bcv])) {
                        $Passages[$bcv] = Passage::createFromVerse($verse);
                    }
                }
            }
            
            ksort($Passages, SORT_NUMERIC);
            $this->Passages = array_values($Passages);
        }
        
        $this->Passages = Passage::explodePassages($this->Passages);

        foreach($this->Passages as $Passage) {
            $Passage->claimVerses($results);
        }
        
        foreach($results as $bible => $unclaimed) {
            if(count($unclaimed) > 0) {
                echo('some verses not claimed');
                var_dump($bible);
                print_r($unclaimed);
                return FALSE;
            }
        }
        
        return TRUE;
    }
    
    protected function _createPassageFromSingleVerse($verse) {
        
    }
    
    protected function _preFormatVerses($results) {
        foreach($results as $key => &$verse) {
            
        }
        unset($value);
        
        return $results;
    }
    
    protected function _preFormatVersesHelper(&$verse) {
        
    }
}
