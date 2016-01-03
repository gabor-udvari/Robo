<?php

namespace Robo\Task\FileSystem;

use Webmozart\Glob\Glob;

class GlobAnt
{
    private $patterns = array();

    public function glob($input)
    {
        if (is_string($input)) {
            $this->patterns = array($input);
        } elseif (is_array($input)) {
            $this->patterns = $input;
        }

        foreach ($this->patterns as $p) {
            $p = getcwd().'/'.$p;
            $paths = Glob::glob($p);
        }

        return $paths;
    }

    public function match()
    {
    }

    public function filter()
    {
    }
}
