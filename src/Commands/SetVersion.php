<?php

namespace FYousri\APIVersioning\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputOption;

class SetVersion extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'api-version:set';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'set the app version.';

    /**
     * @var version
     */
    private $version;

    public function handle()
    {   
        // Get version from options               
        $this->version = $this->option('apiversion');

        if($module = $this->option('module')) {
            // Set the module version 
            DB::table('api_versions')->upsert(
                ['module' => $module, 'version' => $this->version, 'created_at' => now(), 'updated_at' => now()],
                'module',
                ['version', 'updated_at']
            );
        } else {
            // Set the app version
            DB::table('api_versions')->upsert(
                ['module' => 'app', 'version' => $this->version, 'created_at' => now(), 'updated_at' => now()],
                'module',
                ['version', 'updated_at']
            );
        }
     
        $this->info("Version set to {$this->version} successfully.");
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['module', null, InputOption::VALUE_OPTIONAL, 'Want a module version change?', null],
            ['apiversion', null, InputOption::VALUE_REQUIRED, 'What version should we set to?', null],
        ];
    }
}