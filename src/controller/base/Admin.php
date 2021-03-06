<?php

namespace PSFS\controller\base;

use PSFS\base\config\Config;
use PSFS\base\config\LoginForm;
use PSFS\base\Template;
use PSFS\base\types\AuthAdminController;
use PSFS\controller\UserController;
use PSFS\services\AdminServices;

/**
 * Class Admin
 * @package PSFS\controller
 * @domain ROOT
 */
abstract class Admin extends AuthAdminController
{

    const DOMAIN = 'ROOT';

    /**
     * @Injectable
     * @var \PSFS\base\config\Config Configuration service
     */
    protected $config;
    /**
     * @Injectable
     * @var \PSFS\services\AdminServices Admin service
     */
    protected $srv;

    /**
     * Método estático de login de administrador
     * @param string $route
     * @return string HTML
     * @throws \PSFS\base\exception\FormException
     */
    public static function staticAdminLogon($route = null)
    {
        if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . 'admins.json')) {
            if ('login' !== Config::getParam('admin.login')) {
                return AdminServices::getInstance()->setAdminHeaders();
            } else {
                $form = new LoginForm();
                $form->setData(array("route" => $route));
                $form->build();
                $tpl = Template::getInstance();
                $tpl->setPublicZone(true);
                return $tpl->render("login.html.twig", array(
                    'form' => $form,
                ));
            }
        } else {
            return UserController::showAdminManager();
        }
    }

    /**
     * Método que gestiona el menú de administración
     * @GET
     * @route /admin
     * @visible false
     * @return string|null
     */
    public function index()
    {
        return $this->render("index.html.twig");
    }

}
