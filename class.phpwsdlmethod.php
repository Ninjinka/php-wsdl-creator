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

PhpWsdl::RegisterHook('InterpretKeywordpw_omitfncHook', 'internal', 'PhpWsdlMethod::InterpretOmit');
PhpWsdl::RegisterHook('InterpretKeywordignoreHook', 'internal', 'PhpWsdlMethod::InterpretOmit');
PhpWsdl::RegisterHook('CreateObjectHook', 'internalmethod', 'PhpWsdlMethod::CreateMethodObject');

/**
 * A method definition object
 *
 * @author Andreas M�ller-Saala, wan24.de
 */
class PhpWsdlMethod extends PhpWsdlObject
{
    /**
     * A new method is global per default?
     *
     * @var boolean
     */
    public static $IsGlobalDefault = false;
    /**
     * The name of the default exception type
     *
     * @var string
     */
    public static $DefaultException = null;
    /**
     * A list of parameters
     *
     * @var PhpWsdlParam[]
     */
    public $Param = array();
    /**
     * The return value
     *
     * @var PhpWsdlParam
     */
    public $Return = null;
    /**
     * A global method?
     *
     * @var boolean
     */
    public $IsGlobal = false;
    /**
     * The class name or the object that contains this method
     *
     * @var string|object
     */
    public $Class = null;
    /**
     * The exception type name for this method
     * Tip: Set this to an empty string to override the default exception type name
     *
     * @var string
     */
    public $Exception = null;

    /**
     * Constructor
     *
     * @param string $name The name
     * @param PhpWsdlParam[] $param Optional the list of parameters (default: NULL)
     * @param PhpWsdlParam $return Optional the return value (default: NULL)
     * @param array $settings Optional the settings hash array (default: NULL)
     */
    public function __construct($name, $param = null, $return = null, $settings = null)
    {
        PhpWsdl::Debug('New method ' . $name);
        parent::PhpWsdlObject($name, $settings);
        if (!is_null($param))
            $this->Param = $param;
        $this->Return = $return;
        $this->IsGlobal = self::$IsGlobalDefault;
        $this->Exception = self::$DefaultException;
        if (!is_null($settings)) {
            if (isset($settings['global']))
                $this->IsGlobal = $settings['global'] == 'true' || $settings['global'] == '1';
            if (isset($settings['class']))
                $this->Class = $settings['class'];
            if (isset($settings['exception']))
                $this->Exception = $settings['exception'];
        }
    }

    /**
     * Interpret omit keyword
     *
     * @param array $data The parser data
     * @return boolean Response
     */
    public static function InterpretOmit(array &$data):bool
    {
        PhpWsdl::Debug('Interpret omitfnc/ignore');
        $data['omit'] = true;
        return true;
    }

    /**
     * Create method object
     *
     * @param array $data The parser data
     * @return boolean Response
     */
    public static function CreateMethodObject($data)
    {
        if (!is_null($data['obj']))
            return true;
        if ($data['method'] == '')
            return true;
        if (!is_null($data['type']))
            return true;

        PhpWsdl::Debug('Add method ' . $data['method']);
        $server = $data['server'];
        if (!$server) {
            throw(new Exception('Invalid configuration'));
        }
        if ($server && !is_null($server->GetMethod($data['method']))) {
            PhpWsdl::Debug('WARNING: Double method detected!');
            return true;
        }

        if ($server && $server->ParseDocs)
            if (!is_null($data['docs']))
                $data['settings']['docs'] = $data['docs'];
        $data['obj'] = new PhpWsdlMethod($data['method'], $data['param'], $data['return'], $data['settings']);
        $data['settings'] = array();
        if ($server)
            $server->Methods[] = $data['obj'];
        return true;
    }

    /**
     * Create the port type WSDL
     *
     * @param PhpWsdl $pw The PhpWsdl object
     * @return string The WSDL
     */
    public function CreatePortType($pw)
    {
        PhpWsdl::Debug('Create WSDL port type for method ' . $this->Name);
        $res = array();
        $res[] = '<wsdl:operation name="' . $this->Name . '"';
        $o = sizeof($res) - 1;
        $pLen = sizeof($this->Param);
        if ($pLen > 1) {
            $temp = array();
            $i = -1;
            while (++$i < $pLen)
                $temp[] = $this->Param[$i]->Name;
            $res[$o] .= ' parameterOrder="' . implode(' ', $temp) . '"';
        }
        $res[$o] .= '>';
        if ($pw->IncludeDocs && !$pw->Optimize && !is_null($this->Docs))
            $res[] = '<wsdl:documentation><![CDATA[' . $this->Docs . ']]></wsdl:documentation>';
        $res[] = '<wsdl:input message="tns:' . $this->Name . 'SoapIn" />';
        $res[] = '<wsdl:output message="tns:' . $this->Name . 'SoapOut" />';
        $ex = $this->GetExceptionTypeName();
        if ($ex != null)
            $res[] = '<wsdl:fault message="tns:' . $this->Name . 'Exception" />';
        $res[] = '</wsdl:operation>';
        return implode('', $res);
    }

    /**
     * Get the exception type name
     *
     * @return string The type name or NULL
     */
    public function GetExceptionTypeName()
    {
        return (!is_null($this->Exception))
            ? $this->Exception
            : self::$DefaultException;
    }

    /**
     * Create the binding WSDL
     *
     * @param PhpWsdl $pw The PhpWsdl object
     * @return string The WSDL
     */
    public function CreateBinding($pw)
    {
        PhpWsdl::Debug('Create WSDL binding for method ' . $this->Name);
        $res = array();
        $res[] = '<wsdl:operation name="' . $this->Name . '">';
        $res[] = '<soap:operation soapAction="' . $pw->NameSpace . $this->Name . '" />';
        $res[] = '<wsdl:input>';
        $res[] = '<soap:body use="encoded" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" namespace="' . $pw->NameSpace . '"';
        $pLen = sizeof($this->Param);
        if ($pLen > 0) {
            $temp = array();
            $i = -1;
            while (++$i < $pLen)
                $temp[] = $this->Param[$i]->Name;
            $res[sizeof($res) - 1] .= ' parts="' . implode(' ', $temp) . '"';
        }
        $res[sizeof($res) - 1] .= ' />';
        $res[] = '</wsdl:input>';
        $res[] = '<wsdl:output>';
        $res[] = '<soap:body use="encoded" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" namespace="' . $pw->NameSpace . '"';
        if (!is_null($this->Return))
            $res[sizeof($res) - 1] .= ' parts="' . $this->Return->Name . '"';
        $res[sizeof($res) - 1] .= ' />';
        $res[] = '</wsdl:output>';
        $ex = $this->GetExceptionTypeName();
        if ($ex != null) {
            $res[] = '<wsdl:fault name="' . $this->Name . 'Exception">';
            $res[] = '<soap:fault name="' . $this->Name . 'Exception" use="encoded" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/" namespace="' . $pw->NameSpace . '" />';
            $res[] = '</wsdl:fault>';
        }
        $res[] = '</wsdl:operation>';
        return implode('', $res);
    }

    /**
     * Create the input/output messages WSDL
     *
     * @param PhpWsdl $pw The PhpWsdl object
     * @return string The WSDL
     */
    public function CreateMessages($pw)
    {
        PhpWsdl::Debug('Create WSDL message for method ' . $this->Name);
        $pLen = sizeof($this->Param);
        $res = array();
        // Request
        if ($pLen < 1) {
            $res[] = '<wsdl:message name="' . $this->Name . 'SoapIn" />';
        } else {
            $res[] = '<wsdl:message name="' . $this->Name . 'SoapIn">';
            $i = -1;
            while (++$i < $pLen)
                $res[] = $this->Param[$i]->CreatePart($pw);
            $res[] = '</wsdl:message>';
        }
        // Response
        if (is_null($this->Return)) {
            $res[] = '<wsdl:message name="' . $this->Name . 'SoapOut" />';
        } else {
            $res[] = '<wsdl:message name="' . $this->Name . 'SoapOut">';
            $res[] = $this->Return->CreatePart($pw);
            $res[] = '</wsdl:message>';
        }
        // Exception
        $ex = $this->GetExceptionTypeName();
        if ($ex != null) {
            $res[] = '<wsdl:message name="' . $this->Name . 'Exception">';
            $res[] = '<wsdl:part name="fault" type="tns:' . $ex . '" />';
            $res[] = '</wsdl:message>';
        }
        return implode('', $res);
    }

    /**
     * Find a parameter of this method
     *
     * @param string $name The parameter name
     * @return PhpWsdlParam The parameter or NULL, if not found
     */
    public function GetParam($name)
    {
        PhpWsdl::Debug('Find parameter ' . $name);
        $i = -1;
        $len = sizeof($this->Param);
        while (++$i < $len)
            if ($this->Param[$i]->Name == $name) {
                PhpWsdl::Debug('Found parameter at index ' . $i);
                return $this->Param[$i];
            }
        return null;
    }

    /**
     * Create HTML docs
     *
     * @param array $data Some data
     */
    public function CreateMethodHtml($data)
    {
        PhpWsdl::Debug('CreateMethodHtml for ' . $data['method']->Name);
        $res =& $data['res'];
        $m =& $data['method'];
        $res[] = '<h3>' . $m->Name . '</h3>';
        $res[] = '<a name="' . $m->Name . '"></a>';
        $res[] = '<p class="pre">';
        $o = sizeof($res) - 1;
        if (!is_null($m->Return)) {
            $type = $m->Return->Type;
            if (in_array($type, PhpWsdl::$BasicTypes)) {
                $res[$o] .= '<span class="blue">' . $type . '</span>';
            } else {
                $res[$o] .= '<a href="#' . $type . '"><span class="lightBlue">' . $type . '</span></a>';
            }
        } else {
            $res[$o] .= 'void';
        }
        $res[$o] .= ' <span class="bold">' . $m->Name . '</span> (';
        $pLen = sizeof($m->Param);
        $spacer = '';
        if ($pLen > 1) {
            $res[$o] .= '<br>';
            $spacer = '&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        $hasDocs = false;
        if ($pLen > 0) {
            $j = -1;
            while (++$j < $pLen) {
                $p = $m->Param[$j];
                if (in_array($p->Type, PhpWsdl::$BasicTypes)) {
                    $res[] = $spacer . '<span class="blue">' . $p->Type . '</span> <span class="bold">' . $p->Name . '</span>';
                } else {
                    $res[] = $spacer . '<a href="#' . $p->Type . '"><span class="lightBlue">' . $p->Type . '</span></a> <span class="bold">' . $p->Name . '</span>';
                }
                $o = sizeof($res) - 1;
                if ($j < $pLen - 1)
                    $res[$o] .= ', ';
                if ($pLen > 1)
                    $res[$o] .= '<br>';
                if (!$hasDocs)
                    if (!is_null($p->Docs))
                        $hasDocs = true;
            }
        }
        $res[] .= ')</p>';
        // Method documentation
        if (!is_null($m->Docs))
            $res[] = '<p>' . nl2br(htmlentities($m->Docs)) . '</p>';
        // Parameters documentation
        if ($hasDocs) {
            $res[] = '<ul>';
            $j = -1;
            while (++$j < $pLen)
                $m->Param[$j]->CreateParamHtml(array_merge(
                        $data,
                        array(
                            'param' => $m->Param[$j]
                        )
                    )
                );
            $res[] = '</ul>';
        }
        // Return value documentation
        if (!is_null($m->Return) && !is_null($m->Return->Docs))
            $m->Return->CreateReturnHtml($data);
        PhpWsdl::CallHook(
            'CreateMethodHtmlHook',
            $data
        );
    }

    /**
     * Create method PHP
     *
     * @param array $data The event data
     */
    public function CreateMethodPhp($data)
    {
        $server = $data['server'];
        $res =& $data['res'];
        $res[] = "\t/**";
        if (!is_null($this->Docs)) {
            $res[] = "\t * " . implode("\n\t * ", explode("\n", $this->Docs));
            $res[] = "\t *";
        }
        $param = array();
        $i = -1;
        $pLen = sizeof($this->Param);
        while (++$i < $pLen) {
            $p = $this->Param[$i];
            $param[] = '$' . $p->Name;
            $res[] = "\t * @param " . $p->Type . " \$" . $p->Name . ((!is_null($p->Docs)) ? " " . implode("\n\t * ", explode("\n", $p->Docs)) : "");
        }
        if (!is_null($this->Return)) {
            $res[] = "\t * @return " . $this->Return->Type . ((!is_null($this->Return->Docs)) ? " " . implode("\n\t * ", explode("\n", $this->Return->Docs)) : "");
        }
        $res[] = "\t */";
        $res[] = "\tpublic function " . $this->Name . "(" . implode(',', $param) . "){";
        if (PhpWsdl::CallHook(
            'CreateMethodPhpHook',
            array_merge(
                $data,
                array(
                    'method' => $this
                )
            )
        )
        ) {
            $res[] = "\t\treturn self::_Call('" . $this->Name . "',Array(";
            if ($pLen > 0)
                $res[] = "\t\t\t" . implode(",\n\t\t\t", $param);
            $res[] = "\t\t));";
        }
        $res[] = "\t}";
    }
}
