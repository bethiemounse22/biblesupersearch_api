<?php

namespace App;
use App\Models\Bible;
use App\Models\Process;
use App\Models\Rendering;
use App\ProcessManager;

class RenderManager {
    use Traits\Error;

    static public $format_kinds = [
        'pdf'       => [
            'name'      => 'PDF',
            'desc'      => 'Ready-to-print PDF files',
            'formats'   => ['pdf_cpt_let', 'pdf_cpt_a4', 'pdf_cpt_let_ul', 'pdf_cpt_a4_ul'],
        ],
        'text'      => [
            'name'      => 'Plain Text',
            'desc'      => '',
            'formats'   => ['text', 'mr_text'],
        ],       
        'spreadsheet'      => [
            'name'      => 'Spreadsheet',
            'desc'      => 'Opens in MS Excel or other spreadsheet software.  Both human and machine readable.',
            'formats'   => ['csv'],
        ],
        'database' => [
            'name'      => 'Databases',
            'desc'      => 'Databases and database dumps.  Ready to import into your own software',
            'formats'   => ['csv', 'pdf'],
        ],
    ];

    static public $register = [
        'pdf'               => \App\Renderers\PdfCompact::class,
        'pdf_cpt_let'       => \App\Renderers\PdfCompact::class,
        'pdf_cpt_a4'        => \App\Renderers\PdfCompactA4::class,  
        'pdf_cpt_let_ul'    => \App\Renderers\PdfCompactUl::class,
        'pdf_cpt_a4_ul'     => \App\Renderers\PdfCompactUlA4::class,        
        'text'              => \App\Renderers\PlainText::class,
        'mr_text'           => \App\Renderers\MachineReadableText::class,
        'csv'               => \App\Renderers\Csv::class,
    ];

    protected $Bibles = [];
    protected $format = [];
    protected $modules = [];
    protected $zip = FALSE;
    protected $multi_bibles = FALSE;
    protected $multi_format = FALSE;
    protected $needs_process = FALSE;

    public function __construct($modules, $format, $zip = FALSE) {
        $this->multi_bibles = ($modules == 'ALL' || count($modules) > 1) ? TRUE : FALSE;
        $this->multi_format = ($format  == 'ALL' || count($format)  > 1) ? TRUE : FALSE;
        $this->zip = ($this->multi_bibles && $this->multi_format) ? TRUE : $zip;

        if($this->multi_bibles && $this->multi_format) {
            $this->addError('Cannot request multiple items for both Bible and format!');
            return;
        }

        if($format == 'ALL') {
            $this->format = array_keys(static::$register);
        }
        else {
            $format = (array) $format;

            foreach($format as $fm) {
                if(!array_key_exists($fm, static::$register)) {
                    $this->addError("Format {$fm} does not exist!");
                    continue;
                }

                $this->format[] = $fm;
            }
        }

        if($modules == 'ALL') {
            $this->_selectAllBibles();
        }
        else {
            $modules = (array) $modules;
            $this->modules = $modules;

            foreach($modules as $module) {
                $Bible = Bible::findByModule($module);

                if(!$Bible) {
                    $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
                    continue;
                }

                if(!$Bible->isDownloadable()) {
                    $this->addError( trans('errors.bible_no_download', ['module' => $module]) );
                    continue;
                }

                $this->Bibles[] = $Bible;
            }
        }
    }

    protected function _selectAllBibles() {
        $Bibles = Bible::where('enabled', 1) -> get() -> all();

        foreach($Bibles as $Bible) {
            if($Bible->isDownloadable()) {
                $this->Bibles[]  = $Bible;
                $this->modules[] = $Bible->module;
            }
        }

        if(empty($this->Bibles)) {
            $this->addError('No downloadable Bibles installed');
        }
    }

    public function separateProcessSupported() {
        return FALSE;
    }

    public function getBiblesNeedingRender($format = NULL, $overwrite = FALSE, $bypass_render_limit = FALSE, $limit_override = NULL) {
        $format = $format ?: $this->format[0];
        $CLASS = static::$register[$format];
        $limit = isset($limit_override) ? $limit_override : $CLASS::getRenderBiblesLimit();

        if($overwrite) {
            $Bibles_Needing_Render = $this->Bibles;
        }
        else {
            $Bibles_Needing_Render = [];

            foreach($this->Bibles as $Bible) {
                $Renderer = new $CLASS($Bible);

                if($Renderer->isRenderNeeded()) {
                    $Bibles_Needing_Render[] = $Bible;
                }
            }
        }

        if(!$bypass_render_limit && $limit !== TRUE && count($Bibles_Needing_Render) > $limit) {
            // create detatched process on 'php artisan queue:work --once ONLY' if jobs table is EMPTY
            // $this->_createDetatchedProcess($format, $Bibles_Needing_Render, $overwrite);
            $this->needs_process = TRUE;
            return $this->addError('The requested Bibles will take a while to render.  Please come back in an hour and try your download again.');
        }

        return $Bibles_Needing_Render;
    }

    public function render($overwrite = FALSE, $suppress_overwrite_error = TRUE, $bypass_render_limit = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        set_time_limit(0);
        
        if(count($this->modules) > 1 && !$this->cleanUpTempFiles()) {
            return FALSE;
        }

        $error_reporting_cache = error_reporting();
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        $this->needs_process = FALSE;

        foreach($this->format as $format) {
            $CLASS = static::$register[$format];
            $Bibles_Needing_Render = $this->getBiblesNeedingRender($format, $overwrite, $bypass_render_limit);

            if($Bibles_Needing_Render === FALSE) {
                return FALSE;
            }
            
            foreach($Bibles_Needing_Render as $Bible) {
                if(!static::isRenderWritable($format, $Bible->module)) {
                    $this->addError('Unable to render ' . $Bible->name . ', could not write to file.  Please contact system administrator');
                }

                $Renderer = new $CLASS($Bible);

                // if(!$Renderer->render($overwrite, $suppress_overwrite_error)) {
                if(!$Renderer->render(TRUE, $suppress_overwrite_error)) {
                    $this->addErrors($Renderer->getErrors(), $Renderer->getErrorLevel());
                }
            }
        }

        error_reporting($error_reporting_cache);
        return !$this->hasErrors();
    }

    public function download($bypass_render_limit = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        $rs = $this->render(FALSE, TRUE, $bypass_render_limit);

        if(!$rs) {
            return FALSE;
        }

        $download_file_path = NULL;
        $delete_file = FALSE;

        if($this->multi_bibles || $this->multi_format || $this->zip) {
            $date = new \DateTime();
            $zip_filename = 'truth_' . $date->format('Ymd_His_u') . '.zip';

            // Create Zip File in tmp dir
            // $dir = sys_get_temp_dir();
            $dir = Renderers\RenderAbstract::getRenderBasePath(); // . 'temp_zip/';
            $delete_file = TRUE;
            $zip_path = $dir . $zip_filename;
            $Zip = new \ZipArchive;

            if(!$Zip->open($zip_path, \ZipArchive::CREATE)) {
                return $this->addError('Unable to create ZIP file <tmppath>/' . $zip_filename);
            }

            // Copy all appropiate files into Zip file
            foreach($this->format as $format) {
                $CLASS = static::$register[$format];

                foreach($this->Bibles as $Bible) {
                    $Renderer = new $CLASS($Bible);
                    $filepath = $Renderer->getRenderFilePath();
                    $Zip->addFile($filepath, basename($filepath));
                    $Renderer->incrementHitCounter();
                }
            }

            $Zip->close();

            // Send Zip file to browser as download
            $download_file_path = $zip_path;
            $download_file_name = $zip_filename;
        }
        else {
            $format     = $this->format[0];
            $Bible      = $this->Bibles[0];
            $CLASS      = static::$register[$format];
            $Renderer   = new $CLASS($Bible);
            $Renderer->incrementHitCounter();
            $download_file_path = $Renderer->getRenderFilePath();
            $download_file_name = basename($download_file_path);

            // Send file to browser as download
        }

        if(file_exists($download_file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $download_file_name);
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($download_file_path));

            readfile($download_file_path);

            if($delete_file) {
                unlink($download_file_path);
            }

            exit;
        }
        else {
            return $this->addError('Unknown error - download file no longer exists');
        }
    }

    public function needsProcess() {
        return $this->needs_process;
    }

    public static function deleteAllFiles($dry_run = FALSE) {
        if($dry_run) {
            return TRUE;
        }

        $DeletableRenderings = Rendering::whereNotNull('rendered_at')->get();

        foreach ($DeletableRenderings as $DR) {
            $DR->deleteRenderedFile();
        }

        return TRUE;
    }

    /**
     * Deletes files as needed to make room for the current batch
     */
    public function cleanUpFiles($dry_run = FALSE) {
        $CLASS = $this->getRenderClass();
        $RendererId = $CLASS::getRendererId();
        $modules_has_file = $modules_no_file = [];
        $modules = $this->modules;

        foreach($this->Bibles as $Bible) {
            $Renderer = new $CLASS($Bible);

            if(file_exists($Renderer->getRenderFilePath())) {
                $modules_has_file[] = $Bible->module;
            }
            else {
                $modules_no_file[] = $Bible->module;
            }
        }

        $cur_space = static::getUsedSpace();
        $est_space = $this->getEstimatedSpace( count($modules_no_file) );
        $est_space_all = $this->getEstimatedSpace( count($modules) );
        $max_space = config('download.cache.cache_size') + config('download.cache.temp_cache_size');

        if($est_space_all > $max_space) {
            return $this->addError('Not enough space allocated to render all of the selected Bibles.  Please reduce the amount of Bibles selected.');
        }
        
        $total_space = $cur_space + $ext_space;

        if($total_space <= $max_space) {
            $needed_space = 0; // Because this operation doesn't need any space freed up
        }
        else {
            $needed_space = $total_space - $max_space;
        }

        // 'WHERE rendered_at IS NOT NULL AND (renderer != :renderer OR module NOT IN () )'; // WHERE needs to be in this form!
        $DeletableQuery = Rendering::whereNotNull('rendered_at') -> where( function($Query) use ($RendererId, $modules) {
            $Query  -> where('renderer', '!=', $RendererId)
                    -> orWhereNotIn('module', $modules);
        });

        static::_deletableQueryAddSort($DeletableQuery);

        $DeletableRenderings = $DeletableQuery->get();

        $success = static::_cleanUpFilesHelper($DeletableRenderings, $needed_space, $dry_run);

        if(!$success) {
            return $this->addError('Unknown error while cleaning up files, please contact system administrator');
        }

        return TRUE;
    }

    public static function cleanUpTempFiles($dry_run = FALSE) {
        $DeletableQuery = Rendering::whereNotNull('rendered_at');
        static::_deletableQueryAddSort($DeletableQuery);
        $Renderings = $DeletableQuery->get();
        return static::_cleanUpFilesHelper($Renderings, 0, $dry_run);
    }

    private static function _deletableQueryAddSort(&$DeletableQuery) {
        // Todo: Make this ordering a config?
        // This will need continued tweaking
        $DeletableQuery->orderBy('rendered_duration', 'desc');
        $DeletableQuery->orderBy('hits', 'asc');
        $DeletableQuery->orderBy('file_size', 'desc');
        $DeletableQuery->orderBy('custom', 'desc');
        $DeletableQuery->oldest('downloaded_at');
        // $DeletableQuery->oldest('rendered_at');
    }

    public static function _testCleanUpFiles($space_needed_render = 0, $verbose = FALSE, $debug_overrides = []) {
        $DeletableQuery = Rendering::whereNotNull('rendered_at');
        static::_deletableQueryAddSort($DeletableQuery);
        $Renderings = $DeletableQuery->get();
        return static::_cleanUpFilesHelper($Renderings, $space_needed_render, TRUE, $verbose, $debug_overrides);
    }

    private static function _cleanUpFilesHelper($DeletableRenderings, $space_needed_render = 0, $dry_run = FALSE, $verbose = FALSE, $debug_overrides = []) {
        $min_render_time    = config('download.cache.min_render_time') ?: FALSE;
        $min_hits           = config('download.cache.min_hits') ?: FALSE;
        $cache_size         = config('download.cache.cache_size') ?: FALSE;
        $temp_cache_size    = config('download.cache.temp_cache_size') ?: FALSE;
        $days               = config('download.cache.days') ?: FALSE;
        $max_filesize       = config('download.cache.max_filesize') ?: FALSE;
        $cur_space          = static::getUsedSpace();
        $comp_date          = NULL;

        if($dry_run) {
            if(is_array($debug_overrides)) {
                foreach($debug_overrides as $key => $value) {
                    if($verbose) {
                        print "\n$" . $key . ' = ' . $value;
                    }
                    $$key = $value;
                }
            }
        }

        $dry_run_verbose = ($dry_run && $verbose) ? TRUE : FALSE;
        $space_needed_render = ($space_needed_render < 0 ) ? 0 : $space_needed_render;
        $space_needed_cache = $space_needed_overall = $freed_space = $space_needed_extra = 0;
        $cache_size_max     = (int) $cache_size + (int) $temp_cache_size;

        if($space_needed_render > $cache_size_max) {
            return FALSE;
        }

        if($cur_space > $cache_size) {
            $space_needed_cache = $cur_space - $cache_size;

            if($space_needed_render > $temp_cache_size) {
                $space_needed_extra = $space_needed_render - $temp_cache_size;
                $space_needed_overall = $space_needed_cache + $space_needed_extra;
            }
            else {
                $space_needed_overall = $space_needed_cache;
            }
        }
        else {
            if($space_needed_render + $cur_space > $cache_size_max) {
                $space_needed_extra = $space_needed_render + $cur_space - $cache_size_max;
                // $space_needed_overall = $space_needed_extra + $space_needed_render;
                $space_needed_overall = $space_needed_extra;
            }
            else {
                // $space_needed_overall = $space_needed_render;
                $space_needed_overall = 0;
            }
        }

        if($days) {
            $comp_date = strtotime('23:59:59 -' . $days . ' days');
        }

        if($dry_run_verbose) {
            print "\nspace needed render: " . $space_needed_render;
            print "\nspace needed cache: " . $space_needed_cache;
            print "\nspace needed overall: " . $space_needed_overall;
            print "\ncomp date: " . date('Y-m-d H:i:s', $comp_date) . "\n\n";
        }

        foreach($DeletableRenderings as $key => $R) {
            $delete = FALSE;

            if($min_render_time && $R->rendered_duration < $min_render_time) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'min_render_time');
                $delete = TRUE;
            }           

            if($min_hits && $R->hits < $min_hits) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'min_hits');
                $delete = TRUE;
            }

            if($max_filesize && $R->file_size > $max_filesize) {
                static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'max_filesize');
                $delete = TRUE;
            }

            if($days) {
                $date = $R->downloaded_at ?: $R->rendered_at;
                $date_ts = strtotime($date);

                if($date_ts < $comp_date) {
                    $delete = TRUE;
                    static::_cleanUpDryRunMessage($dry_run_verbose, $R, 'days');
                }
            }

            if($delete) {
                $freed_space += $R->file_size;

                if(!$dry_run) {
                    $R->deleteRenderedFile();
                }

                unset($DeletableRenderings[$key]);
            }
        }

        if($space_needed_overall > $freed_space) {
            foreach($DeletableRenderings as $R) {
                $freed_space += $R->file_size;
                static::_cleanUpDryRunMessage($dry_run, $R, 'more_space_needed');
                
                if(!$dry_run) {
                    $R->deleteRenderedFile();
                }

                if($freed_space >= $space_needed_overall) {
                    break;
                }
            }
        }

        if($dry_run_verbose) {
            print "space in use: {$cur_space}\n\n";
            print "cache size max: {$cache_size_max}\n\n";
            print "space needed overall: {$space_needed_overall}\n\n";
            print "space freed: {$freed_space}\n\n";
        }

        if($dry_run) {
            return compact('cur_space', 'space_needed_overall', 'freed_space');
        }

        return ($space_needed_overall > $freed_space) ? FALSE : TRUE;
    }

    private static function _cleanUpDryRunMessage($dry_run, $Rendering, $config) {
        if($dry_run) {
            // print "Deleting {$Rendering->renderer}/{$Rendering->file_name} -> {$config} \n\n";
        }
    }

    public static function getUsedSpace() {
        return Rendering::whereNotNull('rendered_at')->sum('file_size');
    }

    /**
      * Returns the estimated space needed to render the selected Bible(s) in the selected format
      *
      */
    public function getEstimatedSpace($number_of_bibles = NULL) {
        $CLASS = $this->getRenderClass();
        $number_of_bibles = $number_of_bibles ?: count($this->Bibles);
        return $CLASS::$render_est_size * $number_of_bibles;
    }

    public function getRenderClass() {
        $format = $this->format[0];
        return static::$register[$format];
    }

    protected function _createDetatchedProcess($format, $Bibles_Needing_Render, $overwrite = FALSE) {
        $Pending = Process::where('status', 'pending')->where('form_action', 'download')->get()->all();

        $pending_bibles = $process_bibles = [];

        foreach($Pending as $Process) {
            $data = json_decode($Process->form_data);
            $pending_bibles = array_merge($pending_bibles, $data->bible);
        }

        foreach($Bibles_Needing_Render as $Bible) {
            if(!in_array($Bible->module, $pending_bibles)) {
                $process_bibles[] = $Bible->module;
            }
        }

        if(empty($process_bibles)) {
            return;
        }

        $cmd = 'php ' . $_SERVER['DOCUMENT_ROOT'] . '../artisan bible:render ' . $format . ' "' . implode(',', $process_bibles) . '"'; 

        if($overwrite) {
            $cmd .= ' --overwrite';
        }

        // $cmd .= ' > /dev/null 2>&1';
        // $cmd .= ' > /dev/null & ';
        // $cmd .= ' > /dev/null ';
        $cmd .= ' > ' . $_SERVER['DOCUMENT_ROOT'] . '../bibles/rendered/log_' . time() . '.txt';

        // Use Laravel queues???

        // See these options on php artisan queue:work
        //  --once
        //  --stop-when-empty

        // var_dump(getcwd());

        // die($cmd);

        exec($cmd);
        return TRUE;

        $handle = popen($cmd, 'r');

        echo "handle: '$handle'; " . gettype($handle) . "\n";
        $read = fread($handle, 2096);
        echo 'read' . $read;

        if(!is_resource($handle)) {
            return $this->addError('Unable to start render process');
        }

        pclose($handle);
        return TRUE;
    }

    static public function getRendererList() {
        $list = [];

        foreach(static::$register as $format => $CLASS) {
            $list[$format] = [
                'format' => $format,
                'name'   => $CLASS::getName(),
                'desc'   => $CLASS::getDescription(),
                'CLASS'  => $CLASS,
            ];
        }

        return $list;
    }    

    static public function getGroupedRendererList() {
        $list = [];

        foreach(static::$format_kinds as $kind => $info) {
            $list[$kind] = $info;
            $list[$kind]['renderers'] = [];

            foreach($info['formats'] as $format) {
                $CLASS = static::$register[$format];

                $list[$kind]['renderers'][$format] = [
                    'format' => $format,
                    'name'   => $CLASS::getName(),
                    'desc'   => $CLASS::getDescription(),
                    'CLASS'  => $CLASS,
                ];
            }
        }

        return $list;
    }

    static public function getRenderFilepath($format, $module) {
        $basedir = Renderers\RenderAbstract::getRenderBasePath();
        $CLASS = static::$register[$format];

        if(!$format || !$CLASS) {
            return FALSE;
        }

        $cc = explode('\\', $CLASS);
        $basename = array_pop($cc);

        return $basedir . $basename . '/' . $module;
    }

    static public function isRenderWritable($format = NULL, $module = NULL) {
        if($format && $module) {
            $pcs = explode('/', static::getRenderFilepath($format, $module));
            $file = array_pop($pcs);
            $dir = implode('/', $pcs);
        }
        else {
            $dir = Renderers\RenderAbstract::getRenderBasePath();
        }

        return is_writable($dir);
    }
}
