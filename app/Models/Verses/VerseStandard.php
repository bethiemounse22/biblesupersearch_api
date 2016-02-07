<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;
use App\Passage;
use App\Search;
use DB;

class VerseStandard extends VerseAbstract {
    protected static $special_table = 'bible';
    
    /**  
     * Processes and executes the Bible search query
     * 
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, reporesenting the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public static function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) {
        $Verse = new static;
        $table = $Verse->getTable();
        $passage_query = $search_query = NULL;
        $is_special_search = ($Search && $Search->is_special) ? TRUE : FALSE;
        $Query = DB::table($table . ' AS tb')->select('id','book','chapter','verse','text');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');
        
        if($Passages) {
            $table = ($is_special_search) ? static::$special_table . '_1' : '';
            $passage_query = static::_buildPassageQuery($Passages, $table);
            
            if($passage_query && !$is_special_search) {
                $Query->whereRaw($passage_query);
            }
        }
        
        if($Search) {
            if($is_special_search) {
                $search_query = static::_buildSpecialSearchQuery($Search, $parameters, $passage_query);
            }
            else {                
                list($search_query, $binddata) = static::_buildSearchQuery($Search, $parameters);
                $Query->whereRaw($search_query, $binddata);
            }
        }
        
        //echo(PHP_EOL . $Query->toSql() . PHP_EOL);
        $verses = $Query->get();
        return $verses;
    }
    
    protected static function _buildPassageQuery($Passages, $table = '') {
        if(empty($Passages)) {
            return FALSE;
        }
        
        $query = array();
        $table_fmt = ($table) ? '`' . $table . '`.' : '';
        
        foreach($Passages as $Passage) {
            if(count($Passage->chapter_verse_normal)) {                
                foreach($Passage->chapter_verse_normal as $parsed) {
                    $q = $table_fmt . '`book` = ' . $Passage->Book->id;

                    // Single verses
                    if($parsed['type'] == 'single') {
                        $q .= ' AND ' . $table_fmt . '`chapter` = ' . $parsed['c'];
                        $q .= ($parsed['v']) ? ' AND ' . $table_fmt . '`verse` = ' . $parsed['v'] : '';
                    }
                    elseif($parsed['type'] == 'range') {
                        if(!$parsed['cst'] && !$parsed['cen']) {
                            continue;
                        }

                        $cvst = $parsed['cst'] * 1000 + intval($parsed['vst']);
                        $cven = $parsed['cen'] * 1000 + intval($parsed['ven']);
                        $q .= ' AND ' . $table_fmt . '`chapter_verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                    }

                    $query[] = $q;
                }
            }
            else {
                if($Passage->is_book_range) {
                    $query[] = '`book` BETWEEN ' . $Passage->Book->id . ' AND ' . $Passage->Book_En->id;
                }
                else {
                    $query[] = '`book` = ' . $Passage->Book->id;
                }
            }
        }
        
        return '(' . implode(') OR (', $query) . ')';
    }
    
    protected static function _buildSearchQuery($Search, $parameters) {
        if(empty($Search)) {
            return '';
        }

        return $Search->generateQuery();
    }
    
    protected static function _buildSpecialSearchQuery($Search, $parameters, $lookup_query = NULL) {
        $Verse = new static;
        $table = $Verse->getTable();
        $alias = static::$special_table;
        $Query = DB::table($table . ' AS ' . $alias . '_1')->select($alias . '_1.id AS id_1');
        
        list($Searches, $operators) = $Search->parseProximitySearch();
        $SubSearch1 = array_shift($Searches);
        $where = $SubSearch1->generateQuery($alias . '_1');
        
        foreach($Searches as $key => $SubSearch) {
            $key ++;
            $Query->addSelect($alias . '_' . $key . '.id AS id_' . $key);
            $full_alias = $alias . '_' . $key;
            $on_clause  = $SubSearch->generateQuery($full_alias);
            
        }

        
    }

    // Todo - prevent installation if already installed!
    public function install() {
        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE) ? TRUE : FALSE;

        Schema::create($this->table, function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->mediumInteger('chapter_verse')->unsigned();
            $table->text('text');
            $table->text('italics')->nullable();
            $table->text('strongs')->nullable();
            $table->index('book', 'ixb');
            $table->index('chapter', 'ixc');
            $table->index('verse', 'ixv');
            $table->index('chapter_verse', 'ixcv');
            $table->index(['book', 'chapter', 'verse'], 'ixbcv'); // Composite index on b, c, v
            //$table->index('text'); // Needs length - not supported in Laravel?
        });

        // If importing from V2, make sure v2 table exists
        if (env('IMPORT_FROM_V2', FALSE)) {
            $v2_table = 'bible_' . $this->Bible->module_v2;
            $res = DB::select("SHOW TABLES LIKE '" . $v2_table . "'");
            $v2_table_exists = (count($res)) ? TRUE : FALSE;
        }

        if (env('IMPORT_FROM_V2', FALSE) && $v2_table_exists) {
            if ($in_console) {
                echo(PHP_EOL . 'Importing Bible from V2: ' . $this->Bible->name . ' (' . $this->module . ')' . PHP_EOL);
            }

            // we use this to determine what the strongs / italics fileds are
            $v_test = DB::select("SELECT * FROM {$v2_table} ORDER BY `index` LIMIT 1");
            $strongs = $italics = 'NULL';

            $strongs = isset($v_test[0]->strongs) ? 'strongs' : $strongs;
            $italics = isset($v_test[0]->italics) ? 'italics' : $italics;
            $italics = isset($v_test[0]->map) ? 'map' : $italics;

            $prefix = DB::getTablePrefix();

            $sql = "
                INSERT INTO {$prefix}verses_{$this->module} (id, book, chapter, verse, chapter_verse, text, italics, strongs)
                SELECT `index`, book, chapter, verse, chapter * 1000 + verse, text, {$italics}, {$strongs}
                FROM {$v2_table}
            ";

            DB::insert($sql);
        } 
        else {
            // todo - import records from text file
        }

        return TRUE;
    }

    public function uninstall() {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }

        return TRUE;
    }
    
    /*
    protected static function _buildPassageQuery__OLD($Passages) {
        $query = array();
        
        foreach($Passages as $Passage) {
            foreach($Passage->chapter_verse_parsed as $parsed) {
                $q = '`book` = ' . $Passage->Book->id;
                
                // Single verses
                if($parsed['type'] == 'single') {
                    $q .= ' AND `chapter` = ' . $parsed['c'];
                    $q .= ($parsed['v']) ? ' AND `verse` = ' . $parsed['v'] : '';
                }
                elseif($parsed['type'] == 'range') {
                    if(!$parsed['cst'] && !$parsed['cen']) {
                        continue;
                    }
                    
                    $q .= ' AND (';
                    
                    // Intra-chapter ranges
                    if($parsed['cst'] == $parsed['cen']) {
                        $q .= '`chapter` =' . $parsed['cst'];
                        
                        if($parsed['vst'] && $parsed['ven']) {
                            $q .= ' AND `verse` BETWEEN ' . $parsed['vst'] . ' AND ' . $parsed['ven'];
                        }
                        else {
                            $q .= ($parsed['vst']) ? ' AND `verse` >= ' . $parsed['vst'] : '';
                            $q .= ($parsed['ven']) ? ' AND `verse` <= ' . $parsed['ven'] : '';
                        }
                    }
                    // Cross-chapter ranges
                    else {
                        $cvst = $parsed['cst'] * 1000 + intval($parsed['vst']);
                        $cven = $parsed['cen'] * 1000 + intval($parsed['ven']);
                        
                        if($parsed['vst'] && $parsed['ven']) {
                            $q .= '`chapter` * 1000 + `verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                        }
                        else {
                            $q .= ($parsed['vst']) ? '     `chapter` * 1000 + `verse` >= ' . $cvst : '     `chapter` >= ' . $parsed['cst'];
                            $q .= ($parsed['ven']) ? ' AND `chapter` * 1000 + `verse` <= ' . $cven : ' AND `chapter` <= ' . $parsed['cen'];
                        }
                    }
                    
                    $q .= ')';
                }
                
                $query[] = $q;
            }
        }
        
        return '(' . implode(') OR (', $query) . ')';
    }
     * 
     * 
     */
}
