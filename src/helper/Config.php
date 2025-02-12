<?php

namespace Infira\pmg\helper;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

class Config
{
    protected array $config = [];

    public function __construct(string $yamlFile)
    {
        $this->config = array_merge($this->config, Yaml::parseFile($yamlFile));
    }

    protected function getPathArr(string $configPath): array
    {
        return explode('.', $configPath);
    }

    public function getAll(): array
    {
        return $this->config;
    }

    public function get(string $configPath, mixed $default = null)
    {
        if (!$this->exists($configPath)) {
            if (func_num_args() === 1) {
                throw new InvalidArgumentException("config path $configPath does not exist");
            }
            return $default;
        }
        $to = &$this->config;
        foreach ($this->getPathArr($configPath) as $p) {
            $to = &$to[$p];
        }

        return $to;
    }

    public function getOnEmpty(string $configPath, mixed $default = null)
    {
        $output = $this->get($configPath, $default);
        if (empty($output)) {
            return $default;
        }
        return $output;
    }

    public function exists(string $configPath): bool
    {
        $to = &$this->config;
        foreach ($this->getPathArr($configPath) as $p) {
            if (!array_key_exists($p, $to)) {
                return false;
            }
            $to = &$to[$p];
        }

        return true;
    }

    public function set(string $configPath, $value): void
    {
        $to = &$this->config;
        $pathArr = $this->getPathArr($configPath);
        $lastKey = array_key_last($pathArr);
        foreach ($pathArr as $key => $p) {
            if (!array_key_exists($p, $to)) {
                $to[$p] = [];
            }
            if ($key == $lastKey) {
                $to[$p] = $value;
                break;
            }
            $to = &$to[$p];
        }
    }

    public function add(string $configPath, $value): void
    {
        $to = &$this->config;
        $pathArr = $this->getPathArr($configPath);
        $lastKey = array_key_last($pathArr);
        foreach ($pathArr as $key => $p) {
            if (!array_key_exists($p, $to)) {
                $to[$p] = [];
            }
            if ($key == $lastKey) {
                $to[$p][] = $value;
                break;
            }
            $to = &$to[$p];
        }
    }
}