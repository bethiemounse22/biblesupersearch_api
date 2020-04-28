<?php

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use ZipArchive;
use Illuminate\Http\UploadedFile;

/*
 * IRV (Indian Revised Version - Hindi) importer
 *
 * For future reference, this imports from 'USFM' format ... we may want a standardized importer for that
 *
 * Incoming markup / meta is as follows:
 *  [,] - italics
 *  <,> - red letter
 *  «,» - Chapter titles in Psalms
 *
 */

//[brackets] are for Italicized words
//
//<brackets> are for the Words of Christ in Red
//
//«brackets»  are for the Titles in the Book  of Psalms.

class Irv extends ImporterAbstract {
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = '<';
    protected $redletter_en = '>';
    protected $strongs_st   = NULL;
    protected $strongs_en   = NULL;
    protected $paragraph    = NULL;

    protected function _importHelper(Bible &$Bible) {
        ini_set("memory_limit", "500M");

        // Script settings
        $dir  = dirname(__FILE__) . '/../../bibles/misc/'; // directory of Bible files
        $file   = 'hin2017_usfm.zip';
        $zipfile = $dir . $file;
        $module = $this->module;

        // Where did you get this Bible?
        $source = "";

        $overwrite_existing         = $this->overwrite;

        $Bible    = $this->_getBible($module);
        $existing = $this->_existing;

        if(!$overwrite_existing && $this->_existing) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        $Zip = new ZipArchive;

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($Zip->open($zipfile) === TRUE) {

            // Not importing any metadata at this time!

            if($this->insert_into_bible_table) {
                $attr = $this->bible_attributes;
                // $attr['description'] = $desc . '<br /><br />' . $source;
                $Bible->fill($attr);
                $Bible->save();
            }

            $Bible->install(TRUE);

            for($i = 0; $i < $Zip->numFiles; $i++) {
                $filename = $Zip->getNameIndex($i);
                // var_dump($filename);
                $this->_zipImportHelper($Zip, $filename);
            }

            $Zip->close();
        }
        else {
            return $this->addError('Unable to open ' . $zipfile, 4);
        }

        $this->_insertVerses();
    }

    private function _zipImportHelper(&$Zip, $filename) {
        $pseudo_book = intval($filename);
        $chapter = $verse = NULL;

        if(!$pseudo_book) {
            return FALSE;
        }

        if($pseudo_book < 70) {
            $book = $pseudo_book - 1;
        }
        else {
            $book = $pseudo_book - 30;
        }

        $next_line_para = FALSE;
        $bib = $Zip->getFromName($filename);
        $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);

        foreach($bib as $line) {

            if(strpos($line, '\c') === 0) {
                preg_match('/([0-9]+)/', $line, $matches);
                $chapter = (int) $matches[1];
                continue;
            }            
            if(strpos($line, '\p') === 0) {
                $next_line_para = TRUE;
                continue;
            }

            if(strpos($line, '\v') === FALSE) {
                continue;
            }

            preg_match('/([0-9]+) (.+)/', $line, $matches);
            $verse = (int) $matches[1];
            $text  = $matches[2];

            if(preg_match('/[0-9]+:[0-9]+/', $text)) {
                $lpp = strrpos($text, '(');
                $text = substr($text, 0, $lpp);
            }

            if($next_line_para) {
                $text = '¶ ' . $text;
                $next_line_para = FALSE;
            }

            $text = str_replace('*', '', $text);
            $this->_addVerse($book, $chapter, $verse, $text, TRUE);
        }

        return TRUE;
    }

    public function checkUploadedFile(UploadedFile $File) {
        return TRUE;
    }
}
