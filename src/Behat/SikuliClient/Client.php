<?php

namespace Behat\SikuliClient;

use Exception;

class Client
{

    public $connection = null;
    public $started = false;

    private $_browserId = null;
    private $_windowSize = null;
    private $_defaultWindowSize = null;
    private $_window = null;


    private $_debugging = true;

    /**
     * Temporary directory used during script execution.
     *
     * @var string
     */
    private $_tmpDir = null;


    /**
     * List of supported browsers.
     *
     * @var array
     */
    private $_supportedBrowsers = array(
        'firefox'        => 'Firefox',
        'firefoxNightly' => 'FirefoxNightly',
        'chrome'         => 'Google Chrome',
        'chromium'       => 'Chromium',
        'safari'         => 'Safari',
        'ie8'            => 'Internet Explorer 8',
        'ie9'            => 'Internet Explorer 9',
        'ie10'           => 'Internet Explorer 10',
        'ie11'           => 'Internet Explorer 11',
    );

    /**
     * Initialize Accessor.
     *
     * @param   Connection  $con    Sikuli Connection
     */
    public function __construct(Connection $con = null)
    {

        $this->_tmpDir = dirname(__FILE__).'/tmp';
        if (file_exists($this->_tmpDir) === FALSE) {
            mkdir($this->_tmpDir, 0777, TRUE);
        } else {
            // Remove temp files.
            $files = glob($this->_tmpDir.'/*.*');
            foreach ($files as $file) {
                if (is_file($file) === TRUE) {
                    unlink($file);
                }
            }
        }

        chmod($this->_tmpDir, 0777);

        if (null === $con) {
            $con = new Connection();
        }

        $this->connection = $con;
    }

    /**
     * Start Sikuli session.
     *
     * @param   string $browserName (firefox, ie, safari, chrome, opera)
     *
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function start($browserName = null)
    {
        echo('starting');
        if ($this->started) {
            throw new Exception('Client is already startedzzz');
        }

        $this->connection->connect();
        $this->setBrowser($browserName);

        $this->connection->setSetting('OcrTextSearch','True');
        $this->connection->setSetting('OcrTextRead','True');

        $this->started = true;
    }

    /**
     * Stop Sikuli session.
     */
    public function stop()
    {
        if (!$this->started) {
            throw new Exception('Client is not started');
        }
        $this->closeBrowser();
        $this->connection->disconnect();

        $this->started = false;
    }




    ////


    /**
     * Sets the browser to be used.
     *
     * @param string $browser A valid browser id (E.g. firefox).
     *
     * @return void
     * @throws Exception If the specified browser is not supported.
     */
    public function setBrowser($browser)
    {
        if (isset($this->_supportedBrowsers[$browser]) === FALSE) {
            throw new Exception('Browser is not supported');
        }

        $appName = $browser;
        if ($this->connection->getOS() === 'windows') {
            switch ($appName) {
                case 'chrome':
                case 'chromium':
                    $appName = 'Chrome';
                    break;

                case 'firefox':
                case 'firefoxNightly':
                    $appName = 'Firefox';
                    break;

                default:
                    if (strpos($appName, 'ie') === 0) {
                        $appName = 'iexplore';
                    }
                    break;
            }
        } else {
            $appName = $this->getBrowserName($browser);
        }

        $app = $this->switchApp($appName);
        if ($this->connection->getOS() !== 'windows') {
            $windowNum = 0;
            switch ($appName) {
                case 'Google Chrome':
                case 'Chromium':
                    $windowNum = 1;
                    break;

                default:
                    $windowNum = 0;
                    break;
            }

            $this->_window = $this->connection->callFunc(
                'window',
                array($windowNum),
                $app,
                TRUE
            );
        } else {
            $this->_window = $app;
        }

        $this->_browserId = $browser;

        $this->connection->addCacheVar($this->_window);

        // Resize the browser.
        $this->resize();

    }

    /**
     * Returns the name of the current browser.
     *
     * @param string $browserId Id of the browser.
     *
     * @return string
     */
    public function getBrowserName($browserId=NULL)
    {
        if ($browserId === NULL) {
            $browserId = $this->getBrowserid();
        }

        $browserName = $this->_supportedBrowsers[$browserId];

        if($this->connection->getOS() === 'linux'){
            $browserName = strtolower($browserName);
        }

        return $browserName;

    }

    /**
     * Returns the id of the browser.
     *
     * @return string
     */
    public function getBrowserid()
    {
        return $this->_browserId;

    }

    public function closeBrowser()
    {
        switch ($this->connection->getOS()) {
            case 'osx':
                // Shutdown browser.
                $this->connection->keyDown('Key.CMD + q');
                sleep(1);
                break;
            case 'windows':
                // Shutdown browser.
                $this->connection->keyDown('Key.ALT + Key.F4');
                break;
            default:
                // OS not supported.
                break;
        }
    }

    /**
     * Switches to the specifed application.
     *
     * @param string $name The name of the application to switch to.
     *
     * @return string
     */
    public function switchApp($name)
    {
        if ($this->connection->getOS() !== 'windows') {
            return $this->connection->switchApp($name);
        }

        $this->connection->keyDown('Key.WIN + r');
        $this->connection->keyDown('Key.DELETE');
        $this->connection->type($name.' about:blank');
        $this->connection->keyDown('Key.ENTER');
        sleep(2);
        return $this->connection->callFunc('App.focusedWindow', array(), NULL, TRUE);

    }

    /**
     * Resizes the browser window.
     *
     * @param integer $w The width of the window.
     * @param integer $h The height of the window.
     *
     * @return void
     */
    public function resize($w=NULL, $h=NULL)
    {
        if ($w === NULL || $h === NULL) {
            $size = $this->getDefaultWindowSize();
            if ($size === NULL) {
                return;
            }

            if ($w === NULL) {
                $w = $size['w'];
            }

            if ($h === NULL) {
                $h = $size['h'];
            }
        }

        if (is_array($this->_windowSize) === TRUE) {
            if ($this->_windowSize['w'] === $w && $this->_windowSize['h'] === $h) {
                return;
            }
        }

        $window = $this->getBrowserWindow();
        if ($this->connection->getW($window) === $w && $this->connection->getH($window) === $h) {
            return;
        }

        $bottomRight = $this->connection->getBottomRight($window);

        if ($this->connection->getOS() === 'windows') {
            $bottomRight = $this->connection->createLocation(
                ($this->connection->getX($bottomRight) - 3),
                ($this->connection->getY($bottomRight) - 3)
            );
        }

        $browserX = $this->connection->getX($window);
        $browserY = $this->connection->getY($window);
        $locX     = ($browserX + $w);
        $locY     = ($browserY + $h);

        $screenW = $this->connection->getW('SCREEN');
        $screenH = $this->connection->getH('SCREEN');

        if ($locX > $screenW) {
            $locX = ($screenW - 5);
        }

        if ($locY > $screenH) {
            $locY = ($screenH - 5);
        }

        $newLocation = $this->connection->createLocation($locX, $locY);

        $this->connection->dragDrop($bottomRight, $newLocation);

        // Update the window object.
        $this->connection->removeCacheVar($this->_window);
        $this->_window = $this->connection->createRegion($browserX, $browserY, $w, $h);
        $this->connection->addCacheVar($this->_window);

        $this->_windowSize = array(
            'w' => $w,
            'h' => $h,
        );

        // Set the default region of the find operations.
        $this->connection->setDefaultRegion($this->_window);

    }

    /**
     * Returns the default browser window size.
     *
     * @return array
     */
    public function getDefaultWindowSize()
    {
        return $this->_defaultWindowSize;

    }

    /**
     * Returns the region of the browser window.
     *
     * @return string
     */
    public function getBrowserWindow()
    {
        return $this->_window;

    }

    public function getConnection(){
        return $this->connection;
    }

    /**
     * Executes the specified JavaScript and returns its result.
     *
     * @param string  $js            The JavaScript to execute.
     * @param boolean $noReturnValue If TRUE then JS has no return value and NULL
     *                               will be returned to speed up execution.
     * @param boolean $raw           If TRUE content will not be modified.
     *
     * @return string
     * @throws Exception If there is a Selenium error.
     */
    public function execJS($js, $noReturnValue=FALSE, $raw=FALSE)
    {
        $this->debug('ExecJS: '.$js);

        clearstatcache();
        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsres.tmp');
        }

        file_put_contents($this->_tmpDir.'/jsexec.tmp', $js);
        chmod($this->_tmpDir.'/jsexec.tmp', 0777);

        $startTime = microtime(TRUE);
        $timeout   = 3;
        while (file_exists($this->_tmpDir.'/jsres.tmp') === FALSE) {
            if ((microtime(TRUE) - $startTime) > $timeout) {
                break;
            }

            usleep(50000);
        }

        $result = NULL;
        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            $result = file_get_contents($this->_tmpDir.'/jsres.tmp');
            unlink($this->_tmpDir.'/jsres.tmp');

            if ($result === 'undefined' || trim($result) === '') {
                return NULL;
            }

            $result = json_decode($result, TRUE);

            if (is_string($result) === TRUE && $raw !== TRUE) {
                $result = str_replace("\r\n", '\n', $result);
                $result = str_replace("\n", '\n', $result);
            }
        }

        return $result;

    }

    /**
     * Returns the HTML contents of the found element.
     *
     * @param string  $selector The jQuery selector to use for finding the element.
     * @param integer $index    The element index of the resulting array.
     *
     * @return string
     */
    public function getHTML($selector, $index=0)
    {
        $html = $this->execJS('$.find("'.$selector.'")['.$index.'].innerHTML');
        return $html;

    }


    /**
     * Stops the JS polling for commands.
     *
     * @return void
     */
    public function stopJSPolling()
    {
        $this->execJS('PHPSikuliBrowser.stopPolling()');

        if (file_exists($this->_tmpDir.'/jsres.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsres.tmp');
        }

        if (file_exists($this->_tmpDir.'/jsexec.tmp') === TRUE) {
            unlink($this->_tmpDir.'/jsexec.tmp');
        }

    }

    /**
     * Sets the browser URL to the specified URL.
     *
     * @param string $url The new URL.
     *
     * @return void
     */
    public function goToURL($url)
    {
        $this->stopJSPolling();
        $this->connection->keyDown('Key.CMD+l');
        $this->connection->type($url);
        $this->connection->keyDown('Key.ENTER');
        $this->connection->keyDown('Key.ENTER');
        sleep(5);

    }

    public function setProxy($proxyAddress, $proxyPort){
        switch ($this->connection->getOS()) {
            case 'osx':
                $this->connection->keyDown('Key.CMD + ,');
                $this->connection->click(dirname(__FILE__).'/../../../img/mac-os/firefox/preferences/advanced.png');
                $this->connection->click(dirname(__FILE__).'/../../../img/mac-os/firefox/preferences/advanced/network/settings.png');
                $this->connection->click(dirname(__FILE__).'/../../../img/mac-os/firefox/preferences/advanced/network/manual-proxy-configuration.png');
                $this->connection->type($proxyAddress);
                $this->connection->keyDown('Key.TAB');
                $this->connection->type($proxyPort);
                $this->connection->keyDown('Key.ENTER');
                $this->connection->keyDown('Key.ESC');
            break;
            case 'linux':
                // the only supported version of linux is currently Fedora
                $this->connection->keyDown('Key.ALT + Key.SHIFT + e');
                $this->connection->keyDown('n');
                $this->connection->click(dirname(__FILE__).'/../../../img/linux/firefox/preferences/advanced.png');
                $this->connection->click(dirname(__FILE__).'/../../../img/linux/firefox/preferences/advanced/network.png');
                $this->connection->click(dirname(__FILE__).'/../../../img/linux/firefox/preferences/advanced/network/settings.png');
                $this->connection->click(dirname(__FILE__).'/../../../img/linux/firefox/preferences/advanced/network/manual-proxy-configuration.png');
                $this->connection->keyDown('Key.TAB');
                $this->connection->type($proxyAddress);
                $this->connection->keyDown('Key.TAB');
                $this->connection->type($proxyPort);
                $this->connection->keyDown('Key.ENTER');
                $this->connection->keyDown('Key.ESC');
            break;
            case 'windows':
                throw new \Exception('not yet supported');
            break;
        }
    }

    /**
     * Prints debug output if debugging is enabled.
     *
     * @param string $content The content to print.
     *
     * @return void
     */
    public function debug($content)
    {
        if ($this->_debugging === TRUE && trim($content) !== '') {
            echo trim($content)."\n";
            @ob_flush();
        }

    }
}