<?php

namespace FYousri\APIVersioning\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\InputOption;

class IncrementVersion extends Command 
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'api-version:increment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Increment the app version and add necessary files.';

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Meta information for the requested migration.
     *
     * @var array
     */
    protected $meta;

    /**
     * @var Composer
     */
    private $composer;


    /**
     * @var version
     */
    private $version;

    /**
     * Create a new command instance.
     *
     * @param Composer $composer
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
        $this->composer = app()['composer'];
    }
    
    public function handle()
    {
        $force = $this->option("force");

        $module = $this->option('module');

        // get and increment the module version    
        $moduleRecord = DB::table('api_versions')->where('module', $module)->first();
        $previousVersion= $moduleRecord ? $moduleRecord->version : 0;
        $this->version = $previousVersion + 1;
        
        if($module == 'app') {
            $this->paths = [
                'apiRoutesPath'                  => config('api-versioning.paths.routes') . '/v' . $this->version . '.php',
                'apiRoutesPreviousVersionPath'   => config('api-versioning.paths.routes') . '/v' . $previousVersion . '.php',
                'routeServiceProviderPath'       => config('api-versioning.paths.routeServiceProvider'),
                'requestsPath'                   => config('api-versioning.paths.requests') . '/v' . $this->version . '/APIRequest.php',
                'controllersPath'                => config('api-versioning.paths.controllers') . '/v' . $this->version . '/APIController.php',
                'transformersPath'               => config('api-versioning.paths.transformers') . '/v' . $this->version . '/APITransformer.php'
            ];
    

            if ($this->files->exists($this->paths['apiRoutesPath'])) {
                if (!($force or $this->confirm(trans("version_files_already_exist")))) {
                    $this->warn('command aborted with no changes.');
                    return 0;
                }
            }


        } else {
            // Increment and get the module version 

            $this->paths = [
                'apiRoutesPath'                  => module_path($this->option('module')) . config('api-versioning.module-paths.routes') . '/v' . $this->version . '.php',
                'apiRoutesPreviousVersionPath'   => module_path($this->option('module')) . config('api-versioning.module-paths.routes') . '/v' . $previousVersion . '.php',
                'routeServiceProviderPath'       => module_path($this->option('module')) . config('api-versioning.module-paths.routeServiceProvider'),
                'requestsPath'                   => module_path($this->option('module')) . config('api-versioning.module-paths.requests') . '/v' . $this->version . '/APIRequest.php',
                'controllersPath'                => module_path($this->option('module')) . config('api-versioning.module-paths.controllers') . '/v' . $this->version . '/APIController.php',
                'transformersPath'               => module_path($this->option('module')) . config('api-versioning.module-paths.transformers') . '/v' . $this->version . '/APITransformer.php'
            ];

            if ($this->files->exists($this->paths['apiRoutesPath'])) {
                if (!($force or $this->confirm(trans("version_files_already_exist")))) {
                    $this->warn('command aborted with no changes.');
                    return 0;
                }
            }
        }
        
        // update or insert the version into database
        DB::table('api_versions')->upsert(
            ['module' => $module, 'version' => $this->version, 'created_at' => now(), 'updated_at' => now()],
            'module',
            ['version', 'updated_at']
        );

        $this->files->put($this->paths['routeServiceProviderPath'] . '/RouteServiceProvider.php', $this->compileRouteServiceProviderStub());

        $this->makeDirectory($this->paths['apiRoutesPath']);

        $this->files->put($this->paths['apiRoutesPath'], $this->compileAPIRouteStub());
        
        $this->makeDirectory($this->paths['requestsPath']);
       
        $this->files->put($this->paths['requestsPath'], $this->compileRequestStub());
        
        $this->makeDirectory($this->paths['controllersPath']);
        
        $this->files->put($this->paths['controllersPath'], $this->compileControllerStub());
        
        $this->makeDirectory($this->paths['transformersPath']);

        $this->files->put($this->paths['transformersPath'], $this->compileTransformerStub());
     
        $this->info("Version {$this->version} files created successfully.");

        $this->composer->dumpAutoloads();
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Compile the API Route stub.
     *
     * @return string
     */
    protected function compileAPIRouteStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/api-route.stub');

        $this->replaceVersionNumber($stub)
            ->replaceFallbackRoutes($stub)
            ->replacePathFunction($stub)
            ->replaceNamespace($stub);

        return $stub;
    }

    /**
     * Compile the route service provider stub.
     *
     * @return string
     */
    protected function compileRouteServiceProviderStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/RouteServiceProvider.stub');

        $this->replaceVersionNumber($stub)
            ->replaceFallbackRoutes($stub)
            ->replacePathFunction($stub)
            ->replaceNamespace($stub);

        return $stub;
    }

    /**
     * Replace the version number in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceVersionNumber(&$stub)
    {
        $stub = str_replace('{{version}}', $this->version, $stub);

        return $this;
    }

    /**
     * Replace the fallback routes in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceFallbackRoutes(&$stub)
    {
        $previousVersion = $this->version - 1;
        if(!($this->version > 0 && File::exists($this->paths['apiRoutesPreviousVersionPath']))){
            $stub = str_replace('{{fallback}}', '', $stub);
            return $this;
        }
        
        if ($moduleName = $this->option('module') !== 'app') {
            $routePath = "/Routes/API/v{$previousVersion}.php";
            $fallback = "Route::name('v{$previousVersion}')->group(" . 'module_path(' . "'{$moduleName}', '{$routePath}'));";
            $stub = str_replace('{{fallback}}', $fallback, $stub);
            return $this;
        }

        $routePath = "routes/API/v{$previousVersion}.php";
        $fallback = "Route::name('v{$previousVersion}')->group(base_path('{$routePath}'));";
        $stub = str_replace('{{fallback}}', $fallback, $stub);

        return $this;
    }

    /**
     * Replace the file path function "base_path or module_path" in the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replacePathFunction(&$stub)
    {
    
        if ($moduleName = $this->option('module') !== 'app') {
            $routePath = "module_path('$moduleName', 'Routes/API/v{$this->version}.php')";
            $stub = str_replace('{{route_path}}', $routePath, $stub);
            return $this;
        }

        $routePath = "base_path('routes/API/v{$this->version}.php')";
        $stub = str_replace('{{route_path}}', $routePath, $stub);

        return $this;
    }

    protected function replaceNamespace(&$stub)
    {
        if ($moduleName = $this->option('module') !== 'app') {
            $requestNamespace       = "Modules\\{$moduleName}\\Http\Requests\\v{$this->version}";
            $controllerNamespace    = "Modules\\{$moduleName}\\Http\Controllers\\v{$this->version}";
            $transformerNamespace   = "Modules\\{$moduleName}\\Transformers\\v{$this->version}";
            $routeNamespace         = "Modules\\{$moduleName}\\Providers";
        } else {
            $requestNamespace       = "App\\Http\\Requests\\v{$this->version}";
            $controllerNamespace    = "App\\Http\\Controllers\\v{$this->version}";
            $transformerNamespace   = "App\\Transformers\\v{$this->version}";
            $routeNamespace         = "App\\Providers";
        }
        $stub = str_replace('{{transformer_namespace}}', $transformerNamespace, $stub);
        $stub = str_replace('{{controller_namespace}}', $controllerNamespace, $stub);
        $stub = str_replace('{{request_namespace}}', $requestNamespace, $stub);
        $stub = str_replace('{{route_provider_namespace}}', $routeNamespace, $stub);

        return $this;
    }

    protected function compileTransformerStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/transformer.stub');

        $this->replaceVersionNumber($stub)
            ->replaceFallbackRoutes($stub)
            ->replacePathFunction($stub)
            ->replaceNamespace($stub);

        return $stub;
    }

    protected function compileControllerStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/controller.stub');

        $this->replaceVersionNumber($stub)
            ->replaceFallbackRoutes($stub)
            ->replacePathFunction($stub)
            ->replaceNamespace($stub);

        return $stub;
    }

    protected function compileRequestStub()
    {
        $stub = $this->files->get(__DIR__ . '/../stubs/request.stub');

        $this->replaceVersionNumber($stub)
            ->replaceFallbackRoutes($stub)
            ->replacePathFunction($stub)
            ->replaceNamespace($stub);

        return $stub;
    }

        /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['module', null, InputOption::VALUE_OPTIONAL, 'Want a module version change?', 'app'],
            ['force', null, InputOption::VALUE_NONE, 'don\'t ask for permission'],
        ];
    }
}