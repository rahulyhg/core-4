<?php
namespace PSFS\services;

use PSFS\base\config\Config;
use PSFS\base\Security;
use PSFS\base\Service;
use Symfony\Component\Finder\Finder;

class AdminServices extends Service
{

    /**
     * @Injectable
     * @var \PSFS\base\config\Config Servicio de configuración
     */
    protected $config;
    /**
     * @Injectable
     * @var \PSFS\base\Security Servicio de autenticación
     */
    protected $security;
    /**
     * @Injectable
     * @var \PSFS\base\Template Servicio de gestión de plantillas
     */
    protected $tpl;

    /**
     * Servicio que devuelve las cabeceras de autenticación
     * @return string HTML
     */
    public function setAdminHeaders()
    {
        $platform = trim(Config::getInstance()->get('platform.name'));
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic Realm="' . $platform . '"');
        echo t('Zona restringida');
        exit();
    }

    /**
     * Servicio que devuelve los administradores de la plataforma
     * @return array|mixed
     */
    public function getAdmins()
    {
        $admins = $this->security->getAdmins();
        if (!empty($admins)) {
            if (!$this->security->checkAdmin()) {
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
        if (!empty($admins)) {
            foreach ($admins as &$admin) {
                if (isset($admin['profile'])) {
                    switch ($admin['profile']) {
                        case Security::MANAGER_ID_TOKEN:
                            $admin['class'] = 'warning';
                            break;
                        case Security::ADMIN_ID_TOKEN:
                            $admin['class'] = 'info';
                            break;
                        default:
                        case Security::USER_ID_TOKEN:
                            $admin['class'] = 'primary';
                            break;
                    }
                } else {
                    $admin['class'] = 'primary';
                }
            }
        }
    }

    /**
     * Servicio que lee los logs y los formatea para listarlos
     * @return array
     */
    public function getLogFiles()
    {
        $files = new Finder();
        $files->files()->in(LOG_DIR)->name('*.log')->sortByModifiedTime();
        $logs = array();
        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $size = $file->getSize() / 8 / 1024;
            $time = date('c', $file->getMTime());
            $dateTime = new \DateTime($time);
            if (!isset($logs[$dateTime->format('Y')])) {
                $logs[$dateTime->format('Y')] = [];
            }
            $logs[$dateTime->format('Y')][$dateTime->format('m')][$time] = [
                'filename' => $file->getFilename(),
                'size' => round($size, 3),
            ];
            krsort($logs[$dateTime->format('Y')][$dateTime->format('m')]);
            krsort($logs[$dateTime->format('Y')]);
        }
        return $logs;
    }

    /**
     * Servicio que parsea el fichero de log seleccionado
     * @param string|null $selectedLog
     *
     * @return array
     */
    public function formatLogFile($selectedLog)
    {
        $monthOpen = null;
        $files = new Finder();
        $files->files()->in(LOG_DIR)->name($selectedLog);
        $file = $match = null;
        $log = array();
        foreach($files as $item) {
            $file = $item;
            $match = $item;
            break;
        }
        /** @var \SplFileInfo $file */
        if (null !== $file) {
            $time = date('c', $file->getMTime());
            $dateTime = new \DateTime($time);
            $monthOpen = $dateTime->format('m');
            $content = file($file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename());
            krsort($content);
            $detailLog = array();
            foreach ($content as &$line) {
                list($line, $detail) = $this->parseLogLine($line, $match);
                $detailLog[] = array_merge([
                    'log' => $line,
                ], $detail);
                if (count($detailLog) >= 1000) {
                    break;
                }
            }
            unset($line);
            $log = $detailLog;
        }
        return [$log, $monthOpen];
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

                $detail = json_decode($match[0][0], TRUE);
            }
            if (empty($detail)) {
                $detail = array();
            }
            preg_match_all('/\>\ (.*):/', $line, $match);

            $type = isset($match[1][0]) ? $match[1][0] : '';
            $type = explode('.', $type);
            $type = count($type) > 1 ? $type[1] : $type[0];
            switch ($type) {
                case 'INFO':
                    $detail['type'] = 'success';
                    break;
                case 'DEBUG':
                    $detail['type'] = 'info';
                    break;
                case 'ERROR':
                    $detail['type'] = 'danger';
                    break;
                case 'WARN':
                    $detail['type'] = 'warning';
                    break;
            }

        } catch (\Exception $e) {
            $detail = [
                'type' => 'danger',
            ];
        }

        return [$line, $detail];
    }
}
