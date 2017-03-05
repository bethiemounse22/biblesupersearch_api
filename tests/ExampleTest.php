<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExampleTest extends TestCase
{
    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testBasicExample()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        
        //$this->assertContains('Documentation', $response);
        //$response->assertContains('Documentation');
             //->see('Documentation');
    }
}
