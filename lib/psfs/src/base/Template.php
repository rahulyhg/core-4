<?php

namespace PSFS\Base;

use PSFS\base\Singleton;
use PSFS\config\Config;
use PSFS\Dispatcher;

class Template extends Singleton{

    protected $tpl;
    protected $filters = array();

    private $debug = false;

    function __construct()
    {
        $this->debug = Config::getInstance()->getDebugMode() ?: false;
        $loader = new \Twig_Loader_Filesystem(Config::getInstance()->getTemplatePath());
        $this->tpl = new \Twig_Environment($loader, array(
            'cache' => Config::getInstance()->getCachePath(),
            'debug' => $this->debug,
        ));
        //Asignamos las funciones especiales
        $this->addAssetFunction()
            ->addFormsFunction()
            ->addFormWidgetFunction();
    }

    /**
     * Método que procesa la plantilla
     * @param $tpl
     * @param array $vars
     * @return mixed
     */
    public function render($tpl, array $vars = array())
    {
        ob_start();
        if($this->debug)
        {
            $vars["__DEBUG__"]["includes"] = get_included_files();
            header('X-PSFS-DEBUG-TS: ' . Dispatcher::getInstance()->getTs() . ' s');
            header('X-PSFS-DEBUG-MEM: ' . Dispatcher::getInstance()->getMem('MBytes') . ' MBytes');
            header('X-PSFS-DEBUG-FILES: ' . count(get_included_files()) . ' files opened');
        }
        echo $this->dump($tpl, $vars);
        ob_flush();
        ob_end_clean();
        exit();
    }

    /**
     * Método que devuelve el contenido de una plantilla
     * @param $tpl
     * @param array $vars
     * @return string
     */
    public function dump($tpl, array $vars = array())
    {
        return $this->tpl->render($tpl, $vars);
    }

    /**
     * Funcion Twig para los assets en las plantillas
     * @return $this
     */
    private function addAssetFunction()
    {
        $function = new \Twig_SimpleFunction('asset', function($string){
            $file_path = "";
            if(file_exists(BASE_DIR . $string))
            {
                $base = BASE_DIR . "/html/";
                if(preg_match("/\.css$/i", $string))
                {
                    $file = "/". sha1($string) . ".css";
                    $html_base = "css";
                }elseif(preg_match("/\.js$/i", $string))
                {
                    $file = "/". sha1($string) . ".js";
                    $html_base = "js";
                }elseif(preg_match("/image/i", mime_content_type(BASE_DIR . $string)))
                {
                    $ext = explode(".", $string);
                    $file = "/". sha1($string) . "." . $ext[count($ext) - 1];
                    $html_base = "image";
                }
                $file_path = $html_base . $file;
                if(!file_exists($base . $html_base)) @mkdir($base . $html_base, 0775);
                if(!file_exists($base . $file_path) || filemtime($base . $file_path) < filemtime(BASE_DIR . $string))
                {
                    $data = file_get_contents(BASE_DIR . $string);
                    file_put_contents($base . $file_path, $data);
                }
            }
            return '/' . $file_path;
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Función que pinta un formulario
     * @return $this
     */
    private function addFormsFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('form', function(\PSFS\types\Form $form) use ($tpl) {
            return $tpl->display('forms/base.html.twig', array(
                'form' => $form,
            ));
        });
        $this->tpl->addFunction($function);
        return $this;
    }

    /**
     * Función que pinta un campo de un formulario
     * @return $this
     */
    private function addFormWidgetFunction()
    {
        $tpl = $this->tpl;
        $function = new \Twig_SimpleFunction('form_widget', function(array $field, string $label = null) use ($tpl) {
            if(!empty($label)) $field["label"] = $label;
            //Limpiamos los campos obligatorios
            if(!isset($field["required"])) $field["required"] = true;
            elseif(isset($field["required"]) && (bool)$field["required"] === false) unset($field["required"]);
            return $tpl->display('forms/field.html.twig', array(
                'field' => $field,
            ));
        });
        $this->tpl->addFunction($function);
        return $this;
    }
}