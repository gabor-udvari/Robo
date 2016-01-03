<?php
namespace Robo\Task\Assets;

use Robo\Common\Glob;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Exception\TaskException;

/**
 * Minifies asset file (CSS or JS).
 *
 * ``` php
 * <?php
 * $this->taskMinify( 'web/assets/theme.css' )
 *      ->run()
 * ?>
 * ```
 * Please install additional dependencies to use:
 *
 * ```
 * "patchwork/jsqueeze": "~1.0",
 * "natxet/CssMin": "~3.0"
 * ```
 */
class Minify extends BaseTask
{
    use Glob;

    /** @var array $types */
    protected $types = ['css', 'js'];

    /** @var array $files */
    protected $files = [];

    /** @var string $text */
    protected $text;

    /** @var string $dst */
    protected $dst;

    /** @var string $type css|js */
    protected $type;

    /** @var array $squeezeOptions */
    protected $squeezeOptions = [
        'singleLine' => true,
        'keepImportantComments' => true,
        'specialVarRx' => false,
    ];

    /**
     * Constructor. Accepts asset file path or string source.
     *
     * @param bool|string $input
     */
    public function __construct($input)
    {
        if (is_array($input)) {
            // if input is array handle it as array of files
            $this->files = $input;

            return $this;
        } else {
            // if input is not an array try to glob it
            $files = $this->glob()->glob($input);
            if (is_array($files)) {
                // if the glob returned an array of files
                $this->files = $files;

                return $this;
            } else {
                // if the glob returned no files, handle the input as text
                $this->files = [null];

                return $this->fromText($input);
            }
        }
    }

    /**
     * Sets destination. Tries to guess type from it.
     *
     * @param string $dst
     *
     * @return $this
     */
    public function to($dst)
    {
        $this->dst = $dst;

        if (!empty($this->dst) && empty($this->type)) {
            $this->type($this->getExtension($this->dst));
        }

        return $this;
    }

    /**
     * Sets type with validation.
     *
     * @param string $type css|js
     *
     * @return $this
     */
    public function type($type)
    {
        $type = strtolower($type);

        if (in_array($type, $this->types)) {
            $this->type = $type;
        } else {
            throw new TaskException($this, sprintf('Unsupported extension "%s".', $type));
        }

        return $this;
    }

    /**
     * Sets text from string source.
     *
     * @param string $text
     *
     * @return $this
     */
    protected function fromText($text)
    {
        $this->text = (string)$text;
        unset($this->type);

        return $this;
    }

    /**
     * Sets text from asset file path. Tries to guess type.
     *
     * @param string $path
     *
     * @return $this
     */
    protected function fromFile($path)
    {
        $this->text = file_get_contents($path);
        unset($this->type);
        $this->type($this->getExtension($path));

        return $this;
    }

    /**
     * Sets the destination from asset file path.
     *
     * @param string $path
     *
     * @return $this
     */
    protected function setDestination($files)
    {
        // store the source and destination in key=>value pairs
        foreach ($files as $k => $v) {
            $from = $k;
            $to = $v;
            // check if target was given with the to() method instead of key/value pairs
            if (is_int($k)) {
                $from = $v;
                if (isset($this->dst)) {
                    $to = $this->dst;
                } else {
                    // target was not defined, make it the default one
                    $ext = $this->getExtension($from);
                    $ext_length = strlen($ext) + 1;
                    $to = substr($from, 0, -$ext_length).'.min.'.$ext;
                }
            }
            // if it is a directory, append the source filename
            if (is_dir($to)) {
                $to = $to.'/'.basename($from);
            }
            // store the new key and value
            $files[$from] = $to;
            // delete the old key
            unset($files[$k]);
        }
        // store the prepared array
        $this->files = $files;

        return $this;
    }

    /**
     * Gets file extension from path.
     *
     * @param string $path
     *
     * @return string
     */
    protected function getExtension($path)
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Minifies and returns text.
     *
     * @param string $text
     *
     * @return string|bool
     */
    protected function getMinifiedText($text)
    {
        switch ($this->type) {
            case 'css':
                if (!class_exists('\CssMin')) {
                    return Result::errorMissingPackage($this, 'CssMin', 'natxet/CssMin');
                }

                return \CssMin::minify($text);
                break;

            case 'js':
                if (!class_exists('\JSqueeze') && !class_exists('\Patchwork\JSqueeze')) {
                    return Result::errorMissingPackage($this, 'Patchwork\JSqueeze', 'patchwork/jsqueeze');
                }

                if (class_exists('\JSqueeze')) {
                    $jsqueeze = new \JSqueeze();
                } else {
                    $jsqueeze = new \Patchwork\JSqueeze();
                }

                return $jsqueeze->squeeze(
                    $text,
                    $this->squeezeOptions['singleLine'],
                    $this->squeezeOptions['keepImportantComments'],
                    $this->squeezeOptions['specialVarRx']
                );
                break;
        }

        return false;
    }

    /**
     * Single line option for the JS minimisation.
     *
     * @return $this;
     */
    public function singleLine($singleLine)
    {
        $this->squeezeOptions['singleLine'] = (bool)$singleLine;

        return $this;
    }

    /**
     * keepImportantComments option for the JS minimisation.
     *
     * @return $this;
     */
    public function keepImportantComments($keepImportantComments)
    {
        $this->squeezeOptions['keepImportantComments'] = (bool)$keepImportantComments;

        return $this;
    }

    /**
     * specialVarRx option for the JS minimisation.
     *
     * @return $this;
     */
    public function specialVarRx($specialVarRx)
    {
        $this->squeezeOptions['specialVarRx'] = (bool)$specialVarRx;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getMinifiedText($this->text);
    }

    /**
     * Writes minified result to destination.
     *
     * @return Result
     */
    public function run()
    {
        // process the files
        $this->setDestination($this->files);

        foreach ($this->files as $source => $dst) {
            // get text either already defined by fromText or fromFile
            if (isset($this->text)) {
                $text = $this->text;
            } else {
                $text = $this->fromFile($source);
            }

            // get type
            unset($this->type);
            $this->type($this->getExtension($source));

            $size_before = strlen($text);
            $minified = $this->getMinifiedText($text);

            if ($minified instanceof Result) {
                return $minified;
            } elseif (false === $minified) {
                return Result::error($this, 'Minification failed.');
            }

            $size_after = strlen($minified);
            $write_result = file_put_contents($dst.'.part', $minified);
            rename($dst.'.part', $dst);

            if (false === $write_result) {
                return Result::error($this, 'File write failed.');
            }
            if ($size_before === 0) {
                $minified_percent = 0;
            } else {
                $minified_percent = number_format(100 - ($size_after / $size_before * 100), 1);
            }
            $this->printTaskSuccess(
                sprintf(
                    'Wrote <info>%s</info>',
                    $dst
                )
            );
            $this->printTaskSuccess(
                sprintf(
                    'Wrote <info>%s</info> (reduced by <info>%s</info> / <info>%s%%</info>)',
                    $this->formatBytes($size_after),
                    $this->formatBytes(($size_before - $size_after)),
                    $minified_percent
                )
            );
        }

        return Result::success($this, 'Asset(s) minified.');
    }
}
