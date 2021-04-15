<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

class AddPhoneHomeConfig extends Migration
{
    private $config_items = [
        [
            'key'       => 'app.phone_home',
            'descr'     => 'Allow Phoning Home',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],        
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ConfigManager::addConfigItems($this->config_items);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ConfigManager::removeConfigItems($this->config_items);
    }

}
