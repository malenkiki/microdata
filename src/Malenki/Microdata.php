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
 *     $md = new Microdata($url);
 *     echo $md; // JSON
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

    protected $dom = null;
    protected $extracted_content = false;
    protected $arr_stats = array();


    /**
     * Helper to split content into array or string if one element is returned.
     * 
     * @param string $str The string to split
     * @param boolean $as_array If true, always returns array.
     * @static
     * @access protected
     * @return mixed An array if get more than one elements else it is a string
     */
    protected static function split($str, $as_array = false)
    {
        $arr = preg_split('/[\s]+/', $str);

        if($as_array)
        {
            return $arr;
        }

        if(count($arr) == 1)
        {
            return $arr[0];
        }
        else
        {
            return $arr;
        }
    }



    /**
     * Instanciate loading document as it is or getting it from URL. 
     * 
     * @param string $str Document's URL or document's content.
     * @param integer $type One of the class constant to set the way of getting the document
     * @access public
     * @return void
     */
    public function __construct($str, $type = self::AS_URL)
    {
        $this->dom = new \DOMDocument();
        $this->dom->registerNodeClass('DOMElement', '\Malenki\Microdata');
        $this->dom->preserveWhiteSpace = false;

        if(!is_string($str))
        {
            throw new \InvalidArgumentException('URL or content must be a valid string!');
        }

        if(!in_array($type, array(self::AS_URL, self::AS_STRING)))
        {
            throw new \InvalidArgumentException('Bad type value!');
        }

        if($type == self::AS_URL)
        {
            @$this->dom->loadHTMLFile($str);
        }
        else
        {
            @$this->dom->loadHTML($str);
        }
    }



    /**
     * Extracts microdata's tree
     * 
     * @access public
     * @return \stdClass
     */
    public function extract()
    {
        if(!$this->extracted_content)
        {
            $out = new \stdClass();
            $out->items = array();
            $xpath = new \DOMXPath($this->dom);
            $colPath = $xpath->query('//*[@itemscope and not(@itemprop)]');
            $out->count = $colPath->length;
            $out->hasItems = (boolean) $colPath->length;

            foreach($colPath as $item)
            {
                $out->items[] = $this->getItems($item, array());
            }

            $this->extracted_content = $out;
        }

        return $this->extracted_content;
    }



    public function isExtracted()
    {
        return is_object($this->extracted_content);
    }




    public function getItems($item, array $arr_history)
    {
        $out = new \stdClass();
        $out->properties = array();

        $strType = trim($item->getAttribute('itemtype'));
        $strId = trim($item->getAttribute('itemid'));

        var_dump($strType);
        if (!empty($strType))
        {
            $out->type = self::split($strType);

            // statistical data about types
            if(!is_array($out->type))
            {
                $arr_loop = array($out->type);
            }
            else
            {
                $arr_loop = $out->type;
            }

            foreach($arr_loop as $t)
            {
                if(!array_key_exists($t, $this->arr_stats))
                {
                    $this->arr_stats[$t] = 1;
                }
                else
                {
                    $this->arr_stats[$t] += 1;
                }
            }
        }


        if (!empty($strId))
        {
            $out->id = $strId;
        }

        $out->hasId = isset($out->id);

        foreach ($item->properties() as $elem)
        {
            if ($elem->hasAttribute('itemscope'))
            {
                if (in_array($elem, $arr_history)) {
                    $value = 'ERROR';
                }
                else {
                    $arr_history[] = $item;
                    $value = $this->getItems($elem, $arr_history);
                    array_pop($arr_history);
                }
            }
            else
            {
                $value = $elem->textContent;

                $p = $elem->prop();

                if (!count($p))
                {
                    $value = null;
                }
                
                if ($elem->hasAttribute('itemscope'))
                {
                    $value = $elem;
                }

                $strTag = strtolower($elem->tagName);
                
                if($strTag == 'meta')
                {
                    $value = $elem->getAttribute('content');
                }
                elseif(in_array($strTag, array('audio', 'embed', 'iframe', 'img', 'source', 'track', 'video')))
                {
                    $value = $elem->getAttribute('src');
                }
                elseif(in_array($strTag, array('a', 'area', 'link')))
                {
                    $value = $elem->getAttribute('href');
                }
                elseif($strTag == 'object')
                {
                    $value = $elem->getAttribute('data');
                }
                elseif($strTag == 'data')
                {
                    $value = $elem->getAttribute('value');
                }
                elseif($strTag == 'time')
                {
                    $value = $elem->getAttribute('datetime');
                }
                
            }

            foreach ($elem->prop() as $prop)
            {
                $out->properties[$prop] = $value;
            }
        }


        return $out;
    }
    


    /**
     * hasType 
     *
     * @todo implement it!
     * 
     * @param string $str Type short name or full URL
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
     * @param string $str Type short name or full URL
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
     * @param string $str Type full name
     * @access public
     * @return integer
     */
    public function getTypeCount($str)
    {
        if(!array_key_exists($str, $this->arr_stats))
        {
            return 0;
        }

        if(!$this->isExtracted())
        {
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
        if(!$this->isExtracted())
        {
            $this->extract();
        }

        return $this->arr_stats;
    }



    public function prop()
    {
        $strProp = trim($this->getAttribute('itemprop'));

        if(strlen($strProp))
        {
            return self::split($strProp, true);
        }

        return array();
    }



    public function properties()
    {
        $arr_out = array();

        if ($this->hasAttribute('itemscope'))
        {
            $xpath = new \DOMXPath($this->ownerDocument);
            $arr_to_traverse = array($this);
            $arr_ref = array();
            
            $str_ref = trim($this->getAttribute('itemref'));

            if(!empty($str_ref))
            {
                $arr_ref = self::split($str_ref);
            }

            foreach ($arr_ref as $ref)
            {
                $children = $xpath->query('//*[@id="'.$ref.'"]');
            
                foreach($children as $child)
                {
                    $this->traverse($child, $arr_to_traverse, $arr_out, $this);
                }
            }

            while (count($arr_to_traverse))
            {
                $this->traverse($arr_to_traverse[0], $arr_to_traverse, $arr_out, $this);
            }
        }

        return $arr_out;
    }



    protected function traverse($node, &$arr_to_traverse, &$arr_prop, $root)
    {
        foreach ($arr_to_traverse as $i => $elem)
        {
            if ($elem->isSameNode($node))
            {
                unset($arr_to_traverse[$i]);
            }
        }


        if (!$root->isSameNode($node))
        {
            $names = $node->prop();

            if (count($names))
            {
                $arr_prop[] = $node;
            }

            if ($node->hasAttribute('itemscope'))
            {
                return;
            }
        }

        $xpath = new \DOMXPath($this->ownerDocument);
        $children = $xpath->query($node->getNodePath() . '/*');

        foreach ($children as $child)
        {
            $this->traverse($child, $arr_to_traverse, $arr_prop, $root);
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
