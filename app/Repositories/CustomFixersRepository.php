<?php

namespace App\Repositories;

use App\Project;

class CustomFixersRepository
{
    /** @var array<int, \PhpCsFixer\Fixer\FixerInterface> */
    protected array $resolved;

    /**
     * Create a new Custom Fixers Repository instance.
     *
     * @param  array<int, class-string>  $fixers
     * @return void
     */
    public function __construct(protected array $fixers, protected Project $project = new Project())
    {
        //
    }

    /**
     * Get the resolved custom fixers.
     *
     * @return array<int, \PhpCsFixer\Fixer\FixerInterface>
     */
    public function resolved()
    {
        return $this->resolved ??= $this->resolveCustomFixers($this->fixers);
    }

    /**
     * Resolves the custom fixers within the project context.
     *
     * @param  array<int, class-string>  $fixers
     * @return array<int, \PhpCsFixer\Fixer\FixerInterface>
     */
    protected function resolveCustomFixers(array $fixers)
    {
        // If there are no custom fixers, early return to prevent unneeded autoloader work.
        if (empty($fixers)) {
            return $fixers;
        }

        // Resolve the custom fixers within the project context.
        return $this->inProjectContext(
            fn () => array_map(fn ($fixer) => new $fixer(), $fixers)
        );
    }

    /**
     * Runs the given callback in the project context with the project autoloader.
     *
     * @return mixed
     */
    protected function inProjectContext(callable $callback)
    {
        $pintAutoloadPath = $this->getPintAutoloadPath();
        $projectAutoloadPath = $this->getProjectAutoloadPath();

        // If the project autoloader shouldn't be used, run the callback without it.
        if (! $this->shouldUseProjectAutoloader($pintAutoloadPath, $projectAutoloadPath)) {
            return $callback();
        }

        return $this->runWithProjectAutoloader($projectAutoloadPath, $callback);
    }

    /**
     * Determines if the project autoloader should be used.
     *
     * @return bool
     */
    protected function shouldUseProjectAutoloader(string $pintAutoloadPath, string $projectAutoloadPath)
    {
        // If the project doesn't have an autoloader to load, just run the callback
        // and return the result.
        if (! file_exists($projectAutoloadPath)) {
            return false;
        }

        // If the project is pint itself, the autoloader is already registered and
        // can't be included again. When run as a PHAR, the autoload paths will
        // not match, so the contents of the files are checked instead.
        if (file_exists($pintAutoloadPath) && md5_file($pintAutoloadPath) === md5_file($projectAutoloadPath)) {
            return false;
        }

        return true;
    }

    /**
     * Runs the given callback with the project autoloader.
     *
     * @return mixed
     */
    protected function runWithProjectAutoloader(string $projectAutoloadPath, callable $callback)
    {
        $projectAutoloader = require $projectAutoloadPath;

        $result = $callback();

        $projectAutoloader->unregister();

        return $result;
    }

    /**
     * Get the path to the pint autoloader.
     *
     * @return string
     */
    protected function getPintAutoloadPath()
    {
        return base_path().'/vendor/autoload.php';
    }

    /**
     * Get the path to the project autoloader.
     *
     * @return string
     */
    protected function getProjectAutoloadPath()
    {
        return $this->project::path().'/vendor/autoload.php';
    }
}
