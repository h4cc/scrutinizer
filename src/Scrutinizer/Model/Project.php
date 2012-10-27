<?php

namespace Scrutinizer\Model;

use Symfony\Component\Validator\Constraints\All;

use Scrutinizer\Util\PathUtils;

class Project
{
    private $files;
    private $config;

    public function __construct(array $files, array $config)
    {
        $this->files = $files;
        $this->config = $config;
    }

    public function getFile($path)
    {
        if ( ! isset($this->files[$path])) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $path));
        }

        return $this->files[$path];
    }

    public function hasFile($path)
    {
        return isset($this->files[$path]);
    }

    /**
     * Returns a path specific configuration setting, or the default if there
     * is no path-specific configuration for the file.
     *
     * @param File $file
     * @param string $configPath
     * @param string $default
     *
     * @return mixed
     */
    public function getPathConfig(File $file, $configPath, $default = null)
    {
        list($analyzerName, $segments) = $this->parseConfigPath($configPath);

        if ( ! isset($this->config[$analyzerName]['path_configs'])) {
            return $default;
        }

        $filePath = $file->getPath();
        foreach ($this->config[$analyzerName]['path_configs'] as $pathConfig) {
            if ( ! PathUtils::matches($filePath, $pathConfig['paths'])) {
                continue;
            }

            return $this->walkConfig($pathConfig['paths'], $segments, $configPath);
        }

        return $default;
    }

    /**
     * Returns a file-specific configuration setting.
     *
     * This method first looks whether there is a path-specific setting, and if not
     * falls back to fetch the value from the default configuration.
     *
     * @param File $file
     * @param string $configPath
     *
     * @return mixed
     */
    public function getFileConfig(File $file, $configPath)
    {
        list($analyzerName, $segments) = $this->parseConfigPath($configPath);

        if ( ! isset($this->config[$analyzerName]['config'])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" has no per-file configuration. Did you want to use getGlobalConfig() instead?', $analyzerName));
        }

        $pathConfig = null;
        if (isset($this->config[$analyzerName]['path_configs'])) {
            $filePath = $file->getPath();
            foreach ($this->config[$analyzerName]['path_configs'] as $pathConfig) {
                if ( ! PathUtils::matches($filePath, $pathConfig['paths'])) {
                    continue;
                }

                $config = $pathConfig['config'];
                break;
            }
        }

        return $this->walkConfig($pathConfig ?: $this->config[$analyzerName]['config'], $segments, $configPath);
    }

    /**
     * Returns a global configuration setting.
     *
     * @param string $configPath
     *
     * @return mixed
     */
    public function getGlobalConfig($configPath)
    {
        list($analyzerName, $segments) = $this->parseConfigPath($configPath);

        return $this->walkConfig($this->config[$analyzerName], $segments, $configPath);
    }

    public function getFiles()
    {
        return $this->files;
    }

    private function parseConfigPath($configPath)
    {
        $segments = explode(".", $configPath);
        $analyzerName = array_shift($segments);

        if ( ! isset($this->config[$analyzerName])) {
            throw new \InvalidArgumentException(sprintf('The analyzer "%s" does not exist.', $analyzerName));
        }

        return array($analyzerName, $segments);
    }

    private function matches(array $patterns, $path)
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function walkConfig($config, array $segments, $fullPath)
    {
        $walked = null;
        foreach ($segments as $segment) {
            $walked = $walked ? $segment : '.'.$segment;

            if ( ! array_key_exists($segment, $config)) {
                throw new \InvalidArgumentException(sprintf('There is no config at path "%s"; walked path: "%s".', $fullPath, $walked));
            }

            $config = $config[$segment];
        }

        return $config;
    }
}