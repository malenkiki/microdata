<?php
/*
Copyright (c) 2014 Michel Petit <petit.michel@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Malenki;

/**
 * Parse HTML to get microdata elements.
 *
 * Can use either URL or document content:
 *
 *     $md = new Microdata($url); // Load content from URL
 *     $md = new Microdata($url, Microdata::AS_URL); // Same as previous
 *     $md = new Microdata($content, Microdata::AS_STRING); // Load from content
 *
 * You get microdata tree by calling `extract()` method:
 *
 *     $md = new Microdata($url);
 *     var_dump($md->extract());
 *
 * You can get microdata tree as JSON very quicky by using object into string context:
 *
 *     $md = new Microdata($url);
 *     echo $md; // JSON
 *
 * You can get statistical data about amount of types found:
 *
 *     $md = new Microdata($url);
 *     var_dump($md->getAllTypeCount());
 *
 *
 * This work is greatly taken from the work of [Philip Jägenstedt](http://gitorious.org/microdatajs/microdatajs) and [Lin Clark](http://github.com/linclark/MicrodataPHP).
 *
 * @author Michel Petit <petit.michel@gmail.com>
 * @license MIT
 */
class Microdata extends \DOMElement
{
    const AS_URL = 1;
    const AS_STRING = 2;

    /**
     * DOM object.
     *
     * @var mixed
     * @access protected
     */
    protected $dom = null;

    /**
     * Found charset name.
     *
     * @var string
     * @access protected
     */
    protected $found_charset = null;

    /**
     * Content is extracted?
     *
     * @var boolean
     * @access protected
     */
    protected $extracted_content = false;

    /**
     * Do it must include checking information?
     *
     * @var boolean
     * @access protected
     */
    protected $must_check = false;

    /**
     * URI for a JSON schema to use for checking.
     *
     * @var string
     * @access protected
     */
    protected $str_schema = null;

    /**
     * Schema object for checking document.
     *
     * @var \stdClass
     * @access protected
     */
    protected $schema = null;

    /**
     * Array as key/value for some statistical data.
     *
     * @var array
     * @access protected
     */
    protected $arr_stats = array();

    /**
     * Helper to split content into array or string if one element is returned.
     *
     * @param  string  $str      The string to split
     * @param  boolean $as_array If true, always returns array.
     * @static
     * @access protected
     * @return mixed   An array if get more than one elements else it is a string
     */
    protected static function split($str, $as_array = false)
    {
        $arr = preg_split('/[\s]+/', $str);

        if ($as_array) {
            return $arr;
        }

        if (count($arr) == 1) {
            return $arr[0];
        } else {
            return $arr;
        }
    }

    /**
     * Gets reference schema.
     *
     * This allows to get schema definition to test microdata with it.
     *
     * If no arg is given, then it will get content form <http://schema.rdfs.org/all.json>.
     * If you give a file as arg, it must have the same structure as JSON found into previous link.
     * @param  string $str If given, a local JSON file.
     * @static
     * @access public
     * @return object
     */
    public static function getSchema($str = null)
    {
        if ($str) {
            return json_decode(file_get_contents($str));
        }

        return json_decode(file_get_contents('http://schema.rdfs.org/all.json'));
    }

    /**
     * Instanciate loading document as it is or getting it from URL.
     *
     * @throw \RuntimeException If DOM extension is not loaded.
     * @throw \InvalidArgumentException If URL or content is not a valid string.
     * @throw \InvalidArgumentException If given type does not exist.
     * @param  string  $str  Document's URL or document's content.
     * @param  integer $type One of the class constant to set the way of getting the document
     * @access public
     * @return void
     */
    public function __construct($str, $type = self::AS_URL)
    {
        if (!extension_loaded('dom')) {
            throw new \RuntimeException(__CLASS__.' cannot be used without DOM extension!');
        }

        $this->dom = new \DOMDocument();
        $this->dom->registerNodeClass('DOMElement', '\Malenki\Microdata');
        $this->dom->preserveWhiteSpace = false;

        if (!is_string($str)) {
            throw new \InvalidArgumentException('URL or content must be a valid string!');
        }

        if (!in_array($type, array(self::AS_URL, self::AS_STRING))) {
            throw new \InvalidArgumentException('Bad type value!');
        }

        if ($type == self::AS_URL) {
            @$this->dom->loadHTMLFile($str);
        } else {
            @$this->dom->loadHTML($str);
        }
    }

    /**
     * Get string into UTF-8 format.
     *
     * If the document has other given charset than UTF-8, then this method convert each string to UTF-8.
     *
     * If document's charset is unknown, then UTF-8 is chosen by default.
     *
     * @param  string $str Input string, to convert or no.
     * @access public
     * @return string Converted (or no) string.
     */
    public function getString($str)
    {
        if (!is_null($this->found_charset)) {
            if (!preg_match('/utf[-]{0,1}8/i', $this->found_charset)) {
                if (preg_match('/iso[-_]{0,1}8859[-_]{0,1}/i', $this->found_charset)) {
                    return utf8_encode($str);
                } else {
                    if (extension_loaded('iconv')) {
                        return iconv(strtoupper($this->found_charset), 'UTF-8//IGNORE', $str);
                    } else {
                        trigger_error('Iconv extension is not loaded on your system, resulted strings can be malformed!');

                        return $str;
                    }
                }
            } else {
                return $str;
            }
        }

        return $str;
    }



    /**
     * Enables checking feature.
     *
     * @param  string     $str_uri URI to fetch
     * @access public
     * @return \Microdata
     */
    public function availableChecking($str_uri = null)
    {
        $this->must_check = true;

        if ($str_uri) {
            $this->str_schema = $str_uri;
        } else {
            $this->str_schema = 'http://schema.rdfs.org/all.json';
        }

        return $this;
    }



    /**
     * Extracts microdata's tree
     *
     * @access public
     * @return \stdClass
     */
    public function extract()
    {
        if (!$this->extracted_content) {
            $this->check();

            foreach (array('meta', 'META', 'Meta') as $str_meta) {
                $metas = $this->dom->getElementsByTagName($str_meta);

                foreach ($metas as $m) {
                    foreach (array('charset', 'CHARSET', 'Charset') as $str_attr3) {
                        if ($m->hasAttribute($str_attr3)) {
                            $this->found_charset = trim($m->getAttribute($str_attr3));
                        }
                    }

                    foreach (array('http-equiv', 'HTTP-EQUIV', 'Http-Equiv') as $str_attr) {
                        if ($m->hasAttribute($str_attr)) {
                            if (strtolower(trim($m->getAttribute($str_attr))) == 'content-type') {
                                foreach (array('content', 'CONTENT', 'Content') as $str_attr2) {
                                    if ($m->hasAttribute($str_attr2)) {
                                        $arr_matches = array();
                                        preg_match('/charset=([a-z0-9-]+)/i', $m->getAttribute($str_attr2), $arr_matches);
                                        $this->found_charset = $arr_matches[1];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $xpath = new \DOMXPath($this->dom);
            $colPath = $xpath->query('//*[@itemscope and not(ancestor::*[@itemscope])]');

            $out = new \stdClass();
            $out->count = $colPath->length;
            $out->hasItems = (boolean) $colPath->length;

            $out->items = array();

            foreach ($colPath as $item) {
                $out->items[] = $this->getItems($item, array());
            }

            $this->extracted_content = $out;
        }

        return $this->extracted_content;
    }

    /**
     * Tests whether current selected page has its microdata content extracted.
     *
     * @access public
     * @return boolean
     */
    public function isExtracted()
    {
        return is_object($this->extracted_content);
    }

    public function getItems($item, array $arr_history)
    {
        $out = new \stdClass();
        $out->type = null;
        $out->hasError = false;
        $out->errors = array();

        $arrProvCheck = array();

        $strType = trim($item->getAttribute('itemtype'));
        $strId = trim($item->getAttribute('itemid'));

        if (!empty($strType)) {
            $out->type = self::split($strType);

            // statistical data about types
            if (!is_array($out->type)) {
                $arr_loop = array($out->type);
            } else {
                $arr_loop = $out->type;
            }

            // statistical data + check
            foreach ($arr_loop as $t) {
                // stats
                if (!array_key_exists($t, $this->arr_stats)) {
                    $this->arr_stats[$t] = 1;
                } else {
                    $this->arr_stats[$t] += 1;
                }

                // check
                if ($this->schema) {
                    $type = array_pop(explode('/', $t));

                    if (!isset($this->schema->types->$type)) {
                        $out->hasError = true;
                        $out->errors[] = $type .' schema does not exist!';;
                    } else {
                        $arrProvCheck[] = $type;
                    }
                }
            }
        }

        if (!empty($strId)) {
            $out->id = $strId;
        }

        $out->hasId = isset($out->id);

        $out->properties = array();

        foreach ($item->properties() as $elem) {
            if ($elem->hasAttribute('itemscope')) {
                if (in_array($elem, $arr_history)) {
                    $value = 'ERROR'; //TODO handles that using other way!
                } else {
                    $arr_history[] = $item;
                    $value = $this->getItems($elem, $arr_history);
                    array_pop($arr_history);
                }
            } else {
                $value = $this->getString($elem->textContent);

                $p = $elem->prop();

                if (!count($p)) {
                    $value = null;
                }

                if ($elem->hasAttribute('itemscope')) {
                    $value = $elem;
                }

                $strTag = strtolower($elem->tagName);

                if ($strTag == 'meta') {
                    $value = $this->getString($elem->getAttribute('content'));
                } elseif (in_array($strTag, array('audio', 'embed', 'iframe', 'img', 'source', 'track', 'video'))) {
                    $value = $elem->getAttribute('src');
                } elseif (in_array($strTag, array('a', 'area', 'link'))) {
                    $value = $elem->getAttribute('href');
                } elseif ($strTag == 'object') {
                    $value = $this->getString($elem->getAttribute('data'));
                } elseif ($strTag == 'data') {
                    $value = $this->getString($elem->getAttribute('value'));
                } elseif ($strTag == 'time') {
                    $value = $elem->getAttribute('datetime');
                }

            }

            foreach ($elem->prop() as $prop) {
                if ($this->schema) {
                    foreach ($arrProvCheck as $typeItem) {

                        if(
                            !in_array($prop, $this->schema->types->$typeItem->properties)
                            &&
                            !in_array($prop, $this->schema->types->$typeItem->specific_properties)
                        )
                        {
                            $strError = $prop . ' is not official property of ' . $typeItem . '!';

                            if (!in_array($strError, $out->errors)) {
                                $out->errors[] = $strError;
                            }

                            $out->hasError = true;
                        }
                    }
                }

                // already set? So, it is multiple prop!
                if (isset($out->properties[$prop])) {
                    if (!is_array($out->properties[$prop])) {
                        $out->properties[$prop] = array($out->properties[$prop], $value);
                    } else {
                        $out->properties[$prop][] = $value;
                    }
                } else {
                    $out->properties[$prop] = $value;
                }

            }
        }

        return $out;
    }

    /**
     * hasType
     *
     * @todo implement it!
     *
     * @param  string  $str Type short name or full URL
     * @access public
     * @return boolean
     */
    public function hasType($str)
    {
    }

    /**
     * getType
     *
     * @todo implement it!
     *
     * @param  string $str Type short name or full URL
     * @access public
     * @return mixed
     */
    public function getType($str)
    {
    }

    /**
     * Gets amount of given type.
     *
     * If type was not found, returns 0.
     *
     * @param  string  $str Type full name
     * @access public
     * @return integer
     */
    public function getTypeCount($str)
    {
        if (!array_key_exists($str, $this->arr_stats)) {
            return 0;
        }

        if (!$this->isExtracted()) {
            $this->extract();
        }

        return $this->arr_stats[$str];
    }

    /**
     * Get amount of all types found into the document.
     *
     * Returns values as an array. Keys are type's names, values are amounts.
     *
     * @access public
     * @return array
     */
    public function getAllTypeCount()
    {
        if (!$this->isExtracted()) {
            $this->extract();
        }

        return $this->arr_stats;
    }



    public function prop()
    {
        $strProp = trim($this->getAttribute('itemprop'));

        if (strlen($strProp)) {
            return self::split($strProp, true);
        }

        return array();
    }



    public function properties()
    {
        $arr_out = array();

        if ($this->hasAttribute('itemscope')) {
            $xpath = new \DOMXPath($this->ownerDocument);
            $arr_to_traverse = array($this);
            $arr_ref = array();

            $str_ref = trim($this->getAttribute('itemref'));

            if (!empty($str_ref)) {
                $arr_ref = self::split($str_ref);
            }

            foreach ($arr_ref as $ref) {
                $children = $xpath->query('//*[@id="'.$ref.'"]');

                foreach ($children as $child) {
                    $this->traverse($child, $arr_to_traverse, $arr_out, $this);
                }
            }

            while (count($arr_to_traverse)) {
                $this->traverse($arr_to_traverse[0], $arr_to_traverse, $arr_out, $this);
            }
        }

        return $arr_out;
    }



    protected function traverse($node, &$arr_to_traverse, &$arr_prop, $root)
    {
        foreach ($arr_to_traverse as $i => $elem) {
            if ($elem->isSameNode($node)) {
                unset($arr_to_traverse[$i]);
            }
        }


        if (!$root->isSameNode($node)) {
            $names = $node->prop();

            if (count($names)) {
                $arr_prop[] = $node;
            }

            if ($node->hasAttribute('itemscope')) {
                return;
            }
        }

        $xpath = new \DOMXPath($this->ownerDocument);
        $children = $xpath->query($node->getNodePath() . '/*');

        foreach ($children as $child) {
            $this->traverse($child, $arr_to_traverse, $arr_prop, $root);
        }
    }

    protected function check()
    {
        if ($this->must_check) {
            $this->schema = self::getSchema($this->str_schema);
        }
    }

    /**
     * In string context, returns JSON microdata tree.
     *
     * @access public
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->extract());
    }
}
