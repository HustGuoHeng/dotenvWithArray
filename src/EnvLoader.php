<?php 

namespace Envkit;

use Dotenv;

/**
 * This is the loaded class.
 *
 * It's responsible for loading variables by reading a file from disk and
 *
 * - based on the extension of the phpdotenv Loader
 * - added support for arrays
 */
class EnvLoader extends Dotenv\Loader
{
    /**
     * Resolve the nested variables.
     *
     * Look for {$varname} patterns in the variable value and replace with an
     * existing environment variable.
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function resolveNestedVariables($value)
    {

        if (strpos($value, '$') !== false) {
            $loader = $this;
            $value = preg_replace_callback(
                '/\${([a-zA-Z0-9_.\->]+)}/', //新增
                function ($matchedPatterns) use ($loader) {
                    $matchedPatterns[1] = str_replace('->', '.', $matchedPatterns[1]);
                    $matchedPatterns[1] = preg_replace('/\[(.+)\]/', '.$1',$matchedPatterns[1]);
                    $nestedVariable = $loader->getEnvironmentVariable($matchedPatterns[1]);
                    if ($nestedVariable === null) {
                        return $matchedPatterns[0];
                    } else {
                        return $nestedVariable;
                    }
                },
                $value
            );
        }

        return $value;
    }
    /**
     * Strips quotes and the optional leading "export " from the environment variable name.
     *
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected function sanitiseVariableName($name, $value)
    {
        $name = trim(str_replace(array('export ', '\'', '"'), '', $name));
        //增加对数组的支持
        $name = str_replace('->', '.', $name);
        $name = preg_replace('/\[(.+)\]/', '.$1',$name);

        return array($name, $value);
    }


    /**
     * Search the different places for environment variables and return first value found.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getEnvironmentVariable($name)
    {
        switch (true) {
            case array_key_exists($name, $_ENV):
                return $_ENV[$name];
            case array_key_exists($name, $_SERVER):
                return $_SERVER[$name];
            case $this->getEnvironmentVariableInArray($name, $_ENV):
                return $this->getEnvironmentVariableInArray($name, $_ENV);
            case $this->getEnvironmentVariableInArray($name, $_SERVER):
                return $this->getEnvironmentVariable($name, $_SERVER);
            default:
                //已经不通过setenv方法存储变量
                $value = getenv($name);
                return $value === false ? null : $value; // switch getenv default to null
        }

    }
    //新增，以读取数组的方式从环境变量中获取变量
    public function getEnvironmentVariableInArray($name, $array)
    {
        if (strpos($name, '.') === false) { return false;} // 这里仅仅对数组进行检查，如果不为数组，则说明在字符串检查中不存在
        $name_array = explode(".", $name);
        foreach ($name_array as $val) {
            if (is_array($array) && array_key_exists($val, $array)) {
                $array = $array[$val];
            } else {
                return null;
            }
        }
        return $array;
    }

    /**
     * Set an environment variable.
     *
     * This is done using:
     * - putenv,
     * - $_ENV,
     * - $_SERVER.
     *
     * The environment variable value is stripped of single and double quotes.
     *
     * @param string      $name
     * @param string|null $value
     *
     * @return void
     */
    public function setEnvironmentVariable($name, $value = null)
    {
        list($name, $value) = $this->normaliseEnvironmentVariable($name, $value);

        // Don't overwrite existing environment variables if we're immutable
        // Ruby's dotenv does this with `ENV[key] ||= value`.

        if ($this->immutable && $this->getEnvironmentVariable($name) !== null) { //对getEnvironmentVariable进行修改以便支持数组
            return;
        }

        //原有的设置环境变量的方法
        // If PHP is running as an Apache module and an existing
        // Apache environment variable exists, overwrite it
        // if (function_exists('apache_getenv') && function_exists('apache_setenv') && apache_getenv($name)) {
        //     apache_setenv($name, $value);
        // }

        // if (function_exists('putenv')) {
        //     putenv("$name=$value");
        // }

        // $_ENV[$name] = $value;
        // $_SERVER[$name] = $value;
        // end

        //新增的设置环境变量的方法，增加对数组的支持
        $this->setEnvironmentVariableWithArray($name, $value);
    }
   //新增
    public function setEnvironmentVariableWithArray($name, $value)
    {

        //支持a[]这种样式
        if (preg_match('/^(.*[^.]{1,})\[\]$/', $name, $matches)) {
            $remain = true;
            $name = $matches[1];
        }
        $name_array = explode(".", $name);

        $env_zz = &$_ENV;
        $server_zz = &$_SERVER;
        foreach ($name_array as $val) {
            if (!array_key_exists($val, $env_zz)) {
                $env_zz[$val] = array();
            }
            $env_zz = &$env_zz[$val];
            if (!array_key_exists($val, $server_zz)) {
                $server_zz[$val] = array();
            }
            $server_zz = &$server_zz[$val];
        }
        if (isset($remain) && !empty($remain)) {
            $server_zz[] = $value;
            $env_zz[] = $value;
        } else {
            $server_zz = $value;
            $env_zz = $value;
        }

    }

}