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
 * Microdata 
 * 
 * This work is greatly taken from the work of [Philip Jägenstedt](http://gitorious.org/microdatajs/microdatajs) and [Lin Clark](http://github.com/linclark/MicrodataPHP).
 *
 * @author Michel Petit <petit.michel@gmail.com> 
 * @license MIT
 */
class Microdata extends \DOMElement
{
    protected $dom = null;



    public function __construct($str_url)
    {
        $this->dom = new \DOMDocument();
        $this->dom->registerNodeClass('DOMElement', '\Malenki\Microdata');
        $this->dom->preserveWhiteSpace = false;
        @$this->dom->loadHTMLFile($str_url);
    }



    public function extract()
    {
        $out = new \stdClass();
        $out->items = array();
        $xpath = new \DOMXPath($this->dom);
        $arrPath = $xpath->query('//*[@itemscope and not(@itemprop)]');
        
        foreach($arrPath as $item)
        {
            $out->items[] = $this->getItems($item, array());
        }

        return $out;
    }



    public function getItems($item, array $arr_history)
    {
        $out = new \stdClass();
        $out->type = null;
        $out->id = null;
        $out->properties = array();

        $strType = trim($item->getAttribute('itemtype'));
        $strId = trim($item->getAttribute('itemid'));

        if (!empty($strType))
        {
            $out->type = explode(' ', $strType);
        }


        if (!empty($strId))
        {
            $out->id = $strId;
        }



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
                if ($elem->hasAttribute('itemscope')) {
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
                $out->properties[$prop][] = $value;
            }
        }

        return $out;
    }
    


    public function prop()
    {
        $strProp = trim($this->getAttribute('itemprop'));

        if(strlen($strProp))
        {
            // TODO: use better splitter than this… Use Regex!
            return explode(' ', $strProp);
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
                // TODO: change explode way…
                $arr_ref = explode(' ', $str_ref);
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


    public function __toString()
    {
        return json_encode($this->extract());
    }
}
