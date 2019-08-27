<?php

namespace App\Renderers;

class PlainText extends RenderAbstract {
    static public $name = 'Plain Text';
    static public $description = 'Simple, plain text format';

    protected $file_extension = 'txt';
    protected $include_book_name = TRUE;


    protected $text = '';

    protected $handle;

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() {
        $filepath = $this->getRenderFilePath(TRUE);
        $this->handle = fopen($filepath, 'w');
        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        $text = $verse->book_name . ' ' . $verse->chapter . ':' . $verse->verse . ' '  . $verse->text . PHP_EOL;
        fwrite($this->handle, $text);
    }

    protected function _renderFinish() {
        fclose($this->handle);
        return TRUE;
    }


}
