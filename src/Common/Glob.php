<?php

namespace Robo\Common;

use Robo\Task\FileSystem\GlobAnt;

trait Glob
{
    protected function glob($type = 'ant')
    {
        switch ($type) {
            case 'native':
                $this->say('native');
                break;
            case 'ant':
            default:
                return new GlobAnt();
        }
    }
}
