<?php
    namespace PSFS\services;

    use PSFS\base\config\Config;
    use PSFS\base\Security;
    use PSFS\base\Singleton;
    use PSFS\base\Template;
    use PSFS\controller\Admin;
    use Symfony\Component\Finder\Finder;

    class AdminServices extends Singleton{

        private $config;
        private $security;
        private $tpl;

        public function __construct()
        {
            $this->config = Config::getInstance();
            $this->security = Security::getInstance();
            $this->tpl = Template::getInstance();
        }

        /**
         * Servicio que devuelve las cabeceras de autenticación
         * @return mixed
         */
        public function setAdminHeaders()
        {
            if("login" === $this->config->get("admin_login")) return Admin::getInstance()->adminLogin("admin-setup");
            $platform = trim(Config::getInstance()->get("platform_name"));
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Basic Realm="' . $platform. '"');
            echo _("Zona restringida");
            exit();
        }

        /**
         * Servicio que devuelve los administradores de la plataforma
         * @return array|mixed
         */
        public function getAdmins()
        {
            $admins = $this->security->getAdmins();
            if(!empty($admins))
            {
                if(!$this->security->checkAdmin())
                {
                    $this->setAdminHeaders();
                }
            }
            $this->parseAdmins($admins);
            return $admins;
        }

        /**
         * Servicio que parsea los administradores para mostrarlos en la gestión de usuarios
         * @param array $admins
         */
        private function parseAdmins(&$admins)
        {
            if(!empty($admins)) foreach($admins as &$admin)
            {
                if(isset($admin["profile"]))
                {
                    $admin["class"] = $admin["profile"] == sha1("admin") ? 'primary' : "warning";
                }else{
                    $admin["class"] = "primary";
                }
            }
        }

        /**
         * Método que revisa las traducciones directorio a directorio
         * @param $path
         * @param $locale
         * @return boolean
         */
        public static function findTranslations($path, $locale)
        {
            $locale_path = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
            $locale_path .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

            $translations = false;
            if(file_exists($path))
            {
                $d = dir($path);
                while(false !== ($dir = $d->read()))
                {
                    Config::createDir($locale_path);
                    if(!file_exists($locale_path . 'translations.po')) file_put_contents($locale_path . 'translations.po', '');
                    $inspect_path = realpath($path.DIRECTORY_SEPARATOR.$dir);
                    $cmd_php = "export PATH=\$PATH:/opt/local/bin; xgettext ". $inspect_path . DIRECTORY_SEPARATOR ."*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$locale_path}translations.po";
                    if(is_dir($path.DIRECTORY_SEPARATOR.$dir) && preg_match('/^\./',$dir) == 0)
                    {
                        $return = array();
                        echo "<li>" . _('Revisando directorio: ') . $inspect_path;
                        echo "<li>" . _('Comando ejecutado: '). $cmd_php;
                        shell_exec($cmd_php);// . " >> " . __DIR__ . DIRECTORY_SEPARATOR . "debug.log 2>&1");
                        usleep(10);
                        $translations = self::findTranslations($inspect_path, $locale);
                    }
                }
            }
            return $translations;
        }

        /**
         * Servicio que genera la estructura de un módulo o lo actualiza en caso de ser necesario
         * @param $module
         * @param $logger
         * @param $pb
         *
         * @return mixed
         */
        public function createStructureModule($module, $logger, $pb = null)
        {
            $mod_path = CORE_DIR . DIRECTORY_SEPARATOR;
            $module = ucfirst($module);
            $this->createModulePath($module, $mod_path);
            $this->createModulePathTree($module, $logger, $mod_path);
            $this->createModuleBaseFiles($module, $logger, $mod_path);
            $this->createModuleModels($module, $logger);
            //Redireccionamos al home definido
            $logger->infoLog("Módulo generado correctamente");
        }

        /**
         * Servicio que genera el path base del módulo
         * @param $module
         * @param $mod_path
         */
        private function createModulePath($module, $mod_path)
        {
            //Creamos el directorio base de los módulos
            Config::createDir($mod_path);
            //Creamos la carpeta del módulo
            Config::createDir($mod_path . $module);
        }

        /**
         * Servicio que genera la estructura base
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function createModulePathTree($module, $logger, $mod_path)
        {
            //Creamos las carpetas CORE del módulo
            $logger->infoLog("Generamos la estructura");
            $paths = array("Config", "Controller", "Form", "Models", "Public", "Templates", "Services", "Test");
            foreach($paths as $path) {
                Config::createDir($mod_path . $module . DIRECTORY_SEPARATOR . $path);
            }
            //Creamos las carpetas de los assets
            $htmlPaths = array("css", "js", "img", "media", "font");
            foreach($htmlPaths as $path) {
                Config::createDir($mod_path . $module . DIRECTORY_SEPARATOR . "Public" . DIRECTORY_SEPARATOR . $htmlPaths);
            }
        }

        /**
         * Servicio que genera las plantillas básicas de ficheros del módulo
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function createModuleBaseFiles($module, $logger, $mod_path)
        {
            $this->generateControllerTemplate($module, $logger, $mod_path);
            $this->genereateAutoloaderTemplate($module, $logger, $mod_path);
            $this->generateSchemaTemplate($module, $logger, $mod_path);
            $this->generatePropertiesTemplate($module, $logger, $mod_path);
            $this->generateIndexTemplate($module, $logger, $mod_path);
        }

        /**
         * Servicio que ejecuta Propel y genera el modelo de datos
         * @param $module
         * @param $logger
         */
        private function createModuleModels($module, $logger)
        {
            //Generamos las clases de propel y la configuración
            $exec = "export PATH=\$PATH:/opt/local/bin; " . BASE_DIR . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "propel ";
            $schemaOpt = " --schema-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config";
            $opt = " --config-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config --output-dir=" . CORE_DIR . " --verbose";
            $ret = shell_exec($exec . "build" . $opt . $schemaOpt);

            $logger->infoLog("Generamos clases invocando a propel:\n $ret");
            $ret = shell_exec($exec . "sql:build" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config" . $schemaOpt);
            $logger->infoLog("Generamos sql invocando a propel:\n $ret");
            $ret = shell_exec($exec . "config:convert" . $opt . " --output-dir=" . CORE_DIR . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . "Config");
            $logger->infoLog("Generamos configuración invocando a propel:\n $ret");
        }

        /**
         * Servicio que lee los logs y los formatea para listarlos
         * @return array
         */
        public function getLogFiles()
        {
            $files = new Finder();
            $files->files()->in(LOG_DIR)->name("*.log")->sortByModifiedTime();
            $logs = array();
            /** @var \SplFileInfo $file */
            foreach($files as $file)
            {
                $size = $file->getSize() / 8 / 1024;
                $time = date("c", $file->getMTime());
                $dateTime = new \DateTime($time);
                if(!isset($logs[$dateTime->format("Y")])) $logs[$dateTime->format("Y")] = array();
                $logs[$dateTime->format("Y")][$dateTime->format("m")][$time] = array(
                    "filename" => $file->getFilename(),
                    "size" => round($size, 3)
                );
                krsort($logs[$dateTime->format("Y")][$dateTime->format("m")]);
                krsort($logs[$dateTime->format("Y")]);
            }
            return $logs;
        }

        /**
         * Servicio que parsea el fichero de log seleccionado
         * @param $selectedLog
         *
         * @return array
         */
        public function formatLogFile($selectedLog)
        {
            $monthOpen = null;
            $files = new Finder();
            $files->files()->in(LOG_DIR)->name($selectedLog);
            $file = null;
            $log = array();
            foreach($files as $match)
            {
                $file = $match;
                break;
            }
            /** @var \SplFileInfo $file */
            if(!empty($file))
            {
                $time = date("c", $file->getMTime());
                $dateTime = new \DateTime($time);
                $monthOpen = $dateTime->format("m");
                $content = file($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
                krsort($content);
                $detailLog = array();
                foreach($content as &$line)
                {
                    list($line, $detail) = $this->parseLogLine($line, $match);
                    $detailLog[] = array_merge(array(
                        "log" => $line,
                    ), $detail);
                    if(count($detailLog) >= 1000) break;
                }
                $log = $detailLog;
            }
            return array($log, $monthOpen);
        }

        /**
         * Servicio que trata la línea del log para procesarle en el front end
         * @param $line
         * @param $match
         *
         * @return array
         */
        private function parseLogLine($line, $match)
        {
            $line = preg_replace(array('/^\[(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})\]/'), '<span class="label label-success">$4:$5:$6  $3-$2-$1</span>', $line);
            preg_match_all('/\{(.*)\}/', $line, $match);
            try {
                if (!empty($match[0])) {
                    $line = str_replace('[]', '', str_replace($match[0][0], '', $line));

                    return array($line, $detail);
                    $detail = json_decode($match[0][0], TRUE);

                    return array($line, $detail);
                }
                if (empty($detail)) $detail = array();
                preg_match_all('/\>\ (.*):/i', $line, $match);

                return array($line, $detail);
                $type = (isset($match[1][0])) ? $match[1][0] : '';
                switch ($type) {
                    case 'PSFS.DEBUG':
                        $detail["type"] = "info";
                        break;
                    case 'PSFS.ERROR':
                        $detail["type"] = "danger";
                        break;
                    case 'PSFS.WARN':
                        $detail["type"] = "warning";
                        break;
                }

                return array($line, $detail);
            } catch (\Exception $e) {
                $detail = array(
                    "type" => "danger",
                );

                return array($line, $detail);
            }

            return array($line, $detail);
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateControllerTemplate($module, $logger, $mod_path)
        {
//Generamos el controlador base
            $logger->infoLog("Generamos el controlador BASE");
            $controller = $this->tpl->dump("generator/controller.template.twig", array(
                "module" => $module,
            ));
            if (!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . "{$module}.php")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . "{$module}.php", $controller);
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function genereateAutoloaderTemplate($module, $logger, $mod_path)
        {
//Generamos el autoloader del módulo
            $logger->infoLog("Generamos el autoloader");
            $autoloader = $this->tpl->dump("generator/autoloader.template.twig", array(
                "module" => $module,
            ));
            if (!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "autoload.php")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "autoload.php", $autoloader);
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateSchemaTemplate($module, $logger, $mod_path)
        {
//Generamos el autoloader del módulo
            $logger->infoLog("Generamos el schema");
            $schema = $this->tpl->dump("generator/schema.propel.twig", array(
                "module" => $module,
                "db"     => $this->config->get("db_name"),
            ));
            if (!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "schema.xml", $schema);
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generatePropertiesTemplate($module, $logger, $mod_path)
        {
            $logger->infoLog("Generamos la configuración de Propel");
            $build_properties = $this->tpl->dump("generator/build.properties.twig", array(
                "module" => $module,
                "host"   => $this->config->get("db_host"),
                "port"   => $this->config->get("db_port"),
                "user"   => $this->config->get("db_user"),
                "pass"   => $this->config->get("db_password"),
                "db"     => $this->config->get("db_name"),
            ));
            if (!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Config" . DIRECTORY_SEPARATOR . "propel.yml", $build_properties);
        }

        /**
         * @param $module
         * @param $logger
         * @param $mod_path
         */
        private function generateIndexTemplate($module, $logger, $mod_path)
        {
//Generamos la plantilla de index
            $index = $this->tpl->dump("generator/index.template.twig");
            $logger->infoLog("Generamos una plantilla base por defecto");
            if (!file_exists($mod_path . $module . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig")) file_put_contents($mod_path . $module . DIRECTORY_SEPARATOR . "Templates" . DIRECTORY_SEPARATOR . "index.html.twig", $index);
        }
    }