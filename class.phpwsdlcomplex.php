<?php

/*
PhpWsdl - Generate WSDL from PHP
Copyright (C) 2011  Andreas M�ller-Saala, wan24.de

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, see <http://www.gnu.org/licenses/>.
*/

if (basename($_SERVER['SCRIPT_FILENAME']) == basename(__FILE__))
    exit;

PhpWsdl::RegisterHook('InterpretKeywordpw_complexHook', 'internal', 'PhpWsdlComplex::InterpretComplex');
PhpWsdl::RegisterHook('CreateObjectHook', 'internalcomplex', 'PhpWsdlComplex::CreateComplexTypeObject');

/**
 * This class creates complex types (classes or arrays)
 *
 * @author Andreas M�ller-Saala, wan24.de
 */
class PhpWsdlComplex extends PhpWsdlObject
{
    /**
     * Disable definition of arrays with the "Array" postfix in the type name?
     *
     * @var boolean
     */
    public static $DisableArrayPostfix = false;
    /**
     * Create a PHP constructor that requires all parameters per default?
     *
     * @var boolean
     */
    public static $DefaultEnablePhpConstructor = false;
    /**
     * The array type
     *
     * @var string
     */
    public $Type = null;
    /**
     * Name of the complex type to inherit from
     *
     * @var string
     */
    public $Inherit = null;
    /**
     * A list of elements, if this type is a class
     *
     * @var PhpWsdlElement[]
     */
    public $Elements;
    /**
     * Is this type an array?
     *
     * @var boolean
     */
    public $IsArray;
    /**
     * Enable creating a PHP constructor that requires all parameters?
     *
     * @var boolean
     */
    public $EnablePhpConstructor = false;

    /**
     * Constructor
     *
     * @param string $name The name
     * @param PhpWsdlElement[] $el Optional a list of elements
     * @param array $settings Optional the settings hash array (default: NULL)
     */
    public function __construct($name, $el = array(), $settings = null)
    {
        PhpWsdl::Debug('New complex type ' . $name);
        parent::PhpWsdlObject($name, $settings);
        if (!self::$DisableArrayPostfix) {
            $this->IsArray = str_ends_with($name, 'Array');
            if ($this->IsArray)
                $this->Type = substr($this->Name, 0, strlen($this->Name) - 5);
        }
        $this->Elements = $el;
        $this->EnablePhpConstructor = self::$DefaultEnablePhpConstructor;
        if (!is_null($settings)) {
            if (isset($settings['isarray']))
                $this->IsArray = $settings['isarray'];
            if (isset($settings['phpconstructor']))
                $this->EnablePhpConstructor = $settings['phpconstructor'] == '1' || $settings['phpconstructor'] == 'true';
            if (isset($settings['type']))
                $this->IsArray = $settings['type'];
            if (isset($settings['inherit']))
                $this->Inherit = $settings['inherit'];
        }
    }

    /**
     * Interpret a complex type
     *
     * @param array $data The parser data
     * @return boolean Response
     */
    public static function InterpretComplex($data)
    {
        $info = explode(' ', $data['keyword'][1], 3);
        if (sizeof($info) < 1)
            return true;
        $server = $data['server'];
        $name = $info[0];
        PhpWsdl::Debug('Interpret complex type "' . $name . '"');
        $type = null;
        $docs = null;
        if (strpos($name, '[]') > -1) {
            if (sizeof($info) < 2) {
                PhpWsdl::Debug('WARNING: Invalid array definition!');
                return true;
            }
            $name = substr($name, 0, strlen($name) - 2);
            if (!is_null($server->GetType($name))) {
                PhpWsdl::Debug('WARNING: Double type detected!');
                return true;
            }
            $type = $info[1];
            if ($server->ParseDocs)
                if (sizeof($info) > 2)
                    $docs = $info[2];
            PhpWsdl::Debug('Array "' . $name . '" type of "' . $type . '" definition');
        } else {
            if (!is_null($server->GetType($name))) {
                PhpWsdl::Debug('WARNING: Double type detected!');
                return true;
            }
            if (!self::$DisableArrayPostfix && str_ends_with($name, 'Array')) {
                $type = substr($name, 0, strlen($name) - 5);
                PhpWsdl::Debug('Array "' . $name . '" type of "' . $type . '" definition');
            } else {
                PhpWsdl::Debug('Complex type definition');
            }
            if ($server->ParseDocs) {
                $temp = sizeof($info);
                if ($temp > 1)
                    $docs = ($temp > 2) ? $info[1] . ' ' . $info[2] : $info[1];
            }
        }
        $data['type'] = array(
            'id' => 'complex',
            'name' => $name,
            'type' => $type,
            'docs' => $docs
        );
        return false;
    }

    /**
     * Create complex type object
     *
     * @param array $data The parser data
     * @return boolean Response
     */
    public static function CreateComplexTypeObject($data)
    {
        if ($data['method'] != '')
            return true;
        if (!is_null($data['obj']))
            return true;
        if (!is_array($data['type']))
            return true;
        if (!isset($data['type']['id']))
            return true;
        if ($data['type']['id'] != 'complex')
            return true;
        if (!is_null($data['docs'])) {
            $data['settings']['docs'] = $data['docs'];
        } else {
            $data['settings']['docs'] = $data['type']['docs'];
        }
        PhpWsdl::Debug('Add complex type ' . $data['type']['name']);
        $data['settings']['isarray'] = !is_null($data['type']['type']);
        $data['obj'] = new PhpWsdlComplex($data['type']['name'], $data['elements'], $data['settings']);
        $data['obj']->Type = $data['type']['type'];
        $data['settings'] = array();
        $data['server']->Types[] = $data['obj'];
        return true;
    }

    /**
     * Create WSDL for the type
     *
     * @param PhpWsdl $pw The PhpWsdl object
     * @return string The WSDL
     */
    public function CreateType($pw)
    {
        $res = array();
        $res[] = '<s:complexType name="' . $this->Name . '">';
        if ($pw->IncludeDocs && !$pw->Optimize && !is_null($this->Docs)) {
            $res[] = '<s:annotation>';
            $res[] = '<s:documentation><![CDATA[' . $this->Docs . ']]></s:documentation>';
            $res[] = '</s:annotation>';
        }
        if (!$this->IsArray) {
            PhpWsdl::Debug('Create WSDL definition for type ' . $this->Name . ' as type');
            if (is_null($this->Inherit)) {
                $res[] = '<s:sequence>';
                $i = -1;
                $len = sizeof($this->Elements);
                while (++$i < $len)
                    $res[] = $this->Elements[$i]->CreateElement($pw);
                $res[] = '</s:sequence>';
            } else {
                PhpWsdl::Debug('Inherit from "' . $this->Inherit . '"');
                $res[] = '<s:complexContent>';
                $res[] = '<s:extension base="tns:' . $this->Inherit . '">';
                $res[] = '<s:sequence>';
                $i = -1;
                $len = sizeof($this->Elements);
                while (++$i < $len)
                    $res[] = $this->Elements[$i]->CreateElement($pw);
                $res[] = '</s:sequence>';
                $res[] = '</s:extension>';
                $res[] = '</s:complexContent>';
            }
        } else {
            PhpWsdl::Debug('Create WSDL definition for type ' . $this->Name . ' as array');
            $res[] = '<s:complexContent>';
            $res[] = '<s:restriction base="soapenc:Array">';
            $res[] = '<s:attribute ref="soapenc:arrayType" wsdl:arrayType="' . PhpWsdl::TranslateType($this->Type) . '[]" />';
            $res[] = '</s:restriction>';
            $res[] = '</s:complexContent>';
        }
        $res[] = '</s:complexType>';
        return implode('', $res);
    }

    /**
     * Find an element within this type
     *
     * @param string $name The name
     * @return PhpWsdlElement The element or NULL, if not found
     */
    public function GetElement($name)
    {
        PhpWsdl::Debug('Find element ' . $name);
        $i = -1;
        $len = sizeof($this->Elements);
        while (++$i < $len)
            if ($this->Elements[$i]->Name == $name) {
                PhpWsdl::Debug('Found element at index ' . $i);
                return $this->Elements[$i];
            }
        return null;
    }

    /**
     * Create the HTML documentation for a complex type
     *
     * @param array $data
     */
    public function CreateTypeHtml($data)
    {
        PhpWsdl::Debug('CreateTypeHtml for ' . $data['type']->Name);
        $server = $data['server'];
        $res =& $data['res'];
        $t =& $data['type'];
        $res[] = '<h3>' . $t->Name . '</h3>';
        $res[] = '<a name="' . $t->Name . '"></a>';
        if (!is_null($t->Inherit))
            $res[] = '<p>This type inherits all properties from <a href="#' . $t->Inherit . '"><span class="lightBlue">' . $t->Inherit . '</span></a>.</p>';
        $eLen = sizeof($t->Elements);
        if ($t->IsArray) {
            // Array type
            $res[] = '<p>This is an array type of <span class="pre">';
            $o = sizeof($res) - 1;
            if (in_array($t->Type, PhpWsdl::$BasicTypes)) {
                $res[$o] .= '<span class="blue">' . $t->Type . '</span>';
            } else {
                $res[$o] .= '<a href="#' . $t->Type . '"><span class="lightBlue">' . $t->Type . '</span></a>';
            }
            $res[$o] .= '</span>.</p>';
            if (!is_null($t->Docs))
                $res[] = '<p>' . nl2br(htmlentities($t->Docs)) . '</p>';
        } else if ($eLen > 0) {
            // Complex type with elements
            if (!is_null($t->Docs))
                $res[] = '<p>' . nl2br(htmlentities($t->Docs)) . '</p>';
            $res[] = '<ul class="pre">';
            $j = -1;
            while (++$j < $eLen)
                $t->Elements[$j]->CreateElementHtml(array_merge(
                    $data,
                    array(
                        'element' => $t->Elements[$j]
                    )
                ));
            $res[] = '</ul>';
        } else {
            // Complex type without elements
            $res[] = '<p>This type has no elements.</p>';
        }
        PhpWsdl::CallHook(
            'CreateTypeHtmlHook',
            $data
        );
    }

    /**
     * Create type PHP code
     *
     * @param array $data The event data
     */
    public function CreateTypePhp($data)
    {
        if ($this->IsArray)
            return;
        $server = $data['server'];
        $res =& $data['res'];
        $res[] = "/**";
        if (!is_null($this->Docs)) {
            $res[] = " * " . implode("\n * ", explode("\n", $this->Docs));
            $res[] = " *";
        }
        $temp = array();
        $i = -1;
        $eLen = sizeof($this->Elements);
        while (++$i < $eLen) {
            $e = $this->Elements[$i];
            $temp[] = '$' . $e->Name;
            $res[] = " * @pw_element " . $e->Type . " \$" . $e->Name . ((!is_null($e->Docs)) ? ' ' . $e->Docs : '');
        }
        $res[] = " * @pw_complex " . $this->Name;
        $res[] = " */";
        $res[] = "class " . $this->Name . ((is_null($this->Inherit)) ? '' : ' extends ' . $this->Inherit) . "{";
        if ($eLen > 0 && $this->EnablePhpConstructor) {
            $res[] = "\t/**";
            $res[] = "\t * Constructor with parameters (all required!)";
            $res[] = "\t *";
            $i = -1;
            while (++$i < $eLen) {
                $e = $this->Elements[$i];
                $res[] = "\t * @param " . $e->Type . " " . $temp[$i] . ((!is_null($e->Docs)) ? ' ' . $e->Docs : '');
            }
            if (!is_null($this->Inherit)) {
                $it = $server->GetType($this->Inherit);
                if ($it->EnablePhpConstructor) {
                    $i = -1;
                    $tLen = sizeof($it->Elements);
                    while (++$i < $tLen) {
                        $e = $it->Elements[$i];
                        $tempb[] = '$' . $e->Name;
                        $res[] = " * @param " . $e->Type . " \$" . $e->Name . ((!is_null($e->Docs)) ? ' ' . $e->Docs : '');
                    }
                }
            }
            $res[] = "\t */";
            $res[] = "\tpublic function " . $this->Name . "(" . implode(',', $temp) . "){";
            if (!is_null($this->Inherit) && $it->EnablePhpConstructor)
                $res[] = "\t\tparent::" . $this->Inherit . "(" . implode(',', $tempb) . ");";
            $i = -1;
            while (++$i < $eLen)
                $res[] = "\t\t\$this->" . $this->Elements[$i]->Name . "=" . $temp[$i];
            $res[] = "\t}";
        }
        $i = -1;
        while (++$i < $eLen) {
            $e = $this->Elements[$i];
            $res[] = "\t/**";
            if (!is_null($e->Docs)) {
                $res[] = "\t * " . implode("\n\t * ", explode("\n", $e->Docs));
                $res[] = "\t *";
            }
            $res[] = "\t * @var " . $e->Type;
            $res[] = "\t */";
            $res[] = "\tpublic \$" . $this->Elements[$i]->Name . ";";
        }
        $res[] = "}";
    }
}
