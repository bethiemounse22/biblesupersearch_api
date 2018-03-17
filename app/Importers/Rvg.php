<?php

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??

// New RVG importer
//
//Markup is as follows:
//
//[brackets] are for Italicized words
//
//<brackets> are for the Words of Christ in Red
//
//«brackets»  are for the Titles in the Book  of Psalms.

class Rvg extends ImporterAbstract {
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    public function import() {
        ini_set("memory_limit", "500M");

        // Script settings
        $dir  = dirname(__FILE__) . '/../../bibles/misc/'; // directory of Bible files
        $file   = 'RVG20180201.txt';
        $path   = $dir . $file;
        $module = $this->module;

        // Where did you get this Bible?
        $source = "";

        $insert_into_bible_table    = TRUE; // Inserts (or updates) the record in the Bible versions table
        $overwrite_existing         = $this->overwrite;

        $Bible    = Bible::findByModule($module);
        $existing = ($Bible) ? TRUE   : FALSE;
        $Bible    = ($Bible) ? $Bible : new Bible;

        if(!$overwrite_existing && $existing) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($existing) {
            $Bible->uninstall();
        }

        $contents = file($path);

        if(!$contents) {
            return $this->addError('Unable to open ' . $file, 4);
        }

        if(count($contents) != 31102) {
            return $this->addError('Doesnt have 31102 lines');
        }

        if($insert_into_bible_table) {
            $attr = $this->bible_attributes;
//            $attr['description'] = $desc . '<br /><br />' . $source;
            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $map = DB::table('verses_kjv')->select('id', 'book', 'chapter', 'verse')->get();

        if(count($map) != 31102) {
            return $this->addError('KJV Map Doesnt have 31102 lines');
        }

        foreach($contents as $key => $text) {
            $mapped = $map[$key];

            // <> indicate red letter. Removing for now as it will screw up display in HTML
            $text = str_replace(array('<', '>'), '', $text);

            $binddata = array(
                'book'             => $mapped->book,
                'chapter'          => $mapped->chapter,
                'verse'            => $mapped->verse,
                'chapter_verse'    => $mapped->chapter * 1000 + $mapped->verse,
                'text'             => $text,
            );

            //$Verses->forceCreate($binddata);
            DB::table($table)->insert($binddata);
        }

    }
}