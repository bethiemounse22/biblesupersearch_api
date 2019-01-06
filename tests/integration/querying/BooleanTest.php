<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class BooleanTest extends TestCase
{
    public function testPhraseNoWholeword() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => ' (faith OR hope) love ', 'search_type' => 'boolean', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(31, $results['kjv']);
        $this->assertEquals(5, $results['kjv'][0]->book);
        $this->assertEquals(7, $results['kjv'][0]->chapter);
        $this->assertEquals(9, $results['kjv'][0]->verse);

        // TODO - THIS IS A BUG - FIX IT!
        // NOT WORKING
        // Manually running the query finds this vers
        // Doing a raw query finds it:  $verses = DB::select($Query->toSql(), $binddata);
        // Not sure what is going on with Laravel's query builder
        // WORKS if I query 'appearing "blessed hope"'
        // WORKAROUND: put phrases at end of query

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '"blessed hope" appearing', 'search_type' => 'boolean', 'whole_words' => FALSE]);
//        $this->assertFalse($Engine->hasErrors());
//        $this->assertCount(1, $results['kjv']);
//        $this->assertEquals(56, $results['kjv'][0]['book']);
//        $this->assertEquals(2,  $results['kjv'][0]['chapter']);
//        $this->assertEquals(13, $results['kjv'][0]['verse']);
    }
 }
