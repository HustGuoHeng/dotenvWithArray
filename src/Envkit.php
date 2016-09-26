<?php 

namespace Envkit;

use Dotenv;

/**
 * This is the Envkit class.
 *
 * It's responsible for loading a `.env` file in the given directory 
 * and setting the environment vars in `_ENC` and `_SERVER`
 *
 * Based on the extension of the phpdotenv Dotenv
 * and added support for arrays
 */

class Envkit extends Dotenv\Dotenv
{
    /**
     * Create a new envkit instance.
     *
     * @param string $path
     * @param string $file
     *
     * @return void
     */
    public function __construct($path, $file = '.env')
    {
        $this->filePath = $this->getFilePath($path, $file);
        $this->loader = new EnvLoader($this->filePath,  true);
    }

    /**
     * Actually load the data.
     *
     * @param bool $overload
     *
     * @return array
     */
    protected function loadData($overload = false)
    {
        $this->loader = new EnvLoader($this->filePath, !$overload);

        return $this->loader->load();
    }
}