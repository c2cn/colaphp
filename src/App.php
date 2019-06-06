<?php

namespace Cola;

defined('COLA_DIR') || define('COLA_DIR', dirname(__FILE__));
require_once COLA_DIR . '/Config.php';
require_once COLA_DIR . '/Container.php';

class App
{
    /**
     * Singleton instance
     *
     * Marked only as protected to allow extension of the class. To extend,
     * simply override {@link getInstance()}.
     *
     * @var App
     */
    protected static $_instance = null;

    /**
     * Run time config
     *
     * @var Config
     */
    public $config;

    /**
     * Object container
     *
     * @var Container
     */
    public $container;

    /**
     * Router
     *
     * @var Router
     */
    public $router;

    /**
     * Path info
     *
     * @var string
     */
    public $pathInfo;

    /**
     * Dispatch info
     *
     * @var array
     */
    public $dispatchInfo;

    /**
     * Constructor
     *
     */
    protected function __construct()
    {
        $this->config = new Config([
            '_class' => [
                'Cola\Controller'                 => COLA_DIR . '/Controller.php',
                'Cola\Model'                      => COLA_DIR . '/Model.php',
                'Cola\View'                       => COLA_DIR . '/View.php',
                'Cola\Router'                     => COLA_DIR . '/Router.php',
                'Cola\Exception\VisibleException' => COLA_DIR . '/Exception/VisibleException.php',
                'Cola\Http\Request'               => COLA_DIR . '/Http/Request.php',
                'Cola\Http\Response'              => COLA_DIR . '/Http/Response.php',
                'Cola\Db\Pdo'                     => COLA_DIR . '/Db/Pdo.php',
                'Cola\Cache\SimpleCache'          => COLA_DIR . '/Cache/SimpleCache.php',
                'Cola\Cache\Redis'                => COLA_DIR . '/Cache/Redis.php',
                'Cola\Validation\Validator'       => COLA_DIR . '/Validation/Validator.php',
            ],
        ]);
        $this->container = new Container();

        $this->registerAutoload([$this, 'loadClass']);
    }

    /**
     * Bootstrap
     *
     * @param mixed $config string as a file and array as config
     * @return App
     * @throws \Exception
     */
    public function boot($config = [])
    {
        if (is_string($config) && file_exists($config)) {
            include $config;
        }

        if (!is_array($config)) {
            throw new \Exception('Boot config must be an array or a php config file with variable $config');
        }

        $this->config->merge($config);
        return $this;
    }

    /**
     * Singleton instance
     *
     * @return App
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Load class
     *
     * @param string $class
     * @param string $file
     * @return boolean
     */
    public function loadClass($class, $file = '')
    {
        if (class_exists($class, false) || interface_exists($class, false)) {
            return true;
        }

        if (!$file) {
            $file = $this->config->get("_class.{$class}");
        }

        if (file_exists($file)) {
            include $file;
        }

        return (class_exists($class, false) || interface_exists($class, false)) || $this->psr4($class);
    }

    /**
     * User define class file
     *
     * @param array $class
     * @param string $file
     * @return App
     */
    public function setClassFile($class, $file = '')
    {
        if (!is_array($class)) {
            $class = array($class => $file);
        }

        $this->config->merge(array('_class' => $class));

        return $this;
    }

    /**
     * psr-4 autoload
     * @param string $class
     * @return boolean
     */
    public function psr4($class)
    {
        $prefix = $class;
        $psr4 = $this->config->get('_psr4');
        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos);
            $rest = substr($class, $pos + 1);
            if (empty($psr4[$prefix])) continue;
            $file = $psr4[$prefix] . DIRECTORY_SEPARATOR
                  . str_replace('\\', DIRECTORY_SEPARATOR, $rest)
                  . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        return false;
    }

    /**
     * Add psr-4 namespace
     * @param $prefix
     * @param $base
     * @return App
     */
    public function addNamespace($prefix, $base)
    {
        $prefix = trim($prefix, '\\') . '\\';
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $key = "_psr4.{$prefix}";
        $this->config->set($key, $base);
        return $this;
    }

    /**
     * Register autoload function
     *
     * @param string $func
     * @return App
     */
    public function registerAutoload($func, $enable = true)
    {
        spl_autoload_register($func);
        return $this;
    }

    /**
     * Unregister autoload function
     *
     * @param string $func
     * @return App
     */
    public function unregisterAutoload($func)
    {
        spl_autoload_unregister($func);
        return $this;
    }

    public function initDispatchInfo()
    {
        $this->router || ($this->router = new Router($this->config->get('_router', [])));
        $this->pathInfo || ($this->pathInfo = $_SERVER['PATH_INFO']);

        $this->dispatchInfo = $this->router->match($this->pathInfo);

        return $this;
    }

    public function dispatch()
    {
        empty($this->dispatchInfo) && $this->initDispatchInfo();

        $controller = new $this->dispatchInfo['controller'];

        call_user_func_array([$controller, $this->dispatchInfo['action']], $this->dispatchInfo['args']);
    }
}