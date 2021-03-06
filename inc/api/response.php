<?php
/* Licensed under the Apache License, Version 2.0
 * See the LICENSE and NOTICE file for further information
 */

/**
 * Response class which handles outputting the header and body.
 *
 * Output buffering is used and the buffer is flushed only when calling
 * api_response::send().
 */
class api_response {
    /** Headers to send to the client. */
    protected $headers = array();
    /** Cookies to set. */
    protected $cookies = array();
    /** Content type to send to the client as header. */
    protected $contenttype = null;
    /** Character set of the response, sent together with the response type. */
    protected $charset = 'utf-8';
    /** HTTP response code sent to the client. */
    protected $code = null;
    /** Whether to specify the content length in the response header. This is
        off by default. If this is turned on, it will turn off HTTP chunked
        encoding. */
    protected $setContentLengthOutput = false;

    public $getDataCallback = null;

    protected $inputdata = null;

    protected $content = "";
    public $viewParams = array();

    protected $session;

    /**
     * Constructor. Turns on output buffering.
     */
    public function __construct($buffer = null) {
        $this->buffer = is_null($buffer) ? PHP_SAPI !== 'cli' : $buffer;
        if ($this->buffer) {
            ob_start();
        }
    }

    public function setSession($session) {
        $this->session = $session;
    }

    /**
     * Set a single header. Overwrites an existing header of the same
     * name if it exists.
     * @param $header string: Header name.
     * @param $value string: Value of the header.
     */
    public function setHeader($header, $value) {
        $this->headers[$header] = $value;
    }

    /**
     * Returns an associative array of all set headers.
     * @return hash: All headers which have been set.
     */
    public function getHeaders() {
        $headers = $this->headers;

        if (!is_null($this->contenttype)) {
            $ct = $this->contenttype;
            if (!is_null($this->charset)) {
                $ct .= '; charset=' . $this->charset;
            }
            $headers['Content-Type'] = $ct;
        }

        return $headers;
    }

    /**
     * Sets a cookie with the given value.
     * Overwrites an existing Cookie if it's the same name
     *
     * @param $name string: Name of the cookie
     * @param $value string: Value of the cookie
     * @param $expire int: Expiration timestamp of the cookie
     * @param $path string: Path where the cookie can be used
     * @param $domain: string: Domain which can read the cookie
     * @param $secure bool: Secure mode?
     * @param $httponly bool: Only allow HTTP usage?
     */
    public function setCookie($name, $value = '', $expire = 0, $path = '', $domain = '',
                              $secure = false, $httponly = true) {
        if (!empty($expire)) {
            $time = new DateTime();
            $expire = date('D, d-M-Y H:i:s \G\M\T', $expire - $time->getOffset());
        }
        $this->cookies[rawurlencode($name)] = rawurlencode($value)
                                              . (empty($domain) ? '' : '; Domain='.$domain)
                                              . (empty($expire) ? '' : '; expires='.$expire)
                                              . (empty($path) ? '' : '; Path='.$path)
                                              . (!$secure ? '' : '; Secure')
                                              . (!$httponly ? '' : '; HttpOnly');
    }

    /**
     * Returns an associative array of all set cookies.
     * @return hash: All Cookies which have been set.
     */
    public function getCookies() {
        return $this->cookies;
    }

    /**
     * Sets the content type of the current request. By default no
     * content type header is sent to the client.
     * @param $contenttype string: Content type to send.
     */
    public function setContentType($contenttype) {
        $this->contenttype = $contenttype;
    }

    /**
     * Sets the character set of the current request. The character
     * set is only used when content type has been set. The default
     * character set is utf-8 - set to null if you want to send
     * a Content-Type header without character set information.
     * @param $charset string: Character set to send.
     */
    public function setCharset($charset) {
        $this->charset = $charset;
    }

    /**
     * Sets the response code of the current request.
     * @param $code int: Response code to send.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html HTTP status codes
     */
    public function setCode($code) {
        $this->code = $code;
    }

    /**
     * Returns whether the content length will be specified in the response
     * header.
     */
    public function isContentLengthOutput() {
        return $this->setContentLengthOutput;
    }

    /**
     * Output the content length in the output.
     * @param $cl boolean: True if the content length should be set.
     */
    public function setContentLengthOutput($cl) {
        $this->setContentLengthOutput = $cl;
    }

    /**
     * Redirects the user to another location. The location can be
     * relative or absolute, but this methods always converts it into
     * an absolute location before sending it to the client.
     *
     * Calls the api_response::send() method to force output of all
     * headers set so far.
     *
     * @param $to string: Location to redirect to, if empty it redirects back to the referring page.
     * @param $status int: HTTP status code to set. Use one of the following: 301, 302, 303.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.2 HTTP status codes
     */
    public function redirect($to=null, $status=301) {
        if ($to === null) {
            if (isset($_SERVER['HTTP_REFERER'])) {
                $to = $_SERVER['HTTP_REFERER'];
            } else {
                $to = API_HOST . API_MOUNTPATH;
            }
        }

        // some browsers don't like SSL POST requests to be header-redirected to a non-ssl URL
        // triggering warnings to the user, so instead we do it with a html redirect
        if ($_SERVER['SERVER_PORT'] == 443 && $_SERVER['REQUEST_METHOD'] === 'POST' && substr($to, 0, 5) === 'http:') {
            echo '<script type="text/javascript">';
            echo 'window.location.href="'.$to.'";';
            echo '</script>';
            echo '<noscript>';
            echo '<meta http-equiv="refresh" content="0;url='.$to.'" />';
            echo '</noscript>';
        } else {
            $this->setCode($status);
            $this->setHeader('Location', $to);
        }
        $this->send();

        if ($this->session) {
            $this->session->commit();
        }
        die;
    }

    /**
     * Sends the status code, and all headers to the client. Then flushes
     * the output buffer and thus sends the content out.
     */
    public function send() {
        if (PHP_SAPI !== 'cli' || !headers_sent()) {
            if (!is_null($this->code)) {
                $this->sendStatus($this->code);
            }

            foreach ($this->getHeaders() as $header => $value) {
                header("$header: $value");
            }

            foreach ($this->getCookies() as $cookie => $value) {
                header("Set-Cookie: $cookie=$value", false);
            }

            if ($this->setContentLengthOutput && $this->buffer) {
                header("Content-Length: " . ob_get_length() + strlen($this->content));
            }
        }

        $this->getContent();
        if ($this->content) {
            print $this->content;
        }

        if ($this->session) {
            $this->session->commit();
        }

        if ($this->setContentLengthOutput) {
            die;
        }
    }

    /**
     * Send the status header line.
     * @param $code int: Response code to send.
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html HTTP status codes
     */
    protected function sendStatus($code) {
        header(' ', true, $code);
    }

    public function getInputData() {
        if ($this->inputdata) {
            return $this->inputdata;
        }
        //this makes it possible to register a method/function
        // which is called after the view prepare
        // eg. start an async curl request in the action
        // but get the result here in this method
        // Very needed, one of the initial goals of okapi
        if (is_callable($this->getDataCallback)) {
            return call_user_func($this->getDataCallback);
        }
    }

    public function setInputData($data) {
        $this->inputdata = $data;
    }

    public function setViewParam($key,$value) {
        $this->viewParams[$key] = $value;
    }

    public function getContent() {
        $content = '';
        if ($this->buffer) {
            $content .=  ob_get_clean();
            $this->buffer = false;
        }
        $this->content .= $content;
        return $this->content;

    }

    public function setContent($content) {
        //clear flush
        if ($this->buffer) {
            ob_end_clean();
            $this->buffer = false;
        }
        $this->content = $content;
    }

    public function addContent($content) {
        $this->content .= $content;
    }
}
