<?php

namespace App\Models\Verses;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bible;

// Verses models should only be instantiated from within a Bible model instance
// Abstraction allows for the potential for Bibles other than the 'standard' format
// However, actual support for non-standard formats won't be implemented any time soon.

abstract class VerseAbstract extends Model {

    protected $Bible;
    protected $module; // Module name
    protected $hasClass = TRUE; // Indicates if this instantiation has it's own coded extension of this class.
    public $timestamps = FALSE;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
    */
    public function __construct(array $attributes = []) {
        if (empty($this->module)) {
            $class = explode('\\', get_called_class());
            $this->module = strtolower(array_pop($class));
        }
        
        $this->table = self::getTableByModule($this->module);
        parent::__construct($attributes);
    }
    
    public function setBible(Bible &$Bible) {
        $this->Bible = $Bible;
    }

    public function classFileExists() {
        return $this->hasClass;
    }

    abstract public function install();
    abstract public function uninstall();
    
    /**
     * Gets the table name by the module
     * @param string $module
     * @return string $table_name
     */
    public static function getTableByModule($module) {
        return 'verses_' . $module;
    }
}
