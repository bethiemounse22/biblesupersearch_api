<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;

class HighlightTest extends TestCase {
    public function testNoHighlight() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for']);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertTrue((strpos($verse->text, '<' . $tag . '>') === FALSE), 'Should not have highlighting: ' . $verse->text);
        }
    }

    public function testBasic() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE]);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testCustomTag() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = 'span';
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'highlight_tag' => $tag]);

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testWholeWordsWildcard() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = 'em';
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Deut', 'search' => 'faith%', 'highlight' => TRUE, 'highlight_tag' => $tag, 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $verse = $results['kjv'][0];

        $this->assertEquals(5, $verse->book);
        $this->assertEquals(7, $verse->chapter);
        $this->assertEquals(9, $verse->verse);

        // Expected text: 'Know therefore that the LORD thy God, he is God, the <em>faith</em>ful..'
        $this->assertEquals(53, (strpos($verse->text, '<' . $tag . '>')), 'Highlight start tag incorrect: ' . $verse->text);
        $this->assertEquals(62, (strpos($verse->text, '</' . $tag . '>')), 'Highlight end tag incorrect: ' . $verse->text);
    }

    public function testWholeWords() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'whole_words' => TRUE, 'search_type' => 'or']);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }

        $this->assertTrue((strpos($results['kjv'][2]->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'Be should not be highlighted: ' . $verse->text);
    }

    public function testExactCase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'exact_case' => TRUE, 'search_type' => 'or']);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }

        $this->assertTrue((strpos($results['kjv'][2]->text, '<' . $tag . '>For</' . $tag . '>') === FALSE), 'For should not be highlighted: ' . $verse->text);
    }

    public function testProximity() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'search' => 'cometh by hearing', 'highlight' => TRUE, 'search_type' => 'proximity']);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testExactPhrase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'search' => 'cometh by hearing', 'highlight' => TRUE, 'search_type' => 'phrase']);
        $tag = env('DEFAULT_HIGHLIGHT_TAG', 'b');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }
}
