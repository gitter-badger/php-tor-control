<?php
/**
 * Created by PhpStorm.
 * User: ec
 * Date: 29.06.15
 * Time: 0:24
 * Project: php-tor-control
 * @author: Evgeny Pynykh bpteam22@gmail.com
 */

namespace bpteam\TorControl;

use bpteam\File\LocalFile;
use bpteam\PhpShell\PhpShell;

class Tor {
    /**
     * @var LocalFile
     */
    protected $file;
    protected $executorPath = '/etc/init.d/tor';
    /**
     * @var array https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
     */
    protected $ipCountries = array();
    //private $geoIpFile = '/usr/share/tor/geoip';
    CONST DATA_DIRECTORY = '/etc/tor';
    protected $authCode = '';
    CONST CONFIG_DIRECTORY = '/etc/tor';
    protected $host = '127.0.0.1';
    protected $port = '9050';
    protected $config;
    protected $configPattern = 'SocksListenAddress %s
SocksPort %d
PidFile %s/tor%d.pid
RunAsDaemon 1
DataDirectory %s/tor%d
ControlPort %d
ORPort %d
ORListenAddress %s:%d
Nickname tor%d
DirPort %d
DirListenAddress %s:%d';
    protected $geoIpPattern = 'ExitNodes {%s}';
    CONST KEY_PULL_START = 20000;
    CONST KEY_PULL_END = 29999;
    CONST INCREMENT_CONTROL_PORT = 10000;
    CONST INCREMENT_OR_PORT = 20000;
    CONST INCREMENT_DIR_PORT = 30000;
    protected $maxRepeatExecute = 25;
    protected $sleepOnExecute = 200000;

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @param string $host
     */
    public function setHost($host) {
        $this->host = $host;
    }

    /**
     * @return string|integer
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @param string $port
     */
    public function setPort($port) {
        if(preg_match('%^\d+$%',$port) && $port >= self::KEY_PULL_START && $port <= self::KEY_PULL_END && $this->isFreePort($port)){
            $this->port = $port;
            $this->file->open($this->getPortFileName($this->getPort()));
        }
    }

    public function getControlPort(){
        return $this->getPort() + self::INCREMENT_CONTROL_PORT;
    }

    public function getORPort(){
        return $this->getPort() + self::INCREMENT_OR_PORT;
    }

    public function getDirPort(){
        return $this->getPort() + self::INCREMENT_DIR_PORT;
    }

    /**
     * @param array $ipCountries
     */
    public function setIpCountries($ipCountries) {
        $this->ipCountries = is_array($ipCountries) ? $ipCountries : [$ipCountries];
    }

    /**
     * @return string
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * @return int
     */
    public function getSleepOnExecute() {
        return $this->sleepOnExecute;
    }

    /**
     * @param int $sleepOnExecute
     */
    public function setSleepOnExecute($sleepOnExecute) {
        $this->sleepOnExecute = $sleepOnExecute;
    }

    public function __construct($port = false){
        $this->file = new LocalFile();
        $this->executor = new PhpShell();
        $this->executor->setExecutor($this->executorPath);
        if(!$port){
            $this->searchFreePort();
        } else {
            $this->setPort($port);
        }
    }

    public function __destruct(){
        $this->stop();

    }

    public function getTorConnection(){
        return $this->host.':'.$this->port;
    }

    public function start(){
        if($this->createConfig()) {
            $this->executor->setArguments(['start', 'tor' . $this->getPort()]);
            $this->executor->exec();
        }
    }

    public function stop(){
        $this->executor->kill();
        $this->file->delDir(self::CONFIG_DIRECTORY.'/tor'.$this->getPort());
        $this->file->delete();
    }

    public function stopAll(){
        $executor = new PhpShell();
        $executor->parse('killall tor');
        $executor->exec();
    }

    public function restart(){
        $this->stop();
        $this->start();
    }

    public function status(){
        $this->executor->setArguments(['status', 'tor'.$this->getPort()]);
        $result = $this->executor->exec(true);
        return $result;
    }

    public function searchFreePort(){
        do {
            for ($port = rand(self::KEY_PULL_START, self::KEY_PULL_END); $port < self::KEY_PULL_END; $port++) {
                if($this->isFreePort($port)) {
                    $this->setPort($port);
                    return $port;
                }
            }
            echo "Free port not found, wait a minute\n";
        }while(sleep(60));
        return false;
    }

    public function changeNode(){
        $fp = fsockopen($this->host, $this->getControlPort(), $errorNumber, $errorString, 30);
        if (!$fp) return false;

        fputs($fp, "AUTHENTICATE $this->authCode\r\n");
        $response = fread($fp, 1024);
        $code = explode(' ', $response, 2);
        if ($code[0] != '250') {
            fclose($fp);
            return false;
        }

        fputs($fp, "signal NEWNYM\r\n");
        $response = fread($fp, 1024);
        $code = explode(' ', $response, 2);
        $result = $code[0] != '250';
        fclose($fp);

        return $result;
    }

    public function createConfig(){
        if($this->isFreePort($this->port)){
            if($this->file->lock()){
                $this->file->clear();
                $this->config = sprintf(
                    $this->configPattern,
                    $this->host,
                    $this->getPort(),
                    self::DATA_DIRECTORY,$this->getPort(),
                    self::DATA_DIRECTORY,$this->getPort(),
                    $this->getControlPort(),
                    $this->getORPort(),
                    $this->host,$this->getORPort(),
                    $this->getPort(),
                    $this->getDirPort(),
                    $this->host,$this->getDirPort()
                );
                if($this->ipCountries){
                    $this->config .= "\n" . sprintf(
                            $this->geoIpPattern,
                            implode('},{',$this->ipCountries)
                        );
                }
                return $this->file->write($this->config);
            }
        }
        return false;
    }

    public function isFreePort($port){
        return !file_exists($this->getPortFileName($port)) || $this->getPortFileName($port) == $this->file->getName();
    }

    public function isExist(){
        return (bool)preg_match('%is running%', $this->status());
    }

    public function getPortFileName($port){
        return self::CONFIG_DIRECTORY . '/tor' . $port.'.cfg';
    }

}