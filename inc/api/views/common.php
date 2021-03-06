<?php
/* Licensed under the Apache License, Version 2.0
 * See the LICENSE and NOTICE file for further information
 */

/**
 * Abstract class to be extended by views
 *
 * @author   Silvan Zurbruegg
 */
abstract class api_views_common {
    /** @var api_response response object */
    protected $response;
    /** @var api_request request object */
    protected $request;
    /** @var api_routing_route matched route */
    protected $route = array();
    /** @var api_routing routing object */
    protected $routing;

    /**
     * Constructor.
     * @param $routing api_routing routing instance
     */
    public function __construct($routing, $request, $response, $i18n = null, $i18ntransform = true) {
        $this->route = $routing->getRoute();
        $this->routing = $routing;
        $this->request = $request;
        $this->response = $response;
        $this->i18n = $i18n;
        $this->i18ntransform = $i18ntransform;
    }

    /**
     * Prepare for dispatching
     *
     * Gets called before dispatch()
     * Useful for instantiation of DOM objects etc.
     */
    public function prepare() {
        return true;
    }

    /**
     * To be implemented by views for outputting response.
     * @param mixed $data
     */
    abstract function dispatch($data);

    /**
     * To be implemented by views for outputting response in case of an exception.
     * @param mixed $data
     */
    abstract function dispatchException($data);

    /**
     * TODO move into the api_views_xml class, and make xsl or default extend from that
     * Sends text/xml content type headers.
     *
     * @return   void
     */
    protected function setXMLHeaders() {
        $this->response->setContentType('text/xml');
        $this->response->setCharset('utf-8');
    }

    /**
     * Usable by views for setting specific headers
     * Should use the $this->response object to set headers.
     */
    protected function setHeaders() {
    }

    /**
     * Translates content in the given DOM using api_i18n.
     *
     * @param $lang string: Language to translate to.
     * @param $xmlDoc DOMDocument: DOM to translate.
     */
    protected function transformI18n($lang, $xmlDoc) {

        if ($this->i18ntransform === false) {
            return;
        }

        $this->i18n->i18n($lang, $xmlDoc);
    }

    /**
     * Returns a merged DOMDocument of the given data and exception list.
     *
     * Data can be any of these three things:
     *    - DOMDocument: Used directly
     *    - string: Treated as an XML string and loaded into a DOMDocument
     *    - array: Converted to a DOMDocument using api_helpers_xml::array2dom
     *
     * The exceptions are merged into the DOM using the method
     * api_views_default::mergeExceptions()
     *
     * @param $data mixed: See above
     * @param $exceptions array: Array of exceptions merged into the DOM.
     * @return DOMDocument: DOM with exceptions
     */
    protected function getDom($data, $exceptions) {
        $xmldom = null;

        // Use DOM or load XML from string or array.
        if ($data instanceof DOMDocument) {
            $xmldom = $data;
        } else if (is_string($data) && !empty($data)) {
            $xmldom = new DOMDocument();
            $xmldom->loadXML($data);
        } else if (is_array($data) || $data instanceof ArrayObject) {
            $xmldom = new DOMDocument();
            $xmldom->loadXML("<command/>");
            api_helpers_xml::array2dom($data, $xmldom, $xmldom->documentElement);
        }

        if (count($exceptions) > 0) {
             $this->mergeExceptions($xmldom, $exceptions);
        }

        return $xmldom;
    }

    /**
     * Merges exceptions into the DOM Document.
     * Appends a node \<exceptions> to the root node of the given DOM
     * document.
     *
     * @param $xmldom DOMDocument: Response DOM document.
     * @param $exceptions array: List of exceptions
    */
    protected function mergeExceptions(&$xmldom, $exceptions) {
        if (count($exceptions) == 0) {
            return;
        }

        $exceptionsNode = $xmldom->createElement('exceptions');
        foreach($exceptions as $exception) {
            $exceptionNode = $xmldom->createElement('exception');
            foreach($exception->getSummary() as $name => $value) {
                $child = $xmldom->createElement($name);
                $child->nodeValue = $value;
                $exceptionNode->appendChild($child);
            }
            $exceptionsNode->appendChild($exceptionNode);
        }

        $xmldom->documentElement->appendChild($exceptionsNode);
    }

    public function setResponse($response) {
        $this->response = $response;
    }
}
