<?php

namespace PSFS\base\config;

use PSFS\base\Router;
use PSFS\base\types\Form;

/**
 * Class ConfigForm
 * @package PSFS\base\config
 */
class ConfigForm extends Form {

    /**
     * Constructor por defecto
     * @throws \PSFS\base\exception\FormException
     * @throws \PSFS\base\exception\RouterException
     */
    function __construct()
    {
        $this->setAction(Router::getInstance()->getRoute('admin-config'));
        //Añadimos los campos obligatorios
        foreach (Config::$required as $field)
        {
            $type = (in_array($field, Config::$encrypted)) ? "password" : "text";
            $value = (isset(Config::$defaults[$field])) ? Config::$defaults[$field] : null;
            $this->add($field, array(
                "label" => _($field),
                "class" => "col-md-6",
                "required" => true,
                "type" => $type,
                "value" => $value,
            ));
        }
        $this->add(Form::SEPARATOR);
        $data = Config::getInstance()->dumpConfig();
        if (!empty(Config::$optional) && !empty($data)) foreach (Config::$optional as $field)
        {
            if (array_key_exists($field, $data))
            {
                $this->add($field, array(
                    "label" => _($field),
                    "class" => "col-md-6",
                    "required" => false,
                    "value" => $data[$field],
                ));
            }
        }
        $extra = array();
        if (!empty($data)) $extra = array_diff(array_keys($data), array_merge(Config::$required, Config::$optional));
        if (!empty($extra)) foreach ($extra as $key => $field)
        {
            $this->add($key, array(
                "label" => $field,
                "class" => "col-md-6",
                "required" => false,
                "value" => $data[$field],
            ));
        }
        }
        $this->add(Form::SEPARATOR);
        //Aplicamos estilo al formulario
        $this->setAttrs(array(
            "class" => "form-horizontal",
        ));
        //Hidratamos el formulario
        $this->setData($data);
        //Añadimos las acciones del formulario
        $this->addButton('submit', _("Guardar configuración"), "submit", array(
                "class" => "btn-success col-md-offset-2"
            ))
            ->addButton('add_field', _('Añadir nuevo parámetro'), 'button', array(
                "onclick" => "javascript:addNewField(document.getElementById('". $this->getName() ."'));",
                "class" => "btn-warning",
            ));
    }

    /**
     * Nombre del formulario
     * @return string
     */
    public function getName() {
        return "config";
    }

    /**
     * Nombre del título del formulario
     * @return string
     */
    public function getTitle()
    {
        return "Parámetros necesarios para la ejecución de PSFS";
    }
}