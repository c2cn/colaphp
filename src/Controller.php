<?php

namespace Cola;

abstract class Controller
{
    /**
     * Magic method
     *
     * @param string $methodName
     * @param array $args
     */
    public function __call($method, $args)
    {
        $cls = get_class($this);
        throw new \Exception("Call to undefined method: {$cls}->{$method}()");
    }

    /**
    * Get var
    *
    * @param string $key
    * @param mixed $default
    */
    protected function get($key = null, $default = null)
    {
        return Request::get($key, $default);
    }

    /**
    * Post var
    *
    * @param string $key
    * @param mixed $default
    */
    protected function post($key = null, $default = null)
    {
        return Request::post($key, $default);
    }

    /**
     * View
     *
     * @param array $file
     * @return Cola_View
     */
    protected function view($file = null)
    {
        empty($file) && $file = $this->defaultTemplate();
        return $this->view = new \Cola\View($file);
    }

    /**
     * Display the view
     *
     * @param string $tpl
     */
    protected function display($file = null)
    {
        empty($file) && $file = $this->defaultTemplate();
        $this->view->file = $file;
        $this->view->display();
    }

    /**
     * Get default template file path
     *
     * @return string
     */
    protected function defaultTemplate()
    {
        $di = App::getInstance()->dispatchInfo;
        $parts = explode('\\', $di['controller']);
        $controller = strtolower(substr(end($parts), 0, -10));
        $action = strtolower(substr($di['action'], 0, -6));

        $reflector = new \ReflectionClass(\get_class($this));
        $dir = dirname($reflector->getFileName());
        return "{$dir}/views/{$controller}.{$action}.php";
    }

    /**
     * Redirect to other url
     *
     * @param string $url
     */
    protected function redirect($url, $code = 302)
    {
        $this->response->redirect($url, $code);
    }

    /**
     * Abort
     *
     * @param mixed $data
     * @param string $callback callback function name
     *
     */
    protected function abort($data, $callback = null, $encode = 'utf-8')
    {
        is_string($data) || $data = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($callback && (preg_match('/^[a-zA-Z\d_]+$/', $callback))) {
            Cola_Response::charset($encode, 'application/javascript');
            echo "{$callback}({$data})";
        } else {
            Cola_Response::charset($encode, 'application/json');
            echo $data;
        }

        exit();
    }

    protected function error($code, $message = null, $ref = null)
    {
        $data = ['code' => $code];

        if (!is_null($message)) $data['message'] = $message;
        if (!is_null($ref)) $data['ref'] = $ref;

        $this->abort($data);
    }

    /**
     * Dynamic set vars
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value = null)
    {
        $this->$key = $value;
    }

    /**
     * Dynamic get vars
     *
     * @param string $key
     */
    public function __get($key)
    {
        switch ($key) {
            case 'get':
                return $_GET;

            case 'post':
                return $_POST;

            case 'view':
                return $this->view();

            case 'request':
                $this->request = new Cola_Request();
                return $this->request;

            case 'response':
                $this->response = new Cola_Response();
                return $this->response;

            case 'config':
                $this->config = Cola::getInstance()->config;
                return $this->config;

            default:
                throw new \Exception('Undefined property: ' . get_class($this) . '::' . $key);
        }
    }
}
